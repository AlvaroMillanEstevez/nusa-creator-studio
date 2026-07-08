<?php

namespace App\Services\Generation;

use App\Contracts\AiVoiceoverProvider;
use App\Data\Voiceover\AiVoiceoverRequestData;
use App\Data\Voiceover\AiVoiceoverResultData;
use App\Models\Generation;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;

class AiVoiceoverGenerationProcessor
{
    public function __construct(
        private readonly AiVoiceoverProvider $provider,
        private readonly GeneratedVoiceoverMediaService $media,
    ) {}

    public function process(int $generationId): void
    {
        $generation = DB::transaction(function () use ($generationId): ?Generation {
            $generation = Generation::query()
                ->with(['project', 'user'])
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->job_type !== 'generate_ai_voiceover') {
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
            throw new \RuntimeException('Voiceover generation is missing its project.');
        }

        $requestData = AiVoiceoverRequestData::fromGeneration(
            generation: $generation,
            user: $generation->user,
            input: $generation->input_payload ?? [],
        );
        $resultData = $this->provider->generate($requestData);
        $outputPayload = $resultData->outputPayload;

        if ($resultData->provider === 'elevenlabs') {
            $asset = $this->media->store(
                user: $generation->user,
                project: $generation->project,
                requestData: $requestData,
                resultData: $resultData,
            );
            $outputPayload = $this->realProviderPayload($requestData, $resultData, $asset);
        }

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
                'output_payload' => $outputPayload,
                'error_message' => null,
                'finished_at' => now(),
            ])->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function realProviderPayload(
        AiVoiceoverRequestData $requestData,
        AiVoiceoverResultData $resultData,
        MediaAsset $asset,
    ): array {
        $title = is_string($requestData->input['title'] ?? null)
            ? trim($requestData->input['title'])
            : 'Generated AI voiceover';
        $duration = is_numeric($requestData->input['duration_seconds'] ?? null)
            ? (int) $requestData->input['duration_seconds']
            : null;

        return [
            'voiceover_title' => $title,
            'narration_script' => $requestData->text,
            'source_generation_id' => $requestData->sourceGenerationId,
            'source_generation_excerpt' => $requestData->sourceGenerationExcerpt,
            'audio_asset_id' => $asset->getKey(),
            'scene_timing' => [],
            'voice_direction' => 'Generated with the configured ElevenLabs default voice. A voice selector can be added later.',
            'pronunciation_notes' => [],
            'pacing_notes' => [],
            'platform_notes' => [
                'Audio is saved as a reusable project asset for future video, subtitle and avatar workflows.',
                'Use the voiceover with vertical video only after checking that all product claims are accurate.',
            ],
            'estimated_duration_seconds' => $duration,
            'suggested_audio_format' => 'MP3 audio saved privately in project assets.',
            'audio' => [
                'status' => 'generated',
                'media_asset_id' => $asset->getKey(),
                'media_asset_name' => $asset->original_name,
                'mime_type' => $resultData->mimeType,
                'format' => $resultData->extension,
                'provider' => $resultData->provider,
                'provider_model' => $resultData->providerModel,
                'provider_voice_id' => $resultData->providerVoiceId,
                'character_count' => $resultData->characterCount,
                'estimated_cost_usd' => $resultData->estimatedCostUsd,
                'duration_seconds' => $resultData->durationSeconds,
            ],
            'usage_notes' => [
                'Generated audio was saved to this project assets list and the Media Library.',
                'No public storage path is exposed by this response.',
                'Later workflows can reuse this asset for subtitles, overlays, avatar ads and motion video.',
            ],
            'compliance_notes' => [
                'Review the narration for factual product claims before publishing.',
                'Add required affiliate disclosure before using this audio in social commerce content.',
            ],
        ];
    }
}
