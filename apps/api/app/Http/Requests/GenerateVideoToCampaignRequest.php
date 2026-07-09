<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateVideoToCampaignRequest extends FormRequest
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
            'source_video_asset_id' => ['sometimes', 'nullable', 'integer'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'target_audience' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'campaign_context' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'language' => ['sometimes', 'nullable', 'string', Rule::in(['english', 'indonesian', 'spanish'])],
            'tone' => ['sometimes', 'nullable', 'string', Rule::in(['natural', 'funny', 'premium', 'problem_solution', 'review'])],
            'platform' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
