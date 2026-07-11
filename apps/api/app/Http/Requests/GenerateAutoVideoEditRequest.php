<?php

namespace App\Http\Requests;

use App\Support\AutoVideoCaptionStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateAutoVideoEditRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'caption_style' => AutoVideoCaptionStyle::normalize($this->input('caption_style')),
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
            'source_video_asset_id' => ['sometimes', 'nullable', 'integer'],
            'source_video_to_campaign_generation_id' => ['sometimes', 'nullable', 'integer'],
            'voiceover_generation_id' => ['sometimes', 'nullable', 'integer'],
            'voiceover_audio_asset_id' => ['sometimes', 'nullable', 'integer'],
            'include_voiceover' => ['sometimes', 'boolean'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:80'],
            'format' => ['sometimes', 'nullable', 'string', 'max:80'],
            'editing_style' => ['sometimes', 'nullable', 'string', Rule::in(['ugc_natural', 'premium_clean', 'fast_cuts', 'storytelling', 'review'])],
            'pacing' => ['sometimes', 'nullable', 'string', Rule::in(['steady', 'medium', 'fast'])],
            'caption_style' => ['sometimes', 'nullable', 'string', Rule::in(AutoVideoCaptionStyle::canonicalValues())],
            'target_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:120'],
            'requested_target_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:120'],
            'effective_target_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:120'],
            'cta' => ['sometimes', 'nullable', 'string', 'max:240'],
            'music_direction' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
