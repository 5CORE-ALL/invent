<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\DepopController;
use App\Http\Controllers\MarketPlace\InstagramAnalyticsController;
use App\Http\Controllers\MarketPlace\VintedAnalyticsController;
use App\Http\Controllers\Sales\AlibabaSalesController;
use App\Http\Controllers\Sales\FacebookMarketplaceController;
use App\Models\AlibabaSheetPrice;
use App\Models\FbMarketplacePriceSoldData;
use App\Models\InstagramPricing;
use App\Models\MarketplacePercentage;
use App\Models\MercariDailyData;
use App\Models\MercariWoShipDataView;
use App\Models\MercariWoShipPriceSoldData;
use App\Models\MercariWShipDataView;
use App\Models\MercariWShipPriceSoldData;
use App\Models\NeweggDataView;
use App\Models\NeweggListingView;
use App\Models\NeweggPricing;
use App\Models\PLSDataView;
use App\Models\PLSProduct;
use App\Models\ProductMaster;
use App\Models\Temu3Metric;
use App\Models\VintedPricing;
use App\Models\WalmartPricingSales;
use App\Models\WayfairDailyData;
use App\Models\WayfairDataView;
use App\Models\WayfairPricingPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Channels on the marketplace Analytics menu that Master Analytics did not
 * already load. Each row uses that channel's price, L30, and profit formula.
 */
class PricingMasterExtraChannels
{
    /** @var array<string, mixed>|null */
    private static ?array $lookups = null;

    /**
     * @return list<array<string, mixed>>
     */
    public static function rowsFor(string $sku, float $lp, float $ship, float $temuShip): array
    {
        $lookups = self::lookups();
        $key = self::upper($sku);
        $keyNs = str_replace(' ', '', $key);
        if ($key === '') {
            return [];
        }

        $rows = [];
        $rows[] = self::styleRow('Vinted', $key, $lookups['vinted_price'], $lookups['vinted_sales'], $lp, $ship, (float) $lookups['vinted_margin']);
        $rows[] = self::styleRow('Instagram Shop', $key, $lookups['ig_price'], $lookups['ig_sales'], $lp, 0.0, (float) $lookups['ig_margin']);
        $rows[] = self::wayfairRow($key, $lp, $lookups);
        $rows[] = self::temu3Row($key, $lp, $temuShip, $lookups);
        $rows[] = self::alibabaRow($key, $lp, $lookups);
        $rows[] = self::mercariRow('Mercari w Ship', $key, $keyNs, $lp, $ship, $lookups['mercari_w_price'], $lookups['mercari_w_sprice'], $lookups['mercari_w_l30'], (float) $lookups['mercari_w_margin']);
        $rows[] = self::mercariRow('Mercari w/o Ship', $key, $keyNs, $lp, 0.0, $lookups['mercari_wo_price'], $lookups['mercari_wo_sprice'], $lookups['mercari_wo_l30'], (float) $lookups['mercari_wo_margin']);
        $rows[] = self::fbRow($key, $keyNs, $lp, $lookups);
        $rows[] = self::plsRow($key, $lp, $ship, $lookups);
        $rows[] = self::neweggRow($sku, $lp, $ship, $lookups);
        $rows[] = self::walmartRow($key, $lp, $ship, $lookups);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function breakdownRow(array $extra, string $fullSku, float $lp): array
    {
        $price = (float) ($extra['price'] ?? 0);
        $l30 = (int) ($extra['l30'] ?? 0);
        $listed = $price > 0 || $l30 > 0;

        return [
            'marketplace' => $extra['marketplace'],
            'sku' => $listed ? $fullSku : 'Not Listed',
            'price' => round($price, 2),
            'views' => $extra['views'],
            'l30' => $l30,
            'gpft' => $extra['gpft'],
            'ad' => 0,
            'tacos_ch' => 0,
            'npft' => $extra['gpft'],
            'is_listed' => $listed,
            'sprice' => (float) ($extra['sprice'] ?? 0) > 0 ? round((float) $extra['sprice'], 2) : 0,
            'sgpft' => $extra['sgpft'] ?? 0,
            'sroi' => $extra['sroi'] ?? 0,
            'spft' => $extra['sgpft'] ?? 0,
            'lp' => $lp,
            'ship' => (float) ($extra['ship'] ?? 0),
            'margin' => (float) ($extra['margin'] ?? 0),
            'pushed_by' => null,
            'pushed_at' => null,
            'buyer_link' => null,
            'seller_link' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lookups(): array
    {
        if (self::$lookups !== null) {
            return self::$lookups;
        }

        self::$lookups = [
            'vinted_margin' => VintedAnalyticsController::marginFactor(),
            'vinted_price' => self::pricingMap(VintedPricing::class, 'vinted_pricing'),
            'vinted_sales' => self::safeSales(static fn () => VintedAnalyticsController::salesL30BySku()),
            'ig_margin' => InstagramAnalyticsController::marginFactor(),
            'ig_price' => self::pricingMap(InstagramPricing::class, 'instagram_pricing'),
            'ig_sales' => self::safeSales(static fn () => InstagramAnalyticsController::salesL30BySku()),
            'wayfair_margin' => self::percentFactor('Wayfair'),
            'wayfair_price' => self::wayfairPrices(),
            'wayfair_l30' => self::wayfairL30(),
            'wayfair_sprice' => self::jsonSpriceMap(WayfairDataView::class, 'wayfair_data_view', 'SPRICE'),
            'temu3' => self::temu3BySku(),
            'temu_margin' => TemuShopifySalesService::temuMarginDecimal(),
            'alibaba_margin' => MarketplacePercentage::takeHomeDecimal('Alibaba'),
            'alibaba_price' => self::alibabaPrices(),
            'alibaba_l30' => self::alibabaL30(),
            'mercari_w_margin' => self::percentFactor('MercariWShip'),
            'mercari_w_price' => self::priceColumnMap(MercariWShipPriceSoldData::class, 'mercari_wship_price_sold_data'),
            'mercari_w_sprice' => self::jsonSpriceMap(MercariWShipDataView::class, 'mercari_w_ship_data_views', 'SPRICE'),
            'mercari_wo_margin' => self::percentFactor('MercariWoShip'),
            'mercari_wo_price' => self::priceColumnMap(MercariWoShipPriceSoldData::class, 'mercari_woship_price_sold_data'),
            'mercari_wo_sprice' => self::jsonSpriceMap(MercariWoShipDataView::class, 'mercari_wo_ship_data_views', 'SPRICE'),
            'mercari_w_l30' => [],
            'mercari_wo_l30' => [],
            'fb_margin' => self::fbMargin(),
            'fb_price' => self::fbPrices(),
            'fb_l30' => self::safeSales(static fn () => FacebookMarketplaceController::l30MetricsBySku()),
            'fb_latest' => self::safeSales(static fn () => FacebookMarketplaceController::latestSoldPriceBySku()),
            'pls_margin' => MarketplacePercentage::takeHomeDecimal('PLS', 'Pls'),
            'pls' => self::plsBySku(),
            'pls_sprice' => self::jsonSpriceMap(PLSDataView::class, 'pls_data_views', 'sprice'),
            'newegg_margin' => self::neweggFactor(),
            'newegg_price' => ['exact' => [], 'norm' => []],
            'newegg_l30' => ['exact' => [], 'norm' => []],
            'newegg_views' => ['exact' => [], 'norm' => []],
            'newegg_sprice' => [],
            'walmart_margin' => self::percentFactor('Walmart'),
            'walmart' => self::walmartBySku(),
        ];

        [$w, $wo] = self::mercariL30();
        self::$lookups['mercari_w_l30'] = $w;
        self::$lookups['mercari_wo_l30'] = $wo;
        self::$lookups['newegg_price'] = self::neweggPrices();
        self::$lookups['newegg_l30'] = self::neweggL30();
        self::$lookups['newegg_views'] = self::neweggViews();
        self::$lookups['newegg_sprice'] = self::neweggSprice();

        return self::$lookups;
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<string, array<string, mixed>>  $sales
     * @return array<string, mixed>
     */
    private static function styleRow(string $name, string $key, array $pricing, array $sales, float $lp, float $ship, float $margin): array
    {
        $row = $pricing[$key] ?? null;
        $sale = $sales[$key] ?? ['qty' => 0, 'sales' => 0.0, 'fallback_price' => 0.0];
        $list = $row ? (float) ($row->price ?? 0) : 0.0;
        if ($list <= 0) {
            $list = (float) ($sale['fallback_price'] ?? 0);
        }
        $l30 = (int) ($sale['qty'] ?? 0);
        $sold = (float) ($sale['sales'] ?? 0);
        $price = DepopController::effectiveSellPrice($list, $sold, $l30);
        $sprice = $row ? (float) ($row->sprice ?? 0) : 0.0;
        $profit = self::marginProfit($price, $lp, $ship, $margin);
        $suggested = $sprice > 0 ? self::marginProfit($sprice, $lp, $ship, $margin) : ['gpft' => 0.0, 'groi' => 0.0];

        return self::blank($name, $price, $list, $l30, null, $profit, $margin, $ship, $sprice, $suggested);
    }

    /**
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    private static function wayfairRow(string $key, float $lp, array $lookups): array
    {
        $price = (float) ($lookups['wayfair_price'][$key] ?? 0);
        $l30 = (int) ($lookups['wayfair_l30'][$key] ?? 0);
        $margin = (float) $lookups['wayfair_margin'];
        $sprice = (float) ($lookups['wayfair_sprice'][$key] ?? 0);
        $profit = self::marginProfit($price, $lp, 0.0, $margin);
        $suggested = $sprice > 0 ? self::marginProfit($sprice, $lp, 0.0, $margin) : ['gpft' => 0.0, 'groi' => 0.0];

        return self::blank('Wayfair', $price, $price, $l30, null, $profit, $margin, 0.0, $sprice, $suggested);
    }

    /**
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    private static function temu3Row(string $key, float $lp, float $temuShip, array $lookups): array
    {
        $metric = $lookups['temu3'][$key] ?? null;
        $base = $metric ? (float) $metric['base'] : 0.0;
        $price = TemuShopifySalesService::computeFullTemuPrice($base);
        $l30 = $metric ? (int) $metric['l30'] : 0;
        $views = $metric ? (int) $metric['views'] : 0;
        $margin = (float) $lookups['temu_margin'];
        $gpft = TemuShopifySalesService::computeGpftPercent($price, $margin, $lp, $temuShip);
        $groi = ($lp > 0 && $price > 0)
            ? (($price * $margin - $lp - $temuShip) / $lp) * 100
            : 0.0;

        return self::blank('Temu 3', $price, $base, $l30, $views, ['gpft' => round($gpft, 2), 'groi' => round($groi, 2)], $margin, $temuShip, 0.0, ['gpft' => 0.0, 'groi' => 0.0]);
    }

    /**
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    private static function alibabaRow(string $key, float $lp, array $lookups): array
    {
        $stripped = self::upper((string) preg_replace('/\s*\d+\s*pcs?\b/i', '', $key));
        $price = (float) ($lookups['alibaba_price'][$key] ?? $lookups['alibaba_price'][$stripped] ?? 0);
        $l30 = (int) ($lookups['alibaba_l30'][$key]['qty'] ?? $lookups['alibaba_l30'][$stripped]['qty'] ?? 0);
        $margin = (float) $lookups['alibaba_margin'];
        $profit = self::marginProfit($price, $lp, 0.0, $margin);

        return self::blank('Alibaba', $price, $price, $l30, null, $profit, $margin, 0.0, 0.0, ['gpft' => 0.0, 'groi' => 0.0]);
    }

    /**
     * @param  array<string, float>  $prices
     * @param  array<string, float>  $sprices
     * @param  array<string, int>  $l30s
     * @return array<string, mixed>
     */
    private static function mercariRow(string $name, string $key, string $keyNs, float $lp, float $ship, array $prices, array $sprices, array $l30s, float $margin): array
    {
        $price = (float) ($prices[$key] ?? $prices[$keyNs] ?? 0);
        $l30 = (int) ($l30s[$key] ?? $l30s[$keyNs] ?? 0);
        $sprice = (float) ($sprices[$key] ?? $sprices[$keyNs] ?? 0);
        $profit = self::marginProfit($price, $lp, $ship, $margin);
        $suggested = $sprice > 0 ? self::marginProfit($sprice, $lp, $ship, $margin) : ['gpft' => 0.0, 'groi' => 0.0];

        return self::blank($name, $price, $price, $l30, null, $profit, $margin, $ship, $sprice, $suggested);
    }

    /**
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    private static function fbRow(string $key, string $keyNs, float $lp, array $lookups): array
    {
        $uploaded = (float) ($lookups['fb_price'][$key] ?? $lookups['fb_price'][$keyNs] ?? 0);
        $l30Row = $lookups['fb_l30'][$key] ?? $lookups['fb_l30'][$keyNs] ?? null;
        $l30Price = (float) (is_array($l30Row) ? ($l30Row['price'] ?? 0) : 0);
        $latest = (float) ($lookups['fb_latest'][$key] ?? $lookups['fb_latest'][$keyNs] ?? 0);
        $price = $uploaded > 0 ? $uploaded : ($l30Price > 0 ? $l30Price : $latest);
        $l30 = (int) (is_array($l30Row) ? ($l30Row['qty'] ?? 0) : 0);
        $margin = (float) $lookups['fb_margin'];
        $profit = self::marginProfit($price, $lp, 0.0, $margin);

        return self::blank('FB Marketplace', $price, $uploaded > 0 ? $uploaded : $price, $l30, 0, $profit, $margin, 0.0, 0.0, ['gpft' => 0.0, 'groi' => 0.0]);
    }

    /**
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    private static function plsRow(string $key, float $lp, float $ship, array $lookups): array
    {
        $row = $lookups['pls'][$key] ?? null;
        $price = $row ? (float) $row['price'] : 0.0;
        $l30 = $row ? (int) $row['l30'] : 0;
        $views = $row ? (int) $row['views'] : 0;
        $margin = (float) $lookups['pls_margin'];
        $sprice = (float) ($lookups['pls_sprice'][$key] ?? 0);
        $profit = self::marginProfit($price, $lp, $ship, $margin);
        $suggested = $sprice > 0 ? self::marginProfit($sprice, $lp, $ship, $margin) : ['gpft' => 0.0, 'groi' => 0.0];

        return self::blank('PLS', $price, $price, $l30, $views, $profit, $margin, $ship, $sprice, $suggested);
    }

    /**
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    private static function neweggRow(string $sku, float $lp, float $ship, array $lookups): array
    {
        $exact = self::exactSku($sku);
        $norm = self::normSku($sku);
        $price = (float) ($lookups['newegg_price']['exact'][$exact] ?? $lookups['newegg_price']['norm'][$norm] ?? 0);
        $l30 = (int) ($lookups['newegg_l30']['exact'][$exact] ?? $lookups['newegg_l30']['norm'][$norm] ?? 0);
        $views = (int) ($lookups['newegg_views']['exact'][$exact] ?? $lookups['newegg_views']['norm'][$norm] ?? 0);
        if ($l30 <= 0) {
            $sheetUnits = (int) ($lookups['newegg_views']['units'][$exact] ?? $lookups['newegg_views']['units'][$norm] ?? 0);
            if ($sheetUnits > 0) {
                $l30 = $sheetUnits;
            }
        }
        $margin = (float) $lookups['newegg_margin'];
        $sprice = (float) ($lookups['newegg_sprice'][$exact] ?? $lookups['newegg_sprice'][self::upper($sku)] ?? 0);
        $profit = self::marginProfit($price, $lp, $ship, $margin);
        $suggested = $sprice > 0 ? self::marginProfit($sprice, $lp, $ship, $margin) : ['gpft' => 0.0, 'groi' => 0.0];

        return self::blank('Newegg', $price, $price, $l30, $views, $profit, $margin, $ship, $sprice, $suggested);
    }

    /**
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    private static function walmartRow(string $key, float $lp, float $ship, array $lookups): array
    {
        $row = $lookups['walmart'][$key] ?? null;
        $price = $row ? (float) $row['price'] : 0.0;
        $l30 = $row ? (int) $row['l30'] : 0;
        $views = $row ? (int) $row['views'] : 0;
        $margin = (float) $lookups['walmart_margin'];
        $profit = self::marginProfit($price, $lp, $ship, $margin);

        return self::blank('Walmart', $price, $price, $l30, $views, $profit, $margin, $ship, 0.0, ['gpft' => 0.0, 'groi' => 0.0]);
    }

    /**
     * @param  array{gpft: float, groi: float}  $profit
     * @param  array{gpft: float, groi: float}  $suggested
     * @return array<string, mixed>
     */
    private static function blank(string $name, float $price, float $listPrice, int $l30, ?int $views, array $profit, float $margin, float $ship, float $sprice, array $suggested): array
    {
        return [
            'marketplace' => $name,
            'price' => $price,
            'list_price' => $listPrice,
            'l30' => $l30,
            'views' => $views,
            'gpft' => $profit['gpft'],
            'groi' => $profit['groi'],
            'margin' => $margin,
            'ship' => $ship,
            'sprice' => $sprice,
            'sgpft' => $suggested['gpft'],
            'sroi' => $suggested['groi'],
        ];
    }

    /**
     * @return array{gpft: float, groi: float}
     */
    private static function marginProfit(float $price, float $lp, float $ship, float $margin): array
    {
        if ($price <= 0) {
            return ['gpft' => 0.0, 'groi' => 0.0];
        }
        $profit = ($price * $margin) - $lp - $ship;

        return [
            'gpft' => round(($profit / $price) * 100, 2),
            'groi' => $lp > 0 ? round(($profit / $lp) * 100, 2) : 0.0,
        ];
    }

    private static function percentFactor(string $marketplace, float $default = 100.0): float
    {
        try {
            $row = MarketplacePercentage::where('marketplace', $marketplace)->first();
            $pct = $row ? (float) ($row->percentage ?? $default) : $default;

            return $pct / 100;
        } catch (\Throwable $e) {
            return $default / 100;
        }
    }

    private static function fbMargin(): float
    {
        try {
            $row = MarketplacePercentage::where('marketplace', 'FB Marketplace')->first()
                ?: MarketplacePercentage::where('marketplace', 'FBMarketplace')->first();
            $pct = $row && $row->percentage !== null ? (float) $row->percentage : 100.0;

            return $pct / 100;
        } catch (\Throwable $e) {
            return 1.0;
        }
    }

    private static function neweggFactor(): float
    {
        try {
            $row = MarketplacePercentage::where('marketplace', 'Neweggb2c')->first();
            $percentage = $row ? (float) $row->percentage : 80.0;
            $adUpdates = $row ? (float) ($row->ad_updates ?? 0) : 0.0;
            $margin = $percentage - $adUpdates;

            return $margin > 0 ? $margin / 100 : 0.80;
        } catch (\Throwable $e) {
            return 0.80;
        }
    }

    /**
     * @param  class-string  $model
     * @return array<string, object>
     */
    private static function pricingMap(string $model, string $table): array
    {
        $out = [];
        try {
            if (! Schema::hasTable($table)) {
                return [];
            }
            foreach ($model::query()->whereNotNull('sku')->where('sku', '!=', '')->get(['sku', 'price', 'sprice']) as $row) {
                $key = self::upper((string) $row->sku);
                if ($key !== '') {
                    $out[$key] = $row;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics '.$table.' skipped: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @param  class-string  $model
     * @return array<string, float>
     */
    private static function priceColumnMap(string $model, string $table): array
    {
        $out = [];
        try {
            if (! Schema::hasTable($table)) {
                return [];
            }
            foreach ($model::query()->whereNotNull('sku')->get(['sku', 'price']) as $row) {
                $key = self::upper((string) $row->sku);
                $ns = str_replace(' ', '', $key);
                $price = (float) ($row->price ?? 0);
                if ($key !== '') {
                    $out[$key] = $price;
                }
                if ($ns !== '') {
                    $out[$ns] = $price;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics '.$table.' skipped: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @param  class-string  $model
     * @return array<string, float>
     */
    private static function jsonSpriceMap(string $model, string $table, string $keyName): array
    {
        $out = [];
        try {
            if (! Schema::hasTable($table)) {
                return [];
            }
            foreach ($model::query()->get(['sku', 'value']) as $row) {
                $key = self::upper((string) ($row->sku ?? ''));
                if ($key === '') {
                    continue;
                }
                $val = is_array($row->value) ? $row->value : (json_decode((string) ($row->value ?? ''), true) ?: []);
                $stored = $val[$keyName] ?? $val['SPRICE'] ?? $val['sprice'] ?? null;
                if (is_numeric($stored) && (float) $stored > 0) {
                    $out[$key] = (float) $stored;
                    $out[str_replace(' ', '', $key)] = (float) $stored;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics '.$table.' SPRICE skipped: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @param  callable(): array  $loader
     * @return array<string, mixed>
     */
    private static function safeSales(callable $loader): array
    {
        try {
            return $loader();
        } catch (\Throwable $e) {
            Log::warning('Master Analytics sales map skipped: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return array<string, float>
     */
    private static function wayfairPrices(): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('wayfair_pricing_prices')) {
                return [];
            }
            foreach (WayfairPricingPrice::query()->get(['sku', 'price']) as $row) {
                $key = self::upper((string) $row->sku);
                if ($key !== '') {
                    $out[$key] = (float) ($row->price ?? 0);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Wayfair prices skipped: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private static function wayfairL30(): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('wayfair_daily_data')) {
                return [];
            }
            $rows = WayfairDailyData::query()
                ->selectRaw('sku, SUM(COALESCE(quantity, 0)) as al30')
                ->where('period', 'l30')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->groupBy('sku')
                ->get();
            foreach ($rows as $row) {
                $key = self::upper((string) $row->sku);
                if ($key !== '') {
                    $out[$key] = (int) $row->al30;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Wayfair L30 skipped: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @return array<string, array{base: float, l30: int, views: int}>
     */
    private static function temu3BySku(): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('temu3_metrics')) {
                return [];
            }
            foreach (Temu3Metric::query()->whereNotNull('sku')->get(['sku', 'base_price', 'quantity_purchased_l30', 'product_clicks_l30']) as $row) {
                $key = self::upper((string) $row->sku);
                if ($key === '') {
                    continue;
                }
                if (! isset($out[$key])) {
                    $out[$key] = ['base' => 0.0, 'l30' => 0, 'views' => 0];
                }
                $base = (float) ($row->base_price ?? 0);
                if ($base > $out[$key]['base']) {
                    $out[$key]['base'] = $base;
                }
                $out[$key]['l30'] += (int) ($row->quantity_purchased_l30 ?? 0);
                $out[$key]['views'] += (int) ($row->product_clicks_l30 ?? 0);
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Temu 3 skipped: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private static function alibabaPrices(): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('alibaba_sheet_prices')) {
                return [];
            }
            foreach (AlibabaSheetPrice::query()->whereNotNull('sku')->get(['sku', 'sku_price']) as $row) {
                $key = self::upper((string) $row->sku);
                if ($key !== '' && ! isset($out[$key])) {
                    $out[$key] = $row->sku_price !== null ? (float) $row->sku_price : 0.0;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Alibaba prices skipped: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @return array<string, array{qty: int, sales: float}>
     */
    private static function alibabaL30(): array
    {
        try {
            return app(AlibabaSalesController::class)->l30SkuTotals();
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Alibaba L30 skipped: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private static function mercariL30(): array
    {
        $withShip = [];
        $withoutShip = [];
        try {
            if (! Schema::hasTable('mercari_daily_data')) {
                return [$withShip, $withoutShip];
            }
            $lookup = [];
            foreach (ProductMaster::query()->whereNotNull('sku')->pluck('sku') as $sku) {
                $upper = self::upper((string) $sku);
                if (strlen($upper) < 3) {
                    continue;
                }
                $lookup[$upper] = $upper;
                $lookup[str_replace([' ', '-', '_'], '', $upper)] = $upper;
            }
            $orders = MercariDailyData::query()
                ->where('sold_date', '>=', Carbon::now()->subDays(30))
                ->whereNull('canceled_date')
                ->where(function ($query) {
                    $query->whereNull('order_status')
                        ->orWhere('order_status', 'not like', '%cancel%');
                })
                ->get(['item_title', 'buyer_shipping_fee']);
            foreach ($orders as $order) {
                $matched = self::matchTitleSku((string) ($order->item_title ?? ''), $lookup);
                if ($matched === null) {
                    continue;
                }
                $fee = $order->buyer_shipping_fee;
                if ($fee !== null) {
                    $withShip[$matched] = ($withShip[$matched] ?? 0) + 1;
                }
                if (is_numeric($fee) && (float) $fee > 0) {
                    $withoutShip[$matched] = ($withoutShip[$matched] ?? 0) + 1;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Mercari L30 skipped: '.$e->getMessage());
        }

        return [$withShip, $withoutShip];
    }

    /**
     * @param  array<string, string>  $lookup
     */
    private static function matchTitleSku(string $title, array $lookup): ?string
    {
        if ($title === '' || ! preg_match('/\b([A-Za-z0-9\s\-]{3,})\s*$/', $title, $matches)) {
            return null;
        }
        $last = trim($matches[1]);
        $variants = [
            self::upper($last),
            str_replace(' ', '', self::upper($last)),
            str_replace([' ', '-', '_'], '', self::upper($last)),
        ];
        $words = explode(' ', $last);
        if (count($words) > 1 && strlen($words[0]) <= 3) {
            $without = trim(implode(' ', array_slice($words, 1)));
            $variants[] = self::upper($without);
            $variants[] = str_replace([' ', '-', '_'], '', self::upper($without));
        }
        foreach ($variants as $variant) {
            if ($variant !== '' && isset($lookup[$variant])) {
                return $lookup[$variant];
            }
        }

        return null;
    }

    /**
     * @return array<string, float>
     */
    private static function fbPrices(): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('fb_marketplace_price_sold_data')) {
                return [];
            }
            foreach (FbMarketplacePriceSoldData::query()->get(['sku', 'price']) as $row) {
                $key = self::upper((string) $row->sku);
                if ($key === '') {
                    continue;
                }
                $price = (float) ($row->price ?? 0);
                $out[$key] = $price;
                $out[str_replace(' ', '', $key)] = $price;
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics FB prices skipped: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @return array<string, array{price: float, l30: int, views: int}>
     */
    private static function plsBySku(): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('pls_products')) {
                return [];
            }
            foreach (PLSProduct::query()->whereNotNull('sku')->get(['sku', 'price', 'p_l30', 'views']) as $row) {
                $key = self::upper((string) $row->sku);
                if ($key !== '') {
                    $out[$key] = [
                        'price' => (float) ($row->price ?? 0),
                        'l30' => (int) ($row->p_l30 ?? 0),
                        'views' => (int) ($row->views ?? 0),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics PLS skipped: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @return array{exact: array<string, float>, norm: array<string, float>}
     */
    private static function neweggPrices(): array
    {
        $exact = [];
        $norm = [];
        try {
            if (! Schema::hasTable('newegg_pricing')) {
                return ['exact' => [], 'norm' => []];
            }
            foreach (NeweggPricing::query()->get(['seller_part_number', 'selling_price']) as $row) {
                $e = self::exactSku((string) $row->seller_part_number);
                $n = self::normSku((string) $row->seller_part_number);
                $price = $row->selling_price !== null ? (float) $row->selling_price : 0.0;
                if ($e !== '' && ! isset($exact[$e])) {
                    $exact[$e] = $price;
                }
                if ($n !== '' && ! isset($norm[$n])) {
                    $norm[$n] = $price;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Newegg prices skipped: '.$e->getMessage());
        }

        return ['exact' => $exact, 'norm' => $norm];
    }

    /**
     * @return array{exact: array<string, int>, norm: array<string, int>}
     */
    private static function neweggL30(): array
    {
        $exact = [];
        $norm = [];
        try {
            if (! Schema::hasTable('newegg_order_items') || ! Schema::hasTable('newegg_orders')) {
                return ['exact' => [], 'norm' => []];
            }
            $raw = DB::table('newegg_order_items as i')
                ->join('newegg_orders as o', 'o.order_number', '=', 'i.order_number')
                ->where('o.order_date', '>=', now()->subDays(30))
                ->where(function ($q) {
                    $q->whereNull('o.order_status_description')
                        ->orWhere('o.order_status_description', 'not like', '%void%');
                })
                ->whereNotNull('i.seller_part_number')
                ->groupBy('i.seller_part_number')
                ->select('i.seller_part_number', DB::raw('SUM(i.ordered_qty) as qty'))
                ->pluck('qty', 'seller_part_number');
            foreach ($raw as $spn => $qty) {
                $e = self::exactSku((string) $spn);
                $n = self::normSku((string) $spn);
                $units = (int) $qty;
                if ($e !== '') {
                    $exact[$e] = ($exact[$e] ?? 0) + $units;
                }
                if ($n !== '') {
                    $norm[$n] = ($norm[$n] ?? 0) + $units;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Newegg L30 skipped: '.$e->getMessage());
        }

        return ['exact' => $exact, 'norm' => $norm];
    }

    /**
     * @return array{exact: array<string, int>, norm: array<string, int>, units: array<string, int>}
     */
    private static function neweggViews(): array
    {
        $exact = [];
        $norm = [];
        $units = [];
        try {
            if (! Schema::hasTable('newegg_listing_views')) {
                return ['exact' => [], 'norm' => [], 'units' => []];
            }
            foreach (NeweggListingView::query()->get(['seller_part_number', 'page_views', 'sessions', 'units_sold']) as $row) {
                $e = self::exactSku((string) ($row->seller_part_number ?? ''));
                $n = self::normSku((string) ($row->seller_part_number ?? ''));
                $pageViews = (int) ($row->page_views ?? 0);
                $sessions = (int) ($row->sessions ?? 0);
                $views = $pageViews > 0 ? $pageViews : $sessions;
                $sold = (int) ($row->units_sold ?? 0);
                if ($e !== '' && ! isset($exact[$e])) {
                    $exact[$e] = $views;
                    $units[$e] = $sold;
                }
                if ($n !== '' && ! isset($norm[$n])) {
                    $norm[$n] = $views;
                    $units[$n] = $sold;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Newegg views skipped: '.$e->getMessage());
        }

        return ['exact' => $exact, 'norm' => $norm, 'units' => $units];
    }

    /**
     * @return array<string, float>
     */
    private static function neweggSprice(): array
    {
        return self::jsonSpriceMap(NeweggDataView::class, 'newegg_data_views', 'SPRICE');
    }

    /**
     * @return array<string, array{price: float, l30: int, views: int}>
     */
    private static function walmartBySku(): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('walmart_pricing')) {
                return [];
            }
            foreach (WalmartPricingSales::query()->whereNotNull('sku')->get(['sku', 'current_price', 'l30_qty', 'page_views', 'views']) as $row) {
                $key = self::upper((string) $row->sku);
                if ($key === '') {
                    continue;
                }
                $pageViews = (int) ($row->page_views ?? 0);
                $views = (int) ($row->views ?? 0);
                $out[$key] = [
                    'price' => (float) ($row->current_price ?? 0),
                    'l30' => (int) ($row->l30_qty ?? 0),
                    'views' => $pageViews > 0 ? $pageViews : $views,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Master Analytics Walmart skipped: '.$e->getMessage());
        }

        return $out;
    }

    private static function upper(string $value): string
    {
        $value = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}"], ' ', $value);

        return strtoupper(trim($value));
    }

    private static function exactSku(string $sku): string
    {
        $sku = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}"], ' ', $sku);

        return strtoupper(preg_replace('/\s+/', ' ', trim($sku)) ?? '');
    }

    private static function normSku(string $sku): string
    {
        $sku = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}"], ' ', $sku);

        return strtoupper(preg_replace('/[^A-Za-z0-9.]/', '', $sku) ?? '');
    }
}
