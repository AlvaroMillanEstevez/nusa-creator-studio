<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CurrentUserResolver
{
    /**
     * Resolve the authenticated user, with a local/testing fallback while auth is being built.
     *
     * @throws AuthenticationException
     */
    public function resolve(Request $request): User
    {
        $authenticatedUser = $request->user();

        if ($authenticatedUser instanceof User) {
            return $authenticatedUser;
        }

        if (! app()->environment(['local', 'testing'])) {
            throw new AuthenticationException;
        }

        // Development only: reuse the first user, or create one for an empty database.
        return User::query()->first() ?? User::query()->firstOrCreate(
            ['email' => 'development@nusa.test'],
            [
                'name' => 'Nusa Development User',
                'password' => Str::random(64),
            ],
        );
    }
}
