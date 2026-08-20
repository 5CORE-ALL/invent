<?php

return [

    /*
    | When false, any logged-in user can access attendance menu and pages.
    | Set ATTENDANCE_RESTRICTIONS_ENABLED=true to enforce role/email rules below.
    */
    'restrictions_enabled' => env('ATTENDANCE_RESTRICTIONS_ENABLED', false),

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
    'heartbeat_interval_seconds' => (int) env('ATTENDANCE_HEARTBEAT_INTERVAL', 15),

    /*
    | Legacy desktop popup threshold (v1.2.x and older).
    | Keep very high so installed old builds never show the idle popup.
    | Newer agents (v1.3+) use idle_threshold_seconds instead and show IDLE in-app.
    */
    'idle_prompt_seconds' => (int) env('ATTENDANCE_IDLE_PROMPT_SECONDS', 31536000),

    /*
    | Legacy: unused by v1.3+ (no idle confirm popup).
    */
    'idle_prompt_timeout_seconds' => (int) env('ATTENDANCE_IDLE_PROMPT_TIMEOUT', 60),

    /*
    | System idle threshold (seconds) for marking IDLE in the desktop agent UI.
    */
    'idle_threshold_seconds' => (int) env('ATTENDANCE_IDLE_THRESHOLD', 120),

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
    'agent_version' => '1.4.2',
    'agent_installer_path' => env('ATTENDANCE_AGENT_INSTALLER_PATH'),
    'agent_download_filename' => env('ATTENDANCE_AGENT_DOWNLOAD_FILENAME', '5Core-Attendance-Setup.exe'),
    'screenshots_enabled' => env('ATTENDANCE_SCREENSHOTS_ENABLED', true),
    'screenshot_interval_seconds' => (int) env('ATTENDANCE_SCREENSHOT_INTERVAL', 120),
    'screenshot_max_kb' => (int) env('ATTENDANCE_SCREENSHOT_MAX_KB', 5120),
    'screenshot_grid_limit' => (int) env('ATTENDANCE_SCREENSHOT_GRID_LIMIT', 300),
    'screenshot_page_size' => (int) env('ATTENDANCE_SCREENSHOT_PAGE_SIZE', 48),
    'screenshot_disk' => 'attendance',
    'require_desktop_agent' => env('ATTENDANCE_REQUIRE_DESKTOP_AGENT', true),
    'live_watch_enabled' => env('ATTENDANCE_LIVE_WATCH_ENABLED', true),
    'live_fps' => (int) env('ATTENDANCE_LIVE_FPS', 5),
    'live_quality' => (int) env('ATTENDANCE_LIVE_QUALITY', 55),
    'live_viewer_timeout_seconds' => (int) env('ATTENDANCE_LIVE_VIEWER_TIMEOUT', 20),
    'live_recording_max_kb' => (int) env('ATTENDANCE_LIVE_RECORDING_MAX_KB', 204800),

    /*
    | Team timeline (monitor) defaults.
    */
    'timeline_timezone' => env('ATTENDANCE_TIMELINE_TIMEZONE', 'America/Los_Angeles'),
    'timeline_day_reset' => env('ATTENDANCE_TIMELINE_DAY_RESET'),
    'timeline_day_reset_ist' => env('ATTENDANCE_TIMELINE_DAY_RESET_IST', '12:30'),

    /*
    | App names treated as productive / unproductive for scoring.
    */
    'productive_apps' => ['code', 'excel', 'winword', 'powerpnt', 'figma', 'slack', 'teams', 'chrome', 'msedge', 'firefox'],
    'unproductive_apps' => ['steam', 'spotify', 'netflix', 'discord', 'epicgameslauncher'],

];
