<?php

return [
    'provider' => env('VIDEO_TO_CAMPAIGN_PROVIDER', 'mock'),
    'real_enabled' => (bool) env('VIDEO_TO_CAMPAIGN_REAL_ENABLED', false),
    'real_internal_only' => (bool) env('VIDEO_TO_CAMPAIGN_REAL_INTERNAL_ONLY', true),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_VIDEO_MODEL', 'gemini-2.5-flash'),
        'base_url' => rtrim((string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'), '/'),
        'max_file_size_mb' => (int) env('GEMINI_VIDEO_MAX_FILE_SIZE_MB', 50),
        'max_duration_seconds' => (int) env('GEMINI_VIDEO_MAX_DURATION_SECONDS', 60),
        'request_timeout_seconds' => (int) env('GEMINI_VIDEO_REQUEST_TIMEOUT_SECONDS', 180),
        'file_processing_timeout_seconds' => (int) env('GEMINI_VIDEO_FILE_PROCESSING_TIMEOUT_SECONDS', 120),
        'file_processing_poll_interval_seconds' => (int) env('GEMINI_VIDEO_FILE_PROCESSING_POLL_INTERVAL_SECONDS', 3),
        'debug_response_log' => (bool) env('GEMINI_VIDEO_DEBUG_RESPONSE_LOG', false),
        'input_cost_per_1m_tokens' => env('GEMINI_VIDEO_INPUT_USD_PER_1M_TOKENS'),
        'output_cost_per_1m_tokens' => env('GEMINI_VIDEO_OUTPUT_USD_PER_1M_TOKENS'),
    ],
];
