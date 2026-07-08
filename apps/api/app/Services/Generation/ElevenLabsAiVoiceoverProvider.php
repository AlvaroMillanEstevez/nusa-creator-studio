<?php

namespace App\Services\Generation;

use App\Contracts\AiVoiceoverProvider;
use App\Data\Voiceover\AiVoiceoverRequestData;
use App\Data\Voiceover\AiVoiceoverResultData;
use App\Exceptions\Voiceover\AiVoiceoverProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ElevenLabsAiVoiceoverProvider implements AiVoiceoverProvider
{
    public function generate(AiVoiceoverRequestData $data): AiVoiceoverResultData
    {
        $apiKey = (string) config('voiceover.elevenlabs.api_key', '');
        $voiceId = $data->voiceId ?: (string) config('voiceover.elevenlabs.default_voice_id', '');
        $modelId = $data->modelId ?: (string) config('voiceover.elevenlabs.default_model_id', 'eleven_flash_v2_5');

        if ($apiKey === '' || $voiceId === '') {
            throw AiVoiceoverProviderException::missingConfiguration();
        }

        $payload = [
            'text' => $data->text,
            'model_id' => $modelId,
        ];

        if ($data->voiceSettings !== []) {
            $payload['voice_settings'] = $data->voiceSettings;
        }

        try {
            $response = Http::baseUrl((string) config('voiceover.elevenlabs.base_url'))
                ->timeout((int) config('voiceover.elevenlabs.timeout_seconds', 60))
                ->accept('audio/mpeg')
                ->withHeaders([
                    'xi-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('/v1/text-to-speech/'.rawurlencode($voiceId).'?output_format=mp3_44100_128', $payload);
        } catch (ConnectionException) {
            throw AiVoiceoverProviderException::requestFailed();
        }

        if (! $response->successful() || $response->body() === '') {
            throw AiVoiceoverProviderException::requestFailed();
        }

        $characterCount = mb_strlen($data->text);
        $estimatedCost = $this->estimateCost($modelId, $characterCount);
        $providerRequestId = $response->header('request-id')
            ?? $response->header('xi-request-id')
            ?? $response->header('x-request-id');

        return new AiVoiceoverResultData(
            outputPayload: [],
            audioBinary: $response->body(),
            mimeType: 'audio/mpeg',
            extension: 'mp3',
            provider: 'elevenlabs',
            providerModel: $modelId,
            providerVoiceId: $voiceId,
            providerRequestId: is_string($providerRequestId) ? $providerRequestId : null,
            characterCount: $characterCount,
            estimatedCostUsd: $estimatedCost,
            durationSeconds: null,
            metadata: [
                'source_generation_id' => $data->sourceGenerationId,
                'source_generation_excerpt' => $data->sourceGenerationExcerpt,
            ],
        );
    }

    private function estimateCost(string $modelId, int $characterCount): float
    {
        $normalizedModel = Str::lower($modelId);
        $rate = Str::contains($normalizedModel, ['flash', 'turbo'])
            ? (float) config('voiceover.elevenlabs.flash_cost_per_1k_chars', 0.05)
            : (float) config('voiceover.elevenlabs.multilingual_cost_per_1k_chars', 0.10);

        return round(($characterCount / 1000) * $rate, 6);
    }
}
