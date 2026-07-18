<?php

return [

    'enabled' => env('CRON_MONITOR_ENABLED', true),

    'auto_monitor' => [
        'enabled' => env('CRON_MONITOR_AUTO', true),
        'skip' => [
            'schedule:run',
            'schedule:finish',
            'schedule:work',
            'queue:work',
            'queue:listen',
            'queue:ensure-watchdog-daemon',
            'cron-monitor:*',
            'storage:ensure',
            'scheduler-heartbeat',
            'clear-laravel-log',
            'crm-follow-up-reminders',
        ],
    ],

    'auto_watch_scheduled' => env('CRON_MONITOR_AUTO_WATCH', true),

    'thresholds' => [
        'success_min' => (float) env('CRON_MONITOR_SUCCESS_MIN', 95),
        'partial_min' => (float) env('CRON_MONITOR_PARTIAL_MIN', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health score weights (sum should be 100)
    |--------------------------------------------------------------------------
    */
    'health_score' => [
        'cron_started' => 15,
        'api_successful' => 15,
        'fetched_records' => 15,
        'updated_records' => 15,
        'validation_passed' => 15,
        'retry_success' => 10,
        'runtime' => 10,
        'historical' => 5,
        'labels' => [
            'healthy' => 80,
            'warning' => 50,
        ],
    ],

    'validation' => [
        'require_api_data' => true,
        'require_fetched' => true,
        'require_processed' => true,
        'require_updates' => true,
        'allow_zero_when_expected_zero' => true,
        'min_update_ratio_vs_expected' => (float) env('CRON_MONITOR_MIN_UPDATE_RATIO', 0.60),
    ],

    'anomaly' => [
        'enabled' => env('CRON_MONITOR_ANOMALY_ENABLED', true),
        'update_drop_percent' => (float) env('CRON_MONITOR_ANOMALY_UPDATE_DROP', 50),
        'fetch_drop_percent' => (float) env('CRON_MONITOR_ANOMALY_FETCH_DROP', 50),
        'runtime_increase_percent' => (float) env('CRON_MONITOR_ANOMALY_RUNTIME_INCREASE', 100),
        'failure_spike_multiplier' => (float) env('CRON_MONITOR_ANOMALY_FAILURE_SPIKE', 3),
        'failure_rate_increase_percent' => (float) env('CRON_MONITOR_ANOMALY_FAILURE_RATE', 50),
        'memory_increase_percent' => (float) env('CRON_MONITOR_ANOMALY_MEMORY', 100),
        'latency_increase_percent' => (float) env('CRON_MONITOR_ANOMALY_LATENCY', 100),
        'min_baseline_updates' => (int) env('CRON_MONITOR_ANOMALY_MIN_BASELINE', 100),
    ],

    'timeouts' => [
        'default_minutes' => (int) env('CRON_MONITOR_DEFAULT_TIMEOUT', 120),
        'stale_running_minutes' => (int) env('CRON_MONITOR_STALE_RUNNING', 180),
    ],

    'watchdog' => [
        'enabled' => env('CRON_MONITOR_WATCHDOG_ENABLED', true),
        'grace_minutes' => (int) env('CRON_MONITOR_GRACE_MINUTES', 30),
        'skip_miss_schedules' => [
            'every_minute',
            'every_five_minutes',
            'every_ten_minutes',
            'every_thirty_minutes',
        ],
        'auto_unlock_stuck' => env('CRON_MONITOR_AUTO_UNLOCK_STUCK', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Intelligent retry
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => (int) env('CRON_MONITOR_MAX_RETRY', 3),
        'queue' => env('CRON_MONITOR_RETRY_QUEUE', 'default'),
        'retry_delay' => [
            1 => (int) env('CRON_MONITOR_RETRY_DELAY_1', 30),
            2 => (int) env('CRON_MONITOR_RETRY_DELAY_2', 120),
            3 => (int) env('CRON_MONITOR_RETRY_DELAY_3', 300),
        ],
        'recoverable_http' => [429, 500, 502, 503, 504],
        'recoverable_categories' => [
            'timeout',
            'rate_limit',
            'network',
            'database',
            'api',
        ],
        'non_recoverable_categories' => [
            'validation',
            'logic',
            'authentication',
        ],
    ],

    'locks' => [
        'enabled' => env('CRON_MONITOR_LOCKS', true),
        'ttl_seconds' => (int) env('CRON_MONITOR_LOCK_TTL', 7200),
        'prefix' => 'cron-monitor:lock:',
    ],

    'stuck' => [
        'enabled' => env('CRON_MONITOR_STUCK_ENABLED', true),
        'multiplier' => (float) env('CRON_MONITOR_STUCK_MULTIPLIER', 3.0),
        'min_expected_seconds' => (int) env('CRON_MONITOR_STUCK_MIN_EXPECTED', 120),
    ],

    'self_healing' => [
        'enabled' => env('CRON_MONITOR_HEALING_ENABLED', true),
        'db_reconnect' => env('CRON_MONITOR_HEAL_DB', true),
        'queue_watchdog' => env('CRON_MONITOR_HEAL_QUEUE', true),
    ],

    'alerts' => [
        'group_window_minutes' => (int) env('CRON_MONITOR_ALERT_GROUP_MINUTES', 15),
        'flush_on_critical' => env('CRON_MONITOR_ALERT_FLUSH_CRITICAL', false),
    ],

    'retention_days' => (int) env('CRON_MONITOR_RETENTION_DAYS', 90),

    'notifications' => [
        'enabled' => env('CRON_MONITOR_NOTIFY_ENABLED', true),
        'queue' => env('CRON_MONITOR_NOTIFY_QUEUE', 'default'),
        'channels' => array_filter(array_map(
            'trim',
            explode(',', env('CRON_MONITOR_CHANNELS', 'taskmanager,database'))
        )),
        'mail' => [
            'to' => array_filter(array_map('trim', explode(',', env('CRON_MONITOR_MAIL_TO', '')))),
        ],
        'whatsapp' => [
            'to' => array_filter(array_map('trim', explode(',', env('CRON_MONITOR_WHATSAPP_TO', '')))),
        ],
        'alert_on' => [
            'failed',
            'partial_success',
            'validation_failed',
            'no_updates',
            'cron_missed',
            'runtime_exceeded',
            'timed_out',
            'stuck',
            'anomaly',
            'still_running',
            'recovered',
        ],
    ],

    'watched_jobs' => [
        // Optional overrides for Kernel-discovered jobs
    ],

];
