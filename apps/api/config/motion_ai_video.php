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
        'request_mode' => env('GOOGLE_VEO_REQUEST_MODE', 'text_only'),
        'image_references_enabled' => (bool) env('GOOGLE_VEO_IMAGE_REFERENCES_ENABLED', false),
        'image_to_video_enabled' => (bool) env('GOOGLE_VEO_IMAGE_TO_VIDEO_ENABLED', false),
        'allow_experimental_image_input' => (bool) env('GOOGLE_VEO_ALLOW_EXPERIMENTAL_IMAGE_INPUT', false),
        'default_duration_seconds' => (int) env('GOOGLE_VEO_DEFAULT_DURATION_SECONDS', 8),
        'default_aspect_ratio' => env('GOOGLE_VEO_DEFAULT_ASPECT_RATIO', '9:16'),
        'max_reference_images' => (int) env('GOOGLE_VEO_MAX_REFERENCE_IMAGES', 3),
        'connect_timeout_seconds' => (int) env('GOOGLE_VEO_CONNECT_TIMEOUT_SECONDS', 60),
        'timeout_seconds' => (int) env('GOOGLE_VEO_TIMEOUT_SECONDS', env('GOOGLE_VEO_REQUEST_TIMEOUT_SECONDS', 300)),
        'request_timeout_seconds' => (int) env('GOOGLE_VEO_REQUEST_TIMEOUT_SECONDS', 180),
        'retry_times' => (int) env('GOOGLE_VEO_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('GOOGLE_VEO_RETRY_SLEEP_MS', 1500),
        'force_ipv4' => (bool) env('GOOGLE_VEO_FORCE_IPV4', false),
        'force_http1' => (bool) env('GOOGLE_VEO_FORCE_HTTP1', false),
        'poll_interval_seconds' => (int) env('GOOGLE_VEO_POLL_INTERVAL_SECONDS', 5),
        'max_poll_attempts' => (int) env('GOOGLE_VEO_MAX_POLL_ATTEMPTS', 120),
        'sdk_process_timeout_seconds' => (int) env('GOOGLE_VEO_SDK_PROCESS_TIMEOUT_SECONDS', 900),
        'sdk_poll_interval_seconds' => (int) env('GOOGLE_VEO_SDK_POLL_INTERVAL_SECONDS', 10),
        'estimated_cost_usd_per_second' => env('GOOGLE_VEO_ESTIMATED_COST_USD_PER_SECOND'),
    ],
];
