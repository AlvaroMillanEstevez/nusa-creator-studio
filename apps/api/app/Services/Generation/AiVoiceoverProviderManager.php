<?php

namespace App\Services\Generation;

use App\Contracts\AiVoiceoverProvider;
use App\Data\Voiceover\AiVoiceoverRequestData;
use App\Data\Voiceover\AiVoiceoverResultData;

class AiVoiceoverProviderManager implements AiVoiceoverProvider
{
    public function __construct(
        private readonly MockAiVoiceoverProvider $mock,
        private readonly ElevenLabsAiVoiceoverProvider $elevenLabs,
    ) {}

    public function generate(AiVoiceoverRequestData $data): AiVoiceoverResultData
    {
        if ($this->shouldUseElevenLabs($data)) {
            return $this->elevenLabs->generate($data);
        }

        return $this->mock->generate($data);
    }

    public function selectedProviderForUser(bool $realProviderAllowed): string
    {
        if (! $this->isElevenLabsConfiguredForUse()) {
            return 'mock';
        }

        if ((bool) config('voiceover.real_internal_only', true) && ! $realProviderAllowed) {
            return 'mock';
        }

        return 'elevenlabs';
    }

    private function shouldUseElevenLabs(AiVoiceoverRequestData $data): bool
    {
        return $this->selectedProviderForUser($data->realProviderAllowed) === 'elevenlabs';
    }

    private function isElevenLabsConfiguredForUse(): bool
    {
        return config('voiceover.provider', 'mock') === 'elevenlabs'
            && (bool) config('voiceover.real_enabled', false);
    }
}
