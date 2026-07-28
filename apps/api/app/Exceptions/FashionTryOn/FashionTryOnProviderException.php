<?php

namespace App\Exceptions\FashionTryOn;

use RuntimeException;
use Throwable;

class FashionTryOnProviderException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $reason = 'fashion_try_on_provider_error',
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function missingConfiguration(array $context = []): self
    {
        return new self('Fashion Try-On provider is not configured.', 'fashn_missing_configuration', $context);
    }

    /** @param array<string, mixed> $context */
    public static function requestFailed(
        string $reason = 'fashn_provider_request_failed',
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self('Fashion Try-On provider request failed.', $reason, $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function invalidInput(
        string $reason = 'fashn_input_validation',
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self('Fashion Try-On input could not be processed.', $reason, $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function invalidResponse(
        string $reason = 'fashn_output_invalid',
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self('Fashion Try-On provider returned an invalid response.', $reason, $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function predictionFailed(array $context = []): self
    {
        return self::requestFailed('fashn_processing_failed', $context);
    }

    /** @param array<string, mixed> $context */
    public static function pollTimedOut(array $context = []): self
    {
        return self::requestFailed('fashn_poll_timeout', $context);
    }
}
