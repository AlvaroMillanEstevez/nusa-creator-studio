<?php

namespace App\Services\Generation;

use App\Contracts\AutoVideoAdProvider;
use App\Models\User;

class AutoVideoAdProviderManager implements AutoVideoAdProvider
{
    public function __construct(
        private readonly MockAutoVideoAdProvider $mock,
        private readonly ShotstackAutoVideoAdProvider $shotstack,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        return $this->mock->generate($input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generateForUser(array $input, User $user): array
    {
        if ($this->selectedProviderForUser($user) === 'shotstack') {
            return $this->shotstack->generateForUser($input, $user);
        }

        return $this->mock->generate($input);
    }

    public function selectedProviderForUser(User $user): string
    {
        if (! $this->isShotstackConfiguredForUse()) {
            return 'mock';
        }

        if ((bool) config('auto_video_ad.real_internal_only', true) && ! $this->userCanUseRealProvider($user)) {
            return 'mock';
        }

        return 'shotstack';
    }

    private function isShotstackConfiguredForUse(): bool
    {
        return config('auto_video_ad.provider', 'mock') === 'shotstack'
            && (bool) config('auto_video_ad.real_enabled', false);
    }

    private function userCanUseRealProvider(User $user): bool
    {
        return $user->isAdmin()
            || $user->isInternalCreator()
            || $user->hasUnlimitedAccess()
            || $user->plan_key === 'internal_unlimited';
    }
}
