<?php

/**
 * Shipping > Report checklist platform names.
 * After first migrate, rows live in `shipping_platforms`; you can edit names there or re-seed.
 * This list is used only when the table is empty (sync in ShippingController).
 */
return [
    'default_platforms' => [
        'Amazon',
        'eBay',
        'Shopify',
        'Walmart',
        'Wayfair',
        "Macy's",
        'Newegg',
        'Reverb',
        'Temu',
        'TikTok Shop',
        'Mercari',
        'Website',
    ],
];
