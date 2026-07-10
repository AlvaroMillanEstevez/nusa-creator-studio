<?php

namespace App\Services\Generation;

use App\Contracts\VideoToCampaignProvider;
use App\Models\User;

class VideoToCampaignProviderManager implements VideoToCampaignProvider
{
    public function __construct(
        private readonly MockVideoToCampaignProvider $mock,
        private readonly GeminiVideoToCampaignProvider $gemini,
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
        if ($this->selectedProviderForUser($user) === 'gemini') {
            return $this->gemini->generate($input);
        }

        return $this->mock->generate($input);
    }

    public function selectedProviderForUser(User $user): string
    {
        if (! $this->isGeminiConfiguredForUse()) {
            return 'mock';
        }

        if ((bool) config('video_to_campaign.real_internal_only', true) && ! $this->userCanUseRealProvider($user)) {
            return 'mock';
        }

        return 'gemini';
    }

    private function isGeminiConfiguredForUse(): bool
    {
        return config('video_to_campaign.provider', 'mock') === 'gemini'
            && (bool) config('video_to_campaign.real_enabled', false);
    }

    private function userCanUseRealProvider(User $user): bool
    {
        return $user->isAdmin()
            || $user->isInternalCreator()
            || $user->hasUnlimitedAccess()
            || $user->plan_key === 'internal_unlimited';
    }
}
