<?php

return [

    /*
    | Automatic Wayfair price-file upload.
    | Prices come from the existing Wayfair pricing page (S PRC / sprice).
    | Credentials and URLs are never hard-coded.
    */

    'enabled' => filter_var(env('WAYFAIR_UPLOAD_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // browser | api | sftp | mock
    // browser is the Partner Home path. api/sftp stay inactive until their URLs are set.
    // mock is for PHPUnit only.
    'mode' => env('WAYFAIR_UPLOAD_MODE', 'browser'),

    'portal_url' => env('WAYFAIR_PORTAL_URL', 'https://partners.wayfair.com'),

    // Partner Home → Pricing Home → New Cost Change → Select Products.
    // The projectId changes every time a new cost-change project is started.
    'upload_url' => env('WAYFAIR_UPLOAD_URL', ''),

    // Exported "Export Product Spreadsheet". Import Product Spreadsheet expects this workbook.
    // Only the New Base Cost column (BaseCost) is written. Pre-filled columns stay as exported.
    'template' => env('WAYFAIR_COST_CHANGE_TEMPLATE', resource_path('wayfair/cost-change-template.xlsx')),

    // Optional page used to read processing status after the file is accepted.
    'status_url' => env('WAYFAIR_STATUS_URL', ''),

    'username' => env('WAYFAIR_USERNAME', ''),
    'password' => env('WAYFAIR_PASSWORD', ''),

    'upload_timeout' => (int) env('WAYFAIR_UPLOAD_TIMEOUT', 120),
    'max_retries' => (int) env('WAYFAIR_UPLOAD_MAX_RETRIES', 3),
    'retry_delay' => (int) env('WAYFAIR_UPLOAD_RETRY_DELAY', 60),
    'job_timeout' => (int) env('WAYFAIR_UPLOAD_JOB_TIMEOUT', 240),
    'status_check_delay' => (int) env('WAYFAIR_STATUS_CHECK_DELAY', 180),
    'stuck_after' => (int) env('WAYFAIR_UPLOAD_STUCK_AFTER', 300),

    // App timezone (config/app.php, currently America/Los_Angeles).
    'schedule_time' => env('WAYFAIR_PRICE_UPLOAD_TIME', '05:00'),

    /*
    | sprice is the calculated S PRC already shown on /wayfair-pricing.
    | It is written into New Base Cost on the Partner Home cost-change workbook.
    */
    'price_field' => env('WAYFAIR_UPLOAD_PRICE_FIELD', 'sprice'),

    /*
    | Default true: the existing Wayfair push (updatePrice / sprc-dil) sends
    | SKU prices that changed, and a no-change day must not upload.
    | Set false only if Wayfair requires the full calculated catalog every day.
    | An identical checksum is still not uploaded twice unless --force is used.
    */
    'only_changed' => filter_var(env('WAYFAIR_UPLOAD_ONLY_CHANGED', true), FILTER_VALIDATE_BOOLEAN),

    // Empty disables the guard. Example: 25 blocks a run when more than 25% of SKUs differ.
    'max_price_change_percent' => (($raw = env('WAYFAIR_MAX_PRICE_CHANGE_PERCENT')) === null || $raw === '')
        ? null
        : (float) $raw,

    // Never mark SUCCESS just because the file was submitted, unless this is turned on.
    'treat_acceptance_as_success' => filter_var(env('WAYFAIR_TREAT_UPLOAD_ACCEPTANCE_AS_SUCCESS', false), FILTER_VALIDATE_BOOLEAN),

    'queue' => env('WAYFAIR_UPLOAD_QUEUE', 'wayfair-upload'),

    'outgoing_directory' => 'wayfair/outgoing',
    'error_directory' => 'wayfair/errors',

    'node_binary' => env('WAYFAIR_NODE_BINARY', 'node'),
    'browser_script' => env('WAYFAIR_BROWSER_SCRIPT', base_path('scripts/wayfair/upload-price-file.js')),

    'selectors' => [
        'username' => env('WAYFAIR_LOGIN_USER_SELECTOR', 'input[type="email"], input[name="email"], input[name="username"]'),
        'password' => env('WAYFAIR_LOGIN_PASSWORD_SELECTOR', 'input[type="password"]'),
        'submit_login' => env('WAYFAIR_LOGIN_SUBMIT_SELECTOR', 'button[type="submit"]'),
        'file' => env('WAYFAIR_FILE_INPUT_SELECTOR', 'input[type="file"]'),
        'import_button' => env('WAYFAIR_IMPORT_BUTTON_TEXT', 'Import Product Spreadsheet'),
        'submit_upload' => env('WAYFAIR_UPLOAD_SUBMIT_SELECTOR', 'button[type="submit"]'),
    ],

    'confirmation_text' => env('WAYFAIR_UPLOAD_CONFIRMATION_TEXT', 'success,submitted,upload complete,file received,imported'),

    'sftp' => [
        'host' => env('WAYFAIR_SFTP_HOST', ''),
        'port' => (int) env('WAYFAIR_SFTP_PORT', 22),
        'username' => env('WAYFAIR_SFTP_USERNAME', ''),
        'password' => env('WAYFAIR_SFTP_PASSWORD', ''),
        'root' => env('WAYFAIR_SFTP_ROOT', '/'),
    ],

    // PHPUnit only: success | fail | permanent | auth | processing
    'mock_result' => env('WAYFAIR_UPLOAD_MOCK_RESULT', 'success'),
];
