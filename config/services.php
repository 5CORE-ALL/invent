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

    /*
    |--------------------------------------------------------------------------
    | Shopify Main Store (5-core.myshopify.com)
    |--------------------------------------------------------------------------
    */
    'shopify' => [
        'api_key' => env('SHOPIFY_API_KEY'),
        'password' => env('SHOPIFY_PASSWORD'),
        'store_url' => env('SHOPIFY_STORE_URL'),
        'access_token' => env('SHOPIFY_ACCESS_TOKEN', env('SHOPIFY_PASSWORD')),
        'inventory_location_id' => env('SHOPIFY_INVENTORY_LOCATION_ID'),
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shopify 5Core Store
    |--------------------------------------------------------------------------
    */
    'shopify_5core' => [
        'domain' => env('SHOPIFY_5CORE_DOMAIN'),
        'api_key' => env('SHOPIFY_5CORE_API_KEY'),
        'password' => env('SHOPIFY_5CORE_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shopify ProLightSounds Store (PLS / 5core-wholesale)
    |--------------------------------------------------------------------------
    */
    'prolightsounds' => [
        'api_key' => env('PROLIGHTSOUNDS_SHOPIFY_API_KEY'),
        'password' => env('PROLIGHTSOUNDS_SHOPIFY_PASSWORD'),
        'domain' => env('PROLIGHTSOUNDS_SHOPIFY_DOMAIN'),
        'store_url' => env('PROLIGHTSOUNDS_SHOPIFY_DOMAIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shopify Business 5Core Store
    |--------------------------------------------------------------------------
    */
    'shopify_b5c' => [
        'domain' => env('BUSINESS_5CORE_SHOPIFY_DOMAIN'),
        'api_key' => env('BUSINESS_5CORE_SHOPIFY_API_KEY'),
        'access_token' => env('BUSINESS_5CORE_SHOPIFY_ACCESS_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google (OAuth)
    |--------------------------------------------------------------------------
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'reverb' => [
        'token' => env('REVERB_TOKEN'),
        'auto_push_debug' => env('REVERB_AUTO_PUSH_DEBUG', false),
        'webhook_secret' => env('REVERB_WEBHOOK_SECRET'),
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

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4
    |--------------------------------------------------------------------------
    */
    'ga4' => [
        'property_id' => env('GA4_PROPERTY_ID'),
        'client_id' => env('GA4_CLIENT_ID'),
        'client_secret' => env('GA4_CLIENT_SECRET'),
        'refresh_token' => env('GA4_REFRESH_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Apps Script / Sheets
    |--------------------------------------------------------------------------
    */
    'google_apps_script' => [
        'export_url' => env('GOOGLE_APPS_SCRIPT_EXPORT_URL', ''),
        'verification_adjustment_sheet_id' => env('GOOGLE_SHEETS_VERIFICATION_ADJUSTMENT_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb
    |--------------------------------------------------------------------------
    */
    'reverb' => [
        'token' => env('REVERB_TOKEN'),
    ],

    'topdawg' => [
        'base_url' => env('TOPDAWG_API_BASE_URL', 'https://topdawg.com/supplier/api'),
        'token' => env('TOPDAWG_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Amazon SP-API
    |--------------------------------------------------------------------------
    */
    'amazon_sp' => [
        'client_id' => env('SPAPI_CLIENT_ID'),
        'client_secret' => env('SPAPI_CLIENT_SECRET'),
        'refresh_token' => env('SPAPI_REFRESH_TOKEN'),
        'region' => env('SPAPI_REGION', 'us-east-1'),
        'marketplace_id' => env('SPAPI_MARKETPLACE_ID', 'ATVPDKIKX0DER'),
        'endpoint' => env('SPAPI_ENDPOINT', 'https://sellingpartnerapi-na.amazon.com'),
        'seller_id' => env('AMAZON_SELLER_ID'),
        'aws_access_key' => env('AWS_ACCESS_KEY_ID'),
        'aws_secret_key' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Amazon Advertising API
    |--------------------------------------------------------------------------
    */
    'amazon_ads' => [
        'client_id' => env('AMAZON_ADS_CLIENT_ID'),
        'client_secret' => env('AMAZON_ADS_CLIENT_SECRET'),
        'refresh_token' => env('AMAZON_ADS_REFRESH_TOKEN'),
        'profile_ids' => env('AMAZON_ADS_PROFILE_IDS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | eBay Account 1 (AmarjitK-Products) / Legacy
    |--------------------------------------------------------------------------
    */
    'ebay' => [
        'app_id' => env('EBAY_APP_ID'),
        'cert_id' => env('EBAY_CERT_ID'),
        'dev_id' => env('EBAY_DEV_ID'),
        'refresh_token' => env('EBAY_REFRESH_TOKEN'),
        'trading_api_endpoint' => env('EBAY_TRADING_API_ENDPOINT', 'https://api.ebay.com/ws/api.dll'),
        'site_id' => env('EBAY_SITE_ID', 0),
        'compat_level' => env('EBAY_COMPAT_LEVEL', '1189'),
        'base_url' => env('EBAY_BASE_URL', 'https://api.ebay.com/'),
        /** Item Specific names for Bullet Points Master (Trading API ReviseItem). Override per locale/category if needed. */
        'bullet_aspect_names' => [
            'Bullet Point 1',
            'Bullet Point 2',
            'Bullet Point 3',
            'Bullet Point 4',
            'Bullet Point 5',
        ],
        /** Item specific names (case-insensitive) that must never be dropped when merging bullet aspects (MPN, etc.). */
        'preserve_item_specific_names' => [
            'MPN', 'Manufacturer Part Number', 'UPC', 'EAN', 'ISBN', 'GTIN', 'Brand', 'Part Number',
        ],
        /** If GetItem omits MPN in ItemSpecifics, set e.g. "Does not apply" (category-dependent). Leave empty to disable. */
        'mpn_fallback_value' => env('EBAY_MPN_FALLBACK_VALUE', ''),
        /** If Brand is required but missing from ItemSpecifics, use this (override with EBAY_BRAND_FALLBACK_VALUE). */
        'brand_fallback_value' => env('EBAY_BRAND_FALLBACK_VALUE', '5 Core'),
    ],
    'ebay1' => [
        'app_id' => env('EBAY_APP_ID'),
        'cert_id' => env('EBAY_CERT_ID'),
        'dev_id' => env('EBAY_DEV_ID'),
        'refresh_token' => env('EBAY_REFRESH_TOKEN'),
        'scope' => env('EBAY_SCOPE', 'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly https://api.ebay.com/oauth/api_scope/sell.inventory'),
        'base_url' => env('EBAY_BASE_URL', 'https://api.ebay.com/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | eBay Account 2
    |--------------------------------------------------------------------------
    */
    'ebay2' => [
        'app_id' => env('EBAY2_APP_ID'),
        'cert_id' => env('EBAY2_CERT_ID'),
        'dev_id' => env('EBAY2_DEV_ID'),
        'refresh_token' => env('EBAY2_REFRESH_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | eBay Account 3
    |--------------------------------------------------------------------------
    */
    'ebay3' => [
        'app_id' => env('EBAY_3_APP_ID'),
        'cert_id' => env('EBAY_3_CERT_ID'),
        'dev_id' => env('EBAY_3_DEV_ID'),
        'refresh_token' => env('EBAY_3_REFRESH_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Walmart
    |--------------------------------------------------------------------------
    */
    'walmart' => [
        'client_id' => env('WALMART_CLIENT_ID'),
        'client_secret' => env('WALMART_CLIENT_SECRET'),
        'api_endpoint' => env('WALMART_API_ENDPOINT', 'https://marketplace.walmartapis.com'),
        'marketplace_id' => env('WALMART_MARKETPLACE_ID', 'WMTMP'),
        'channel_type' => env('WALMART_CHANNEL_TYPE', '0f3e4dd4-0514-4346-b39d-af0e00ea066d'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wayfair
    |--------------------------------------------------------------------------
    */
    'wayfair' => [
        'client_id' => env('WAYFAIR_CLIENT_ID'),
        'client_secret' => env('WAYFAIR_CLIENT_SECRET'),
        'audience' => env('WAYFAIR_AUDIENCE'),
        'supplier_id' => env('WAYFAIR_SUPPLIER_ID', '2603'),
        // Scope for catalog/title updates. If you get "Access Denied", run: php artisan wayfair:test-scopes
        'catalog_scope' => env('WAYFAIR_CATALOG_SCOPE', ''),
        // Product Catalog API (GraphQL) for title updates - use updateMarketSpecificCatalogItems mutation
        'product_catalog_graphql_url' => env('WAYFAIR_API_URL', 'https://api.wayfair.io/v1/product-catalog-api/graphql'),
        'brand' => env('WAYFAIR_BRAND', 'WAYFAIR'),
        'country' => env('WAYFAIR_COUNTRY', 'UNITED_STATES'),
        'locale' => env('WAYFAIR_LOCALE', 'en-US'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Temu
    |--------------------------------------------------------------------------
    */
    'temu' => [
        'app_key' => env('TEMU_APP_KEY'),
        'secret_key' => env('TEMU_SECRET_KEY'),
        'access_token' => env('TEMU_ACCESS_TOKEN'),
        /** goodsBasic key for bullet-style copy (not long goodsDesc). Override via TEMU_GOODS_SUMMARY_FIELD if Temu schema differs. */
        'goods_summary_field' => env('TEMU_GOODS_SUMMARY_FIELD', 'goodsSummary'),
        /** `string` = newline-separated text; `array` = JSON array of bullet lines (if your Temu app expects list form). */
        'goods_summary_format' => env('TEMU_GOODS_SUMMARY_FORMAT', 'string'),
        /** If false, bullet updates send only goodsBasic + goodsId (avoids skuList overwriting other fields). Set true if Temu requires skuList on partial update. */
        'bullet_update_include_sku_list' => filter_var(env('TEMU_BULLET_INCLUDE_SKU_LIST', false), FILTER_VALIDATE_BOOLEAN),
        // API type for updating goods title (per official docs: use partial update for efficiency)
        'goods_update_type' => env('TEMU_GOODS_UPDATE_TYPE', 'bg.local.goods.partial.update'),
        // SKU list field for title update. Official docs use skuList (https://partner-eu.temu.com/documentation)
        'update_sku_list_field' => env('TEMU_UPDATE_SKU_LIST_FIELD', 'skuList'),
        'goods_basic_field' => env('TEMU_GOODS_BASIC_FIELD', 'goodsBasic'),
        'list_price_field' => env('TEMU_LIST_PRICE_FIELD', 'listPrice'),
        'sku_id_field' => env('TEMU_SKU_ID_FIELD', 'skuId'),
        'sku_code_field' => env('TEMU_SKU_CODE_FIELD', 'outSkuSn'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shein
    |--------------------------------------------------------------------------
    */
    'shein' => [
        'app_id' => env('SHEIN_APP_ID'),
        'app_secret' => env('SHEIN_APP_SECRET'),
        'open_key_id' => env('SHEIN_OPEN_KEY_ID'),
        'secret_key' => env('SHEIN_SECRET_KEY'),
        'app_s' => env('SHEIN_APP_S'),
        'base_url' => env('SHEIN_BASE_URL', 'https://openapi.sheincorp.com'),
        /** Product title update — POST JSON body (skuCode + productName) */
        'product_update_path' => env('SHEIN_PRODUCT_UPDATE_PATH', '/open-api/openapi-business-backend/product/update'),
        /** Max characters sent for productName (SHEIN uses short titles; default 80) */
        'title_max_length' => (int) env('SHEIN_TITLE_MAX_LENGTH', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | Doba
    |--------------------------------------------------------------------------
    */
    'doba' => [
        'app_key' => env('DOBA_APP_KEY'),
        'private_key' => env('DOBA_PRIVATE_KEY'),
        'public_key' => env('DOBA_PUBLIC_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Faire
    |--------------------------------------------------------------------------
    */
    'faire' => [
        'app_id' => env('FAIRE_APP_ID'),
        'app_secret' => env('FAIRE_APP_SECRET'),
        'redirect_url' => env('FAIRE_REDIRECT_URL'),
        'bearer_token' => env('FAIRE_BEARER_TOKEN'),
        'access_token' => env('FAIRE_ACCESS_TOKEN'),
        'token' => env('FAIRE_TOKEN'),
        'refresh_token' => env('FAIRE_REFRESH_TOKEN'),
        'auth_code' => env('FAIRE_AUTH_CODE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AliExpress
    |--------------------------------------------------------------------------
    */
    'aliexpress' => [
        'app_key' => env('ALIEXPRESS_APP_KEY'),
        'app_secret' => env('ALIEXPRESS_APP_SECRET'),
        /** OAuth token value (sent as `session` for dropshipping /sync API) */
        'access_token' => env('ALIEXPRESS_ACCESS_TOKEN'),
        /**
         * Dropshipping API POST URL (must end with /sync).
         * Default: https://api-sg.aliexpress.com/sync
         */
        'api_base' => env('ALIEXPRESS_API_BASE', 'https://api-sg.aliexpress.com/sync'),
        /** Public param name for the token: dropshipping uses `session` */
        'token_param' => env('ALIEXPRESS_TOKEN_PARAM', 'session'),
        'partner_id' => env('ALIEXPRESS_PARTNER_ID', 'iop-sdk-php'),
        'format' => env('ALIEXPRESS_FORMAT', 'json'),
        /** String "true"/"false" in form body */
        'simplify' => env('ALIEXPRESS_SIMPLIFY', 'true'),
        /**
         * First segment of the HMAC sign string (before sorted key+value pairs).
         * Must match the API path: /sync for dropshipping.
         */
        'sign_path' => env('ALIEXPRESS_SIGN_PATH', '/sync'),
        /**
         * Official IOP SDK sends system params on the URL query and API params as multipart POST body.
         * Use "form" only if your gateway explicitly expects application/x-www-form-urlencoded body only.
         */
        'transport' => env('ALIEXPRESS_TRANSPORT', 'iop'),
        /** "iop" = time()."000" like SDK msectime(); "ms" = round(microtime(true)*1000) */
        'timestamp_style' => env('ALIEXPRESS_TIMESTAMP_STYLE', 'iop'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TikTok Shop
    |--------------------------------------------------------------------------
    */
    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
        'auth_base' => env('TIKTOK_AUTH_BASE', 'https://auth.tiktok-shops.com'),
        'api_base' => env('TIKTOK_API_BASE', 'https://open-api.tiktokglobalshop.com'),
        'shop_id' => env('TIKTOK_SHOP_ID'),
        'app_key' => env('TIKTOK_APP_KEY', env('TIKTOK_CLIENT_KEY')),
        'app_secret' => env('TIKTOK_APP_SECRET', env('TIKTOK_CLIENT_SECRET')),
        'access_token' => env('TIKTOK_ACCESS_TOKEN'),
        'refresh_token' => env('TIKTOK_REFRESH_TOKEN'),
        'app_id' => env('TIKTOK_APP_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta / Facebook Ads
    |--------------------------------------------------------------------------
    */
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'ad_account_id' => env('META_AD_ACCOUNT_ID'),
        'api_version' => env('META_API_VERSION', 'v21.0'),
    ],

    'facebook' => [
        'access_token' => env('FACEBOOK_ACCESS_TOKEN'),
        'ad_account_id' => env('FACEBOOK_AD_ACCOUNT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SerpApi
    |--------------------------------------------------------------------------
    */
    'serpapi' => [
        'key' => env('SERPAPI_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Services
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'claude' => [
        'key' => env('CLAUDE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JungleScout
    |--------------------------------------------------------------------------
    */
    'junglescout' => [
        'key' => env('JUNGLESCOUT_API_KEY'),
        'key_with_title' => env('JUNGLESCOUT_API_KEY_WITH_TITLE'),
        'timeout' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Manager / Admin
    |--------------------------------------------------------------------------
    */
    'taskmanager' => [
        'url' => env('TASKMANAGER_URL'),
        'api_key' => env('TASKMANAGER_API_KEY'),
    ],

    'admin' => [
        'email' => env('ADMIN_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 5Core Business
    |--------------------------------------------------------------------------
    */
    '5core' => [
        'domains' => [
            'IT' => ['keywords' => ['server', 'database', 'vpn', 'login', 'password', 'email', 'network', 'software', 'hardware', 'printer', 'wifi', 'ssl', 'certificate']],
            'Sales' => ['keywords' => ['client', 'deal', 'invoice', 'payment', 'customer', 'lead', 'sale', 'discount', 'price', 'order']],
            'HR' => ['keywords' => ['leave', 'attendance', 'salary', 'holiday', 'hiring', 'interview', 'hr', 'bonus', 'expense']],
            'Marketing' => ['keywords' => ['campaign', 'social media', 'ads', 'content', 'seo', 'brand', 'marketing', 'promotion']],
            'General' => ['keywords' => []],
        ],
        'senior_email' => env('FIVECORE_SENIOR_EMAIL', 'president@5core.com'),
        'escalation_reply_url' => env('FIVECORE_ESCALATION_REPLY_URL', 'https://inventory.5coremanagement.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp (Gupshup)
    |--------------------------------------------------------------------------
    | Two modes:
    | 1) Wa/Sm API: GUPSHUP_API_KEY + GUPSHUP_SOURCE (+ GUPSHUP_SRC_NAME).
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
            'template_id_task_assigned' => env('GUPSHUP_TEMPLATE_ID_TASK_ASSIGNED') ?? env('GUPSHUP_TEMPLATE_ID'),
            'template_id_task_done' => env('GUPSHUP_TEMPLATE_ID_DONE_TASK') ?? env('GUPSHUP_TEMPLATE_ID'),
            'template_id_task_rework' => env('GUPSHUP_TEMPLATE_ID_REWORK_TASK') ?? env('GUPSHUP_TEMPLATE_ID'),
            'template_id_task_updated' => env('GUPSHUP_TEMPLATE_ID_UPDATE_TASK') ?? env('GUPSHUP_TEMPLATE_ID'),
            'test_destination' => env('GUPSHUP_TEST_DESTINATION'),
        ],
        'use_template_for_tasks' => env('WHATSAPP_USE_TEMPLATE_FOR_TASKS', true),
    ],
];
