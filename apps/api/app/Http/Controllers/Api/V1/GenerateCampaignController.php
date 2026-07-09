<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\CampaignGenerationService;
use App\Services\ToolAvailabilityService;
use App\Support\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GenerateCampaignController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUser,
        private readonly CampaignGenerationService $campaigns,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $user = $this->currentUser->resolve($request);

        $this->ensureOwnedBy($user, $project);

        if (! in_array($project->type, ['affiliate_campaign', 'workspace'], true)) {
            return response()->json([
                'message' => 'Only affiliate campaign or workspace projects can use campaign generation.',
                'code' => 'invalid_project_type',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->tools->isAvailable('affiliate_campaign')) {
            return response()->json([
                'message' => 'This tool is temporarily unavailable.',
                'code' => 'tool_unavailable',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $generation = $this->campaigns->generate($project, $user);
        } catch (InsufficientCreditsException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
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
