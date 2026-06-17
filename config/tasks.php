<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task business timezone (office calendar)
    |--------------------------------------------------------------------------
    |
    | TID, daily auto-task creation, same-day rules, and auto-delete all use this
    | timezone. Set to IST (Asia/Kolkata) so the TID reflects the current India
    | date and daily automated tasks are generated for 14:00 IST.
    |
    */
    'business_timezone' => env('TASK_BUSINESS_TIMEZONE', 'Asia/Kolkata'),

    'timezone_label' => env('TASK_TIMEZONE_LABEL', 'India (IST)'),

    'timezone_short' => env('TASK_TIMEZONE_SHORT', 'IST'),

    /** Local time (business TZ) when incomplete daily auto-tasks are archived. */
    'auto_delete_time' => env('TASK_AUTO_DELETE_TIME', '00:05:00'),

    /** start_date time for newly generated daily automated instances. */
    'daily_generate_time' => env('TASK_DAILY_GENERATE_TIME', '14:00:00'),

    /*
    |--------------------------------------------------------------------------
    | Automated "missed task" windows (hours after the generated start time)
    |--------------------------------------------------------------------------
    |
    | An AUTO-GENERATED task instance becomes "missed" this many hours after its
    | generated start time (start_date). Measured in the business timezone.
    | These rules apply ONLY to automated tasks (is_automate_task = 1) — never to
    | normal/manual tasks.
    |   daily   = 24 hours
    |   weekly  = 144 hours (6 days)
    |   monthly = 720 hours (30 days)
    |
    */
    'missed_after_hours' => [
        'daily' => (int) env('TASK_MISSED_AFTER_HOURS_DAILY', 24),
        'weekly' => (int) env('TASK_MISSED_AFTER_HOURS_WEEKLY', 144),
        'monthly' => (int) env('TASK_MISSED_AFTER_HOURS_MONTHLY', 720),
    ],

];
