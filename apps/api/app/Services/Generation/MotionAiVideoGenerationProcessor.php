<?php

namespace App\Services\Generation;

use App\Contracts\MotionAiVideoProvider;

class MotionAiVideoGenerationProcessor
{
    public function __construct(
        private readonly MotionAiVideoProvider $provider,
        private readonly GenerationJobProcessor $processor,
    ) {}

    public function process(int $generationId): void
    {
        $this->processor->process(
            $generationId,
            'generate_motion_ai_video',
            fn (array $input): array => $this->provider->generate($input),
        );
    }
}
