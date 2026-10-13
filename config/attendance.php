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
        'software@5core.com',
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
    /*
    | Fallback only. The version actually advertised comes from the release manifest
    | written by `php artisan attendance:agent-publish`, so the portal can never offer
    | a build that the download link is unable to serve.
    */
    'agent_version' => '1.5.0',

    /*
    | First agent build that can stream live screen video. Older installs can never
    | show video on the team wall no matter how long the viewer waits.
    */
    'live_min_version' => '1.4.0',

    /*
    | Encoded video stream (WebM over MSE). Older builds only know how to post
    | single JPEG frames, so the viewer keeps the still-image path for them.
    */
    'live_stream_enabled' => env('ATTENDANCE_LIVE_STREAM', true),
    'live_stream_min_version' => '1.5.0',
    'live_stream_fps' => (int) env('ATTENDANCE_LIVE_STREAM_FPS', 20),
    'live_stream_width' => (int) env('ATTENDANCE_LIVE_STREAM_WIDTH', 1280),
    'live_stream_bitrate' => (int) env('ATTENDANCE_LIVE_STREAM_BITRATE', 1200000),
    /*
    | Milliseconds of video per uploaded chunk. Smaller means lower latency but
    | more requests; the viewer is always at least one chunk behind.
    */
    'live_stream_chunk_ms' => (int) env('ATTENDANCE_LIVE_STREAM_CHUNK_MS', 600),
    /*
    | How many chunks stay replayable. MediaRecorder output is a single continuous
    | stream, so a viewer that falls past this window cannot be fed contiguously
    | and has to resync.
    */
    'live_stream_keep_chunks' => (int) env('ATTENDANCE_LIVE_STREAM_KEEP', 60),
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
