<?php

namespace App\Services\Generation;

use App\Data\Campaign\CampaignGenerationRequestData;
use App\Jobs\GenerateAffiliateCampaignJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use App\Support\CampaignWorkflowMetadata;
use DomainException;
use Illuminate\Validation\ValidationException;

class CampaignGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
        private readonly CampaignGenerationProviderManager $providers,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if (! in_array($project->type, ['affiliate_campaign', 'workspace'], true)) {
            throw new DomainException('Only affiliate campaign or workspace projects can use campaign generation.');
        }

        $inputPayload = $this->inputPayload($project);
        $requestData = CampaignGenerationRequestData::fromGeneration(
            generation: new Generation([
                'project_id' => $project->getKey(),
                'input_payload' => $inputPayload,
            ]),
            user: $user,
            input: $inputPayload,
        );

        $this->ensureInputIsWithinLimit($requestData);

        $provider = $this->providers->selectedProviderForUser($user);

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_affiliate_campaign',
            creditCost: $this->tools->creditCost('affiliate_campaign'),
            creditReason: $provider === 'openai'
                ? 'OpenAI affiliate campaign generation'
                : 'Mock affiliate campaign generation',
            dispatch: fn (int $generationId) => GenerateAffiliateCampaignJob::dispatch($generationId),
            inputPayload: $inputPayload,
            provider: $provider,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inputPayload(Project $project): array
    {
        $input = is_array($project->input_payload) ? $project->input_payload : [];
        $sourceImage = $this->sourceImageAsset($project, $input);
        $sourceImageSummary = $sourceImage instanceof MediaAsset ? $this->assetSummary($sourceImage) : null;
        $sourceImageAssetId = $sourceImage?->getKey();
        $metadata = CampaignWorkflowMetadata::forProductContentKit($input, $sourceImageAssetId);

        return array_merge($input, $metadata, [
            'source_image_asset_id' => $sourceImageAssetId,
            'product_image_asset_id' => $sourceImageAssetId,
            'source_image_asset' => $sourceImageSummary,
            'product_image_asset' => $sourceImageSummary,
            'image_assisted_context_note' => $sourceImage instanceof MediaAsset
                ? 'Image-assisted context is based on the selected product image metadata. No deep image analysis has been performed yet.'
                : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function sourceImageAsset(Project $project, array $input): ?MediaAsset
    {
        $assetId = $this->nullableInt($input['source_image_asset_id'] ?? null)
            ?? $this->nullableInt($input['product_image_asset_id'] ?? null);

        if ($assetId !== null) {
            $asset = $project->mediaAssets()
                ->whereKey($assetId)
                ->where('type', 'image')
                ->first();

            return $asset instanceof MediaAsset ? $asset : null;
        }

        $requestedMode = is_string($input['campaign_input_mode'] ?? null)
            ? $input['campaign_input_mode']
            : CampaignWorkflowMetadata::MODE_BRIEF_ONLY;

        if ($requestedMode !== CampaignWorkflowMetadata::MODE_IMAGE_ASSISTED) {
            return null;
        }

        $asset = $project->mediaAssets()
            ->where('type', 'image')
            ->whereIn('usage_role', ['product_image', 'brand_reference', 'general'])
            ->latest('created_at')
            ->latest('id')
            ->first();

        return $asset instanceof MediaAsset ? $asset : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
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

    private function ensureInputIsWithinLimit(CampaignGenerationRequestData $data): void
    {
        $limit = (int) config('campaign.max_input_characters', 4000);

        if ($limit > 0 && $data->inputCharacterCount() > $limit) {
            throw ValidationException::withMessages([
                'input_payload' => "Campaign input must be {$limit} characters or fewer.",
            ]);
        }
    }
}
