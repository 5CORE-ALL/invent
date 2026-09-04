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
        /** Macy MCM Seller API (PM11/P41/P42) — Shop API Key from macysus-prod.mirakl.net */
        'mcm_api_key' => env('MACY_MCM_API_KEY'),
        'mcm_base_url' => env('MACY_MCM_BASE_URL', 'https://macysus-prod.mirakl.net'),
        'shop_id' => env('MACY_SHOP_ID'),
        /** P41 import CSV column for seller SKU (operator format). */
        'mcm_sku_column' => env('MACY_MCM_SKU_COLUMN', 'shopSku'),
        'mcm_category_column' => env('MACY_MCM_CATEGORY_COLUMN', 'categoryCode'),
        'mcm_import_poll_attempts' => (int) env('MACY_MCM_IMPORT_POLL_ATTEMPTS', 60),
        'mcm_import_poll_delay_seconds' => (int) env('MACY_MCM_IMPORT_POLL_DELAY_SECONDS', 2),
        /**
         * Also push bullets to Macy MCM seller portal (P41) when MACY_MCM_API_KEY is set.
         * Connect upsert runs first; MCM P41 updates Specifications / fnb* in macysus-prod.
         */
        'mcm_bullet_push' => filter_var(env('MACY_MCM_BULLET_PUSH', true), FILTER_VALIDATE_BOOL),
        /** Also push title to Macy MCM seller portal (P41 productName) when MACY_MCM_API_KEY is set. */
        'mcm_title_push' => filter_var(env('MACY_MCM_TITLE_PUSH', true), FILTER_VALIDATE_BOOL),
        /** Also push description to Macy MCM seller portal (P41 productLongDescription) when MACY_MCM_API_KEY is set. */
        'mcm_description_push' => filter_var(env('MACY_MCM_DESCRIPTION_PUSH', true), FILTER_VALIDATE_BOOL),
        /** Also push images to Macy MCM seller portal (P41 mainImage/secondImage/thirdImage) when MACY_MCM_API_KEY is set. */
        'mcm_image_push' => filter_var(env('MACY_MCM_IMAGE_PUSH', true), FILTER_VALIDATE_BOOL),
        /** Request P41 to override MCM protected (manually edited) values — needed for mainImage when locked in seller portal. */
        'mcm_allow_locked_override' => filter_var(env('MACY_MCM_ALLOW_LOCKED_OVERRIDE', false), FILTER_VALIDATE_BOOL),
        'mcm_image_allow_locked_override' => filter_var(env('MACY_MCM_IMAGE_ALLOW_LOCKED_OVERRIDE', true), FILTER_VALIDATE_BOOL),
        /** Null-clear Connect images[] before pushing replacements (avoids PATCH merge leaving stale main image). */
        'connect_image_clear_before_push' => filter_var(env('MACY_CONNECT_IMAGE_CLEAR_BEFORE_PUSH', true), FILTER_VALIDATE_BOOL),
        /** Build P41 rows with PM11 REQUIRED fields + offer/macys_price_data (not bullets-only). */
        'mcm_p41_enriched_row' => filter_var(env('MACY_MCM_P41_ENRICHED_ROW', true), FILTER_VALIDATE_BOOL),
        /**
         * Optional map Connect category id/label → Macy MCM PM11 hierarchy (categoryCode).
         * Live Macy offer/product category takes priority when available.
         */
        'connect_category_to_mcm_hierarchy' => [
            'Microphone Stands' => 'Home Entertainment Accessories',
            '1286' => 'Home Entertainment Accessories',
            'Music Benches & Stools' => 'Home Entertainment Accessories',
            '591' => 'Home Entertainment Accessories',
            'Guitar Stool' => 'Home Entertainment Accessories',
            'Guitar Stools' => 'Home Entertainment Accessories',
        ],
        /** Connect category → Macy taxCode-electronics (avoid Headphones code on passive accessories). */
        'connect_category_to_tax_code' => [
            'Microphone Stands' => '78232',
            '1286' => '78232',
            'Music Benches & Stools' => '78232',
            '591' => '78232',
            'Guitar Stool' => '78232',
            'Guitar Stools' => '78232',
        ],
        /** Defaults for PM11 LIST attributes when not on offer / DB (override in .env). */
        'mcm_p41_defaults' => [
            'warranty' => env('MACY_MCM_DEFAULT_WARRANTY', 'Y'),
            'legalWarnings' => env('MACY_MCM_DEFAULT_LEGAL_WARNINGS', 'None'),
            'origin' => env('MACY_MCM_DEFAULT_ORIGIN', 'Imported'),
            'nrfSizeCode' => env('MACY_MCM_DEFAULT_NRF_SIZE_CODE', '10009'),
            'nrfColorCode' => env('MACY_MCM_DEFAULT_NRF_COLOR_CODE', '001'),
            'taxCode-electronics' => env('MACY_MCM_DEFAULT_TAX_CODE', '78232'),
            'fnb20-117' => env('MACY_MCM_DEFAULT_FNB20_117', 'Home Theater Accessories'),
            'Shipping Dimensions-Length' => env('MACY_MCM_DEFAULT_SHIP_LENGTH', '12'),
            'Shipping Dimensions-Width' => env('MACY_MCM_DEFAULT_SHIP_WIDTH', '8'),
            'Shipping Dimensions-Height' => env('MACY_MCM_DEFAULT_SHIP_HEIGHT', '6'),
            'Shipping Weight' => env('MACY_MCM_DEFAULT_SHIP_WEIGHT', '20'),
        ],
        /** Hierarchy-specific PM11 fallbacks when operator master API returns sparse attributes. */
        'mcm_p41_hierarchy_defaults' => [
            'Computer Accessories' => [
                'taxCode-electronics' => '78232',
                'fnb20-90' => 'Outlet Adapters',
            ],
            'Home Entertainment Accessories' => [
                'taxCode-electronics' => '78232',
                'fnb20-117' => 'Home Theater Accessories',
            ],
        ],
        /** Macy MCM Features and Benefits per-bullet field limit (Specifications tab). */
        'features_benefits_max_length' => (int) env('MACY_FEATURES_BENEFITS_MAX_LENGTH', 254),
        'features_benefits_verify_attempts' => (int) env('MACY_FEATURES_BENEFITS_VERIFY_ATTEMPTS', 4),
        'features_benefits_verify_delay_seconds' => (int) env('MACY_FEATURES_BENEFITS_VERIFY_DELAY_SECONDS', 2),
        /** Mirakl Connect upsertProducts: max once per minute (Catalog API). 0 = disable wait. */
        'upsert_min_interval_seconds' => (int) env('MACY_UPSERT_MIN_INTERVAL_SECONDS', 60),
        /** Optional: null-clear F&B attrs before re-upsert to force MCM sync (2 API calls). */
        'features_benefits_null_clear_on_sync' => filter_var(env('MACY_FB_NULL_CLEAR_ON_SYNC', false), FILTER_VALIDATE_BOOL),
        'features_benefits_null_clear_delay_seconds' => (int) env('MACY_FB_NULL_CLEAR_DELAY_SECONDS', 3),
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
        /** Store hostname only, e.g. your-store.myshopify.com (no https://) */
        'store_url' => env('SHOPIFY_STORE_URL'),
        /** Admin API access token (private app or custom app) */
        'access_token' => env('SHOPIFY_ACCESS_TOKEN', env('SHOPIFY_PASSWORD')),
        /** Admin REST API version path segment, e.g. 2025-01 */
        'api_version' => env('SHOPIFY_API_VERSION', '2025-01'),
        'inventory_location_id' => env('SHOPIFY_INVENTORY_LOCATION_ID'),
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET', env('SHOPIFY_API_SECRET', env('SHOPIFY_SHARED_SECRET'))),
        /** Guzzle timeouts (seconds) */
        'http_timeout' => env('SHOPIFY_HTTP_TIMEOUT', 120),
        'connect_timeout' => env('SHOPIFY_CONNECT_TIMEOUT', 15),
        /** When X-Shopify-Shop-Api-Call-Limit used/max reaches this fraction of max, pause (leaky bucket) */
        'rate_limit_threshold' => env('SHOPIFY_RATE_LIMIT_THRESHOLD', 0.85),
        'rate_limit_pause_seconds' => env('SHOPIFY_RATE_LIMIT_PAUSE', 2),
        /** Retries for 429 / 5xx / connection errors */
        'max_retries' => env('SHOPIFY_MAX_RETRIES', 5),
        /**
         * Shopify sync paginates (orders/customers/products). PHP default max_execution_time is often 30s.
         * After each REST page we call set_time_limit() so the full sync can finish. 0 = unlimited (use with care).
         */
        'sync_page_time_limit_seconds' => (int) env('SHOPIFY_SYNC_PAGE_TIME_LIMIT', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM automation & activity
    |--------------------------------------------------------------------------
    */
    'crm' => [
        /** User id to assign auto follow-ups from new Shopify orders (falls back to first active user) */
        'shopify_new_order_followup_assignee_id' => env('CRM_SHOPIFY_NEW_ORDER_ASSIGNEE_ID'),
        /**
         * When syncing from Shopify Admin REST (SHOPIFY_STORE_URL + SHOPIFY_ACCESS_TOKEN),
         * create a `customers` row for each synced Shopify customer that has an email and no CRM match.
         */
        'shopify_auto_create_customers' => env('CRM_SHOPIFY_AUTO_CREATE_CUSTOMERS', true),
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
        'client_id' => env('PROLIGHTSOUNDS_SHOPIFY_CLIENT_ID', env('PROLIGHTSOUNDS_SHOPIFY_API_KEY')),
        'client_secret' => env('PROLIGHTSOUNDS_SHOPIFY_CLIENT_SECRET'),
        'api_key' => env('PROLIGHTSOUNDS_SHOPIFY_API_KEY'),
        'password' => env('PROLIGHTSOUNDS_SHOPIFY_PASSWORD'),
        'access_token' => env('PROLIGHTSOUNDS_SHOPIFY_ACCESS_TOKEN'),
        'domain' => env('PROLIGHTSOUNDS_SHOPIFY_DOMAIN'),
        'store_url' => env('PROLIGHTSOUNDS_SHOPIFY_DOMAIN'),
    ],

    'prolightsounds_shopify' => [
        'client_id' => env('PROLIGHTSOUNDS_SHOPIFY_CLIENT_ID', env('PROLIGHTSOUNDS_SHOPIFY_API_KEY')),
        'client_secret' => env('PROLIGHTSOUNDS_SHOPIFY_CLIENT_SECRET'),
        'api_key' => env('PROLIGHTSOUNDS_SHOPIFY_API_KEY'),
        'password' => env('PROLIGHTSOUNDS_SHOPIFY_PASSWORD'),
        'access_token' => env('PROLIGHTSOUNDS_SHOPIFY_ACCESS_TOKEN'),
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
        'password' => env('BUSINESS_5CORE_SHOPIFY_PASSWORD'),
        'access_token' => env('BUSINESS_5CORE_SHOPIFY_ACCESS_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | FleetCart website (business5core.com listing prices API)
    |--------------------------------------------------------------------------
    */
    'store' => [
        'url' => env('BUSINESS5CORE_API_URL', env('STORE_API_URL', 'https://business5core.com')),
        'api_key' => env('BUSINESS5CORE_API_KEY', env('STORE_API_KEY', '')),
        'timeout' => (int) env('BUSINESS5CORE_TIMEOUT', env('STORE_API_TIMEOUT', 30)),
        'locale' => env('STORE_API_LOCALE', 'en'),
        'price_update_method' => env('STORE_API_PRICE_UPDATE_METHOD', 'PUT'),
        'price_update_path' => env('STORE_API_PRICE_UPDATE_PATH', '/api/listings/prices/{id}'),
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

    // Separate "Desktop app" OAuth client for the Electron attendance agent
    // (loopback redirect URIs require the Desktop client type, unlike the web client above).
    'google_desktop' => [
        'client_id' => env('GOOGLE_DESKTOP_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DESKTOP_CLIENT_SECRET'),
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
        'share_domain' => env('GOOGLE_SHEETS_SHARE_DOMAIN', '5core.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb — OAuth2 client_credentials at https://reverb.com/oauth/token
    | (optional static REVERB_TOKEN still works if client credentials unset)
    |--------------------------------------------------------------------------
    */
    'reverb' => [
        'client_id' => env('REVERB_CLIENT_ID'),
        'client_secret' => env('REVERB_CLIENT_SECRET'),
        'token' => env('REVERB_TOKEN'),
        'oauth_url' => env('REVERB_OAUTH_URL', 'https://reverb.com/oauth/token'),
        'api_url' => env('REVERB_API_URL', 'https://api.reverb.com/api'),
        'scope' => env('REVERB_SCOPE', 'read_listings write_listings read_orders'),
        'auto_push_debug' => env('REVERB_AUTO_PUSH_DEBUG', false),
        'webhook_secret' => env('REVERB_WEBHOOK_SECRET'),
        /*
        | Base URL Reverb uses to fetch images (HTTPS, no trailing slash). Defaults to APP_URL via Storage URL.
        | Title push does not need this; image push does. If APP_URL is http://localhost, set this to your
        | public site (e.g. https://inventory.example.com) where /storage/... is reachable from the internet.
        */
        'sku_image_public_base_url' => env('REVERB_SKU_IMAGE_PUBLIC_BASE_URL'),
        'shipping_profile_id' => env('REVERB_SHIPPING_PROFILE_ID'),
        'default_category_uuid' => env('REVERB_DEFAULT_CATEGORY_UUID'),
        'default_condition_uuid' => env('REVERB_DEFAULT_CONDITION_UUID'),
    ],

    'topdawg' => [
        'base_url' => env('TOPDAWG_API_BASE_URL', 'https://topdawg.com/supplier/api'),
        'token' => env('TOPDAWG_API_TOKEN'),
    ],

    'bestbuy' => [
        /** Best Buy MCM Seller API (PM11/P41/P42) — Shop API Key from bestbuyus-prod.mirakl.net */
        'mcm_api_key' => env('BESTBUY_MCM_API_KEY'),
        'mcm_base_url' => env('BESTBUY_MCM_BASE_URL', 'https://bestbuyus-prod.mirakl.net'),
        'shop_id' => env('BESTBUY_SHOP_ID'),
        'mcm_sku_column' => env('BESTBUY_MCM_SKU_COLUMN', 'shop-sku'),
        'mcm_import_poll_attempts' => (int) env('BESTBUY_MCM_IMPORT_POLL_ATTEMPTS', 60),
        'mcm_import_poll_delay_seconds' => (int) env('BESTBUY_MCM_IMPORT_POLL_DELAY_SECONDS', 2),
        'mcm_bullet_fallback_codes' => ['bulletPoints'],
        'features_benefits_max_length' => (int) env('BESTBUY_BULLET_MAX_LENGTH', 500),
        'features_benefits_verify_attempts' => (int) env('BESTBUY_BULLET_VERIFY_ATTEMPTS', 4),
        'features_benefits_verify_delay_seconds' => (int) env('BESTBUY_BULLET_VERIFY_DELAY_SECONDS', 2),
        'mcm_title_push' => filter_var(env('BESTBUY_MCM_TITLE_PUSH', true), FILTER_VALIDATE_BOOL),
        'mcm_image_push' => filter_var(env('BESTBUY_MCM_IMAGE_PUSH', true), FILTER_VALIDATE_BOOL),
        'mcm_image_allow_locked_override' => filter_var(env('BESTBUY_MCM_IMAGE_ALLOW_LOCKED_OVERRIDE', true), FILTER_VALIDATE_BOOL),
        'mcm_p41_enriched_row' => filter_var(env('BESTBUY_MCM_P41_ENRICHED_ROW', true), FILTER_VALIDATE_BOOL),
    ],

    'purchasingpower' => [
        'api_key' => env('PURCHASING_POWER_API_KEY'),
        'api_base' => env('PURCHASING_POWER_API_BASE', 'https://api.purchasingpower.com'),
        /** Purchasing Power MCM Seller API (PM11/P41/P42) */
        'mcm_api_key' => env('PURCHASING_POWER_MCM_API_KEY', env('PURCHASING_POWER_API_KEY')),
        'mcm_base_url' => env('PURCHASING_POWER_MCM_BASE_URL', 'https://purchasingpowerus-prod.mirakl.net'),
        'shop_id' => env('PURCHASING_POWER_SHOP_ID'),
        'mcm_sku_column' => env('PURCHASING_POWER_MCM_SKU_COLUMN', 'shop-sku'),
        'mcm_import_poll_attempts' => (int) env('PURCHASING_POWER_MCM_IMPORT_POLL_ATTEMPTS', 60),
        'mcm_import_poll_delay_seconds' => (int) env('PURCHASING_POWER_MCM_IMPORT_POLL_DELAY_SECONDS', 2),
        'mcm_bullet_fallback_codes' => ['bulletPoints'],
        'features_benefits_max_length' => (int) env('PURCHASING_POWER_BULLET_MAX_LENGTH', 500),
        'features_benefits_verify_attempts' => (int) env('PURCHASING_POWER_BULLET_VERIFY_ATTEMPTS', 4),
        'features_benefits_verify_delay_seconds' => (int) env('PURCHASING_POWER_BULLET_VERIFY_DELAY_SECONDS', 2),
        'mcm_title_push' => filter_var(env('PURCHASING_POWER_MCM_TITLE_PUSH', true), FILTER_VALIDATE_BOOL),
        'mcm_p41_enriched_row' => filter_var(env('PURCHASING_POWER_MCM_P41_ENRICHED_ROW', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipment Tracking (multi-carrier aggregator)
    |--------------------------------------------------------------------------
    |
    | Used by the "All Orders" Status column to pull live shipment status from
    | a tracking number across carriers (GOFO, USPS, UPS, FedEx, ...).
    |
    | Default driver is 17TRACK (https://api.17track.net) which natively
    | supports all of the above with a single API key. Get a key from the
    | 17TRACK dashboard -> Settings -> Security and put it in TRACKING_API_KEY.
    |
    | Swap providers by changing TRACKING_PROVIDER and TRACKING_API_BASE.
    |
    */
    'tracking' => [
        'provider'      => env('TRACKING_PROVIDER', '17track'),
        'api_key'       => env('TRACKING_API_KEY', ''),
        'api_base'      => env('TRACKING_API_BASE', 'https://api.17track.net/track/v2.2'),
        // Per-run safety limits so a sync can't blow the provider quota
        'batch_size'    => (int) env('TRACKING_BATCH_SIZE', 40),
        // Upper bound per cron tick; native USPS also capped by remaining hourly budget.
        'max_per_run'   => (int) env('TRACKING_MAX_PER_RUN', 200),
        'http_timeout'  => (int) env('TRACKING_HTTP_TIMEOUT', 30),
        'sleep_ms'      => (int) env('TRACKING_SLEEP_MS', 400),
        // Prefer 17TRACK for scheduled/bulk sync (batch API). Native USPS/UPS are rate-limited.
        'prefer_aggregator' => filter_var(env('TRACKING_PREFER_AGGREGATOR', true), FILTER_VALIDATE_BOOL),
        // After backlog clears: refresh each open tracking about this often per day.
        'target_passes_per_day' => (int) env('TRACKING_TARGET_PASSES_PER_DAY', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | USPS API (OAuth consumer credentials)
    |--------------------------------------------------------------------------
    |
    | Consumer Key / Secret from the USPS Developer Portal app.
    | Callback URL must match the value registered on the USPS app.
    | Default product quota is often ~60 req/hour — use nearly all of it 24/7.
    |
    */
    'usps' => [
        'consumer_key'    => env('USPS_CONSUMER_KEY', ''),
        'consumer_secret' => env('USPS_CONSUMER_SECRET', ''),
        'callback_url'    => env('USPS_CALLBACK_URL', 'http://127.0.0.1:8000/admin'),
        'api_base'        => env('USPS_API_BASE', 'https://apis.usps.com'),
        'max_per_hour'    => (int) env('USPS_MAX_PER_HOUR', 55),
        'min_interval_ms' => (int) env('USPS_MIN_INTERVAL_MS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | UPS API (OAuth client credentials)
    |--------------------------------------------------------------------------
    |
    | Client ID / Secret from the UPS Developer Portal app.
    | Production base: https://onlinetools.ups.com
    | CIE/sandbox base: https://wwwcie.ups.com
    |
    */
    'ups' => [
        'client_id'     => env('UPS_CLIENT_ID', ''),
        'client_secret' => env('UPS_CLIENT_SECRET', ''),
        'api_base'      => env('UPS_API_BASE', 'https://onlinetools.ups.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | FedEx API (OAuth client credentials)
    |--------------------------------------------------------------------------
    |
    | API Key / Secret Key from the FedEx Developer Portal project.
    | Sandbox: https://apis-sandbox.fedex.com
    | Production: https://apis.fedex.com
    |
    */
    'fedex' => [
        'client_id'     => env('FEDEX_CLIENT_ID', ''),
        'client_secret' => env('FEDEX_CLIENT_SECRET', ''),
        'api_base'      => env('FEDEX_API_BASE', 'https://apis-sandbox.fedex.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GOFO Express Open API (Basic Auth)
    |--------------------------------------------------------------------------
    |
    | Username / password from GOFO activation email.
    | Production base: https://dmsapi.gofoexpress.com
    |
    */
    'gofo' => [
        'username'      => env('GOFO_USERNAME', ''),
        'password'      => env('GOFO_PASSWORD', ''),
        'api_base'      => env('GOFO_API_BASE', 'https://dmsapi.gofoexpress.com'),
        'product_code'  => env('GOFO_PRODUCT_CODE', 'GOFO Parcel Pickup'),
        'http_timeout'  => (int) env('GOFO_HTTP_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Veeqo API (private x-api-key)
    |--------------------------------------------------------------------------
    |
    | Header: x-api-key: VEEQO_API_KEY
    | Base:   https://api.veeqo.com
    | Docs:   https://developers.veeqo.com/
    |
    */
    'veeqo' => [
        'api_key'      => env('VEEQO_API_KEY', ''),
        'api_base'     => env('VEEQO_API_BASE', 'https://api.veeqo.com'),
        'http_timeout' => (int) env('VEEQO_HTTP_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | 4Seller ERP (optional — labels bought in 4Seller via GOFO)
    |--------------------------------------------------------------------------
    |
    | 4Seller has no public docs. Labels purchased in 4Seller use GOFO; this
    | app looks up tracking on the GOFO Open API first. Set a 4Seller token
    | only if their ERP API is enabled. Leave empty to skip that extra source.
    |
    */
    'fourseller' => [
        'api_base'     => env('FOURSELLER_API_BASE', 'https://openapi.4seller.com'),
        'access_token' => env('FOURSELLER_ACCESS_TOKEN', env('FOURSELLER_API_TOKEN', '')),
        'app_key'      => env('FOURSELLER_APP_KEY', ''),
        'http_timeout' => (int) env('FOURSELLER_HTTP_TIMEOUT', 20),
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

    // Dedicated SP-API "B2" credentials used ONLY by the Hero Images Master
    // push. Keeping these separate from `amazon_sp` means rotating credentials
    // for one feature does not break the other (e.g. Title Master push).
    // Each SPAPIB2_* env var falls back to its SPAPI_* counterpart when empty,
    // so this block works even if the operator forgets to set the new keys.
    'amazon_sp_b2' => [
        'client_id' => env('SPAPIB2_CLIENT_ID', env('SPAPI_CLIENT_ID')),
        'client_secret' => env('SPAPIB2_CLIENT_SECRET', env('SPAPI_CLIENT_SECRET')),
        'refresh_token' => env('SPAPIB2_REFRESH_TOKEN', env('SPAPI_REFRESH_TOKEN')),
        'region' => env('SPAPIB2_REGION', env('SPAPI_REGION', 'us-east-1')),
        'marketplace_id' => env('SPAPIB2_MARKETPLACE_ID', env('SPAPI_MARKETPLACE_ID', 'ATVPDKIKX0DER')),
        'endpoint' => env('SPAPIB2_ENDPOINT', env('SPAPI_ENDPOINT', 'https://sellingpartnerapi-na.amazon.com')),
        'seller_id' => env('AMAZONB2_SELLER_ID', env('AMAZON_SELLER_ID')),
    ],

    // Public HTTPS host used to serve uploaded hero images to Amazon SP-API
    // (Amazon needs publicly reachable HTTPS URLs). Falls back to REVERB image
    // host (already configured) and finally APP_URL. Useful for local dev:
    // set HERO_PUBLIC_BASE_URL=https://your-tunnel.ngrok.app to push without
    // touching APP_URL.
    'hero_images' => [
        'public_base_url' => env('HERO_PUBLIC_BASE_URL', env('REVERB_SKU_IMAGE_PUBLIC_BASE_URL')),
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
        // Amazon Advertising console deep-links (campaign editor). The console URL uses an
        // account "entityId" (copy it from any campaign URL in advertising.amazon.com). When
        // set, campaign links deep-link straight to the right account.
        'console_base' => env('AMAZON_ADS_CONSOLE_BASE', 'https://advertising.amazon.com'),
        'entity_id' => env('AMAZON_ADS_ENTITY_ID', ''),
        // Default bid applied when pushing/creating keywords across linked campaigns.
        'default_keyword_bid' => env('AMAZON_ADS_DEFAULT_KEYWORD_BID', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | eBay Price Push Microservice (cPanel)
    |--------------------------------------------------------------------------
    |
    | Instead of calling eBay's Trading API directly, this application
    | delegates price pushes to an external cPanel Laravel microservice.
    | The microservice handles eBay OAuth, ReviseFixedPriceItem, retries,
    | and any account-specific restrictions.
    |
    | EBAY_PUSH_MICROSERVICE_URL          – full base URL (no trailing slash)
    |                                        e.g. https://ebay-api.example.com
    | EBAY_PUSH_MICROSERVICE_TOKEN        – shared Bearer token
    | EBAY_PUSH_MICROSERVICE_TIMEOUT      – per-request timeout in seconds
    | EBAY_PUSH_MICROSERVICE_RETRIES      – retry attempts on network/5xx failure
    | EBAY_PUSH_MICROSERVICE_RETRY_DELAY_MS – ms to wait between retry attempts
    |
    */
    'ebay_push_microservice' => [
        'url'            => env('EBAY_PUSH_MICROSERVICE_URL', ''),
        'token'          => env('EBAY_PUSH_MICROSERVICE_TOKEN', ''),
        'timeout'        => (int) env('EBAY_PUSH_MICROSERVICE_TIMEOUT', 60),
        'retries'        => (int) env('EBAY_PUSH_MICROSERVICE_RETRIES', 3),
        'retry_delay_ms' => (int) env('EBAY_PUSH_MICROSERVICE_RETRY_DELAY_MS', 5000),
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
        /** MPN for bullet ReviseItem only when GetItem has no seller SKU; must not duplicate Brand. Prefer leaving empty when SKU exists on listings. */
        'mpn_fallback_value' => env('EBAY_MPN_FALLBACK_VALUE', ''),
        /** If Brand is required but missing from ItemSpecifics, use this (override with EBAY_BRAND_FALLBACK_VALUE). */
        'brand_fallback_value' => env('EBAY_BRAND_FALLBACK_VALUE', '5 Core Inc.'),
        /** Max chars eBay accepts for item-specific Bullet Point values in common categories. */
        'item_specific_bullet_max_length' => (int) env('EBAY_ITEM_SPECIFIC_BULLET_MAX_LENGTH', 65),
        /** Required by some categories when revising item specifics. Override with EBAY_TYPE_FALLBACK_VALUE if needed. */
        'type_fallback_value' => env('EBAY_TYPE_FALLBACK_VALUE', 'Audio Connector'),
        /** Guitar/amp categories may require Amplifier Type when revising bullets (eBay3). */
        'amplifier_type_fallback_value' => env('EBAY_AMPLIFIER_TYPE_FALLBACK_VALUE', 'Guitar Speaker'),
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
        'app_id' => env('EBAY2_APP_ID', env('EBAY_2_APP_ID')),
        'cert_id' => env('EBAY2_CERT_ID', env('EBAY_2_CERT_ID')),
        'dev_id' => env('EBAY2_DEV_ID', env('EBAY_2_DEV_ID')),
        'refresh_token' => env('EBAY2_REFRESH_TOKEN', env('EBAY_2_REFRESH_TOKEN')),
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
    | Newegg Marketplace API
    |--------------------------------------------------------------------------
    |
    | Newegg uses three pieces of identity on (almost) every request:
    |   - SellerID   : your Newegg seller account id (sent as ?sellerid=XXXX)
    |   - API Key    : sent in the `Authorization` header
    |   - Secret Key : sent in the `SecretKey` header
    |
    | NOTE: api.newegg.com is fronted by Cloudflare. Calls must originate from
    | an IP that is whitelisted in the Newegg Seller Portal, otherwise Cloudflare
    | returns a 403 "managed challenge" CAPTCHA page before the request ever
    | reaches the API. Run any test from a whitelisted (production) server.
    |
    */
    'newegg' => [
        'seller_id'   => env('NEWEGG_SELLER_ID'),
        'api_key'     => env('NEWEGG_API_KEY'),
        'secret_key'  => env('NEWEGG_SECRET_KEY'),
        'base_url'    => env('NEWEGG_BASE_URL', 'https://api.newegg.com'),
        'http_timeout'    => (int) env('NEWEGG_HTTP_TIMEOUT', 60),
        'connect_timeout' => (int) env('NEWEGG_CONNECT_TIMEOUT', 15),
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
        'market' => env('WALMART_MARKET', 'us'),
        'global_version' => env('WALMART_GLOBAL_VERSION', '3.1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wayfair
    |--------------------------------------------------------------------------
    */
    'wayfair' => [
        'client_id' => env('WAYFAIR_CLIENT_ID'),
        'client_secret' => env('WAYFAIR_CLIENT_SECRET'),
        // Wayfair's OAuth tenant requires audience; default to the public API audience.
        // Override with WAYFAIR_AUDIENCE only if Wayfair issues a different audience for your client.
        'audience' => env('WAYFAIR_AUDIENCE', 'https://api.wayfair.com/'),
        'http_timeout' => (int) env('WAYFAIR_HTTP_TIMEOUT', 90),
        'connect_timeout' => (int) env('WAYFAIR_CONNECT_TIMEOUT', 30),
        'oauth_retries' => (int) env('WAYFAIR_OAUTH_RETRIES', 3),
        'supplier_id' => env('WAYFAIR_SUPPLIER_ID', '2603'),
        // Scope for catalog/title updates. If you get "Access Denied", run: php artisan wayfair:test-scopes
        'catalog_scope' => env('WAYFAIR_CATALOG_SCOPE', ''),
        // Product Catalog API (GraphQL) for title updates - use updateMarketSpecificCatalogItems mutation
        'product_catalog_graphql_url' => env('WAYFAIR_API_URL', 'https://api.wayfair.io/v1/product-catalog-api/graphql'),
        'brand' => env('WAYFAIR_BRAND', 'WAYFAIR'),
        'country' => env('WAYFAIR_COUNTRY', 'UNITED_STATES'),
        'locale' => env('WAYFAIR_LOCALE', 'en-US'),
        'shopify_source_name' => env('WAYFAIR_SHOPIFY_SOURCE_NAME', 'wayfair'),
        'shopify_source_display_name' => env('WAYFAIR_SHOPIFY_SOURCE_DISPLAY_NAME', 'Wayfair'),
        'shopify_source_url_template' => env('WAYFAIR_SHOPIFY_SOURCE_URL_TEMPLATE', 'https://partners.wayfair.com/d/orders/{order_id}'),
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
        /** Long description field inside goodsBasic (no carousel images — use carouselImageUrlList). */
        'goods_desc_field' => env('TEMU_GOODS_DESC_FIELD', 'goodsDesc'),
        /** Carousel / gallery field inside goodsBasic (Temu-hosted URLs after upload). */
        'goods_image_urls_field' => env('TEMU_GOODS_IMAGE_URLS_FIELD', 'carouselImageUrlList'),
        /** Product video URL list inside goodsBasic (after Temu-hosted upload). */
        'goods_video_urls_field' => env('TEMU_GOODS_VIDEO_URLS_FIELD', 'productVideoUrlList'),
        /** Primary video upload `type` (Partner router). */
        'video_upload_type' => env('TEMU_VIDEO_UPLOAD_TYPE', 'files/upload_video'),
        /** Comma-separated extra `type` values to try after the primary. */
        'video_upload_types' => array_values(array_filter(array_map('trim', explode(',', env('TEMU_VIDEO_UPLOAD_TYPES', 'temu.local.video.upload,bg.local.goods.video.upload'))))),
        /** If true, after URL-based upload fails, download the video and try base64 fields. */
        'video_upload_try_base64' => filter_var(env('TEMU_VIDEO_UPLOAD_TRY_BASE64', true), FILTER_VALIDATE_BOOLEAN),
        /** If true, download each video and upload base64 first (large files; use with caution). */
        'video_upload_prefer_base64' => filter_var(env('TEMU_VIDEO_UPLOAD_PREFER_BASE64', false), FILTER_VALIDATE_BOOLEAN),
        /** Primary image upload `type` (Partner router). Community/docs often use `files/upload_image` with `url`. */
        'image_upload_type' => env('TEMU_IMAGE_UPLOAD_TYPE', 'files/upload_image'),
        /** Comma-separated extra `type` values to try after the primary (e.g. regional variants). */
        'image_upload_types' => array_values(array_filter(array_map('trim', explode(',', env('TEMU_IMAGE_UPLOAD_TYPES', 'temu.local.image.upload,bg.local.goods.image.upload'))))),
        'openapi_router_url' => env('TEMU_OPENAPI_URL', 'https://openapi-b-us.temu.com/openapi/router'),
        /** Optional warehouseId for self-fulfilled shipment confirm (bg.logistics.shipment.v2.confirm). */
        'self_shipping_warehouse_id' => env('TEMU_SELF_SHIPPING_WAREHOUSE_ID', ''),
        /** If true, after URL-based upload fails, download the image and try base64 fields (implementation-dependent). */
        'image_upload_try_base64' => filter_var(env('TEMU_IMAGE_UPLOAD_TRY_BASE64', true), FILTER_VALIDATE_BOOLEAN),
        /** If true, download each image and upload base64 first (avoids Temu fetching your image host URL). */
        'image_upload_prefer_base64' => filter_var(env('TEMU_IMAGE_UPLOAD_PREFER_BASE64', true), FILTER_VALIDATE_BOOLEAN),
        'list_price_field' => env('TEMU_LIST_PRICE_FIELD', 'listPrice'),
        'sku_id_field' => env('TEMU_SKU_ID_FIELD', 'skuId'),
        'sku_code_field' => env('TEMU_SKU_CODE_FIELD', 'outSkuSn'),

        /** Seller Center cookie session for product Views scrape (no OpenAPI for organic clicks). */
        'seller_cookie' => env('TEMU_SELLER_COOKIE'),
        'seller_mall_id' => env('TEMU_SELLER_MALL_ID'),
        'seller_anti_content' => env('TEMU_SELLER_ANTI_CONTENT'),
        'seller_base_url' => env('TEMU_SELLER_BASE_URL', 'https://seller.temu.com'),
        'agentseller_base_url' => env('TEMU_AGENTSELLER_BASE_URL', 'https://agentseller.temu.com'),
        'seller_user_agent' => env('TEMU_SELLER_USER_AGENT'),
        /** JSON array of {label,base,url,body} — overrides default candidate scrape endpoints. */
        'seller_view_endpoints' => env('TEMU_SELLER_VIEW_ENDPOINTS'),
        /** Create-listing API (Publish to Temu 1). v2 is recommended; v1 fallback is bg.local.goods.add. */
        'goods_add_type' => env('TEMU_GOODS_ADD_TYPE', 'temu.local.goods.v2.add'),
        'cost_template_id' => env('TEMU_COST_TEMPLATE_ID', ''),
        'shipment_limit_day' => (int) env('TEMU_SHIPMENT_LIMIT_DAY', 2),
        'import_designation' => env('TEMU_IMPORT_DESIGNATION', '4'),
        'origin_region1' => env('TEMU_ORIGIN_REGION1', 'China'),
        'origin_region2' => env('TEMU_ORIGIN_REGION2', 'Guangdong'),
        'currency' => env('TEMU_CURRENCY', 'USD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Temu 2 (separate shop / app — do not reuse TEMU_* credentials)
    |--------------------------------------------------------------------------
    */
    'temu2' => [
        'app_key' => env('TEMU2_APP_KEY'),
        'secret_key' => env('TEMU2_SECRET_KEY'),
        'access_token' => env('TEMU2_ACCESS_TOKEN'),
        'goods_summary_field' => env('TEMU2_GOODS_SUMMARY_FIELD', 'goodsSummary'),
        'goods_summary_format' => env('TEMU2_GOODS_SUMMARY_FORMAT', 'string'),
        'bullet_update_include_sku_list' => filter_var(env('TEMU2_BULLET_INCLUDE_SKU_LIST', false), FILTER_VALIDATE_BOOLEAN),
        'goods_update_type' => env('TEMU2_GOODS_UPDATE_TYPE', 'bg.local.goods.partial.update'),
        'update_sku_list_field' => env('TEMU2_UPDATE_SKU_LIST_FIELD', 'skuList'),
        'goods_basic_field' => env('TEMU2_GOODS_BASIC_FIELD', 'goodsBasic'),
        'goods_desc_field' => env('TEMU2_GOODS_DESC_FIELD', 'goodsDesc'),
        'goods_image_urls_field' => env('TEMU2_GOODS_IMAGE_URLS_FIELD', 'carouselImageUrlList'),
        'goods_video_urls_field' => env('TEMU2_GOODS_VIDEO_URLS_FIELD', 'productVideoUrlList'),
        'video_upload_type' => env('TEMU2_VIDEO_UPLOAD_TYPE', 'files/upload_video'),
        'video_upload_types' => array_values(array_filter(array_map('trim', explode(',', env('TEMU2_VIDEO_UPLOAD_TYPES', 'temu.local.video.upload,bg.local.goods.video.upload'))))),
        'video_upload_try_base64' => filter_var(env('TEMU2_VIDEO_UPLOAD_TRY_BASE64', true), FILTER_VALIDATE_BOOLEAN),
        'video_upload_prefer_base64' => filter_var(env('TEMU2_VIDEO_UPLOAD_PREFER_BASE64', false), FILTER_VALIDATE_BOOLEAN),
        'image_upload_type' => env('TEMU2_IMAGE_UPLOAD_TYPE', 'files/upload_image'),
        'image_upload_types' => array_values(array_filter(array_map('trim', explode(',', env('TEMU2_IMAGE_UPLOAD_TYPES', 'temu.local.image.upload,bg.local.goods.image.upload'))))),
        // Partner US OpenAPI path is /openapi/router (same host as Temu).
        'openapi_router_url' => env('TEMU2_OPENAPI_URL', 'https://openapi-b-us.temu.com/openapi/router'),
        'self_shipping_warehouse_id' => env('TEMU2_SELF_SHIPPING_WAREHOUSE_ID', ''),
        'image_upload_try_base64' => filter_var(env('TEMU2_IMAGE_UPLOAD_TRY_BASE64', true), FILTER_VALIDATE_BOOLEAN),
        'image_upload_prefer_base64' => filter_var(env('TEMU2_IMAGE_UPLOAD_PREFER_BASE64', true), FILTER_VALIDATE_BOOLEAN),
        'list_price_field' => env('TEMU2_LIST_PRICE_FIELD', 'listPrice'),
        'sku_id_field' => env('TEMU2_SKU_ID_FIELD', 'skuId'),
        'sku_code_field' => env('TEMU2_SKU_CODE_FIELD', 'outSkuSn'),
        /** Create-listing API (Publish to Temu2). v2 is recommended; v1 fallback is bg.local.goods.add. */
        'goods_add_type' => env('TEMU2_GOODS_ADD_TYPE', 'temu.local.goods.v2.add'),
        'cost_template_id' => env('TEMU2_COST_TEMPLATE_ID', ''),
        'shipment_limit_day' => (int) env('TEMU2_SHIPMENT_LIMIT_DAY', 2),
        'import_designation' => env('TEMU2_IMPORT_DESIGNATION', '4'),
        /** English country name for goodsOriginInfo.originRegion1 (ISO-2 like CN is rejected). */
        'origin_region1' => env('TEMU2_ORIGIN_REGION1', 'China'),
        /** China province/region for originRegion2; required when origin_region1 is China. */
        'origin_region2' => env('TEMU2_ORIGIN_REGION2', 'Guangdong'),
        'currency' => env('TEMU2_CURRENCY', 'USD'),
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
        /** Inventory write — POST /open-api/gsp/goods/change-inventory (needs warehouse) */
        'stock_update_path' => env('SHEIN_STOCK_UPDATE_PATH', '/open-api/gsp/goods/change-inventory'),
        'warehouse_code' => env('SHEIN_WAREHOUSE_CODE'),
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
        'auth_mode' => env('FAIRE_AUTH_MODE', 'api_key'),
        // External API v2 — use www host (bare faire.com 301 strips POST bodies)
        'base_url' => env('FAIRE_BASE_URL', 'https://www.faire.com/external-api/v2'),
        'auth_url' => env('FAIRE_AUTH_URL', 'https://faire.com/oauth2/authorize'),
        'token_url' => env('FAIRE_TOKEN_URL', 'https://www.faire.com/api/external-api-oauth2/token'),
        'http_timeout' => (int) env('FAIRE_HTTP_TIMEOUT', 60),
        'connect_timeout' => (int) env('FAIRE_CONNECT_TIMEOUT', 15),
        'taxonomy_type_id' => env('FAIRE_TAXONOMY_TYPE_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AliExpress
    |--------------------------------------------------------------------------
    */
  /*
    |--------------------------------------------------------------------------
    | Alibaba.com (B2B)
    |--------------------------------------------------------------------------
    */
    'alibaba' => [
        'app_key' => env('ALIBABA_APP_KEY'),
        'app_secret' => env('ALIBABA_APP_SECRET'),
        'access_token' => env('ALIBABA_ACCESS_TOKEN'),
        'refresh_token' => env('ALIBABA_REFRESH_TOKEN'),
        'api_base' => env('ALIBABA_API_BASE', 'https://openapi.alibaba.com'),
        'gateway' => env('ALIBABA_GATEWAY', 'rest'),
        'rest_base' => env('ALIBABA_REST_BASE', 'https://api-sg.alibaba.com/rest'),
        'rest_sign_method' => env('ALIBABA_REST_SIGN_METHOD', 'hmac'),
        'connect_timeout' => (int) env('ALIBABA_CONNECT_TIMEOUT', 30),
        'timeout' => (int) env('ALIBABA_TIMEOUT', 60),
        'resolve_ipv4' => filter_var(env('ALIBABA_RESOLVE_IPV4', true), FILTER_VALIDATE_BOOL),
        'http_proxy' => env('ALIBABA_HTTP_PROXY'),
        'shopify_source_name' => env('ALIBABA_SHOPIFY_SOURCE_NAME', 'alibaba'),
        'shopify_source_display_name' => env('ALIBABA_SHOPIFY_SOURCE_DISPLAY_NAME', 'Alibaba'),
        'shopify_source_url_template' => env(
            'ALIBABA_SHOPIFY_SOURCE_URL_TEMPLATE',
            'https://www.alibaba.com/trade/order_detail.htm?orderId={order_id}'
        ),
        // Official ICBU OAuth: authorize → code → POST /auth/token/create.
        // Callback must match openapi.alibaba.com App Overview exactly (not /index — that is TikTok 2).
        'redirect_uri' => env('ALIBABA_REDIRECT_URI', 'https://inventory.5coremanagement.com/alibaba/callback'),
        'auth_base' => env('ALIBABA_AUTH_BASE', 'https://oauth.alibaba.com/authorize'),
        'token_url' => env('ALIBABA_TOKEN_URL', 'https://open-api.alibaba.com/rest/auth/token/create'),
        'oauth_site' => env('ALIBABA_OAUTH_SITE', 'alibaba'),
    ],

    'aliexpress' => [
        'app_key' => env('ALIEXPRESS_APP_KEY'),
        'app_secret' => env('ALIEXPRESS_APP_SECRET'),
        /** OAuth token value (sent as `session` for dropshipping /sync API) */
        'access_token' => env('ALIEXPRESS_ACCESS_TOKEN'),
        'redirect_uri' => env('ALIEXPRESS_REDIRECT_URI', env('APP_URL')),
        /**
         * API gateway: "rest" = business APIs (product list, orders); "sync" = legacy dropshipping /sync.
         */
        /** Prefer rest (business HMAC-SHA256) for solution APIs */
        'gateway' => env('ALIEXPRESS_GATEWAY', 'rest'),
        'rest_base' => env('ALIEXPRESS_REST_BASE', 'https://api-sg.aliexpress.com/rest'),
        /** TOP router for AE-数据 APIs (views / CVR) — datetime timestamp + session + hmac-md5 */
        'top_base' => env('ALIEXPRESS_TOP_BASE', 'https://eco.taobao.com/router/rest'),
        /** hmac (HMAC-MD5) or md5 for rest gateway — see TOP docs */
        'rest_sign_method' => env('ALIEXPRESS_REST_SIGN_METHOD', 'hmac'),
        'connect_timeout' => (int) env('ALIEXPRESS_CONNECT_TIMEOUT', 30),
        'timeout' => (int) env('ALIEXPRESS_TIMEOUT', 60),
        /** Force IPv4 when IPv6 routes to AliExpress are blocked (common on some ISPs) */
        'resolve_ipv4' => filter_var(env('ALIEXPRESS_RESOLVE_IPV4', true), FILTER_VALIDATE_BOOL),
        /** Optional HTTP proxy, e.g. http://127.0.0.1:7890 */
        'http_proxy' => env('ALIEXPRESS_HTTP_PROXY'),
        /** Try the other gateway if the primary cannot connect */
        'gateway_fallback' => filter_var(env('ALIEXPRESS_GATEWAY_FALLBACK', true), FILTER_VALIDATE_BOOL),
        /**
         * Dropshipping API POST URL (must end with /sync). Used when gateway=sync.
         */
        'api_base' => env('ALIEXPRESS_API_BASE', 'https://api-sg.aliexpress.com/sync'),
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
        'transport' => env('ALIEXPRESS_TRANSPORT', 'form'),
        /** iop = /sync prefix sign; legacy = secret sandwich (matches ProductMaster) */
        'sync_sign_style' => env('ALIEXPRESS_SYNC_SIGN_STYLE', 'legacy'),
        /** "iop" = time()."000" like SDK msectime(); "ms" = round(microtime(true)*1000) */
        'timestamp_style' => env('ALIEXPRESS_TIMESTAMP_STYLE', 'iop'),
        /**
         * Shopify order attribution handle (case-sensitive). Must match a handle registered
         * under your app’s Marketplace extension in the Shopify Partner Dashboard,
         * or Channel information will show the app name (e.g. 5core) instead of AliExpress.
         */
        'shopify_source_name' => env('ALIEXPRESS_SHOPIFY_SOURCE_NAME', 'aliexpress'),
        'shopify_source_display_name' => env('ALIEXPRESS_SHOPIFY_SOURCE_DISPLAY_NAME', 'AliExpress'),
        'shopify_source_url_template' => env(
            'ALIEXPRESS_SHOPIFY_SOURCE_URL_TEMPLATE',
            'https://csp.aliexpress.com/m_apps/order-manage/order_detail?orderId={order_id}'
        ),
        'freight_template_id' => env('ALIEXPRESS_FREIGHT_TEMPLATE_ID', ''),
        'default_category_id' => env('ALIEXPRESS_DEFAULT_CATEGORY_ID', ''),
        'product_unit' => env('ALIEXPRESS_PRODUCT_UNIT', '100000015'),
        'shipping_lead_time' => (int) env('ALIEXPRESS_SHIPPING_LEAD_TIME', 7),
        'brand_name' => env('ALIEXPRESS_BRAND_NAME', '5 Core Inc.'),
        'service_policy_id' => env('ALIEXPRESS_SERVICE_POLICY_ID', '0'),
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
        'shop_cipher' => env('TIKTOK_SHOP_CIPHER'),
        'app_key' => env('TIKTOK_APP_KEY', env('TIKTOK_CLIENT_KEY')),
        'app_secret' => env('TIKTOK_APP_SECRET', env('TIKTOK_CLIENT_SECRET')),
        'access_token' => env('TIKTOK_ACCESS_TOKEN'),
        'refresh_token' => env('TIKTOK_REFRESH_TOKEN'),
        'app_id' => env('TIKTOK_APP_ID'),
        'warehouse_id' => env('TIKTOK_WAREHOUSE_ID'),
        'category_version' => env('TIKTOK_CATEGORY_VERSION', 'v2'),
    ],

    /*
    | GMV Max spend (TikTok Marketing API). Shop analytics does not return ads cost.
    */
    'tiktok_ads' => [
        'access_token' => env('TIKTOK_ADS_ACCESS_TOKEN'),
        'advertiser_id' => env('TIKTOK_ADS_ADVERTISER_ID', env('TIKTOK_ADVERTISER_ID')),
        'store_id' => env('TIKTOK_ADS_STORE_ID', env('TIKTOK_SHOP_ID')),
    ],

    /*
    |--------------------------------------------------------------------------
    | TikTok Shop 2 (second Partner Center app / shop)
    |--------------------------------------------------------------------------
    */
    'tiktok2' => [
        'client_key' => env('TIKTOK2_CLIENT_KEY'),
        'client_secret' => env('TIKTOK2_CLIENT_SECRET'),
        'redirect_uri' => env('TIKTOK2_REDIRECT_URI', 'https://inventory.5coremanagement.com/index'),
        'auth_base' => env('TIKTOK2_AUTH_BASE', env('TIKTOK_AUTH_BASE', 'https://auth.tiktok-shops.com')),
        'api_base' => env('TIKTOK2_API_BASE', env('TIKTOK_API_BASE', 'https://open-api.tiktokglobalshop.com')),
        'shop_id' => env('TIKTOK2_SHOP_ID'),
        'shop_cipher' => env('TIKTOK2_SHOP_CIPHER'),
        'app_key' => env('TIKTOK2_APP_KEY', env('TIKTOK2_CLIENT_KEY')),
        'app_secret' => env('TIKTOK2_APP_SECRET', env('TIKTOK2_CLIENT_SECRET')),
        'access_token' => env('TIKTOK2_ACCESS_TOKEN'),
        'refresh_token' => env('TIKTOK2_REFRESH_TOKEN'),
        'app_id' => env('TIKTOK2_APP_ID'),
        'warehouse_id' => env('TIKTOK2_WAREHOUSE_ID'),
        'category_version' => env('TIKTOK2_CATEGORY_VERSION', env('TIKTOK_CATEGORY_VERSION', 'v2')),
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
    | Apify (used for TikTok Shop competitor search — SerpApi has no TikTok
    | engine, so the /repricer/tiktok-search module hits an Apify Actor.
    |--------------------------------------------------------------------------
    | APIFY_TOKEN                  Apify API token (https://console.apify.com/account/integrations)
    | APIFY_TIKTOK_ACTOR_ID        Actor slug (owner~name). Default is the
    |                              "sentry/tiktok-shop-search-pro" actor whose
    |                              output fields (product_id, brand, seller,
    |                              min/avg/max_price, rating, review_count,
    |                              rank_on_page) map cleanly onto our
    |                              tiktok_competitor_products schema.
    | APIFY_TIKTOK_REGION          Default ISO country code (US, GB, MY, ...).
    | APIFY_TIKTOK_MAX_PRODUCTS    Upper bound passed to the actor per run.
    | APIFY_TIKTOK_TIMEOUT_SECS    Synchronous actor run timeout (seconds).
    */
    'apify' => [
        'token' => env('APIFY_TOKEN'),
        'base_url' => env('APIFY_BASE_URL', 'https://api.apify.com/v2'),
        'tiktok' => [
            'actor_id' => env('APIFY_TIKTOK_ACTOR_ID', 'sentry~tiktok-shop-search-pro'),
            'region' => env('APIFY_TIKTOK_REGION', 'US'),
            'max_products' => (int) env('APIFY_TIKTOK_MAX_PRODUCTS', 100),
            'timeout_secs' => (int) env('APIFY_TIKTOK_TIMEOUT_SECS', 180),
        ],
        // Shein has no SerpApi engine either, so /repricer/shein-search hits
        // an Apify Actor. Default is `scraper-engine/shein-search-products-scraper`
        // whose output maps cleanly onto shein_competitor_products
        // (goods_id, goods_name, salePrice, retailPrice, rankInfo).
        // Override APIFY_SHEIN_ACTOR_ID to swap providers.
        'shein' => [
            'actor_id' => env('APIFY_SHEIN_ACTOR_ID', 'scraper-engine~shein-search-products-scraper'),
            'country' => env('APIFY_SHEIN_COUNTRY', 'us'),
            'max_products' => (int) env('APIFY_SHEIN_MAX_PRODUCTS', 100),
            // Shein actor spends ~60–120s loading the Shein SPA before it can
            // scrape rows. Default to the run-sync max (300s) so a normal
            // 100-item search has headroom.
            'timeout_secs' => (int) env('APIFY_SHEIN_TIMEOUT_SECS', 300),
            // scraper-engine/shein-search-products-scraper is PAY_PER_EVENT:
            // ~$0.00499 per result row + a small actor-start fee. Apify will
            // auto-abort the run if maxTotalChargeUsd is too low (it defaults
            // to ~$0.00125 on free / pay-as-you-go accounts, which won't even
            // pay for a single row). Set APIFY_SHEIN_MAX_USD to the
            // per-search budget you're willing to spend.
            'max_usd' => (float) env('APIFY_SHEIN_MAX_USD', 1.0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Services
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'key' => \App\Support\OpenAiRequest::normalizeApiKey(env('OPENAI_API_KEY')),
        'packing_vision_model' => env('OPENAI_PACKING_VISION_MODEL', 'gpt-4o-mini'),
        'title_master_stack_model' => env('OPENAI_TITLE_MASTER_STACK_MODEL', 'gpt-4o-mini'),
        'hero_vision_model' => env('OPENAI_HERO_VISION_MODEL', 'gpt-4o-mini'),
        'organization' => ($o = env('OPENAI_ORGANIZATION')) === null || $o === '' ? null : trim($o),
        'project' => ($p = env('OPENAI_PROJECT')) === null || $p === '' ? null : trim($p),
    ],

    // Raw Images page only — high-quality product image generation.
    'raw_images_ai' => [
        'gemini_key' => env('GEMINI_API_KEY', env('GOOGLE_GEMINI_API_KEY')),
        'gemini_model' => env('RAW_IMAGES_GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image-preview'),
        'openai_key' => \App\Support\OpenAiRequest::normalizeApiKey(
            env('RAW_IMAGES_OPENAI_API_KEY', env('OPENAI_API_KEY'))
        ),
        'image_model' => env('RAW_IMAGES_OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        'image_quality' => env('RAW_IMAGES_OPENAI_IMAGE_QUALITY', 'high'),
        'image_size' => env('RAW_IMAGES_OPENAI_IMAGE_SIZE', '1024x1024'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY', env('GOOGLE_GEMINI_API_KEY')),
        'video_model' => env('GEMINI_VIDEO_MODEL', 'gemini-flash-latest'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY', env('CLAUDE_API_KEY')),
        // Default model picked from the currently-available list returned by
        // /v1/models for this key (claude-haiku-4-5-20251001 is the fastest /
        // cheapest tier the key has access to). Override via ANTHROPIC_MODEL.
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
        'hero_vision_model' => env('ANTHROPIC_HERO_VISION_MODEL', 'claude-sonnet-4-5-20250929'),
        'version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
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
