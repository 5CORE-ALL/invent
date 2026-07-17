<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature toggle
    |--------------------------------------------------------------------------
    */
    'enabled' => env('CRON_MONITOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-monitor every artisan command in Kernel::schedule()
    |--------------------------------------------------------------------------
    |
    | Uses CommandStarting/Finished so runInBackground() jobs are tracked until
    | they actually complete. Rich metrics still require MonitoredCommand.
    |
    */
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

    // Watchdog miss-detection for all Kernel commands (except skip_miss_schedules)
    'auto_watch_scheduled' => env('CRON_MONITOR_AUTO_WATCH', true),

    /*
    |--------------------------------------------------------------------------
    | Status thresholds (success percentage)
    |--------------------------------------------------------------------------
    |
    | Success:        >= success_min
    | Partial Success: success_min > rate >= partial_min
    | Failed:         < partial_min
    |
    */
    'thresholds' => [
        'success_min' => (float) env('CRON_MONITOR_SUCCESS_MIN', 95),
        'partial_min' => (float) env('CRON_MONITOR_PARTIAL_MIN', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health score weights (must sum to 100)
    |--------------------------------------------------------------------------
    */
    'health_score' => [
        'cron_started' => 20,
        'api_successful' => 20,
        'fetched_records' => 20,
        'updated_records' => 20,
        'validation_passed' => 20,
        'labels' => [
            'healthy' => 80,
            'warning' => 50,
            // below warning => critical
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation rules
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'require_api_data' => true,
        'require_fetched' => true,
        'require_processed' => true,
        'require_updates' => true,
        'allow_zero_when_expected_zero' => true,
        // Fail when updated/expected is below this ratio (e.g. 0.60 = 60%)
        'min_update_ratio_vs_expected' => (float) env('CRON_MONITOR_MIN_UPDATE_RATIO', 0.60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Historical anomaly detection
    |--------------------------------------------------------------------------
    |
    | Compare against the previous successful run for the same job.
    | Values are drop/increase ratios that trigger alerts.
    |
    */
    'anomaly' => [
        'enabled' => env('CRON_MONITOR_ANOMALY_ENABLED', true),
        'update_drop_percent' => (float) env('CRON_MONITOR_ANOMALY_UPDATE_DROP', 50),
        'fetch_drop_percent' => (float) env('CRON_MONITOR_ANOMALY_FETCH_DROP', 50),
        'runtime_increase_percent' => (float) env('CRON_MONITOR_ANOMALY_RUNTIME_INCREASE', 100),
        'failure_spike_multiplier' => (float) env('CRON_MONITOR_ANOMALY_FAILURE_SPIKE', 3),
        'min_baseline_updates' => (int) env('CRON_MONITOR_ANOMALY_MIN_BASELINE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeouts & watchdog
    |--------------------------------------------------------------------------
    */
    'timeouts' => [
        'default_minutes' => (int) env('CRON_MONITOR_DEFAULT_TIMEOUT', 120),
        'stale_running_minutes' => (int) env('CRON_MONITOR_STALE_RUNNING', 180),
    ],

    'watchdog' => [
        'enabled' => env('CRON_MONITOR_WATCHDOG_ENABLED', true),
        'grace_minutes' => (int) env('CRON_MONITOR_GRACE_MINUTES', 30),
        // Miss alerts are noisy for these; timeouts/stuck still apply
        'skip_miss_schedules' => [
            'every_minute',
            'every_five_minutes',
            'every_ten_minutes',
            'every_thirty_minutes',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry failed records
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => (int) env('CRON_MONITOR_MAX_RETRY', 3),
        'queue' => env('CRON_MONITOR_RETRY_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */
    'retention_days' => (int) env('CRON_MONITOR_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Notifications — only non-healthy outcomes
    |--------------------------------------------------------------------------
    |
    | Uses your existing in-app channels (no external webhooks):
    |   taskmanager  → TASKMANAGER_URL + TASKMANAGER_API_KEY (same as scheduler hooks)
    |   database     → Laravel notifications for users
    |   mail         → CRON_MONITOR_MAIL_TO or ADMIN_EMAIL
    |   whatsapp     → existing WhatsAppService / Gupshup
    |
    */
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
        // Alert only on these statuses / events
        'alert_on' => [
            'failed',
            'partial_success',
            'validation_failed',
            'no_updates',
            'cron_missed',
            'runtime_exceeded',
            'timed_out',
            'anomaly',
            'still_running',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Watched jobs (for miss / timeout detection)
    |--------------------------------------------------------------------------
    |
    | schedule: daily|hourly|weekly|every_minute|every_five_minutes|every_ten_minutes|custom
    | expected_at: HH:MM for daily/weekly (timezone applied)
    | day_of_week: 0=Sunday … 6=Saturday (weekly only)
    | timeout_minutes: mark running executions as timed out after this
    | grace_minutes: how late a job may start before "missed"
    |
    */
    'watched_jobs' => [
        // Example — uncomment / add real commands as you adopt monitoring:
        // 'amazon:auto-update-over-kw-bids' => [
        //     'job_name' => 'Amazon Bid Sync (KW Over)',
        //     'schedule' => 'daily',
        //     'expected_at' => '02:00',
        //     'timezone' => 'Asia/Kolkata',
        //     'timeout_minutes' => 90,
        //     'grace_minutes' => 45,
        // ],
    ],

];
