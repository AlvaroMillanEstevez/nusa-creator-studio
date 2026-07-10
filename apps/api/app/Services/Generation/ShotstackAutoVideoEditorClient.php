<?php

namespace App\Services\Generation;

use App\Exceptions\AutoVideoEditor\AutoVideoEditorProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShotstackAutoVideoEditorClient
{
    /**
     * @param  array<string, mixed>  $edit
     * @return array<string, mixed>
     */
    public function submitRender(array $edit, array $context = []): array
    {
        $this->ensureApiKey();

        $startedAt = microtime(true);

        try {
            $response = $this->http()
                ->timeout(60)
                ->post('/render', $edit);
        } catch (ConnectionException $exception) {
            throw AutoVideoEditorProviderException::requestFailed(
                'shotstack_render_submit_failed',
                $context + ['stage' => 'submit', 'provider' => 'shotstack'],
                $exception,
            );
        }

        if (! $response->successful()) {
            $this->logFailure('Shotstack render submit failed.', $response, $context + ['stage' => 'submit']);

            throw AutoVideoEditorProviderException::requestFailed(
                'shotstack_render_submit_failed',
                $this->responseContext($response, $context + ['stage' => 'submit']),
            );
        }

        $body = $this->json($response, $context + ['stage' => 'submit']);
        $renderId = data_get($body, 'response.id') ?? data_get($body, 'id');

        if (! is_string($renderId) || trim($renderId) === '') {
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', $context + [
                'stage' => 'submit',
                'http_status' => $response->status(),
                'response_keys' => array_keys($body),
            ]);
        }

        return [
            'render_id' => $renderId,
            'submit_response' => $this->safeResponseSummary($body),
            'submit_latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function waitForCompletion(string $renderId, array $context = []): array
    {
        $maxAttempts = max(1, (int) config('auto_video_editor.shotstack.render_max_poll_attempts', 120));
        $pollInterval = max(0, (int) config('auto_video_editor.shotstack.render_poll_interval_seconds', 5));
        $deadline = microtime(true) + max(1, (int) config('auto_video_editor.shotstack.render_timeout_seconds', 600));
        $startedAt = microtime(true);
        $lastStatus = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $status = $this->renderStatus($renderId, $context + [
                'stage' => 'poll',
                'poll_attempt' => $attempt,
            ]);
            $lastStatus = $status;
            $state = strtolower((string) ($status['status'] ?? ''));

            if (in_array($state, ['done', 'completed', 'succeeded', 'success'], true)) {
                $url = $status['url'] ?? null;

                if (! is_string($url) || trim($url) === '') {
                    throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', $context + [
                        'stage' => 'poll',
                        'render_id' => $renderId,
                        'render_status' => $state,
                    ]);
                }

                return [
                    'render_id' => $renderId,
                    'render_status' => $state,
                    'output_url' => $url,
                    'status_response' => $status,
                    'render_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ];
            }

            if (in_array($state, ['failed', 'error', 'cancelled', 'canceled'], true)) {
                throw AutoVideoEditorProviderException::renderFailed($context + [
                    'stage' => 'poll',
                    'render_id' => $renderId,
                    'render_status' => $state,
                ]);
            }

            if (microtime(true) >= $deadline) {
                break;
            }

            if ($pollInterval > 0 && $attempt < $maxAttempts) {
                sleep($pollInterval);
            }
        }

        throw AutoVideoEditorProviderException::renderTimedOut($context + [
            'stage' => 'poll',
            'render_id' => $renderId,
            'last_render_status' => is_array($lastStatus) ? ($lastStatus['status'] ?? null) : null,
        ]);
    }

    public function download(string $url, array $context = []): string
    {
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(180)->get($url);
        } catch (Throwable $exception) {
            throw AutoVideoEditorProviderException::requestFailed(
                'shotstack_download_failed',
                $context + ['stage' => 'download', 'provider' => 'shotstack'],
                $exception,
            );
        }

        if (! $response->successful() || $response->body() === '') {
            $this->logFailure('Shotstack final video download failed.', $response, $context + ['stage' => 'download']);

            throw AutoVideoEditorProviderException::requestFailed(
                'shotstack_download_failed',
                $this->responseContext($response, $context + ['stage' => 'download']),
            );
        }

        Log::info('Shotstack final video downloaded.', [
            'provider' => 'shotstack',
            'stage' => 'download',
            'generation_id' => $context['generation_id'] ?? null,
            'render_id' => $context['render_id'] ?? null,
            'http_status' => $response->status(),
            'download_latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $response->body();
    }

    /**
     * @return array<string, mixed>
     */
    private function renderStatus(string $renderId, array $context): array
    {
        $this->ensureApiKey();

        try {
            $response = $this->http()
                ->timeout(60)
                ->get('/render/'.$renderId, ['data' => 'false']);
        } catch (ConnectionException $exception) {
            throw AutoVideoEditorProviderException::requestFailed(
                'shotstack_invalid_response',
                $context + ['stage' => 'poll', 'provider' => 'shotstack', 'render_id' => $renderId],
                $exception,
            );
        }

        if (! $response->successful()) {
            $this->logFailure('Shotstack render poll failed.', $response, $context + ['render_id' => $renderId]);

            throw AutoVideoEditorProviderException::requestFailed(
                'shotstack_invalid_response',
                $this->responseContext($response, $context + ['render_id' => $renderId]),
            );
        }

        $body = $this->json($response, $context + ['render_id' => $renderId]);
        $payload = data_get($body, 'response');

        if (! is_array($payload)) {
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', $context + [
                'render_id' => $renderId,
                'response_keys' => array_keys($body),
            ]);
        }

        return $payload;
    }

    private function ensureApiKey(): void
    {
        if (trim((string) config('auto_video_editor.shotstack.api_key', '')) === '') {
            throw AutoVideoEditorProviderException::missingConfiguration('shotstack_config_missing', [
                'provider' => 'shotstack',
                'stage' => 'configuration',
            ]);
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->editBaseUrl())
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-api-key' => (string) config('auto_video_editor.shotstack.api_key'),
            ]);
    }

    private function editBaseUrl(): string
    {
        $base = rtrim((string) config('auto_video_editor.shotstack.base_url', 'https://api.shotstack.io'), '/');
        $environment = trim((string) config('auto_video_editor.shotstack.environment', 'stage'), '/');
        $environment = $environment === '' ? 'stage' : $environment;

        return $base.'/edit/'.$environment;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response, array $context): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', $context + [
                'http_status' => $response->status(),
            ]);
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseContext(Response $response, array $context): array
    {
        return $context + [
            'provider' => 'shotstack',
            'http_status' => $response->status(),
            'safe_response_excerpt' => mb_substr($response->body(), 0, 500),
        ];
    }

    private function logFailure(string $message, Response $response, array $context): void
    {
        Log::warning($message, $this->responseContext($response, $context));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function safeResponseSummary(array $body): array
    {
        return [
            'success' => $body['success'] ?? null,
            'message' => $body['message'] ?? null,
            'response_keys' => is_array($body['response'] ?? null) ? array_keys($body['response']) : [],
        ];
    }
}
