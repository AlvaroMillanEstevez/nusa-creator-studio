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

    public const CURRENT_MAX_OUTPUTS = 4;

    public const VARIATIONS_DEFAULT_OUTPUT_COUNT = 2;

    public const MAX_PROVIDER_SEED = 4294967295;

    /** @var list<int> */
    public const VARIATIONS_ALLOWED_OUTPUT_COUNTS = [2, 3, 4];

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
                'expected_provider_credits_per_output' => 1,
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
                'expected_provider_credits_per_output' => 3,
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
                'expected_provider_credits_per_output' => 5,
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
            'packages' => self::packages(),
            'active_packages' => self::activePackages(),
            'default_package' => self::PACKAGE_SINGLE_RESULT,
            'future_packages' => self::futurePackages(),
            'active_person_sources' => self::ACTIVE_PERSON_SOURCES,
            'default_person_source' => self::PERSON_SOURCE_USER_UPLOAD,
            'future_person_sources' => self::FUTURE_PERSON_SOURCES,
            'maximum_outputs' => self::CURRENT_MAX_OUTPUTS,
            'max_outputs_enabled' => self::variationsEnabled() ? self::CURRENT_MAX_OUTPUTS : self::STANDARD_OUTPUT_COUNT,
            'current_max_outputs' => self::CURRENT_MAX_OUTPUTS,
            'multi_output_supported' => true,
            'multi_output_generation_enabled' => self::variationsEnabled(),
            'variations_pricing_ready' => self::variationsPricingReady(),
            'variations_enabled' => self::variationsEnabled(),
            'variations' => [
                'enabled' => self::variationsEnabled(),
                'allowed_output_counts' => self::allowedOutputCounts(self::PACKAGE_VARIATIONS, self::TIER_STANDARD),
                'default_output_count' => self::defaultOutputCount(self::PACKAGE_VARIATIONS, self::TIER_STANDARD),
                'max_output_count' => self::maximumOutputCountForSelection(self::TIER_STANDARD),
                'selection_required_for_continuation' => true,
                'partial_refund_supported' => true,
                'standard' => self::outputCountCapability(self::TIER_STANDARD),
                'premium_presets' => self::premiumOutputCountCapabilities(),
            ],
            'output_limits' => [
                'max_bytes_per_output' => self::maxOutputBytesPerImage(),
                'max_total_output_bytes' => self::maxOutputBytesTotal(),
            ],
            'consent_required' => true,
            'person_image_required' => true,
            'product_image_required' => true,
            'supported_mime_types' => self::SUPPORTED_IMAGE_MIME_TYPES,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function packages(): array
    {
        return [
            [
                'key' => self::PACKAGE_SINGLE_RESULT,
                'enabled' => true,
                'status' => 'available',
                'allowed_output_counts' => [self::STANDARD_OUTPUT_COUNT],
                'default_output_count' => self::STANDARD_OUTPUT_COUNT,
                'selection_required_for_continuation' => false,
                'partial_refund_supported' => false,
            ],
            [
                'key' => self::PACKAGE_VARIATIONS,
                'enabled' => self::variationsEnabled(),
                'status' => self::variationsEnabled() ? 'beta' : 'coming_soon',
                'allowed_output_counts' => self::allowedOutputCounts(self::PACKAGE_VARIATIONS, self::TIER_STANDARD),
                'default_output_count' => self::defaultOutputCount(self::PACKAGE_VARIATIONS, self::TIER_STANDARD),
                'selection_required_for_continuation' => true,
                'partial_refund_supported' => true,
            ],
            [
                'key' => self::PACKAGE_CATALOG_PACK,
                'enabled' => false,
                'status' => 'coming_soon',
                'allowed_output_counts' => [],
                'default_output_count' => null,
                'selection_required_for_continuation' => true,
                'partial_refund_supported' => false,
            ],
            [
                'key' => self::PACKAGE_CATALOG_VIDEO_PACK,
                'enabled' => false,
                'status' => 'coming_soon',
                'allowed_output_counts' => [],
                'default_output_count' => null,
                'selection_required_for_continuation' => true,
                'partial_refund_supported' => false,
            ],
        ];
    }

    /** @return list<string> */
    public static function activePackages(): array
    {
        return array_values(array_filter([
            self::PACKAGE_SINGLE_RESULT,
            self::variationsEnabled() ? self::PACKAGE_VARIATIONS : null,
        ], fn (?string $value): bool => $value !== null));
    }

    /** @return list<string> */
    public static function futurePackages(): array
    {
        return array_values(array_filter([
            self::variationsEnabled() ? null : self::PACKAGE_VARIATIONS,
            self::PACKAGE_CATALOG_PACK,
            self::PACKAGE_CATALOG_VIDEO_PACK,
        ], fn (?string $value): bool => $value !== null));
    }

    /** @return list<string> */
    public static function canonicalPackageKeys(): array
    {
        return [
            self::PACKAGE_SINGLE_RESULT,
            self::PACKAGE_VARIATIONS,
            self::PACKAGE_CATALOG_PACK,
            self::PACKAGE_CATALOG_VIDEO_PACK,
        ];
    }

    public static function packageEnabled(string $package): bool
    {
        return in_array($package, self::activePackages(), true);
    }

    /** @return list<int> */
    public static function allowedOutputCounts(string $package, ?string $tier = null, ?string $premiumPreset = null): array
    {
        if ($package !== self::PACKAGE_VARIATIONS) {
            return [self::STANDARD_OUTPUT_COUNT];
        }

        return self::outputCountCapability($tier ?? self::TIER_STANDARD, $premiumPreset)['allowed_output_counts'];
    }

    public static function defaultOutputCount(string $package, ?string $tier = null, ?string $premiumPreset = null): int
    {
        if ($package !== self::PACKAGE_VARIATIONS) {
            return self::STANDARD_OUTPUT_COUNT;
        }

        return self::outputCountCapability($tier ?? self::TIER_STANDARD, $premiumPreset)['default_output_count'];
    }

    /** @return array{allowed_output_counts: list<int>, default_output_count: int, max_output_count: int, limited_for_beta_response_safety: bool} */
    public static function outputCountCapability(string $tier, ?string $premiumPreset = null): array
    {
        $tier = self::normalizeTier($tier);
        $max = self::maximumOutputCountForSelection($tier, $premiumPreset);
        $allowed = array_values(array_filter(
            self::VARIATIONS_ALLOWED_OUTPUT_COUNTS,
            fn (int $count): bool => $count <= $max,
        ));

        if ($allowed === []) {
            $allowed = [self::VARIATIONS_DEFAULT_OUTPUT_COUNT];
        }

        $default = in_array(self::VARIATIONS_DEFAULT_OUTPUT_COUNT, $allowed, true)
            ? self::VARIATIONS_DEFAULT_OUTPUT_COUNT
            : $allowed[0];

        $limitedForBetaSafety = $tier === self::TIER_PREMIUM
            && self::normalizePremiumPreset($premiumPreset) === self::PREMIUM_PRESET_QUALITY_4K
            && $max < self::CURRENT_MAX_OUTPUTS;

        return [
            'allowed_output_counts' => $allowed,
            'default_output_count' => $default,
            'max_output_count' => $max,
            'limited_for_beta_response_safety' => $limitedForBetaSafety,
        ];
    }

    public static function maximumOutputCountForSelection(string $tier, ?string $premiumPreset = null): int
    {
        $tier = self::normalizeTier($tier);

        if ($tier === self::TIER_PREMIUM) {
            $preset = self::normalizePremiumPreset($premiumPreset);
            $default = $preset === self::PREMIUM_PRESET_QUALITY_4K
                ? self::VARIATIONS_DEFAULT_OUTPUT_COUNT
                : self::CURRENT_MAX_OUTPUTS;

            return self::configuredVariationMaxOutputCount(
                config('fashion_try_on.output_max_counts.premium_presets.'.$preset),
                $default,
            );
        }

        return self::configuredVariationMaxOutputCount(
            config('fashion_try_on.output_max_counts.standard'),
            self::CURRENT_MAX_OUTPUTS,
        );
    }

    /** @return array<string, array<string, mixed>> */
    public static function premiumOutputCountCapabilities(): array
    {
        $capabilities = [];

        foreach (self::premiumPresetKeys() as $preset) {
            $capabilities[$preset] = self::outputCountCapability(self::TIER_PREMIUM, $preset);
        }

        return $capabilities;
    }

    private static function configuredVariationMaxOutputCount(mixed $value, int $default): int
    {
        $count = is_numeric($value) ? (int) $value : $default;

        return min(self::CURRENT_MAX_OUTPUTS, max(self::VARIATIONS_DEFAULT_OUTPUT_COUNT, $count));
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
        return (bool) config('fashion_try_on.variations_enabled', false);
    }

    public static function standardProviderCreditsPerOutput(): int
    {
        return 1;
    }

    public static function expectedProviderCreditsPerOutput(string $tier, ?string $premiumPreset = null): int
    {
        if ($tier === self::TIER_PREMIUM) {
            $preset = self::premiumPreset(self::normalizePremiumPreset($premiumPreset));

            return max(0, (int) ($preset['expected_provider_credits_per_output'] ?? $preset['expected_provider_credits'] ?? 0));
        }

        return self::standardProviderCreditsPerOutput();
    }

    public static function expectedProviderCreditsTotal(string $tier, ?string $premiumPreset, int $outputCount): int
    {
        return self::expectedProviderCreditsPerOutput($tier, $premiumPreset) * max(0, $outputCount);
    }

    public static function maxOutputBytesPerImage(): int
    {
        return max(1, (int) config('fashion_try_on.fashn.output_max_bytes_per_image', 33554432));
    }

    public static function maxOutputBytesTotal(): int
    {
        return max(self::maxOutputBytesPerImage(), (int) config('fashion_try_on.fashn.output_max_bytes_total', 134217728));
    }

    /** @return list<array<string, mixed>> */
    public static function knownPremiumPresetsForUser(?User $user = null): array
    {
        return array_values(array_map(function (array $preset) use ($user): array {
            $key = (string) $preset['key'];
            $cost = self::premiumCreditCost($key);
            $pricingConfigured = $cost !== null;
            $disabledReason = self::premiumPresetDisabledReason($user, $key);
            $outputCountCapability = self::outputCountCapability(self::TIER_PREMIUM, $key);
            $allowedCounts = $outputCountCapability['allowed_output_counts'];

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
                'max_output_count' => $outputCountCapability['max_output_count'],
                'allowed_output_counts' => $allowedCounts,
                'default_output_count' => $outputCountCapability['default_output_count'],
                'limited_for_beta_response_safety' => $outputCountCapability['limited_for_beta_response_safety'],
                'variations_pricing_ready' => self::variationsPricingReady(),
                'variations_enabled' => self::variationsEnabled(),
                'total_credit_costs' => self::totalCreditCostsForCounts($cost, $allowedCounts),
                'expected_provider_credits_per_output' => self::expectedProviderCreditsPerOutput(self::TIER_PREMIUM, $key),
                'expected_provider_credits_total_by_count' => self::totalProviderCreditsForCounts(self::TIER_PREMIUM, $key, $allowedCounts),
            ];
        }, self::premiumPresets()));
    }

    /** @param list<int> $counts @return array<int, int>|null */
    public static function totalCreditCostsForCounts(?int $perOutputCost, array $counts): ?array
    {
        if ($perOutputCost === null) {
            return null;
        }

        $totals = [];

        foreach ($counts as $count) {
            $totals[$count] = $perOutputCost * $count;
        }

        return $totals;
    }

    /** @param list<int> $counts @return array<int, int> */
    public static function totalProviderCreditsForCounts(string $tier, ?string $preset, array $counts): array
    {
        $perOutput = self::expectedProviderCreditsPerOutput($tier, $preset);
        $totals = [];

        foreach ($counts as $count) {
            $totals[$count] = $perOutput * $count;
        }

        return $totals;
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
        return in_array($package, self::activePackages(), true)
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
