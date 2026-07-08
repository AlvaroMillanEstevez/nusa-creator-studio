<?php

namespace App\Contracts;

use App\Data\Campaign\CampaignGenerationRequestData;
use App\Data\Campaign\CampaignGenerationResultData;

interface CampaignGenerationProvider
{
    public function generate(CampaignGenerationRequestData $data): CampaignGenerationResultData;
}
