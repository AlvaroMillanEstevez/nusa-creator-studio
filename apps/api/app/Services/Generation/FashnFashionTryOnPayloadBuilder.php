<?php

namespace App\Services\Generation;

use App\Exceptions\FashionTryOn\FashionTryOnProviderException;
use App\Support\FashionTryOnCapabilities;

class FashnFashionTryOnPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function standard(array $input, string $personImageDataUri, string $garmentImageDataUri): array
    {
        $inputs = [
            'model_image' => $personImageDataUri,
            'garment_image' => $garmentImageDataUri,
            'category' => $this->standardCategory($input),
            'garment_photo_type' => $this->garmentPhotoType($input),
            'mode' => $this->standardMode($input),
            'segmentation_free' => (bool) config('fashion_try_on.fashn.standard_segmentation_free', true),
            'moderation_level' => FashionTryOnCapabilities::safeModerationLevel(),
            'num_samples' => $this->requestedOutputCount($input),
            'output_format' => $this->outputFormat(),
            'return_base64' => $this->returnBase64(),
        ];

        $seed = FashionTryOnCapabilities::normalizeProviderSeed($input['provider_seed'] ?? null);

        if ($seed !== null) {
            $inputs['seed'] = $seed;
        }

        return [
            'model_name' => (string) config('fashion_try_on.fashn.standard_model', 'tryon-v1.6'),
            'inputs' => $inputs,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function premium(array $input, string $personImageDataUri, string $productImageDataUri): array
    {
        $preset = $this->premiumPreset($input);
        $seed = FashionTryOnCapabilities::normalizeProviderSeed($input['provider_seed'] ?? null);

        if ($seed === null) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'provider' => 'fashn',
                'stage' => 'build_premium_payload',
                'field' => 'provider_seed',
            ]);
        }

        $inputs = [
            'model_image' => $personImageDataUri,
            'product_image' => $productImageDataUri,
            'resolution' => $preset['resolution'],
            'generation_mode' => $preset['generation_mode'],
            'seed' => $seed,
            'num_images' => $this->requestedOutputCount($input),
            'output_format' => $this->outputFormat(),
            'return_base64' => true,
        ];

        $prompt = $this->premiumPrompt($input);

        if ($prompt !== null) {
            $inputs['prompt'] = $prompt;
        }

        return [
            'model_name' => (string) config('fashion_try_on.fashn.premium_model', 'tryon-max'),
            'inputs' => $inputs,
        ];
    }

    /** @param array<string, mixed> $input */
    public function requestedOutputCount(array $input): int
    {
        $package = FashionTryOnCapabilities::normalizePackage(is_string($input['package_key'] ?? null) ? $input['package_key'] : null);
        $tier = FashionTryOnCapabilities::normalizeTier(is_string($input['tier'] ?? null) ? $input['tier'] : null);
        $premiumPreset = $tier === FashionTryOnCapabilities::TIER_PREMIUM
            ? FashionTryOnCapabilities::premiumPresetKeyFromInput($input)
            : null;
        $value = $input['requested_output_count'] ?? null;
        $allowedCounts = FashionTryOnCapabilities::allowedOutputCounts($package, $tier, $premiumPreset);

        return is_int($value) && in_array($value, $allowedCounts, true)
            ? $value
            : FashionTryOnCapabilities::defaultOutputCount($package, $tier, $premiumPreset);
    }

    /** @param array<string, mixed> $input */
    public function standardCategory(array $input): string
    {
        $category = is_string($input['category'] ?? null)
            ? $input['category']
            : (string) config('fashion_try_on.fashn.standard_category', 'auto');

        return in_array($category, FashionTryOnCapabilities::STANDARD_CATEGORIES, true)
            ? $category
            : 'auto';
    }

    /** @param array<string, mixed> $input */
    public function garmentPhotoType(array $input): string
    {
        $type = is_string($input['garment_photo_type'] ?? null)
            ? $input['garment_photo_type']
            : (string) config('fashion_try_on.fashn.standard_garment_photo_type', 'auto');

        return in_array($type, FashionTryOnCapabilities::STANDARD_GARMENT_PHOTO_TYPES, true)
            ? $type
            : 'auto';
    }

    /** @param array<string, mixed> $input */
    public function standardMode(array $input): string
    {
        $mode = is_string($input['standard_mode'] ?? null)
            ? $input['standard_mode']
            : (string) config('fashion_try_on.fashn.standard_mode', 'balanced');

        return in_array($mode, FashionTryOnCapabilities::STANDARD_MODES, true)
            ? $mode
            : 'balanced';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function premiumPreset(array $input): array
    {
        return FashionTryOnCapabilities::premiumPreset(FashionTryOnCapabilities::premiumPresetKeyFromInput($input));
    }

    /** @param array<string, mixed> $input */
    public function premiumPrompt(array $input): ?string
    {
        return FashionTryOnCapabilities::sanitizePremiumPrompt($input['premium_prompt'] ?? $input['prompt'] ?? null);
    }

    private function outputFormat(): string
    {
        $format = strtolower((string) config('fashion_try_on.fashn.output_format', 'png'));
        $format = $format === 'jpg' ? 'jpeg' : $format;

        return in_array($format, ['png', 'jpeg'], true) ? $format : 'png';
    }

    private function returnBase64(): bool
    {
        return (bool) config('fashion_try_on.fashn.return_base64', true);
    }
}
