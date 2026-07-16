<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Support\MotionAiVideoCapabilities;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateMotionAiVideoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $campaignGenerationId = $this->input('campaign_generation_id');
        $productImageAssetId = $this->input('product_image_asset_id');
        $sourceMode = MotionAiVideoCapabilities::normalizeSourceMode(
            $this->input('source_mode'),
            $campaignGenerationId,
            $productImageAssetId,
        );

        $scriptSource = $this->input('script_source');
        if (! $scriptSource) {
            $scriptSource = $sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_CAMPAIGN_ASSISTED
                ? 'campaign_script'
                : 'project_brief';
        }

        $includeVoiceoverPlan = $this->has('use_voiceover')
            ? $this->boolean('use_voiceover')
            : ($this->has('include_voiceover_plan') ? $this->boolean('include_voiceover_plan') : true);
        $includeCaptionPlan = $this->has('use_captions')
            ? $this->boolean('use_captions')
            : ($this->has('include_caption_plan') ? $this->boolean('include_caption_plan') : true);

        $this->merge([
            'source_mode' => $sourceMode,
            'platform' => $this->input('platform') ?: 'tiktok',
            'target_duration_seconds' => $this->input('target_duration_seconds') ?: MotionAiVideoCapabilities::defaultDurationSeconds(),
            'script_source' => $scriptSource,
            'visual_style' => $this->input('visual_style') ?: 'realistic_product',
            'motion_style' => $this->input('motion_style') ?: 'slow_push_in',
            'aspect_ratio' => $this->input('aspect_ratio') ?: 'vertical_9_16',
            'include_voiceover_plan' => $includeVoiceoverPlan,
            'include_caption_plan' => $includeCaptionPlan,
            'use_voiceover' => $includeVoiceoverPlan,
            'use_captions' => $includeCaptionPlan,
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
            'source_mode' => ['sometimes', 'string', Rule::in(MotionAiVideoCapabilities::sourceModes())],
            'product_image_asset_id' => ['sometimes', 'nullable', 'integer'],
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
            'use_voiceover' => ['sometimes', 'boolean'],
            'use_captions' => ['sometimes', 'boolean'],
            'primary_cta' => ['sometimes', 'nullable', 'string', 'max:180'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sourceMode = MotionAiVideoCapabilities::normalizeSourceMode(
                $this->input('source_mode'),
                $this->input('campaign_generation_id'),
                $this->input('product_image_asset_id'),
            );

            if (in_array($sourceMode, [
                MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_CONTEXT,
                MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_FIRST_FRAME,
                MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL,
            ], true) && ! $this->filled('product_image_asset_id')) {
                $validator->errors()->add('product_image_asset_id', 'Choose a product image for this Motion AI source mode.');
            }

            if ($sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_CAMPAIGN_ASSISTED && ! $this->filled('campaign_generation_id')) {
                $validator->errors()->add('campaign_generation_id', 'Choose a completed campaign generation for campaign-assisted Motion AI Video.');
            }

            if ($sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_FIRST_FRAME
                && ! MotionAiVideoCapabilities::imageToVideoEnabledForUser($this->user())) {
                $validator->errors()->add('source_mode', 'First-frame image-to-video mode is not enabled for the current provider configuration.');
            }

            if ($sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL
                && ! MotionAiVideoCapabilities::imageReferencesEnabledForUser($this->user())) {
                $validator->errors()->add('source_mode', 'Product image visual reference mode is not enabled for the current provider configuration.');
            }

            if ($sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_TEXT_PROMPT && ! $this->hasTextPromptContext()) {
                $validator->errors()->add('creative_notes', 'Add a short prompt or creative direction for text-prompt Motion AI Video.');
            }
        });
    }

    private function hasTextPromptContext(): bool
    {
        foreach (['manual_script', 'creative_notes', 'scene_description', 'background_prompt', 'primary_cta'] as $key) {
            if (is_string($this->input($key)) && trim((string) $this->input($key)) !== '') {
                return true;
            }
        }

        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        $input = is_array($project->input_payload) ? $project->input_payload : [];

        foreach (['product_name', 'product_description', 'content_idea', 'target_audience'] as $key) {
            if (is_string($input[$key] ?? null) && trim((string) $input[$key]) !== '') {
                return true;
            }
        }

        return is_string($project->title) && trim($project->title) !== '';
    }
}
