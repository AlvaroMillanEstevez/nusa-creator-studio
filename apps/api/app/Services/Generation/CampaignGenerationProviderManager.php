<?php

namespace App\Services\Generation;

use App\Contracts\CampaignGenerationProvider;
use App\Data\Campaign\CampaignGenerationRequestData;
use App\Data\Campaign\CampaignGenerationResultData;
use App\Models\User;

class CampaignGenerationProviderManager implements CampaignGenerationProvider
{
    public function __construct(
        private readonly MockAffiliateCampaignProvider $mock,
        private readonly OpenAiCampaignGenerationProvider $openAi,
    ) {}

    public function generate(CampaignGenerationRequestData $data): CampaignGenerationResultData
    {
        if ($this->shouldUseOpenAi($data->realProviderAllowed)) {
            return $this->openAi->generate($data);
        }

        return $this->mock->generate($data);
    }

    public function selectedProviderForUser(User $user): string
    {
        if (! $this->isOpenAiConfiguredForUse()) {
            return 'mock';
        }

        if ((bool) config('campaign.real_internal_only', true) && ! $this->userCanUseRealProvider($user)) {
            return 'mock';
        }

        return 'openai';
    }

    private function shouldUseOpenAi(bool $realProviderAllowed): bool
    {
        if (! $this->isOpenAiConfiguredForUse()) {
            return false;
        }

        return ! (bool) config('campaign.real_internal_only', true) || $realProviderAllowed;
    }

    private function isOpenAiConfiguredForUse(): bool
    {
        return config('campaign.provider', 'mock') === 'openai'
            && (bool) config('campaign.real_enabled', false);
    }

    private function userCanUseRealProvider(User $user): bool
    {
        return $user->isAdmin()
            || $user->isInternalCreator()
            || $user->hasUnlimitedAccess()
            || $user->plan_key === 'internal_unlimited';
    }
}
