<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly CurrentUserResolver $currentUser) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $this->currentUser
            ->resolve($request)
            ->projects()
            ->latest()
            ->paginate();

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] ??= 'draft';

        $project = $this->currentUser
            ->resolve($request)
            ->projects()
            ->create($data);

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Project $project): ProjectResource
    {
        $this->ensureOwnedBy($this->currentUser->resolve($request), $project);

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->ensureOwnedBy($this->currentUser->resolve($request), $project);

        $project->update($request->validated());

        return new ProjectResource($project->refresh());
    }

    public function destroy(Request $request, Project $project): Response
    {
        $this->ensureOwnedBy($this->currentUser->resolve($request), $project);

        $project->delete();

        return response()->noContent();
    }

    private function ensureOwnedBy(User $user, Project $project): void
    {
        abort_unless($project->user_id === $user->id, Response::HTTP_NOT_FOUND);
    }
}
