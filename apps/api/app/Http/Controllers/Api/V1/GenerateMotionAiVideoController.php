<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\MissingMotionAiVideoAssetsException;
use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\MotionAiVideoGenerationService;
use App\Services\ToolAvailabilityService;
use App\Support\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GenerateMotionAiVideoController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUser,
        private readonly MotionAiVideoGenerationService $motionVideos,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $user = $this->currentUser->resolve($request);

        $this->ensureOwnedBy($user, $project);

        if ($project->type !== 'motion_ai_video') {
            return response()->json([
                'message' => 'Only Motion AI Video projects can generate motion video mockups.',
                'code' => 'invalid_project_type',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->tools->isAvailable('motion_ai_video')) {
            return response()->json([
                'message' => 'This tool is temporarily unavailable.',
                'code' => 'tool_unavailable',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $generation = $this->motionVideos->generate($project, $user);
        } catch (MissingMotionAiVideoAssetsException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'missing_visual_reference_asset',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InsufficientCreditsException $exception) {
            return response()->json([
                'message' => 'You do not have enough platform credits to generate this motion video mockup.',
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
