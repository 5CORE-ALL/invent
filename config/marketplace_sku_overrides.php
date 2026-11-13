<?php

/*
 * Manual SKU fixes for marketplace sales that arrive in Shopify through Fluf.
 *
 * Fluf creates some lines as custom items (no Shopify variant) whose "SKU" is only the Vinted listing code
 * (500W, 300W, BPNK ...). The same code can belong to different products, so a fix is keyed by the Shopify
 * ORDER NUMBER, not by the code. The value must be the exact Product Master SKU.
 *
 * An order that has several lines can be fixed per line with "ORDER|LINE CODE", e.g. '#357951|500W'.
 *
 * Applied by fluf:sync-sales (and the hourly job) before anything else. After editing run:
 *   php artisan config:clear && php artisan fluf:sync-sales vinted --days=250
 */
return [
    'vinted' => [
        // '#357951' => 'CS 06 2W',
        // '#357413' => 'GSS BLK 2PCS',
    ],
    'depop' => [
    ],
];
