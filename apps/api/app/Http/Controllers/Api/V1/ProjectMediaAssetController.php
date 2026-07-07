<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaAssetResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectMediaAssetController extends Controller
{
    public function __invoke(Request $request, Project $project): AnonymousResourceCollection
    {
        abort_unless(
            $project->user_id === $request->user()->getKey(),
            Response::HTTP_NOT_FOUND,
        );

        return MediaAssetResource::collection(
            $project->mediaAssets()
                ->with('project:id,title,type')
                ->latest('created_at')
                ->latest('id')
                ->paginate(20),
        );
    }
}
