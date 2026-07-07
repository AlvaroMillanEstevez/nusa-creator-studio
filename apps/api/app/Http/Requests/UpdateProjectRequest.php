<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    private const VOICEOVER_LANGUAGES = ['english', 'indonesian', 'spanish'];
    private const VOICEOVER_STYLES = ['natural_creator', 'energetic_seller', 'calm_explainer', 'premium_brand', 'friendly_storyteller'];
    private const VOICEOVER_TONES = ['natural', 'persuasive', 'playful', 'professional', 'emotional'];
    private const VOICEOVER_PACES = ['slow', 'medium', 'fast'];
    private const VOICEOVER_DURATIONS = [15, 30, 45, 60];
    private const VOICEOVER_PLATFORMS = ['tiktok', 'instagram_reels', 'youtube_shorts', 'generic_vertical_video'];
    private const FASHION_GARMENT_TYPES = ['shirt', 't_shirt', 'dress', 'jacket', 'pants', 'skirt', 'shoes', 'bag', 'accessory', 'other'];
    private const FASHION_STYLE_GOALS = ['product_showcase', 'ecommerce_preview', 'social_media_ad', 'creator_story', 'catalog_style', 'before_after'];
    private const FASHION_OUTPUT_STYLES = ['realistic', 'editorial', 'clean_ecommerce', 'lifestyle', 'premium_brand', 'streetwear'];
    private const FASHION_LANGUAGES = ['english', 'indonesian', 'spanish'];
    private const FASHION_PLATFORMS = ['tiktok', 'instagram_reels', 'shopee', 'tokopedia', 'lazada', 'generic_social', 'ecommerce'];
    private const MOTION_VIDEO_GOALS = ['product_showcase', 'affiliate_ad', 'hook_video', 'product_demo', 'lifestyle_scene', 'before_after', 'brand_story'];
    private const MOTION_STYLES = ['smooth_camera', 'dynamic_zoom', 'handheld_creator', 'cinematic_pan', 'product_spin', 'fast_cuts', 'slow_reveal'];
    private const MOTION_VISUAL_STYLES = ['realistic', 'premium_brand', 'ecommerce_clean', 'lifestyle', 'streetwear', 'cinematic', 'ugc_creator'];
    private const MOTION_DURATIONS = [5, 10, 15, 30];
    private const MOTION_ASPECT_RATIOS = ['vertical_9_16', 'square_1_1', 'horizontal_16_9'];
    private const MOTION_PLATFORMS = ['tiktok', 'instagram_reels', 'youtube_shorts', 'shopee', 'tokopedia', 'lazada', 'generic_social'];
    private const MOTION_LANGUAGES = ['english', 'indonesian', 'spanish'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', Rule::in(Project::TYPES)],
            'status' => ['sometimes', 'required', 'string', Rule::in(Project::STATUSES)],
            'input_payload' => ['sometimes', 'nullable', 'array'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator): null => $this->validateVoiceoverPayload($validator),
            fn (Validator $validator): null => $this->validateFashionTryOnPayload($validator),
            fn (Validator $validator): null => $this->validateMotionVideoPayload($validator),
        ];
    }

    private function validateVoiceoverPayload(Validator $validator): null
    {
        /** @var Project|null $project */
        $project = $this->route('project');
        $targetType = $this->input('type', $project?->type);

        if ($targetType !== 'ai_voiceover' || ! $this->has('input_payload')) {
            return null;
        }

        $payload = $this->input('input_payload');

        if ($payload === null) {
            return null;
        }

        if (! is_array($payload)) {
            $validator->errors()->add('input_payload', 'The input payload must be an object.');

            return null;
        }

        $this->validateStringFields($validator, $payload, [
            'title' => 255,
            'source_text' => 4000,
            'product_name' => 255,
            'product_description' => 4000,
            'target_audience' => 1000,
            'notes' => 2000,
        ]);

        $this->validateAllowedValue($validator, $payload, 'language', self::VOICEOVER_LANGUAGES);
        $this->validateAllowedValue($validator, $payload, 'voice_style', self::VOICEOVER_STYLES);
        $this->validateAllowedValue($validator, $payload, 'tone', self::VOICEOVER_TONES);
        $this->validateAllowedValue($validator, $payload, 'pace', self::VOICEOVER_PACES);
        $this->validateAllowedValue($validator, $payload, 'platform', self::VOICEOVER_PLATFORMS);

        if (array_key_exists('duration_seconds', $payload) && ! in_array((int) $payload['duration_seconds'], self::VOICEOVER_DURATIONS, true)) {
            $validator->errors()->add('input_payload.duration_seconds', 'The selected duration is invalid.');
        }

        return null;
    }

    private function validateFashionTryOnPayload(Validator $validator): null
    {
        /** @var Project|null $project */
        $project = $this->route('project');
        $targetType = $this->input('type', $project?->type);

        if ($targetType !== 'fashion_try_on' || ! $this->has('input_payload')) {
            return null;
        }

        $payload = $this->input('input_payload');

        if ($payload === null) {
            return null;
        }

        if (! is_array($payload)) {
            $validator->errors()->add('input_payload', 'The input payload must be an object.');

            return null;
        }

        $this->validateFashionFields($validator, $payload);

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function validateFashionFields(Validator $validator, array $payload): void
    {
        $this->validateStringFields($validator, $payload, [
            'title' => 255,
            'product_name' => 255,
            'product_description' => 4000,
            'target_audience' => 1000,
            'model_notes' => 2000,
            'garment_notes' => 2000,
            'background_preference' => 1000,
            'notes' => 2000,
        ]);

        $this->validateAllowedValue($validator, $payload, 'garment_type', self::FASHION_GARMENT_TYPES);
        $this->validateAllowedValue($validator, $payload, 'style_goal', self::FASHION_STYLE_GOALS);
        $this->validateAllowedValue($validator, $payload, 'output_style', self::FASHION_OUTPUT_STYLES);
        $this->validateAllowedValue($validator, $payload, 'language', self::FASHION_LANGUAGES);
        $this->validateAllowedValue($validator, $payload, 'platform', self::FASHION_PLATFORMS);
    }


    private function validateMotionVideoPayload(Validator $validator): null
    {
        /** @var Project|null $project */
        $project = $this->route('project');
        $targetType = $this->input('type', $project?->type);

        if ($targetType !== 'motion_ai_video' || ! $this->has('input_payload')) {
            return null;
        }

        $payload = $this->input('input_payload');

        if ($payload === null) {
            return null;
        }

        if (! is_array($payload)) {
            $validator->errors()->add('input_payload', 'The input payload must be an object.');

            return null;
        }

        $this->validateMotionVideoFields($validator, $payload);

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function validateMotionVideoFields(Validator $validator, array $payload): void
    {
        $this->validateStringFields($validator, $payload, [
            'title' => 255,
            'product_name' => 255,
            'product_description' => 4000,
            'target_audience' => 1000,
            'brand_notes' => 2000,
            'scene_notes' => 2000,
            'audio_notes' => 2000,
            'notes' => 2000,
        ]);

        $this->validateAllowedValue($validator, $payload, 'video_goal', self::MOTION_VIDEO_GOALS);
        $this->validateAllowedValue($validator, $payload, 'motion_style', self::MOTION_STYLES);
        $this->validateAllowedValue($validator, $payload, 'visual_style', self::MOTION_VISUAL_STYLES);
        $this->validateAllowedValue($validator, $payload, 'aspect_ratio', self::MOTION_ASPECT_RATIOS);
        $this->validateAllowedValue($validator, $payload, 'platform', self::MOTION_PLATFORMS);
        $this->validateAllowedValue($validator, $payload, 'language', self::MOTION_LANGUAGES);

        if (array_key_exists('duration_seconds', $payload) && ! in_array((int) $payload['duration_seconds'], self::MOTION_DURATIONS, true)) {
            $validator->errors()->add('input_payload.duration_seconds', 'The selected duration is invalid.');
        }
    }
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, int>  $fields
     */
    private function validateStringFields(Validator $validator, array $payload, array $fields): void
    {
        foreach ($fields as $field => $maxLength) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                continue;
            }

            if (! is_string($payload[$field])) {
                $validator->errors()->add("input_payload.{$field}", 'This field must be text.');
                continue;
            }

            if (mb_strlen($payload[$field]) > $maxLength) {
                $validator->errors()->add("input_payload.{$field}", "This field may not be greater than {$maxLength} characters.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $allowed
     */
    private function validateAllowedValue(Validator $validator, array $payload, string $field, array $allowed): void
    {
        if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
            return;
        }

        if (! is_string($payload[$field]) || ! in_array($payload[$field], $allowed, true)) {
            $validator->errors()->add("input_payload.{$field}", 'The selected value is invalid.');
        }
    }
}