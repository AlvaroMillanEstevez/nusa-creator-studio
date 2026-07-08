<?php

return [
    'provider' => env('VOICEOVER_PROVIDER', 'mock'),
    'real_enabled' => (bool) env('VOICEOVER_REAL_ENABLED', false),
    'real_internal_only' => (bool) env('VOICEOVER_REAL_INTERNAL_ONLY', true),
    'max_characters' => (int) env('VOICEOVER_MAX_CHARACTERS', 1000),

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'default_voice_id' => env('ELEVENLABS_DEFAULT_VOICE_ID'),
        'default_model_id' => env('ELEVENLABS_DEFAULT_MODEL_ID', 'eleven_flash_v2_5'),
        'high_quality_model_id' => env('ELEVENLABS_HIGH_QUALITY_MODEL_ID', 'eleven_multilingual_v2'),
        'base_url' => rtrim((string) env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'), '/'),
        'timeout_seconds' => (int) env('ELEVENLABS_TIMEOUT_SECONDS', 60),
        'flash_cost_per_1k_chars' => (float) env('ELEVENLABS_FLASH_COST_PER_1K_CHARS', 0.05),
        'multilingual_cost_per_1k_chars' => (float) env('ELEVENLABS_MULTILINGUAL_COST_PER_1K_CHARS', 0.10),
    ],
];
