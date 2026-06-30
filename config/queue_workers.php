<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permanent queue worker watchdog
    |--------------------------------------------------------------------------
    |
    | The watchdog daemon only starts dedicated queue:work processes with an
    | explicit --queue flag. It never runs a generic worker that could process
    | other jobs from the default queue.
    |
    | Run permanently via Supervisor:
    |   php artisan queue:watchdog
    |
    | Or the shell fallback:
    |   scripts/cron-google-maps-extractor-watchdog.sh
    |
    */

    'watchdog_interval_seconds' => (int) env('QUEUE_WATCHDOG_INTERVAL', 30),

    'watchdog_queues' => [
        'google-maps-extractor' => [
            'timeout' => 3700,
            'max_time' => 7200,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional dedicated workers (manual / separate supervisor programs)
    |--------------------------------------------------------------------------
    |
    | Not started by the Google Maps watchdog daemon. Other masters can keep
    | their own scripts or supervisor entries when needed.
    */

    'optional_dedicated_queues' => [
        'shopify-image-pull' => ['timeout' => 14400, 'max_time' => 14400],
        'shopify-bullet-pull' => ['timeout' => 14400, 'max_time' => 14400],
        'shopify-video-pull' => ['timeout' => 14400, 'max_time' => 14400],
        'image-master-push' => ['timeout' => 7200, 'max_time' => 7200],
        'video-master-push' => ['timeout' => 7200, 'max_time' => 7200],
    ],

];
