<?php

namespace App\Services\Generation;

use App\Jobs\GenerateAutoVideoAdJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use Illuminate\Validation\ValidationException;

class AutoVideoAdGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
        private readonly AutoVideoAdProviderManager $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Project $project, User $user, array $options): Generation
    {
        $productImage = $this->optionalAsset(
            project: $project,
            user: $user,
            id: $options['product_image_asset_id'] ?? null,
            type: 'image',
            field: 'product_image_asset_id',
        );
        $sourceVideo = $this->optionalAsset(
            project: $project,
            user: $user,
            id: $options['source_video_asset_id'] ?? null,
            type: 'video',
            field: 'source_video_asset_id',
        );

        if (! $productImage instanceof MediaAsset && ! $sourceVideo instanceof MediaAsset) {
            throw ValidationException::withMessages([
                'product_image_asset_id' => 'Choose a product image or source video before generating an ad plan.',
                'source_video_asset_id' => 'Choose a product image or source video before generating an ad plan.',
            ]);
        }

        $campaignGeneration = $this->optionalGeneration(
            project: $project,
            user: $user,
            id: $options['campaign_generation_id'] ?? null,
            field: 'campaign_generation_id',
            allowedJobTypes: [
                'generate_affiliate_campaign',
                'generate_video_to_campaign',
                'generate_product_video_script',
            ],
        );
        $voiceoverGeneration = $this->optionalGeneration(
            project: $project,
            user: $user,
            id: $options['voiceover_generation_id'] ?? null,
            field: 'voiceover_generation_id',
            allowedJobTypes: ['generate_ai_voiceover'],
        );

        $provider = $this->providers->selectedProviderForUser($user);

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_auto_video_ad',
            creditCost: $this->tools->creditCost('auto_video_ads'),
            creditReason: $provider === 'shotstack'
                ? 'Auto Video Ads Shotstack render'
                : 'Mock Auto Video Ads plan generation',
            dispatch: fn (int $generationId) => GenerateAutoVideoAdJob::dispatch($generationId),
            inputPayload: $this->inputPayload(
                project: $project,
                productImage: $productImage,
                sourceVideo: $sourceVideo,
                campaignGeneration: $campaignGeneration,
                voiceoverGeneration: $voiceoverGeneration,
                options: $options,
            ),
            provider: $provider,
        );
    }

    private function optionalAsset(Project $project, User $user, mixed $id, string $type, string $field): ?MediaAsset
    {
        if ($id === null || $id === '') {
            return null;
        }

        $asset = $user->mediaAssets()
            ->whereKey((int) $id)
            ->where('type', $type)
            ->where(function ($query) use ($project): void {
                $query->where('project_id', $project->getKey())
                    ->orWhereNull('project_id');
            })
            ->first();

        if ($asset instanceof MediaAsset) {
            return $asset;
        }

        throw ValidationException::withMessages([
            $field => $type === 'image'
                ? 'The selected product image is unavailable for this project.'
                : 'The selected source video is unavailable for this project.',
        ]);
    }

    /**
     * @param  array<int, string>  $allowedJobTypes
     */
    private function optionalGeneration(Project $project, User $user, mixed $id, string $field, array $allowedJobTypes): ?Generation
    {
        if ($id === null || $id === '') {
            return null;
        }

        $generation = $user->generations()
            ->whereKey((int) $id)
            ->where('project_id', $project->getKey())
            ->whereIn('job_type', $allowedJobTypes)
            ->where('status', Generation::STATUS_COMPLETED)
            ->first();

        if ($generation instanceof Generation) {
            return $generation;
        }

        throw ValidationException::withMessages([
            $field => $field === 'campaign_generation_id'
                ? 'The selected campaign source is unavailable for this project.'
                : 'The selected voiceover is unavailable for this project.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function inputPayload(
        Project $project,
        ?MediaAsset $productImage,
        ?MediaAsset $sourceVideo,
        ?Generation $campaignGeneration,
        ?Generation $voiceoverGeneration,
        array $options,
    ): array {
        $projectInput = is_array($project->input_payload) ? $project->input_payload : [];
        $settings = is_array($project->settings) ? $project->settings : [];
        $visualSourceType = match (true) {
            $productImage instanceof MediaAsset && $sourceVideo instanceof MediaAsset => 'hybrid',
            $sourceVideo instanceof MediaAsset => 'video',
            default => 'image',
        };

        return array_merge($projectInput, [
            'project_title' => $project->title,
            'project_type' => $project->type,
            'generation_type' => 'auto_video_ad',
            'ad_format' => $this->stringOption($options, 'ad_format') ?? 'vertical_9_16',
            'platform' => $this->stringOption($options, 'platform') ?? $this->stringOption($settings, 'platform') ?? 'tiktok_ads',
            'ad_goal' => $this->stringOption($options, 'ad_goal') ?? 'sales',
            'target_duration_seconds' => $this->intOption($options, 'target_duration_seconds') ?? 15,
            'tone' => $this->stringOption($options, 'tone') ?? 'direct_response',
            'visual_style' => $this->stringOption($options, 'visual_style') ?? 'clean_product',
            'hook_style' => $this->stringOption($options, 'hook_style') ?? 'benefit_first',
            'primary_cta' => $this->stringOption($options, 'primary_cta'),
            'offer_text' => $this->stringOption($options, 'offer_text'),
            'landing_page_context' => $this->stringOption($options, 'landing_page_context'),
            'audience_override' => $this->stringOption($options, 'audience_override'),
            'include_voiceover_script' => (bool) ($options['include_voiceover_script'] ?? true),
            'include_caption_variants' => (bool) ($options['include_caption_variants'] ?? true),
            'visual_source_type' => $visualSourceType,
            'product_image_asset_id' => $productImage?->getKey(),
            'product_image_asset' => $productImage instanceof MediaAsset ? $this->assetSummary($productImage) : null,
            'source_video_asset_id' => $sourceVideo?->getKey(),
            'source_video_asset' => $sourceVideo instanceof MediaAsset ? $this->assetSummary($sourceVideo) : null,
            'campaign_generation_id' => $campaignGeneration?->getKey(),
            'campaign_generation_summary' => $campaignGeneration?->output_payload,
            'voiceover_generation_id' => $voiceoverGeneration?->getKey(),
            'voiceover_generation_summary' => $voiceoverGeneration?->output_payload,
            'voiceover_ready' => $voiceoverGeneration instanceof Generation,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function assetSummary(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'type' => $asset->type,
            'usage_role' => $asset->usage_role,
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
            'metadata' => $asset->metadata,
        ];
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

    /** @param array<string, mixed> $input */
    private function intOption(array $input, string $key): ?int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
