<?php

namespace App\Services\Generation;

use App\Jobs\GenerateAiVoiceoverJob;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use DomainException;

class AiVoiceoverGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if ($project->type !== 'ai_voiceover') {
            throw new DomainException('Only AI voiceover projects can generate voiceover plans.');
        }

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_ai_voiceover',
            creditCost: $this->tools->creditCost('ai_voiceover'),
            creditReason: 'Mock AI voiceover generation',
            dispatch: fn (int $generationId) => GenerateAiVoiceoverJob::dispatch($generationId),
        );
    }
}
