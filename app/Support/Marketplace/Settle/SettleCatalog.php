<?php

namespace App\Support\Marketplace\Settle;

/**
 * Human-readable marketplace / kind labels for the ledger task keys
 * (YesterdaySettleLedger::tasks()). Display only; unknown keys fall back to the key itself.
 */
final class SettleCatalog
{
    /** @var array<string, array{0: string, 1: string}> task key => [marketplace, kind] */
    private const MAP = [
        'ebay' => ['eBay', 'orders'],
        'ebay2' => ['eBay 2', 'orders'],
        'ebay3' => ['eBay 3', 'orders'],
        'ads_ebay' => ['eBay', 'ads'],
        'ads_ebay2' => ['eBay 2', 'ads'],
        'ads_ebay3' => ['eBay 3', 'ads'],
        'temu' => ['Temu', 'orders'],
        'temu2' => ['Temu 2', 'orders'],
        'temu3' => ['Temu 3', 'orders'],
        'wayfair_api' => ['Wayfair', 'orders'],
        'wayfair' => ['Wayfair', 'orders'],
        'shein_l30' => ['Shein', 'orders'],
        'shein_l60' => ['Shein', 'orders'],
        'tiktok' => ['TikTok', 'orders'],
        'tiktok2' => ['TikTok 2', 'orders'],
        'mirakl' => ['Mirakl (Macy\'s / Best Buy)', 'orders'],
        'doba' => ['Doba', 'orders'],
        'pls' => ['PLS', 'orders'],
        'ads_amazon_sp' => ['Amazon', 'ads'],
        'ads_amazon_sb' => ['Amazon', 'ads'],
        'amazon_close' => ['Amazon', 'orders'],
        'amazon_refresh' => ['Amazon', 'orders'],
        'views_ebay' => ['eBay', 'views'],
        'views_ebay2' => ['eBay 2', 'views'],
        'views_ebay3' => ['eBay 3', 'views'],
        'views_temu' => ['Temu', 'views'],
        'views_temu2' => ['Temu 2', 'views'],
        'views_temu3' => ['Temu 3', 'views'],
        'views_store' => ['Store views (eBay, eBay 2, Walmart, Temu, Temu 2, Shopify)', 'views'],
        'views_amazon_settled' => ['Amazon', 'views'],
        'calculate' => ['Channel matrix', 'calculate'],
    ];

    public static function marketplace(string $taskKey): string
    {
        return self::MAP[$taskKey][0] ?? $taskKey;
    }

    public static function kind(string $taskKey): string
    {
        return self::MAP[$taskKey][1] ?? 'other';
    }

    /** "Temu (orders) [temu]" — used in tooltips, alerts and CLI output. */
    public static function label(string $taskKey): string
    {
        return self::marketplace($taskKey).' '.self::kind($taskKey).' ['.$taskKey.']';
    }
}
