<?php

namespace App\Services\Generation;

use App\Data\Campaign\CampaignGenerationRequestData;
use App\Jobs\GenerateAffiliateCampaignJob;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use DomainException;
use Illuminate\Validation\ValidationException;

class CampaignGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
        private readonly CampaignGenerationProviderManager $providers,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if ($project->type !== 'affiliate_campaign') {
            throw new DomainException('Only affiliate campaign projects can use campaign generation.');
        }

        $inputPayload = $project->input_payload ?? [];
        $requestData = CampaignGenerationRequestData::fromGeneration(
            generation: new Generation([
                'project_id' => $project->getKey(),
                'input_payload' => $inputPayload,
            ]),
            user: $user,
            input: $inputPayload,
        );

        $this->ensureInputIsWithinLimit($requestData);

        $provider = $this->providers->selectedProviderForUser($user);

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_affiliate_campaign',
            creditCost: $this->tools->creditCost('affiliate_campaign'),
            creditReason: $provider === 'openai'
                ? 'OpenAI affiliate campaign generation'
                : 'Mock affiliate campaign generation',
            dispatch: fn (int $generationId) => GenerateAffiliateCampaignJob::dispatch($generationId),
            inputPayload: $inputPayload,
            provider: $provider,
        );
    }

    private function ensureInputIsWithinLimit(CampaignGenerationRequestData $data): void
    {
        $limit = (int) config('campaign.max_input_characters', 4000);

        if ($limit > 0 && $data->inputCharacterCount() > $limit) {
            throw ValidationException::withMessages([
                'input_payload' => "Campaign input must be {$limit} characters or fewer.",
            ]);
        }
    }
}
