<?php

namespace App\Services\Generation;

use App\Jobs\GenerateProductVideoScriptJob;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use DomainException;

class ProductVideoScriptGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if ($project->type !== 'product_to_video') {
            throw new DomainException('Only product-to-video projects can generate video script packs.');
        }

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_product_video_script',
            creditCost: $this->tools->creditCost('product_to_video'),
            creditReason: 'Mock product-to-video script generation',
            dispatch: fn (int $generationId) => GenerateProductVideoScriptJob::dispatch($generationId),
        );
    }
}
