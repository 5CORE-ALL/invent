<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ShopifyB2BDailyData;
use App\Models\ShopifySku;
use App\Services\PricingErrorsFixCvrCacheBuilder;
use App\Services\TemuShopifySalesService;
use App\Support\ReverbPricingViews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Views Master — same SKU × channel rows as /pricing-errors-fix.
 * Only channels that store a views metric, and only rows with views > 0.
 */
class ViewsMasterController extends Controller
{
    /**
     * Analytics pages that store page views.
     * Faire, Shein, AliExpress, and Shopify B2B are zeroed on /pricing-errors-fix;
     * their views are filled from the same tables as those analytics pages.
     * Best Buy and Purchasing Power analytics pages have no Views metric.
     *
     * @var array<string, string>
     */
    public const CHANNELS = [
        'amazon' => 'Amazon',
        'ebay' => 'eBay 1',
        'ebay2' => 'eBay 2',
        'ebay3' => 'eBay 3',
        'temu' => 'Temu',
        'temu2' => 'Temu 2',
        'doba' => 'Doba',
        'tiktok' => 'TikTok 1',
        'tiktok2' => 'TikTok 2',
        'macy' => "Macy's",
        'reverb' => 'Reverb',
        'topdawg' => 'TopDawg',
        'sb2c' => 'Shopify B2C',
        'sb2b' => 'Shopify B2B',
        'shein' => 'Shein',
        'faire' => 'Faire',
        'aliexpress' => 'AliExpress',
    ];

    public function index(): View
    {
        $channels = [];
        foreach (self::CHANNELS as $key => $label) {
            $channels[] = ['key' => $key, 'label' => $label];
        }

        return view('market-places.views_master_view', [
            'channels' => $channels,
        ]);
    }

    public function dataJson(Request $request): JsonResponse
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $wanted = $this->wantedKeys($request);
            $built = app(PricingErrorsFixCvrCacheBuilder::class)->build($wanted, null, true);
            $analyticsViews = $this->analyticsViewsMaps();

            $rows = [];
            $present = [];
            foreach ($built['rows'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $pull = strtolower((string) ($row['pull_key'] ?? ''));
                if (! isset(self::CHANNELS[$pull])) {
                    continue;
                }
                $row = $this->applyAnalyticsViews($row, $pull, $analyticsViews);
                $row = $this->applyAnalyticsMargins($row, $pull);
                if ($pull === 'reverb') {
                    $rawViews = (float) ($row['views'] ?? 0);
                    $row['views'] = ReverbPricingViews::scale($rawViews);
                    $row['cvr'] = ReverbPricingViews::cvrPercent((float) ($row['l30'] ?? 0), $rawViews, 1);
                }
                $views = $row['views'] ?? null;
                if (! is_numeric($views) || (float) $views <= 0) {
                    continue;
                }
                $rows[] = $row;
                $present[$pull] = self::CHANNELS[$pull];
            }

            $channels = [];
            foreach (self::CHANNELS as $key => $label) {
                if (isset($present[$key])) {
                    $channels[] = ['key' => $key, 'label' => $label];
                }
            }

            return response()->json([
                'data' => $rows,
                'channels' => $channels,
                'meta' => [
                    'total' => count($rows),
                    'channels' => array_column($channels, 'key'),
                    'errors' => $built['errors'] ?? [],
                    'source' => 'pricing-errors-fix',
                ],
            ])->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            Log::error('Views Master dataJson: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to fetch views data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GPFT / GROI / NPFT / NROI using each channel's analytics formula.
     * Pricing-errors-fix math is not used.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyAnalyticsMargins(array $row, string $pull): array
    {
        $price = (float) ($row['price'] ?? 0);
        $lp = (float) ($row['lp'] ?? 0);
        $ship = (float) ($row['ship'] ?? 0);
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin = $margin / 100;
        }
        $ads = (float) ($row['ads_pct'] ?? 0);
        $noShip = in_array($pull, ['faire', 'topdawg', 'sb2b'], true);
        $noAds = in_array($pull, ['doba', 'topdawg', 'shein', 'faire', 'aliexpress'], true);
        if ($noShip) {
            $ship = 0.0;
        }
        if ($noAds) {
            $ads = 0.0;
        }

        if (in_array($pull, ['temu', 'temu2'], true)) {
            $base = (float) ($row['base_price'] ?? 0);
            if (! ($base > 0) && $price > 0) {
                $base = $price > 29.98 ? $price : max(0.0, $price - 2.99);
            }
            $rPrice = TemuShopifySalesService::computeRPrice($base);
            $tPrice = TemuShopifySalesService::computeFullTemuPrice($base);
            if ($pull === 'temu2') {
                $ads = 0.0;
            }
            if (! ($rPrice > 0) || ! ($tPrice > 0) || ! ($margin > 0)) {
                $row['gpft'] = null;
                $row['groi'] = null;
                $row['npft'] = null;
                $row['nroi'] = null;

                return $row;
            }
            $gross = TemuShopifySalesService::computeGroiProfit($rPrice, $margin, $lp, $ship);
            $net = $gross - ($tPrice * ($ads / 100));
            $row['gpft'] = round(($gross / $tPrice) * 100, 2);
            $row['groi'] = $lp > 0 ? round(($gross / $lp) * 100, 2) : null;
            $row['npft'] = round(($net / $tPrice) * 100, 2);
            $row['nroi'] = $lp > 0 ? round(($net / $lp) * 100, 2) : null;

            return $row;
        }

        if (! ($price > 0) || ! ($margin > 0)) {
            $row['gpft'] = null;
            $row['groi'] = null;
            $row['npft'] = null;
            $row['nroi'] = null;

            return $row;
        }

        $gross = ($price * $margin) - $lp - $ship;
        $gpft = ($gross / $price) * 100;
        $groi = $lp > 0 ? ($gross / $lp) * 100 : null;
        $net = $gross - ($price * ($ads / 100));
        $row['gpft'] = round($gpft, 2);
        $row['groi'] = $groi === null ? null : round($groi, 2);
        $row['npft'] = round($gpft - $ads, 2);
        $row['nroi'] = $lp > 0 ? round(($net / $lp) * 100, 2) : null;

        return $row;
    }

    /**
     * Views from the analytics page source when /pricing-errors-fix stores 0.
     *
     * @return array{views: array<string, array<string, int>>, cvr: array<string, array<string, float>>, l30: array<string, array<string, int>>}
     */
    private function analyticsViewsMaps(): array
    {
        return [
            'views' => [
                'faire' => $this->viewsBySku('faire_products_sheets', 'views'),
                'shein' => $this->viewsBySku('shein_metrics', 'views'),
                'aliexpress' => $this->viewsBySku('aliexpress_metric', 'views'),
                'sb2b' => $this->shopifyB2bViewsBySku(),
            ],
            'cvr' => [
                'aliexpress' => $this->aliexpressCvrBySku(),
            ],
            'l30' => [
                'sb2b' => $this->shopifyB2bL30BySku(),
            ],
        ];
    }

    /**
     * @param  array{views: array<string, array<string, int>>, cvr: array<string, array<string, float>>, l30: array<string, array<string, int>>}  $maps
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyAnalyticsViews(array $row, string $pull, array $maps): array
    {
        $current = $row['views'] ?? null;
        if (is_numeric($current) && (float) $current > 0) {
            return $row;
        }
        if (! isset($maps['views'][$pull])) {
            return $row;
        }

        $sku = $pull === 'sb2b'
            ? ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''))
            : $this->normSku((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            return $row;
        }

        $views = (int) ($maps['views'][$pull][$sku] ?? 0);
        if ($views <= 0) {
            return $row;
        }

        $row['views'] = $views;
        if (isset($maps['l30'][$pull][$sku])) {
            $row['l30'] = (int) $maps['l30'][$pull][$sku];
        }
        if (isset($maps['cvr'][$pull][$sku]) && (float) $maps['cvr'][$pull][$sku] > 0) {
            $row['cvr'] = round((float) $maps['cvr'][$pull][$sku], 2);
        } else {
            $l30 = (float) ($row['l30'] ?? 0);
            $row['cvr'] = round(($l30 / $views) * 100, 2);
        }

        return $row;
    }

    /**
     * @return array<string, int>
     */
    private function viewsBySku(string $table, string $column): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        $map = [];
        try {
            foreach (DB::table($table)->select('sku', $column)->whereNotNull('sku')->where('sku', '!=', '')->cursor() as $item) {
                $key = $this->normSku((string) $item->sku);
                if ($key === '') {
                    continue;
                }
                $views = (int) ($item->{$column} ?? 0);
                if (! isset($map[$key]) || $views > $map[$key]) {
                    $map[$key] = $views;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Views Master '.$table.' lookup failed: '.$e->getMessage());
        }

        return $map;
    }

    /**
     * @return array<string, float>
     */
    private function aliexpressCvrBySku(): array
    {
        if (! Schema::hasTable('aliexpress_metric') || ! Schema::hasColumn('aliexpress_metric', 'cvr')) {
            return [];
        }

        $map = [];
        try {
            foreach (DB::table('aliexpress_metric')->select('sku', 'views', 'cvr')->whereNotNull('sku')->cursor() as $item) {
                $key = $this->normSku((string) $item->sku);
                if ($key === '' || (int) ($item->views ?? 0) <= 0) {
                    continue;
                }
                $map[$key] = (float) ($item->cvr ?? 0);
            }
        } catch (\Throwable $e) {
            Log::warning('Views Master AliExpress CVR lookup failed: '.$e->getMessage());
        }

        return $map;
    }

    /**
     * Same store as Shopify B2B analytics Views (store_listing_prices.views).
     *
     * @return array<string, int>
     */
    private function shopifyB2bViewsBySku(): array
    {
        if (! Schema::hasTable('store_listing_prices') || ! Schema::hasColumn('store_listing_prices', 'views')) {
            return [];
        }

        $map = [];
        try {
            $query = DB::table('store_listing_prices')->select('sku', 'views')->whereNotNull('sku')->where('sku', '!=', '');
            if (Schema::hasColumn('store_listing_prices', 'is_variant')) {
                $query->addSelect('is_variant');
            }
            foreach ($query->cursor() as $item) {
                $key = ShopifySku::normalizeSkuForShopifyLookup((string) $item->sku);
                if ($key === '') {
                    continue;
                }
                $views = (int) ($item->views ?? 0);
                $isVariant = (bool) ($item->is_variant ?? false);
                if (! isset($map[$key]) || ($isVariant && $views >= $map[$key]) || $views > $map[$key]) {
                    $map[$key] = $views;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Views Master Shopify B2B views lookup failed: '.$e->getMessage());
        }

        return $map;
    }

    /**
     * Shopify B2B L30 units, same source as the B2B analytics page.
     *
     * @return array<string, int>
     */
    private function shopifyB2bL30BySku(): array
    {
        if (! Schema::hasTable('shopify_b2b_daily_data')) {
            return [];
        }

        $map = [];
        try {
            $rows = ShopifyB2BDailyData::query()
                ->where('period', 'l30')
                ->countableSales()
                ->selectRaw('sku, SUM(quantity) as total_quantity')
                ->groupBy('sku')
                ->get();
            foreach ($rows as $item) {
                $key = ShopifySku::normalizeSkuForShopifyLookup((string) ($item->sku ?? ''));
                if ($key === '') {
                    continue;
                }
                $map[$key] = (int) ($item->total_quantity ?? 0);
            }
        } catch (\Throwable $e) {
            Log::warning('Views Master Shopify B2B L30 lookup failed: '.$e->getMessage());
        }

        return $map;
    }

    private function normSku(string $sku): string
    {
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim($sku));
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sku);

        return strtoupper(preg_replace('/\s+/u', ' ', $clean !== false ? $clean : $sku) ?? '');
    }

    /**
     * @return array<int, string>
     */
    private function wantedKeys(Request $request): array
    {
        $all = array_keys(self::CHANNELS);
        $raw = trim((string) $request->query('channel', ''));
        if ($raw === '') {
            return $all;
        }
        $wanted = array_values(array_filter(array_map(
            static fn ($k) => strtolower(trim((string) $k)),
            explode(',', $raw)
        )));
        $wanted = array_values(array_intersect($wanted, $all));

        return $wanted !== [] ? $wanted : $all;
    }
}
