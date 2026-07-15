<?php

namespace App\Exceptions\MotionAiVideo;

use RuntimeException;
use Throwable;

class MotionAiVideoProviderException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $reason = 'motion_ai_video_provider_error',
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function missingConfiguration(array $context = []): self
    {
        return new self('Motion AI Video provider is not configured.', 'google_veo_missing_configuration', $context);
    }

    /** @param array<string, mixed> $context */
    public static function requestFailed(
        string $reason = 'google_veo_provider_request_failed',
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self('Motion AI Video provider request failed.', $reason, $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function invalidResponse(
        string $reason = 'google_veo_invalid_response',
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self('Motion AI Video provider returned an invalid response.', $reason, $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function renderFailed(array $context = []): self
    {
        return self::requestFailed('google_veo_render_failed', $context);
    }

    /** @param array<string, mixed> $context */
    public static function renderTimedOut(array $context = []): self
    {
        return self::requestFailed('google_veo_render_timeout', $context);
    }
}
