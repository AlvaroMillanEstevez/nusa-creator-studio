<?php

return [
    'provider' => env('AUTO_VIDEO_EDITOR_PROVIDER', 'mock'),
    'real_enabled' => (bool) env('AUTO_VIDEO_EDITOR_REAL_ENABLED', false),
    'real_internal_only' => (bool) env('AUTO_VIDEO_EDITOR_REAL_INTERNAL_ONLY', true),

    'shotstack' => [
        'api_key' => env('SHOTSTACK_API_KEY'),
        'base_url' => rtrim((string) env('SHOTSTACK_BASE_URL', 'https://api.shotstack.io'), '/'),
        'environment' => env('SHOTSTACK_ENV', 'stage'),
        'render_timeout_seconds' => (int) env('SHOTSTACK_RENDER_TIMEOUT_SECONDS', 600),
        'render_poll_interval_seconds' => (int) env('SHOTSTACK_RENDER_POLL_INTERVAL_SECONDS', 5),
        'render_max_poll_attempts' => (int) env('SHOTSTACK_RENDER_MAX_POLL_ATTEMPTS', 120),
        'estimated_provider_cost_usd' => env('SHOTSTACK_ESTIMATED_RENDER_COST_USD'),
    ],

    'provider_assets' => [
        'public_base_url' => rtrim((string) env('PROVIDER_ASSET_PUBLIC_BASE_URL', ''), '/'),
        'ttl_minutes' => (int) env('PROVIDER_ASSET_URL_TTL_MINUTES', 30),
    ],
];
