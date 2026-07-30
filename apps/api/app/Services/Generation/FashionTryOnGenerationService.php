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

        if ($tier === FashionTryOnCapabilities::TIER_PREMIUM && ! FashionTryOnCapabilities::premiumEnabledForUser($user)) {
            throw FashionTryOnProviderException::invalidInput('fashn_premium_disabled', [
                'provider' => FashionTryOnCapabilities::selectedProviderForUser($user),
                'stage' => 'validate_tier',
                'tier' => $tier,
            ]);
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

        $personImage = $this->resolveRequestAsset($project, $user, $options, 'person_image_asset_id', 'person_image');
        $productImage = $this->resolveRequestAsset($project, $user, $options, 'product_image_asset_id', 'product_image');
        $provider = $this->providers->selectedProviderForUser($user);
        $creditCost = $this->creditCost($tier, is_string($options['quality_preset'] ?? null) ? $options['quality_preset'] : null);

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_fashion_try_on',
            creditCost: $creditCost,
            creditReason: $this->creditReason($tier, $provider),
            dispatch: fn (int $generationId) => GenerateFashionTryOnJob::dispatch($generationId),
            inputPayload: $this->inputPayload($project, $packageKey, $personSource, $tier, $provider, $personImage, $productImage, $options),
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

        if ($value !== null && ! in_array($value, FashionTryOnCapabilities::ACTIVE_PACKAGES, true)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'validate_package',
                'field' => 'package_key',
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

    private function creditCost(string $tier, ?string $preset): int
    {
        $toolCost = $this->tools->creditCost('fashion_try_on');

        if ($tier === FashionTryOnCapabilities::TIER_PREMIUM) {
            $presetCost = $preset !== null ? config('fashion_try_on.credit_costs.premium_presets.'.$preset) : null;
            $premiumCost = $presetCost ?? config('fashion_try_on.credit_costs.premium');

            return is_numeric($premiumCost) ? max(0, (int) $premiumCost) : $toolCost;
        }

        $standardCost = config('fashion_try_on.credit_costs.standard');

        return is_numeric($standardCost) ? max(0, (int) $standardCost) : $toolCost;
    }

    private function creditReason(string $tier, string $provider): string
    {
        return $provider === 'fashn'
            ? 'AI Fashion Try-On '.Str::title($tier).' generation'
            : 'Mock AI Fashion Try-On '.Str::title($tier).' preparation';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function inputPayload(Project $project, string $packageKey, string $personSource, string $tier, string $provider, MediaAsset $personImage, MediaAsset $productImage, array $options): array
    {
        $projectInput = is_array($project->input_payload) ? $project->input_payload : [];
        $category = is_string($options['category'] ?? null) ? $options['category'] : 'auto';

        return array_filter(array_merge($projectInput, [
            'source_workflow' => 'ai_fashion_try_on',
            'origin_workflow' => 'ai_fashion_try_on',
            'origin_label' => 'AI Fashion Try-On',
            'provider' => $provider,
            'package_key' => $packageKey,
            'person_source' => $personSource,
            'tier' => $tier,
            'person_image_asset_id' => $personImage->getKey(),
            'product_image_asset_id' => $productImage->getKey(),
            'category' => in_array($category, FashionTryOnCapabilities::STANDARD_CATEGORIES, true) ? $category : 'auto',
            'garment_photo_type' => is_string($options['garment_photo_type'] ?? null) ? $options['garment_photo_type'] : null,
            'standard_mode' => is_string($options['standard_mode'] ?? null) ? $options['standard_mode'] : null,
            'quality_preset' => is_string($options['quality_preset'] ?? null) ? $options['quality_preset'] : null,
            'prompt' => $tier === FashionTryOnCapabilities::TIER_PREMIUM && is_string($options['prompt'] ?? null)
                ? Str::limit(trim($options['prompt']), 700, '')
                : null,
            'person_image_permission_confirmed' => true,
            'consent_confirmed_at' => now()->toISOString(),
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
        ]), fn (mixed $value): bool => $value !== null && $value !== '');
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
