<?php

namespace App\Services\Generation;

use App\Jobs\GenerateAffiliateCampaignJob;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use DomainException;

class CampaignGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if ($project->type !== 'affiliate_campaign') {
            throw new DomainException('Only affiliate campaign projects can use campaign generation.');
        }

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_affiliate_campaign',
            creditCost: $this->tools->creditCost('affiliate_campaign'),
            creditReason: 'Mock affiliate campaign generation',
            dispatch: fn (int $generationId) => GenerateAffiliateCampaignJob::dispatch($generationId),
        );
    }
}
