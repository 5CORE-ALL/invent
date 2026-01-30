<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],
    'macy' => [
        'client_id' => env('MACY_CLIENT_ID'),
        'client_secret' => env('MACY_CLIENT_SECRET'),
        'company_id' => env('MACY_COMPANY_ID'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'junglescout' => [
        'key' => env('JUNGLESCOUT_API_KEY'),
        'timeout' => 15,
    ],

    'shopify' => [
        'api_key' => env('SHOPIFY_API_KEY'),
        'password' => env('SHOPIFY_PASSWORD'),
        'store_url' => env('SHOPIFY_STORE_URL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'reverb' => [
        'token' => env('REVERB_TOKEN'),
    ],

    'macy' => [
        'client_id' => env('MACY_CLIENT_ID'),
        'client_secret' => env('MACY_CLIENT_SECRET'),
        'company_id' => env('MACY_COMPANY_ID'),
    ],

    'wayfair' => [
        'client_id' => env('WAYFAIR_CLIENT_ID'),
        'client_secret' => env('WAYFAIR_CLIENT_SECRET'),
        'audience' => env('WAYFAIR_AUDIENCE'),
    ],

    'google_ads' => [
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'ad_account_id' => env('META_AD_ACCOUNT_ID'),
        'api_version' => env('META_API_VERSION', 'v21.0'),
    ],

    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
        'auth_base' => env('TIKTOK_AUTH_BASE', 'https://auth.tiktok-shops.com'),
        'api_base' => env('TIKTOK_API_BASE', 'https://open-api.tiktokglobalshop.com'),
        'shop_id' => env('TIKTOK_SHOP_ID'),
    ],

    'serpapi' => [
        'key' => env('SERPAPI_KEY'),
    ],

    /*
    | Task Manager WhatsApp (Gupshup). Two modes:
    | 1) Wa/Sm API: GUPSHUP_API_KEY + GUPSHUP_SOURCE (+ GUPSHUP_SRC_NAME). Endpoint: wa/api/v1/msg.
    | 2) Partner v3: GUPSHUP_APP_ID + GUPSHUP_ACCESS_TOKEN + GUPSHUP_SOURCE.
    | Set WHATSAPP_ENABLED=true. Users need `phone` (digits only) for delivery.
    */
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'provider' => env('WHATSAPP_PROVIDER', 'gupshup'),
        'gupshup' => [
            'api_key' => env('GUPSHUP_API_KEY'),
            'source' => env('GUPSHUP_SOURCE'),
            'src_name' => env('GUPSHUP_SRC_NAME', ''),
            'app_id' => env('GUPSHUP_APP_ID'),
            'access_token' => env('GUPSHUP_ACCESS_TOKEN'),
            'api_base' => env('GUPSHUP_API_BASE', 'https://partner.gupshup.io/partner/app'),
            'wa_api_base' => env('GUPSHUP_WA_API_BASE', 'https://api.gupshup.io/sm/api/v1'),
            'template_api_base' => env('GUPSHUP_TEMPLATE_API_BASE', 'https://api.gupshup.io/wa/api/v1'),
            'template_id' => env('GUPSHUP_TEMPLATE_ID'),
            'template_id_task_assigned' => env('GUPSHUP_TEMPLATE_ID_TASK_ASSIGNED', env('GUPSHUP_TEMPLATE_ID')),
            'template_id_task_done' => env('GUPSHUP_TEMPLATE_ID_DONE_TASK', env('GUPSHUP_TEMPLATE_ID')),
            'template_id_task_rework' => env('GUPSHUP_TEMPLATE_ID_REWORK_TASK', env('GUPSHUP_TEMPLATE_ID')),
            'template_id_task_updated' => env('GUPSHUP_TEMPLATE_ID_UPDATE_TASK', env('GUPSHUP_TEMPLATE_ID')),
        ],
        'use_template_for_tasks' => env('WHATSAPP_USE_TEMPLATE_FOR_TASKS', true),
    ],
];
