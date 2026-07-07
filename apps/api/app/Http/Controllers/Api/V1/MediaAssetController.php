<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MediaAssetIndexRequest;
use App\Http\Requests\StoreMediaAssetRequest;
use App\Http\Resources\MediaAssetResource;
use App\Models\MediaAsset;
use App\Services\Media\MediaStorageService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class MediaAssetController extends Controller
{
    public function index(MediaAssetIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $query = $request->user()->mediaAssets()->with('project:id,title,type');

        $this->applyIndexFilters($query, $request, $validated);

        return MediaAssetResource::collection(
            $query
                ->latest('created_at')
                ->latest('id')
                ->paginate((int) ($validated['per_page'] ?? 20)),
        );
    }

    public function stats(Request $request): JsonResponse
    {
        $baseQuery = $request->user()->mediaAssets();
        $totalAssets = (clone $baseQuery)->count();
        $totalSizeBytes = (int) (clone $baseQuery)->sum('size_bytes');

        /** @var array<string, int> $typeCounts */
        $typeCounts = (clone $baseQuery)
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($count): int => (int) $count)
            ->all();

        /** @var array<string, int> $usageRoleCounts */
        $usageRoleCounts = (clone $baseQuery)
            ->whereNotNull('usage_role')
            ->selectRaw('usage_role, COUNT(*) as aggregate')
            ->groupBy('usage_role')
            ->pluck('aggregate', 'usage_role')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $byUsageRole = [];

        foreach (MediaAsset::USAGE_ROLES as $role) {
            $byUsageRole[$role] = $usageRoleCounts[$role] ?? 0;
        }

        return response()->json([
            'data' => [
                'total_assets' => $totalAssets,
                'total_size_bytes' => $totalSizeBytes,
                'total_size_label' => $this->sizeLabel($totalSizeBytes),
                'images_count' => $typeCounts['image'] ?? 0,
                'videos_count' => $typeCounts['video'] ?? 0,
                'audio_count' => $typeCounts['audio'] ?? 0,
                'documents_count' => $typeCounts['document'] ?? 0,
                'other_count' => $typeCounts['other'] ?? 0,
                'by_usage_role' => $byUsageRole,
                'recent_uploads_count' => (clone $baseQuery)
                    ->where('created_at', '>=', Carbon::now()->subDays(7))
                    ->count(),
            ],
        ]);
    }

    public function store(
        StoreMediaAssetRequest $request,
        MediaStorageService $storage,
    ): JsonResponse {
        $project = isset($request->validated()['project_id'])
            ? $request->user()->projects()->findOrFail($request->integer('project_id'))
            : null;

        $asset = $storage->store(
            user: $request->user(),
            file: $request->file('file'),
            project: $project,
            usageRole: $request->validated('usage_role'),
        );

        return (new MediaAssetResource($asset->load('project:id,title,type')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(
        Request $request,
        MediaAsset $mediaAsset,
        MediaStorageService $storage,
    ): JsonResponse {
        abort_unless(
            $mediaAsset->user_id === $request->user()->getKey(),
            Response::HTTP_NOT_FOUND,
        );

        $storage->delete($mediaAsset);

        return response()->json([
            'message' => 'Media asset deleted successfully.',
        ]);
    }

    /**
     * @param  HasMany<MediaAsset, mixed>  $query
     * @param  array<string, mixed>  $validated
     */
    private function applyIndexFilters(HasMany $query, MediaAssetIndexRequest $request, array $validated): void
    {
        if (isset($validated['project_id'])) {
            $projectId = (int) $validated['project_id'];

            abort_unless(
                $request->user()->projects()->whereKey($projectId)->exists(),
                Response::HTTP_NOT_FOUND,
            );

            $query->where('project_id', $projectId);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['usage_role'])) {
            $query->where('usage_role', $validated['usage_role']);
        }

        if (isset($validated['search'])) {
            $search = trim((string) $validated['search']);

            if ($search !== '') {
                $query->whereRaw('LOWER(original_name) LIKE ?', ['%'.mb_strtolower($search).'%']);
            }
        }
    }

    private function sizeLabel(int $sizeBytes): string
    {
        if ($sizeBytes < 1024) {
            return $sizeBytes.' B';
        }

        if ($sizeBytes < 1024 * 1024) {
            return number_format($sizeBytes / 1024, 1).' KB';
        }

        return number_format($sizeBytes / (1024 * 1024), 1).' MB';
    }
}
