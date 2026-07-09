<?php

namespace App\Jobs;

use App\Contracts\VideoToCampaignProvider;
use App\Services\Generation\GenerationFailureService;
use App\Services\Generation\GenerationJobProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateVideoToCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $generationId) {}

    public function handle(
        VideoToCampaignProvider $provider,
        GenerationJobProcessor $processor,
    ): void {
        $processor->process(
            $this->generationId,
            'generate_video_to_campaign',
            fn (array $input): array => $provider->generate($input),
        );
    }

    public function failed(?Throwable $exception): void
    {
        app(GenerationFailureService::class)->fail($this->generationId, $exception);
    }
}
