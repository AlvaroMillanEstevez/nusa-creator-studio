<?php

namespace App\Services\Generation;

use App\Exceptions\VideoToCampaign\VideoToCampaignProviderException;
use App\Models\MediaAsset;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeminiVideoToCampaignClient
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array{
     *     response: array<string, mixed>,
     *     file: array<string, mixed>,
     *     latency_ms: int,
     *     provider_file_deleted: bool,
     * }
     */
    public function analyze(MediaAsset $asset, string $prompt, array $schema): array
    {
        $startedAt = microtime(true);
        $uploadedFile = $this->uploadFile($asset);
        $activeFile = $this->waitForFile($uploadedFile);
        $deleted = false;

        try {
            $response = $this->requestAnalysis($activeFile, $asset, $prompt, $schema);
        } finally {
            $deleted = $this->deleteFile($uploadedFile['name'] ?? null);
        }

        return [
            'response' => $response,
            'file' => $activeFile,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'provider_file_deleted' => $deleted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadFile(MediaAsset $asset): array
    {
        $apiKey = $this->apiKey();
        $mimeType = $asset->mime_type ?: 'video/mp4';
        $filename = $asset->original_name ?: 'nusa-source-video';
        $uploadContext = [
            'provider' => 'gemini',
            'model' => $this->model(),
            'stage' => 'upload_file',
            'endpoint_type' => 'files.upload',
            'media_asset_id' => $asset->getKey(),
            'mime_type' => $mimeType,
            'size_bytes' => $asset->size_bytes,
        ];

        Log::info('Gemini Video-to-Campaign upload started.', $this->safeLogContext($uploadContext));

        $disk = Storage::disk($asset->disk);

        if (! $disk->exists($asset->path)) {
            throw VideoToCampaignProviderException::fileUnavailable($uploadContext + [
                'reason' => 'source_file_missing',
            ]);
        }

        $binary = $disk->get($asset->path);

        if ($binary === null || $binary === '') {
            throw VideoToCampaignProviderException::fileUnavailable($uploadContext + [
                'reason' => 'source_file_empty',
            ]);
        }

        try {
            $start = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeoutSeconds())
                ->acceptJson()
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'X-Goog-Upload-Protocol' => 'resumable',
                    'X-Goog-Upload-Command' => 'start',
                    'X-Goog-Upload-Header-Content-Length' => (string) strlen($binary),
                    'X-Goog-Upload-Header-Content-Type' => $mimeType,
                    'Content-Type' => 'application/json',
                ])
                ->post('/upload/v1beta/files', [
                    'file' => [
                        'display_name' => $filename,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->requestFailure('gemini_file_upload_start_connection_failed', $uploadContext + [
                'endpoint_type' => 'files.upload.start',
            ], $exception);
        }

        if (! $start->successful()) {
            $this->requestFailure('gemini_file_upload_start_failed', $this->responseContext($start, $uploadContext + [
                'endpoint_type' => 'files.upload.start',
            ]));
        }

        $uploadUrl = $start->header('x-goog-upload-url') ?? $start->header('X-Goog-Upload-URL');

        if (! is_string($uploadUrl) || trim($uploadUrl) === '') {
            Log::warning('Gemini Video-to-Campaign provider returned an invalid response.', $this->safeLogContext($uploadContext + [
                'reason' => 'gemini_upload_url_missing',
                'endpoint_type' => 'files.upload.start',
                'http_status' => $start->status(),
            ]));

            throw VideoToCampaignProviderException::invalidResponse('gemini_upload_url_missing', $uploadContext + [
                'endpoint_type' => 'files.upload.start',
                'http_status' => $start->status(),
            ]);
        }

        try {
            $upload = Http::timeout($this->timeoutSeconds())
                ->acceptJson()
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Length' => (string) strlen($binary),
                    'X-Goog-Upload-Offset' => '0',
                    'X-Goog-Upload-Command' => 'upload, finalize',
                ])
                ->withBody($binary, $mimeType)
                ->post($uploadUrl);
        } catch (ConnectionException $exception) {
            $this->requestFailure('gemini_file_upload_finalize_connection_failed', $uploadContext + [
                'endpoint_type' => 'files.upload.finalize',
            ], $exception);
        }

        if (! $upload->successful()) {
            $this->requestFailure('gemini_file_upload_finalize_failed', $this->responseContext($upload, $uploadContext + [
                'endpoint_type' => 'files.upload.finalize',
            ]));
        }

        $json = $upload->json();

        if (! is_array($json)) {
            throw VideoToCampaignProviderException::invalidResponse('gemini_upload_invalid_json_shape', $uploadContext + [
                'endpoint_type' => 'files.upload.finalize',
            ]);
        }

        $file = $json['file'] ?? $json;

        if (! is_array($file) || ! is_string($file['name'] ?? null)) {
            throw VideoToCampaignProviderException::invalidResponse('gemini_upload_file_name_missing', $uploadContext + [
                'endpoint_type' => 'files.upload.finalize',
            ]);
        }

        Log::info('Gemini Video-to-Campaign upload completed.', $this->safeLogContext($uploadContext + [
            'endpoint_type' => 'files.upload.finalize',
            'provider_file_state' => $file['state'] ?? null,
        ]));

        return $file;
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function waitForFile(array $file): array
    {
        $deadline = microtime(true) + max(1, (int) config('video_to_campaign.gemini.file_processing_timeout_seconds', 120));
        $pollInterval = max(1, (int) config('video_to_campaign.gemini.file_processing_poll_interval_seconds', 3));
        $current = $file;
        $pollContext = [
            'provider' => 'gemini',
            'model' => $this->model(),
            'stage' => 'wait_for_file',
            'endpoint_type' => 'files.get',
        ];

        while (true) {
            $state = strtoupper((string) ($current['state'] ?? 'ACTIVE'));

            if ($state === 'ACTIVE') {
                Log::info('Gemini Video-to-Campaign file is active.', $this->safeLogContext($pollContext + [
                    'provider_file_state' => $state,
                ]));

                return $current;
            }

            if (in_array($state, ['FAILED', 'ERROR'], true)) {
                $context = $pollContext + [
                    'provider_file_state' => $state,
                    'reason' => 'gemini_file_processing_failed',
                ];

                Log::warning('Gemini Video-to-Campaign file processing failed.', $this->safeLogContext($context));

                throw VideoToCampaignProviderException::requestFailed('gemini_file_processing_failed', $context);
            }

            if (microtime(true) >= $deadline) {
                $context = $pollContext + [
                    'provider_file_state' => $state,
                    'reason' => 'gemini_file_processing_timeout',
                ];

                Log::warning('Gemini Video-to-Campaign file processing timed out.', $this->safeLogContext($context));

                throw VideoToCampaignProviderException::fileProcessingTimedOut($context);
            }

            sleep($pollInterval);
            $current = $this->getFile((string) $file['name']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getFile(string $name): array
    {
        $context = [
            'provider' => 'gemini',
            'model' => $this->model(),
            'stage' => 'get_file',
            'endpoint_type' => 'files.get',
        ];

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeoutSeconds())
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                ->get('/v1beta/'.$this->normalizeFileName($name));
        } catch (ConnectionException $exception) {
            $this->requestFailure('gemini_file_poll_connection_failed', $context, $exception);
        }

        if (! $response->successful()) {
            $this->requestFailure('gemini_file_poll_failed', $this->responseContext($response, $context));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw VideoToCampaignProviderException::invalidResponse('gemini_file_poll_invalid_response', $context);
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $file
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function requestAnalysis(array $file, MediaAsset $asset, string $prompt, array $schema): array
    {
        $uri = $file['uri'] ?? null;

        if (! is_string($uri) || trim($uri) === '') {
            throw VideoToCampaignProviderException::invalidResponse('gemini_file_uri_missing', [
                'provider' => 'gemini',
                'model' => $this->model(),
                'stage' => 'request_analysis',
                'endpoint_type' => 'models.generateContent',
            ]);
        }

        $mimeType = $asset->mime_type ?: ($file['mimeType'] ?? $file['mime_type'] ?? 'video/mp4');
        $mimeType = is_string($mimeType) && trim($mimeType) !== '' ? $mimeType : 'video/mp4';
        $context = [
            'provider' => 'gemini',
            'model' => $this->model(),
            'stage' => 'request_analysis',
            'endpoint_type' => 'models.generateContent',
            'media_asset_id' => $asset->getKey(),
            'mime_type' => $mimeType,
            'size_bytes' => $asset->size_bytes,
        ];
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'fileData' => [
                                'mimeType' => $mimeType,
                                'fileUri' => $uri,
                            ],
                        ],
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        Log::info('Gemini Video-to-Campaign analysis request started.', $this->safeLogContext($context));

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeoutSeconds())
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                ->post('/v1beta/models/'.$this->modelName().':generateContent', $payload);
        } catch (ConnectionException $exception) {
            $this->requestFailure('gemini_analysis_connection_failed', $context, $exception);
        }

        if (! $response->successful()) {
            $this->requestFailure('gemini_analysis_request_failed', $this->responseContext($response, $context));
        }

        $json = $response->json();

        if (! is_array($json)) {
            Log::warning('Gemini Video-to-Campaign provider returned an invalid response.', $this->safeLogContext($context + [
                'reason' => 'gemini_analysis_invalid_json_shape',
                'http_status' => $response->status(),
            ]));

            throw VideoToCampaignProviderException::invalidResponse('gemini_analysis_invalid_json_shape', $context + [
                'http_status' => $response->status(),
            ]);
        }

        Log::info('Gemini Video-to-Campaign analysis request completed.', $this->safeLogContext($context + [
            'http_status' => $response->status(),
        ]));

        return $json;
    }

    private function deleteFile(mixed $name): bool
    {
        if (! is_string($name) || trim($name) === '') {
            return false;
        }

        $context = [
            'provider' => 'gemini',
            'model' => $this->model(),
            'stage' => 'delete_file',
            'endpoint_type' => 'files.delete',
        ];

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeoutSeconds())
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                ->delete('/v1beta/'.$this->normalizeFileName($name));
        } catch (ConnectionException $exception) {
            Log::warning('Gemini Video-to-Campaign temporary file cleanup failed.', $this->safeLogContext($context + [
                'reason' => 'gemini_delete_temp_file_connection_failed',
                'exception_class' => $exception::class,
            ]));

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Gemini Video-to-Campaign temporary file cleanup failed.', $this->safeLogContext($this->responseContext($response, $context + [
                'reason' => 'gemini_delete_temp_file_failed',
            ])));

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function requestFailure(string $reason, array $context, ?Throwable $previous = null): never
    {
        $context = $this->safeLogContext($context + [
            'reason' => $reason,
            'exception_class' => $previous instanceof Throwable ? $previous::class : null,
        ]);

        Log::warning('Gemini Video-to-Campaign provider request failed.', $context);

        throw VideoToCampaignProviderException::requestFailed($reason, $context, $previous);
    }

    /**
     * @param  array<string, mixed>  $baseContext
     * @return array<string, mixed>
     */
    private function responseContext(Response $response, array $baseContext): array
    {
        $json = $response->json();
        $providerError = is_array($json) ? data_get($json, 'error.message') : null;
        $providerStatus = is_array($json) ? data_get($json, 'error.status') : null;
        $providerCode = is_array($json) ? data_get($json, 'error.code') : null;
        $requestId = $response->header('x-goog-request-id') ?? $response->header('x-request-id');
        $body = $response->body();

        return $baseContext + [
            'http_status' => $response->status(),
            'provider_error_code' => is_scalar($providerCode) ? $providerCode : null,
            'provider_error_status' => is_scalar($providerStatus) ? $providerStatus : null,
            'provider_error_message' => is_scalar($providerError) ? (string) $providerError : null,
            'provider_request_id' => is_scalar($requestId) ? (string) $requestId : null,
            'response_body_excerpt' => is_string($body) && $body !== '' ? substr($body, 0, 1500) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function safeLogContext(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            $safeValue = $this->sanitizeValue($value);

            if ($safeValue === null || $safeValue === '' || $safeValue === []) {
                continue;
            }

            $safe[$key] = $safeValue;
        }

        return $safe;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $safe = [];

            foreach ($value as $key => $item) {
                $safe[$key] = $this->sanitizeValue($item);
            }

            return $safe;
        }

        if (is_string($value)) {
            return $this->sanitizeText($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return get_debug_type($value);
    }

    private function sanitizeText(string $value): string
    {
        $apiKey = (string) config('video_to_campaign.gemini.api_key', '');

        if ($apiKey !== '') {
            $value = str_replace($apiKey, '[redacted-api-key]', $value);
        }

        $value = preg_replace('/gemini:\/\/[^\s"\']+/', '[redacted-provider-uri]', $value) ?? $value;
        $value = preg_replace('/files\/[A-Za-z0-9._\-]+/', 'files/[redacted]', $value) ?? $value;

        return substr($value, 0, 1500);
    }

    private function apiKey(): string
    {
        $apiKey = (string) config('video_to_campaign.gemini.api_key', '');

        if ($apiKey === '') {
            throw VideoToCampaignProviderException::missingConfiguration([
                'provider' => 'gemini',
                'stage' => 'configuration',
                'reason' => 'api_key_missing',
            ]);
        }

        return $apiKey;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('video_to_campaign.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
    }

    private function model(): string
    {
        $model = (string) config('video_to_campaign.gemini.model', '');

        if ($model === '') {
            throw VideoToCampaignProviderException::missingConfiguration([
                'provider' => 'gemini',
                'stage' => 'configuration',
                'reason' => 'model_missing',
            ]);
        }

        return $model;
    }

    private function modelName(): string
    {
        $model = $this->model();

        return str_starts_with($model, 'models/') ? substr($model, 7) : $model;
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) config('video_to_campaign.gemini.request_timeout_seconds', 180));
    }

    private function normalizeFileName(string $name): string
    {
        return ltrim($name, '/');
    }
}
