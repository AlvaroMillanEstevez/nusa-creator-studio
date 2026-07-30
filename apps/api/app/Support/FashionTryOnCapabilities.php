<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class FashionTryOnCapabilities
{
    public const TIER_STANDARD = 'standard';

    public const TIER_PREMIUM = 'premium';

    public const PACKAGE_SINGLE_RESULT = 'single_result';

    public const PACKAGE_VARIATIONS = 'variations';

    public const PACKAGE_CATALOG_PACK = 'catalog_pack';

    public const PACKAGE_CATALOG_VIDEO_PACK = 'catalog_video_pack';

    public const PERSON_SOURCE_USER_UPLOAD = 'user_upload';

    public const PERSON_SOURCE_NUSA_AI_MODEL = 'nusa_ai_model';

    public const PERSON_SOURCE_CUSTOM_AI_MODEL = 'custom_ai_model';

    /** @var list<string> */
    public const STANDARD_CATEGORIES = ['auto', 'tops', 'bottoms', 'one-pieces'];

    /** @var list<string> */
    public const STANDARD_GARMENT_PHOTO_TYPES = ['auto', 'flat_lay', 'model'];

    /** @var list<string> */
    public const STANDARD_MODES = ['performance', 'balanced', 'quality'];

    /** @var list<string> */
    public const SUPPORTED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    public const ACTIVE_PACKAGES = [self::PACKAGE_SINGLE_RESULT];

    /** @var list<string> */
    public const FUTURE_PACKAGES = [
        self::PACKAGE_VARIATIONS,
        self::PACKAGE_CATALOG_PACK,
        self::PACKAGE_CATALOG_VIDEO_PACK,
    ];

    /** @var list<string> */
    public const ACTIVE_PERSON_SOURCES = [self::PERSON_SOURCE_USER_UPLOAD];

    /** @var list<string> */
    public const FUTURE_PERSON_SOURCES = [
        self::PERSON_SOURCE_NUSA_AI_MODEL,
        self::PERSON_SOURCE_CUSTOM_AI_MODEL,
    ];

    /** @return array<string, array<string, mixed>> */
    public static function premiumPresets(): array
    {
        return [
            'premium_fast' => [
                'label' => 'Premium Fast',
                'resolution' => '1k',
                'generation_mode' => 'fast',
                'credit_cost_key' => 'premium_fast',
            ],
            'premium_balanced' => [
                'label' => 'Premium Balanced',
                'resolution' => '2k',
                'generation_mode' => 'balanced',
                'credit_cost_key' => 'premium_balanced',
            ],
            'premium_quality' => [
                'label' => 'Premium Quality',
                'resolution' => '4k',
                'generation_mode' => 'quality',
                'credit_cost_key' => 'premium_quality',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function forUser(?User $user = null): array
    {
        $selectedProvider = self::selectedProviderForUser($user);

        return [
            'provider' => $selectedProvider,
            'mock_mode' => $selectedProvider === 'mock',
            'public_enabled' => (bool) config('fashion_try_on.public_enabled', true),
            'real_rendering_supported' => true,
            'real_rendering_enabled' => self::realEnabledForUser($user),
            'standard_supported' => true,
            'standard_enabled' => (bool) config('fashion_try_on.fashn.standard_enabled', true),
            'premium_supported' => true,
            'premium_enabled' => self::premiumEnabledForUser($user),
            'standard_categories' => self::STANDARD_CATEGORIES,
            'standard_garment_photo_types' => self::STANDARD_GARMENT_PHOTO_TYPES,
            'standard_modes' => self::STANDARD_MODES,
            'premium_presets' => self::premiumEnabledForUser($user) ? array_keys(self::premiumPresets()) : [],
            'active_packages' => self::ACTIVE_PACKAGES,
            'default_package' => self::PACKAGE_SINGLE_RESULT,
            'future_packages' => self::FUTURE_PACKAGES,
            'active_person_sources' => self::ACTIVE_PERSON_SOURCES,
            'default_person_source' => self::PERSON_SOURCE_USER_UPLOAD,
            'future_person_sources' => self::FUTURE_PERSON_SOURCES,
            'maximum_outputs' => 1,
            'max_outputs_enabled' => 1,
            'multi_output_supported' => true,
            'multi_output_generation_enabled' => false,
            'consent_required' => true,
            'person_image_required' => true,
            'product_image_required' => true,
            'supported_mime_types' => self::SUPPORTED_IMAGE_MIME_TYPES,
        ];
    }

    public static function normalizePackage(?string $package): string
    {
        return in_array($package, self::ACTIVE_PACKAGES, true)
            ? $package
            : self::PACKAGE_SINGLE_RESULT;
    }

    public static function normalizePersonSource(?string $source): string
    {
        return in_array($source, self::ACTIVE_PERSON_SOURCES, true)
            ? $source
            : self::PERSON_SOURCE_USER_UPLOAD;
    }

    public static function selectedProviderForUser(?User $user = null): string
    {
        if (! self::realEnabledForUser($user)) {
            return 'mock';
        }

        return 'fashn';
    }

    public static function realEnabledForUser(?User $user = null): bool
    {
        if (! in_array(config('fashion_try_on.provider', 'mock'), ['fashn', 'real'], true)) {
            return false;
        }

        if (! (bool) config('fashion_try_on.real_enabled', false)) {
            return false;
        }

        if ((bool) config('fashion_try_on.real_internal_only', true)) {
            return $user !== null && self::userCanUseRealProvider($user);
        }

        return true;
    }

    public static function premiumEnabledForUser(?User $user = null): bool
    {
        return self::realEnabledForUser($user)
            && (bool) config('fashion_try_on.fashn.premium_enabled', false);
    }

    public static function normalizeTier(?string $tier): string
    {
        return Str::of($tier ?: self::TIER_STANDARD)->lower()->replace('_', '-')->toString() === self::TIER_PREMIUM
            ? self::TIER_PREMIUM
            : self::TIER_STANDARD;
    }

    public static function safeModerationLevel(): string
    {
        $level = Str::of((string) config('fashion_try_on.fashn.moderation_level', 'permissive'))
            ->lower()
            ->trim()
            ->toString();

        return $level === 'none' || $level === '' ? 'permissive' : $level;
    }

    private static function userCanUseRealProvider(User $user): bool
    {
        return $user->isAdmin()
            || $user->isInternalCreator()
            || $user->hasUnlimitedAccess()
            || $user->plan_key === 'internal_unlimited';
    }
}
