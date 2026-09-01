<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permanent queue worker watchdog
    |--------------------------------------------------------------------------
    |
    | queue:watchdog runs forever and keeps one dedicated queue:work process
    | alive per queue below. Each worker uses an explicit --queue flag only —
    | never the default queue, so unrelated jobs are not processed.
    |
    |   php artisan queue:watchdog
    |   scripts/cron-queue-watchdog-daemon.sh
    |
    */

    'watchdog_interval_seconds' => (int) env('QUEUE_WATCHDOG_INTERVAL', 30),

    /*
    |--------------------------------------------------------------------------
    | Stale worker detection (zombie queue:work processes)
    |--------------------------------------------------------------------------
    |
    | Workers stuck in blocking I/O may ignore --max-time. The watchdog kills
    | and respawns when process age or worker log staleness exceeds thresholds.
    |
    */
    'stale_process_grace_seconds' => (int) env('QUEUE_WATCHDOG_STALE_PROCESS_GRACE', 300),
    'stale_log_grace_seconds' => (int) env('QUEUE_WATCHDOG_STALE_LOG_GRACE', 600),

    'watchdog_queues' => [
        'google-maps-extractor' => [
            'timeout' => 3700,
            'max_time' => 7200,
        ],
        'shopify-image-pull' => [
            'timeout' => 14400,
            'max_time' => 14400,
        ],
        'shopify-bullet-pull' => [
            'timeout' => 14400,
            'max_time' => 14400,
        ],
        'shopify-video-pull' => [
            'timeout' => 14400,
            'max_time' => 14400,
        ],
        'image-master-push' => [
            'timeout' => 7200,
            'max_time' => 7200,
        ],
        'video-master-push' => [
            'timeout' => 7200,
            'max_time' => 7200,
        ],
        'pricing-errors-fix-push' => [
            'timeout' => 14400,
            'max_time' => 14400,
        ],
        'amazon-push-prc' => [
            'timeout' => 14400,
            'max_time' => 14400,
        ],
        'marketplace-manager' => [
            'timeout' => 1800,
            'max_time' => 7200,
        ],
        'mm-tracking' => [
            'timeout' => 1800,
            'max_time' => 7200,
        ],
        // SOF "Update Shipment Status" (SyncShipmentTrackingStatusJob) — do not use default queue
        'shipment-tracking' => [
            'timeout' => 1800,
            'max_time' => 7200,
        ],

        
        // Shopify webhook ingress (ProcessShopifyInventoryWebhook / ResolveInventoryItemSkuJob)
        'mm-ingress' => [
            'timeout' => 180,
            'max_time' => 7200,
        ],
        // Per-marketplace mm-{slug} queues are injected by QueueWorkerWatchdog
        // from MarketplaceManagerRegistry (auto when you add a new channel).
        'aliexpress' => [
            'timeout' => 900,
            'max_time' => 7200,
        ],
        'alibaba' => [
            'timeout' => 900,
            'max_time' => 7200,
        ],
    ],

];
