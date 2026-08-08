<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Marketplace character limits (LitCommerce-style counters)
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'ebay' => ['title' => 80, 'description' => 500000],
        'ebay1' => ['title' => 80, 'description' => 500000],
        'ebay2' => ['title' => 80, 'description' => 500000],
        'ebaytwo' => ['title' => 80, 'description' => 500000],
        'ebay3' => ['title' => 80, 'description' => 500000],
        'ebaythree' => ['title' => 80, 'description' => 500000],
        'amazon' => ['title' => 200, 'description' => 2000],
        'shopify' => ['title' => 255, 'description' => 65535],
        'default' => ['title' => 200, 'description' => 5000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ebay 2 LitCommerce defaults (Item Location + Business Policies)
    |--------------------------------------------------------------------------
    */
    'ebay2_defaults' => [
        'location_city' => env('LISTING_MANAGER_EBAY2_CITY', 'Bellefontaine'),
        'location_country' => env('LISTING_MANAGER_EBAY2_COUNTRY', 'US'),
        'location_postal_code' => env('LISTING_MANAGER_EBAY2_POSTAL', '43311'),
        'shipping_policy_id' => env('LISTING_MANAGER_EBAY2_SHIPPING_POLICY_ID', ''),
        'shipping_policy_name' => env('LISTING_MANAGER_EBAY2_SHIPPING_POLICY_NAME', 'As Per Weight'),
        'payment_policy_id' => env('LISTING_MANAGER_EBAY2_PAYMENT_POLICY_ID', '307554145021'),
        'payment_policy_name' => env('LISTING_MANAGER_EBAY2_PAYMENT_POLICY_NAME', 'eBay Managed Payments'),
        'return_policy_id' => env('LISTING_MANAGER_EBAY2_RETURN_POLICY_ID', '329818346021'),
        'return_policy_name' => env('LISTING_MANAGER_EBAY2_RETURN_POLICY_NAME', '30 days money back'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Required item specifics defaults (Ebay publish)
    |--------------------------------------------------------------------------
    */
    'default_brand' => env('LISTING_MANAGER_DEFAULT_BRAND', '5 Core'),
];
