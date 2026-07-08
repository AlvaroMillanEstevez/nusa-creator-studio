<?php

namespace App\Exceptions\Voiceover;

use RuntimeException;

class AiVoiceoverProviderException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('AI voiceover provider is not available.');
    }

    public static function missingConfiguration(): self
    {
        return new self('AI voiceover provider is not configured.');
    }

    public static function requestFailed(): self
    {
        return new self('AI voiceover provider request failed.');
    }
}
