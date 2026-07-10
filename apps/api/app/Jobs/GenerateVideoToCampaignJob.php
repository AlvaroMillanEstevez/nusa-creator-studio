<?php

namespace App\Jobs;

use App\Services\Generation\GenerationFailureService;
use App\Services\Generation\VideoToCampaignGenerationProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateVideoToCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 360;

    public function __construct(public readonly int $generationId) {}

    public function handle(VideoToCampaignGenerationProcessor $processor): void
    {
        $processor->process($this->generationId);
    }

    public function failed(?Throwable $exception): void
    {
        app(GenerationFailureService::class)->fail($this->generationId, $exception);
    }
}
