<?php

namespace App\Services\Generation;

use App\Jobs\GenerateVideoToCampaignJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use Illuminate\Validation\ValidationException;

class VideoToCampaignGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
        private readonly VideoToCampaignProviderManager $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Project $project, User $user, array $options = []): Generation
    {
        $sourceVideo = $this->sourceVideoAsset($project, $user, $options);
        $provider = $this->providers->selectedProviderForUser($user);

        if ($provider === 'gemini') {
            $this->ensureGeminiMediaLimits($sourceVideo);
        }

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_video_to_campaign',
            creditCost: $this->tools->creditCost('video_to_campaign'),
            creditReason: 'Video-to-Campaign generation',
            dispatch: fn (int $generationId) => GenerateVideoToCampaignJob::dispatch($generationId),
            inputPayload: $this->inputPayload($project, $sourceVideo, $options),
            provider: $provider,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function sourceVideoAsset(Project $project, User $user, array $options): MediaAsset
    {
        $sourceVideoId = $options['source_video_asset_id'] ?? null;

        if ($sourceVideoId !== null && $sourceVideoId !== '') {
            $asset = $user->mediaAssets()
                ->whereKey((int) $sourceVideoId)
                ->where('project_id', $project->getKey())
                ->where('type', 'video')
                ->first();

            if ($asset instanceof MediaAsset) {
                return $asset;
            }

            throw ValidationException::withMessages([
                'source_video_asset_id' => 'The selected source video is unavailable for this project.',
            ]);
        }

        $asset = $project->mediaAssets()
            ->where('type', 'video')
            ->whereIn('usage_role', [
                'source_video',
                'raw_product_video',
                'campaign_source_video',
                'video_reference',
                'general',
            ])
            ->latest('created_at')
            ->latest('id')
            ->first();

        if ($asset instanceof MediaAsset) {
            return $asset;
        }

        throw ValidationException::withMessages([
            'source_video_asset_id' => 'Select or upload a source video asset before generating a video campaign.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function inputPayload(Project $project, MediaAsset $sourceVideo, array $options): array
    {
        $projectInput = is_array($project->input_payload) ? $project->input_payload : [];
        $settings = is_array($project->settings) ? $project->settings : [];

        return array_merge($projectInput, [
            'project_title' => $project->title,
            'project_type' => $project->type,
            'source_video_asset_id' => $sourceVideo->getKey(),
            'source_video_duration_seconds' => $this->durationSeconds($sourceVideo),
            'source_video_asset' => $this->assetSummary($sourceVideo),
            'product_name' => $this->stringOption($options, 'product_name') ?? $this->stringOption($projectInput, 'product_name') ?? $project->title,
            'product_description' => $this->stringOption($options, 'product_description') ?? $this->stringOption($projectInput, 'product_description'),
            'product_category' => $this->stringOption($options, 'product_category') ?? $this->stringOption($projectInput, 'product_category'),
            'target_audience' => $this->stringOption($options, 'target_audience') ?? $this->stringOption($projectInput, 'target_audience') ?? 'social commerce viewers',
            'campaign_context' => $this->stringOption($options, 'campaign_context') ?? $this->stringOption($projectInput, 'content_idea'),
            'selling_angle' => $this->stringOption($options, 'selling_angle') ?? $this->stringOption($projectInput, 'selling_angle'),
            'language' => $this->stringOption($options, 'language') ?? $this->stringOption($projectInput, 'language') ?? 'english',
            'tone' => $this->stringOption($options, 'tone') ?? $this->stringOption($projectInput, 'tone') ?? 'natural',
            'platform' => $this->stringOption($options, 'platform') ?? $this->stringOption($settings, 'platform') ?? 'tiktok',
            'notes' => $this->stringOption($options, 'notes'),
        ]);
    }

    private function ensureGeminiMediaLimits(MediaAsset $asset): void
    {
        $maxBytes = max(1, (int) config('video_to_campaign.gemini.max_file_size_mb', 50)) * 1024 * 1024;

        if ($asset->size_bytes !== null && $asset->size_bytes > $maxBytes) {
            throw ValidationException::withMessages([
                'source_video_asset_id' => 'The selected source video is too large for real video analysis.',
            ]);
        }

        $duration = $this->durationSeconds($asset);
        $maxDuration = max(1, (int) config('video_to_campaign.gemini.max_duration_seconds', 60));

        if ($duration !== null && $duration > $maxDuration) {
            throw ValidationException::withMessages([
                'source_video_asset_id' => 'The selected source video is too long for real video analysis.',
            ]);
        }
    }

    private function durationSeconds(MediaAsset $asset): ?float
    {
        $metadata = is_array($asset->metadata) ? $asset->metadata : [];

        foreach (['duration_seconds', 'duration', 'video.duration_seconds', 'video.duration'] as $key) {
            $value = data_get($metadata, $key);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $input */
    private function stringOption(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    private function assetSummary(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'usage_role' => $asset->usage_role,
            'type' => $asset->type,
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
            'metadata' => $asset->metadata,
        ];
    }
}
