<?php

namespace App\Services\Generation;

use App\Contracts\MotionAiVideoProvider;
use App\Models\User;

class MotionAiVideoProviderManager implements MotionAiVideoProvider
{
    public function __construct(
        private readonly MockMotionAiVideoProvider $mock,
        private readonly GoogleVeoMotionAiVideoProvider $googleVeo,
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
        if ($this->selectedProviderForUser($user) === 'google_veo') {
            return $this->googleVeo->generateForUser($input, $user);
        }

        return $this->mock->generate($input);
    }

    public function selectedProviderForUser(User $user): string
    {
        if (! $this->isGoogleVeoConfiguredForUse()) {
            return 'mock';
        }

        if ((bool) config('motion_ai_video.real_internal_only', true) && ! $this->userCanUseRealProvider($user)) {
            return 'mock';
        }

        return 'google_veo';
    }

    private function isGoogleVeoConfiguredForUse(): bool
    {
        return in_array(config('motion_ai_video.provider', 'mock'), ['google_veo', 'veo'], true)
            && (bool) config('motion_ai_video.real_enabled', false);
    }

    private function userCanUseRealProvider(User $user): bool
    {
        return $user->isAdmin()
            || $user->isInternalCreator()
            || $user->hasUnlimitedAccess()
            || $user->plan_key === 'internal_unlimited';
    }
}
