<?php

namespace App\Support;

class AutoVideoEditSettings
{
    public const SOURCE_AUDIO_MUTE = 'mute_source';

    public const SOURCE_AUDIO_LOW = 'low_source';

    public const SOURCE_AUDIO_KEEP = 'keep_source';

    public const SOURCE_AUDIO_PRODUCT_SOUNDS = 'product_sounds';

    public const VOICEOVER_MIX_BALANCED = 'balanced';

    public const VOICEOVER_MIX_DOMINANT = 'voiceover_dominant';

    public const VOICEOVER_MIX_SOFT_BACKGROUND = 'soft_background';

    public const TRANSITION_CLEAN_CUTS = 'clean_cuts';

    public const TRANSITION_SOFT_FADES = 'soft_fades';

    public const TRANSITION_SNAPPY_SOCIAL = 'snappy_social';

    public const TRANSITION_PREMIUM_SMOOTH = 'premium_smooth';

    public const MOTION_NONE = 'none';

    public const MOTION_SUBTLE_ZOOM = 'subtle_zoom';

    public const MOTION_PRODUCT_FOCUS = 'product_focus';

    public const MOTION_DYNAMIC_CROP = 'dynamic_crop';

    public const CLIP_DIVERSITY_BALANCED = 'balanced';

    public const CLIP_DIVERSITY_MORE_UNIQUE = 'more_unique_shots';

    public const CLIP_DIVERSITY_CONCISE = 'concise';

    public const END_SCREEN_NONE = 'none';

    public const END_SCREEN_SIMPLE_CTA = 'simple_cta';

    public const END_SCREEN_PRODUCT_HOLD_CTA = 'product_hold_cta';

    public const OVERLAY_MINIMAL_CLEAN = 'minimal_clean';

    public const OVERLAY_UGC_NATIVE = 'ugc_native';

    public const OVERLAY_PRODUCT_HIGHLIGHTS = 'product_highlights';

    public const OVERLAY_PROMO_BADGES = 'promo_badges';

    public const OVERLAY_PREMIUM_BRAND = 'premium_brand';

    /** @return array<int, string> */
    public static function sourceAudioModes(): array
    {
        return [
            self::SOURCE_AUDIO_MUTE,
            self::SOURCE_AUDIO_LOW,
            self::SOURCE_AUDIO_KEEP,
            self::SOURCE_AUDIO_PRODUCT_SOUNDS,
        ];
    }

    /** @return array<int, string> */
    public static function voiceoverMixes(): array
    {
        return [
            self::VOICEOVER_MIX_BALANCED,
            self::VOICEOVER_MIX_DOMINANT,
            self::VOICEOVER_MIX_SOFT_BACKGROUND,
        ];
    }

    /** @return array<int, string> */
    public static function transitionStyles(): array
    {
        return [
            self::TRANSITION_CLEAN_CUTS,
            self::TRANSITION_SOFT_FADES,
            self::TRANSITION_SNAPPY_SOCIAL,
            self::TRANSITION_PREMIUM_SMOOTH,
        ];
    }

    /** @return array<int, string> */
    public static function motionPolishOptions(): array
    {
        return [
            self::MOTION_NONE,
            self::MOTION_SUBTLE_ZOOM,
            self::MOTION_PRODUCT_FOCUS,
            self::MOTION_DYNAMIC_CROP,
        ];
    }

    /** @return array<int, string> */
    public static function clipDiversityOptions(): array
    {
        return [
            self::CLIP_DIVERSITY_BALANCED,
            self::CLIP_DIVERSITY_MORE_UNIQUE,
            self::CLIP_DIVERSITY_CONCISE,
        ];
    }

    /** @return array<int, string> */
    public static function endScreenOptions(): array
    {
        return [
            self::END_SCREEN_NONE,
            self::END_SCREEN_SIMPLE_CTA,
            self::END_SCREEN_PRODUCT_HOLD_CTA,
        ];
    }

    /** @return array<int, string> */
    public static function overlayStyles(): array
    {
        return [
            self::OVERLAY_MINIMAL_CLEAN,
            self::OVERLAY_UGC_NATIVE,
            self::OVERLAY_PRODUCT_HIGHLIGHTS,
            self::OVERLAY_PROMO_BADGES,
            self::OVERLAY_PREMIUM_BRAND,
        ];
    }

    public static function sourceAudioMode(mixed $value, bool $hasVoiceover): string
    {
        if (! is_string($value) || trim($value) === '') {
            return $hasVoiceover ? self::SOURCE_AUDIO_MUTE : self::SOURCE_AUDIO_LOW;
        }

        $value = trim($value);

        return in_array($value, self::sourceAudioModes(), true) ? $value : $value;
    }

    public static function voiceoverMix(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return self::VOICEOVER_MIX_DOMINANT;
        }

        $value = trim($value);

        return in_array($value, self::voiceoverMixes(), true) ? $value : $value;
    }

    public static function transitionStyle(mixed $value, mixed $editingStyle = null): string
    {
        if (is_string($value) && trim($value) !== '') {
            $value = trim($value);

            return in_array($value, self::transitionStyles(), true) ? $value : $value;
        }

        return is_string($editingStyle) && str_contains(strtolower($editingStyle), 'premium')
            ? self::TRANSITION_PREMIUM_SMOOTH
            : self::TRANSITION_CLEAN_CUTS;
    }

    public static function motionPolish(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return self::MOTION_SUBTLE_ZOOM;
        }

        $value = trim($value);

        return in_array($value, self::motionPolishOptions(), true) ? $value : $value;
    }

    public static function clipDiversity(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return self::CLIP_DIVERSITY_BALANCED;
        }

        $value = trim($value);

        return in_array($value, self::clipDiversityOptions(), true) ? $value : $value;
    }

    public static function endScreen(mixed $value, bool $hasCta): string
    {
        if (! is_string($value) || trim($value) === '') {
            return $hasCta ? self::END_SCREEN_PRODUCT_HOLD_CTA : self::END_SCREEN_NONE;
        }

        $value = trim($value);

        return in_array($value, self::endScreenOptions(), true) ? $value : $value;
    }

    public static function overlayStyle(mixed $value, string $editMode = 'manual_edit', bool $hasProductMoments = false): string
    {
        if (is_string($value) && trim($value) !== '') {
            $value = trim($value);

            return in_array($value, self::overlayStyles(), true) ? $value : $value;
        }

        return $editMode === 'ai_video_analysis' && $hasProductMoments
            ? self::OVERLAY_PRODUCT_HIGHLIGHTS
            : self::OVERLAY_MINIMAL_CLEAN;
    }

    /** @return array{source_audio_volume: float, voiceover_volume: float, original_audio_muted: bool, audio_cleanup_strategy: string} */
    public static function audioMix(string $sourceAudioMode, string $voiceoverMix, bool $hasVoiceover): array
    {
        $sourceVolume = match ($sourceAudioMode) {
            self::SOURCE_AUDIO_MUTE => 0.0,
            self::SOURCE_AUDIO_KEEP => $hasVoiceover ? 0.22 : 1.0,
            self::SOURCE_AUDIO_PRODUCT_SOUNDS => $hasVoiceover ? 0.12 : 0.18,
            default => $hasVoiceover ? 0.08 : 0.14,
        };

        $voiceoverVolume = match ($voiceoverMix) {
            self::VOICEOVER_MIX_BALANCED => 0.96,
            self::VOICEOVER_MIX_SOFT_BACKGROUND => 0.88,
            default => 1.0,
        };

        if ($hasVoiceover && $voiceoverMix === self::VOICEOVER_MIX_DOMINANT) {
            $sourceVolume = min($sourceVolume, $sourceAudioMode === self::SOURCE_AUDIO_MUTE ? 0.0 : 0.08);
        }

        return [
            'source_audio_volume' => round($sourceVolume, 2),
            'voiceover_volume' => $hasVoiceover ? round($voiceoverVolume, 2) : 0.0,
            'original_audio_muted' => $sourceVolume <= 0.0,
            'audio_cleanup_strategy' => self::audioCleanupStrategy($sourceAudioMode, $voiceoverMix, $hasVoiceover),
        ];
    }

    private static function audioCleanupStrategy(string $sourceAudioMode, string $voiceoverMix, bool $hasVoiceover): string
    {
        if ($sourceAudioMode === self::SOURCE_AUDIO_MUTE) {
            return $hasVoiceover ? 'clean_voiceover_source_muted' : 'source_audio_muted';
        }

        if ($sourceAudioMode === self::SOURCE_AUDIO_PRODUCT_SOUNDS) {
            return 'subtle_product_sounds_under_voiceover';
        }

        if ($sourceAudioMode === self::SOURCE_AUDIO_KEEP) {
            return $hasVoiceover ? 'source_audio_reduced_under_voiceover' : 'source_audio_kept';
        }

        return $voiceoverMix === self::VOICEOVER_MIX_SOFT_BACKGROUND
            ? 'soft_voiceover_with_low_source_ambience'
            : 'low_source_audio_mix';
    }
}
