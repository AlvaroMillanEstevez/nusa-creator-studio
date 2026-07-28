<?php

return [
    'provider' => env('FASHION_TRY_ON_PROVIDER', 'mock'),
    'real_enabled' => (bool) env('FASHION_TRY_ON_REAL_ENABLED', false),
    'real_internal_only' => (bool) env('FASHION_TRY_ON_REAL_INTERNAL_ONLY', true),
    'public_enabled' => (bool) env('FASHION_TRY_ON_PUBLIC_ENABLED', true),

    'credit_costs' => [
        'standard' => env('FASHION_TRY_ON_STANDARD_CREDIT_COST'),
        'premium' => env('FASHION_TRY_ON_PREMIUM_CREDIT_COST'),
        'premium_presets' => [
            'premium_fast' => env('FASHION_TRY_ON_PREMIUM_FAST_CREDIT_COST'),
            'premium_balanced' => env('FASHION_TRY_ON_PREMIUM_BALANCED_CREDIT_COST'),
            'premium_quality' => env('FASHION_TRY_ON_PREMIUM_QUALITY_CREDIT_COST'),
        ],
    ],

    'fashn' => [
        'api_key' => env('FASHN_API_KEY'),
        'base_url' => rtrim((string) env('FASHN_BASE_URL', 'https://api.fashn.ai/v1'), '/'),

        'standard_enabled' => (bool) env('FASHN_STANDARD_ENABLED', true),
        'standard_model' => env('FASHN_STANDARD_MODEL', 'tryon-v1.6'),
        'standard_mode' => env('FASHN_STANDARD_MODE', 'balanced'),
        'standard_category' => env('FASHN_STANDARD_CATEGORY', 'auto'),
        'standard_garment_photo_type' => env('FASHN_STANDARD_GARMENT_PHOTO_TYPE', 'auto'),
        'standard_segmentation_free' => (bool) env('FASHN_STANDARD_SEGMENTATION_FREE', true),

        'premium_enabled' => (bool) env('FASHN_PREMIUM_ENABLED', false),
        'premium_model' => env('FASHN_PREMIUM_MODEL', 'tryon-max'),
        'premium_default_resolution' => env('FASHN_PREMIUM_DEFAULT_RESOLUTION', '1k'),
        'premium_default_generation_mode' => env('FASHN_PREMIUM_DEFAULT_GENERATION_MODE', 'fast'),

        'moderation_level' => env('FASHN_MODERATION_LEVEL', 'permissive'),
        'output_format' => env('FASHN_OUTPUT_FORMAT', 'png'),
        'return_base64' => (bool) env('FASHN_RETURN_BASE64', true),

        'connect_timeout_seconds' => (int) env('FASHN_CONNECT_TIMEOUT_SECONDS', 30),
        'request_timeout_seconds' => (int) env('FASHN_REQUEST_TIMEOUT_SECONDS', 120),
        'poll_interval_seconds' => (int) env('FASHN_POLL_INTERVAL_SECONDS', 3),
        'max_poll_attempts' => (int) env('FASHN_MAX_POLL_ATTEMPTS', 60),

        'estimated_cost_usd_per_credit' => env('FASHN_ESTIMATED_COST_USD_PER_CREDIT', 0.075),
    ],
];
