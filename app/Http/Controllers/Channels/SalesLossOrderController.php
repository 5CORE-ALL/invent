<?php

namespace App\Http\Controllers\Channels;

use App\Models\ChannelMasterCalculatedData;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\TemuShopifySalesService;
use App\Services\VeeqoApiService;
use App\Support\ProductMasterTemuShip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Sales Loss Order — all marketplace orders.
 * Same row shape as /sales-order-fulfillment All Order, without the 30-day default.
 */
class SalesLossOrderController extends SalesOrderFulfillmentController
{
    public function index(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        $sloChannels = collect(MarketplaceManagerRegistry::channels())
            ->filter(fn ($c) => ($c['enabled'] ?? false) === true)
            ->map(fn ($c) => [
                'slug' => (string) ($c['slug'] ?? ''),
                'label' => (string) ($c['label'] ?? ($c['slug'] ?? '')),
            ])
            ->filter(fn ($c) => ($c['slug'] ?? '') !== '')
            ->values()
            ->all();

        $tz = self::SOF_TIMEZONE;

        return view('channels.sales_loss_order', [
            'sloChannels' => $sloChannels,
            'sloDateFrom' => now($tz)->subDays(30)->toDateString(),
            'sloDateTo' => now($tz)->toDateString(),
        ]);
    }

    /**
     * All marketplace orders (every status).
     * Same 30-day default as /sales-order-fulfillment All Order.
     */
    public function data(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->allOrdersQuery($slug), $slug),
                false,
                true
            );
            try {
                $rows = $this->attachCostShipDetailsToRows($rows);
            } catch (\Throwable) {
                // Keep orders even if product-master cost lookup fails.
            }
            $rows = $this->attachSkuSiteProfitPctToRows($rows);

            $amountTotal = 0.0;
            $channels = [];
            foreach ($rows as $row) {
                if (is_numeric($row['amount'] ?? null)) {
                    $amountTotal += (float) $row['amount'];
                }
                $slug = (string) ($row['mm_slug'] ?? '');
                if ($slug !== '') {
                    $channels[$slug] = true;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
                'channel_count' => count($channels),
                'amount_total' => round($amountTotal, 2),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load sales loss orders.',
                'data' => [],
                'count' => 0,
                'channel_count' => 0,
                'amount_total' => 0,
            ], 500);
        }
    }

    /**
     * Cancelled / refunded / voided / lost orders for a marketplace.
     */
    protected function lossOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            // Newegg: 4 = Voided
            'newegg' => $base->where(function (Builder $q) {
                $q->whereIn('status', ['4', 4])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%CANCEL%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%VOID%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%REFUND%']);
            }),
            'temu', 'temu2' => $base->where(function (Builder $q) {
                foreach (['parent_order_status_text', 'order_status_text'] as $col) {
                    foreach (['%CANCEL%', '%REFUND%', '%VOID%', '%LOST%'] as $needle) {
                        $q->orWhereRaw("UPPER(TRIM(COALESCE({$col}, ''))) LIKE ?", [$needle]);
                    }
                }
            }),
            'doba' => $this->applyLossLikeFilter($base, 'order_status'),
            'aliexpress', 'alibaba' => $this->applyLossLikeFilter($base, 'status', ['%CLOSE%']),
            default => $this->applyLossLikeFilter($base, 'status'),
        };
    }

    /**
     * @param  list<string>  $extraNeedles
     */
    protected function applyLossLikeFilter(Builder $query, string $column, array $extraNeedles = []): Builder
    {
        $needles = array_merge(['%CANCEL%', '%REFUND%', '%VOID%', '%LOST%'], $extraNeedles);

        return $query->where(function (Builder $q) use ($column, $needles) {
            foreach ($needles as $i => $needle) {
                $sql = "UPPER(TRIM(COALESCE({$column}, ''))) LIKE ?";
                if ($i === 0) {
                    $q->whereRaw($sql, [$needle]);
                } else {
                    $q->orWhereRaw($sql, [$needle]);
                }
            }
        });
    }

    /**
     * Apply date_from / date_to only when the request sends them.
     * Unlike SOF, empty dates mean all historical rows.
     */
    protected function maybeScopeByRequestDates(?Builder $query, string $slug): ?Builder
    {
        if ($query === null) {
            return null;
        }

        $fromRaw = trim((string) request()->input('date_from', ''));
        $toRaw = trim((string) request()->input('date_to', ''));
        if ($fromRaw === '' && $toRaw === '') {
            return $query;
        }

        $tz = self::SOF_TIMEZONE;
        $from = $fromRaw !== ''
            ? $this->parseCaliforniaDateInput($fromRaw, now($tz)->subDays(30)->startOfDay())
            : now($tz)->subYears(20)->startOfDay();
        $to = $toRaw !== ''
            ? $this->parseCaliforniaDateInput($toRaw, now($tz)->endOfDay(), true)
            : now($tz)->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return $this->applyOrderDateRangeFilter($query, $from, $to, $slug);
    }

    /**
     * INV from Shopify plus DIL% = (shopify L30 / INV) × 100.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachInvToOrderRows(array $rows): array
    {
        $rows = parent::attachInvToOrderRows($rows);
        if ($rows === []) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }
        if ($skus === []) {
            return $rows;
        }

        $shopifyBySku = ShopifySku::mapByProductSkus(array_keys($skus));
        foreach ($rows as &$row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $shopify = $sku !== '' ? $shopifyBySku->get($sku) : null;
            $inv = (int) ($row['INV'] ?? 0);
            // Same as /amazon-tabulator-view: OV L30 = shopify_skus.quantity
            $l30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
            $row['l30'] = $l30;
            $row['dil'] = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0.0;
        }
        unset($row);

        return $rows;
    }

    /**
     * SKU × site GROI / GPFT / NROI / NPFT — same formulas as the channel tabulators.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachSkuSiteProfitPctToRows(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $ctxBySlug = $this->siteProfitContextBySlug();
        $priceBySlugNorm = $this->listingPriceBySlugAndNorm($rows);

        foreach ($rows as &$row) {
            $slug = (string) ($row['mm_slug'] ?? '');
            $ctx = $ctxBySlug[$slug] ?? [
                'margin' => 0.80,
                'ads' => 0.0,
                'ship_key' => 'ship',
                'is_temu' => false,
                'is_temu2' => false,
                'no_ads' => false,
            ];
            $sku = $this->rowLookupSku($row);
            $norm = $sku !== '' ? ShopifySku::normalizeSkuForShopifyLookup($sku) : '';
            $listing = ($norm !== '' && isset($priceBySlugNorm[$slug][$norm]))
                ? (float) $priceBySlugNorm[$slug][$norm]
                : 0.0;
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $amount = is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : 0.0;
            $orderUnit = $amount > 0 ? round($amount / $qty, 2) : 0.0;
            $price = $listing > 0 ? $listing : $orderUnit;

            $lp = is_numeric($row['lp'] ?? null) ? (float) $row['lp'] : 0.0;
            $ship = $this->shipForSiteRow($row, $ctx);

            $metrics = $this->computeSkuSiteProfitPct($slug, $price, $lp, $ship, $ctx);
            $row['groi_pct'] = $metrics['groi_pct'];
            $row['gpft_pct'] = $metrics['gpft_pct'];
            $row['nroi_pct'] = $metrics['nroi_pct'];
            $row['npft_pct'] = $metrics['npft_pct'];
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, array{margin: float, ads: float, ship_key: string, is_temu: bool, is_temu2: bool, no_ads: bool}>
     */
    protected function siteProfitContextBySlug(): array
    {
        $pctByName = [];
        try {
            if (Schema::hasTable('marketplace_percentages')) {
                foreach (MarketplacePercentage::query()->get(['marketplace', 'percentage']) as $row) {
                    $key = strtolower(trim((string) ($row->marketplace ?? '')));
                    if ($key === '' || isset($pctByName[$key])) {
                        continue;
                    }
                    $pct = (float) $row->percentage;
                    $pctByName[$key] = $pct > 1 ? $pct / 100 : $pct;
                }
            }
        } catch (\Throwable) {
            $pctByName = [];
        }

        $adsByName = [];
        try {
            if (Schema::hasTable('channel_master_calculated_data')) {
                foreach (ChannelMasterCalculatedData::query()->get(['channel', 'ads_percentage', 'tacos_percentage']) as $row) {
                    $key = strtolower(trim((string) ($row->channel ?? '')));
                    if ($key === '' || isset($adsByName[$key])) {
                        continue;
                    }
                    $ads = $row->ads_percentage;
                    if ($ads === null || $ads === '') {
                        $ads = $row->tacos_percentage;
                    }
                    $adsByName[$key] = is_numeric($ads) ? (float) $ads : 0.0;
                }
            }
        } catch (\Throwable) {
            $adsByName = [];
        }

        $noAds = ['doba', 'purchasingpower', 'topdawg', 'shein', 'faire', 'temu2', 'alibaba'];
        $noShip = ['faire', 'topdawg', 'purchasingpower'];
        $out = [];

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }
            $slug = (string) ($channel['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $candidates = array_merge(
                [$slug],
                $channel['mp_channel_keys'] ?? [],
                match ($slug) {
                    'ebay1' => ['Ebay', 'eBay', 'ebay'],
                    'ebay2' => ['EbayTwo', 'eBay 2'],
                    'ebay3' => ['EbayThree', 'eBay 3'],
                    'tiktok', 'tiktok2' => ['TiktokShop', 'TikTok Shop'],
                    'purchasingpower' => ['Purchase'],
                    'newegg' => ['Neweggb2c', 'Newegg'],
                    default => [],
                }
            );
            $margin = 0.80;
            foreach ($candidates as $name) {
                $key = strtolower(trim((string) $name));
                if ($key !== '' && isset($pctByName[$key])) {
                    $margin = (float) $pctByName[$key];
                    break;
                }
            }
            $ads = 0.0;
            foreach ($candidates as $name) {
                $key = strtolower(trim((string) $name));
                if ($key !== '' && isset($adsByName[$key])) {
                    $ads = (float) $adsByName[$key];
                    break;
                }
            }
            $out[$slug] = [
                'margin' => $margin > 0 ? $margin : 0.80,
                'ads' => in_array($slug, $noAds, true) ? 0.0 : $ads,
                'ship_key' => match (true) {
                    in_array($slug, $noShip, true) => 'none',
                    in_array($slug, ['temu', 'temu2'], true) => 'ship_temu',
                    $slug === 'bestbuy' => 'ship_bb',
                    default => 'ship',
                },
                'is_temu' => $slug === 'temu',
                'is_temu2' => $slug === 'temu2',
                'no_ads' => in_array($slug, $noAds, true),
            ];
        }

        return $out;
    }

    /**
     * @param  array{margin: float, ads: float, ship_key: string, is_temu: bool, is_temu2: bool, no_ads: bool}  $ctx
     * @return array{groi_pct: ?float, gpft_pct: ?float, nroi_pct: ?float, npft_pct: ?float}
     */
    protected function computeSkuSiteProfitPct(string $slug, float $price, float $lp, float $ship, array $ctx): array
    {
        $empty = ['groi_pct' => null, 'gpft_pct' => null, 'nroi_pct' => null, 'npft_pct' => null];
        if ($price <= 0) {
            return $empty;
        }

        $margin = (float) ($ctx['margin'] ?? 0.80);
        $ads = (float) ($ctx['ads'] ?? 0);
        $isTemu = ! empty($ctx['is_temu']);
        $isTemu2 = ! empty($ctx['is_temu2']);
        $noAds = ! empty($ctx['no_ads']) || $isTemu2;

        if ($isTemu || $isTemu2) {
            $rPrice = TemuShopifySalesService::computeRPrice($price);
            $full = TemuShopifySalesService::computeFullTemuPrice($price);
            $gpft = $full > 0
                ? round(TemuShopifySalesService::computeGpftPercent($full, $margin, $lp, $ship), 2)
                : null;
            $groi = $lp > 0 && $rPrice > 0
                ? round(TemuShopifySalesService::computeGroiPercent($rPrice, $margin, $lp, $ship), 2)
                : null;
        } else {
            $gross = ($price * $margin) - $lp - $ship;
            $gpft = round(($gross / $price) * 100, 2);
            $groi = $lp > 0 ? round(($gross / $lp) * 100, 2) : null;
        }

        if ($gpft === null && $groi === null) {
            return $empty;
        }

        if ($noAds) {
            return [
                'groi_pct' => $groi,
                'gpft_pct' => $gpft,
                'nroi_pct' => $groi,
                'npft_pct' => $gpft,
            ];
        }

        $npft = $gpft !== null
            ? ($ads == 100.0 ? $gpft : round($gpft - $ads, 2))
            : null;
        if ($isTemu) {
            $nroi = $groi !== null
                ? ($ads == 100.0 ? $groi : round($groi - $ads, 2))
                : null;
        } else {
            $gross = ($price * $margin) - $lp - $ship;
            $adsPerUnit = $price * ($ads / 100);
            $nroi = $lp > 0 ? round((($gross - $adsPerUnit) / $lp) * 100, 2) : null;
        }

        return [
            'groi_pct' => $groi,
            'gpft_pct' => $gpft,
            'nroi_pct' => $nroi,
            'npft_pct' => $npft,
        ];
    }

    /**
     * @param  array{ship_key: string}  $ctx
     */
    protected function shipForSiteRow(array $row, array $ctx): float
    {
        $key = (string) ($ctx['ship_key'] ?? 'ship');
        if ($key === 'none') {
            return 0.0;
        }
        $v = $row[$key] ?? $row['ship'] ?? null;

        return is_numeric($v) ? (float) $v : 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, float>>
     */
    protected function listingPriceBySlugAndNorm(array $rows): array
    {
        $skusBySlug = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['mm_slug'] ?? '');
            $sku = $this->rowLookupSku($row);
            if ($slug === '' || $sku === '') {
                continue;
            }
            $skusBySlug[$slug][$sku] = true;
        }

        $out = [];
        foreach ($skusBySlug as $slug => $skuSet) {
            $skus = array_keys($skuSet);
            $map = [];
            foreach ($this->listingPriceSourcesForSlug($slug) as $source) {
                $map = $map + $this->priceMapFromTable($source[0], $source[1], $source[2], $skus);
            }
            $out[$slug] = $map;
        }

        return $out;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    protected function listingPriceSourcesForSlug(string $slug): array
    {
        return match ($slug) {
            'amazon' => [['amazon_datsheets', 'sku', 'price']],
            'ebay1' => [['ebay_metrics', 'sku', 'ebay_price']],
            'ebay2' => [['ebay_2_metrics', 'sku', 'ebay_price']],
            'ebay3' => [['ebay_3_metrics', 'sku', 'ebay_price']],
            'temu' => [['temu_metrics', 'sku', 'base_price'], ['temu_pricing', 'sku', 'base_price']],
            'temu2' => [['temu2_pricing', 'sku', 'base_price']],
            'reverb' => [['reverb_products', 'sku', 'price']],
            'shein' => [['shein_pricing_prices', 'sku', 'special_offer_price'], ['shein_pricing_prices', 'sku', 'price']],
            'faire' => [['faire_metric', 'sku', 'price']],
            'doba' => [['doba_daily_data', 'sku', 'anticipated_income']],
            'wayfair' => [['wayfair_daily_data', 'sku', 'unit_price']],
            'aliexpress' => [['aliexpress_pricing_prices', 'sku', 'price']],
            'topdawg' => [['topdawg_products', 'sku', 'price']],
            'purchasingpower' => [['purchasing_power_products', 'sku', 'price']],
            default => [],
        };
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    protected function priceMapFromTable(string $table, string $skuCol, string $priceCol, array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable($table)) {
            return [];
        }
        try {
            if (! Schema::hasColumn($table, $skuCol) || ! Schema::hasColumn($table, $priceCol)) {
                return [];
            }
            $map = [];
            DB::table($table)
                ->whereIn($skuCol, $skus)
                ->get([$skuCol, $priceCol])
                ->each(function ($r) use (&$map, $skuCol, $priceCol) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($r->{$skuCol} ?? ''));
                    $p = (float) ($r->{$priceCol} ?? 0);
                    if ($norm !== '' && $p > 0 && ! isset($map[$norm])) {
                        $map[$norm] = $p;
                    }
                });

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    protected function rowLookupSku(array $row): string
    {
        $sku = trim((string) ($row['sku'] ?? ''));
        $sku = preg_replace('/\s+\+\d+$/', '', $sku) ?? $sku;

        return trim((string) $sku);
    }

    /**
     * CP, freight, LP, ship rates from product_master.Values (same keys as Product Master).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachCostShipDetailsToRows(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('product_master')) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }
        if ($skus === []) {
            return $rows;
        }

        $scalar = static function (array $values, string ...$keys) {
            $lookup = [];
            foreach ($values as $k => $v) {
                $lookup[strtolower((string) $k)] = $v;
            }
            foreach ($keys as $key) {
                $hit = $lookup[strtolower($key)] ?? null;
                if ($hit === null || $hit === '') {
                    continue;
                }
                if (is_numeric($hit)) {
                    return 0 + $hit;
                }
                $trimmed = trim((string) $hit);
                if ($trimmed === '') {
                    continue;
                }
                $numeric = str_replace(',', '', $trimmed);
                if (is_numeric($numeric)) {
                    return 0 + $numeric;
                }

                return $trimmed;
            }

            return null;
        };

        $byNorm = [];
        try {
            ProductMaster::query()
                ->whereIn('sku', array_keys($skus))
                ->get(['sku', 'Values'])
                ->each(function ($product) use (&$byNorm, $scalar) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($product->sku ?? ''));
                    if ($norm === '' || isset($byNorm[$norm])) {
                        return;
                    }
                    $values = is_array($product->Values)
                        ? $product->Values
                        : (is_string($product->Values) ? (json_decode($product->Values, true) ?: []) : []);

                    $l = $scalar($values, 'l');
                    $w = $scalar($values, 'w');
                    $h = $scalar($values, 'h');
                    $frght = $scalar($values, 'frght', 'freight', 'frg');
                    if (($frght === null || $frght === '') && is_numeric($l) && is_numeric($w) && is_numeric($h)) {
                        $cbm = (((float) $l * 2.54) * ((float) $w * 2.54) * ((float) $h * 2.54)) / 1000000;
                        $frght = round($cbm * 200, 2);
                    }

                    $lp = $scalar($values, 'lp');
                    if (($lp === null || $lp === '' || (is_numeric($lp) && (float) $lp <= 0)) && isset($product->lp) && is_numeric($product->lp)) {
                        $lp = (float) $product->lp;
                    }

                    $ship = $scalar($values, 'ship');
                    if (($ship === null || $ship === '') && isset($product->ship) && is_numeric($product->ship)) {
                        $ship = (float) $product->ship;
                    }

                    $shipBb = $scalar($values, 'ship_bb');
                    if (($shipBb === null || $shipBb === '') && isset($product->ship_bb) && is_numeric($product->ship_bb)) {
                        $shipBb = (float) $product->ship_bb;
                    }

                    $storedTemu = ProductMasterTemuShip::stored($values, $product);
                    if ($storedTemu === null) {
                        $storedTemu = $scalar($values, 'ship_temu');
                    }
                    $shipTemu = $storedTemu;
                    if ($shipTemu === null) {
                        $computedTemu = ProductMasterTemuShip::forPricing($values, $product);
                        $shipTemu = $computedTemu > 0 ? $computedTemu : null;
                    } else {
                        $shipTemu = round((float) $shipTemu, 2);
                    }

                    $byNorm[$norm] = [
                        'cp' => $scalar($values, 'cp'),
                        'frght' => is_numeric($frght) ? round((float) $frght, 2) : $frght,
                        'lp' => is_numeric($lp) ? round((float) $lp, 2) : $lp,
                        'ship' => is_numeric($ship) ? round((float) $ship, 2) : $ship,
                        'ship_temu' => $shipTemu,
                        'ship_bb' => is_numeric($shipBb) ? round((float) $shipBb, 2) : $shipBb,
                        'l' => $l,
                        'w' => $w,
                        'h' => $h,
                        'wt_act' => $scalar($values, 'wt_act'),
                        'wt_decl' => $scalar($values, 'wt_decl'),
                    ];
                });
        } catch (\Throwable) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $empty = [
                'cp' => null,
                'frght' => null,
                'lp' => null,
                'ship' => null,
                'ship_temu' => null,
                'ship_bb' => null,
            ];
            $sku = trim((string) ($row['sku'] ?? ''));
            $norm = $sku !== '' ? ShopifySku::normalizeSkuForShopifyLookup($sku) : '';
            $extra = ($norm !== '' && isset($byNorm[$norm])) ? $byNorm[$norm] : $empty;
            foreach ($extra as $k => $v) {
                if (in_array($k, ['l', 'w', 'h', 'wt_act', 'wt_decl'], true)
                    && array_key_exists($k, $row)
                    && $row[$k] !== null
                    && $row[$k] !== '') {
                    continue;
                }
                $row[$k] = $v;
            }
        }
        unset($row);

        return $rows;
    }
}
