<?php

namespace App\Services\Generation;

use App\Exceptions\MotionAiVideo\MotionAiVideoProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleVeoMotionAiVideoClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function submit(array $payload, array $context = []): array
    {
        $this->ensureConfigured();
        $startedAt = microtime(true);

        try {
            $response = $this->http()
                ->timeout($this->requestTimeout())
                ->post($this->generateEndpoint(), $payload);
        } catch (ConnectionException $exception) {
            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_submit_failed',
                $context + ['stage' => 'submit', 'provider' => 'google_veo'],
                $exception,
            );
        }

        if (! $response->successful()) {
            $this->logFailure('Google Veo submit failed.', $response, $context + ['stage' => 'submit']);

            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_submit_failed',
                $this->responseContext($response, $context + ['stage' => 'submit']),
            );
        }

        $body = $this->json($response, $context + ['stage' => 'submit']);
        $operationName = $this->operationName($body);
        $completedVideo = $this->extractVideo($body);

        if ($operationName === null && $completedVideo === null) {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', $context + [
                'stage' => 'submit',
                'http_status' => $response->status(),
                'response_keys' => array_keys($body),
            ]);
        }

        return [
            'operation_name' => $operationName,
            'done' => (bool) ($body['done'] ?? $completedVideo !== null),
            'video' => $completedVideo,
            'submit_response' => $this->safeResponseSummary($body),
            'submit_latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function waitForCompletion(string $operationName, array $context = []): array
    {
        $maxAttempts = max(1, (int) config('motion_ai_video.google_veo.max_poll_attempts', 120));
        $pollInterval = max(0, (int) config('motion_ai_video.google_veo.poll_interval_seconds', 5));
        $startedAt = microtime(true);
        $lastStatus = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $status = $this->operationStatus($operationName, $context + [
                'stage' => 'poll',
                'poll_attempt' => $attempt,
            ]);
            $lastStatus = $status;

            if ($this->isFailed($status)) {
                throw MotionAiVideoProviderException::renderFailed($context + [
                    'stage' => 'poll',
                    'operation_name_present' => true,
                    'provider_status' => $this->statusLabel($status),
                ]);
            }

            if ((bool) ($status['done'] ?? false)) {
                $video = $this->extractVideo($status);

                if ($video === null) {
                    throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', $context + [
                        'stage' => 'poll',
                        'provider_status' => $this->statusLabel($status),
                        'response_keys' => array_keys($status),
                    ]);
                }

                return [
                    'operation_name' => $operationName,
                    'render_status' => 'done',
                    'response_status' => $this->statusLabel($status) ?? 'done',
                    'video' => $video,
                    'status_response' => $this->safeResponseSummary($status),
                    'render_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ];
            }

            if ($pollInterval > 0 && $attempt < $maxAttempts) {
                sleep($pollInterval);
            }
        }

        throw MotionAiVideoProviderException::renderTimedOut($context + [
            'stage' => 'poll',
            'operation_name_present' => true,
            'last_provider_status' => is_array($lastStatus) ? $this->statusLabel($lastStatus) : null,
        ]);
    }

    public function download(string $uri, array $context = []): string
    {
        $startedAt = microtime(true);

        try {
            $response = Http::timeout($this->requestTimeout())
                ->withHeaders(['x-goog-api-key' => (string) config('motion_ai_video.google_veo.api_key')])
                ->get($uri);
        } catch (Throwable $exception) {
            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_download_failed',
                $context + ['stage' => 'download', 'provider' => 'google_veo'],
                $exception,
            );
        }

        if (! $response->successful() || $response->body() === '') {
            $this->logFailure('Google Veo download failed.', $response, $context + ['stage' => 'download']);

            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_download_failed',
                $this->responseContext($response, $context + ['stage' => 'download']),
            );
        }

        Log::info('Google Veo final motion clip downloaded.', [
            'provider' => 'google_veo',
            'stage' => 'download',
            'generation_id' => $context['generation_id'] ?? null,
            'http_status' => $response->status(),
            'download_latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $response->body();
    }

    /**
     * @param  array<string, mixed>  $video
     */
    public function videoBinary(array $video, array $context = []): string
    {
        $base64 = $this->stringValue(
            $video['bytesBase64Encoded']
            ?? $video['bytes_base64_encoded']
            ?? $video['base64']
            ?? $video['data']
            ?? null,
        );

        if ($base64 !== null) {
            $base64 = preg_replace('/^data:video\/[a-z0-9.+-]+;base64,/i', '', $base64) ?? $base64;
            $decoded = base64_decode($base64, true);

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }

            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', $context + [
                'stage' => 'decode_video',
                'response_format' => 'invalid_base64',
            ]);
        }

        $uri = $this->stringValue(
            $video['uri']
            ?? $video['url']
            ?? $video['downloadUri']
            ?? $video['download_uri']
            ?? $video['fileUri']
            ?? $video['file_uri']
            ?? $video['gcsUri']
            ?? $video['gcs_uri']
            ?? null,
        );

        if ($uri !== null) {
            return $this->download($uri, $context);
        }

        throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', $context + [
            'stage' => 'extract_video',
            'response_format' => 'missing_video_uri_or_base64',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function operationStatus(string $operationName, array $context): array
    {
        $this->ensureConfigured();

        try {
            $response = $this->http()
                ->timeout($this->requestTimeout())
                ->get($this->operationEndpoint($operationName));
        } catch (ConnectionException $exception) {
            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_poll_failed',
                $context + ['stage' => 'poll', 'provider' => 'google_veo'],
                $exception,
            );
        }

        if (! $response->successful()) {
            $this->logFailure('Google Veo poll failed.', $response, $context);

            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_poll_failed',
                $this->responseContext($response, $context),
            );
        }

        return $this->json($response, $context);
    }

    private function ensureConfigured(): void
    {
        if (trim((string) config('motion_ai_video.google_veo.api_key', '')) === '') {
            throw MotionAiVideoProviderException::missingConfiguration([
                'provider' => 'google_veo',
                'stage' => 'configuration',
            ]);
        }

        if (trim((string) config('motion_ai_video.google_veo.model', '')) === '') {
            throw MotionAiVideoProviderException::missingConfiguration([
                'provider' => 'google_veo',
                'stage' => 'configuration',
                'field' => 'model',
            ]);
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-goog-api-key' => (string) config('motion_ai_video.google_veo.api_key'),
            ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('motion_ai_video.google_veo.base_url', 'https://generativelanguage.googleapis.com'), '/');
    }

    private function generateEndpoint(): string
    {
        return '/v1beta/models/'.$this->model().':predictLongRunning';
    }

    private function operationEndpoint(string $operationName): string
    {
        if (str_starts_with($operationName, 'http://') || str_starts_with($operationName, 'https://')) {
            return $operationName;
        }

        return '/v1beta/'.ltrim($operationName, '/');
    }

    private function model(): string
    {
        return trim((string) config('motion_ai_video.google_veo.model', 'veo-3.0-generate-preview'));
    }

    private function requestTimeout(): int
    {
        return max(1, (int) config('motion_ai_video.google_veo.request_timeout_seconds', 180));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response, array $context): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', $context + [
                'http_status' => $response->status(),
            ]);
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function operationName(array $body): ?string
    {
        return $this->stringValue($body['name'] ?? data_get($body, 'operation.name') ?? data_get($body, 'operation'));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function extractVideo(array $body): ?array
    {
        foreach ($this->videoCandidates($body) as $candidate) {
            if (is_array($candidate)) {
                $video = $candidate['video'] ?? $candidate;

                if (is_array($video) && $this->looksLikeVideo($video)) {
                    return $video;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<int, mixed>
     */
    private function videoCandidates(array $body): array
    {
        return array_values(array_filter([
            data_get($body, 'response.generateVideoResponse.generatedSamples.0.video'),
            data_get($body, 'response.generateVideoResponse.generatedSamples.0'),
            data_get($body, 'response.generatedVideos.0.video'),
            data_get($body, 'response.generatedVideos.0'),
            data_get($body, 'response.videos.0.video'),
            data_get($body, 'response.videos.0'),
            data_get($body, 'generateVideoResponse.generatedSamples.0.video'),
            data_get($body, 'generatedVideos.0.video'),
            data_get($body, 'videos.0.video'),
            data_get($body, 'video'),
        ], fn (mixed $value): bool => $value !== null));
    }

    /** @param array<string, mixed> $video */
    private function looksLikeVideo(array $video): bool
    {
        foreach (['uri', 'url', 'downloadUri', 'fileUri', 'gcsUri', 'bytesBase64Encoded', 'base64', 'data'] as $key) {
            if ($this->stringValue($video[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $body */
    private function isFailed(array $body): bool
    {
        if (isset($body['error'])) {
            return true;
        }

        $status = strtolower((string) ($this->statusLabel($body) ?? ''));

        return in_array($status, ['failed', 'failure', 'error', 'cancelled', 'canceled'], true);
    }

    /** @param array<string, mixed> $body */
    private function statusLabel(array $body): ?string
    {
        return $this->stringValue(
            $body['status']
            ?? $body['state']
            ?? data_get($body, 'metadata.status')
            ?? data_get($body, 'metadata.state')
            ?? data_get($body, 'error.status')
            ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseContext(Response $response, array $context): array
    {
        return $context + [
            'provider' => 'google_veo',
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
            'done' => $body['done'] ?? null,
            'top_level_keys' => array_keys($body),
            'has_response' => isset($body['response']) && is_array($body['response']),
            'has_video' => $this->extractVideo($body) !== null,
            'status' => $this->statusLabel($body),
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
