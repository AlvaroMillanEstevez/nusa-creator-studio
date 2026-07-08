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
        ];
    }
}
