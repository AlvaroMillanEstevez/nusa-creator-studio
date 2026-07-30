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
            'quality_preset' => ['nullable', 'string', Rule::in(array_keys(FashionTryOnCapabilities::premiumPresets()))],
            'prompt' => ['nullable', 'string', 'max:700'],
            'person_image_permission_confirmed' => ['accepted'],
            'num_samples' => ['prohibited'],
            'num_images' => ['prohibited'],
            'output_count' => ['prohibited'],
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
            'num_samples.prohibited' => 'Output count is controlled by Nusa and cannot be submitted directly.',
            'num_images.prohibited' => 'Output count is controlled by Nusa and cannot be submitted directly.',
            'output_count.prohibited' => 'Output count is controlled by Nusa and cannot be submitted directly.',
        ];
    }
}
