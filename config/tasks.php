<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task business timezone (office calendar)
    |--------------------------------------------------------------------------
    |
    | TID, daily auto-task creation, same-day rules, and auto-delete all use this
    | timezone — not the app default (Asia/Kolkata) and not each user's browser.
    | Staff in India still see California business dates on /tasks.
    |
    */
    'business_timezone' => env('TASK_BUSINESS_TIMEZONE', 'America/Los_Angeles'),

    'timezone_label' => env('TASK_TIMEZONE_LABEL', 'California (PT)'),

    'timezone_short' => env('TASK_TIMEZONE_SHORT', 'PT'),

    /** Local time (business TZ) when incomplete daily auto-tasks are archived. */
    'auto_delete_time' => env('TASK_AUTO_DELETE_TIME', '00:05:00'),

    /** start_date time for newly generated daily automated instances. */
    'daily_generate_time' => env('TASK_DAILY_GENERATE_TIME', '00:01:00'),

];
