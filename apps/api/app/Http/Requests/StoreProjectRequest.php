<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    private const VOICEOVER_LANGUAGES = ['english', 'indonesian', 'spanish'];

    private const VOICEOVER_STYLES = [
        'natural_creator',
        'energetic_seller',
        'calm_explainer',
        'premium_brand',
        'friendly_storyteller',
    ];

    private const VOICEOVER_TONES = [
        'natural',
        'persuasive',
        'playful',
        'professional',
        'emotional',
    ];

    private const VOICEOVER_PACES = ['slow', 'medium', 'fast'];

    private const VOICEOVER_DURATIONS = [15, 30, 45, 60];

    private const VOICEOVER_PLATFORMS = [
        'tiktok',
        'instagram_reels',
        'youtube_shorts',
        'generic_vertical_video',
    ];

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
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Project::TYPES)],
            'status' => ['sometimes', 'string', Rule::in(Project::STATUSES)],
            'input_payload' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator): null => $this->validateVoiceoverPayload($validator),
        ];
    }

    private function validateVoiceoverPayload(Validator $validator): null
    {
        if ($this->input('type') !== 'ai_voiceover') {
            return null;
        }

        $payload = $this->input('input_payload');

        if (! is_array($payload)) {
            $validator->errors()->add('input_payload', 'The input payload is required for AI voiceover projects.');

            return null;
        }

        foreach (['title', 'language', 'voice_style', 'tone', 'pace', 'duration_seconds', 'platform'] as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                $validator->errors()->add("input_payload.{$field}", 'This field is required for AI voiceover projects.');
            }
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