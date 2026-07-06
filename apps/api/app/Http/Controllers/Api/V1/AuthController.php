<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create($request->safe()->only([
            'name',
            'email',
            'password',
        ]));

        $user->refresh();

        Auth::login($user);
        $request->session()->regenerate();

        return $this->userResponse($request, $user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        return $this->userResponse($request, $request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->userResponse($request, $request->user());
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return $this->userResponse($request, $user->refresh());
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    private function userResponse(Request $request, User $user, int $status = 200): JsonResponse
    {
        $user->load('subscriptionPlan');

        return response()->json([
            'user' => (new AuthenticatedUserResource($user))->resolve($request),
        ], $status);
    }
}
