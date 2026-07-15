<?php

return [
    'provider' => env('MOTION_AI_VIDEO_PROVIDER', 'mock'),
    'real_enabled' => (bool) env('MOTION_AI_VIDEO_REAL_ENABLED', false),
    'real_internal_only' => (bool) env('MOTION_AI_VIDEO_REAL_INTERNAL_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Google Veo
    |--------------------------------------------------------------------------
    |
    | Real Motion AI Video rendering is disabled by default. The Google Veo
    | request shape is isolated in a dedicated client so endpoint/model changes
    | can be adjusted without touching the workflow service layer. The base URL
    | may be configured with or without the /v1beta suffix.
    |
    */
    'google_veo' => [
        'api_key' => env('GOOGLE_VEO_API_KEY'),
        'base_url' => rtrim((string) env('GOOGLE_VEO_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'model' => env('GOOGLE_VEO_MODEL', 'veo-3.1-generate-preview'),
        'image_mode' => env('GOOGLE_VEO_IMAGE_MODE', 'reference_images'),
        'request_timeout_seconds' => (int) env('GOOGLE_VEO_REQUEST_TIMEOUT_SECONDS', 180),
        'poll_interval_seconds' => (int) env('GOOGLE_VEO_POLL_INTERVAL_SECONDS', 5),
        'max_poll_attempts' => (int) env('GOOGLE_VEO_MAX_POLL_ATTEMPTS', 120),
        'estimated_cost_usd_per_second' => env('GOOGLE_VEO_ESTIMATED_COST_USD_PER_SECOND'),
    ],
];
