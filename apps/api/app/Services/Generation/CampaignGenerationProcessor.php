<?php

namespace App\Services\Generation;

use App\Data\Campaign\CampaignGenerationRequestData;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;

class CampaignGenerationProcessor
{
    public function __construct(private readonly CampaignGenerationProviderManager $provider) {}

    public function process(int $generationId): void
    {
        $generation = DB::transaction(function () use ($generationId): ?Generation {
            $generation = Generation::query()
                ->with(['project', 'user'])
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->job_type !== 'generate_affiliate_campaign') {
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

            return $generation;
        });

        if (! $generation instanceof Generation) {
            return;
        }

        $generation->loadMissing(['project', 'user']);

        if ($generation->project === null) {
            throw new \RuntimeException('Campaign generation is missing its project.');
        }

        $requestData = CampaignGenerationRequestData::fromGeneration(
            generation: $generation,
            user: $generation->user,
            input: $generation->input_payload ?? [],
        );
        $resultData = $this->provider->generate($requestData);
        $outputPayload = $resultData->toOutputPayload();

        DB::transaction(function () use ($generationId, $resultData, $outputPayload): void {
            $generation = Generation::query()
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->status !== Generation::STATUS_PROCESSING) {
                return;
            }

            $generation->forceFill([
                'provider' => $resultData->provider,
                'status' => Generation::STATUS_COMPLETED,
                'cost_usd' => $resultData->estimatedCostUsd ?? 0,
                'output_payload' => $outputPayload,
                'error_message' => null,
                'finished_at' => now(),
            ])->save();
        });
    }
}
