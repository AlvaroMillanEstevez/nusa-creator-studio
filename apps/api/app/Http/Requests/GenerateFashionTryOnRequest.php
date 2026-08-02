<?php

namespace App\Http\Requests;

use App\Support\FashionTryOnCapabilities;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateFashionTryOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'package_key' => ['nullable', 'string', Rule::in(FashionTryOnCapabilities::activePackages())],
            'person_source' => ['nullable', 'string', Rule::in(FashionTryOnCapabilities::ACTIVE_PERSON_SOURCES)],
            'tier' => ['nullable', 'string', Rule::in([
                FashionTryOnCapabilities::TIER_STANDARD,
                FashionTryOnCapabilities::TIER_PREMIUM,
            ])],
            'person_image_asset_id' => ['required', 'integer'],
            'product_image_asset_id' => ['required', 'integer'],
            'category' => ['nullable', 'string', Rule::in(FashionTryOnCapabilities::STANDARD_CATEGORIES)],
            'garment_photo_type' => ['nullable', 'string', Rule::in(FashionTryOnCapabilities::STANDARD_GARMENT_PHOTO_TYPES)],
            'standard_mode' => ['nullable', 'string', Rule::in(FashionTryOnCapabilities::STANDARD_MODES)],
            'premium_preset' => ['nullable', 'string', Rule::in(FashionTryOnCapabilities::premiumPresetKeys())],
            'premium_prompt' => ['nullable', 'string', 'max:'.FashionTryOnCapabilities::premiumPromptMaxCharacters()],
            'requested_output_count' => ['nullable', $this->strictOutputCountRule()],
            'person_image_permission_confirmed' => ['accepted'],
            'model_name' => ['prohibited'],
            'provider' => ['prohibited'],
            'resolution' => ['prohibited'],
            'generation_mode' => ['prohibited'],
            'expected_provider_credits' => ['prohibited'],
            'expected_provider_credits_per_output' => ['prohibited'],
            'expected_provider_credits_total' => ['prohibited'],
            'seed' => ['prohibited'],
            'provider_seed' => ['prohibited'],
            'num_samples' => ['prohibited'],
            'num_images' => ['prohibited'],
            'output_count' => ['prohibited'],
            'nusa_credit_cost' => ['prohibited'],
            'configured_nusa_credit_cost' => ['prohibited'],
            'total_nusa_credit_cost' => ['prohibited'],
            'credit_cost' => ['prohibited'],
            'credits_charged' => ['prohibited'],
            'total_nusa_cost' => ['prohibited'],
            'per_output_credit_cost' => ['prohibited'],
            'actual_provider_credits' => ['prohibited'],
            'estimated_provider_cost_usd' => ['prohibited'],
            'actual_provider_cost_usd' => ['prohibited'],
            'provider_credits_used' => ['prohibited'],
            'output_format' => ['prohibited'],
            'return_base64' => ['prohibited'],
            'inputs' => ['prohibited'],
            'provider_payload' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_image_permission_confirmed.accepted' => 'Please confirm you have permission to use this person photo for AI fashion try-on.',
            'package_key.in' => 'The selected Fashion Try-On package is not available right now.',
            'person_source.in' => 'Upload a person photo is the only available person source right now.',
            'premium_preset.in' => 'Choose one of the available Premium presets.',
            'model_name.prohibited' => 'Provider model names are controlled by Nusa.',
            'provider.prohibited' => 'Provider selection is controlled by Nusa.',
            'resolution.prohibited' => 'Premium resolution is controlled by the selected Nusa preset.',
            'generation_mode.prohibited' => 'Premium generation mode is controlled by the selected Nusa preset.',
            'expected_provider_credits.prohibited' => 'Provider credit estimates are controlled by Nusa.',
            'expected_provider_credits_per_output.prohibited' => 'Provider credit estimates are controlled by Nusa.',
            'expected_provider_credits_total.prohibited' => 'Provider credit estimates are controlled by Nusa.',
            'seed.prohibited' => 'Provider seed is created by Nusa for each generation.',
            'provider_seed.prohibited' => 'Provider seed is created by Nusa for each generation.',
            'num_samples.prohibited' => 'Output count is controlled by Nusa and cannot be submitted directly.',
            'num_images.prohibited' => 'Output count is controlled by Nusa and cannot be submitted directly.',
            'output_count.prohibited' => 'Output count is controlled by Nusa and cannot be submitted directly.',
            'nusa_credit_cost.prohibited' => 'Nusa credit pricing is controlled by the server.',
            'configured_nusa_credit_cost.prohibited' => 'Nusa credit pricing is controlled by the server.',
            'total_nusa_credit_cost.prohibited' => 'Nusa credit pricing is controlled by the server.',
            'credit_cost.prohibited' => 'Nusa credit pricing is controlled by the server.',
            'credits_charged.prohibited' => 'Credits charged are calculated by the server.',
            'total_nusa_cost.prohibited' => 'Nusa credit pricing is controlled by the server.',
            'per_output_credit_cost.prohibited' => 'Nusa credit pricing is controlled by the server.',
            'actual_provider_credits.prohibited' => 'Provider accounting is controlled by Nusa.',
            'estimated_provider_cost_usd.prohibited' => 'Provider accounting is controlled by Nusa.',
            'actual_provider_cost_usd.prohibited' => 'Provider accounting is controlled by Nusa.',
            'provider_credits_used.prohibited' => 'Provider accounting is controlled by Nusa.',
            'output_format.prohibited' => 'Output format is controlled by Nusa.',
            'return_base64.prohibited' => 'Provider delivery mode is controlled by Nusa.',
            'inputs.prohibited' => 'Raw provider inputs cannot be submitted directly.',
            'provider_payload.prohibited' => 'Raw provider payloads cannot be submitted directly.',
        ];
    }

    private function strictOutputCountRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_int($value)) {
                $fail('Output count must be submitted as a JSON integer controlled by Nusa.');

                return;
            }

            $package = is_string($this->input('package_key'))
                ? (string) $this->input('package_key')
                : FashionTryOnCapabilities::PACKAGE_SINGLE_RESULT;

            if ($package === FashionTryOnCapabilities::PACKAGE_VARIATIONS) {
                if (! FashionTryOnCapabilities::variationsEnabled()) {
                    $fail('Try-On Variations is not available right now.');

                    return;
                }

                $tier = FashionTryOnCapabilities::normalizeTier(is_string($this->input('tier')) ? (string) $this->input('tier') : null);
                $premiumPreset = $tier === FashionTryOnCapabilities::TIER_PREMIUM
                    ? FashionTryOnCapabilities::premiumPresetKeyFromInput($this->all())
                    : null;
                $allowedCounts = FashionTryOnCapabilities::allowedOutputCounts($package, $tier, $premiumPreset);

                if (! in_array($value, $allowedCounts, true)) {
                    $fail('Try-On Variations supports '.implode(', ', $allowedCounts).' outputs for the selected quality during beta.');
                }

                return;
            }

            if ($value !== FashionTryOnCapabilities::STANDARD_OUTPUT_COUNT) {
                $fail('Single Try-On supports exactly one output image.');
            }
        };
    }
}
