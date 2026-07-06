<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\VideoEnhancerGenerationService;
use App\Support\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GenerateVideoEnhancerController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUser,
        private readonly VideoEnhancerGenerationService $videoEnhancer,
    ) {}

    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $user = $this->currentUser->resolve($request);

        $this->ensureOwnedBy($user, $project);

        if ($project->type !== 'video_enhancer') {
            return response()->json([
                'message' => 'Only video enhancer projects can use video enhancer generation.',
                'code' => 'invalid_project_type',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $generation = $this->videoEnhancer->generate($project, $user);
        } catch (InsufficientCreditsException $exception) {
            return response()->json([
                'message' => 'You do not have enough platform credits to generate this caption pack.',
                'code' => 'insufficient_credits',
                'available_credits' => $exception->availableCredits,
                'required_credits' => $exception->requiredCredits,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return response()->json([
            'data' => (new GenerationResource($generation))->resolve($request),
            'meta' => [
                'credits_balance' => $user->refresh()->credits_balance,
            ],
        ], Response::HTTP_CREATED);
    }

    private function ensureOwnedBy(User $user, Project $project): void
    {
        abort_unless($project->user_id === $user->id, Response::HTTP_NOT_FOUND);
    }
}
