<?php

namespace App\Services\Generation;

use App\Contracts\FashionTryOnProvider;
use App\Exceptions\FashionTryOn\FashionTryOnProviderException;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Support\FashionTryOnCapabilities;
use Illuminate\Support\Facades\Log;
use Throwable;

class FashnFashionTryOnProvider implements FashionTryOnProvider
{
    public function __construct(
        private readonly FashnFashionTryOnPayloadBuilder $payloads,
        private readonly FashnFashionTryOnClient $client,
        private readonly FashionTryOnAssetResolver $assets,
        private readonly GeneratedFashionTryOnImageMediaService $images,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $user = User::query()->findOrFail((int) ($input['user_id'] ?? 0));

        return $this->generateForUser($input, $user);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generateForUser(array $input, User $user): array
    {
        $startedAt = microtime(true);
        $generationId = (int) ($input['generation_id'] ?? 0);
        $project = $this->project($input, $user);
        $tier = FashionTryOnCapabilities::normalizeTier(is_string($input['tier'] ?? null) ? $input['tier'] : null);
        $packageKey = FashionTryOnCapabilities::normalizePackage(is_string($input['package_key'] ?? null) ? $input['package_key'] : null);
        $personSource = FashionTryOnCapabilities::normalizePersonSource(is_string($input['person_source'] ?? null) ? $input['person_source'] : null);
        $premiumPreset = $tier === FashionTryOnCapabilities::TIER_PREMIUM
            ? FashionTryOnCapabilities::premiumPreset($this->premiumPresetKey($input))
            : null;

        if ($tier === FashionTryOnCapabilities::TIER_PREMIUM && ! FashionTryOnCapabilities::premiumRealAvailableForUser($user)) {
            throw FashionTryOnProviderException::invalidInput('fashn_premium_disabled', [
                'provider' => 'fashn',
                'stage' => 'validate_tier',
                'tier' => $tier,
            ]);
        }

        $personImage = $this->assets->resolveProjectImage($user, $project, (int) ($input['person_image_asset_id'] ?? 0), 'person_image');
        $productImage = $this->assets->resolveProjectImage($user, $project, (int) ($input['product_image_asset_id'] ?? 0), 'product_image');
        $predictionId = $this->existingPredictionId($input);
        $context = $this->context($input, $project, $generationId, $tier, $personImage, $productImage);
        $submit = null;

        if ($predictionId === null) {
            $payload = $this->providerPayload($tier, $input, $personImage, $productImage);
            $submit = $this->client->submit($payload, $context + [
                'stage' => 'submit',
                'provider_model' => $payload['model_name'],
                'payload_shape' => $tier === FashionTryOnCapabilities::TIER_PREMIUM ? 'premium_product_image' : 'standard_garment_image',
            ]);
            $predictionId = (string) $submit['prediction_id'];
            $this->persistPrediction($generationId, $predictionId, $payload['model_name'], $tier, $submit);
        }

        $completion = $this->client->waitForCompletion($predictionId, $context + [
            'stage' => 'poll',
            'provider_prediction_id' => $predictionId,
        ]);
        [$imageBinary, $mimeType, $observedOutputCount] = $this->imageBinary($completion['output'], $context + [
            'stage' => 'decode_output',
            'provider_prediction_id' => $predictionId,
        ]);
        $providerModel = $this->modelForTier($tier);
        $actualProviderCredits = $completion['provider_credits_used'] ?? null;
        if ($actualProviderCredits === null && $submit !== null) {
            $actualProviderCredits = $submit['provider_credits_used'] ?? null;
        }
        $providerCreditsSource = $completion['provider_credits_source'] ?? 'unavailable';
        if ($providerCreditsSource === 'unavailable' && $submit !== null) {
            $providerCreditsSource = $submit['provider_credits_source'] ?? 'unavailable';
        }
        $expectedProviderCredits = is_array($premiumPreset)
            ? (int) $premiumPreset['expected_provider_credits']
            : null;
        $providerLatencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        try {
            $finalImage = $this->images->store(
                user: $user,
                project: $project,
                generationId: $generationId,
                imageBinary: $imageBinary,
                mimeType: $mimeType,
                metadata: [
                    'provider' => 'fashn',
                    'provider_model' => $providerModel,
                    'provider_prediction_id' => $predictionId,
                    'provider_status' => 'completed',
                    'package_key' => $packageKey,
                    'tier' => $tier,
                    'person_source' => $personSource,
                    'premium_preset' => $premiumPreset['key'] ?? null,
                    'provider_resolution' => $premiumPreset['resolution'] ?? null,
                    'provider_generation_mode' => $premiumPreset['generation_mode'] ?? null,
                    'provider_seed' => $tier === FashionTryOnCapabilities::TIER_PREMIUM ? FashionTryOnCapabilities::normalizeProviderSeed($input['provider_seed'] ?? null) : null,
                    'expected_provider_credits' => $expectedProviderCredits,
                    'actual_provider_credits' => is_numeric($actualProviderCredits) ? $actualProviderCredits + 0 : null,
                    'provider_credits_source' => is_string($providerCreditsSource) ? $providerCreditsSource : 'unavailable',
                    'quality_preset' => $this->presetLabel($tier, $input),
                    'person_image_asset_id' => $personImage->getKey(),
                    'product_image_asset_id' => $productImage->getKey(),
                    'output_index' => 0,
                    'output_count' => 1,
                    'observed_provider_output_count' => $observedOutputCount,
                    'provider_returned_extra_outputs' => $observedOutputCount > 1,
                ],
            );
        } catch (Throwable $exception) {
            throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', $context + [
                'stage' => 'store',
                'provider_prediction_id' => $predictionId,
            ], $exception);
        }

        Log::info('FASHN Fashion Try-On generation completed.', [
            'provider' => 'fashn',
            'workflow' => 'fashion_try_on',
            'generation_id' => $generationId ?: null,
            'project_id' => $project->getKey(),
            'package_key' => $packageKey,
            'person_source' => $personSource,
            'tier' => $tier,
            'premium_preset' => $premiumPreset['key'] ?? null,
            'provider_model' => $providerModel,
            'provider_prediction_id' => $predictionId,
            'final_image_asset_id' => $finalImage->getKey(),
            'observed_provider_output_count' => $observedOutputCount,
        ]);

        return $this->outputPayload(
            input: $input,
            tier: $tier,
            providerModel: $providerModel,
            predictionId: $predictionId,
            actualProviderCredits: is_numeric($actualProviderCredits) ? $actualProviderCredits + 0 : null,
            expectedProviderCredits: $expectedProviderCredits,
            providerCreditsSource: is_string($providerCreditsSource) ? $providerCreditsSource : 'unavailable',
            providerLatencyMs: $providerLatencyMs,
            packageKey: $packageKey,
            personSource: $personSource,
            personImage: $personImage,
            productImage: $productImage,
            finalImage: $finalImage,
            mimeType: $mimeType,
            observedOutputCount: $observedOutputCount,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function providerPayload(string $tier, array $input, MediaAsset $personImage, MediaAsset $productImage): array
    {
        $personDataUri = $this->assets->dataUri($personImage, 'person_image');
        $productDataUri = $this->assets->dataUri($productImage, 'product_image');

        if ($tier === FashionTryOnCapabilities::TIER_PREMIUM) {
            return $this->payloads->premium($input, $personDataUri, $productDataUri);
        }

        return $this->payloads->standard($input, $personDataUri, $productDataUri);
    }

    /** @param array<string, mixed> $input */
    private function project(array $input, User $user): Project
    {
        $project = Project::query()
            ->whereKey((int) ($input['project_id'] ?? 0))
            ->where('user_id', $user->getKey())
            ->first();

        if (! $project instanceof Project) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'provider' => 'fashn',
                'stage' => 'resolve_project',
            ]);
        }

        return $project;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function context(array $input, Project $project, int $generationId, string $tier, MediaAsset $personImage, MediaAsset $productImage): array
    {
        $premiumPreset = $tier === FashionTryOnCapabilities::TIER_PREMIUM
            ? FashionTryOnCapabilities::premiumPreset($this->premiumPresetKey($input))
            : null;

        return [
            'provider' => 'fashn',
            'workflow' => 'fashion_try_on',
            'generation_id' => $generationId ?: null,
            'project_id' => $project->getKey(),
            'package_key' => FashionTryOnCapabilities::normalizePackage(is_string($input['package_key'] ?? null) ? $input['package_key'] : null),
            'person_source' => FashionTryOnCapabilities::normalizePersonSource(is_string($input['person_source'] ?? null) ? $input['person_source'] : null),
            'tier' => $tier,
            'premium_preset' => $premiumPreset['key'] ?? null,
            'provider_resolution' => $premiumPreset['resolution'] ?? null,
            'provider_generation_mode' => $premiumPreset['generation_mode'] ?? null,
            'model' => $this->modelForTier($tier),
            'person_image_asset_id' => $personImage->getKey(),
            'product_image_asset_id' => $productImage->getKey(),
            'category' => $tier === FashionTryOnCapabilities::TIER_STANDARD ? ($input['category'] ?? null) : null,
        ];
    }

    /** @param array<string, mixed> $input */
    private function existingPredictionId(array $input): ?string
    {
        $value = $input['provider_prediction_id'] ?? data_get($input, 'provider_metadata.provider_prediction_id');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>|null  $submit
     */
    private function persistPrediction(int $generationId, string $predictionId, string $model, string $tier, ?array $submit): void
    {
        if ($generationId <= 0) {
            return;
        }

        $generation = Generation::query()->find($generationId);

        if (! $generation instanceof Generation) {
            return;
        }

        $input = is_array($generation->input_payload) ? $generation->input_payload : [];
        $input['provider_prediction_id'] = $predictionId;
        $input['provider_model'] = $model;
        $input['provider'] = 'fashn';
        $input['package_key'] = FashionTryOnCapabilities::normalizePackage(is_string($input['package_key'] ?? null) ? $input['package_key'] : null);
        $input['person_source'] = FashionTryOnCapabilities::normalizePersonSource(is_string($input['person_source'] ?? null) ? $input['person_source'] : null);
        $input['tier'] = $tier;
        $input['provider_submit_status'] = $submit['status'] ?? 'starting';
        $input['provider_submit_latency_ms'] = $submit['submit_latency_ms'] ?? null;

        $generation->forceFill([
            'input_payload' => $input,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: int}
     */
    private function imageBinary(mixed $output, array $context): array
    {
        $value = $this->firstOutputValue($output);
        $observedOutputCount = $this->observedOutputCount($output);

        if (! is_string($value) || trim($value) === '') {
            throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', $context + [
                'response_format' => 'missing_output',
            ]);
        }

        $value = trim($value);

        if (preg_match('/^data:(image\/(?:png|jpeg|webp));base64,([A-Za-z0-9+\/=\r\n]+)$/', $value, $matches) === 1) {
            $decoded = base64_decode(preg_replace('/\s+/', '', $matches[2]) ?? '', true);

            if (is_string($decoded) && $decoded !== '') {
                return [$decoded, $matches[1], $observedOutputCount];
            }
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $binary = $this->client->download($value, $context + ['stage' => 'download_output']);
            $mimeType = $this->detectMimeType($binary);

            return [$binary, $mimeType, $observedOutputCount];
        }

        throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', $context + [
            'response_format' => 'invalid_image_output',
        ]);
    }

    private function firstOutputValue(mixed $output): mixed
    {
        if (is_string($output)) {
            return $output;
        }

        if (! is_array($output)) {
            return null;
        }

        foreach (['image', 'url', 'uri', 'base64', 'data'] as $key) {
            if (isset($output[$key])) {
                return $output[$key];
            }
        }

        foreach ($output as $value) {
            return is_array($value) ? $this->firstOutputValue($value) : $value;
        }

        return null;
    }

    private function observedOutputCount(mixed $output): int
    {
        if (is_string($output)) {
            return 1;
        }

        if (! is_array($output)) {
            return 0;
        }

        foreach (['image', 'url', 'uri', 'base64', 'data'] as $key) {
            if (isset($output[$key])) {
                return 1;
            }
        }

        return count($output);
    }

    private function detectMimeType(string $binary): string
    {
        $detectedMime = function_exists('finfo_buffer')
            ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary)
            : null;

        if (in_array($detectedMime, FashionTryOnCapabilities::SUPPORTED_IMAGE_MIME_TYPES, true)) {
            return (string) $detectedMime;
        }

        throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', [
            'provider' => 'fashn',
            'stage' => 'detect_output_mime',
        ]);
    }

    private function modelForTier(string $tier): string
    {
        return $tier === FashionTryOnCapabilities::TIER_PREMIUM
            ? (string) config('fashion_try_on.fashn.premium_model', 'tryon-max')
            : (string) config('fashion_try_on.fashn.standard_model', 'tryon-v1.6');
    }

    /** @param array<string, mixed> $input */
    private function premiumPresetKey(array $input): string
    {
        return FashionTryOnCapabilities::premiumPresetKeyFromInput($input);
    }

    /** @param array<string, mixed> $input */
    private function presetLabel(string $tier, array $input): string
    {
        if ($tier === FashionTryOnCapabilities::TIER_PREMIUM) {
            $preset = FashionTryOnCapabilities::premiumPreset($this->premiumPresetKey($input));

            return is_string($preset['label'] ?? null) ? $preset['label'] : 'Premium';
        }

        return $this->payloads->standardMode($input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function outputPayload(
        array $input,
        string $tier,
        string $providerModel,
        string $predictionId,
        int|float|null $actualProviderCredits,
        ?int $expectedProviderCredits,
        string $providerCreditsSource,
        int $providerLatencyMs,
        string $packageKey,
        string $personSource,
        MediaAsset $personImage,
        MediaAsset $productImage,
        MediaAsset $finalImage,
        string $mimeType,
        int $observedOutputCount,
    ): array {
        $premiumPreset = $tier === FashionTryOnCapabilities::TIER_PREMIUM
            ? FashionTryOnCapabilities::premiumPreset($this->premiumPresetKey($input))
            : null;
        $estimatedCost = FashionTryOnCapabilities::estimatedProviderCostUsd($expectedProviderCredits ?? $actualProviderCredits);
        $actualCost = FashionTryOnCapabilities::estimatedProviderCostUsd($actualProviderCredits);
        $category = $tier === FashionTryOnCapabilities::TIER_STANDARD ? $this->payloads->standardCategory($input) : null;
        $qualityPreset = $this->presetLabel($tier, $input);
        $configuredNusaCreditCost = FashionTryOnCapabilities::configuredCreditCost($input['configured_nusa_credit_cost'] ?? $input['nusa_credit_cost'] ?? null);
        $perOutputCreditCost = FashionTryOnCapabilities::configuredCreditCost($input['per_output_credit_cost'] ?? $configuredNusaCreditCost);

        return array_filter([
            'try_on_title' => 'AI Fashion Try-On result',
            'concept_summary' => 'A real AI fashion try-on image was generated and stored as a private project asset.',
            'package_key' => $packageKey,
            'person_source' => $personSource,
            'tier' => $tier,
            'quality_preset' => $qualityPreset,
            'premium_preset' => $premiumPreset['key'] ?? null,
            'category' => $category,
            'nusa_credit_cost' => $configuredNusaCreditCost,
            'configured_nusa_credit_cost' => $configuredNusaCreditCost,
            'per_output_credit_cost' => $perOutputCreditCost,
            'output_count' => 1,
            'selected_person_image' => $this->assetSummary($personImage),
            'selected_product_image' => $this->assetSummary($productImage),
            'generated_image' => [
                'status' => 'rendered',
                'message' => 'Real try-on image generated and stored privately.',
                'provider' => 'fashn',
            ],
            'final_image_asset_id' => $finalImage->getKey(),
            'final_image_stream_url' => route('media-assets.stream', ['mediaAsset' => $finalImage->getKey()], false),
            'final_image_download_url' => route('media-assets.download', ['mediaAsset' => $finalImage->getKey()], false),
            'final_image_asset_ids' => [$finalImage->getKey()],
            'rendered_image_asset' => [
                'id' => $finalImage->getKey(),
                'stream_url' => route('media-assets.stream', ['mediaAsset' => $finalImage->getKey()], false),
                'download_url' => route('media-assets.download', ['mediaAsset' => $finalImage->getKey()], false),
                'mime_type' => $mimeType,
                'size_bytes' => $finalImage->size_bytes,
                'output_index' => 0,
            ],
            'rendered_image_assets' => [[
                'id' => $finalImage->getKey(),
                'stream_url' => route('media-assets.stream', ['mediaAsset' => $finalImage->getKey()], false),
                'download_url' => route('media-assets.download', ['mediaAsset' => $finalImage->getKey()], false),
                'mime_type' => $mimeType,
                'size_bytes' => $finalImage->size_bytes,
                'output_index' => 0,
            ]],
            'generation_summary' => array_filter([
                'quality' => ucfirst($tier),
                'preset' => $qualityPreset,
                'premium_preset' => $premiumPreset['key'] ?? null,
                'provider_resolution' => $premiumPreset['resolution_label'] ?? null,
                'provider_generation_mode' => $premiumPreset['quality_label'] ?? null,
                'category' => $category,
                'output_format' => $mimeType,
                'output_count' => 1,
                'nusa_credit_cost' => $configuredNusaCreditCost,
                'configured_nusa_credit_cost' => $configuredNusaCreditCost,
                'per_output_credit_cost' => $perOutputCreditCost,
                'package_key' => $packageKey,
                'person_source' => $personSource,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'provider_metadata' => array_filter([
                'provider' => 'fashn',
                'provider_model' => $providerModel,
                'provider_prediction_id' => $predictionId,
                'provider_status' => 'completed',
                'provider_credits_used' => $actualProviderCredits,
                'actual_provider_credits' => $actualProviderCredits,
                'expected_provider_credits' => $expectedProviderCredits,
                'provider_credits_source' => $providerCreditsSource,
                'estimated_provider_cost_usd' => $estimatedCost,
                'actual_provider_cost_usd' => $actualCost,
                'provider_latency_ms' => $providerLatencyMs,
                'package_key' => $packageKey,
                'person_source' => $personSource,
                'tier' => $tier,
                'quality_preset' => $qualityPreset,
                'premium_preset' => $premiumPreset['key'] ?? null,
                'provider_resolution' => $premiumPreset['resolution'] ?? null,
                'provider_generation_mode' => $premiumPreset['generation_mode'] ?? null,
                'provider_seed' => $tier === FashionTryOnCapabilities::TIER_PREMIUM ? FashionTryOnCapabilities::normalizeProviderSeed($input['provider_seed'] ?? null) : null,
                'output_count' => 1,
                'observed_provider_output_count' => $observedOutputCount,
                'provider_returned_extra_outputs' => $observedOutputCount > 1,
                'provider_output_warning' => $observedOutputCount > 1 ? 'provider_returned_more_outputs_than_requested_first_output_used' : null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'suggested_next_actions' => [
                'Animate with Motion AI Video',
                'Download image',
                'Return to project',
            ],
            'compliance_notes' => [
                'Use only person photos and product images you have permission to process.',
                'Review the output for fit, identity and product accuracy before publishing.',
                'Avoid implying exact sizing, body changes or guaranteed results.',
            ],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return array<string, mixed> */
    private function assetSummary(MediaAsset $asset): array
    {
        return [
            'id' => $asset->getKey(),
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
        ];
    }
}
