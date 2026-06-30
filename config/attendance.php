<?php

return [

    /*
    | Emails with full monitor access (all employees).
    */
    'monitor_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software@5core.com',
        'software5@5core.com',
    ],

    /*
    | Emails that can manage policies and review AI flags.
    */
    'admin_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software5@5core.com',
    ],

    /*
    | Heartbeat interval in seconds (client-side).
    */
    'heartbeat_interval_seconds' => 60,

    /*
    | Idle threshold — no mouse/keyboard for this many seconds = idle.
    */
    'idle_threshold_seconds' => 120,

    /*
    | Auto-close sessions with no heartbeat for this many minutes.
    */
    'auto_close_minutes' => 30,

    /*
    | Only track internal team members (@5core.com or show_in_salary users).
    */
    'internal_email_domain' => '@5core.com',

    /*
    | Emails that always see the Attendance menu (even if not @5core.com).
    */
    'menu_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software@5core.com',
        'software5@5core.com',
    ],

    /*
    | AI model for misuse analysis.
    */
    'ai_model' => env('ATTENDANCE_AI_MODEL', 'gpt-4o-mini'),

    /*
    | Desktop agent
    */
    'agent_version' => '1.0.0',
    'screenshots_enabled' => env('ATTENDANCE_SCREENSHOTS_ENABLED', true),
    'screenshot_interval_seconds' => (int) env('ATTENDANCE_SCREENSHOT_INTERVAL', 300),
    'screenshot_max_kb' => (int) env('ATTENDANCE_SCREENSHOT_MAX_KB', 5120),
    'screenshot_disk' => 'attendance',
    'require_desktop_agent' => env('ATTENDANCE_REQUIRE_DESKTOP_AGENT', false),

    /*
    | App names treated as productive / unproductive for scoring.
    */
    'productive_apps' => ['code', 'excel', 'winword', 'powerpnt', 'figma', 'slack', 'teams', 'chrome', 'msedge', 'firefox'],
    'unproductive_apps' => ['steam', 'spotify', 'netflix', 'discord', 'epicgameslauncher'],

];
