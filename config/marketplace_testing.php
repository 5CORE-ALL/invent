<?php

/**
 * Default SKUs for safe marketplace master API testing (live platforms).
 * Override per run with --sku= on artisan marketplace:audit-master.
 */
return [
    'bullet_point_sku' => env('MARKETPLACE_TEST_SKU_BULLET', 'SP 12120 4OHM GTR'),

    'title_sku' => env('MARKETPLACE_TEST_SKU_TITLE', 'SP 12120 4OHM GTR'),

    'description_sku' => env('MARKETPLACE_TEST_SKU_DESCRIPTION', 'SP 12120 4OHM GTR'),

    'image_sku' => env('MARKETPLACE_TEST_SKU_IMAGE', 'SP 12120 4OHM GTR'),

    'video_sku' => env('MARKETPLACE_TEST_SKU_VIDEO', 'SP 12120 4OHM GTR'),
];
