<?php

namespace App\Services\Generation;

use App\Exceptions\MissingFashionTryOnAssetsException;
use App\Jobs\GenerateFashionTryOnJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use DomainException;

class FashionTryOnGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if ($project->type !== 'fashion_try_on') {
            throw new DomainException('Only Fashion Try-On projects can generate try-on mockups.');
        }

        $modelReference = $this->requiredImageAsset($project, 'model_reference');
        $garmentReference = $this->requiredImageAsset($project, 'garment_reference');

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_fashion_try_on',
            creditCost: $this->tools->creditCost('fashion_try_on'),
            creditReason: 'Mock Fashion Try-On generation',
            dispatch: fn (int $generationId) => GenerateFashionTryOnJob::dispatch($generationId),
            inputPayload: $this->inputPayload($project, $modelReference, $garmentReference),
        );
    }

    private function requiredImageAsset(Project $project, string $usageRole): MediaAsset
    {
        $asset = $project->mediaAssets()
            ->where('usage_role', $usageRole)
            ->where('type', 'image')
            ->latest('created_at')
            ->latest('id')
            ->first();

        if (! $asset instanceof MediaAsset) {
            throw new MissingFashionTryOnAssetsException;
        }

        return $asset;
    }

    /**
     * @return array<string, mixed>
     */
    private function inputPayload(Project $project, MediaAsset $modelReference, MediaAsset $garmentReference): array
    {
        return array_merge($project->input_payload ?? [], [
            'assets' => [
                'model_reference' => $this->assetSummary($modelReference),
                'garment_reference' => $this->assetSummary($garmentReference),
            ],
        ]);
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
