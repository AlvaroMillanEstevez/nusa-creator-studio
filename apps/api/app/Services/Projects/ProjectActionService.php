<?php

namespace App\Services\Projects;

use App\Models\CreatorTool;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\AiVoiceoverSourceResolver;
use Illuminate\Database\Eloquent\Collection;

class ProjectActionService
{
    public function __construct(private readonly AiVoiceoverSourceResolver $voiceoverSources) {}

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
                'fashion_try_on',
                'motion_ai_video',
            ])
            ->get()
            ->keyBy('key');

        return [
            $this->generateCampaignAction($project, $tools->get('affiliate_campaign')),
            $this->voiceoverFromCampaignAction($project, $tools->get('ai_voiceover')),
            $this->videoAdAction(),
            $this->fashionTryOnAction($project, $tools->get('fashion_try_on')),
            $this->motionVideoAction($project, $tools->get('motion_ai_video')),
            $this->avatarPresenterAction(),
        ];
    }

    /**
     * @param  Collection<int, Generation>  $generations
     */
    public function recommendedVoiceoverSource(Collection $generations): ?Generation
    {
        return $generations
            ->filter(fn (Generation $generation): bool => $generation->status === Generation::STATUS_COMPLETED
                && in_array($generation->job_type, [
                    'generate_affiliate_campaign',
                    'generate_product_video_script',
                ], true)
                && $this->voiceoverSources->extractTextFromGeneration($generation) !== null)
            ->sortByDesc(fn (Generation $generation): string => $generation->created_at?->toISOString() ?? '')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function generateCampaignAction(Project $project, ?CreatorTool $tool): array
    {
        $input = is_array($project->input_payload) ? $project->input_payload : [];
        $hasBrief = $this->hasText($input['product_name'] ?? null)
            && $this->hasText($input['product_description'] ?? null);

        $status = $this->toolStatus($tool, $hasBrief ? 'available' : 'needs_source');

        return $this->action(
            key: 'generate_campaign',
            title: 'Generate campaign',
            description: 'Create hooks, script, caption, hashtags and practical filming notes from this project brief.',
            tool: $tool,
            status: $status,
            requiredSource: $hasBrief ? null : 'source_text',
            reason: $hasBrief ? 'Project has enough product brief context.' : 'Add product name and description first.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-campaign",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function voiceoverFromCampaignAction(Project $project, ?CreatorTool $tool): array
    {
        $source = $this->recommendedVoiceoverSource($project->generations);
        $toolAvailableStatus = $this->toolStatus($tool, $source === null ? 'needs_source' : 'available');

        return $this->action(
            key: 'generate_voiceover_from_campaign',
            title: 'Create voiceover from this script',
            description: 'Generate a voiceover using a completed campaign or product-to-video script on this project.',
            tool: $tool,
            status: $toolAvailableStatus,
            requiredSource: 'generation',
            sourceGenerationId: $source?->getKey(),
            reason: $source === null
                ? 'Generate or select a completed campaign script first.'
                : 'A completed script is available for voiceover generation.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-voiceover",
        );
    }

    /** @return array<string, mixed> */
    private function videoAdAction(): array
    {
        return $this->action(
            key: 'start_video_ad',
            title: 'Create video ad',
            description: 'Use project context to assemble a video ad workflow later.',
            tool: null,
            status: 'coming_soon',
            requiredSource: 'generation',
            reason: 'Video ad assembly is planned for a later workflow.',
            actionEndpoint: null,
            serviceTier: 'premium',
            outputType: 'video',
        );
    }

    /** @return array<string, mixed> */
    private function fashionTryOnAction(Project $project, ?CreatorTool $tool): array
    {
        $hasModel = $project->mediaAssets->contains(fn ($asset): bool => $asset->type === 'image' && $asset->usage_role === 'model_reference');
        $hasGarment = $project->mediaAssets->contains(fn ($asset): bool => $asset->type === 'image' && $asset->usage_role === 'garment_reference');
        $hasAssets = $hasModel && $hasGarment;

        return $this->action(
            key: 'start_fashion_try_on',
            title: 'Start Fashion Try-On',
            description: 'Use project context with model and garment references for a try-on workflow.',
            tool: $tool,
            status: $this->toolStatus($tool, $hasAssets ? 'available' : 'needs_assets'),
            requiredSource: 'media_asset',
            reason: $hasAssets
                ? 'Model and garment reference assets are available.'
                : 'Add model reference and garment reference images first.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-fashion-try-on",
        );
    }

    /** @return array<string, mixed> */
    private function motionVideoAction(Project $project, ?CreatorTool $tool): array
    {
        $hasVisual = $project->mediaAssets->contains(function ($asset): bool {
            if ($asset->type === 'video') {
                return $asset->usage_role === 'source_video';
            }

            return $asset->type === 'image' && in_array($asset->usage_role, [
                'product_image',
                'background_reference',
                'brand_reference',
            ], true);
        }) || $project->mediaAssets->contains(fn ($asset): bool => in_array($asset->type, ['image', 'video'], true));

        return $this->action(
            key: 'start_motion_video',
            title: 'Generate Motion AI Video',
            description: 'Use product or visual references to generate a motion video plan.',
            tool: $tool,
            status: $this->toolStatus($tool, $hasVisual ? 'available' : 'needs_assets'),
            requiredSource: 'media_asset',
            reason: $hasVisual
                ? 'A visual reference asset is available.'
                : 'Add a product image, source video, background reference or brand reference first.',
            actionEndpoint: "/api/v1/projects/{$project->id}/generate-motion-video",
        );
    }

    /** @return array<string, mixed> */
    private function avatarPresenterAction(): array
    {
        return $this->action(
            key: 'avatar_presenter',
            title: 'Avatar Presenter',
            description: 'Turn a script into an avatar-led presenter workflow later.',
            tool: null,
            status: 'coming_soon',
            requiredSource: 'generation',
            reason: 'Avatar presenter generation is not implemented yet.',
            actionEndpoint: null,
            serviceTier: 'premium',
            outputType: 'avatar',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function action(
        string $key,
        string $title,
        string $description,
        ?CreatorTool $tool,
        string $status,
        ?string $requiredSource,
        ?string $reason,
        ?string $actionEndpoint,
        ?int $sourceGenerationId = null,
        ?string $serviceTier = null,
        ?string $outputType = null,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'service_tier' => $tool?->service_tier ?? $serviceTier,
            'output_type' => $tool?->output_type ?? $outputType,
            'status' => $status,
            'required_source' => $requiredSource,
            'source_generation_id' => $sourceGenerationId,
            'reason' => $reason,
            'action_endpoint' => $actionEndpoint,
            'tool_key' => $tool?->key,
            'tool_status' => $tool?->status,
            'credit_cost' => $tool?->credit_cost,
        ];
    }

    private function toolStatus(?CreatorTool $tool, string $availableStatus): string
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

        return $availableStatus;
    }

    private function hasText(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
