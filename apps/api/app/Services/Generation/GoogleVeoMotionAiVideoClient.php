<?php

namespace App\Services\Generation;

use App\Exceptions\MotionAiVideo\MotionAiVideoProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
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
                ->post($this->generateEndpoint(), $payload);
        } catch (ConnectionException $exception) {
            $safeContext = $this->safeNetworkContext('submit', $this->generateEndpoint(), $exception, $context);
            $this->logConnectionFailure('Google Veo submit connection failed.', $safeContext);

            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_submit_failed',
                $safeContext,
                $exception,
            );
        } catch (RequestException $exception) {
            $response = $exception->response;
            $submitContext = $context + ['stage' => 'submit'];

            if ($response instanceof Response) {
                $this->logFailure('Google Veo submit failed.', $response, $submitContext);

                throw MotionAiVideoProviderException::requestFailed(
                    $this->responseFailureReason($response, $submitContext),
                    $this->responseContext($response, $submitContext),
                    $exception,
                );
            }

            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_submit_failed',
                $this->safeNetworkContext('submit', $this->generateEndpoint(), $exception, $context),
                $exception,
            );
        }

        if (! $response->successful()) {
            $submitContext = $context + ['stage' => 'submit'];
            $this->logFailure('Google Veo submit failed.', $response, $submitContext);

            throw MotionAiVideoProviderException::requestFailed(
                $this->responseFailureReason($response, $submitContext),
                $this->responseContext($response, $submitContext),
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
            $response = $this->downloadHttp()
                ->get($uri);
        } catch (ConnectionException $exception) {
            $safeContext = $this->safeNetworkContext('download', $uri, $exception, $context);
            $this->logConnectionFailure('Google Veo download connection failed.', $safeContext);

            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_download_failed',
                $safeContext,
                $exception,
            );
        } catch (Throwable $exception) {
            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_download_failed',
                $this->safeNetworkContext('download', $uri, $exception, $context),
                $exception,
            );
        }

        if (! $response->successful()) {
            $this->logFailure('Google Veo download failed.', $response, $context + ['stage' => 'download']);

            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_download_failed',
                $this->responseContext($response, $context + ['stage' => 'download']),
            );
        }

        $this->assertValidDownload($response, $context);

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
            $endpoint = $this->operationEndpoint($operationName);
            $response = $this->http()
                ->get($endpoint);
        } catch (ConnectionException $exception) {
            $safeContext = $this->safeNetworkContext('poll', $endpoint ?? $this->operationEndpoint($operationName), $exception, $context);
            $this->logConnectionFailure('Google Veo poll connection failed.', $safeContext);

            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_poll_failed',
                $safeContext,
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
        return $this->withNetworkOptions(
            Http::baseUrl($this->apiBaseUrl())
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'x-goog-api-key' => (string) config('motion_ai_video.google_veo.api_key'),
                ])
        );
    }

    private function downloadHttp(): PendingRequest
    {
        return $this->withNetworkOptions(
            Http::withOptions(['allow_redirects' => true])
                ->withHeaders([
                    'x-goog-api-key' => (string) config('motion_ai_video.google_veo.api_key'),
                ])
        );
    }

    private function withNetworkOptions(PendingRequest $request): PendingRequest
    {
        $request = $request
            ->connectTimeout($this->connectTimeoutSeconds())
            ->timeout($this->timeoutSeconds())
            ->retry($this->retryTimes(), $this->retrySleepMs());

        $guzzleOptions = $this->guzzleOptions();

        if ($guzzleOptions !== []) {
            $request->withOptions($guzzleOptions);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    public function networkOptionsForDiagnostics(): array
    {
        return [
            'connect_timeout' => $this->connectTimeoutSeconds(),
            'timeout' => $this->timeoutSeconds(),
            'retry_times' => $this->retryTimes(),
            'retry_sleep_ms' => $this->retrySleepMs(),
            'curl' => $this->curlOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function safeNetworkSummary(?string $stage = null, ?string $endpoint = null): array
    {
        return $this->endpointDetails($endpoint ?? $this->generateEndpoint(), $stage === 'download') + [
            'connect_timeout_seconds' => $this->connectTimeoutSeconds(),
            'timeout_seconds' => $this->timeoutSeconds(),
            'retry_times' => $this->retryTimes(),
            'retry_sleep_ms' => $this->retrySleepMs(),
            'force_ipv4' => $this->forceIpv4(),
            'force_http1' => $this->forceHttp1(),
        ];
    }

    private function apiBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('motion_ai_video.google_veo.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');

        return str_ends_with($baseUrl, '/v1beta') ? $baseUrl : $baseUrl.'/v1beta';
    }

    private function generateEndpoint(): string
    {
        return '/models/'.$this->model().':predictLongRunning';
    }

    private function operationEndpoint(string $operationName): string
    {
        if (str_starts_with($operationName, 'http://') || str_starts_with($operationName, 'https://')) {
            return $operationName;
        }

        return '/'.ltrim($operationName, '/');
    }

    private function model(): string
    {
        return trim((string) config('motion_ai_video.google_veo.model', 'veo-3.1-generate-preview'));
    }

    private function connectTimeoutSeconds(): int
    {
        return max(1, (int) config('motion_ai_video.google_veo.connect_timeout_seconds', 60));
    }

    private function timeoutSeconds(): int
    {
        $timeout = config('motion_ai_video.google_veo.timeout_seconds');

        if ($timeout === null || $timeout === '') {
            $timeout = config('motion_ai_video.google_veo.request_timeout_seconds', 300);
        }

        return max(1, (int) $timeout);
    }

    private function retryTimes(): int
    {
        return max(1, (int) config('motion_ai_video.google_veo.retry_times', 2));
    }

    private function retrySleepMs(): int
    {
        return max(0, (int) config('motion_ai_video.google_veo.retry_sleep_ms', 1500));
    }

    private function forceIpv4(): bool
    {
        return (bool) config('motion_ai_video.google_veo.force_ipv4', false);
    }

    private function forceHttp1(): bool
    {
        return (bool) config('motion_ai_video.google_veo.force_http1', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function guzzleOptions(): array
    {
        $curlOptions = $this->curlOptions();

        return $curlOptions === [] ? [] : ['curl' => $curlOptions];
    }

    /**
     * @return array<int, int>
     */
    private function curlOptions(): array
    {
        $options = [];
        $ipResolveOption = $this->curlConstant('CURLOPT_IPRESOLVE');
        $ipv4Value = $this->curlConstant('CURL_IPRESOLVE_V4');
        $httpVersionOption = $this->curlConstant('CURLOPT_HTTP_VERSION');
        $http1Value = $this->curlConstant('CURL_HTTP_VERSION_1_1');

        if ($this->forceIpv4() && $ipResolveOption !== null && $ipv4Value !== null) {
            $options[$ipResolveOption] = $ipv4Value;
        }

        if ($this->forceHttp1() && $httpVersionOption !== null && $http1Value !== null) {
            $options[$httpVersionOption] = $http1Value;
        }

        return $options;
    }

    protected function curlConstant(string $name): ?int
    {
        return defined($name) ? (int) constant($name) : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function endpointDetails(string $endpoint, bool $redactPath = false): array
    {
        $isAbsolute = str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://');
        $basePath = (string) (parse_url($this->apiBaseUrl(), PHP_URL_PATH) ?: '');
        $path = $isAbsolute
            ? (string) (parse_url($endpoint, PHP_URL_PATH) ?: '/')
            : rtrim($basePath, '/').'/'.ltrim($endpoint, '/');

        return [
            'endpoint_host' => $isAbsolute
                ? (string) (parse_url($endpoint, PHP_URL_HOST) ?: 'unknown')
                : (string) (parse_url($this->apiBaseUrl(), PHP_URL_HOST) ?: 'unknown'),
            'endpoint_path' => $redactPath ? '[redacted-download-path]' : $path,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function safeNetworkContext(string $stage, string $endpoint, Throwable $exception, array $context = []): array
    {
        return array_merge($context, [
            'provider' => 'google_veo',
            'stage' => $stage,
            'model' => $this->model(),
            'exception_class' => $exception::class,
            'safe_exception_message' => $this->safeExceptionMessage($exception),
        ], $this->safeNetworkSummary($stage, $endpoint));
    }

    /** @param array<string, mixed> $context */
    private function logConnectionFailure(string $message, array $context): void
    {
        Log::warning($message, $context);
    }

    private function safeExceptionMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $apiKey = trim((string) config('motion_ai_video.google_veo.api_key', ''));

        if ($apiKey !== '') {
            $message = str_replace($apiKey, '[redacted-api-key]', $message);
        }

        $message = preg_replace('/([?&]key=)[^&\s)]+/i', '$1[redacted-api-key]', $message) ?? $message;
        $message = preg_replace('/https?:\/\/[^\s)]+/i', '[redacted-url]', $message) ?? $message;
        $message = preg_replace('/[A-Za-z0-9+\/=]{200,}/', '[redacted-long-token]', $message) ?? $message;

        return mb_substr($message, 0, 500);
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
            'safe_response_excerpt' => $this->safeResponseExcerpt($response->body()),
        ];
    }

    /** @param array<string, mixed> $context */
    private function responseFailureReason(Response $response, array $context): string
    {
        $excerpt = strtolower($this->safeResponseExcerpt($response->body()));
        $shape = (string) ($context['provider_request_shape'] ?? '');

        if (($context['stage'] ?? null) === 'submit'
            && in_array($shape, ['image_first_frame', 'reference_images'], true)
            && str_contains($excerpt, 'inlinedata')
            && str_contains($excerpt, 'supported')) {
            return 'google_veo_unsupported_image_input';
        }

        return 'google_veo_submit_failed';
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

    private function assertValidDownload(Response $response, array $context): void
    {
        $body = $response->body();
        $contentType = strtolower((string) ($response->header('Content-Type') ?? ''));
        $prefix = strtolower(substr(ltrim($body), 0, 256));
        $looksLikeHtmlOrJson = str_starts_with($prefix, '<!doctype')
            || str_starts_with($prefix, '<html')
            || str_starts_with($prefix, '<')
            || str_starts_with($prefix, '{')
            || str_starts_with($prefix, '[');
        $isMp4 = str_contains($contentType, 'video/mp4');
        $isOctetStream = str_contains($contentType, 'application/octet-stream');

        if ($body !== '' && ! $looksLikeHtmlOrJson && ($isMp4 || ($isOctetStream && $this->looksLikeMp4Binary($body)))) {
            return;
        }

        throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', array_merge($context, [
            'stage' => 'download_validate',
            'http_status' => $response->status(),
            'content_type' => $contentType,
            'response_format' => $looksLikeHtmlOrJson ? 'html_or_json' : 'unknown_non_mp4',
        ]));
    }

    private function looksLikeMp4Binary(string $body): bool
    {
        return strlen($body) >= 12 && substr($body, 4, 4) === 'ftyp';
    }

    private function safeResponseExcerpt(string $body): string
    {
        $excerpt = mb_substr($body, 0, 500);
        $excerpt = preg_replace('/https?:\/\/\S+/i', '[redacted-url]', $excerpt) ?? $excerpt;

        return preg_replace('/[A-Za-z0-9+\/=]{200,}/', '[redacted-long-token]', $excerpt) ?? $excerpt;
    }
}
