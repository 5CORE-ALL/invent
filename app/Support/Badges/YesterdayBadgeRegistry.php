<?php

namespace App\Support\Badges;

use App\Http\Controllers\MarketPlace\SheinController;
use App\Http\Controllers\Sales\AlibabaSalesController;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Http\Controllers\Sales\BestBuySalesController;
use App\Http\Controllers\Sales\DobaSalesController;
use App\Http\Controllers\Sales\Ebay2SalesController;
use App\Http\Controllers\Sales\Ebay3SalesController;
use App\Http\Controllers\Sales\EbaySalesController;
use App\Http\Controllers\Sales\FacebookMarketplaceController;
use App\Http\Controllers\Sales\MacysSalesController;
use App\Http\Controllers\Sales\MercariController;
use App\Http\Controllers\Sales\NeweggSalesController;
use App\Http\Controllers\Sales\ShopifyB2BSalesController;
use App\Http\Controllers\Sales\ShopifyB2CSalesController;
use App\Http\Controllers\Sales\ShopifyWholesalesController;
use App\Http\Controllers\Sales\TikTokSalesController;
use App\Http\Controllers\Sales\WalmartSalesController;
use App\Http\Controllers\Sales\WayfairSalesController;

/**
 * Pages whose "Y Sales" (yesterday) badge keeps a daily history (trend dot + history chart).
 *
 * Values live in badges_data_histories under page_name "ybadge:{page}". Two ways a day gets saved:
 *  - server pages (controller set): `app:snapshot-yesterday-sales-badges` calls the page's own controller method and
 *    reads the same view variable the badge prints, so history fills even if nobody opens the page;
 *  - browser pages (no controller): the page reports its live value when opened (needs the page open that day).
 * Every page also reports its live value when opened.
 */
class YesterdayBadgeRegistry
{
    public const PAGE_PREFIX = 'ybadge:';

    /** @return array<string, array{label: string, controller?: class-string, method?: string, key?: string, fields: array<string, array{label: string, format: string, invert?: bool}>}> */
    public static function pages(): array
    {
        $ySales = ['y_sales' => ['label' => 'Y Sales', 'format' => 'money']];

        $server = static fn (string $label, string $controller, string $method, string $key = 'salesYesterday') => [
            'label' => $label,
            'controller' => $controller,
            'method' => $method,
            'key' => $key,
            'fields' => ['y_sales' => ['label' => 'Y Sales', 'format' => 'money']],
        ];

        return [
            'amazon_daily_sales' => $server('Amazon', AmazonSalesController::class, 'index'),
            'walmart_daily_sales' => $server('Walmart', WalmartSalesController::class, 'index'),
            'ebay_daily_sales' => $server('eBay', EbaySalesController::class, 'index'),
            'ebay2_daily_sales' => $server('eBay 2', Ebay2SalesController::class, 'index'),
            'ebay3_daily_sales' => $server('eBay 3', Ebay3SalesController::class, 'index'),
            'doba_daily_sales' => $server('Doba', DobaSalesController::class, 'index'),
            'newegg_daily_sales' => $server('Newegg', NeweggSalesController::class, 'index'),
            'macys_daily_sales' => $server("Macy's", MacysSalesController::class, 'index'),
            'bestbuy_daily_sales' => $server('Best Buy', BestBuySalesController::class, 'index'),
            'alibaba_daily_sales' => $server('Alibaba', AlibabaSalesController::class, 'index'),
            'shopify_b2c_daily_sales' => $server('Shopify B2C', ShopifyB2CSalesController::class, 'index'),
            'shopify_b2b_daily_sales' => $server('Shopify B2B', ShopifyB2BSalesController::class, 'index'),
            'shopify_wholesales_sales' => $server('Shopify Wholesale', ShopifyWholesalesController::class, 'index'),
            'tiktok_daily_sales' => $server('TikTok', TikTokSalesController::class, 'index'),
            'tiktok_two_daily_sales' => $server('TikTok 2', TikTokSalesController::class, 'indexTwo'),
            'mercari_with_ship' => $server('Mercari (with ship)', MercariController::class, 'mercariTabulatorView'),
            'mercari_without_ship' => $server('Mercari (without ship)', MercariController::class, 'mercariWithoutShipView'),
            'shein_tabulator' => $server('Shein', SheinController::class, 'sheinTabulatorView'),
            'wayfair_daily_sales' => $server('Wayfair', WayfairSalesController::class, 'index', 'ySales'),
            'facebook_marketplace' => $server('Facebook Marketplace', FacebookMarketplaceController::class, 'index', 'ySales'),

            // Browser-computed Y badges (value comes from the loaded table, so only saved when the page is opened).
            'reverb_sales_tabulator' => ['label' => 'Reverb', 'fields' => $ySales],
            'temu_tabulator' => ['label' => 'Temu', 'fields' => self::temuFields()],
            'temu2_tabulator' => ['label' => 'Temu 2', 'fields' => self::temuFields()],
            'temu3_tabulator' => ['label' => 'Temu 3', 'fields' => self::temuFields()],
        ];
    }

    /** @return array<string, array{label: string, format: string}> */
    private static function temuFields(): array
    {
        return [
            'y_sales' => ['label' => 'Y Line Sales', 'format' => 'money'],
            'y_sales_gpft' => ['label' => 'Y Sales GPFT$', 'format' => 'money'],
            'y_sales_roi' => ['label' => 'Y Sales ROI%', 'format' => 'pct'],
            'y_sales_gpft_pct' => ['label' => 'Y Sales GPFT%', 'format' => 'pct'],
        ];
    }

    public static function has(string $page): bool
    {
        return array_key_exists($page, self::pages());
    }

    /** @return list<string> */
    public static function fieldsFor(string $page): array
    {
        return array_keys(self::pages()[$page]['fields'] ?? []);
    }

    public static function storageName(string $page): string
    {
        return self::PAGE_PREFIX . $page;
    }
}
