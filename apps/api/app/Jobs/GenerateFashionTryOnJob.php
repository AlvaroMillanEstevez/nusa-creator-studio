<?php

namespace App\Jobs;

use App\Services\Generation\FashionTryOnGenerationProcessor;
use App\Services\Generation\GenerationFailureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateFashionTryOnJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly int $generationId) {}

    public function handle(FashionTryOnGenerationProcessor $processor): void
    {
        $processor->process($this->generationId);
    }

    public function failed(?Throwable $exception): void
    {
        app(GenerationFailureService::class)->fail($this->generationId, $exception);
    }
}
