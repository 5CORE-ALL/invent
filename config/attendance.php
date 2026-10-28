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
        'tech-support@5core.com',
        'software5@5core.com',
    ],

    /*
    | Emails that can manage policies and review AI flags.
    */
    'admin_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'tech-support@5core.com',
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
    | Auto clock-out for inactivity: when the machine has had no keyboard / mouse input for this many
    | minutes while clocked in, the session is ended. The end time is backdated to the last real
    | activity and the trailing idle stretch is not counted. 0 disables it.
    | The desktop app warns `auto_logout_warn_seconds` before it happens.
    | Note: a long meeting or video call with no input will also trigger it.
    */
    'auto_logout_idle_minutes' => (int) env('ATTENDANCE_AUTO_LOGOUT_IDLE_MINUTES', 10),
    'auto_logout_warn_seconds' => (int) env('ATTENDANCE_AUTO_LOGOUT_WARN_SECONDS', 60),

    /*
    | Auto-close sessions with no heartbeat for this many minutes.
    */
    'auto_close_minutes' => 30,

    /*
    | Track internal team members (@5core.com, show_in_salary users, and directors).
    */
    'internal_email_domain' => '@5core.com',

    /*
    | Emails that always see the Attendance menu (even if not @5core.com).
    */
    'menu_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'tech-support@5core.com',
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
    'agent_version' => '1.4.8',
    'agent_installer_path' => env('ATTENDANCE_AGENT_INSTALLER_PATH'),
    'agent_download_filename' => env('ATTENDANCE_AGENT_DOWNLOAD_FILENAME', '5Core-Attendance-Setup.exe'),
    'agent_mac_installer_path' => env('ATTENDANCE_AGENT_MAC_INSTALLER_PATH'),
    'agent_mac_arm64_path' => env('ATTENDANCE_AGENT_MAC_ARM64_PATH'),
    'agent_mac_x64_path' => env('ATTENDANCE_AGENT_MAC_X64_PATH'),
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
    | Fraud checkup: runs every fraud check on recent days and raises a red triangle beside the
    | executive's name while there are open (not yet reviewed / dismissed) alerts.
    */
    'checkup' => [
        // Open alerts from the last N days count towards the triangle.
        'lookback_days' => (int) env('ATTENDANCE_CHECKUP_LOOKBACK_DAYS', 7),
        // Lowest severity that shows the triangle: low | medium | high. Low alerts stay visible
        // on the employee page only (e.g. a single network hiccup should not shame anyone).
        'alert_min_severity' => env('ATTENDANCE_CHECKUP_MIN_SEVERITY', 'medium'),
        // How many recent days the scheduled run re-checks (today counts as 1).
        'run_days' => (int) env('ATTENDANCE_CHECKUP_RUN_DAYS', 2),
        // A single session this long with (almost) no break: machine left running / never clocked out.
        'marathon_hours' => (float) env('ATTENDANCE_CHECKUP_MARATHON_HOURS', 14),
        // Fewer screenshots than this share of the expected count: screen capture blocked or disabled.
        'min_screenshot_ratio' => (float) env('ATTENDANCE_CHECKUP_MIN_SCREENSHOT_RATIO', 0.4),
    ],

    /*
    | Timezone that policy start times (e.g. 09:30) are written in. Clock-ins are stored in the
    | server timezone (Pacific), so lateness is evaluated in this zone instead. A policy can override
    | it with its own `timezone` column (e.g. Asia/Shanghai for a China team).
    */
    'policy_timezone' => env('ATTENDANCE_POLICY_TIMEZONE', 'Asia/Kolkata'),

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

    /*
    | Heuristics used by the daily misuse analysis to detect faked activity.
    | A session needs at least `min_hours` of heartbeats before it can be judged.
    */
    'simulation' => [
        'min_hours' => (float) env('ATTENDANCE_SIM_MIN_HOURS', 2),
        // Share of heartbeats that were idle below which a long session looks implausible for a human.
        'min_idle_share_percent' => (float) env('ATTENDANCE_SIM_MIN_IDLE_PERCENT', 2),
        // ...AND the longest stretch without any input must also stay at or below this many seconds.
        // Low idle share alone is normal (most employees never reach the 120s idle threshold); a
        // jiggler that nudges the mouse about once a minute caps the OS idle reading near 60s.
        'max_os_idle_ceiling_seconds' => (int) env('ATTENDANCE_SIM_MAX_IDLE_CEILING', 65),
        // Keystrokes + clicks per "working" hour below which a long session looks like no real work
        // (only judged where the agent's input hook was verified working).
        'min_inputs_per_hour' => (float) env('ATTENDANCE_SIM_MIN_INPUTS_PER_HOUR', 30),
    ],

];
