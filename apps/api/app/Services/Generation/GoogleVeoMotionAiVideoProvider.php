<?php

namespace App\Services\Generation;

use App\Contracts\MotionAiVideoProvider;
use App\Exceptions\MotionAiVideo\MotionAiVideoProviderException;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Support\MotionAiVideoCapabilities;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GoogleVeoMotionAiVideoProvider implements MotionAiVideoProvider
{
    public function __construct(
        private readonly MockMotionAiVideoProvider $planner,
        private readonly GoogleVeoMotionAiVideoPromptBuilder $prompt,
        private readonly GoogleVeoMotionAiVideoClient $client,
        private readonly GeneratedEditedVideoMediaService $videos,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $user = User::query()->findOrFail((int) ($input['user_id'] ?? 0));

        return $this->generateForUser($input, $user);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generateForUser(array $input, User $user): array
    {
        $startedAt = microtime(true);
        $generationId = (int) ($input['generation_id'] ?? 0);
        $project = $this->project($input, $user);
        $sourceMode = $this->sourceMode($input);
        $requestMode = $this->requestMode();
        $productImage = $this->optionalAsset($input, $project, $user, 'product_image_asset_id', 'image', 'resolve_product_image');
        $scenarioImage = $this->optionalAsset($input, $project, $user, 'scenario_image_asset_id', 'image', 'resolve_scenario_image');
        $providerRequestShape = $this->providerRequestShape($sourceMode, $productImage);
        $referenceImagesCount = $this->referenceImagesCount($providerRequestShape, $productImage);
        $context = $this->diagnostics(
            input: $input,
            project: $project,
            generationId: $generationId,
            sourceMode: $sourceMode,
            requestMode: $requestMode,
            providerRequestShape: $providerRequestShape,
            productImage: $productImage,
            scenarioImage: $scenarioImage,
            referenceImagesCount: $referenceImagesCount,
        );
        $plan = $this->planner->generate($input);
        $prompt = $this->prompt->build($input, $plan, $productImage, $scenarioImage);
        $requestPayload = $providerRequestShape === 'reference_images'
            ? $this->buildReferenceImagesRequest($prompt, $productImage, $input, $context)
            : $this->buildTextOnlyRequest($prompt, $input);

        Log::info('Submitting Google Veo Motion AI Video request.', $context + [
            'stage' => 'submit',
        ]);

        $submit = $this->client->submit($requestPayload, $context);
        $completion = $this->completion($submit, $context);
        $binary = $this->client->videoBinary($completion['video'], $context + ['stage' => 'download']);

        $this->assertValidMp4($binary, $context + ['stage' => 'download_validate']);

        try {
            $finalVideo = $this->videos->store(
                user: $user,
                project: $project,
                generationId: $generationId,
                videoBinary: $binary,
                metadata: $this->safeMetadata(
                    input: $input,
                    sourceMode: $sourceMode,
                    requestMode: $requestMode,
                    providerRequestShape: $providerRequestShape,
                    productImage: $productImage,
                    scenarioImage: $scenarioImage,
                    referenceImagesCount: $referenceImagesCount,
                    completion: $completion,
                    providerLatencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                    finalVideoAssetId: null,
                    outputSize: null,
                    prompt: $prompt,
                    submit: $submit,
                ) + [
                    'provider' => 'google_veo',
                    'workflow_key' => 'motion_ai_video',
                    'workflow' => 'motion-ai-video',
                    'mode' => 'real',
                    'visual_first_clip' => true,
                    'target_duration_seconds' => $this->duration($input),
                ],
                workflow: 'motion-ai-video',
                usageRole: 'edited_video_result',
                originalNamePrefix: 'motion-ai-video',
            );
        } catch (Throwable $exception) {
            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_store_failed',
                $context + ['stage' => 'store'],
                $exception,
            );
        }

        Log::info('Google Veo Motion AI Video generation completed.', [
            'provider' => 'google_veo',
            'workflow' => 'motion_ai_video',
            'generation_id' => $generationId ?: null,
            'project_id' => $project->getKey(),
            'final_video_asset_id' => $finalVideo->getKey(),
            'provider_request_shape' => $providerRequestShape,
            'product_image_sent_to_provider' => $this->productImageSentToProvider($providerRequestShape, $productImage),
        ]);

        return $this->outputPayload(
            plan: $plan,
            prompt: $prompt,
            input: $input,
            sourceMode: $sourceMode,
            requestMode: $requestMode,
            providerRequestShape: $providerRequestShape,
            productImage: $productImage,
            scenarioImage: $scenarioImage,
            referenceImagesCount: $referenceImagesCount,
            finalVideo: $finalVideo,
            submit: $submit,
            completion: $completion,
            providerLatencyMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function project(array $input, User $user): Project
    {
        $project = Project::query()
            ->whereKey((int) ($input['project_id'] ?? 0))
            ->where('user_id', $user->getKey())
            ->first();

        if (! $project instanceof Project) {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', [
                'provider' => 'google_veo',
                'workflow' => 'motion_ai_video',
                'stage' => 'resolve_project',
            ]);
        }

        return $project;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optionalAsset(array $input, Project $project, User $user, string $key, string $type, string $stage): ?MediaAsset
    {
        if ($this->nullableInt($input[$key] ?? null) === null) {
            return null;
        }

        return $this->asset($input, $project, $user, $key, $type, $stage);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function asset(array $input, Project $project, User $user, string $key, string $type, string $stage): MediaAsset
    {
        $assetId = $this->nullableInt($input[$key] ?? null);

        if ($assetId === null) {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', [
                'provider' => 'google_veo',
                'workflow' => 'motion_ai_video',
                'stage' => $stage,
            ]);
        }

        $asset = $user->mediaAssets()
            ->whereKey($assetId)
            ->where('type', $type)
            ->where(function ($query) use ($project): void {
                $query->where('project_id', $project->getKey())
                    ->orWhereNull('project_id');
            })
            ->first();

        if (! $asset instanceof MediaAsset) {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', [
                'provider' => 'google_veo',
                'workflow' => 'motion_ai_video',
                'stage' => $stage,
                'media_asset_id' => $assetId,
            ]);
        }

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $prompt
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function buildTextOnlyRequest(array $prompt, array $input): array
    {
        return [
            'instances' => [[
                'prompt' => $prompt['prompt'],
            ]],
            'parameters' => [
                'aspectRatio' => $this->aspectRatio($input),
                'durationSeconds' => $this->duration($input),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $prompt
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildReferenceImagesRequest(array $prompt, ?MediaAsset $productImage, array $input, array $context): array
    {
        if (! $productImage instanceof MediaAsset) {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', $context + [
                'stage' => 'build_reference_images_request',
                'response_format' => 'missing_product_image',
            ]);
        }

        return [
            'instances' => [[
                'prompt' => $prompt['prompt'],
                'referenceImages' => $this->referenceImages($productImage, $context),
            ]],
            'parameters' => [
                'aspectRatio' => $this->aspectRatio($input),
                'durationSeconds' => $this->duration($input),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function referenceImages(MediaAsset $productImage, array $context): array
    {
        return array_slice([[
            'image' => $this->inlineImage($productImage, $context + ['stage' => 'read_product_image_reference']),
            'referenceType' => 'asset',
        ]], 0, 3);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, array<string, string>>
     */
    private function inlineImage(MediaAsset $asset, array $context): array
    {
        try {
            $binary = Storage::disk($asset->disk)->get($asset->path);
        } catch (Throwable $exception) {
            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_source_asset_unavailable',
                $context + [
                    'media_asset_id' => $asset->getKey(),
                    'asset_type' => $asset->type,
                ],
                $exception,
            );
        }

        if (! is_string($binary) || $binary === '') {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', $context + [
                'media_asset_id' => $asset->getKey(),
                'asset_type' => $asset->type,
                'response_format' => 'empty_source_asset',
            ]);
        }

        return [
            'inlineData' => [
                'mimeType' => $asset->mime_type ?: 'image/jpeg',
                'data' => base64_encode($binary),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $submit
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function completion(array $submit, array $context): array
    {
        if (is_array($submit['video'] ?? null)) {
            return [
                'operation_name' => $submit['operation_name'] ?? null,
                'render_status' => 'done',
                'response_status' => 'done',
                'video' => $submit['video'],
                'render_time_ms' => $submit['submit_latency_ms'] ?? null,
            ];
        }

        $operationName = $submit['operation_name'] ?? null;

        if (! is_string($operationName) || trim($operationName) === '') {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', $context + [
                'stage' => 'submit',
                'response_format' => 'missing_operation_name',
            ]);
        }

        return $this->client->waitForCompletion($operationName, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertValidMp4(string $binary, array $context): void
    {
        $prefix = substr(ltrim($binary), 0, 256);
        $lower = strtolower($prefix);
        $hasFtypBox = str_contains(substr($binary, 0, 32), 'ftyp');

        if ($binary === '' || str_starts_with($lower, '<!doctype') || str_starts_with($lower, '<html') || ! $hasFtypBox) {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_invalid_response', $context + [
                'response_format' => str_starts_with($lower, '<') ? 'html_or_xml' : 'unknown_non_mp4',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $prompt
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $submit
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    private function outputPayload(
        array $plan,
        array $prompt,
        array $input,
        string $sourceMode,
        string $requestMode,
        string $providerRequestShape,
        ?MediaAsset $productImage,
        ?MediaAsset $scenarioImage,
        int $referenceImagesCount,
        MediaAsset $finalVideo,
        array $submit,
        array $completion,
        int $providerLatencyMs,
    ): array {
        $streamUrl = route('media-assets.stream', ['mediaAsset' => $finalVideo->getKey()], false);
        $downloadUrl = route('media-assets.download', ['mediaAsset' => $finalVideo->getKey()], false);
        $estimatedCost = $this->estimatedCost($input);
        $payload = $plan;
        $metadata = $this->safeMetadata(
            input: $input,
            sourceMode: $sourceMode,
            requestMode: $requestMode,
            providerRequestShape: $providerRequestShape,
            productImage: $productImage,
            scenarioImage: $scenarioImage,
            referenceImagesCount: $referenceImagesCount,
            completion: $completion,
            providerLatencyMs: $providerLatencyMs,
            finalVideoAssetId: $finalVideo->getKey(),
            outputSize: $finalVideo->size_bytes,
            prompt: $prompt,
            submit: $submit,
        );

        $payload['motion_summary'] = array_merge(is_array($plan['motion_summary'] ?? null) ? $plan['motion_summary'] : [], [
            'duration' => $this->duration($input),
            'requested_duration' => $this->requestedDuration($input),
            'aspect_ratio' => $this->aspectRatio($input),
            'requested_aspect_ratio' => $this->requestedAspectRatio($input),
            'source_mode' => $sourceMode,
            'render_available' => true,
            'visual_first_clip' => true,
            'final_video_asset_id' => $finalVideo->getKey(),
        ]);
        $payload['generation_prompt'] = array_merge(is_array($plan['generation_prompt'] ?? null) ? $plan['generation_prompt'] : [], [
            'main_prompt' => $prompt['prompt'],
            'negative_prompt' => $prompt['negative_prompt'] ?? data_get($plan, 'generation_prompt.negative_prompt'),
        ]);
        $payload['rendered_video_asset'] = [
            'id' => $finalVideo->getKey(),
            'stream_url' => $streamUrl,
            'download_url' => $downloadUrl,
            'original_name' => $finalVideo->original_name,
            'mime_type' => $finalVideo->mime_type,
            'size_bytes' => $finalVideo->size_bytes,
        ];
        $payload['final_video_asset_id'] = $finalVideo->getKey();
        $payload['final_video_stream_url'] = $streamUrl;
        $payload['final_video_download_url'] = $downloadUrl;
        $payload['provider_readiness'] = [
            'provider' => 'google_veo',
            'real_provider_required' => false,
            'render_available' => true,
            'recommended_provider_type' => $providerRequestShape === 'reference_images' ? 'image_reference_to_video' : 'text_to_video',
            'future_provider_inputs' => [
                'prompt',
                'campaign_context',
                'product_image_context',
                'duration',
                'aspect_ratio',
            ],
        ];
        $payload['metadata'] = array_merge(is_array($plan['metadata'] ?? null) ? $plan['metadata'] : [], $metadata + [
            'generation_type' => 'motion_ai_video',
            'provider' => 'google_veo',
            'mode' => 'real',
            'visual_first_clip' => true,
            'render_available' => true,
            'real_provider_enabled' => true,
            'estimated_provider_cost_usd' => $estimatedCost,
        ]);
        $payload['provider_metadata'] = $metadata + [
            'provider' => 'google_veo',
            'workflow' => 'motion_ai_video',
            'mode' => 'real',
            'visual_first_clip' => true,
            'response_status' => $completion['response_status'] ?? $completion['render_status'] ?? 'done',
            'estimated_provider_cost_usd' => $estimatedCost,
            'native_audio_ignored' => true,
            'prompt_summary' => $prompt['summary'] ?? [],
        ];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $completion
     * @param  array<string, mixed>  $prompt
     * @param  array<string, mixed>  $submit
     * @return array<string, mixed>
     */
    private function safeMetadata(
        array $input,
        string $sourceMode,
        string $requestMode,
        string $providerRequestShape,
        ?MediaAsset $productImage,
        ?MediaAsset $scenarioImage,
        int $referenceImagesCount,
        array $completion,
        int $providerLatencyMs,
        ?int $finalVideoAssetId,
        ?int $outputSize,
        array $prompt,
        array $submit,
    ): array {
        return [
            'source_mode' => $sourceMode,
            'provider' => 'google_veo',
            'model' => $this->model(),
            'request_mode' => $requestMode,
            'request_mode_summary' => $requestMode,
            'image_input_mode' => $requestMode,
            'image_reference_enabled' => $this->imageReferencesEnabled(),
            'product_image_asset_id' => $productImage?->getKey(),
            'product_image_sent_to_provider' => $this->productImageSentToProvider($providerRequestShape, $productImage),
            'image_input_sent' => $this->productImageSentToProvider($providerRequestShape, $productImage),
            'product_image_sent_as_reference' => $this->productImageSentToProvider($providerRequestShape, $productImage),
            'scenario_image_asset_id' => $scenarioImage?->getKey(),
            'scenario_image_sent_as_reference' => false,
            'scenario_image_used_as_prompt_context_only' => $scenarioImage instanceof MediaAsset,
            'scenario_image_prompt_context_only' => $scenarioImage instanceof MediaAsset,
            'campaign_generation_id' => $this->nullableInt($input['campaign_generation_id'] ?? null),
            'voiceover_generation_id' => $this->nullableInt($input['voiceover_generation_id'] ?? null),
            'has_manual_prompt' => $this->hasManualPrompt($input),
            'has_product_image' => $productImage instanceof MediaAsset,
            'has_scenario_image' => $scenarioImage instanceof MediaAsset,
            'reference_images_count' => $referenceImagesCount,
            'requested_duration_seconds' => $this->requestedDuration($input),
            'duration_requested_seconds' => $this->requestedDuration($input),
            'provider_duration_seconds' => $this->duration($input),
            'duration_provider_seconds' => $this->duration($input),
            'target_duration_seconds' => $this->duration($input),
            'requested_target_duration_seconds' => $this->requestedDuration($input),
            'duration_normalized_for_provider' => $this->requestedDuration($input) !== $this->duration($input),
            'requested_aspect_ratio' => $this->requestedAspectRatio($input),
            'aspect_ratio_requested' => $this->requestedAspectRatio($input),
            'provider_aspect_ratio' => $this->aspectRatio($input),
            'aspect_ratio_provider' => $this->aspectRatio($input),
            'aspect_ratio' => $this->aspectRatio($input),
            'provider_request_shape' => $providerRequestShape,
            'provider_operation_created' => is_string($submit['operation_name'] ?? null) && trim((string) $submit['operation_name']) !== '',
            'provider_render_status' => $completion['render_status'] ?? 'done',
            'provider_latency_ms' => $providerLatencyMs,
            'submit_latency_ms' => $submit['submit_latency_ms'] ?? null,
            'render_time_ms' => $completion['render_time_ms'] ?? null,
            'final_video_asset_id' => $finalVideoAssetId,
            'output_size' => $outputSize,
            'platform' => $input['platform'] ?? 'tiktok',
            'prompt_summary' => $prompt['summary'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function diagnostics(
        array $input,
        Project $project,
        int $generationId,
        string $sourceMode,
        string $requestMode,
        string $providerRequestShape,
        ?MediaAsset $productImage,
        ?MediaAsset $scenarioImage,
        int $referenceImagesCount,
    ): array {
        return [
            'provider' => 'google_veo',
            'workflow' => 'motion_ai_video',
            'generation_id' => $generationId ?: null,
            'project_id' => $project->getKey(),
            'source_mode' => $sourceMode,
            'model' => $this->model(),
            'request_mode' => $requestMode,
            'image_input_mode' => $requestMode,
            'provider_request_shape' => $providerRequestShape,
            'request_mode_summary' => $requestMode,
            'image_reference_enabled' => $this->imageReferencesEnabled(),
            'has_product_image' => $productImage instanceof MediaAsset,
            'has_scenario_image' => $scenarioImage instanceof MediaAsset,
            'product_image_asset_id' => $productImage?->getKey(),
            'scenario_image_asset_id' => $scenarioImage?->getKey(),
            'reference_images_count' => $referenceImagesCount,
            'product_image_sent_to_provider' => $this->productImageSentToProvider($providerRequestShape, $productImage),
            'requested_duration' => $this->requestedDuration($input),
            'provider_duration' => $this->duration($input),
            'requested_aspect_ratio' => $this->requestedAspectRatio($input),
            'provider_aspect_ratio' => $this->aspectRatio($input),
            'product_image_mime' => $productImage?->mime_type,
            'product_image_size_bytes' => $productImage?->size_bytes,
        ];
    }

    private function model(): string
    {
        return (string) config('motion_ai_video.google_veo.model', 'veo-3.1-generate-preview');
    }

    /** @param array<string, mixed> $input */
    private function sourceMode(array $input): string
    {
        return MotionAiVideoCapabilities::normalizeSourceMode(
            $input['source_mode'] ?? null,
            $input['campaign_generation_id'] ?? null,
            $input['product_image_asset_id'] ?? null,
        );
    }

    private function requestMode(): string
    {
        return MotionAiVideoCapabilities::normalizeRequestMode();
    }

    private function imageReferencesEnabled(): bool
    {
        return $this->requestMode() === MotionAiVideoCapabilities::REQUEST_MODE_REFERENCE_IMAGES_EXPERIMENTAL
            && (bool) config('motion_ai_video.google_veo.image_references_enabled', false)
            && (bool) config('motion_ai_video.google_veo.allow_experimental_image_input', false);
    }

    private function providerRequestShape(string $sourceMode, ?MediaAsset $productImage): string
    {
        if ($sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL
            && $this->imageReferencesEnabled()
            && $productImage instanceof MediaAsset) {
            return 'reference_images';
        }

        return 'text_only';
    }

    private function productImageSentToProvider(string $providerRequestShape, ?MediaAsset $productImage): bool
    {
        return $providerRequestShape === 'reference_images' && $productImage instanceof MediaAsset;
    }

    private function referenceImagesCount(string $providerRequestShape, ?MediaAsset $productImage): int
    {
        return $this->productImageSentToProvider($providerRequestShape, $productImage) ? 1 : 0;
    }

    private function hasManualPrompt(array $input): bool
    {
        foreach (['manual_script', 'creative_notes', 'scene_description', 'background_prompt'] as $key) {
            if (is_string($input[$key] ?? null) && trim((string) $input[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $input */
    private function duration(array $input): int
    {
        return MotionAiVideoCapabilities::defaultDurationSeconds();
    }

    /** @param array<string, mixed> $input */
    private function requestedDuration(array $input): int
    {
        return is_numeric($input['target_duration_seconds'] ?? null)
            ? max(1, (int) $input['target_duration_seconds'])
            : MotionAiVideoCapabilities::defaultDurationSeconds();
    }

    /** @param array<string, mixed> $input */
    private function aspectRatio(array $input): string
    {
        return match ($input['aspect_ratio'] ?? 'vertical_9_16') {
            'horizontal_16_9', 'landscape_16_9', '16:9' => '16:9',
            default => MotionAiVideoCapabilities::defaultAspectRatio(),
        };
    }

    /** @param array<string, mixed> $input */
    private function requestedAspectRatio(array $input): string
    {
        return is_scalar($input['aspect_ratio'] ?? null)
            ? trim((string) $input['aspect_ratio'])
            : 'vertical_9_16';
    }

    /** @param array<string, mixed> $input */
    private function estimatedCost(array $input): ?float
    {
        $rate = config('motion_ai_video.google_veo.estimated_cost_usd_per_second');

        if (! is_numeric($rate)) {
            return null;
        }

        return round($this->duration($input) * (float) $rate, 6);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
