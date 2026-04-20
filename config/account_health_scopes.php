<?php

/**
 * eBay 1 / 2 / 3 share one field-definition scope (`ebay_group`).
 * One row per field_key in DB — not separate copies per account.
 *
 * `ebay_group_channel_names`: paste the **exact** `channel_master.channel` values for each eBay
 * account (copy from phpMyAdmin). Matching is case-insensitive; spaces trimmed; zero-width chars stripped.
 *
 * Optional: force by id (comma-separated in .env):
 * ACCOUNT_HEALTH_EBAY_GROUP_CHANNEL_IDS=12,34,56
 */
return [
    /** @var list<int> */
    'ebay_group_channel_ids' => array_values(array_filter(
        array_map(
            static fn ($v) => (int) trim((string) $v),
            explode(',', (string) env('ACCOUNT_HEALTH_EBAY_GROUP_CHANNEL_IDS', ''))
        ),
        static fn (int $id) => $id > 0
    )),

    'ebay_group_channel_names' => [
        'eBay',
        'Ebay',
        'eBay 1',
        'Ebay 1',
        'eBay1',
        'Ebay1',
        'eBay 2',
        'Ebay 2',
        'EbayTwo',
        'Ebay2',
        'eBay2',
        'eBay 3',
        'Ebay 3',
        'EbayThree',
        'Ebay3',
        'eBay3',
    ],
];
