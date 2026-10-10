<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inventory ledger as push qty source
    |--------------------------------------------------------------------------
    |
    | When true, syncSkusFromShopify (webhook / SKU fast-path) reads quantities
    | from mm_inventory_ledger first and only falls back to live Shopify Admin
    | API for SKUs missing from the ledger. Scheduled full catalog crawls are
    | unchanged until Phase 3.
    |
    */
    'use_inventory_ledger' => (bool) env('MM_USE_INVENTORY_LEDGER', false),

    /*
    |--------------------------------------------------------------------------
    | Shopify webhook ingress queue
    |--------------------------------------------------------------------------
    */
    'webhook_queue' => env('MM_WEBHOOK_QUEUE', 'mm-ingress'),

    'default_store' => env('MM_DEFAULT_STORE', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Marketplaces whose orders must NOT be created on Shopify by this app
    |--------------------------------------------------------------------------
    |
    | Comma-separated slugs. Doba is created here like the other channels
    | (Veeqo and 4Seller/GOFO pick the Shopify copy up). Leave the env empty
    | to import every channel.
    |
    */
    'paused_shopify_imports' => array_values(array_filter(array_map(
        'trim',
        explode(',', strtolower((string) env('MM_PAUSED_SHOPIFY_IMPORTS', '')))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Inventory sync cadence (documented; schedule lives in Console\Kernel)
    |--------------------------------------------------------------------------
    | - Full SyncInventoryTo*: every 4 hours
    | - Mismatch-only SyncMarketplaceMismatchInventoryJob: every 15 minutes
    | - Webhook PushLinkedSkuInventoryFromShopify: immediate on qty change
    */
];
