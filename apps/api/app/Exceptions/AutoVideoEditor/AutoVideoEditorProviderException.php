<?php

namespace App\Exceptions\AutoVideoEditor;

use RuntimeException;
use Throwable;

class AutoVideoEditorProviderException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $reason = 'auto_video_editor_provider_error',
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function missingConfiguration(string $reason = 'shotstack_config_missing', array $context = []): self
    {
        return new self('Auto Video Editor provider is not configured.', $reason, $context);
    }

    /** @param array<string, mixed> $context */
    public static function requestFailed(
        string $reason,
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self('Auto Video Editor provider request failed.', $reason, $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function invalidResponse(
        string $reason = 'shotstack_invalid_response',
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self('Auto Video Editor provider returned an invalid response.', $reason, $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function renderFailed(array $context = []): self
    {
        return self::requestFailed('shotstack_render_failed', $context);
    }

    /** @param array<string, mixed> $context */
    public static function renderTimedOut(array $context = []): self
    {
        return self::requestFailed('shotstack_render_timeout', $context);
    }
}
