<?php

namespace App\Exceptions\VideoToCampaign;

use RuntimeException;
use Throwable;

class VideoToCampaignProviderException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $reason = 'video_to_campaign_provider_error',
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function missingConfiguration(array $context = []): self
    {
        return new self('Video-to-Campaign provider is not configured.', 'gemini_missing_configuration', $context);
    }

    /** @param array<string, mixed> $context */
    public static function requestFailed(
        string $reason = 'gemini_provider_request_failed',
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self('Video-to-Campaign provider request failed.', $reason, $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function invalidResponse(
        string $reason = 'gemini_unexpected_response_shape',
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self('Video-to-Campaign provider returned an invalid response.', $reason, $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function invalidJson(array $context = [], ?Throwable $previous = null): self
    {
        return self::invalidResponse('gemini_invalid_json', $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function schemaValidationFailed(array $context = [], ?Throwable $previous = null): self
    {
        return self::invalidResponse('gemini_schema_validation_failed', $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function fileUnavailable(array $context = []): self
    {
        return new self('Video-to-Campaign source video is unavailable.', 'gemini_source_video_unavailable', $context);
    }

    /** @param array<string, mixed> $context */
    public static function fileProcessingTimedOut(array $context = []): self
    {
        return new self('Video-to-Campaign provider file processing timed out.', 'gemini_file_processing_timeout', $context);
    }
}
