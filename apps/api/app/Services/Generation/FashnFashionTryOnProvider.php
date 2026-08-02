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
use RuntimeException;
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
        $requestedOutputCount = $this->requestedOutputCount($input, $packageKey);
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
        $context = $this->context($input, $project, $generationId, $tier, $personImage, $productImage, $requestedOutputCount);
        $submit = null;

        if ($predictionId === null) {
            $payload = $this->providerPayload($tier, $input, $personImage, $productImage);
            $submit = $this->client->submit($payload, $context + [
                'stage' => 'submit',
                'provider_model' => $payload['model_name'],
                'payload_shape' => $tier === FashionTryOnCapabilities::TIER_PREMIUM ? 'premium_product_image' : 'standard_garment_image',
            ]);
            $predictionId = (string) $submit['prediction_id'];
            $this->persistPrediction($generationId, $predictionId, $payload['model_name'], $tier, $submit, $requestedOutputCount);
        }

        $completion = $this->client->waitForCompletion($predictionId, $context + [
            'stage' => 'poll',
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

        $expectedProviderCreditsPerOutput = FashionTryOnCapabilities::expectedProviderCreditsPerOutput($tier, $premiumPreset['key'] ?? null);
        $expectedProviderCreditsTotal = FashionTryOnCapabilities::expectedProviderCreditsTotal($tier, $premiumPreset['key'] ?? null, $requestedOutputCount);
        $providerLatencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $stored = $this->storeCompletedOutputs(
            user: $user,
            project: $project,
            generationId: $generationId,
            input: $input,
            tier: $tier,
            packageKey: $packageKey,
            personSource: $personSource,
            premiumPreset: $premiumPreset,
            personImage: $personImage,
            productImage: $productImage,
            providerModel: $providerModel,
            predictionId: $predictionId,
            expectedProviderCreditsPerOutput: $expectedProviderCreditsPerOutput,
            expectedProviderCreditsTotal: $expectedProviderCreditsTotal,
            actualProviderCredits: is_numeric($actualProviderCredits) ? $actualProviderCredits + 0 : null,
            providerCreditsSource: is_string($providerCreditsSource) ? $providerCreditsSource : 'unavailable',
            requestedOutputCount: $requestedOutputCount,
            output: $completion['output'] ?? null,
            context: $context + ['stage' => 'decode_output', 'provider_prediction_id' => $predictionId],
        );

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
            'requested_output_count' => $requestedOutputCount,
            'delivered_output_count' => count($stored['assets']),
            'provider_observed_output_count' => $stored['observed_output_count'],
        ]);

        return $this->outputPayload(
            input: $input,
            tier: $tier,
            providerModel: $providerModel,
            predictionId: $predictionId,
            actualProviderCredits: is_numeric($actualProviderCredits) ? $actualProviderCredits + 0 : null,
            expectedProviderCreditsPerOutput: $expectedProviderCreditsPerOutput,
            expectedProviderCreditsTotal: $expectedProviderCreditsTotal,
            providerCreditsSource: is_string($providerCreditsSource) ? $providerCreditsSource : 'unavailable',
            providerLatencyMs: $providerLatencyMs,
            packageKey: $packageKey,
            personSource: $personSource,
            personImage: $personImage,
            productImage: $productImage,
            stored: $stored,
            requestedOutputCount: $requestedOutputCount,
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
    private function context(array $input, Project $project, int $generationId, string $tier, MediaAsset $personImage, MediaAsset $productImage, int $requestedOutputCount): array
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
            'requested_output_count' => $requestedOutputCount,
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
    private function persistPrediction(int $generationId, string $predictionId, string $model, string $tier, ?array $submit, int $requestedOutputCount): void
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
        $input['requested_output_count'] = $requestedOutputCount;
        $input['provider_submit_status'] = $submit['status'] ?? 'starting';
        $input['provider_submit_latency_ms'] = $submit['submit_latency_ms'] ?? null;

        $generation->forceFill(['input_payload' => $input])->save();
    }

    /**
     * @param  array<string, mixed>|null  $premiumPreset
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     * @return array{assets: list<MediaAsset>, observed_output_count: int, invalid_output_count: int, invalid_output_indexes: list<int>, total_decoded_bytes: int}
     */
    private function storeCompletedOutputs(
        User $user,
        Project $project,
        int $generationId,
        array $input,
        string $tier,
        string $packageKey,
        string $personSource,
        ?array $premiumPreset,
        MediaAsset $personImage,
        MediaAsset $productImage,
        string $providerModel,
        string $predictionId,
        int $expectedProviderCreditsPerOutput,
        int $expectedProviderCreditsTotal,
        int|float|null $actualProviderCredits,
        string $providerCreditsSource,
        int $requestedOutputCount,
        mixed $output,
        array $context,
    ): array {
        $values = $this->outputValues($output);
        $observedOutputCount = $this->observedOutputCount($output);
        $maxPerOutputBytes = FashionTryOnCapabilities::maxOutputBytesPerImage();
        $maxTotalBytes = FashionTryOnCapabilities::maxOutputBytesTotal();
        $assets = [];
        $invalidIndexes = [];
        $totalBytes = 0;

        foreach (array_slice($values, 0, $requestedOutputCount, true) as $outputIndex => $value) {
            try {
                [$imageBinary, $mimeType] = $this->imageBinary($value, $context + ['output_index' => $outputIndex]);
                $sizeBytes = strlen($imageBinary);

                if ($sizeBytes > $maxPerOutputBytes) {
                    throw new RuntimeException('Generated image exceeded per-output byte limit.');
                }

                if ($totalBytes + $sizeBytes > $maxTotalBytes) {
                    throw new RuntimeException('Generated images exceeded total byte limit.');
                }

                $asset = $this->images->storeOrReuse(
                    user: $user,
                    project: $project,
                    generationId: $generationId,
                    outputIndex: (int) $outputIndex,
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
                        'provider_seed' => FashionTryOnCapabilities::normalizeProviderSeed($input['provider_seed'] ?? null),
                        'expected_provider_credits_per_output' => $expectedProviderCreditsPerOutput,
                        'expected_provider_credits_total' => $expectedProviderCreditsTotal,
                        'expected_provider_credits' => $expectedProviderCreditsTotal,
                        'actual_provider_credits' => $actualProviderCredits,
                        'provider_credits_source' => $providerCreditsSource,
                        'quality_preset' => $this->presetLabel($tier, $input),
                        'person_image_asset_id' => $personImage->getKey(),
                        'product_image_asset_id' => $productImage->getKey(),
                        'output_count' => $requestedOutputCount,
                        'requested_output_count' => $requestedOutputCount,
                        'provider_observed_output_count' => $observedOutputCount,
                        'observed_provider_output_count' => $observedOutputCount,
                    ],
                );

                $assets[] = $asset->refresh();
                $totalBytes += $sizeBytes;
                unset($imageBinary);
            } catch (Throwable $exception) {
                $invalidIndexes[] = (int) $outputIndex;
                Log::warning('FASHN Fashion Try-On output was skipped safely.', $context + [
                    'stage' => 'store_output',
                    'output_index' => (int) $outputIndex,
                    'safe_error_category' => 'invalid_or_oversized_output',
                    'exception_class' => $exception::class,
                ]);
            }
        }

        if ($assets === []) {
            throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', $context + [
                'response_format' => 'no_valid_outputs',
                'provider_observed_output_count' => $observedOutputCount,
                'invalid_output_count' => count($invalidIndexes),
            ]);
        }

        $deliveredOutputCount = count($assets);
        $missingOutputCount = max(0, $requestedOutputCount - $deliveredOutputCount);

        $this->images->mergeMetadata($assets, [
            'delivered_output_count' => $deliveredOutputCount,
            'missing_output_count' => $missingOutputCount,
            'partial_output' => $missingOutputCount > 0,
            'provider_returned_extra_outputs' => $observedOutputCount > $requestedOutputCount,
            'invalid_output_ranges_discarded' => count($invalidIndexes),
        ]);

        return [
            'assets' => array_map(fn (MediaAsset $asset): MediaAsset => $asset->refresh(), $assets),
            'observed_output_count' => $observedOutputCount,
            'invalid_output_count' => count($invalidIndexes),
            'invalid_output_indexes' => $invalidIndexes,
            'total_decoded_bytes' => $totalBytes,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string}
     */
    private function imageBinary(mixed $value, array $context): array
    {
        if (! is_string($value) || trim($value) === '') {
            throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', $context + [
                'response_format' => 'missing_output',
            ]);
        }

        $value = trim($value);

        if (preg_match('/^data:(image\/(?:png|jpeg|webp));base64,([A-Za-z0-9+\/=\r\n]+)$/', $value, $matches) === 1) {
            $decoded = base64_decode(preg_replace('/\s+/', '', $matches[2]) ?? '', true);

            if (is_string($decoded) && $decoded !== '') {
                $mimeType = $this->detectMimeType($decoded);

                if ($mimeType !== $matches[1]) {
                    throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', $context + [
                        'response_format' => 'mime_mismatch',
                    ]);
                }

                return [$decoded, $mimeType];
            }
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $binary = $this->client->download($value, $context + ['stage' => 'download_output']);
            $mimeType = $this->detectMimeType($binary);

            return [$binary, $mimeType];
        }

        throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', $context + [
            'response_format' => 'invalid_image_output',
        ]);
    }

    /** @return array<int, mixed> */
    private function outputValues(mixed $output): array
    {
        if (is_string($output)) {
            return [0 => $output];
        }

        if (! is_array($output)) {
            return [];
        }

        foreach (['image', 'url', 'uri', 'base64', 'data'] as $key) {
            if (isset($output[$key])) {
                return [0 => $output[$key]];
            }
        }

        $values = [];

        foreach (array_values($output) as $index => $item) {
            $values[$index] = $this->outputValue($item);
        }

        return $values;
    }

    private function outputValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach (['image', 'url', 'uri', 'base64', 'data'] as $key) {
            if (isset($value[$key])) {
                return $value[$key];
            }
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
        $prefix = mb_substr(ltrim($binary), 0, 120);

        if (str_starts_with(strtolower($prefix), '<!doctype') || str_starts_with(strtolower($prefix), '<html')) {
            throw FashionTryOnProviderException::invalidResponse('fashn_output_invalid', [
                'provider' => 'fashn',
                'stage' => 'detect_output_mime',
                'response_format' => 'html_output',
            ]);
        }

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
    private function requestedOutputCount(array $input, string $packageKey): int
    {
        $value = $input['requested_output_count'] ?? null;
        $tier = FashionTryOnCapabilities::normalizeTier(is_string($input['tier'] ?? null) ? $input['tier'] : null);
        $premiumPreset = $tier === FashionTryOnCapabilities::TIER_PREMIUM
            ? FashionTryOnCapabilities::premiumPresetKeyFromInput($input)
            : null;
        $allowedCounts = FashionTryOnCapabilities::allowedOutputCounts($packageKey, $tier, $premiumPreset);

        return is_int($value) && in_array($value, $allowedCounts, true)
            ? $value
            : FashionTryOnCapabilities::defaultOutputCount($packageKey, $tier, $premiumPreset);
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
     * @param  array{assets: list<MediaAsset>, observed_output_count: int, invalid_output_count: int, invalid_output_indexes: list<int>, total_decoded_bytes: int}  $stored
     * @return array<string, mixed>
     */
    private function outputPayload(
        array $input,
        string $tier,
        string $providerModel,
        string $predictionId,
        int|float|null $actualProviderCredits,
        int $expectedProviderCreditsPerOutput,
        int $expectedProviderCreditsTotal,
        string $providerCreditsSource,
        int $providerLatencyMs,
        string $packageKey,
        string $personSource,
        MediaAsset $personImage,
        MediaAsset $productImage,
        array $stored,
        int $requestedOutputCount,
    ): array {
        $assets = $stored['assets'];
        usort($assets, fn (MediaAsset $a, MediaAsset $b): int => ((int) ($a->metadata['output_index'] ?? 0)) <=> ((int) ($b->metadata['output_index'] ?? 0)));
        $firstImage = $assets[0];
        $premiumPreset = $tier === FashionTryOnCapabilities::TIER_PREMIUM
            ? FashionTryOnCapabilities::premiumPreset($this->premiumPresetKey($input))
            : null;
        $estimatedCost = FashionTryOnCapabilities::estimatedProviderCostUsd($expectedProviderCreditsTotal);
        $actualCost = FashionTryOnCapabilities::estimatedProviderCostUsd($actualProviderCredits);
        $category = $tier === FashionTryOnCapabilities::TIER_STANDARD ? $this->payloads->standardCategory($input) : null;
        $qualityPreset = $this->presetLabel($tier, $input);
        $configuredNusaCreditCost = FashionTryOnCapabilities::configuredCreditCost($input['configured_nusa_credit_cost'] ?? $input['nusa_credit_cost'] ?? null);
        $perOutputCreditCost = FashionTryOnCapabilities::configuredCreditCost($input['per_output_credit_cost'] ?? null)
            ?? ($requestedOutputCount > 0 && $configuredNusaCreditCost !== null ? (int) floor($configuredNusaCreditCost / $requestedOutputCount) : null);
        $deliveredOutputCount = count($assets);
        $missingOutputCount = max(0, $requestedOutputCount - $deliveredOutputCount);
        $partialOutput = $missingOutputCount > 0;
        $refundAmount = $partialOutput && $perOutputCreditCost !== null ? $perOutputCreditCost * $missingOutputCount : 0;
        $netCreditCost = $configuredNusaCreditCost !== null ? max(0, $configuredNusaCreditCost - $refundAmount) : null;
        $renderedAssets = array_map(fn (MediaAsset $asset): array => $this->renderedAsset($asset), $assets);
        $finalImageAssetIds = array_map(fn (MediaAsset $asset): int => (int) $asset->getKey(), $assets);
        $selectedImageAssetId = $packageKey === FashionTryOnCapabilities::PACKAGE_VARIATIONS ? null : (int) $firstImage->getKey();
        $selectedOutputIndex = $packageKey === FashionTryOnCapabilities::PACKAGE_VARIATIONS ? null : (int) ($firstImage->metadata['output_index'] ?? 0);

        $payload = array_filter([
            'try_on_title' => $packageKey === FashionTryOnCapabilities::PACKAGE_VARIATIONS ? 'AI Fashion Try-On variations' : 'AI Fashion Try-On result',
            'concept_summary' => $packageKey === FashionTryOnCapabilities::PACKAGE_VARIATIONS
                ? 'Real AI fashion try-on variations were generated and stored as private project assets.'
                : 'A real AI fashion try-on image was generated and stored as a private project asset.',
            'package_key' => $packageKey,
            'person_source' => $personSource,
            'tier' => $tier,
            'quality_preset' => $qualityPreset,
            'premium_preset' => $premiumPreset['key'] ?? null,
            'category' => $category,
            'nusa_credit_cost' => $configuredNusaCreditCost,
            'configured_nusa_credit_cost' => $configuredNusaCreditCost,
            'total_nusa_credit_cost' => $configuredNusaCreditCost,
            'per_output_credit_cost' => $perOutputCreditCost,
            'requested_output_count' => $requestedOutputCount,
            'maximum_allowed_outputs' => FashionTryOnCapabilities::maximumOutputCountForSelection($tier, $premiumPreset['key'] ?? null),
            'allowed_output_counts' => FashionTryOnCapabilities::allowedOutputCounts($packageKey, $tier, $premiumPreset['key'] ?? null),
            'provider_observed_output_count' => $stored['observed_output_count'],
            'delivered_output_count' => $deliveredOutputCount,
            'missing_output_count' => $missingOutputCount,
            'partial_output' => $partialOutput,
            'refunded_credit_amount' => $refundAmount,
            'net_credit_cost' => $netCreditCost,
            'selected_image_asset_id' => $selectedImageAssetId,
            'selected_output_index' => $selectedOutputIndex,
            'selected_person_image' => $this->assetSummary($personImage),
            'selected_product_image' => $this->assetSummary($productImage),
            'generated_image' => [
                'status' => 'rendered',
                'message' => $partialOutput ? 'Some variations could not be created, but valid results were saved privately.' : 'Real try-on image generated and stored privately.',
                'provider' => 'fashn',
            ],
            'final_image_asset_id' => $firstImage->getKey(),
            'final_image_stream_url' => route('media-assets.stream', ['mediaAsset' => $firstImage->getKey()], false),
            'final_image_download_url' => route('media-assets.download', ['mediaAsset' => $firstImage->getKey()], false),
            'final_image_asset_ids' => $finalImageAssetIds,
            'rendered_image_asset' => $this->renderedAsset($firstImage),
            'rendered_image_assets' => $renderedAssets,
            'generation_summary' => array_filter([
                'quality' => ucfirst($tier),
                'preset' => $qualityPreset,
                'premium_preset' => $premiumPreset['key'] ?? null,
                'provider_resolution' => $premiumPreset['resolution_label'] ?? null,
                'provider_generation_mode' => $premiumPreset['quality_label'] ?? null,
                'category' => $category,
                'output_format' => $firstImage->mime_type,
                'output_count' => $deliveredOutputCount,
                'requested_output_count' => $requestedOutputCount,
                'maximum_allowed_outputs' => FashionTryOnCapabilities::maximumOutputCountForSelection($tier, $premiumPreset['key'] ?? null),
                'allowed_output_counts' => FashionTryOnCapabilities::allowedOutputCounts($packageKey, $tier, $premiumPreset['key'] ?? null),
                'provider_observed_output_count' => $stored['observed_output_count'],
                'delivered_output_count' => $deliveredOutputCount,
                'missing_output_count' => $missingOutputCount,
                'partial_output' => $partialOutput,
                'nusa_credit_cost' => $configuredNusaCreditCost,
                'configured_nusa_credit_cost' => $configuredNusaCreditCost,
                'total_nusa_credit_cost' => $configuredNusaCreditCost,
                'per_output_credit_cost' => $perOutputCreditCost,
                'refunded_credit_amount' => $refundAmount,
                'net_credit_cost' => $netCreditCost,
                'package_key' => $packageKey,
                'person_source' => $personSource,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'credit_refund' => $partialOutput ? [
                'type' => 'partial_output',
                'amount' => $refundAmount,
                'reason' => 'Partial refund for missing Fashion Try-On variations',
                'metadata' => [
                    'requested_output_count' => $requestedOutputCount,
                    'delivered_output_count' => $deliveredOutputCount,
                    'missing_output_count' => $missingOutputCount,
                    'per_output_credit_cost' => $perOutputCreditCost,
                    'requested_total_cost' => $configuredNusaCreditCost,
                    'refunded_credit_amount' => $refundAmount,
                    'net_credit_cost' => $netCreditCost,
                    'refund_scope' => 'fashion_try_on_partial_output',
                ],
            ] : null,
            'provider_metadata' => array_filter([
                'provider' => 'fashn',
                'provider_model' => $providerModel,
                'provider_prediction_id' => $predictionId,
                'provider_status' => 'completed',
                'provider_credits_used' => $actualProviderCredits,
                'actual_provider_credits' => $actualProviderCredits,
                'expected_provider_credits' => $expectedProviderCreditsTotal,
                'expected_provider_credits_per_output' => $expectedProviderCreditsPerOutput,
                'expected_provider_credits_total' => $expectedProviderCreditsTotal,
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
                'provider_seed' => FashionTryOnCapabilities::normalizeProviderSeed($input['provider_seed'] ?? null),
                'output_count' => $deliveredOutputCount,
                'requested_output_count' => $requestedOutputCount,
                'maximum_allowed_outputs' => FashionTryOnCapabilities::maximumOutputCountForSelection($tier, $premiumPreset['key'] ?? null),
                'allowed_output_counts' => FashionTryOnCapabilities::allowedOutputCounts($packageKey, $tier, $premiumPreset['key'] ?? null),
                'provider_observed_output_count' => $stored['observed_output_count'],
                'observed_provider_output_count' => $stored['observed_output_count'],
                'delivered_output_count' => $deliveredOutputCount,
                'missing_output_count' => $missingOutputCount,
                'partial_output' => $partialOutput,
                'invalid_output_count' => $stored['invalid_output_count'],
                'invalid_output_indexes' => $stored['invalid_output_indexes'],
                'total_decoded_output_bytes' => $stored['total_decoded_bytes'],
                'max_decoded_bytes_per_output' => FashionTryOnCapabilities::maxOutputBytesPerImage(),
                'max_decoded_bytes_total' => FashionTryOnCapabilities::maxOutputBytesTotal(),
                'provider_returned_extra_outputs' => $stored['observed_output_count'] > $requestedOutputCount,
                'provider_output_warning' => $this->providerOutputWarning($requestedOutputCount, $deliveredOutputCount, $stored['observed_output_count']),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'suggested_next_actions' => $packageKey === FashionTryOnCapabilities::PACKAGE_VARIATIONS
                ? ['Choose your favorite result', 'Download individual images', 'Generate another set', 'Return to project']
                : ['Animate with Motion AI Video', 'Download image', 'Return to project'],
            'compliance_notes' => [
                'Use only person photos and product images you have permission to process.',
                'Review the output for fit, identity and product accuracy before publishing.',
                'Avoid implying exact sizing, body changes or guaranteed results.',
            ],
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($packageKey === FashionTryOnCapabilities::PACKAGE_VARIATIONS) {
            $payload['selected_image_asset_id'] = null;
            $payload['selected_output_index'] = null;
        }

        return $payload;
    }

    private function providerOutputWarning(int $requested, int $delivered, int $observed): ?string
    {
        if ($delivered < $requested) {
            return 'provider_returned_fewer_valid_outputs_than_requested';
        }

        if ($observed > $requested) {
            return 'provider_returned_more_outputs_than_requested_extra_outputs_ignored';
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function renderedAsset(MediaAsset $asset): array
    {
        return [
            'id' => $asset->getKey(),
            'stream_url' => route('media-assets.stream', ['mediaAsset' => $asset->getKey()], false),
            'download_url' => route('media-assets.download', ['mediaAsset' => $asset->getKey()], false),
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
            'output_index' => is_numeric($asset->metadata['output_index'] ?? null) ? (int) $asset->metadata['output_index'] : null,
        ];
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
