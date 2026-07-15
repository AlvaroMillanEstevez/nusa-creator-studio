<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateMotionAiVideoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $campaignGenerationId = $this->input('campaign_generation_id');

        $this->merge([
            'platform' => $this->input('platform') ?: 'tiktok',
            'target_duration_seconds' => $this->input('target_duration_seconds') ?: 8,
            'script_source' => $this->input('script_source') ?: ($campaignGenerationId ? 'campaign_script' : 'project_brief'),
            'visual_style' => $this->input('visual_style') ?: 'realistic_product',
            'motion_style' => $this->input('motion_style') ?: 'slow_push_in',
            'aspect_ratio' => $this->input('aspect_ratio') ?: 'vertical_9_16',
            'include_voiceover_plan' => $this->has('include_voiceover_plan')
                ? $this->boolean('include_voiceover_plan')
                : true,
            'include_caption_plan' => $this->has('include_caption_plan')
                ? $this->boolean('include_caption_plan')
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
            'product_image_asset_id' => ['required', 'integer'],
            'scenario_image_asset_id' => ['sometimes', 'nullable', 'integer'],
            'campaign_generation_id' => ['sometimes', 'nullable', 'integer'],
            'voiceover_generation_id' => ['sometimes', 'nullable', 'integer'],
            'platform' => ['sometimes', 'string', Rule::in([
                'tiktok',
                'instagram_reels',
                'youtube_shorts',
                'facebook_ads',
                'instagram_ads',
                'general_social',
            ])],
            'target_duration_seconds' => ['sometimes', 'integer', Rule::in([5, 8, 10, 15])],
            'script_source' => ['sometimes', 'string', Rule::in([
                'campaign_script',
                'manual_script',
                'project_brief',
            ])],
            'manual_script' => ['required_if:script_source,manual_script', 'nullable', 'string', 'max:4000'],
            'creative_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'visual_style' => ['sometimes', 'string', Rule::in([
                'realistic_product',
                'ugc_lifestyle',
                'premium_brand',
                'clean_studio',
                'cinematic',
                'social_ad',
            ])],
            'motion_style' => ['sometimes', 'string', Rule::in([
                'slow_push_in',
                'handheld_ugc',
                'product_orbit',
                'top_down_demo',
                'lifestyle_reveal',
                'before_after',
            ])],
            'camera_direction' => ['sometimes', 'nullable', 'string', 'max:600'],
            'scene_description' => ['sometimes', 'nullable', 'string', 'max:1200'],
            'background_prompt' => ['sometimes', 'nullable', 'string', 'max:1200'],
            'negative_prompt' => ['sometimes', 'nullable', 'string', 'max:1200'],
            'aspect_ratio' => ['sometimes', 'string', Rule::in([
                'vertical_9_16',
                'square_1_1',
                'portrait_4_5',
            ])],
            'include_voiceover_plan' => ['sometimes', 'boolean'],
            'include_caption_plan' => ['sometimes', 'boolean'],
            'primary_cta' => ['sometimes', 'nullable', 'string', 'max:180'],
        ];
    }
}
