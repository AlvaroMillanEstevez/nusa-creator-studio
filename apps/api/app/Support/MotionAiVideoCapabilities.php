<?php

namespace App\Support;

use App\Models\User;
use App\Services\Generation\MotionAiVideoProviderManager;

class MotionAiVideoCapabilities
{
    public const SOURCE_MODE_TEXT_PROMPT = 'text_prompt';

    public const SOURCE_MODE_CAMPAIGN_ASSISTED = 'campaign_assisted';

    public const SOURCE_MODE_IMAGE_CONTEXT = 'image_context';

    public const SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL = 'image_reference_experimental';

    public const REQUEST_MODE_TEXT_ONLY = 'text_only';

    public const REQUEST_MODE_REFERENCE_IMAGES_EXPERIMENTAL = 'reference_images_experimental';

    public const REQUEST_MODE_FIRST_FRAME_EXPERIMENTAL = 'first_frame_experimental';

    /** @return array<int, string> */
    public static function sourceModes(): array
    {
        return [
            self::SOURCE_MODE_TEXT_PROMPT,
            self::SOURCE_MODE_CAMPAIGN_ASSISTED,
            self::SOURCE_MODE_IMAGE_CONTEXT,
            self::SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL,
        ];
    }

    public static function normalizeSourceMode(mixed $value, mixed $campaignGenerationId = null, mixed $productImageAssetId = null): string
    {
        if (is_string($value) && in_array($value, self::sourceModes(), true)) {
            return $value;
        }

        if ($campaignGenerationId !== null && $campaignGenerationId !== '') {
            return self::SOURCE_MODE_CAMPAIGN_ASSISTED;
        }

        if ($productImageAssetId !== null && $productImageAssetId !== '') {
            return self::SOURCE_MODE_IMAGE_CONTEXT;
        }

        // Preserve the original image-first API behavior for legacy clients
        // that do not send a source mode.
        return self::SOURCE_MODE_IMAGE_CONTEXT;
    }

    public static function normalizeRequestMode(mixed $value = null): string
    {
        $mode = is_string($value)
            ? trim($value)
            : trim((string) config('motion_ai_video.google_veo.request_mode', self::REQUEST_MODE_TEXT_ONLY));

        return in_array($mode, [
            self::REQUEST_MODE_TEXT_ONLY,
            self::REQUEST_MODE_REFERENCE_IMAGES_EXPERIMENTAL,
            self::REQUEST_MODE_FIRST_FRAME_EXPERIMENTAL,
        ], true)
            ? $mode
            : self::REQUEST_MODE_TEXT_ONLY;
    }

    /** @return array<string, mixed> */
    public static function forUser(?User $user): array
    {
        $provider = $user instanceof User
            ? app(MotionAiVideoProviderManager::class)->selectedProviderForUser($user)
            : 'mock';
        $requestMode = $provider === 'google_veo'
            ? self::normalizeRequestMode()
            : 'mock';
        $imageReferencesEnabled = $provider === 'google_veo'
            && $requestMode === self::REQUEST_MODE_REFERENCE_IMAGES_EXPERIMENTAL
            && (bool) config('motion_ai_video.google_veo.image_references_enabled', false)
            && (bool) config('motion_ai_video.google_veo.allow_experimental_image_input', false);

        return [
            'provider_mode' => $provider,
            'real_enabled' => $provider === 'google_veo',
            'request_mode' => $requestMode,
            'product_image_required' => $imageReferencesEnabled,
            'product_image_supported' => $imageReferencesEnabled ? true : 'context_only',
            'campaign_supported' => true,
            'text_prompt_supported' => true,
            'image_reference_experimental' => $imageReferencesEnabled,
            'provider_label' => $provider === 'google_veo' ? 'Google Veo' : 'Mock',
            'default_duration_seconds' => self::defaultDurationSeconds(),
            'default_aspect_ratio' => self::defaultAspectRatio(),
            'supports_real_rendering' => $provider === 'google_veo',
            'source_modes' => self::sourceModes(),
        ];
    }

    public static function imageReferencesEnabledForUser(?User $user): bool
    {
        return (bool) self::forUser($user)['image_reference_experimental'];
    }

    public static function defaultDurationSeconds(): int
    {
        return max(1, (int) config('motion_ai_video.google_veo.default_duration_seconds', 8));
    }

    public static function defaultAspectRatio(): string
    {
        $aspectRatio = trim((string) config('motion_ai_video.google_veo.default_aspect_ratio', '9:16'));

        return in_array($aspectRatio, ['9:16', '16:9'], true) ? $aspectRatio : '9:16';
    }
}
