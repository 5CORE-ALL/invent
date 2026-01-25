<?php

return [
    'character_limits' => [
        'amazon' => 150,
        'ebay' => 80,
        'ebay2' => 80,
        'ebay3' => 80,
        'shopify' => 150,
    ],

    'tables' => [
        'amazon' => 'amazon_datsheets',
        'ebay' => 'ebay_metrics',
        'ebay2' => 'ebay_2_metrics',
        'ebay3' => 'ebay_3_metrics',
        'shopify' => 'shopify_skus',
    ],

    'title_columns' => [
        'amazon' => 'amazon_title',
        'ebay' => 'ebay_title',
        'ebay2' => 'ebay_title',
        'ebay3' => 'ebay_title',
        'shopify' => 'product_title',
    ],

    'link_columns' => [
        'amazon' => 'amazon_link',
        'ebay' => 'ebay_link',
        'ebay2' => 'ebay_link',
        'ebay3' => 'ebay_link',
        'shopify' => 'product_link',
    ],

    'icons' => [
        'amazon' => 'fab fa-amazon',
        'ebay' => 'fab fa-ebay',
        'ebay2' => 'fab fa-ebay',
        'ebay3' => 'fab fa-ebay',
        'shopify' => 'fab fa-shopify',
    ],

    'colors' => [
        'amazon' => '#FF9900',
        'ebay' => '#0064D2',
        'ebay2' => '#0064D2',
        'ebay3' => '#0064D2',
        'shopify' => '#96BF48',
    ],
];
