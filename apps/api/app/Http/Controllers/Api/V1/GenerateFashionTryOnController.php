<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\MissingFashionTryOnAssetsException;
use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\FashionTryOnGenerationService;
use App\Services\ToolAvailabilityService;
use App\Support\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GenerateFashionTryOnController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUser,
        private readonly FashionTryOnGenerationService $tryOns,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $user = $this->currentUser->resolve($request);

        $this->ensureOwnedBy($user, $project);

        if ($project->type !== 'fashion_try_on') {
            return response()->json([
                'message' => 'Only Fashion Try-On projects can generate try-on mockups.',
                'code' => 'invalid_project_type',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->tools->isAvailable('fashion_try_on')) {
            return response()->json([
                'message' => 'This tool is temporarily unavailable.',
                'code' => 'tool_unavailable',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $generation = $this->tryOns->generate($project, $user);
        } catch (MissingFashionTryOnAssetsException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'missing_reference_assets',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InsufficientCreditsException $exception) {
            return response()->json([
                'message' => 'You do not have enough platform credits to generate this try-on mockup.',
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
