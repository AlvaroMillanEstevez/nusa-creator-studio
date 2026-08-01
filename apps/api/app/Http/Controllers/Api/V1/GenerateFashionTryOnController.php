<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\FashionTryOn\FashionTryOnProviderException;
use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateFashionTryOnRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\FashionTryOnGenerationService;
use App\Services\ToolAvailabilityService;
use App\Support\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GenerateFashionTryOnController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUser,
        private readonly FashionTryOnGenerationService $tryOns,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function __invoke(GenerateFashionTryOnRequest $request, Project $project): JsonResponse
    {
        $user = $this->currentUser->resolve($request);

        $this->ensureOwnedBy($user, $project);

        if (! in_array($project->type, ['workspace', 'fashion_try_on'], true)) {
            return response()->json([
                'message' => 'Only project workspaces can generate AI Fashion Try-On results.',
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
            $generation = $this->tryOns->generate($project, $user, $request->validated());
        } catch (FashionTryOnProviderException $exception) {
            return response()->json([
                'message' => $this->safeMessage($exception->reason),
                'code' => $exception->reason,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InsufficientCreditsException $exception) {
            return response()->json([
                'message' => 'You do not have enough platform credits to generate this AI Fashion Try-On result.',
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

    private function safeMessage(string $reason): string
    {
        return match ($reason) {
            'fashn_premium_disabled' => 'Premium Fashion Try-On is prepared but not enabled yet.',
            'fashn_premium_pricing_unconfigured' => 'Premium Fashion Try-On pricing is not configured yet.',
            'fashn_standard_disabled' => 'Standard Fashion Try-On is temporarily disabled.',
            'fashn_image_load_failed' => 'Use clear, accessible image assets before generating a fashion try-on.',
            'fashn_pose_not_detected' => 'Use a clear photo where the person’s body and pose are visible.',
            'fashn_content_moderation' => 'This image could not be processed under the fashion-image safety policy.',
            'fashn_insufficient_credits' => 'The provider is temporarily unavailable for this test account.',
            default => 'Please choose one person photo, one clothing photo and confirm permission before generating.',
        };
    }
}
