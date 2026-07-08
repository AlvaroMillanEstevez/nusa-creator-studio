<?php

namespace App\Services\Generation;

use App\Exceptions\MissingMotionAiVideoAssetsException;
use App\Jobs\GenerateMotionAiVideoJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use DomainException;
use Illuminate\Support\Collection;

class MotionAiVideoGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if ($project->type !== 'motion_ai_video') {
            throw new DomainException('Only Motion AI Video projects can generate motion video mockups.');
        }

        $visualReferences = $this->visualReferenceAssets($project);

        if ($visualReferences->isEmpty()) {
            throw new MissingMotionAiVideoAssetsException;
        }

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_motion_ai_video',
            creditCost: $this->tools->creditCost('motion_ai_video'),
            creditReason: 'Mock Motion AI Video generation',
            dispatch: fn (int $generationId) => GenerateMotionAiVideoJob::dispatch($generationId),
            inputPayload: $this->inputPayload($project, $visualReferences),
        );
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    private function visualReferenceAssets(Project $project): Collection
    {
        /** @var Collection<int, MediaAsset> $assets */
        $assets = $project->mediaAssets()
            ->whereIn('type', ['image', 'video'])
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->filter(fn (MediaAsset $asset): bool => $this->isSafeVisualAsset($asset))
            ->values();

        $roleTagged = $assets
            ->filter(fn (MediaAsset $asset): bool => $this->isMotionVideoReference($asset))
            ->values();

        return $roleTagged->isNotEmpty() ? $roleTagged : $assets;
    }

    private function isSafeVisualAsset(MediaAsset $asset): bool
    {
        return in_array($asset->type, ['image', 'video'], true)
            && $asset->mime_type !== 'image/svg+xml';
    }

    private function isMotionVideoReference(MediaAsset $asset): bool
    {
        return match ($asset->usage_role) {
            'product_image', 'background_reference', 'brand_reference' => $asset->type === 'image',
            'source_video' => $asset->type === 'video',
            default => false,
        };
    }

    /**
     * @param  Collection<int, MediaAsset>  $visualReferences
     * @return array<string, mixed>
     */
    private function inputPayload(Project $project, Collection $visualReferences): array
    {
        $audioTracks = $project->mediaAssets()
            ->where('usage_role', 'audio_track')
            ->where('type', 'audio')
            ->latest('created_at')
            ->latest('id')
            ->get();

        return array_merge($project->input_payload ?? [], [
            'assets' => [
                'visual_references' => $visualReferences
                    ->map(fn (MediaAsset $asset): array => $this->assetSummary($asset))
                    ->values()
                    ->all(),
                'audio_tracks' => $audioTracks
                    ->map(fn (MediaAsset $asset): array => $this->assetSummary($asset))
                    ->values()
                    ->all(),
                'background_references' => $visualReferences
                    ->filter(fn (MediaAsset $asset): bool => $asset->usage_role === 'background_reference')
                    ->map(fn (MediaAsset $asset): array => $this->assetSummary($asset))
                    ->values()
                    ->all(),
                'brand_references' => $visualReferences
                    ->filter(fn (MediaAsset $asset): bool => $asset->usage_role === 'brand_reference')
                    ->map(fn (MediaAsset $asset): array => $this->assetSummary($asset))
                    ->values()
                    ->all(),
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
