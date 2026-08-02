<?php

namespace App\Services\Generation;

use App\Exceptions\FashionTryOn\FashionTryOnProviderException;
use App\Exceptions\MissingFashionTryOnAssetsException;
use App\Jobs\GenerateFashionTryOnJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use App\Support\FashionTryOnCapabilities;
use DomainException;
use Illuminate\Support\Str;

class FashionTryOnGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
        private readonly FashionTryOnAssetResolver $assets,
        private readonly FashionTryOnProviderManager $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Project $project, User $user, array $options): Generation
    {
        if (! in_array($project->type, ['workspace', 'fashion_try_on'], true)) {
            throw new DomainException('Only project workspaces can generate AI Fashion Try-On results.');
        }

        $packageKey = $this->activePackage($options);
        $personSource = $this->activePersonSource($options);
        $tier = FashionTryOnCapabilities::normalizeTier(is_string($options['tier'] ?? null) ? $options['tier'] : null);
        $premiumPreset = null;
        $providerSeed = $packageKey === FashionTryOnCapabilities::PACKAGE_VARIATIONS
            ? $this->providerSeed($options)
            : null;
        $premiumPrompt = null;

        if ($tier === FashionTryOnCapabilities::TIER_PREMIUM) {
            if (! FashionTryOnCapabilities::premiumRealAvailableForUser($user)) {
                throw FashionTryOnProviderException::invalidInput('fashn_premium_disabled', [
                    'provider' => FashionTryOnCapabilities::selectedProviderForUser($user),
                    'stage' => 'validate_tier',
                    'tier' => $tier,
                ]);
            }

            $premiumPreset = $this->premiumPreset($options);

            if (! FashionTryOnCapabilities::premiumPresetEnabledForUser($user, $premiumPreset)) {
                $disabledReason = FashionTryOnCapabilities::premiumPresetDisabledReason($user, $premiumPreset) ?? 'premium_disabled';
                $errorCode = match ($disabledReason) {
                    'premium_quality_validation_pending' => 'fashn_premium_quality_validation_pending',
                    'premium_pricing_unconfigured' => 'fashn_premium_pricing_unconfigured',
                    default => 'fashn_premium_disabled',
                };

                throw FashionTryOnProviderException::invalidInput($errorCode, [
                    'provider' => FashionTryOnCapabilities::selectedProviderForUser($user),
                    'stage' => 'validate_premium_preset_availability',
                    'tier' => $tier,
                    'premium_preset' => $premiumPreset,
                    'disabled_reason' => $disabledReason,
                ]);
            }

            $providerSeed ??= $this->providerSeed($options);
            $premiumPrompt = FashionTryOnCapabilities::sanitizePremiumPrompt($options['premium_prompt'] ?? $options['prompt'] ?? null);
        }

        if ($tier === FashionTryOnCapabilities::TIER_STANDARD && ! (bool) config('fashion_try_on.fashn.standard_enabled', true)) {
            throw FashionTryOnProviderException::invalidInput('fashn_standard_disabled', [
                'provider' => FashionTryOnCapabilities::selectedProviderForUser($user),
                'stage' => 'validate_tier',
                'tier' => $tier,
            ]);
        }

        if (($options['person_image_permission_confirmed'] ?? false) !== true) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'validate_consent',
                'safe_error_category' => 'consent_required',
            ]);
        }

        $requestedOutputCount = $this->requestedOutputCount($options, $packageKey, $tier, $premiumPreset);
        $personImage = $this->resolveRequestAsset($project, $user, $options, 'person_image_asset_id', 'person_image');
        $productImage = $this->resolveRequestAsset($project, $user, $options, 'product_image_asset_id', 'product_image');
        $provider = $this->providers->selectedProviderForUser($user);
        $perOutputCreditCost = $this->perOutputCreditCost($user, $tier, $premiumPreset);
        $creditCost = $perOutputCreditCost * $requestedOutputCount;

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_fashion_try_on',
            creditCost: $creditCost,
            creditReason: $this->creditReason($tier, $provider, $premiumPreset),
            dispatch: fn (int $generationId) => GenerateFashionTryOnJob::dispatch($generationId),
            inputPayload: $this->inputPayload(
                project: $project,
                packageKey: $packageKey,
                personSource: $personSource,
                tier: $tier,
                provider: $provider,
                personImage: $personImage,
                productImage: $productImage,
                options: $options,
                premiumPreset: $premiumPreset,
                providerSeed: $providerSeed,
                premiumPrompt: $premiumPrompt,
                creditCost: $creditCost,
                perOutputCreditCost: $perOutputCreditCost,
                requestedOutputCount: $requestedOutputCount,
            ),
            provider: $provider,
        );
    }

    /**
     * Legacy helper retained for older internal call sites.
     */
    public function generateFromLatestProjectAssets(Project $project, User $user): Generation
    {
        $person = $this->latestRoleAsset($project, 'model_reference');
        $product = $this->latestRoleAsset($project, 'garment_reference');

        if (! $person instanceof MediaAsset || ! $product instanceof MediaAsset) {
            throw new MissingFashionTryOnAssetsException;
        }

        return $this->generate($project, $user, [
            'package_key' => FashionTryOnCapabilities::PACKAGE_SINGLE_RESULT,
            'person_source' => FashionTryOnCapabilities::PERSON_SOURCE_USER_UPLOAD,
            'tier' => FashionTryOnCapabilities::TIER_STANDARD,
            'person_image_asset_id' => $person->getKey(),
            'product_image_asset_id' => $product->getKey(),
            'category' => 'auto',
            'person_image_permission_confirmed' => true,
        ]);
    }

    /** @param array<string, mixed> $options */
    private function activePackage(array $options): string
    {
        $value = is_string($options['package_key'] ?? null) ? $options['package_key'] : null;

        if ($value !== null && ! in_array($value, FashionTryOnCapabilities::canonicalPackageKeys(), true)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'validate_package',
                'field' => 'package_key',
            ]);
        }

        if ($value !== null && ! FashionTryOnCapabilities::packageEnabled($value)) {
            $reason = $value === FashionTryOnCapabilities::PACKAGE_VARIATIONS
                ? 'fashn_variations_disabled'
                : 'fashn_package_unavailable';

            throw FashionTryOnProviderException::invalidInput($reason, [
                'stage' => 'validate_package',
                'field' => 'package_key',
                'package_key' => $value,
            ]);
        }

        return FashionTryOnCapabilities::normalizePackage($value);
    }

    /** @param array<string, mixed> $options */
    private function activePersonSource(array $options): string
    {
        $value = is_string($options['person_source'] ?? null) ? $options['person_source'] : null;

        if ($value !== null && ! in_array($value, FashionTryOnCapabilities::ACTIVE_PERSON_SOURCES, true)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'validate_person_source',
                'field' => 'person_source',
            ]);
        }

        return FashionTryOnCapabilities::normalizePersonSource($value);
    }

    /** @param array<string, mixed> $options */
    private function premiumPreset(array $options): string
    {
        $value = $options['premium_preset'] ?? $options['quality_preset'] ?? null;
        $preset = is_string($value) ? $value : FashionTryOnCapabilities::defaultPremiumPresetKey();

        if (! in_array($preset, FashionTryOnCapabilities::premiumPresetKeys(), true)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'validate_premium_preset',
                'field' => 'premium_preset',
            ]);
        }

        return $preset;
    }

    /** @param array<string, mixed> $options */
    private function providerSeed(array $options): int
    {
        $provided = FashionTryOnCapabilities::normalizeProviderSeed($options['provider_seed'] ?? null);

        return $provided ?? FashionTryOnCapabilities::generateProviderSeed();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveRequestAsset(Project $project, User $user, array $options, string $key, string $field): MediaAsset
    {
        $assetId = $options[$key] ?? null;

        if (! is_numeric($assetId)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'validate_'.$field,
                'field' => $key,
            ]);
        }

        return $this->assets->resolveProjectImage($user, $project, (int) $assetId, $field);
    }

    private function latestRoleAsset(Project $project, string $usageRole): ?MediaAsset
    {
        return $project->mediaAssets()
            ->where('usage_role', $usageRole)
            ->where('type', 'image')
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    /** @param array<string, mixed> $options */
    private function requestedOutputCount(array $options, string $packageKey, string $tier, ?string $premiumPreset): int
    {
        $value = $options['requested_output_count'] ?? null;
        $allowedCounts = FashionTryOnCapabilities::allowedOutputCounts($packageKey, $tier, $premiumPreset);

        if ($value === null || $value === '') {
            return FashionTryOnCapabilities::defaultOutputCount($packageKey, $tier, $premiumPreset);
        }

        if (! is_int($value)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'validate_requested_output_count',
                'field' => 'requested_output_count',
                'package_key' => $packageKey,
                'tier' => $tier,
                'premium_preset' => $premiumPreset,
                'allowed_output_counts' => $allowedCounts,
            ]);
        }

        if (! in_array($value, $allowedCounts, true)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'validate_requested_output_count',
                'field' => 'requested_output_count',
                'package_key' => $packageKey,
                'tier' => $tier,
                'premium_preset' => $premiumPreset,
                'allowed_output_counts' => $allowedCounts,
            ]);
        }

        return $value;
    }

    private function perOutputCreditCost(User $user, string $tier, ?string $premiumPreset): int
    {
        if ($tier === FashionTryOnCapabilities::TIER_PREMIUM) {
            $presetCost = FashionTryOnCapabilities::premiumCreditCost($premiumPreset);

            if ($presetCost !== null) {
                return $presetCost;
            }

            if (FashionTryOnCapabilities::userCanUsePremiumWithoutConfiguredPricing($user)) {
                return 0;
            }

            throw FashionTryOnProviderException::invalidInput('fashn_premium_pricing_unconfigured', [
                'provider' => FashionTryOnCapabilities::selectedProviderForUser($user),
                'stage' => 'validate_premium_pricing',
                'tier' => $tier,
                'premium_preset' => $premiumPreset,
            ]);
        }

        $standardCost = config('fashion_try_on.credit_costs.standard');

        return is_numeric($standardCost) ? max(0, (int) $standardCost) : $this->tools->creditCost('fashion_try_on');
    }

    private function creditReason(string $tier, string $provider, ?string $premiumPreset): string
    {
        $suffix = $tier === FashionTryOnCapabilities::TIER_PREMIUM && $premiumPreset !== null
            ? ' '.$premiumPreset
            : '';

        return $provider === 'fashn'
            ? 'AI Fashion Try-On '.Str::title($tier).$suffix.' generation'
            : 'Mock AI Fashion Try-On '.Str::title($tier).$suffix.' preparation';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function inputPayload(
        Project $project,
        string $packageKey,
        string $personSource,
        string $tier,
        string $provider,
        MediaAsset $personImage,
        MediaAsset $productImage,
        array $options,
        ?string $premiumPreset,
        ?int $providerSeed,
        ?string $premiumPrompt,
        int $creditCost,
        int $perOutputCreditCost,
        int $requestedOutputCount,
    ): array {
        $projectInput = is_array($project->input_payload) ? $project->input_payload : [];
        $category = is_string($options['category'] ?? null) ? $options['category'] : 'auto';
        $payload = array_merge($projectInput, [
            'source_workflow' => 'ai_fashion_try_on',
            'origin_workflow' => 'ai_fashion_try_on',
            'origin_label' => 'AI Fashion Try-On',
            'provider' => $provider,
            'package_key' => $packageKey,
            'person_source' => $personSource,
            'tier' => $tier,
            'person_image_asset_id' => $personImage->getKey(),
            'product_image_asset_id' => $productImage->getKey(),
            'person_image_permission_confirmed' => true,
            'consent_confirmed_at' => now()->toISOString(),
            'nusa_credit_cost' => $creditCost,
            'configured_nusa_credit_cost' => $creditCost,
            'total_nusa_credit_cost' => $creditCost,
            'per_output_credit_cost' => $perOutputCreditCost,
            'requested_output_count' => $requestedOutputCount,
            'output_count' => $requestedOutputCount,
            'current_max_outputs' => FashionTryOnCapabilities::CURRENT_MAX_OUTPUTS,
            'maximum_allowed_outputs' => FashionTryOnCapabilities::maximumOutputCountForSelection($tier, $premiumPreset),
            'allowed_output_counts' => FashionTryOnCapabilities::allowedOutputCounts($packageKey, $tier, $premiumPreset),
            'expected_provider_credits_per_output' => FashionTryOnCapabilities::expectedProviderCreditsPerOutput($tier, $premiumPreset),
            'expected_provider_credits_total' => FashionTryOnCapabilities::expectedProviderCreditsTotal($tier, $premiumPreset, $requestedOutputCount),
            'variations_pricing_ready' => FashionTryOnCapabilities::variationsPricingReady(),
            'variations_enabled' => FashionTryOnCapabilities::variationsEnabled(),
            'assets' => [
                'model_reference' => $this->assetSummary($personImage),
                'garment_reference' => $this->assetSummary($productImage),
                'person_image' => $this->assetSummary($personImage),
                'product_image' => $this->assetSummary($productImage),
            ],
            'project_context_summary' => [
                'project_id' => $project->getKey(),
                'title' => $project->title,
                'product_name' => $projectInput['product_name'] ?? null,
                'target_audience' => $projectInput['target_audience'] ?? null,
            ],
        ]);

        if ($tier === FashionTryOnCapabilities::TIER_PREMIUM && $premiumPreset !== null && $providerSeed !== null) {
            $preset = FashionTryOnCapabilities::premiumPreset($premiumPreset);
            $payload = array_merge($payload, [
                'premium_preset' => $premiumPreset,
                'premium_prompt' => $premiumPrompt,
                'provider_seed' => $providerSeed,
                'provider_resolution' => $preset['resolution'],
                'provider_generation_mode' => $preset['generation_mode'],
                'expected_provider_credits' => FashionTryOnCapabilities::expectedProviderCreditsTotal($tier, $premiumPreset, $requestedOutputCount),
                'expected_provider_credits_per_output' => FashionTryOnCapabilities::expectedProviderCreditsPerOutput($tier, $premiumPreset),
                'expected_provider_credits_total' => FashionTryOnCapabilities::expectedProviderCreditsTotal($tier, $premiumPreset, $requestedOutputCount),
                'output_count' => $requestedOutputCount,
                'requested_output_count' => $requestedOutputCount,
                'configured_nusa_credit_cost' => $creditCost,
                'total_nusa_credit_cost' => $creditCost,
                'per_output_credit_cost' => $perOutputCreditCost,
                'premium_pricing_configured' => FashionTryOnCapabilities::premiumCreditCost($premiumPreset) !== null,
            ]);
        } else {
            $payload = array_merge($payload, [
                'category' => in_array($category, FashionTryOnCapabilities::STANDARD_CATEGORIES, true) ? $category : 'auto',
                'garment_photo_type' => is_string($options['garment_photo_type'] ?? null) ? $options['garment_photo_type'] : null,
                'standard_mode' => is_string($options['standard_mode'] ?? null) ? $options['standard_mode'] : null,
                'provider_seed' => $providerSeed,
            ]);
        }

        return array_filter($payload, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return array<string, mixed> */
    private function assetSummary(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'usage_role' => $asset->usage_role,
            'type' => $asset->type,
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
        ];
    }
}
