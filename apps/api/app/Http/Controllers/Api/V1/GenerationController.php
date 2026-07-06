<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Models\User;
use App\Support\CurrentUserResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GenerationController extends Controller
{
    public function __construct(private readonly CurrentUserResolver $currentUser) {}

    public function __invoke(Request $request, Generation $generation): GenerationResource
    {
        $user = $this->currentUser->resolve($request);

        $this->ensureOwnedBy($user, $generation);

        return new GenerationResource($generation);
    }

    private function ensureOwnedBy(User $user, Generation $generation): void
    {
        abort_unless($generation->user_id === $user->id, Response::HTTP_NOT_FOUND);
    }
}
