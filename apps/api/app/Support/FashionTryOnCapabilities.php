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

    public const PREMIUM_PRESET_FAST_1K = 'fast_1k';

    public const PREMIUM_PRESET_BALANCED_2K = 'balanced_2k';

    public const PREMIUM_PRESET_QUALITY_4K = 'quality_4k';

    public const PREMIUM_OUTPUT_COUNT = 1;

    public const STANDARD_OUTPUT_COUNT = 1;

    public const CURRENT_MAX_OUTPUTS = 1;

    public const MAX_PROVIDER_SEED = 4294967295;

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
            self::PREMIUM_PRESET_FAST_1K => [
                'key' => self::PREMIUM_PRESET_FAST_1K,
                'label' => 'Premium Fast',
                'label_key' => 'fashionTryOn.premiumPresets.fast_1k.label',
                'description_key' => 'fashionTryOn.premiumPresets.fast_1k.description',
                'resolution' => '1k',
                'resolution_label' => '1K',
                'generation_mode' => 'fast',
                'quality_label' => 'Fast',
                'expected_provider_credits' => 1,
                'num_images' => self::PREMIUM_OUTPUT_COUNT,
                'recommended' => false,
            ],
            self::PREMIUM_PRESET_BALANCED_2K => [
                'key' => self::PREMIUM_PRESET_BALANCED_2K,
                'label' => 'Premium Balanced',
                'label_key' => 'fashionTryOn.premiumPresets.balanced_2k.label',
                'description_key' => 'fashionTryOn.premiumPresets.balanced_2k.description',
                'resolution' => '2k',
                'resolution_label' => '2K',
                'generation_mode' => 'balanced',
                'quality_label' => 'Balanced',
                'expected_provider_credits' => 3,
                'num_images' => self::PREMIUM_OUTPUT_COUNT,
                'recommended' => true,
            ],
            self::PREMIUM_PRESET_QUALITY_4K => [
                'key' => self::PREMIUM_PRESET_QUALITY_4K,
                'label' => 'Premium Quality',
                'label_key' => 'fashionTryOn.premiumPresets.quality_4k.label',
                'description_key' => 'fashionTryOn.premiumPresets.quality_4k.description',
                'resolution' => '4k',
                'resolution_label' => '4K',
                'generation_mode' => 'quality',
                'quality_label' => 'Quality',
                'expected_provider_credits' => 5,
                'num_images' => self::PREMIUM_OUTPUT_COUNT,
                'recommended' => false,
            ],
        ];
    }

    /** @return list<string> */
    public static function premiumPresetKeys(): array
    {
        return array_keys(self::premiumPresets());
    }

    /** @return array<string, mixed> */
    public static function forUser(?User $user = null): array
    {
        $selectedProvider = self::selectedProviderForUser($user);
        $knownPremiumPresets = self::knownPremiumPresetsForUser($user);
        $availablePremiumPresets = array_values(array_filter(
            $knownPremiumPresets,
            fn (array $preset): bool => ($preset['enabled'] ?? false) === true,
        ));

        return [
            'provider' => $selectedProvider,
            'mock_mode' => $selectedProvider === 'mock',
            'public_enabled' => (bool) config('fashion_try_on.public_enabled', true),
            'real_rendering_supported' => true,
            'real_rendering_enabled' => self::realEnabledForUser($user),
            'standard_supported' => true,
            'standard_enabled' => (bool) config('fashion_try_on.fashn.standard_enabled', true),
            'standard_credit_cost' => self::standardCreditCost(),
            'standard_per_output_credit_cost' => self::standardCreditCost(),
            'standard_output_count' => self::STANDARD_OUTPUT_COUNT,
            'premium_supported' => true,
            'premium_enabled' => self::premiumEnabledForUser($user),
            'premium_real_available_for_user' => self::premiumRealAvailableForUser($user),
            'premium_pricing_configured' => self::premiumPricingConfigured(),
            'premium_default_preset' => self::defaultPremiumPresetKey(),
            'premium_quality_enabled' => self::premiumQualityEnabled(),
            'premium_output_count' => self::PREMIUM_OUTPUT_COUNT,
            'premium_presets' => array_column($availablePremiumPresets, 'key'),
            'available_premium_presets' => $availablePremiumPresets,
            'known_premium_presets' => $knownPremiumPresets,
            'standard_categories' => self::STANDARD_CATEGORIES,
            'standard_garment_photo_types' => self::STANDARD_GARMENT_PHOTO_TYPES,
            'standard_modes' => self::STANDARD_MODES,
            'active_packages' => self::ACTIVE_PACKAGES,
            'default_package' => self::PACKAGE_SINGLE_RESULT,
            'future_packages' => self::FUTURE_PACKAGES,
            'active_person_sources' => self::ACTIVE_PERSON_SOURCES,
            'default_person_source' => self::PERSON_SOURCE_USER_UPLOAD,
            'future_person_sources' => self::FUTURE_PERSON_SOURCES,
            'maximum_outputs' => self::CURRENT_MAX_OUTPUTS,
            'max_outputs_enabled' => self::CURRENT_MAX_OUTPUTS,
            'current_max_outputs' => self::CURRENT_MAX_OUTPUTS,
            'multi_output_supported' => true,
            'multi_output_generation_enabled' => false,
            'variations_pricing_ready' => self::variationsPricingReady(),
            'variations_enabled' => self::variationsEnabled(),
            'consent_required' => true,
            'person_image_required' => true,
            'product_image_required' => true,
            'supported_mime_types' => self::SUPPORTED_IMAGE_MIME_TYPES,
        ];
    }

    /** @return array<string, mixed> */
    public static function premiumPreset(string $key): array
    {
        $presets = self::premiumPresets();

        return $presets[$key] ?? $presets[self::defaultPremiumPresetKey()];
    }

    public static function defaultPremiumPresetKey(): string
    {
        $configured = (string) config('fashion_try_on.fashn.premium_default_preset', self::PREMIUM_PRESET_BALANCED_2K);

        return in_array($configured, self::premiumPresetKeys(), true)
            ? $configured
            : self::PREMIUM_PRESET_BALANCED_2K;
    }

    public static function normalizePremiumPreset(?string $preset): string
    {
        return in_array($preset, self::premiumPresetKeys(), true)
            ? $preset
            : self::defaultPremiumPresetKey();
    }

    /** @param array<string, mixed> $input */
    public static function premiumPresetKeyFromInput(array $input): string
    {
        $value = $input['premium_preset'] ?? $input['quality_preset'] ?? null;

        return self::normalizePremiumPreset(is_string($value) ? $value : null);
    }

    public static function standardCreditCost(): int
    {
        return self::configuredCreditCost(config('fashion_try_on.credit_costs.standard')) ?? 2;
    }

    public static function premiumCreditCost(?string $preset): ?int
    {
        $key = self::normalizePremiumPreset($preset);

        return self::configuredCreditCost(config('fashion_try_on.credit_costs.premium_presets.'.$key));
    }

    public static function configuredCreditCost(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    public static function premiumPricingConfigured(): bool
    {
        foreach (self::premiumPresetKeys() as $preset) {
            if (self::premiumCreditCost($preset) === null) {
                return false;
            }
        }

        return true;
    }

    public static function premiumQualityEnabled(): bool
    {
        return (bool) config('fashion_try_on.fashn.premium_quality_enabled', false);
    }

    public static function premiumRealAvailableForUser(?User $user = null): bool
    {
        return self::realEnabledForUser($user)
            && (bool) config('fashion_try_on.fashn.premium_enabled', false);
    }

    public static function premiumEnabledForUser(?User $user = null): bool
    {
        if (! self::premiumRealAvailableForUser($user)) {
            return false;
        }

        return count(array_filter(
            self::premiumPresetKeys(),
            fn (string $preset): bool => self::premiumPresetEnabledForUser($user, $preset),
        )) > 0;
    }

    public static function premiumPresetEnabledForUser(?User $user, string $preset): bool
    {
        return self::premiumPresetDisabledReason($user, $preset) === null;
    }

    public static function premiumPresetDisabledReason(?User $user, string $preset): ?string
    {
        if (! self::premiumRealAvailableForUser($user)) {
            return 'premium_disabled';
        }

        if ($preset === self::PREMIUM_PRESET_QUALITY_4K && ! self::premiumQualityEnabled()) {
            return 'premium_quality_validation_pending';
        }

        if (self::premiumCreditCost($preset) === null && ! self::userCanUsePremiumWithoutConfiguredPricing($user)) {
            return 'premium_pricing_unconfigured';
        }

        return null;
    }

    public static function premiumPresetValidationStatus(string $preset): string
    {
        if ($preset === self::PREMIUM_PRESET_QUALITY_4K && ! self::premiumQualityEnabled()) {
            return 'final_provider_validation_pending';
        }

        return 'beta_validated';
    }

    public static function userCanUsePremiumWithoutConfiguredPricing(?User $user): bool
    {
        return $user !== null && $user->hasUnlimitedAccess();
    }

    public static function variationsPricingReady(): bool
    {
        return true;
    }

    public static function variationsEnabled(): bool
    {
        return false;
    }

    /** @return list<array<string, mixed>> */
    public static function knownPremiumPresetsForUser(?User $user = null): array
    {
        return array_values(array_map(function (array $preset) use ($user): array {
            $key = (string) $preset['key'];
            $cost = self::premiumCreditCost($key);
            $pricingConfigured = $cost !== null;
            $disabledReason = self::premiumPresetDisabledReason($user, $key);

            return $preset + [
                'nusa_credit_cost' => $cost,
                'configured_nusa_credit_cost' => $cost,
                'per_output_credit_cost' => $cost,
                'pricing_configured' => $pricingConfigured,
                'enabled' => $disabledReason === null,
                'disabled_reason' => $disabledReason,
                'validation_status' => self::premiumPresetValidationStatus($key),
                'beta' => true,
                'output_count' => self::PREMIUM_OUTPUT_COUNT,
                'current_max_outputs' => self::CURRENT_MAX_OUTPUTS,
                'variations_pricing_ready' => self::variationsPricingReady(),
                'variations_enabled' => self::variationsEnabled(),
            ];
        }, self::premiumPresets()));
    }

    public static function sanitizePremiumPrompt(mixed $prompt): ?string
    {
        if (! is_string($prompt)) {
            return null;
        }

        $prompt = trim($prompt);

        return $prompt === '' ? null : Str::limit($prompt, self::premiumPromptMaxCharacters(), '');
    }

    public static function premiumPromptMaxCharacters(): int
    {
        return max(1, (int) config('fashion_try_on.fashn.premium_prompt_max_characters', 500));
    }

    public static function generateProviderSeed(): int
    {
        return random_int(0, self::MAX_PROVIDER_SEED);
    }

    public static function normalizeProviderSeed(mixed $seed): ?int
    {
        if (is_int($seed)) {
            return $seed >= 0 && $seed <= self::MAX_PROVIDER_SEED ? $seed : null;
        }

        if (is_string($seed) && preg_match('/^\d+$/', $seed) === 1) {
            $value = (int) $seed;

            return $value >= 0 && $value <= self::MAX_PROVIDER_SEED ? $value : null;
        }

        return null;
    }

    public static function estimatedProviderCostUsd(int|float|null $providerCredits): ?float
    {
        $rate = config('fashion_try_on.fashn.estimated_cost_usd_per_credit');

        if (! is_numeric($rate) || ! is_numeric($providerCredits)) {
            return null;
        }

        return round((float) $providerCredits * (float) $rate, 6);
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
