<?php

namespace App\Services\Generation;

use App\Models\Generation;
use Illuminate\Support\Facades\DB;

class FashionTryOnGenerationProcessor
{
    public function __construct(private readonly FashionTryOnProviderManager $providers) {}

    public function process(int $generationId): void
    {
        $context = DB::transaction(function () use ($generationId): ?array {
            $generation = Generation::query()
                ->with('user')
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->job_type !== 'generate_fashion_try_on') {
                return null;
            }

            $input = $generation->input_payload ?? [];
            $hasPrediction = is_string($input['provider_prediction_id'] ?? null)
                && trim($input['provider_prediction_id']) !== '';

            if (! in_array($generation->status, [Generation::STATUS_QUEUED, 'pending'], true)
                && ! ($generation->status === Generation::STATUS_PROCESSING && $hasPrediction)) {
                return null;
            }

            $generation->forceFill([
                'status' => Generation::STATUS_PROCESSING,
                'started_at' => $generation->started_at ?? now(),
                'error_message' => null,
            ])->save();

            return [
                'user' => $generation->user,
                'input' => array_merge($input, [
                    'generation_id' => $generation->getKey(),
                    'project_id' => $generation->project_id,
                    'user_id' => $generation->user_id,
                ]),
            ];
        });

        if ($context === null) {
            return;
        }

        $output = $this->providers->generateForUser($context['input'], $context['user']);

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
