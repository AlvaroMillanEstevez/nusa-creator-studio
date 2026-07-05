<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentUserResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectGenerationController extends Controller
{
    public function __construct(private readonly CurrentUserResolver $currentUser) {}

    public function __invoke(Request $request, Project $project): AnonymousResourceCollection
    {
        $user = $this->currentUser->resolve($request);

        $this->ensureOwnedBy($user, $project);

        $generations = $project->generations()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return GenerationResource::collection($generations);
    }

    private function ensureOwnedBy(User $user, Project $project): void
    {
        abort_unless($project->user_id === $user->id, Response::HTTP_NOT_FOUND);
    }
}
