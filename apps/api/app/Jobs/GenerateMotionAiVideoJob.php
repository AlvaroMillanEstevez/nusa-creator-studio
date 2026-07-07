<?php

namespace App\Jobs;

use App\Contracts\MotionAiVideoProvider;
use App\Services\Generation\GenerationFailureService;
use App\Services\Generation\GenerationJobProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateMotionAiVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $generationId) {}

    public function handle(
        MotionAiVideoProvider $provider,
        GenerationJobProcessor $processor,
    ): void {
        $processor->process(
            $this->generationId,
            'generate_motion_ai_video',
            fn (array $input): array => $provider->generate($input),
        );
    }

    public function failed(?Throwable $exception): void
    {
        app(GenerationFailureService::class)->fail($this->generationId, $exception);
    }
}