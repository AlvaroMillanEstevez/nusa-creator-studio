<?php

namespace App\Services\Generation;

use App\Jobs\GenerateMotionAiVideoJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use App\Support\MotionAiVideoCapabilities;
use Illuminate\Support\Str;
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

        if ($sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_FIRST_FRAME
            && ! MotionAiVideoCapabilities::imageToVideoEnabledForUser($user)) {
            throw ValidationException::withMessages([
                'source_mode' => 'First-frame image-to-video mode is not enabled for the current provider configuration.',
            ]);
        }

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

        if ($sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_CAMPAIGN_ASSISTED
            && $campaignGeneration instanceof Generation
            && ! $this->hasUsableCampaignOutput($campaignGeneration)) {
            throw ValidationException::withMessages([
                'campaign_generation_id' => 'The selected campaign generation does not contain usable campaign output yet.',
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
            MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_FIRST_FRAME,
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
        $includeVoiceoverPlan = $this->boolOption($options, 'use_voiceover')
            ?? $this->boolOption($options, 'include_voiceover_plan')
            ?? true;
        $includeCaptionPlan = $this->boolOption($options, 'use_captions')
            ?? $this->boolOption($options, 'include_caption_plan')
            ?? true;
        $userPrimaryCta = $this->stringOption($options, 'primary_cta');
        $campaignPrimaryCta = $this->stringOption($projectInput, 'cta_preference');
        $primaryCta = $userPrimaryCta
            ?? $campaignPrimaryCta
            ?? 'See product details';
        $primaryCtaSource = $userPrimaryCta !== null
            ? 'user_provided'
            : ($campaignPrimaryCta !== null ? 'campaign_provided' : 'default_suggestion');

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
            'campaign_generation_summary' => $campaignGeneration instanceof Generation
                ? $this->campaignGenerationSummary($campaignGeneration)
                : null,
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
            'include_voiceover_plan' => $includeVoiceoverPlan,
            'include_caption_plan' => $includeCaptionPlan,
            'use_voiceover' => $includeVoiceoverPlan,
            'use_captions' => $includeCaptionPlan,
            'primary_cta' => $primaryCta,
            'primary_cta_source' => $primaryCtaSource,
            'user_negative_prompt' => $this->stringOption($options, 'negative_prompt'),
            'system_visual_constraints_applied' => true,
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

    private function hasUsableCampaignOutput(Generation $generation): bool
    {
        $summary = $this->campaignGenerationSummary($generation);

        foreach (['campaign_title', 'campaign_angle', 'product_angle', 'caption', 'cta', 'visual_direction', 'core_selling_proposition'] as $key) {
            if ($this->stringValue($summary[$key] ?? null) !== null) {
                return true;
            }
        }

        foreach (['hooks', 'benefits', 'cta_options'] as $key) {
            if (is_array($summary[$key] ?? null) && $summary[$key] !== []) {
                return true;
            }
        }

        return $this->stringValue(data_get($summary, 'script.voiceover_script')) !== null
            || (is_array(data_get($summary, 'script.scenes')) && data_get($summary, 'script.scenes') !== []);
    }

    /** @return array<string, mixed> */
    private function campaignGenerationSummary(Generation $generation): array
    {
        $output = is_array($generation->output_payload) ? $generation->output_payload : [];
        $summary = [
            'job_type' => $generation->job_type,
            'created_at' => $generation->created_at?->toISOString(),
            'campaign_title' => $this->firstString([
                data_get($output, 'campaign_title'),
                data_get($output, 'concept_title'),
                data_get($output, 'ad_summary.creative_angle'),
                data_get($output, 'video_summary.title'),
            ]),
            'product_name' => $this->firstString([
                data_get($output, 'product_name'),
                data_get($output, 'ad_summary.product_name'),
                data_get($output, 'video_summary.product_name'),
            ]),
            'product_description' => $this->firstString([
                data_get($output, 'product_description'),
                data_get($output, 'video_summary.product_description'),
                data_get($output, 'product_context.description'),
            ]),
            'target_audience' => $this->firstString([
                data_get($output, 'target_audience'),
                data_get($output, 'ad_summary.target_audience'),
                data_get($output, 'video_summary.target_audience'),
            ]),
            'campaign_angle' => $this->firstString([
                data_get($output, 'campaign_angle'),
                data_get($output, 'ad_summary.creative_angle'),
                data_get($output, 'creative_angle'),
            ]),
            'product_angle' => $this->firstString([
                data_get($output, 'product_angle'),
                data_get($output, 'core_selling_proposition'),
                data_get($output, 'ad_summary.main_cta'),
            ]),
            'core_selling_proposition' => $this->firstString([
                data_get($output, 'core_selling_proposition'),
                data_get($output, 'main_benefit'),
                data_get($output, 'proof_or_demo_line'),
            ]),
            'desired_tone' => $this->firstString([
                data_get($output, 'tone'),
                data_get($output, 'ad_summary.tone'),
                data_get($output, 'voice_direction'),
            ]),
            'visual_direction' => $this->firstString([
                data_get($output, 'visual_direction'),
                data_get($output, 'render_plan.timeline_strategy'),
                data_get($output, 'edit_plan.visual_direction'),
            ]),
            'caption' => $this->firstString([
                data_get($output, 'caption'),
                data_get($output, 'caption_variants.short_caption'),
                data_get($output, 'captions.0'),
            ]),
            'cta' => $this->firstString([
                data_get($output, 'cta'),
                data_get($output, 'script.CTA'),
                data_get($output, 'ad_script.CTA'),
                data_get($output, 'script_plan.CTA_line'),
                data_get($output, 'ad_summary.main_cta'),
            ]),
            'hooks' => $this->stringList(data_get($output, 'hooks'), ['hook', 'text', 'title']),
            'benefits' => $this->stringList(
                data_get($output, 'product_benefits') ?? data_get($output, 'benefits') ?? data_get($output, 'ad_script.product_benefit_sequence'),
                ['benefit', 'text', 'label']
            ),
            'cta_options' => $this->stringList(data_get($output, 'cta_options'), ['cta', 'text', 'label']),
            'hashtags' => $this->stringList(data_get($output, 'hashtags'), []),
            'script' => [
                'title' => $this->firstString([
                    data_get($output, 'script.title'),
                    data_get($output, 'main_script.title'),
                    data_get($output, 'ad_script.opening_hook'),
                ]),
                'voiceover_script' => $this->firstString([
                    data_get($output, 'voiceover_ready_script'),
                    data_get($output, 'narration_voice_script'),
                    data_get($output, 'main_script.voiceover_script'),
                    data_get($output, 'script.voiceover_script'),
                    data_get($output, 'ad_script.voiceover_ready_script'),
                ]),
                'scenes' => $this->sceneSummaries(
                    data_get($output, 'script.scenes')
                    ?? data_get($output, 'storyboard')
                    ?? data_get($output, 'edit_plan.timeline')
                ),
            ],
        ];

        return $this->pruneEmpty($summary);
    }

    /** @param array<int, string> $keys @return array<int, string> */
    private function stringList(mixed $value, array $keys): array
    {
        if (! is_array($value)) {
            $string = $this->stringValue($value);

            return $string === null ? [] : [$string];
        }

        $items = [];

        foreach ($value as $item) {
            $string = null;

            if (is_array($item)) {
                foreach ($keys as $key) {
                    $string = $this->stringValue($item[$key] ?? null);

                    if ($string !== null) {
                        break;
                    }
                }

                $string ??= $this->firstString(array_values($item));
            } else {
                $string = $this->stringValue($item);
            }

            if ($string !== null) {
                $items[] = $string;
            }
        }

        return array_values(array_unique(array_slice($items, 0, 8)));
    }

    /** @return array<int, array<string, string>> */
    private function sceneSummaries(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $scenes = [];

        foreach (array_slice($value, 0, 6) as $scene) {
            if (! is_array($scene)) {
                $text = $this->stringValue($scene);
                if ($text !== null) {
                    $scenes[] = ['visual_direction' => $text];
                }

                continue;
            }

            $scenes[] = $this->pruneEmpty([
                'visual_direction' => $this->firstString([
                    $scene['visual_direction'] ?? null,
                    $scene['instruction'] ?? null,
                    $scene['visual'] ?? null,
                ]),
                'voiceover' => $this->firstString([
                    $scene['voiceover'] ?? null,
                    $scene['narration_line'] ?? null,
                    $scene['voiceover_line'] ?? null,
                ]),
                'on_screen_text' => $this->firstString([
                    $scene['on_screen_text'] ?? null,
                    $scene['overlay_text'] ?? null,
                    $scene['caption'] ?? null,
                ]),
            ]);
        }

        return array_values(array_filter($scenes, fn (array $scene): bool => $scene !== []));
    }

    /** @param array<int, mixed> $values */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            $string = $this->stringValue($value);

            if ($string !== null) {
                return $string;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function pruneEmpty(array $value): array
    {
        $clean = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $item = $this->pruneEmpty($item);
            }

            if ($item === null || $item === '' || $item === []) {
                continue;
            }

            $clean[$key] = $item;
        }

        return $clean;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 700, '');
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
    private function boolOption(array $input, string $key): ?bool
    {
        if (! array_key_exists($key, $input)) {
            return null;
        }

        return filter_var($input[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /** @param array<string, mixed> $input */
    private function intOption(array $input, string $key): ?int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
