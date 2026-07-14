<?php

namespace App\Http\Requests;

use App\Support\AutoVideoCaptionStyle;
use App\Support\AutoVideoEditSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateAutoVideoEditRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $hasVoiceover = $this->boolean('include_voiceover')
            || $this->filled('voiceover_generation_id')
            || $this->filled('voiceover_audio_asset_id');
        $hasCta = is_string($this->input('cta')) && trim((string) $this->input('cta')) !== '';

        $this->merge([
            'edit_mode' => $this->input('edit_mode') ?: ($this->filled('source_video_to_campaign_generation_id') ? 'ai_video_analysis' : 'manual_edit'),
            'caption_style' => AutoVideoCaptionStyle::normalize($this->input('caption_style')),
            'source_audio_mode' => AutoVideoEditSettings::sourceAudioMode($this->input('source_audio_mode'), $hasVoiceover),
            'voiceover_mix' => AutoVideoEditSettings::voiceoverMix($this->input('voiceover_mix')),
            'transition_style' => AutoVideoEditSettings::transitionStyle($this->input('transition_style'), $this->input('editing_style')),
            'motion_polish' => AutoVideoEditSettings::motionPolish($this->input('motion_polish')),
            'clip_diversity' => AutoVideoEditSettings::clipDiversity($this->input('clip_diversity')),
            'end_screen' => AutoVideoEditSettings::endScreen($this->input('end_screen'), $hasCta),
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
            'edit_mode' => ['sometimes', 'nullable', 'string', Rule::in(['ai_video_analysis', 'manual_edit'])],
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
            'source_audio_mode' => ['sometimes', 'nullable', 'string', Rule::in(AutoVideoEditSettings::sourceAudioModes())],
            'voiceover_mix' => ['sometimes', 'nullable', 'string', Rule::in(AutoVideoEditSettings::voiceoverMixes())],
            'transition_style' => ['sometimes', 'nullable', 'string', Rule::in(AutoVideoEditSettings::transitionStyles())],
            'motion_polish' => ['sometimes', 'nullable', 'string', Rule::in(AutoVideoEditSettings::motionPolishOptions())],
            'clip_diversity' => ['sometimes', 'nullable', 'string', Rule::in(AutoVideoEditSettings::clipDiversityOptions())],
            'end_screen' => ['sometimes', 'nullable', 'string', Rule::in(AutoVideoEditSettings::endScreenOptions())],
            'target_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:120'],
            'requested_target_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:120'],
            'effective_target_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:120'],
            'cta' => ['sometimes', 'nullable', 'string', 'max:240'],
            'music_direction' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
