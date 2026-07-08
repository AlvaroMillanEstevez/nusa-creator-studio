<?php

return [
    'provider' => env('CAMPAIGN_PROVIDER', 'mock'),
    'real_enabled' => env('CAMPAIGN_REAL_ENABLED', false),
    'real_internal_only' => env('CAMPAIGN_REAL_INTERNAL_ONLY', true),
    'max_input_characters' => (int) env('CAMPAIGN_MAX_INPUT_CHARACTERS', 4000),
    'max_output_tokens' => (int) env('CAMPAIGN_MAX_OUTPUT_TOKENS', 2500),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'campaign_model' => env('OPENAI_CAMPAIGN_MODEL', 'gpt-5.4-nano'),
        'campaign_fallback_model' => env('OPENAI_CAMPAIGN_FALLBACK_MODEL', 'gpt-5.4-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 60),
        'input_cost_per_1m_tokens' => env('OPENAI_CAMPAIGN_INPUT_COST_PER_1M_TOKENS'),
        'output_cost_per_1m_tokens' => env('OPENAI_CAMPAIGN_OUTPUT_COST_PER_1M_TOKENS'),
    ],
];
