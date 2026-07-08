<?php

namespace App\Exceptions\Campaign;

use RuntimeException;

class CampaignGenerationProviderException extends RuntimeException
{
    public static function missingConfiguration(): self
    {
        return new self('Campaign generation provider is not configured.');
    }

    public static function requestFailed(): self
    {
        return new self('Campaign generation provider request failed.');
    }

    public static function invalidResponse(): self
    {
        return new self('Campaign generation provider returned an invalid response.');
    }
}
