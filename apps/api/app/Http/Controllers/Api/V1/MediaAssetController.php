<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MediaAssetIndexRequest;
use App\Http\Requests\StoreMediaAssetRequest;
use App\Http\Resources\MediaAssetResource;
use App\Models\MediaAsset;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaAssetController extends Controller
{
    public function index(MediaAssetIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $query = $request->user()->mediaAssets();

        if (isset($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['usage_role'])) {
            $query->where('usage_role', $validated['usage_role']);
        }

        return MediaAssetResource::collection(
            $query->latest('created_at')->latest('id')->paginate(20),
        );
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

        return (new MediaAssetResource($asset))
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
}
