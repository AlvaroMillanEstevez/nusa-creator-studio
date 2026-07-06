<?php

return [
    'disk' => env('MEDIA_DISK', 'local'),
    'max_size_kb' => (int) env('MEDIA_MAX_SIZE_KB', 51200),
];
