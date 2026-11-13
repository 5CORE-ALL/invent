<?php

namespace App\Support\Marketplace\Settle\Verification;

/**
 * Which verifier (and which tables / columns / timezones) checks each ledger task.
 *
 * Timezones are the ones the existing code stores / reads the column in:
 *  - amazon_orders.order_date, mirakl / tiktok created_at : UTC (see YesterdayMarketplaceMetricsService)
 *  - ebay_orders.order_date                               : UTC (creationDate parsed from an ISO 'Z' string)
 *  - temu*, doba, ebay3 creation_date                     : Pacific wall clock
 *  - shein_daily_data.order_processed_on                  : Asia/Shanghai
 *  - pls_sales.order_date                                 : the order's own (US Eastern) offset
 * A wrong timezone only shifts the window; every volume check compares with the same-shaped
 * window of earlier days, and the hard failure ("zero rows") is timezone independent.
 */
final class VerifierRegistry
{
    private const LA = 'America/Los_Angeles';

    public static function for(string $taskKey): Verifier
    {
        $specs = self::orderSpecs();
        if (isset($specs[$taskKey])) {
            return new OrdersWindowVerifier($specs[$taskKey]);
        }
        $ads = self::adsSpecs();
        if (isset($ads[$taskKey])) {
            return new AdsReportVerifier($ads[$taskKey]);
        }
        $views = self::viewsSpecs();
        if (isset($views[$taskKey])) {
            return new ViewsVerifier($views[$taskKey]);
        }
        if ($taskKey === 'calculate') {
            return new CalculateVerifier;
        }
        if ($taskKey === 'wayfair_api') {
            return new OutputOnlyVerifier('sync:wayfair-l30-api only updates product-sheet L30/L60 columns (no dated table); wayfair:daily is the data-level check for Wayfair.');
        }

        return new OutputOnlyVerifier('No data-level verifier is defined for this task; verified from the command output only.');
    }

    /** @return array<string, array<string, mixed>> */
    public static function orderSpecs(): array
    {
        $amazon = [
            'table' => 'amazon_orders',
            'time_column' => 'order_date',
            'tz' => 'UTC',
            'key' => ['amazon_order_id'],
            'status_column' => 'status',
            'exclude_statuses' => ['Canceled', 'Cancelled'],
            'items' => ['table' => 'amazon_order_items', 'fk' => 'amazon_order_id', 'price' => 'price'],
            'pending_prices' => true,
        ];

        return [
            'amazon_close' => $amazon,
            'amazon_refresh' => $amazon,
            'ebay' => [
                'table' => 'ebay_orders', 'time_column' => 'order_date', 'tz' => 'UTC',
                'key' => ['ebay_order_id'], 'price_column' => 'total_amount',
                'items' => ['table' => 'ebay_order_items', 'fk' => 'ebay_order_id', 'price' => 'price'],
            ],
            'ebay2' => [
                'table' => 'ebay2_orders', 'time_column' => 'order_date', 'tz' => 'UTC',
                'key' => ['ebay_order_id'], 'price_column' => 'total_amount',
                'items' => ['table' => 'ebay2_order_items', 'fk' => 'ebay2_order_id', 'price' => 'price'],
            ],
            'ebay3' => [
                'table' => 'ebay3_daily_data', 'time_column' => 'creation_date', 'tz' => self::LA,
                'key' => ['order_id', 'line_item_id'], 'price_column' => 'unit_price',
            ],
            'temu' => [
                'table' => 'temu_orders', 'time_column' => 'parent_order_time', 'tz' => self::LA,
                'key' => ['order_sn'], 'table_shrink' => true,
            ],
            'temu2' => [
                'table' => 'temu2_orders', 'time_column' => 'parent_order_time', 'tz' => self::LA,
                'key' => ['order_sn'], 'price_column' => 'order_base_amount',
            ],
            'temu3' => [
                'table' => 'temu3_orders', 'time_column' => 'purchase_date', 'tz' => self::LA,
                'key' => ['order_id', 'order_item_id'], 'price_column' => 'base_price_total',
            ],
            'wayfair' => [
                'table' => 'wayfair_daily_data', 'time_column' => 'po_date', 'time_type' => 'date',
                'key' => ['po_number', 'sku'], 'price_column' => 'unit_price',
            ],
            // Shein rebuilds the whole table (delete + reinsert): also guard against a wipe.
            'shein_l30' => [
                'table' => 'shein_daily_data', 'time_column' => 'order_processed_on', 'tz' => 'Asia/Shanghai',
                'key' => ['order_number', 'seller_sku', 'shein_sku'], 'price_column' => 'product_price',
                'table_shrink' => true,
            ],
            'shein_l60' => ['table' => 'shein_daily_data_l60', 'mode' => 'table', 'table_shrink' => true],
            'tiktok' => [
                'table' => 'tiktok_orders', 'time_column' => 'order_created_at', 'tz' => 'UTC',
                'key' => ['order_id', 'line_item_id'], 'price_column' => 'sale_price', 'table_shrink' => true,
            ],
            'tiktok2' => [
                'table' => 'tiktok2_orders', 'time_column' => 'order_created_at', 'tz' => 'UTC',
                'key' => ['order_id', 'line_item_id'], 'price_column' => 'sale_price', 'table_shrink' => true,
            ],
            'mirakl' => [
                'table' => 'mirakl_daily_data', 'time_column' => 'order_created_at', 'tz' => 'UTC',
                'key' => ['order_id', 'order_line_id'], 'price_column' => 'unit_price', 'group_by' => 'channel_name',
            ],
            'doba' => [
                'table' => 'doba_daily_data', 'time_column' => 'order_time', 'tz' => self::LA,
                'key' => ['order_no', 'item_no'], 'price_column' => 'item_price',
            ],
            'pls' => [
                'table' => 'pls_sales', 'time_column' => 'order_date', 'tz' => 'America/New_York',
                'key' => ['shopify_order_id', 'shopify_line_item_id'], 'price_column' => 'price',
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function adsSpecs(): array
    {
        $ebay = fn (string $table) => [
            'table' => $table, 'date_column' => 'report_range', 'key' => ['campaign_id'],
            'spend' => ['type' => 'ebay_string', 'column' => 'cpc_ad_fees_payout_currency'],
        ];

        return [
            'ads_ebay' => $ebay('ebay_priority_reports'),
            'ads_ebay2' => $ebay('ebay_2_priority_reports'),
            'ads_ebay3' => $ebay('ebay_3_priority_reports'),
            'ads_amazon_sp' => [
                'table' => 'amazon_sp_campaign_reports', 'date_column' => 'report_date_range',
                'key' => ['campaign_id', 'profile_id'], 'spend' => ['type' => 'numeric', 'columns' => ['cost', 'spend']],
            ],
            'ads_amazon_sb' => [
                'table' => 'amazon_sb_campaign_reports', 'date_column' => 'report_date_range',
                'key' => ['campaign_id', 'profile_id'], 'spend' => ['type' => 'numeric', 'columns' => ['cost']],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function viewsSpecs(): array
    {
        return [
            // MarketplaceViewWindowStore::storeAll() writes the dated rows for these channels.
            'views_store' => ['channels' => ['ebay', 'ebay2', 'walmart', 'temu', 'temu2', 'shopify']],
            'views_ebay' => ['channels' => ['ebay'], 'depends_on' => 'views_store'],
            'views_ebay2' => ['channels' => ['ebay2'], 'depends_on' => 'views_store'],
            'views_temu' => ['channels' => ['temu'], 'depends_on' => 'views_store'],
            'views_temu2' => ['channels' => ['temu2'], 'depends_on' => 'views_store'],
            // No dated one-day store exists for these (limited verification).
            'views_ebay3' => ['channels' => []],
            'views_temu3' => ['channels' => []],
            'views_amazon_settled' => ['channels' => [], 'amazon' => true],
        ];
    }
}
