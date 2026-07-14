<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateAutoVideoAdRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'target_duration_seconds' => $this->input('target_duration_seconds') ?: 15,
            'tone' => $this->input('tone') ?: 'direct_response',
            'visual_style' => $this->input('visual_style') ?: 'clean_product',
            'hook_style' => $this->input('hook_style') ?: 'benefit_first',
            'include_voiceover_script' => $this->has('include_voiceover_script')
                ? $this->boolean('include_voiceover_script')
                : true,
            'include_caption_variants' => $this->has('include_caption_variants')
                ? $this->boolean('include_caption_variants')
                : true,
        ]);
    }

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
            'ad_format' => ['required', 'string', Rule::in(['vertical_9_16', 'square_1_1', 'portrait_4_5'])],
            'platform' => ['required', 'string', Rule::in(['facebook_ads', 'instagram_ads', 'tiktok_ads', 'reels_ads', 'general_paid_social'])],
            'ad_goal' => ['required', 'string', Rule::in(['sales', 'traffic', 'awareness', 'product_demo', 'lead_generation'])],
            'product_image_asset_id' => ['required_without:source_video_asset_id', 'nullable', 'integer'],
            'source_video_asset_id' => ['required_without:product_image_asset_id', 'nullable', 'integer'],
            'campaign_generation_id' => ['sometimes', 'nullable', 'integer'],
            'voiceover_generation_id' => ['sometimes', 'nullable', 'integer'],
            'target_duration_seconds' => ['sometimes', 'integer', Rule::in([10, 15, 20, 30])],
            'primary_cta' => ['sometimes', 'nullable', 'string', 'max:180'],
            'offer_text' => ['sometimes', 'nullable', 'string', 'max:240'],
            'landing_page_context' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'audience_override' => ['sometimes', 'nullable', 'string', 'max:240'],
            'tone' => ['sometimes', 'string', Rule::in(['clean', 'ugc', 'premium', 'energetic', 'direct_response'])],
            'visual_style' => ['sometimes', 'string', Rule::in(['clean_product', 'ugc_ad', 'bold_promo', 'premium_brand', 'problem_solution'])],
            'hook_style' => ['sometimes', 'string', Rule::in(['curiosity', 'pain_point', 'benefit_first', 'demo_first', 'offer_first'])],
            'include_voiceover_script' => ['sometimes', 'boolean'],
            'include_caption_variants' => ['sometimes', 'boolean'],
        ];
    }
}
