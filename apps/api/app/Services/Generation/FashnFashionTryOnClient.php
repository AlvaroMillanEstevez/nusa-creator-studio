<?php

namespace App\Services\Generation;

use App\Exceptions\FashionTryOn\FashionTryOnProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FashnFashionTryOnClient
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
            $response = $this->http()->post('/run', $payload);
        } catch (ConnectionException $exception) {
            $networkContext = $this->networkContext('submit', '/run', $context, $exception);
            $this->logNetworkFailure('FASHN submit connection failed.', $networkContext);

            throw FashionTryOnProviderException::requestFailed(
                'fashn_connection_failed',
                $networkContext,
                $exception,
            );
        } catch (Throwable $exception) {
            $networkContext = $this->networkContext('submit', '/run', $context, $exception);
            $this->logNetworkFailure('FASHN submit failed before response.', $networkContext);

            throw FashionTryOnProviderException::requestFailed(
                'fashn_unknown_error',
                $networkContext,
                $exception,
            );
        }

        if (! $response->successful()) {
            $this->logFailure('FASHN submit failed.', $response, $context + ['stage' => 'submit']);

            throw FashionTryOnProviderException::requestFailed(
                $this->failureReason($response),
                $this->responseContext($response, $context + ['stage' => 'submit']),
            );
        }

        $body = $this->json($response);
        $predictionId = $this->predictionId($body);

        if ($predictionId === null) {
            throw FashionTryOnProviderException::invalidResponse('fashn_bad_request', $context + [
                'stage' => 'submit',
                'http_status' => $response->status(),
                'response_keys' => array_keys($body),
            ]);
        }

        Log::info('FASHN Fashion Try-On prediction submitted.', $context + [
            'provider' => 'fashn',
            'stage' => 'submit',
            'prediction_id' => $predictionId,
            'http_status' => $response->status(),
        ]);

        return [
            'prediction_id' => $predictionId,
            'status' => $this->statusLabel($body) ?? 'starting',
            'provider_credits_used' => $this->creditsUsed($response, $body),
            'provider_credits_source' => $this->creditsSource($response, $body),
            'submit_latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'response_summary' => $this->safeResponseSummary($body),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function waitForCompletion(string $predictionId, array $context = []): array
    {
        $maxAttempts = max(1, (int) config('fashion_try_on.fashn.max_poll_attempts', 60));
        $pollInterval = max(0, (int) config('fashion_try_on.fashn.poll_interval_seconds', 3));
        $startedAt = microtime(true);
        $lastStatus = null;
        $lastBody = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $poll = $this->status($predictionId, $context + [
                'stage' => 'poll',
                'poll_attempt' => $attempt,
            ]);
            $lastStatus = $poll['status'];
            $lastBody = is_array($poll['body'] ?? null) ? $poll['body'] : [];

            if ($poll['status'] === 'completed') {
                return [
                    'prediction_id' => $predictionId,
                    'status' => 'completed',
                    'output' => $poll['output'],
                    'provider_credits_used' => $poll['provider_credits_used'],
                    'provider_credits_source' => $poll['provider_credits_source'],
                    'provider_latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'response_summary' => $this->safeResponseSummary($lastBody),
                ];
            }

            if ($poll['status'] === 'failed') {
                $providerError = $this->normalizeProviderError($lastBody);

                throw FashionTryOnProviderException::predictionFailed($context + [
                    'stage' => 'poll',
                    'prediction_id' => $predictionId,
                    'provider_prediction_id' => $predictionId,
                    'provider_status' => 'failed',
                    'provider_error_name' => $providerError['name'] ?? null,
                    'provider_error_code' => $providerError['code'] ?? null,
                    'provider_error_message' => $providerError['message'],
                    'safe_error_category' => $this->providerErrorCategory($lastBody),
                ]);
            }

            if ($pollInterval > 0 && $attempt < $maxAttempts) {
                sleep($pollInterval);
            }
        }

        throw FashionTryOnProviderException::pollTimedOut($context + [
            'stage' => 'poll',
            'prediction_id' => $predictionId,
            'last_provider_status' => $lastStatus,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function status(string $predictionId, array $context = []): array
    {
        $this->ensureConfigured();
        $endpoint = '/status/'.rawurlencode($predictionId);

        try {
            $response = $this->http()->get($endpoint);
        } catch (ConnectionException $exception) {
            $networkContext = $this->networkContext('poll', $endpoint, $context, $exception) + ['prediction_id' => $predictionId];
            $this->logNetworkFailure('FASHN status poll connection failed.', $networkContext);

            throw FashionTryOnProviderException::requestFailed(
                'fashn_connection_failed',
                $networkContext,
                $exception,
            );
        } catch (Throwable $exception) {
            $networkContext = $this->networkContext('poll', $endpoint, $context, $exception) + ['prediction_id' => $predictionId];
            $this->logNetworkFailure('FASHN status poll failed before response.', $networkContext);

            throw FashionTryOnProviderException::requestFailed(
                'fashn_unknown_error',
                $networkContext,
                $exception,
            );
        }

        if (! $response->successful()) {
            $this->logFailure('FASHN status poll failed.', $response, $context + [
                'stage' => 'poll',
                'prediction_id' => $predictionId,
            ]);

            throw FashionTryOnProviderException::requestFailed(
                $this->failureReason($response),
                $this->responseContext($response, $context + [
                    'stage' => 'poll',
                    'prediction_id' => $predictionId,
                ]),
            );
        }

        $body = $this->json($response);
        $status = $this->statusLabel($body);

        if ($status === null) {
            throw FashionTryOnProviderException::invalidResponse('fashn_bad_request', $context + [
                'stage' => 'poll',
                'prediction_id' => $predictionId,
                'http_status' => $response->status(),
                'response_keys' => array_keys($body),
            ]);
        }

        return [
            'status' => $status,
            'output' => $body['output'] ?? $body['result'] ?? $body['outputs'] ?? null,
            'provider_credits_used' => $this->creditsUsed($response, $body),
            'provider_credits_source' => $this->creditsSource($response, $body),
            'body' => $body,
        ];
    }

    public function download(string $url, array $context = []): string
    {
        try {
            $response = Http::connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->get($url);
        } catch (Throwable $exception) {
            $networkContext = $this->networkContext('download', parse_url($url, PHP_URL_PATH) ?: '/download', $context, $exception);
            $this->logNetworkFailure('FASHN output download failed before response.', $networkContext);

            throw FashionTryOnProviderException::requestFailed(
                'fashn_output_invalid',
                $networkContext,
                $exception,
            );
        }

        if (! $response->successful() || $response->body() === '') {
            throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', $context + [
                'stage' => 'download',
                'http_status' => $response->status(),
            ]);
        }

        return $response->body();
    }

    /** @return array<string, mixed> */
    public function safeNetworkSummary(): array
    {
        return [
            'connect_timeout_seconds' => $this->connectTimeout(),
            'request_timeout_seconds' => $this->timeout(),
            'poll_interval_seconds' => (int) config('fashion_try_on.fashn.poll_interval_seconds', 3),
            'max_poll_attempts' => (int) config('fashion_try_on.fashn.max_poll_attempts', 60),
            'endpoint_host' => parse_url($this->baseUrl(), PHP_URL_HOST) ?: 'api.fashn.ai',
            'submit_endpoint_path' => '/run',
            'status_endpoint_path' => '/status/{predictionId}',
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->acceptJson()
            ->asJson()
            ->withToken($this->apiKey());
    }

    private function ensureConfigured(): void
    {
        if ($this->apiKey() === '') {
            throw FashionTryOnProviderException::missingConfiguration([
                'provider' => 'fashn',
                'stage' => 'configuration',
            ]);
        }
    }

    private function apiKey(): string
    {
        return (string) config('fashion_try_on.fashn.api_key', '');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('fashion_try_on.fashn.base_url', 'https://api.fashn.ai/v1'), '/');
    }

    private function connectTimeout(): int
    {
        return max(1, (int) config('fashion_try_on.fashn.connect_timeout_seconds', 30));
    }

    private function timeout(): int
    {
        return max(1, (int) config('fashion_try_on.fashn.request_timeout_seconds', 120));
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /** @param array<string, mixed> $body */
    private function predictionId(array $body): ?string
    {
        $value = $body['id'] ?? $body['prediction_id'] ?? $body['predictionId'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $body */
    private function statusLabel(array $body): ?string
    {
        $value = $body['status'] ?? $body['state'] ?? null;

        return is_string($value) && trim($value) !== '' ? strtolower(trim($value)) : null;
    }

    /** @param array<string, mixed> $body */
    private function creditsUsed(Response $response, array $body): int|float|null
    {
        $header = $response->header('x-fashn-credits-used');

        if (is_numeric($header)) {
            return $header + 0;
        }

        $value = $body['credits_used'] ?? $body['creditsUsed'] ?? null;

        return is_numeric($value) ? $value + 0 : null;
    }

    /** @param array<string, mixed> $body */
    private function creditsSource(Response $response, array $body): string
    {
        if (is_numeric($response->header('x-fashn-credits-used'))) {
            return 'response_header';
        }

        if (is_numeric($body['credits_used'] ?? $body['creditsUsed'] ?? null)) {
            return 'response_field';
        }

        return 'unavailable';
    }

    private function failureReason(Response $response): string
    {
        return match ($response->status()) {
            401, 403 => 'fashn_auth_failed',
            402 => 'fashn_insufficient_credits',
            400, 422 => $this->providerErrorCategory($this->json($response)),
            429, 500, 502, 503, 504 => 'fashn_provider_unavailable',
            default => 'fashn_unknown_error',
        };
    }

    /** @param array<string, mixed> $body */
    private function providerErrorCategory(array $body): string
    {
        $providerError = $this->normalizeProviderError($body);
        $name = strtolower($providerError['name'] ?? '');
        $code = strtolower($providerError['code'] ?? '');
        $message = strtolower($providerError['message']);
        $nameAndCode = trim($name.' '.$code);
        $allText = trim($nameAndCode.' '.$message);

        if ($nameAndCode !== '') {
            return match (true) {
                str_contains($nameAndCode, 'unauthor') || str_contains($nameAndCode, 'auth') || str_contains($nameAndCode, 'token') => 'fashn_auth_failed',
                str_contains($nameAndCode, 'credit') || str_contains($nameAndCode, 'balance') || str_contains($nameAndCode, 'payment') => 'fashn_insufficient_credits',
                str_contains($nameAndCode, 'pose') => 'fashn_pose_not_detected',
                str_contains($nameAndCode, 'imageload') || str_contains($nameAndCode, 'image_load') || str_contains($nameAndCode, 'image-load') => 'fashn_image_load_failed',
                str_contains($nameAndCode, 'moderation') || str_contains($nameAndCode, 'safety') || str_contains($nameAndCode, 'content') => 'fashn_content_moderation',
                str_contains($nameAndCode, 'rate') || str_contains($nameAndCode, 'unavailable') || str_contains($nameAndCode, 'server') => 'fashn_provider_unavailable',
                str_contains($nameAndCode, 'timeout') || str_contains($nameAndCode, 'timedout') => 'fashn_poll_timeout',
                str_contains($nameAndCode, 'validation') || str_contains($nameAndCode, 'input') || str_contains($nameAndCode, 'badrequest') => 'fashn_input_validation',
                default => '',
            } ?: $this->providerErrorCategoryFromText($message);
        }

        return $this->providerErrorCategoryFromText($allText);
    }

    private function providerErrorCategoryFromText(string $message): string
    {
        return match (true) {
            $message === '' || $message === 'provider did not provide a readable error message.' => 'fashn_unknown_error',
            str_contains($message, 'unauthor') || str_contains($message, 'invalid token') || str_contains($message, 'api key') || str_contains($message, 'forbidden') => 'fashn_auth_failed',
            str_contains($message, 'credit') || str_contains($message, 'balance') || str_contains($message, 'payment') => 'fashn_insufficient_credits',
            str_contains($message, 'moderation') || str_contains($message, 'safety') || str_contains($message, 'content policy') => 'fashn_content_moderation',
            str_contains($message, 'pose') || str_contains($message, 'person not detected') || str_contains($message, 'no person') || str_contains($message, 'human body') => 'fashn_pose_not_detected',
            str_contains($message, 'image load') || str_contains($message, 'load image') || str_contains($message, 'cannot load') || str_contains($message, 'download image') => 'fashn_image_load_failed',
            (str_contains($message, 'garment') || str_contains($message, 'product image') || str_contains($message, 'model image')) && (str_contains($message, 'invalid') || str_contains($message, 'missing') || str_contains($message, 'unsupported')) => 'fashn_input_validation',
            str_contains($message, 'garment') || str_contains($message, 'product image') || str_contains($message, 'model image') || str_contains($message, 'image') => 'fashn_image_load_failed',
            str_contains($message, 'rate limit') || str_contains($message, 'too many') || str_contains($message, 'unavailable') || str_contains($message, 'server error') || str_contains($message, 'overloaded') => 'fashn_provider_unavailable',
            str_contains($message, 'timeout') || str_contains($message, 'timed out') => 'fashn_poll_timeout',
            default => 'fashn_processing_failed',
        };
    }

    /**
     * @param  array<string, mixed>|object|string|int|float|bool|null  $body
     * @return array{name?: string, code?: string, message: string}
     */
    private function normalizeProviderError(mixed $body): array
    {
        $name = $this->providerErrorName($body);
        $code = $this->providerErrorCode($body);
        $message = $this->providerErrorMessage($body);

        return array_filter([
            'name' => $name,
            'code' => $code,
            'message' => $message ?? 'Provider did not provide a readable error message.',
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function providerErrorName(mixed $body): ?string
    {
        foreach (['error.name', 'name', 'detail.name'] as $path) {
            $value = $this->boundedScalarText(data_get($body, $path));

            if ($value !== null) {
                return $value;
            }
        }

        $rawError = data_get($body, 'error');
        $error = is_string($rawError) ? $this->boundedScalarText($rawError) : null;

        if ($error !== null && $this->looksLikeProviderErrorName($error)) {
            return $error;
        }

        return null;
    }

    private function providerErrorCode(mixed $body): ?string
    {
        foreach (['error.code', 'code', 'detail.code'] as $path) {
            $value = $this->boundedScalarText(data_get($body, $path), 80);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function providerErrorMessage(mixed $body): ?string
    {
        foreach (['error.message', 'message', 'detail.message'] as $path) {
            $value = $this->boundedScalarText(data_get($body, $path));

            if ($value !== null) {
                return $value;
            }
        }

        foreach (['error', 'detail', 'errors'] as $path) {
            $value = data_get($body, $path);
            $scalarText = $this->boundedScalarText($value);

            if ($scalarText !== null && ! $this->looksLikeProviderErrorName($scalarText)) {
                return $scalarText;
            }

            $collectedText = $this->boundedNestedScalarText($value);

            if ($collectedText !== null) {
                return $collectedText;
            }
        }

        return $this->boundedNestedScalarText($this->unexpectedErrorPayload($body));
    }

    private function unexpectedErrorPayload(mixed $body): mixed
    {
        if (is_object($body)) {
            $body = get_object_vars($body);
        }

        if (! is_array($body)) {
            return $body;
        }

        return array_diff_key($body, array_flip([
            'id',
            'prediction_id',
            'predictionId',
            'status',
            'state',
            'output',
            'outputs',
            'result',
        ]));
    }

    private function boundedScalarText(mixed $value, int $maxLength = 240): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if ($text === '') {
            return null;
        }

        $text = $this->redactProviderText($text);

        return mb_substr($text, 0, max(1, $maxLength));
    }

    private function boundedNestedScalarText(mixed $value, int $maxDepth = 3, int $maxParts = 6, int $maxLength = 240): ?string
    {
        $parts = [];
        $this->collectScalarTexts($value, $parts, $maxDepth, $maxParts, $maxLength);

        if ($parts === []) {
            return null;
        }

        return mb_substr(implode(' ', array_values(array_unique($parts))), 0, $maxLength);
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function collectScalarTexts(mixed $value, array &$parts, int $remainingDepth, int $maxParts, int $maxLength): void
    {
        if ($remainingDepth < 0 || count($parts) >= $maxParts) {
            return;
        }

        $scalarText = $this->boundedScalarText($value, $maxLength);

        if ($scalarText !== null) {
            $parts[] = $scalarText;

            return;
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->collectScalarTexts($item, $parts, $remainingDepth - 1, $maxParts, $maxLength);

            if (count($parts) >= $maxParts) {
                return;
            }
        }
    }

    private function looksLikeProviderErrorName(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_:-]{2,80}$/', $value) === 1
            && ! str_contains(trim($value), ' ');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function responseContext(Response $response, array $context): array
    {
        $body = $this->json($response);
        $providerError = $this->normalizeProviderError($body);

        return $context + [
            'provider' => 'fashn',
            'http_status' => $response->status(),
            'endpoint_host' => parse_url($this->baseUrl(), PHP_URL_HOST) ?: 'api.fashn.ai',
            'connect_timeout_seconds' => $this->connectTimeout(),
            'request_timeout_seconds' => $this->timeout(),
            'provider_error_name' => $providerError['name'] ?? null,
            'provider_error_code' => $providerError['code'] ?? null,
            'provider_error_message' => $providerError['message'],
            'safe_error_category' => $this->failureReason($response),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function networkContext(string $stage, string $path, array $context, Throwable $exception): array
    {
        return $context + [
            'provider' => 'fashn',
            'stage' => $stage,
            'endpoint_host' => parse_url($this->baseUrl(), PHP_URL_HOST) ?: 'api.fashn.ai',
            'endpoint_path' => $path,
            'exception_class' => $exception::class,
            'safe_exception_message' => $this->safeExceptionMessage($exception),
            'connect_timeout_seconds' => $this->connectTimeout(),
            'request_timeout_seconds' => $this->timeout(),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function safeResponseSummary(array $body): array
    {
        return [
            'keys' => array_keys($body),
            'status' => $this->statusLabel($body),
            'has_output' => isset($body['output']) || isset($body['result']) || isset($body['outputs']),
        ];
    }

    private function safeExceptionMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $apiKey = $this->apiKey();

        if ($apiKey !== '') {
            $message = str_replace($apiKey, '[redacted-api-key]', $message);
        }

        return mb_substr($this->redactProviderText($message), 0, 220);
    }

    private function redactProviderText(string $message): string
    {
        $apiKey = $this->apiKey();

        if ($apiKey !== '') {
            $message = str_replace($apiKey, '[redacted-api-key]', $message);
        }

        $message = preg_replace('/data:image\/[^;\s]+;base64,[A-Za-z0-9+\/=\r\n]+/', '[redacted-data-uri]', $message) ?? $message;
        $message = preg_replace('/https?:\/\/[^\s]+/', '[redacted-url]', $message) ?? $message;
        $message = preg_replace('/[A-Za-z]:?\\[^\s]+/', '[redacted-path]', $message) ?? $message;
        $message = preg_replace('/\/[A-Za-z0-9._\-\/]*(storage|media|private)[^\s]*/', '[redacted-path]', $message) ?? $message;

        return $message;
    }

    /** @param array<string, mixed> $context */
    private function logNetworkFailure(string $message, array $context): void
    {
        Log::warning($message, $context);
    }

    /** @param array<string, mixed> $context */
    private function logFailure(string $message, Response $response, array $context): void
    {
        Log::warning($message, $this->responseContext($response, $context));
    }
}
