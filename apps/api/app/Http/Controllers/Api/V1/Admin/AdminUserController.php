<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserIndexRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function index(AdminUserIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = User::query()->withCount(['projects', 'generations']);

        if (! empty($validated['search'])) {
            $search = '%'.strtolower($validated['search']).'%';

            $query->where(function ($query) use ($search): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
            });
        }

        if (! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        if (! empty($validated['plan_key'])) {
            $query->where('plan_key', $validated['plan_key']);
        }

        if (array_key_exists('is_unlimited', $validated) && $validated['is_unlimited'] !== null) {
            $query->where('is_unlimited', $request->boolean('is_unlimited'));
        }

        $users = $query
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return AdminUserResource::collection($users)->response();
    }

    public function show(User $user, AdminUserService $users): JsonResponse
    {
        return response()->json([
            'data' => $users->detail($user),
        ]);
    }
}
