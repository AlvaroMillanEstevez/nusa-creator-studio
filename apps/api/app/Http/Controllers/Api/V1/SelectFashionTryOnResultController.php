<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SelectFashionTryOnResultRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SelectFashionTryOnResultController extends Controller
{
    public function __invoke(SelectFashionTryOnResultRequest $request, Generation $generation): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $generation->user_id === $user->id, Response::HTTP_NOT_FOUND);

        if ($generation->job_type !== 'generate_fashion_try_on' || $generation->status !== Generation::STATUS_COMPLETED) {
            return response()->json([
                'message' => 'Only completed Fashion Try-On generations can select a favorite result.',
                'code' => 'fashion_try_on_selection_unavailable',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $mediaAssetId = (int) $request->validated('media_asset_id');
        $output = is_array($generation->output_payload) ? $generation->output_payload : [];
        $allowedIds = array_values(array_map('intval', is_array($output['final_image_asset_ids'] ?? null) ? $output['final_image_asset_ids'] : []));

        if (! in_array($mediaAssetId, $allowedIds, true)) {
            return response()->json([
                'message' => 'Choose one of the generated Fashion Try-On result images.',
                'code' => 'fashion_try_on_selection_invalid_asset',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $asset = MediaAsset::query()
            ->whereKey($mediaAssetId)
            ->where('user_id', $user->id)
            ->where('project_id', $generation->project_id)
            ->where('type', 'image')
            ->where('usage_role', 'fashion_try_on_result')
            ->first();

        if (! $asset instanceof MediaAsset || ! $this->assetBelongsToGeneration($asset, $generation)) {
            return response()->json([
                'message' => 'Choose one of the generated Fashion Try-On result images.',
                'code' => 'fashion_try_on_selection_invalid_asset',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $outputIndex = $this->outputIndex($output, $asset);
        $output['selected_image_asset_id'] = $asset->getKey();
        $output['selected_output_index'] = $outputIndex;
        $output['selection_updated_at'] = now()->toISOString();
        $summary = is_array($output['generation_summary'] ?? null) ? $output['generation_summary'] : [];
        $summary['selected_image_asset_id'] = $asset->getKey();
        $summary['selected_output_index'] = $outputIndex;
        $output['generation_summary'] = $summary;

        $generation->forceFill(['output_payload' => $output])->save();

        return response()->json([
            'data' => (new GenerationResource($generation->refresh()))->resolve($request),
        ]);
    }

    private function assetBelongsToGeneration(MediaAsset $asset, Generation $generation): bool
    {
        $metadata = is_array($asset->metadata) ? $asset->metadata : [];

        return (int) ($metadata['generation_id'] ?? 0) === (int) $generation->getKey()
            && ($metadata['workflow'] ?? null) === 'fashion_try_on';
    }

    /** @param array<string, mixed> $output */
    private function outputIndex(array $output, MediaAsset $asset): ?int
    {
        $assets = is_array($output['rendered_image_assets'] ?? null) ? $output['rendered_image_assets'] : [];

        foreach ($assets as $renderedAsset) {
            if (is_array($renderedAsset) && (int) ($renderedAsset['id'] ?? 0) === (int) $asset->getKey()) {
                return is_numeric($renderedAsset['output_index'] ?? null) ? (int) $renderedAsset['output_index'] : null;
            }
        }

        return is_numeric($asset->metadata['output_index'] ?? null) ? (int) $asset->metadata['output_index'] : null;
    }
}
