<?php

namespace App\Services\Generation;

use App\Jobs\GenerateVideoEnhancerJob;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use DomainException;

class VideoEnhancerGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if ($project->type !== 'video_enhancer') {
            throw new DomainException('Only video enhancer projects can use video enhancer generation.');
        }

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_video_enhancer',
            creditCost: $this->tools->creditCost('video_enhancer'),
            creditReason: 'Mock video enhancer generation',
            dispatch: fn (int $generationId) => GenerateVideoEnhancerJob::dispatch($generationId),
        );
    }
}
