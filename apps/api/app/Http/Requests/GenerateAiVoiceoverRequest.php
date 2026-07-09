<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAiVoiceoverRequest extends FormRequest
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
        $maxCharacters = max(1, (int) config('voiceover.max_characters', 1000));

        return [
            'source_text' => ['sometimes', 'nullable', 'string', 'max:'.$maxCharacters],
            'source_generation_id' => ['sometimes', 'nullable', 'integer'],
            'language' => ['sometimes', 'nullable', 'string', 'max:40'],
            'tone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'pace' => ['sometimes', 'nullable', 'string', 'max:40'],
            'style' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'voice_id' => ['sometimes', 'nullable', 'string', 'max:160'],
            'voice_settings' => ['sometimes', 'nullable', 'array'],
            'voice_settings.stability' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'voice_settings.similarity_boost' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'voice_settings.style' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'voice_settings.use_speaker_boost' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
