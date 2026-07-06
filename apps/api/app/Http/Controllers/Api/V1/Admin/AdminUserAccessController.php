<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminUserAccessRequest;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\JsonResponse;

class AdminUserAccessController extends Controller
{
    public function __invoke(
        UpdateAdminUserAccessRequest $request,
        User $user,
        AdminUserService $users,
    ): JsonResponse {
        $updatedUser = $users->updateAccess($user, $request->validated());

        return response()->json([
            'data' => $users->detail($updatedUser),
        ]);
    }
}
