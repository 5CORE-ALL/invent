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
        'tiktok' => ['title' => 255, 'description' => 10000],
        'tiktokshop' => ['title' => 255, 'description' => 10000],
        'tiktok2' => ['title' => 255, 'description' => 10000],
        'tiktokshop2' => ['title' => 255, 'description' => 10000],
        'temu' => ['title' => 250, 'description' => 5000],
        'temu2' => ['title' => 250, 'description' => 5000],
        'temutwo' => ['title' => 250, 'description' => 5000],
        'faire' => ['title' => 200, 'description' => 5000],
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

    'ebay1_defaults' => [
        'location_city' => env('LISTING_MANAGER_EBAY1_CITY', env('LISTING_MANAGER_EBAY2_CITY', 'Bellefontaine')),
        'location_country' => env('LISTING_MANAGER_EBAY1_COUNTRY', env('LISTING_MANAGER_EBAY2_COUNTRY', 'US')),
        'location_postal_code' => env('LISTING_MANAGER_EBAY1_POSTAL', env('LISTING_MANAGER_EBAY2_POSTAL', '43311')),
        'shipping_policy_id' => env('LISTING_MANAGER_EBAY1_SHIPPING_POLICY_ID', ''),
        'shipping_policy_name' => env('LISTING_MANAGER_EBAY1_SHIPPING_POLICY_NAME', 'As Per Weight'),
        'payment_policy_id' => env('LISTING_MANAGER_EBAY1_PAYMENT_POLICY_ID', ''),
        'payment_policy_name' => env('LISTING_MANAGER_EBAY1_PAYMENT_POLICY_NAME', 'eBay Managed Payments'),
        'return_policy_id' => env('LISTING_MANAGER_EBAY1_RETURN_POLICY_ID', ''),
        'return_policy_name' => env('LISTING_MANAGER_EBAY1_RETURN_POLICY_NAME', '30 days money back'),
    ],

    'ebay3_defaults' => [
        'location_city' => env('LISTING_MANAGER_EBAY3_CITY', env('LISTING_MANAGER_EBAY2_CITY', 'Bellefontaine')),
        'location_country' => env('LISTING_MANAGER_EBAY3_COUNTRY', env('LISTING_MANAGER_EBAY2_COUNTRY', 'US')),
        'location_postal_code' => env('LISTING_MANAGER_EBAY3_POSTAL', env('LISTING_MANAGER_EBAY2_POSTAL', '43311')),
        'shipping_policy_id' => env('LISTING_MANAGER_EBAY3_SHIPPING_POLICY_ID', ''),
        'shipping_policy_name' => env('LISTING_MANAGER_EBAY3_SHIPPING_POLICY_NAME', 'As Per Weight'),
        'payment_policy_id' => env('LISTING_MANAGER_EBAY3_PAYMENT_POLICY_ID', ''),
        'payment_policy_name' => env('LISTING_MANAGER_EBAY3_PAYMENT_POLICY_NAME', 'eBay Managed Payments'),
        'return_policy_id' => env('LISTING_MANAGER_EBAY3_RETURN_POLICY_ID', ''),
        'return_policy_name' => env('LISTING_MANAGER_EBAY3_RETURN_POLICY_NAME', '30 days money back'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Required item specifics defaults (Ebay publish)
    |--------------------------------------------------------------------------
    */
    'default_brand' => env('LISTING_MANAGER_DEFAULT_BRAND', '5 Core Inc.'),
    'default_manufacturer' => env('LISTING_MANAGER_DEFAULT_MANUFACTURER', '5 Core Inc.'),
];
