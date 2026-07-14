<?php

namespace App\Services\Generation;

class AutoVideoAdGenerationProcessor
{
    public function __construct(
        private readonly GenerationJobProcessor $jobs,
        private readonly MockAutoVideoAdProvider $provider,
    ) {}

    public function process(int $generationId): void
    {
        $this->jobs->process(
            generationId: $generationId,
            jobType: 'generate_auto_video_ad',
            generate: fn (array $input): array => $this->provider->generate($input),
        );
    }
}
