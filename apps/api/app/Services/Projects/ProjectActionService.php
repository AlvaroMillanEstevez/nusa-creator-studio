<?php

namespace App\Services\Projects;

use App\Models\CreatorTool;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\AiVoiceoverSourceResolver;
use App\Support\CampaignWorkflowMetadata;
use App\Support\MotionAiVideoCapabilities;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProjectActionService
{
    public function __construct(
        private readonly AiVoiceoverSourceResolver $voiceoverSources,
        private readonly ProjectActionAccessService $access,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actionsFor(Project $project, User $user): array
    {
        $project->loadMissing(['generations', 'mediaAssets']);
        $tools = CreatorTool::query()
            ->whereIn('key', [
                'affiliate_campaign',
                'ai_voiceover',
                'video_to_campaign',
                'auto_video_editor',
                'auto_video_ads',
                'fashion_try_on',
                'motion_ai_video',
            ])
            ->get()
            ->keyBy('key');

        return [
            $this->generateCampaignAction($project, $user, $tools->get('affiliate_campaign')),
            $this->voiceoverFromScriptAction($project, $user, $tools->get('ai_voiceover')),
            $this->videoToCampaignAction($project, $user, $tools->get('video_to_campaign')),
            $this->autoVideoEditorAction($project, $user, $tools->get('auto_video_editor')),
            $this->videoAdAction($project, $user, $tools->get('auto_video_ads')),
            $this->fashionTryOnAction($project, $user, $tools->get('fashion_try_on')),
            $this->motionVideoAction($project, $user, $tools->get('motion_ai_video')),
            $this->avatarPresenterAction($user),
        ];
    }

    /**
     * @param  Collection<int, Generation>  $generations
     */
    public function recommendedVoiceoverSource(Collection $generations): ?Generation
    {
        return $this->voiceoverSourceGenerations($generations)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function generateCampaignAction(Project $project, User $user, ?CreatorTool $tool): array
    {
        $input = is_array($project->input_payload) ? $project->input_payload : [];
        $requirements = [
            $this->requirement(
                type: 'input',
                key: 'product_name',
                label: 'Product name',
                satisfied: $this->hasText($input['product_name'] ?? null),
                helpText: 'Name the product or offer for this campaign.',
            ),
            $this->requirement(
                type: 'input',
                key: 'product_description',
                label: 'Product description',
                satisfied: $this->hasText($input['product_description'] ?? null),
                helpText: 'Add a short, factual product description.',
            ),
        ];
        $hasBrief = $this->missingRequirementCount($requirements) === 0;
        $existingCampaigns = $this->generationCount($project, 'generate_affiliate_campaign');

        return $this->action(
            user: $user,
            key: 'generate_campaign',
            title: 'Generate campaign',
            description: 'Create hooks, script, caption, hashtags and practical filming notes from this project brief.',
            serviceTier: 'basic',
            outputType: 'script_pack',
            tool: $tool,
            status: $hasBrief ? 'available' : 'configure',
            actionMode: $hasBrief ? 'direct' : 'configure',
            primaryLabel: $existingCampaigns > 0 ? 'Generate another campaign version' : 'Generate campaign',
            primaryAction: 'generate_campaign',
            requirements: $requirements,
            missingRequirementsCount: $this->missingRequirementCount($requirements),
            reason: $hasBrief ? 'Project has enough product brief context.' : 'Complete the project brief before generating.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-campaign",
            creditCost: $tool?->credit_cost,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function voiceoverFromScriptAction(Project $project, User $user, ?CreatorTool $tool): array
    {
        $sources = $this->voiceoverSourceGenerations($project->generations);
        $source = $sources->first();
        $hasSource = $source instanceof Generation;
        $existingVoiceovers = $this->generationCount($project, 'generate_ai_voiceover');

        $requirements = [
            $this->requirement(
                type: 'source_generation',
                key: 'campaign_or_script_generation',
                label: 'Campaign or product-to-video script',
                satisfied: $hasSource,
                helpText: 'Choose a completed campaign or product-to-video script from this project.',
            ),
            $this->requirement(
                type: 'source_text',
                key: 'source_text',
                label: 'Source script text',
                satisfied: $hasSource,
                helpText: 'Write or paste a source script if no saved generation fits.',
            ),
        ];

        return $this->action(
            user: $user,
            key: 'generate_voiceover_from_script',
            title: 'Create voiceover',
            description: 'Generate a voiceover from a completed campaign, product-to-video script or direct source text.',
            serviceTier: 'advanced',
            outputType: 'audio',
            tool: $tool,
            status: $hasSource ? 'available' : 'configure',
            actionMode: $hasSource ? 'direct' : 'configure',
            primaryLabel: $existingVoiceovers > 0 ? 'Create another voiceover' : 'Create voiceover',
            primaryAction: 'generate_voiceover',
            requirements: $requirements,
            recommendedSources: $this->recommendedSources($sources),
            missingRequirementsCount: $hasSource ? 0 : 1,
            reason: $hasSource
                ? 'A completed script is available for voiceover generation.'
                : 'Choose a source script or write one in the launcher.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-voiceover",
            sourceGenerationId: $source?->getKey(),
            creditCost: $tool?->credit_cost,
        );
    }

    /** @return array<string, mixed> */
    private function videoToCampaignAction(Project $project, User $user, ?CreatorTool $tool): array
    {
        $hasSourceVideo = $this->hasAnyAssetRole($project, [
            'source_video',
            'raw_product_video',
            'campaign_source_video',
            'video_reference',
            'general',
        ], 'video');
        $existingVideoCampaigns = $this->generationCount($project, 'generate_video_to_campaign');

        $requirements = [
            $this->requirement(
                type: 'media_asset',
                key: 'source_video',
                label: 'Source product video',
                satisfied: $hasSourceVideo,
                helpText: 'Choose or upload a raw product video for the campaign analysis.',
                suggestedUsageRole: 'campaign_source_video',
            ),
        ];

        return $this->action(
            user: $user,
            key: 'generate_video_to_campaign',
            title: 'Analyze product footage',
            description: 'Turn source product footage into hooks, narration, captions, recommended cuts and an edit plan.',
            serviceTier: 'advanced',
            outputType: 'assisted_media',
            tool: $tool,
            status: 'configure',
            actionMode: 'configure',
            primaryLabel: $existingVideoCampaigns > 0 ? 'Generate another video analysis kit' : 'Generate video analysis kit',
            primaryAction: 'configure_video_to_campaign',
            requirements: $requirements,
            recommendedAssets: $this->recommendedAssets($project, [
                'campaign_source_video',
                'raw_product_video',
                'source_video',
                'video_reference',
                'general',
            ]),
            missingRequirementsCount: $hasSourceVideo ? 0 : 1,
            reason: $hasSourceVideo
                ? 'A project video is available for campaign analysis.'
                : 'Upload or choose a product video in the setup flow.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-video-campaign",
            creditCost: $tool?->credit_cost,
        );
    }

    /** @return array<string, mixed> */
    private function autoVideoEditorAction(Project $project, User $user, ?CreatorTool $tool): array
    {
        $videoCampaignSources = $this->completedGenerations($project, 'generate_video_to_campaign')
            ->filter(fn (Generation $generation): bool => CampaignWorkflowMetadata::isAutoVideoEditorEligible($generation->output_payload ?? []));
        $hasReusableAnalysis = $videoCampaignSources->isNotEmpty();
        $hasSourceVideo = $this->hasAnyAssetRole($project, [
            'source_video',
            'raw_product_video',
            'campaign_source_video',
            'video_reference',
            'general',
        ], 'video');
        $voiceoverSources = $project->generations
            ->filter(fn (Generation $generation): bool => $generation->status === Generation::STATUS_COMPLETED
                && $generation->job_type === 'generate_ai_voiceover')
            ->sortByDesc(fn (Generation $generation): string => $generation->created_at?->toISOString() ?? '');
        $hasVoiceover = $voiceoverSources->isNotEmpty()
            || $this->hasAnyAssetRole($project, ['voiceover_audio', 'audio_track'], 'audio');

        $requirements = [
            $this->requirement(
                type: 'source_generation',
                key: 'video_campaign_analysis',
                label: 'Video campaign analysis',
                satisfied: $hasReusableAnalysis,
                helpText: 'Reuse a completed Video-to-Campaign result when available.',
            ),
            $this->requirement(
                type: 'media_asset',
                key: 'source_video',
                label: 'Raw product video',
                satisfied: $hasSourceVideo,
                helpText: 'Choose or upload a raw product video if no saved analysis is available.',
                suggestedUsageRole: 'source_video',
            ),
            $this->requirement(
                type: 'media_asset',
                key: 'voiceover',
                label: 'Voiceover or audio track',
                satisfied: $hasVoiceover,
                helpText: 'Optional: reuse a voiceover generation or uploaded audio asset.',
                suggestedUsageRole: 'voiceover_audio',
            ),
        ];

        return $this->action(
            user: $user,
            key: 'generate_auto_video_edit',
            title: 'Auto Video Editor',
            description: 'Combine product footage, video campaign context and optional voiceover into an edit-ready vertical video plan.',
            serviceTier: 'premium',
            outputType: 'video',
            tool: $tool,
            status: 'configure',
            actionMode: 'configure',
            primaryLabel: 'Configure edit plan',
            primaryAction: 'configure_auto_video_editor',
            requirements: $requirements,
            recommendedSources: array_merge(
                $this->recommendedSources($videoCampaignSources),
                $this->recommendedSources($voiceoverSources),
            ),
            recommendedAssets: $this->recommendedAssets($project, [
                'campaign_source_video',
                'raw_product_video',
                'source_video',
                'video_reference',
                'voiceover_audio',
                'audio_track',
            ]),
            missingRequirementsCount: ($hasReusableAnalysis || $hasSourceVideo) ? 0 : 1,
            reason: $hasReusableAnalysis
                ? 'Can reuse existing video campaign analysis.'
                : ($hasSourceVideo ? 'Manual edit setup is available from a raw source video.' : 'Choose a Video-to-Campaign Insight or raw product video in setup.'),
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-auto-video-edit",
            creditCost: $tool?->credit_cost,
        );
    }

    /** @return array<string, mixed> */
    private function videoAdAction(Project $project, User $user, ?CreatorTool $tool): array
    {
        $hasProductImage = $this->hasAnyAssetRole($project, [
            'product_image',
            'brand_reference',
            'general',
        ], 'image');
        $hasSourceVideo = $this->hasAnyAssetRole($project, [
            'source_video',
            'raw_product_video',
            'campaign_source_video',
            'video_reference',
            'general',
        ], 'video');
        $campaignSources = $this->completedGenerations($project, 'generate_affiliate_campaign')
            ->merge($this->completedGenerations($project, 'generate_video_to_campaign'));
        $voiceoverSources = $this->completedGenerations($project, 'generate_ai_voiceover');
        $hasCampaignSource = $campaignSources->isNotEmpty();

        $requirements = [
            $this->requirement(
                type: 'media_asset',
                key: 'product_image_or_source_video',
                label: 'Product image or source video',
                satisfied: $hasProductImage || $hasSourceVideo,
                helpText: 'Choose a product image, source video, or both for the ad plan.',
                suggestedUsageRole: 'product_image',
            ),
            $this->requirement(
                type: 'source_generation',
                key: 'campaign_generation',
                label: 'Product Content Kit',
                satisfied: $hasCampaignSource,
                helpText: 'Optional but recommended: use a Product Content Kit or video analysis as the campaign source.',
            ),
        ];

        return $this->action(
            user: $user,
            key: 'create_video_ad',
            title: 'Auto Video Ads',
            description: 'Create a paid ad creative plan from campaign content, product assets, overlays, CTA and end card.',
            serviceTier: 'premium',
            outputType: 'ad_plan',
            tool: $tool,
            status: 'configure',
            actionMode: 'configure',
            primaryLabel: 'Configure ad plan',
            primaryAction: 'configure_auto_video_ad',
            requirements: $requirements,
            recommendedSources: array_merge(
                $this->recommendedSources($campaignSources),
                $this->recommendedSources($voiceoverSources),
            ),
            recommendedAssets: $this->recommendedAssets($project, [
                'product_image',
                'brand_reference',
                'campaign_source_video',
                'raw_product_video',
                'source_video',
                'video_reference',
                'general',
            ]),
            missingRequirementsCount: ($hasProductImage || $hasSourceVideo) ? 0 : 1,
            reason: ($hasProductImage || $hasSourceVideo)
                ? 'Ready to create an ad plan from project visuals.'
                : 'Choose a product image or source video in setup.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-auto-video-ad",
            creditCost: $tool?->credit_cost,
        );
    }

    /** @return array<string, mixed> */
    private function fashionTryOnAction(Project $project, User $user, ?CreatorTool $tool): array
    {
        $requirements = [
            $this->requirement(
                type: 'media_asset',
                key: 'model_reference',
                label: 'Model reference image',
                satisfied: $this->hasAssetRole($project, 'model_reference', 'image'),
                helpText: 'Upload a clear model/person reference image in the launcher.',
                suggestedUsageRole: 'model_reference',
            ),
            $this->requirement(
                type: 'media_asset',
                key: 'garment_reference',
                label: 'Garment reference image',
                satisfied: $this->hasAssetRole($project, 'garment_reference', 'image'),
                helpText: 'Upload a clear garment/product reference image in the launcher.',
                suggestedUsageRole: 'garment_reference',
            ),
        ];

        return $this->action(
            user: $user,
            key: 'start_fashion_try_on',
            title: 'Start Fashion Try-On',
            description: 'Use project context with model and garment references for a try-on workflow.',
            serviceTier: 'premium',
            outputType: 'image',
            tool: $tool,
            status: 'configure',
            actionMode: 'configure',
            primaryLabel: 'Setup Try-On',
            primaryAction: 'configure_fashion_try_on',
            requirements: $requirements,
            recommendedAssets: $this->recommendedAssets($project, ['model_reference', 'garment_reference']),
            missingRequirementsCount: $this->missingRequirementCount($requirements),
            reason: 'Open setup to choose or upload model and garment reference images.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-fashion-try-on",
            creditCost: $tool?->credit_cost,
        );
    }

    /** @return array<string, mixed> */
    private function motionVideoAction(Project $project, User $user, ?CreatorTool $tool): array
    {
        $capabilities = MotionAiVideoCapabilities::forUser($user);
        $hasProductImage = $this->hasAssetRole($project, 'product_image', 'image')
            || $project->mediaAssets->contains(fn ($asset): bool => $asset->type === 'image');
        $hasCampaign = $this->completedGenerations($project, 'generate_affiliate_campaign')->isNotEmpty()
            || $this->completedGenerations($project, 'generate_video_to_campaign')->isNotEmpty()
            || $this->completedGenerations($project, 'generate_product_video_script')->isNotEmpty()
            || $this->completedGenerations($project, 'generate_auto_video_ad')->isNotEmpty();
        $requirements = [
            $this->requirement(
                type: 'input',
                key: 'text_prompt',
                label: 'Text prompt',
                satisfied: true,
                helpText: 'You can generate from the project brief and a manual creative prompt without a product image.',
            ),
            $this->requirement(
                type: 'source_generation',
                key: 'campaign_script',
                label: 'Saved campaign',
                satisfied: $hasCampaign,
                helpText: 'Optional. Use a Product Content Kit, video analysis or ad generation as campaign context.',
            ),
            $this->requirement(
                type: 'media_asset',
                key: 'product_image',
                label: 'Product image',
                satisfied: $hasProductImage,
                helpText: $capabilities['image_reference_experimental'] === true
                    ? 'Optional for text/campaign modes; required only for experimental visual reference mode.'
                    : 'Optional. Use it as Nusa workspace context; current provider mode will not send image bytes.',
                suggestedUsageRole: 'product_image',
            ),
            $this->requirement(
                type: 'media_asset',
                key: 'scenario_image',
                label: 'Scenario image',
                satisfied: $this->hasAssetRole($project, 'background_reference', 'image'),
                helpText: 'Optional. Add a scenario or background image as prompt context.',
                suggestedUsageRole: 'background_reference',
            ),
        ];

        return $this->action(
            user: $user,
            key: 'start_motion_video',
            title: 'Motion AI Video',
            description: 'Create text-to-video or campaign-to-video motion clips, with optional product image context.',
            serviceTier: 'premium',
            outputType: 'video',
            tool: $tool,
            status: 'configure',
            actionMode: 'configure',
            primaryLabel: 'Configure Motion AI Video',
            primaryAction: 'configure_motion_ai_video',
            primaryRoute: "/projects/{$project->id}/actions/motion-ai-video",
            requirements: $requirements,
            recommendedAssets: $this->recommendedAssets($project, [
                'product_image',
                'background_reference',
                'brand_reference',
            ]),
            missingRequirementsCount: 0,
            reason: 'Open setup to choose text, campaign or image-context source mode.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-motion-ai-video",
            creditCost: $tool?->credit_cost,
            providerCapabilities: $capabilities,
        );
    }

    /** @return array<string, mixed> */
    private function avatarPresenterAction(User $user): array
    {
        return $this->action(
            user: $user,
            key: 'avatar_presenter',
            title: 'Avatar Presenter',
            description: 'Turn a script into an avatar-led presenter workflow later.',
            serviceTier: 'premium',
            outputType: 'avatar',
            tool: null,
            status: 'coming_soon',
            actionMode: 'coming_soon',
            primaryLabel: 'Coming soon',
            requirements: [],
            reason: 'Avatar presenter generation is not implemented yet.',
            creditCost: null,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $requirements
     * @param  array<int, array<string, mixed>>  $recommendedSources
     * @param  array<int, array<string, mixed>>  $recommendedAssets
     * @return array<string, mixed>
     */
    private function action(
        User $user,
        string $key,
        string $title,
        string $description,
        string $serviceTier,
        ?string $outputType,
        ?CreatorTool $tool,
        string $status,
        string $actionMode,
        string $primaryLabel,
        array $requirements,
        ?string $primaryAction = null,
        ?string $primaryRoute = null,
        array $recommendedSources = [],
        array $recommendedAssets = [],
        ?int $missingRequirementsCount = null,
        ?string $reason = null,
        ?string $actionEndpoint = null,
        ?int $sourceGenerationId = null,
        ?int $creditCost = null,
        array $providerCapabilities = [],
    ): array {
        $access = $this->access->metadataFor($user, $serviceTier);
        $toolStatus = $this->toolStatus($tool);

        if ($actionMode !== 'coming_soon' && $toolStatus !== 'available') {
            $status = $toolStatus;
            $actionMode = 'coming_soon';
            $primaryLabel = $toolStatus === 'coming_soon' ? 'Coming soon' : 'Unavailable';
            $primaryAction = null;
            $primaryRoute = null;
            $access['upgrade_required'] = false;
            $access['locked_reason'] = $toolStatus === 'coming_soon'
                ? 'This action is planned but not available yet.'
                : 'This action is temporarily unavailable.';
        } elseif ($actionMode !== 'coming_soon' && ! $access['user_has_access']) {
            $status = 'unavailable';
            $actionMode = 'upgrade';
            $primaryLabel = 'Upgrade subscription';
            $primaryRoute = '/pricing';
            $primaryAction = null;
        } elseif ($actionMode === 'coming_soon') {
            $access['upgrade_required'] = false;
            $access['locked_reason'] = null;
            $primaryAction = null;
            $primaryRoute = null;
        }

        $missingRequirementsCount ??= $this->missingRequirementCount($requirements);

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'service_tier' => $tool?->service_tier ?? $serviceTier,
            'output_type' => $tool?->output_type ?? $outputType,
            'status' => $status,
            'credit_cost' => $creditCost,
            'user_has_access' => $access['user_has_access'],
            'upgrade_required' => $access['upgrade_required'],
            'access_label' => $access['access_label'],
            'locked_reason' => $access['locked_reason'],
            'action_mode' => $actionMode,
            'primary_cta' => [
                'label' => $primaryLabel,
                'route' => $primaryRoute,
                'action' => $primaryAction,
            ],
            'requirements' => $requirements,
            'recommended_sources' => $recommendedSources,
            'recommended_assets' => $recommendedAssets,
            'missing_requirements_count' => $missingRequirementsCount,
            // Compatibility fields for the current lightweight Action Hub.
            'required_source' => $requirements[0]['type'] ?? null,
            'source_generation_id' => $sourceGenerationId,
            'reason' => $reason,
            'action_endpoint' => $actionEndpoint,
            'tool_key' => $tool?->key,
            'tool_status' => $tool?->status,
            'provider_capabilities' => $providerCapabilities === [] ? null : $providerCapabilities,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requirement(
        string $type,
        string $key,
        string $label,
        bool $satisfied,
        string $helpText,
        ?string $suggestedUsageRole = null,
    ): array {
        return [
            'type' => $type,
            'key' => $key,
            'label' => $label,
            'satisfied' => $satisfied,
            'help_text' => $helpText,
            'suggested_usage_role' => $suggestedUsageRole,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $requirements
     */
    private function missingRequirementCount(array $requirements): int
    {
        return collect($requirements)
            ->filter(fn (array $requirement): bool => $requirement['satisfied'] === false)
            ->count();
    }

    private function toolStatus(?CreatorTool $tool): string
    {
        if ($tool === null || ! $tool->is_visible || $tool->status === 'disabled') {
            return 'unavailable';
        }

        if ($tool->status === 'coming_soon') {
            return 'coming_soon';
        }

        if ($tool->status !== 'available') {
            return 'unavailable';
        }

        return 'available';
    }

    private function hasText(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function generationCount(Project $project, string $jobType): int
    {
        return $project->generations
            ->filter(fn (Generation $generation): bool => $generation->job_type === $jobType)
            ->count();
    }

    private function hasAssetRole(Project $project, string $usageRole, ?string $type = null): bool
    {
        return $project->mediaAssets->contains(fn ($asset): bool => $asset->usage_role === $usageRole
            && ($type === null || $asset->type === $type));
    }

    /** @param array<int, string> $usageRoles */
    private function hasAnyAssetRole(Project $project, array $usageRoles, ?string $type = null): bool
    {
        return $project->mediaAssets->contains(fn ($asset): bool => in_array($asset->usage_role ?? 'general', $usageRoles, true)
            && ($type === null || $asset->type === $type));
    }

    /**
     * @return Collection<int, Generation>
     */
    private function completedGenerations(Project $project, string $jobType): Collection
    {
        return $project->generations
            ->filter(fn (Generation $generation): bool => $generation->status === Generation::STATUS_COMPLETED
                && $generation->job_type === $jobType)
            ->sortByDesc(fn (Generation $generation): string => $generation->created_at?->toISOString() ?? '');
    }

    /**
     * @param  array<int, string>  $usageRoles
     * @return array<int, array<string, mixed>>
     */
    private function recommendedAssets(Project $project, array $usageRoles): array
    {
        return $project->mediaAssets
            ->filter(fn ($asset): bool => in_array($asset->usage_role, $usageRoles, true))
            ->map(fn ($asset): array => [
                'media_asset_id' => $asset->getKey(),
                'type' => $asset->type,
                'usage_role' => $asset->usage_role,
                'original_name' => $asset->original_name,
                'mime_type' => $asset->mime_type,
                'created_at' => $asset->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Generation>  $generations
     * @return Collection<int, Generation>
     */
    private function voiceoverSourceGenerations(Collection $generations): Collection
    {
        return $generations
            ->filter(fn (Generation $generation): bool => $generation->status === Generation::STATUS_COMPLETED
                && in_array($generation->job_type, [
                    'generate_affiliate_campaign',
                    'generate_product_video_script',
                    'generate_video_to_campaign',
                    'generate_auto_video_edit',
                ], true)
                && $this->voiceoverSources->extractTextFromGeneration($generation) !== null)
            ->sortByDesc(fn (Generation $generation): string => $generation->created_at?->toISOString() ?? '');
    }

    /**
     * @param  Collection<int, Generation>  $sources
     * @return array<int, array<string, mixed>>
     */
    private function recommendedSources(Collection $sources): array
    {
        return $sources
            ->map(function (Generation $generation): array {
                $text = $this->voiceoverSources->extractTextFromGeneration($generation) ?? '';
                $payload = $generation->output_payload ?? [];

                return [
                    'generation_id' => $generation->getKey(),
                    'job_type' => $generation->job_type,
                    'title' => $this->sourceTitle($generation, $payload),
                    'created_at' => $generation->created_at?->toISOString(),
                    'excerpt' => Str::limit($text, 180, ''),
                ];
            })
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $payload */
    private function sourceTitle(Generation $generation, array $payload): string
    {
        $title = data_get($payload, 'campaign_title')
            ?? data_get($payload, 'main_script.title')
            ?? data_get($payload, 'script.title')
            ?? data_get($payload, 'concept_title')
            ?? data_get($payload, 'edit_plan.title')
            ?? data_get($payload, 'render_settings.editing_style');

        return is_string($title) && trim($title) !== ''
            ? trim($title)
            : Str::headline($generation->job_type);
    }
}
