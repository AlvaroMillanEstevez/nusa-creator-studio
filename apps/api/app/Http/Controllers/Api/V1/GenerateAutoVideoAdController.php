<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateAutoVideoAdRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\AutoVideoAdGenerationService;
use App\Services\ToolAvailabilityService;
use App\Support\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GenerateAutoVideoAdController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUser,
        private readonly AutoVideoAdGenerationService $autoVideoAds,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function __invoke(GenerateAutoVideoAdRequest $request, Project $project): JsonResponse
    {
        $user = $this->currentUser->resolve($request);

        $this->ensureOwnedBy($user, $project);

        if (! $this->tools->isAvailable('auto_video_ads')) {
            return response()->json([
                'message' => 'This tool is temporarily unavailable.',
                'code' => 'tool_unavailable',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $generation = $this->autoVideoAds->generate($project, $user, $request->validated());
        } catch (InsufficientCreditsException $exception) {
            return response()->json([
                'message' => 'You do not have enough platform credits to generate this auto video ad plan.',
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
