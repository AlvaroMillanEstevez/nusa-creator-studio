<?php

namespace App\Services\Generation;

use App\Models\Generation;
use Illuminate\Support\Facades\DB;

class GenerationJobProcessor
{
    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $generate
     */
    public function process(int $generationId, string $jobType, callable $generate): void
    {
        $input = DB::transaction(function () use ($generationId, $jobType): ?array {
            $generation = Generation::query()
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->job_type !== $jobType) {
                return null;
            }

            if (! in_array($generation->status, [Generation::STATUS_QUEUED, 'pending'], true)) {
                return null;
            }

            $generation->forceFill([
                'status' => Generation::STATUS_PROCESSING,
                'started_at' => $generation->started_at ?? now(),
                'error_message' => null,
            ])->save();

            return $generation->input_payload ?? [];
        });

        if ($input === null) {
            return;
        }

        $output = $generate($input);

        DB::transaction(function () use ($generationId, $output): void {
            $generation = Generation::query()
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->status !== Generation::STATUS_PROCESSING) {
                return;
            }

            $generation->forceFill([
                'status' => Generation::STATUS_COMPLETED,
                'output_payload' => $output,
                'error_message' => null,
                'finished_at' => now(),
            ])->save();
        });
    }
}
