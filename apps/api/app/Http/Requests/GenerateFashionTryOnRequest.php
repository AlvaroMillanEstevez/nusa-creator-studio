<?php

namespace App\Http\Requests;

use App\Support\FashionTryOnCapabilities;
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
            'package_key' => ['nullable', 'string', Rule::in(FashionTryOnCapabilities::ACTIVE_PACKAGES)],
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
            'person_image_permission_confirmed' => ['accepted'],
            'model_name' => ['prohibited'],
            'provider' => ['prohibited'],
            'resolution' => ['prohibited'],
            'generation_mode' => ['prohibited'],
            'expected_provider_credits' => ['prohibited'],
            'seed' => ['prohibited'],
            'provider_seed' => ['prohibited'],
            'num_samples' => ['prohibited'],
            'num_images' => ['prohibited'],
            'output_count' => ['prohibited'],
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
            'package_key.in' => 'Only Single Try-On is available right now.',
            'person_source.in' => 'Upload a person photo is the only available person source right now.',
            'premium_preset.in' => 'Choose one of the available Premium presets.',
            'model_name.prohibited' => 'Provider model names are controlled by Nusa.',
            'provider.prohibited' => 'Provider selection is controlled by Nusa.',
            'resolution.prohibited' => 'Premium resolution is controlled by the selected Nusa preset.',
            'generation_mode.prohibited' => 'Premium generation mode is controlled by the selected Nusa preset.',
            'expected_provider_credits.prohibited' => 'Provider credit estimates are controlled by Nusa.',
            'seed.prohibited' => 'Provider seed is created by Nusa for each generation.',
            'provider_seed.prohibited' => 'Provider seed is created by Nusa for each generation.',
            'num_samples.prohibited' => 'Output count is controlled by Nusa and cannot be submitted directly.',
            'num_images.prohibited' => 'Output count is controlled by Nusa and cannot be submitted directly.',
            'output_count.prohibited' => 'Output count is controlled by Nusa and cannot be submitted directly.',
            'output_format.prohibited' => 'Output format is controlled by Nusa.',
            'return_base64.prohibited' => 'Provider delivery mode is controlled by Nusa.',
            'inputs.prohibited' => 'Raw provider inputs cannot be submitted directly.',
            'provider_payload.prohibited' => 'Raw provider payloads cannot be submitted directly.',
        ];
    }
}
