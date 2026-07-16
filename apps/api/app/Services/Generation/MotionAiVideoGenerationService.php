<?php

namespace App\Services\Generation;

use App\Jobs\GenerateMotionAiVideoJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use App\Support\MotionAiVideoCapabilities;
use Illuminate\Validation\ValidationException;

class MotionAiVideoGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
        private readonly MotionAiVideoProviderManager $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Project $project, User $user, array $options): Generation
    {
        $provider = $this->providers->selectedProviderForUser($user);
        $sourceMode = MotionAiVideoCapabilities::normalizeSourceMode(
            $options['source_mode'] ?? null,
            $options['campaign_generation_id'] ?? null,
            $options['product_image_asset_id'] ?? null,
        );

        if ($sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL
            && ! MotionAiVideoCapabilities::imageReferencesEnabledForUser($user)) {
            throw ValidationException::withMessages([
                'source_mode' => 'Product image visual reference mode is not enabled for the current provider configuration.',
            ]);
        }

        $productImage = $this->productImageRequiredForSourceMode($sourceMode)
            ? $this->requiredImageAsset(
                project: $project,
                user: $user,
                id: $options['product_image_asset_id'] ?? null,
                field: 'product_image_asset_id',
                message: 'Choose a product image for this Motion AI source mode.',
            )
            : $this->optionalImageAsset(
                project: $project,
                user: $user,
                id: $options['product_image_asset_id'] ?? null,
                field: 'product_image_asset_id',
                message: 'The selected product image is unavailable for this project.',
            );
        $scenarioImage = $this->optionalImageAsset(
            project: $project,
            user: $user,
            id: $options['scenario_image_asset_id'] ?? null,
            field: 'scenario_image_asset_id',
            message: 'The selected scenario image is unavailable for this project.',
        );
        $campaignGeneration = $this->optionalGeneration(
            project: $project,
            user: $user,
            id: $options['campaign_generation_id'] ?? null,
            field: 'campaign_generation_id',
            allowedJobTypes: [
                'generate_affiliate_campaign',
                'generate_video_to_campaign',
                'generate_product_video_script',
                'generate_auto_video_ad',
            ],
        );

        if ($sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_CAMPAIGN_ASSISTED && ! $campaignGeneration instanceof Generation) {
            throw ValidationException::withMessages([
                'campaign_generation_id' => 'Choose a completed campaign generation for campaign-assisted Motion AI Video.',
            ]);
        }

        $voiceoverGeneration = $this->optionalGeneration(
            project: $project,
            user: $user,
            id: $options['voiceover_generation_id'] ?? null,
            field: 'voiceover_generation_id',
            allowedJobTypes: ['generate_ai_voiceover'],
        );

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_motion_ai_video',
            creditCost: $this->tools->creditCost('motion_ai_video'),
            creditReason: $provider === 'google_veo'
                ? 'Google Veo Motion AI Video generation'
                : 'Mock Motion AI Video plan generation',
            dispatch: fn (int $generationId) => GenerateMotionAiVideoJob::dispatch($generationId),
            inputPayload: $this->inputPayload(
                project: $project,
                productImage: $productImage,
                scenarioImage: $scenarioImage,
                campaignGeneration: $campaignGeneration,
                voiceoverGeneration: $voiceoverGeneration,
                sourceMode: $sourceMode,
                options: $options,
            ),
            provider: $provider,
        );
    }

    private function productImageRequiredForSourceMode(string $sourceMode): bool
    {
        return in_array($sourceMode, [
            MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_CONTEXT,
            MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL,
        ], true);
    }

    private function requiredImageAsset(Project $project, User $user, mixed $id, string $field, string $message): MediaAsset
    {
        if ($id === null || $id === '') {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $this->imageAsset($project, $user, $id, $field, $message);
    }

    private function optionalImageAsset(Project $project, User $user, mixed $id, string $field, string $message): ?MediaAsset
    {
        if ($id === null || $id === '') {
            return null;
        }

        return $this->imageAsset($project, $user, $id, $field, $message);
    }

    private function imageAsset(Project $project, User $user, mixed $id, string $field, string $message): MediaAsset
    {
        $asset = $user->mediaAssets()
            ->whereKey((int) $id)
            ->where('type', 'image')
            ->where(function ($query): void {
                $query->whereNull('mime_type')
                    ->orWhere('mime_type', '!=', 'image/svg+xml');
            })
            ->where(function ($query) use ($project): void {
                $query->where('project_id', $project->getKey())
                    ->orWhereNull('project_id');
            })
            ->first();

        if ($asset instanceof MediaAsset) {
            return $asset;
        }

        throw ValidationException::withMessages([$field => $message]);
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
        ?MediaAsset $scenarioImage,
        ?Generation $campaignGeneration,
        ?Generation $voiceoverGeneration,
        string $sourceMode,
        array $options,
    ): array {
        $projectInput = is_array($project->input_payload) ? $project->input_payload : [];
        $settings = is_array($project->settings) ? $project->settings : [];
        $scriptSource = $this->stringOption($options, 'script_source')
            ?? ($campaignGeneration instanceof Generation ? 'campaign_script' : 'project_brief');

        return array_merge($projectInput, [
            'project_title' => $project->title,
            'project_type' => $project->type,
            'generation_type' => 'motion_ai_video',
            'source_mode' => $sourceMode,
            'product_image_asset_id' => $productImage?->getKey(),
            'product_image_asset' => $productImage instanceof MediaAsset ? $this->assetSummary($productImage) : null,
            'scenario_image_asset_id' => $scenarioImage?->getKey(),
            'scenario_image_asset' => $scenarioImage instanceof MediaAsset ? $this->assetSummary($scenarioImage) : null,
            'campaign_generation_id' => $campaignGeneration?->getKey(),
            'campaign_generation_summary' => $campaignGeneration?->output_payload,
            'voiceover_generation_id' => $voiceoverGeneration?->getKey(),
            'voiceover_generation_summary' => $voiceoverGeneration?->output_payload,
            'script_source' => $scriptSource,
            'manual_script' => $this->stringOption($options, 'manual_script'),
            'creative_notes' => $this->stringOption($options, 'creative_notes'),
            'platform' => $this->stringOption($options, 'platform') ?? $this->stringOption($settings, 'platform') ?? 'tiktok',
            'target_duration_seconds' => $this->intOption($options, 'target_duration_seconds') ?? MotionAiVideoCapabilities::defaultDurationSeconds(),
            'visual_style' => $this->stringOption($options, 'visual_style') ?? 'realistic_product',
            'motion_style' => $this->stringOption($options, 'motion_style') ?? 'slow_push_in',
            'camera_direction' => $this->stringOption($options, 'camera_direction'),
            'scene_description' => $this->stringOption($options, 'scene_description'),
            'background_prompt' => $this->stringOption($options, 'background_prompt'),
            'negative_prompt' => $this->stringOption($options, 'negative_prompt'),
            'aspect_ratio' => $this->stringOption($options, 'aspect_ratio') ?? 'vertical_9_16',
            'include_voiceover_plan' => (bool) ($options['include_voiceover_plan'] ?? true),
            'include_caption_plan' => (bool) ($options['include_caption_plan'] ?? true),
            'primary_cta' => $this->stringOption($options, 'primary_cta')
                ?? $this->stringOption($projectInput, 'cta_preference')
                ?? 'See product details',
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
