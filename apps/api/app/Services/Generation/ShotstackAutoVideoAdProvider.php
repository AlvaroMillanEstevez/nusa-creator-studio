<?php

namespace App\Services\Generation;

use App\Contracts\AutoVideoAdProvider;
use App\Exceptions\AutoVideoEditor\AutoVideoEditorProviderException;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Media\TemporaryProviderAssetUrlService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShotstackAutoVideoAdProvider implements AutoVideoAdProvider
{
    public function __construct(
        private readonly TemporaryProviderAssetUrlService $assetUrls,
        private readonly MockAutoVideoAdProvider $planner,
        private readonly ShotstackAutoVideoAdTimelineBuilder $timelineBuilder,
        private readonly ShotstackAutoVideoEditorClient $client,
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
        $productImage = $this->asset($input, $project, $user, 'product_image_asset_id', 'image', 'resolve_product_image');
        $sourceVideo = $this->asset($input, $project, $user, 'source_video_asset_id', 'video', 'resolve_source_video');
        $voiceoverAsset = $this->voiceoverAsset($input, $project, $user);

        if (! $productImage instanceof MediaAsset && ! $sourceVideo instanceof MediaAsset) {
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', [
                'provider' => 'shotstack',
                'workflow' => 'auto_video_ads',
                'stage' => 'resolve_visual_assets',
            ]);
        }

        $context = [
            'provider' => 'shotstack',
            'workflow' => 'auto_video_ads',
            'generation_id' => $generationId ?: null,
            'project_id' => $project->getKey(),
            'product_image_asset_id' => $productImage?->getKey(),
            'source_video_asset_id' => $sourceVideo?->getKey(),
            'voiceover_asset_id' => $voiceoverAsset?->getKey(),
        ];

        $productImageUrl = $productImage instanceof MediaAsset
            ? $this->assetUrls->create($productImage, 'shotstack')
            : null;
        $sourceVideoUrl = $sourceVideo instanceof MediaAsset
            ? $this->assetUrls->create($sourceVideo, 'shotstack')
            : null;
        $voiceoverUrl = $voiceoverAsset instanceof MediaAsset
            ? $this->assetUrls->create($voiceoverAsset, 'shotstack')
            : null;

        $plannerInput = array_merge($input, [
            'voiceover_duration_seconds' => $this->assetDuration($voiceoverAsset),
        ]);
        $plan = $this->planner->generate($plannerInput);
        $timeline = $this->timelineBuilder->build(
            productImageUrl: $productImageUrl,
            sourceVideoUrl: $sourceVideoUrl,
            voiceoverUrl: $voiceoverUrl,
            plan: $plan,
            input: $plannerInput,
        );
        $submit = $this->client->submitRender($timeline['edit'], $context);
        $renderId = (string) $submit['render_id'];
        $completion = $this->client->waitForCompletion($renderId, $context + ['render_id' => $renderId]);
        $downloaded = $this->client->download((string) $completion['output_url'], $context + [
            'render_id' => $renderId,
        ]);

        $this->assertValidMp4($downloaded, $context + ['render_id' => $renderId]);

        try {
            $finalVideo = $this->videos->store(
                user: $user,
                project: $project,
                generationId: $generationId,
                videoBinary: $downloaded,
                metadata: [
                    'provider' => 'shotstack',
                    'workflow_key' => 'auto_video_ads',
                    'ad_format' => $plannerInput['ad_format'] ?? null,
                    'platform' => $plannerInput['platform'] ?? null,
                    'visual_source_type' => $plannerInput['visual_source_type'] ?? null,
                    'render_id' => $renderId,
                    'render_status' => $completion['render_status'] ?? null,
                    'final_duration_seconds' => data_get($timeline, 'timeline_summary.final_duration_seconds'),
                    'product_image_asset_id' => $productImage?->getKey(),
                    'source_video_asset_id' => $sourceVideo?->getKey(),
                    'voiceover_asset_id' => $voiceoverAsset?->getKey(),
                    'campaign_generation_id' => $this->nullableInt($plannerInput['campaign_generation_id'] ?? null),
                    'timeline_summary' => $timeline['timeline_summary'] ?? null,
                    'render_settings' => $timeline['render_settings'] ?? null,
                    'captions_plan' => $timeline['captions_plan'] ?? null,
                    'render_duration_ms' => $completion['render_time_ms'] ?? null,
                ],
                workflow: 'auto-video-ads',
                usageRole: 'edited_video_result',
                originalNamePrefix: 'auto-video-ad',
            );
        } catch (Throwable $exception) {
            throw AutoVideoEditorProviderException::requestFailed(
                'shotstack_download_failed',
                $context + ['stage' => 'store', 'render_id' => $renderId],
                $exception,
            );
        }

        Log::info('Shotstack Auto Video Ads render completed.', [
            'provider' => 'shotstack',
            'workflow' => 'auto_video_ads',
            'generation_id' => $generationId ?: null,
            'project_id' => $project->getKey(),
            'render_id' => $renderId,
            'final_video_asset_id' => $finalVideo->getKey(),
        ]);

        return $this->outputPayload(
            plan: $plan,
            input: $plannerInput,
            productImage: $productImage,
            sourceVideo: $sourceVideo,
            voiceoverAsset: $voiceoverAsset,
            finalVideo: $finalVideo,
            timeline: $timeline,
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
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', [
                'provider' => 'shotstack',
                'workflow' => 'auto_video_ads',
                'stage' => 'resolve_project',
            ]);
        }

        return $project;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function asset(array $input, Project $project, User $user, string $key, string $type, string $stage): ?MediaAsset
    {
        $assetId = $this->nullableInt($input[$key] ?? null);

        if ($assetId === null) {
            return null;
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
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', [
                'provider' => 'shotstack',
                'workflow' => 'auto_video_ads',
                'stage' => $stage,
                'media_asset_id' => $assetId,
            ]);
        }

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function voiceoverAsset(array $input, Project $project, User $user): ?MediaAsset
    {
        $assetId = $this->nullableInt($input['voiceover_audio_asset_id'] ?? null)
            ?? $this->nullableInt(data_get($input, 'voiceover_generation_summary.audio_asset_id'))
            ?? $this->nullableInt(data_get($input, 'voiceover_generation_summary.audio.asset_id'))
            ?? $this->nullableInt(data_get($input, 'voiceover_generation_summary.audio.id'));

        if ($assetId === null) {
            return null;
        }

        $asset = $user->mediaAssets()
            ->whereKey($assetId)
            ->where('type', 'audio')
            ->where(function ($query) use ($project): void {
                $query->where('project_id', $project->getKey())
                    ->orWhereNull('project_id');
            })
            ->first();

        if (! $asset instanceof MediaAsset) {
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', [
                'provider' => 'shotstack',
                'workflow' => 'auto_video_ads',
                'stage' => 'resolve_voiceover',
                'voiceover_asset_id' => $assetId,
            ]);
        }

        return $asset;
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
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', $context + [
                'stage' => 'download_validate',
                'response_format' => str_starts_with($lower, '<') ? 'html_or_xml' : 'unknown_non_mp4',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $timeline
     * @param  array<string, mixed>  $submit
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    private function outputPayload(
        array $plan,
        array $input,
        ?MediaAsset $productImage,
        ?MediaAsset $sourceVideo,
        ?MediaAsset $voiceoverAsset,
        MediaAsset $finalVideo,
        array $timeline,
        array $submit,
        array $completion,
        int $providerLatencyMs,
    ): array {
        $timelineSummary = is_array($timeline['timeline_summary'] ?? null) ? $timeline['timeline_summary'] : [];
        $renderSettings = is_array($timeline['render_settings'] ?? null) ? $timeline['render_settings'] : [];
        $estimatedCost = config('auto_video_ad.shotstack.estimated_provider_cost_usd')
            ?? config('auto_video_editor.shotstack.estimated_provider_cost_usd');
        $estimatedCost = is_numeric($estimatedCost) ? (float) $estimatedCost : null;
        $streamUrl = route('media-assets.stream', ['mediaAsset' => $finalVideo->getKey()], false);
        $downloadUrl = route('media-assets.download', ['mediaAsset' => $finalVideo->getKey()], false);

        $payload = $plan;
        $payload['timeline'] = $timeline['timeline'] ?? [];
        $payload['clip_sequence'] = $timeline['clip_sequence'] ?? [];
        $payload['captions_plan'] = $timeline['captions_plan'] ?? [];
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
        $payload['render_plan'] = array_merge(is_array($plan['render_plan'] ?? null) ? $plan['render_plan'] : [], [
            'renderer' => 'shotstack',
            'render_ready' => true,
            'render_available' => true,
            'missing_items' => [],
            'timeline_strategy' => 'Render a product-focused paid ad with clear hook, readable overlays and one final CTA.',
            'shotstack_render_pending' => false,
        ]);
        $payload['metadata'] = array_merge(is_array($plan['metadata'] ?? null) ? $plan['metadata'] : [], [
            'generation_type' => 'auto_video_ad',
            'provider' => 'shotstack',
            'mode' => 'real',
            'visual_source_type' => $input['visual_source_type'] ?? null,
            'product_image_asset_id' => $productImage?->getKey(),
            'source_video_asset_id' => $sourceVideo?->getKey(),
            'voiceover_asset_id' => $voiceoverAsset?->getKey(),
            'voiceover_generation_id' => $this->nullableInt($input['voiceover_generation_id'] ?? null),
            'campaign_generation_id' => $this->nullableInt($input['campaign_generation_id'] ?? null),
            'target_duration_seconds' => $input['target_duration_seconds'] ?? null,
            'final_duration_seconds' => $timelineSummary['final_duration_seconds'] ?? null,
            'render_available' => true,
            'shotstack_render_pending' => false,
            'provider_render_status' => 'done',
            'render_id' => $submit['render_id'] ?? null,
            'final_video_asset_id' => $finalVideo->getKey(),
            'timeline_summary' => $timelineSummary,
        ]);
        $payload['provider_metadata'] = [
            'provider' => 'shotstack',
            'workflow' => 'auto_video_ads',
            'mode' => 'real',
            'render_id' => $submit['render_id'] ?? null,
            'render_status' => $completion['render_status'] ?? null,
            'provider_render_status' => 'done',
            'final_video_asset_id' => $finalVideo->getKey(),
            'output_size' => $finalVideo->size_bytes,
            'final_duration_seconds' => $timelineSummary['final_duration_seconds'] ?? null,
            'source_clip_ranges_used' => $timelineSummary['source_clip_ranges_used'] ?? [],
            'repeated_clip_count' => $timelineSummary['repeated_clip_count'] ?? null,
            'repeated_clip_reason' => $timelineSummary['repeated_clip_reason'] ?? null,
            'diverse_source_segments_used' => $timelineSummary['diverse_source_segments_used'] ?? null,
            'invalid_clip_ranges_discarded' => $timelineSummary['invalid_clip_ranges_discarded'] ?? null,
            'caption_schedule' => $timelineSummary['caption_schedule'] ?? [],
            'deduped_caption_count' => $timelineSummary['deduped_caption_count'] ?? null,
            'repeated_captions_removed' => $timelineSummary['repeated_captions_removed'] ?? null,
            'captions_overlapping' => $timelineSummary['captions_overlapping'] ?? false,
            'caption_visual_strategy' => $timelineSummary['caption_visual_strategy'] ?? null,
            'render_settings' => $renderSettings,
            'provider_latency_ms' => $providerLatencyMs,
            'render_time_ms' => $completion['render_time_ms'] ?? null,
            'asset_url_ttl_minutes' => $this->assetUrls->ttlMinutes(),
            'estimated_provider_cost_usd' => $estimatedCost,
        ];

        return $payload;
    }

    private function assetDuration(?MediaAsset $asset): ?float
    {
        if (! $asset instanceof MediaAsset || ! is_array($asset->metadata)) {
            return null;
        }

        $duration = $asset->metadata['duration_seconds'] ?? null;

        return is_numeric($duration) && (float) $duration > 0 ? (float) $duration : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
