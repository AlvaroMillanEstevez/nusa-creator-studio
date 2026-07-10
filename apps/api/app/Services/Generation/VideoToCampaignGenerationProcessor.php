<?php

namespace App\Services\Generation;

use App\Models\Generation;
use Illuminate\Support\Facades\DB;

class VideoToCampaignGenerationProcessor
{
    public function __construct(private readonly VideoToCampaignProviderManager $provider) {}

    public function process(int $generationId): void
    {
        $generation = DB::transaction(function () use ($generationId): ?Generation {
            $generation = Generation::query()
                ->with(['project', 'user'])
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->job_type !== 'generate_video_to_campaign') {
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
            throw new \RuntimeException('Video-to-Campaign generation is missing its project.');
        }

        $outputPayload = $this->provider->generateForUser(
            $generation->input_payload ?? [],
            $generation->user,
        );
        $provider = data_get($outputPayload, 'provider_metadata.provider', $generation->provider ?: 'mock');
        $estimatedCost = data_get($outputPayload, 'provider_metadata.estimated_cost_usd');

        DB::transaction(function () use ($generationId, $outputPayload, $provider, $estimatedCost): void {
            $generation = Generation::query()
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->status !== Generation::STATUS_PROCESSING) {
                return;
            }

            $generation->forceFill([
                'provider' => is_string($provider) && $provider !== '' ? $provider : 'mock',
                'status' => Generation::STATUS_COMPLETED,
                'cost_usd' => is_numeric($estimatedCost) ? (float) $estimatedCost : 0,
                'output_payload' => $outputPayload,
                'error_message' => null,
                'finished_at' => now(),
            ])->save();
        });
    }
}
