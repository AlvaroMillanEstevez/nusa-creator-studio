<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectActionService;
use App\Support\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectActionController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUser,
        private readonly ProjectActionService $actions,
    ) {}

    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $user = $this->currentUser->resolve($request);

        $this->ensureOwnedBy($user, $project);

        return response()->json([
            'data' => $this->actions->actionsFor($project, $user),
        ]);
    }

    private function ensureOwnedBy(User $user, Project $project): void
    {
        abort_unless($project->user_id === $user->id, Response::HTTP_NOT_FOUND);
    }
}
