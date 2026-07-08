<?php

namespace App\Data\Voiceover;

class AiVoiceoverResultData
{
    /**
     * @param  array<string, mixed>  $outputPayload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly array $outputPayload,
        public readonly ?string $audioBinary,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly string $provider,
        public readonly ?string $providerModel,
        public readonly ?string $providerVoiceId,
        public readonly ?string $providerRequestId,
        public readonly int $characterCount,
        public readonly ?float $estimatedCostUsd,
        public readonly ?float $durationSeconds,
        public readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $outputPayload
     */
    public static function mock(array $outputPayload, int $characterCount): self
    {
        return new self(
            outputPayload: $outputPayload,
            audioBinary: null,
            mimeType: 'audio/mpeg',
            extension: 'mp3',
            provider: 'mock',
            providerModel: null,
            providerVoiceId: null,
            providerRequestId: null,
            characterCount: $characterCount,
            estimatedCostUsd: null,
            durationSeconds: null,
            metadata: [
                'generated_audio' => false,
            ],
        );
    }
}
