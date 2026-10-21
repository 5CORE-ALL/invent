<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\Ebay2Metric;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Temu2CampaignReport;
use App\Models\Temu2DataView;
use App\Models\Temu2ListingStatus;
use App\Models\TemuLmp;
use App\Models\Temu2Metric;
use App\Models\Temu2Order;
use App\Models\Temu2Pricing;
use App\Models\Temu2ViewData;
use App\Services\LmpSkuGroupService;
use App\Services\Support\ChannelPushSpriceRunner;
use App\Services\Support\NewTemutwoSuggestedPriceStore;
use App\Services\Temu2ApiService;
use App\Services\TemuPromotionApiService;
use App\Services\TemuShopifySalesService;
use App\Support\DetachedArtisan;
use App\Support\ProductMasterTemuShip;
use App\Support\TemuGoodsIdHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NewTemutwoController extends Controller
{
    public function __construct(protected LmpSkuGroupService $lmpSkuGroupService)
    {
    }

    public function index()
    {
        return view('market-places.new_temutwo_tabulator_view', [
            'temuMargin' => TemuShopifySalesService::temu2MarginDecimal(),
            'temuAds' => $this->temuChannelAdsSummary(),
            'newtemutwoPushSpriceLive' => ChannelPushSpriceRunner::livePushAllowed(),
            'newtemutwoAutoPaused' => self::autoPushPaused(),
        ]);
    }

    private const PROMOTION_SETTING_KEY = 'newtemutwo_promotion';

    /** Temu 2 promotions go through the Temu 2 account (TEMU2_* credentials), never Temu 1's. */
    private function promotionApi(Temu2ApiService $temu): TemuPromotionApiService
    {
        return new TemuPromotionApiService($temu);
    }

    /**
     * Promotion settings (optional pinned activity). Promotion itself is always ON — there is no off switch.
     * `paused` is the Pause button: while true nothing is pushed automatically (scheduled job or page),
     * only the manual push buttons still work.
     *
     * @return array{enabled: bool, activity_id: string|null, paused: bool}
     */
    public static function promotionSetting(): array
    {
        try {
            $row = ChannelTabulatorColumnSetting::query()
                ->where('channel_name', self::PROMOTION_SETTING_KEY)
                ->first();
        } catch (\Throwable) {
            return ['enabled' => true, 'activity_id' => null, 'paused' => false];
        }
        $vis = is_array($row?->visibility) ? $row->visibility : [];
        $activity = trim((string) ($vis['activity_id'] ?? ''));

        return [
            'enabled' => true,
            'activity_id' => $activity !== '' ? $activity : null,
            'paused' => filter_var($vis['paused'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public static function autoPushPaused(): bool
    {
        return self::promotionSetting()['paused'];
    }

    public static function promotionEnabled(): bool
    {
        return self::promotionSetting()['enabled'];
    }

    public function getPromotionSetting()
    {
        return response()->json(['success' => true] + self::promotionSetting());
    }

    public function savePromotionSetting(Request $request)
    {
        $current = self::promotionSetting();
        $enabled = true; // Promotion is always ON.
        $paused = $request->has('paused')
            ? filter_var($request->input('paused'), FILTER_VALIDATE_BOOLEAN)
            : $current['paused'];
        $activity = $request->has('activity_id')
            ? trim((string) $request->input('activity_id'))
            : (string) ($current['activity_id'] ?? '');
        if ($activity !== '' && ! ctype_digit($activity)) {
            return response()->json(['success' => false, 'message' => 'activity_id must be numeric'], 422);
        }

        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => self::PROMOTION_SETTING_KEY],
            ['visibility' => ['enabled' => $enabled, 'activity_id' => $activity !== '' ? $activity : null, 'paused' => $paused], 'column_order' => []]
        );

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
            'activity_id' => $activity !== '' ? $activity : null,
            'paused' => $paused,
        ]);
    }

    /** Open Temu 2 promotion activities, so an activity_id can be pinned. */
    public function promotionActivities(Temu2ApiService $temu)
    {
        $promo = $this->promotionApi($temu);
        $out = [];
        foreach ([TemuPromotionApiService::STATUS_ONGOING, TemuPromotionApiService::STATUS_NOT_STARTED] as $status) {
            $res = $promo->queryActivities(TemuPromotionApiService::TYPE_LIGHTNING_DEAL, $status);
            if (! $res['success']) {
                return response()->json(['success' => false, 'message' => $res['message']], 502);
            }
            $out = array_merge($out, $res['activities']);
        }

        return response()->json(['success' => true, 'activities' => $out]);
    }

    /**
     * Which goods Temu 2 lets us enroll in the promotion activity (candidate list).
     * Read-only, cached for 10 minutes. A goods that is not in the list is "not eligible".
     */
    public function promotionEligibility(Temu2ApiService $temu)
    {
        $promo = $this->promotionApi($temu);
        $setting = self::promotionSetting();
        $activityId = $setting['activity_id'];
        $activityName = null;
        if ($activityId === null) {
            $resolved = $promo->resolveOpenActivity();
            if (! $resolved['success']) {
                return response()->json(['success' => false, 'message' => $resolved['message']], 502);
            }
            $activityId = (string) $resolved['activity']['activityId'];
            $activityName = $resolved['activity']['activityName'] ?? null;
        }

        $cacheKey = 'newtemutwo_promo_eligibility:'.$activityId;
        try {
            $cached = Cache::get($cacheKey);
        } catch (\Throwable) {
            $cached = null;
        }
        if (is_array($cached) && request()->query('refresh') !== '1') {
            return response()->json($cached);
        }

        $res = $promo->candidateGoods($activityId);
        if (! $res['success']) {
            return response()->json(['success' => false, 'message' => $res['message']], 502);
        }

        $payload = [
            'success' => true,
            'activity_id' => $activityId,
            'activity_name' => $activityName,
            'goods' => $res['goods'],
        ];
        try {
            Cache::put($cacheKey, $payload, now()->addMinutes(10));
        } catch (\Throwable) {
            // Cache unavailable — the answer is still correct for this request.
        }

        return response()->json($payload);
    }

    /** Same rule as the page's S Base Prc: S R = S × 0.88; S Base = S R − 2.99 when S R < 30. */
    private static function promotionBaseFromSprice(float $sprice): float
    {
        $sR = round($sprice * 0.88, 2);
        if (! ($sR > 0)) {
            return 0.0;
        }
        $base = $sR < 30 ? round($sR - 2.99, 2) : $sR;

        return $base > 0 ? $base : 0.0;
    }

    /**
     * Promo Price (S PRC rule): Price = (Std × 0.88) − (Std × Disc %), where Std × Disc % is the Disc $ column.
     * Base sent to Temu = Price − $2.99 when Price is under $30, otherwise Price.
     */
    public static function promoBaseFromStd(float $std, float $pct): float
    {
        if (! ($std > 0) || ! ($pct > 0)) {
            return 0.0;
        }
        $price = round(($std * 0.88) - ($std * $pct / 100), 2);
        if (! ($price > 0)) {
            return 0.0;
        }
        $base = $price < 30 ? round($price - 2.99, 2) : $price;

        return $base > 0 ? $base : 0.0;
    }

    /**
     * Enroll SKUs in a Temu 2 promotion at Sum disc %.
     * items: [{sku, pct, std, sprice}] where pct is the page's Sum disc and std is the Std Price.
     * Promo price = Std × 0.88 − Std × Disc % (see promoBaseFromStd). Refused for unattended requests while auto-push is paused.
     */
    public function pushPromotion(Request $request, Temu2ApiService $temu, bool $ignoreSwitch = false)
    {
        $promo = $this->promotionApi($temu);
        $setting = self::promotionSetting();
        // $ignoreSwitch is only used by the newtemutwo:promotion-test --apply command (skips the Pause check).

        // Auto-push is paused: only a manual request (a person clicked) may enroll.
        if (! $ignoreSwitch && $setting['paused'] && ! $request->boolean('manual')) {
            return response()->json(['success' => false, 'message' => 'Auto-push is paused. Resume it to apply promotions automatically.'], 409);
        }

        $items = $request->input('items');
        if (! is_array($items) || $items === []) {
            return response()->json(['success' => false, 'message' => 'items array required'], 422);
        }
        $items = array_slice($items, 0, 50);

        $activityId = $setting['activity_id'];
        if ($activityId === null) {
            $resolved = $promo->resolveOpenActivity();
            if (! $resolved['success']) {
                return response()->json(['success' => false, 'message' => $resolved['message']], 502);
            }
            $activityId = (string) $resolved['activity']['activityId'];
        }

        $results = [];
        $ok = 0;
        $fail = 0;
        $failItem = function (string $sku, float $pct, string $message) use (&$results, &$fail): void {
            $fail++;
            $results[] = ['sku' => $sku, 'pct' => $pct, 'status' => 'failed', 'message' => $message];
        };

        // Temu only accepts a goods when ALL its eligible SKUs are submitted together, so
        // requested SKUs are grouped by goodsId and every sibling SKU goes in the same call.
        $groups = [];
        foreach ($items as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            $pct = round((float) ($item['pct'] ?? 0), 2);
            if ($sku === '') {
                continue;
            }
            if (! ($pct > 0) || $pct >= 100) {
                $failItem($sku, $pct, 'Sum disc must be between 0 and 100 to apply a promotion.');
                continue;
            }
            $metric = Temu2Metric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
            $goodsId = trim((string) ($metric?->goods_id ?? '')) ?: (string) ($temu->getGoodsIdBySku($sku) ?? '');
            if ($goodsId === '') {
                $failItem($sku, $pct, 'Temu goodsId not found for this SKU.');
                continue;
            }
            $groups[$goodsId][] = [
                'sku' => $sku,
                'pct' => $pct,
                'std' => round((float) ($item['std'] ?? 0), 2),
                'sprice' => round((float) ($item['sprice'] ?? 0), 2),
            ];
        }

        foreach ($groups as $goodsId => $requested) {
            $goodsId = (string) $goodsId;
            $fallbackPct = (float) $requested[0]['pct'];
            $pctBySku = [];
            $spriceBySku = [];
            $stdBySku = [];
            foreach ($requested as $r) {
                $stdBySku[strtoupper($r['sku'])] = (float) ($r['std'] ?? 0);
                $pctBySku[strtoupper($r['sku'])] = (float) $r['pct'];
                $spriceBySku[strtoupper($r['sku'])] = (float) ($r['sprice'] ?? 0);
            }

            // One entry per Temu skuId (the metrics table also holds alias names for the same skuId).
            $siblings = [];
            foreach (Temu2Metric::query()->where('goods_id', $goodsId)->get() as $row) {
                $skuId = trim((string) ($row->sku_id ?? ''));
                if ($skuId === '') {
                    continue;
                }
                $siblings[$skuId]['names'][] = (string) $row->sku;
                $siblings[$skuId]['base'] = (float) ($siblings[$skuId]['base'] ?? 0) > 0
                    ? $siblings[$skuId]['base']
                    : (float) ($row->base_price ?? 0);
            }
            // The promotion sits on the base we pushed from the Std price; Temu's synced base_price can lag behind it.
            foreach ($siblings as $skuId => $sib) {
                foreach ($sib['names'] as $n) {
                    if (! isset($pctBySku[strtoupper($n)])) {
                        continue;
                    }
                    $view = Temu2DataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($n))])->first();
                    $pushed = is_array($view?->value) ? ($view->value[NewTemutwoSuggestedPriceStore::KEY_PUSHED_BASE] ?? null) : null;
                    if (is_numeric($pushed) && (float) $pushed > 0) {
                        $siblings[$skuId]['base'] = round((float) $pushed, 2);
                        break;
                    }
                }
            }
            if ($siblings === []) {
                foreach ($requested as $r) {
                    $failItem($r['sku'], (float) $r['pct'], 'Temu skuId not found for this SKU.');
                }
                continue;
            }

            $allNames = [];
            foreach ($siblings as $sib) {
                foreach ($sib['names'] as $n) {
                    $allNames[] = $n;
                }
            }
            $inventory = ShopifySku::mapByProductSkus(array_values(array_unique($allNames)));

            $skuPrices = [];
            $quantity = 0;
            $bad = null;
            foreach ($siblings as $skuId => $sib) {
                $pct = $fallbackPct;
                $sprice = 0.0;
                $std = 0.0;
                $inv = 0;
                foreach ($sib['names'] as $n) {
                    if (isset($pctBySku[strtoupper($n)])) {
                        $pct = $pctBySku[strtoupper($n)];
                        $sprice = (float) ($spriceBySku[strtoupper($n)] ?? 0);
                        $std = (float) ($stdBySku[strtoupper($n)] ?? 0);
                    }
                    $inv = max($inv, (int) floor((float) ($inventory->get($n)->inv ?? 0)));
                }
                if (! ($sib['base'] > 0)) {
                    $bad = 'Temu base price not found for SKU '.($sib['names'][0] ?? $skuId).'.';
                    break;
                }
                if ($sprice > 0) {
                    // S PRC is the Promo Price (Std × 0.88 − Std × Disc %, LMP-capped). Temu base = S R Prc, − $2.99 under $30.
                    $skuPrices[$skuId] = self::promotionBaseFromSprice($sprice);
                } elseif ($std > 0) {
                    // No S PRC sent: same rule straight from Std.
                    $skuPrices[$skuId] = self::promoBaseFromStd($std, $pct);
                } else {
                    $skuPrices[$skuId] = round($sib['base'] * (1 - $pct / 100), 2);
                }
                if ($std > 0 && $skuPrices[$skuId] > 0
                    && $skuPrices[$skuId] >= self::promotionBaseFromSprice($std) - 0.001) {
                    $bad = 'Promo price is not below the Std (full) price for SKU '.($sib['names'][0] ?? $skuId).'.';
                    break;
                }
                if (! ($skuPrices[$skuId] > 0)) {
                    $bad = 'Promotion price is not positive for SKU '.($sib['names'][0] ?? $skuId).'.';
                    break;
                }
                $quantity += $inv;
            }
            if ($bad === null && $quantity < 1) {
                $bad = 'INV is 0 — nothing to put on promotion.';
            }
            if ($bad !== null) {
                foreach ($requested as $r) {
                    $failItem($r['sku'], (float) $r['pct'], $bad);
                }
                continue;
            }

            $res = $promo->enrollGoods($activityId, $goodsId, $skuPrices, $quantity, 'nt2'.substr(md5($goodsId.$activityId.json_encode($skuPrices)), 0, 24));
            if (! $res['success']) {
                foreach ($requested as $r) {
                    $failItem($r['sku'], (float) $r['pct'], $res['message']);
                }
                continue;
            }

            foreach ($requested as $r) {
                $price = 0.0;
                foreach ($siblings as $skuId => $sib) {
                    if (in_array(strtoupper($r['sku']), array_map('strtoupper', $sib['names']), true)) {
                        $price = (float) $skuPrices[$skuId];
                    }
                }
                NewTemutwoSuggestedPriceStore::markPromotionApplied($r['sku'], (float) $r['pct'], $price, $activityId, $res['draft_id'] ?? null);
                $ok++;
                $results[] = [
                    'sku' => $r['sku'],
                    'pct' => $r['pct'],
                    'price' => $price,
                    'status' => 'ok',
                    'message' => $res['message'],
                    'applied_at' => now()->toDateTimeString(),
                    'activity_id' => $activityId,
                ];
            }
        }

        return response()->json([
            'success' => $ok > 0 || $fail === 0,
            'activity_id' => $activityId,
            'ok' => $ok,
            'failed' => $fail,
            'results' => $results,
            'message' => "Promotion: {$ok} applied".($fail ? ", {$fail} failed" : '').' (activity '.$activityId.').',
        ]);
    }

    /** Channel Ads% the page nets from every row. */
    public function channelAdsPercent(): float
    {
        return (float) ($this->temuChannelAdsSummary()['percent'] ?? 0);
    }

    /**
     * Schema::hasTable hits information_schema, which costs ~45 ms per call on this
     * database. Table existence never changes mid-request, so memoize it.
     *
     * @var array<string, bool>
     */
    private static array $tableExistsCache = [];

    private function hasTable(string $table): bool
    {
        if (array_key_exists($table, self::$tableExistsCache)) {
            return self::$tableExistsCache[$table];
        }

        try {
            $exists = (bool) Cache::remember(
                'newtemutwo_has_table:'.$table,
                now()->addHours(12),
                static fn () => Schema::hasTable($table)
            );
        } catch (\Throwable $e) {
            $exists = Schema::hasTable($table);
        }

        return self::$tableExistsCache[$table] = $exists;
    }

    /**
     * Temu 2 Ads% with the same definition /channel-master uses for the Temu 2 row:
     * L30 ad spend from temu2_campaign_reports ÷ L30 order revenue from temu2_orders.
     *
     * @return array{spend: float, sales: float, percent: float, window: string}
     */
    private function temuChannelAdsSummary(): array
    {
        // index() and dataJson() both need this; the orders scan behind it is not cheap.
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }

        try {
            $cached = Cache::get('newtemutwo_channel_ads_summary_v3');
            if (is_array($cached)) {
                return $memo = $cached;
            }
        } catch (\Throwable $e) {
            // Cache unavailable — fall through and compute.
        }

        $spend = 0.0;
        $sales = 0.0;
        $window = '';

        try {
            if ($this->hasTable('temu2_campaign_reports')) {
                $spend = (float) (Temu2CampaignReport::badgeTotals('L30')['spend'] ?? 0);
            }
        } catch (\Throwable $e) {
            Log::warning('New Temu Two ads spend failed: '.$e->getMessage());
        }

        try {
            [$start, $end] = TemuShopifySalesService::channelMasterL30Window();
            $window = substr((string) $start, 0, 10).' → '.substr((string) $end, 0, 10);
            $m = TemuShopifySalesService::computeMetricsFromOrders($start, $end, true);
            $sales = (float) ($m['base_sales'] ?? $m['sales'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning('New Temu Two ads sales failed: '.$e->getMessage());
        }

        $summary = [
            'spend' => round($spend, 2),
            'sales' => round($sales, 2),
            'percent' => $sales > 0 ? round(($spend / $sales) * 100, 2) : 0.0,
            'window' => $window,
        ];

        try {
            Cache::put('newtemutwo_channel_ads_summary_v3', $summary, now()->addMinutes(10));
        } catch (\Throwable $e) {
            // Cache unavailable — value is still correct for this request.
        }

        return $memo = $summary;
    }

    public function dataJson()
    {
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderBy('sku', 'asc')
                ->get();

            $productMasters = $productMasters->filter(function ($item) {
                return stripos((string) $item->sku, 'PARENT') === false;
            })->values();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyData = ShopifySku::mapByProductSkus($skus);

            try {
                $this->lmpSkuGroupService->prepareForSkus($skus);
            } catch (\Throwable $e) {
                Log::warning('New Temu Two LmpSkuGroupService prepareForSkus failed: '.$e->getMessage());
            }

            $listingStatusData = Temu2ListingStatus::whereIn('sku', $skus)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [strtolower((string) $item->sku) => $item];
                });

            $normalizeSku = static function ($sku) {
                return TemuShopifySalesService::normalizeTemuSku((string) $sku);
            };

            $temuMetricsBySku = [];
            foreach (Temu2Metric::query()->get(['sku', 'base_price', 'goods_id', 'sku_id', 'product_clicks_l30']) as $metric) {
                $key = $normalizeSku($metric->sku);
                if ($key === '') {
                    continue;
                }
                $price = (float) ($metric->base_price ?? 0);
                if (!isset($temuMetricsBySku[$key]) || ($price > 0 && (float) ($temuMetricsBySku[$key]->base_price ?? 0) <= 0)) {
                    $temuMetricsBySku[$key] = $metric;
                }
            }

            // Same sheet as /temu2-decrease Up Pricing. When a SKU is on the
            // upload, that Base price wins over the API metric.
            $sheetPriceBySku = [];
            if ($this->hasTable('temu2_pricing')) {
                foreach (Temu2Pricing::query()->get(['sku', 'base_price']) as $sheet) {
                    $key = $normalizeSku($sheet->sku);
                    if ($key === '' || isset($sheetPriceBySku[$key])) {
                        continue;
                    }
                    $sheetPrice = ($sheet->base_price !== null && $sheet->base_price !== '')
                        ? (float) $sheet->base_price
                        : 0.0;
                    if ($sheetPrice > 0) {
                        $sheetPriceBySku[$key] = $sheetPrice;
                    }
                }
            }

            // Views — same as /temu2-decrease: temu2_view_data.product_clicks by goods_id,
            // else temu2_metrics.product_clicks_l30 + Ads API clicks when the sheet has no row.
            $viewDataByGoodsId = $this->hasTable('temu2_view_data')
                ? Temu2ViewData::selectRaw('goods_id, SUM(product_clicks) as product_clicks')
                    ->groupBy('goods_id')
                    ->get()
                    ->keyBy(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
                : collect();

            $adsViewsByGoodsId = collect();
            if ($this->hasTable('temu2_campaign_reports')) {
                $ids = Temu2CampaignReport::latestRowIdsByGoodsId('L30');
                if ($ids->isNotEmpty()) {
                    $adsViewsByGoodsId = Temu2CampaignReport::query()
                        ->whereIn('id', $ids)
                        ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'not created'")
                        ->whereNotNull('goods_id')
                        ->get(['goods_id', 'clicks'])
                        ->filter(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
                        ->keyBy(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
                        ->map(fn ($r) => (int) ($r->clicks ?? 0));
                }
            }

            // L30 qty from temu2_orders — same source/window as /temu2-decrease (Qty Purchased).
            $l30ByNormalizedSku = [];
            $noSpaceToNormalized = [];
            foreach ($skus as $sku) {
                $n = $normalizeSku($sku);
                if ($n === '') {
                    continue;
                }
                $l30ByNormalizedSku[$n] = 0;
                $noSpace = str_replace(' ', '', $n);
                if ($noSpace !== '') {
                    $noSpaceToNormalized[$noSpace] = $n;
                }
            }

            $l60ByNormalizedSku = $l30ByNormalizedSku;

            $tallyOrders = function (array $window, array &$bucket) use ($normalizeSku, $noSpaceToNormalized): void {
                foreach ($this->temuOrderQtyBySku($window[0], $window[1]) as $raw => $qty) {
                    $n = $normalizeSku($raw);
                    if (isset($bucket[$n])) {
                        $bucket[$n] += $qty;
                    } else {
                        $nNoSpace = str_replace(' ', '', $n);
                        if (isset($noSpaceToNormalized[$nNoSpace])) {
                            $bucket[$noSpaceToNormalized[$nNoSpace]] += $qty;
                        }
                    }
                }
            };

            $tallyOrders(TemuShopifySalesService::channelMasterL30Window(), $l30ByNormalizedSku);
            // Prior 30 complete Pacific days (31–60). Feeds CVR 45 / CVR 60, which is what
            // the CVR up/down arrow and the Sprc Dil CVR overlay compare against.
            $tallyOrders(TemuShopifySalesService::channelMasterL60Window(), $l60ByNormalizedSku);

            $lookupStdPrc = $this->buildStdPriceLookup($skus, $normalizeSku);
            $amazonPriceBySku = [];
            $ebayPriceBySku = [];
            $ebay2PriceBySku = [];
            foreach (array_chunk($skus, 500) as $skuChunk) {
                $amazonPriceBySku += $this->buildPriceLookup(
                    AmazonDatasheet::whereIn('sku', $skuChunk)->get(['sku', 'price']),
                    $normalizeSku,
                    static fn ($row) => is_numeric($row->price ?? null) ? (float) $row->price : null
                );
                $ebayPriceBySku += $this->buildPriceLookup(
                    EbayMetric::whereIn('sku', $skuChunk)->get(['sku', 'ebay_price']),
                    $normalizeSku,
                    static fn ($row) => is_numeric($row->ebay_price ?? null) ? (float) $row->ebay_price : null
                );
                $ebay2PriceBySku += $this->buildPriceLookup(
                    Ebay2Metric::whereIn('sku', $skuChunk)->get(['sku', 'ebay_price']),
                    $normalizeSku,
                    static fn ($row) => is_numeric($row->ebay_price ?? null) ? (float) $row->ebay_price : null
                );
            }

            // S PRC is saved + pushed by the server (newtemutwo:sprc-dil-auto-push). The page only reads it.
            $suggestedStore = new NewTemutwoSuggestedPriceStore();
            $suggestedStore->loadForSkus($skus);
            $percentage = TemuShopifySalesService::temu2MarginDecimal();
            // One channel-level Ads% nets every row, same as /temu2-decrease.
            $adsPercent = (float) $this->temuChannelAdsSummary()['percent'];

            $temuLmpByNormalizedSku = [];
            if ($this->hasTable('temu_lmp')) {
                foreach (TemuLmp::all() as $row) {
                    $nk = $normalizeSku($row->sku);
                    if ($nk !== '' && !isset($temuLmpByNormalizedSku[$nk])) {
                        $temuLmpByNormalizedSku[$nk] = $row;
                    }
                }
            }

            $result = [];

            foreach ($productMasters as $pm) {
                $sku = (string) $pm->sku;
                $shopify = $shopifyData->get($pm->sku);
                $listingStatus = $listingStatusData[strtolower($sku)] ?? null;
                $temuMetric = $temuMetricsBySku[$normalizeSku($sku)] ?? null;

                $inv = (float) ($shopify->inv ?? 0);
                $ovL30 = (float) ($shopify->quantity ?? 0);
                $temuL30 = (int) ($l30ByNormalizedSku[$normalizeSku($sku)] ?? 0);
                $goodsIdKey = $temuMetric
                    ? TemuGoodsIdHelper::normalizeKey($temuMetric->goods_id ?? null)
                    : null;
                $viewDataItem = $goodsIdKey ? $viewDataByGoodsId->get($goodsIdKey) : null;
                $oClicks = $viewDataItem ? max(0, (int) $viewDataItem->product_clicks) : 0;
                $productClicks = $viewDataItem
                    ? $oClicks
                    : max(0, (int) ($temuMetric?->product_clicks_l30 ?? 0));
                $adsViews = $goodsIdKey ? max(0, (int) ($adsViewsByGoodsId->get($goodsIdKey) ?? 0)) : 0;
                $views = $oClicks > 0 ? $oClicks : ($productClicks + $adsViews);
                if ($views < 0) {
                    $views = 0;
                }
                $cvrPercent = $views > 0 ? round(($temuL30 / $views) * 100, 2) : 0.0;
                // Same CVR 45 / CVR 60 definition as /temu2-decrease: one Views denominator,
                // L45 units being the midpoint of the current and prior 30-day windows.
                $temuL60 = (int) ($l60ByNormalizedSku[$normalizeSku($sku)] ?? 0);
                $temuL45 = round(($temuL30 + $temuL60) / 2, 2);
                $cvr45 = $views > 0 ? round(($temuL45 / $views) * 100, 2) : 0.0;
                $cvr60 = $views > 0 ? round(($temuL60 / $views) * 100, 2) : 0.0;

                $buyerLink = '';
                $sellerLink = '';
                if ($listingStatus) {
                    $statusValue = is_array($listingStatus->value)
                        ? $listingStatus->value
                        : (json_decode((string) $listingStatus->value, true) ?? []);
                    if (!empty($statusValue['buyer_link'])) {
                        $buyerLink = $statusValue['buyer_link'];
                    }
                    if (!empty($statusValue['seller_link'])) {
                        $sellerLink = $statusValue['seller_link'];
                    }
                }

                $dilPct = $inv == 0.0 ? 0.0 : round(($ovL30 / $inv) * 100, 2);
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);
                $lp = $this->productMasterLp($values, $pm);
                $temuShip = ProductMasterTemuShip::forPricing($values, $pm);
                $basePrice = $temuMetric ? (float) ($temuMetric->base_price ?? 0) : 0.0;
                $sheetBase = $sheetPriceBySku[$normalizeSku($sku)] ?? null;
                if ($sheetBase !== null) {
                    $basePrice = $sheetBase;
                }
                $rPrice = TemuShopifySalesService::computeRPrice($basePrice);
                $tPrice = $basePrice > 0
                    ? round(TemuShopifySalesService::computeFullTemuPrice($basePrice), 2)
                    : 0.0;
                $amazonPrice = $this->lookupMappedPrice($amazonPriceBySku, $sku, $normalizeSku);
                $ebayPrice = $this->lookupMappedPrice($ebayPriceBySku, $sku, $normalizeSku);
                $ebay2Price = $this->lookupMappedPrice($ebay2PriceBySku, $sku, $normalizeSku);
                $stdPrc = $lookupStdPrc($sku);
                if ($stdPrc === null) {
                    foreach ($this->lmpSkuGroupService->groupContaining($sku) as $linkedSku) {
                        $stdPrc = $lookupStdPrc($linkedSku);
                        if ($stdPrc !== null) {
                            break;
                        }
                    }
                }

                $lmpInfo = $this->resolveTemu2Lmp($sku, $temuLmpByNormalizedSku, $normalizeSku);
                $suggested = $suggestedStore->readSaved($sku);
                $sprice = (float) ($suggested['sprice'] ?? 0);
                $sBasePrice = (float) ($suggested['s_base'] ?? 0);
                // S R Prc = S PRC × 0.88 (S PRC may be the LMP when capped). S Base is S R, or S R − $2.99 under $30.
                $sRPrice = TemuShopifySalesService::newTemuoneSRPrice($sprice);
                $spriceCap = [
                    'sprice' => $sprice,
                    'labels' => $suggested['labels'] ?? [],
                    'lmpAlert' => (bool) ($suggested['lmp_alert'] ?? false),
                ];

                // GPFT / GROI on the listing R Price (marketplace Temu margin).
                // SPFT / SGROI use the same 0.95 take-home as Sprc Dil. Dil + CVR
                // set SNROI; SGROI is live SPFT ÷ LP at that S PRC.
                $gpftDollars = TemuShopifySalesService::computeGroiProfit($rPrice, $percentage, $lp, $temuShip);
                $gpftPercent = ($rPrice > 0 && $tPrice > 0) ? round(($gpftDollars / $tPrice) * 100, 2) : 0.0;
                $groiPercent = ($rPrice > 0 && $lp > 0) ? round(($gpftDollars / $lp) * 100, 2) : 0.0;
                $spftDollars = ($sRPrice > 0)
                    ? ($sRPrice * TemuShopifySalesService::DECREASE_TAKEHOME) - $temuShip - $lp
                    : 0.0;
                $sgpftPercent = ($sRPrice > 0 && $sprice > 0) ? round(($spftDollars / $sprice) * 100, 2) : 0.0;
                $sroiPercent = ($sRPrice > 0 && $lp > 0) ? round(($spftDollars / $lp) * 100, 2) : 0.0;
                $sgroiPercent = $suggested['sgroi'];

                // NPFT = Gpft − (T Price × Ads%); SNPFT = SPFT − (S PRC × Ads%).
                $npftDollars = $gpftDollars - ($tPrice * ($adsPercent / 100));
                $npftPercent = ($rPrice > 0 && $tPrice > 0) ? round(($npftDollars / $tPrice) * 100, 2) : 0.0;
                $nroiPercent = ($rPrice > 0 && $lp > 0) ? round(($npftDollars / $lp) * 100, 2) : 0.0;
                $snpftDollars = $spftDollars - ($sprice * ($adsPercent / 100));
                $snpftPercent = ($sRPrice > 0 && $sprice > 0) ? round(($snpftDollars / $sprice) * 100, 2) : 0.0;
                $snroiPercent = ($sRPrice > 0 && $lp > 0) ? round(($snpftDollars / $lp) * 100, 2) : 0.0;

                $result[] = [
                    'Parent' => $pm->parent,
                    'parent' => $pm->parent,
                    'sku' => $sku,
                    '(Child) sku' => $sku,
                    'INV' => $inv,
                    'inventory' => $inv,
                    'L30' => $ovL30,
                    'temu_l30' => $temuL30,
                    'temu_l45' => $temuL45,
                    'temu_l60' => $temuL60,
                    'views' => $views,
                    'cvr_percent' => $cvrPercent,
                    'cvr_30' => $cvrPercent,
                    'cvr_45' => $cvr45,
                    'cvr_60' => $cvr60,
                    'Dil%' => $dilPct,
                    'lp' => $lp,
                    'LP_productmaster' => $lp,
                    'temu_ship' => $temuShip,
                    'Ship_productmaster' => $temuShip,
                    'percentage' => $percentage,
                    'base_price' => $basePrice,
                    'temu_goods_id' => $temuMetric && $temuMetric->goods_id ? (string) $temuMetric->goods_id : null,
                    'temu_sku_id' => $temuMetric && $temuMetric->sku_id ? (string) $temuMetric->sku_id : null,
                    'r_price' => $rPrice,
                    't_price' => $tPrice,
                    'temu_price' => $tPrice,
                    'STANDARD_PRICE' => $stdPrc,
                    'a_price' => $amazonPrice,
                    'e_price' => $ebayPrice,
                    'e2_price' => $ebay2Price,
                    'lmp' => $lmpInfo['recovery'],
                    'lmp_raw' => $lmpInfo['raw'],
                    'lmp_link' => $lmpInfo['link'],
                    'lmp_entries' => $lmpInfo['entries'],
                    'sprice' => $sprice > 0 ? $sprice : null,
                    'SPRICE' => $sprice > 0 ? $sprice : null,
                    's_base_price' => $sBasePrice > 0 ? $sBasePrice : null,
                    's_r_price' => $sRPrice > 0 ? $sRPrice : null,
                    'profit_percent' => $gpftPercent,
                    'roi_percent' => $groiPercent,
                    'sgpft_percent' => $sgpftPercent,
                    'sroi_percent' => $sroiPercent,
                    'sgroi_percent' => $sgroiPercent,
                    'nto_use_saved' => (bool) ($suggested['use_saved'] ?? false),
                    'nto_capped' => (bool) ($suggested['capped'] ?? false),
                    'nto_pushed_base' => $suggested['pushed_base'] ?? null,
                    'nto_pushed_at' => $suggested['pushed_at'] ?? null,
                    'nto_push_status' => $suggested['push_status'] ?? null,
                    'nto_push_error_at' => $suggested['push_error_at'] ?? null,
                    'pm_applied_pct' => $suggested['pm_applied_pct'] ?? null,
                    'pm_applied_price' => $suggested['pm_applied_price'] ?? null,
                    'pm_applied_at' => $suggested['pm_applied_at'] ?? null,
                    'pm_activity_id' => $suggested['pm_activity_id'] ?? null,
                    'npft_percent' => $npftPercent,
                    'nroi_percent' => $nroiPercent,
                    'snpft_percent' => $snpftPercent,
                    'snroi_percent' => $snroiPercent,
                    'sprice_labels' => $spriceCap['labels'],
                    'sprice_lmp_alert' => $spriceCap['lmpAlert'],
                    'B Link' => $buyerLink,
                    'S Link' => $sellerLink,
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error fetching New Temu Two data: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function saveLinks(Request $request)
    {
        $sku = trim((string) $request->input('sku'));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }

        $buyerLink = trim((string) $request->input('buyer_link', ''));
        $sellerLink = trim((string) $request->input('seller_link', ''));

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $field => $val) {
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'Invalid URL for ' . $field], 422);
            }
        }

        $status = Temu2ListingStatus::where('sku', $sku)->first();
        $rawValue = $status ? $status->getRawOriginal('value') : null;
        $existing = $status ? $status->value : [];
        if (!is_array($existing)) {
            $existing = is_string($rawValue) && $rawValue !== ''
                ? (json_decode($rawValue, true) ?: [])
                : [];
        }

        $existing['buyer_link'] = $buyerLink;
        $existing['seller_link'] = $sellerLink;

        Temu2ListingStatus::updateOrCreate(
            ['sku' => $sku],
            ['value' => $existing]
        );

        return response()->json([
            'success' => true,
            'buyer_link' => $buyerLink,
            'seller_link' => $sellerLink,
        ]);
    }

    /**
     * Units per SKU from temu2_orders for one window.
     *
     * TemuShopifySalesService::getOrdersTableRows() answers the same question, but it
     * hydrates every order model and joins product masters, metrics and margins to build
     * a full row per order — two windows of that exhausted a 128 MB request. The window,
     * the cancel filter and the SKU (ext_code, falling back to display_sku) match it
     * exactly; only the summing moved into SQL.
     *
     * @return array<string, int> raw order SKU => units
     */
    private function temuOrderQtyBySku(Carbon $start, Carbon $end): array
    {
        if (! $this->hasTable('temu2_orders')) {
            return [];
        }

        $appTz = config('app.timezone');

        // Grouped on the raw columns rather than the fallback expression: ONLY_FULL_GROUP_BY
        // rejects grouping by an expression containing a literal. One row per SKU pair.
        $rows = Temu2Order::query()
            ->whereBetween('parent_order_time', [
                $start->copy()->setTimezone($appTz),
                $end->copy()->setTimezone($appTz),
            ])
            ->where(function ($q) {
                $q->whereNull('order_status_text')
                    ->orWhereRaw('UPPER(order_status_text) NOT IN (?, ?)', ['CANCELED', 'CANCELLED']);
            })
            ->selectRaw('ext_code, display_sku, SUM(quantity) as units')
            ->groupBy('ext_code', 'display_sku')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->ext_code ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($row->display_sku ?? ''));
            }
            if ($sku === '') {
                continue;
            }
            $out[$sku] = ($out[$sku] ?? 0) + (int) $row->units;
        }

        return $out;
    }

    private function productMasterLp(array $values, ProductMaster $pm): float
    {
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp' && is_numeric($v)) {
                return (float) $v;
            }
        }
        if (isset($pm->lp) && is_numeric($pm->lp)) {
            return (float) $pm->lp;
        }
        if (isset($pm->LP) && is_numeric($pm->LP)) {
            return (float) $pm->LP;
        }

        return 0.0;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return array<string, float>
     */
    private function buildPriceLookup(iterable $rows, callable $normalizeSku, callable $valueFn): array
    {
        $out = [];
        foreach ($rows as $row) {
            $val = $valueFn($row);
            if (! is_numeric($val) || (float) $val <= 0) {
                continue;
            }
            $rounded = round((float) $val, 2);
            $raw = trim((string) ($row->sku ?? ''));
            if ($raw === '') {
                continue;
            }
            $out[$raw] = $rounded;
            $out[strtoupper($raw)] = $rounded;
            $norm = $normalizeSku($raw);
            if ($norm !== '') {
                $out[$norm] = $rounded;
            }
        }

        return $out;
    }

    private function lookupMappedPrice(array $map, string $sku, callable $normalizeSku): float
    {
        $raw = trim($sku);
        if ($raw === '') {
            return 0.0;
        }

        return (float) ($map[$raw] ?? $map[strtoupper($raw)] ?? $map[$normalizeSku($raw)] ?? 0);
    }

    /**
     * Same Std Prc source as /temu2-decrease: amazon_data_view.STANDARD_PRICE.
     */
    private function buildStdPriceLookup(array $skus, callable $normalizeSku): \Closure
    {
        $stdLookupSkus = $skus;
        foreach ($skus as $pageSku) {
            foreach ($this->lmpSkuGroupService->groupContaining((string) $pageSku) as $memberSku) {
                $stdLookupSkus[] = $memberSku;
            }
        }
        $stdLookupSkus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $stdLookupSkus
        ))));

        $amazonStandardPrices = [];
        $indexAmazonStdPrc = function ($skuKey, $std) use (&$amazonStandardPrices, $normalizeSku) {
            if (! is_numeric($std) || (float) $std <= 0) {
                return;
            }
            $rounded = round((float) $std, 2);
            $raw = trim((string) $skuKey);
            if ($raw === '') {
                return;
            }
            $amazonStandardPrices[$raw] = $rounded;
            $amazonStandardPrices[strtoupper($raw)] = $rounded;
            $norm = $normalizeSku($raw);
            if ($norm !== '') {
                $amazonStandardPrices[$norm] = $rounded;
            }
        };

        foreach (array_chunk($stdLookupSkus, 500) as $chunk) {
            foreach (AmazonDataView::whereIn('sku', $chunk)->get(['sku', 'value']) as $adv) {
                $val = is_array($adv->value)
                    ? $adv->value
                    : (json_decode((string) ($adv->value ?? ''), true) ?: []);
                $indexAmazonStdPrc($adv->sku, $val['STANDARD_PRICE'] ?? null);
            }
        }

        $normalizedStdSet = [];
        foreach ($stdLookupSkus as $s) {
            $n = $normalizeSku($s);
            if ($n !== '') {
                $normalizedStdSet[$n] = true;
            }
        }
        if ($normalizedStdSet !== []) {
            $matchedAmazonSkus = [];
            foreach (AmazonDataView::query()->pluck('sku') as $asku) {
                $n = $normalizeSku($asku);
                if ($n !== '' && isset($normalizedStdSet[$n])) {
                    $matchedAmazonSkus[] = $asku;
                }
            }
            foreach (array_chunk(array_values(array_unique($matchedAmazonSkus)), 500) as $chunk) {
                foreach (AmazonDataView::whereIn('sku', $chunk)->get(['sku', 'value']) as $adv) {
                    $n = $normalizeSku($adv->sku);
                    $val = is_array($adv->value)
                        ? $adv->value
                        : (json_decode((string) ($adv->value ?? ''), true) ?: []);
                    $indexAmazonStdPrc($adv->sku, $val['STANDARD_PRICE'] ?? null);
                    $indexAmazonStdPrc($n, $val['STANDARD_PRICE'] ?? null);
                }
            }
        }

        return function ($candidate) use ($amazonStandardPrices, $normalizeSku) {
            $raw = trim((string) $candidate);
            if ($raw === '') {
                return null;
            }

            return $amazonStandardPrices[$raw]
                ?? $amazonStandardPrices[strtoupper($raw)]
                ?? $amazonStandardPrices[$normalizeSku($raw)]
                ?? null;
        };
    }

    /**
     * Same LMP as /temu2-decrease: lowest Price+Delivery from temu_lmp (Sku Link group).
     *
     * @param  array<string, TemuLmp>  $temuLmpByNormalizedSku
     * @return array{raw: float|null, recovery: float|null, link: string|null, entries: list<array>}
     */
    private function resolveTemu2Lmp(string $sku, array $temuLmpByNormalizedSku, callable $normalizeSku): array
    {
        $linkedLmpSkus = $this->lmpSkuGroupService->groupContaining($sku);
        if ($linkedLmpSkus === []) {
            $linkedLmpSkus = [$sku];
        }

        $lmpEntries = [];
        foreach ($linkedLmpSkus as $linkedSku) {
            $temuLmpRow = $temuLmpByNormalizedSku[$normalizeSku($linkedSku)] ?? null;
            if (! $temuLmpRow) {
                continue;
            }
            foreach ($this->extractTemuLmpEntries($temuLmpRow) as $e) {
                if (! is_array($e)) {
                    continue;
                }
                $e['source_sku'] = (string) ($temuLmpRow->sku ?? $linkedSku);
                $lmpEntries[] = $e;
            }
        }
        $lmpEntries = $this->dedupeTemuLmpEntries($lmpEntries);
        $temuLmpRow = $temuLmpByNormalizedSku[$normalizeSku($sku)] ?? null;

        $activeEntries = array_values(array_filter($lmpEntries, fn ($e) => empty($e['ignored'])));
        $prices = [];
        foreach ($activeEntries as $e) {
            $eff = $this->temuLmpEntryEffectivePrice($e);
            if ($eff !== null) {
                $prices[] = $eff;
            }
        }
        $lmpRaw = count($prices) > 0 ? min($prices) : null;
        $lmpLink = null;
        if ($lmpRaw !== null) {
            foreach ($activeEntries as $e) {
                $eff = $this->temuLmpEntryEffectivePrice($e);
                if ($eff !== null && round($eff, 2) === round((float) $lmpRaw, 2)) {
                    $lmpLink = $e['link'] ?? null;
                    break;
                }
            }
        }
        if ($lmpRaw === null && $lmpEntries === [] && $temuLmpRow) {
            $legacy = $temuLmpRow->lmp;
            $lmpRaw = ($legacy !== null && $legacy !== '' && is_numeric($legacy)) ? (float) $legacy : null;
            $lmpLink = $temuLmpRow->lmp_link;
        }

        return [
            'raw' => $lmpRaw,
            'recovery' => $this->temuLmpRecoveryPrice($lmpRaw),
            'link' => $lmpLink,
            'entries' => $lmpEntries,
        ];
    }

    private function extractTemuLmpEntries(?TemuLmp $temuLmpRow): array
    {
        if (! $temuLmpRow) {
            return [];
        }

        $entries = $temuLmpRow->lmp_entries;
        if (is_array($entries)) {
            return array_values($entries);
        }

        $lmpEntries = [];
        if ($temuLmpRow->lmp !== null || $temuLmpRow->lmp_link) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp, 'link' => $temuLmpRow->lmp_link];
        }
        if ($temuLmpRow->lmp_2 !== null || $temuLmpRow->lmp_link_2) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp_2, 'link' => $temuLmpRow->lmp_link_2];
        }

        return $lmpEntries;
    }

    private function temuLmpEntryEffectivePrice(?array $entry): ?float
    {
        if (! is_array($entry)) {
            return null;
        }
        $price = $entry['price'] ?? null;
        if ($price === null || $price === '' || ! is_numeric($price)) {
            return null;
        }
        $p = (float) $price;
        if (! ($p > 0) && $p !== 0.0) {
            return null;
        }
        $delivery = $entry['delivery'] ?? 0;
        $d = (is_numeric($delivery) && (float) $delivery > 0) ? (float) $delivery : 0.0;
        if ($d <= 0 && $p < 27) {
            $d = 2.99;
        }

        return round($p + $d, 2);
    }

    private function temuLmpRecoveryPrice($price): ?float
    {
        if ($price === null || $price === '' || ! is_numeric($price)) {
            return null;
        }
        $p = (float) $price;
        if (! ($p > 0)) {
            return null;
        }
        if ($p <= 27) {
            return round(($p * 0.85) + 2.99, 2);
        }

        return round($p * 0.85, 2);
    }

    private function dedupeTemuLmpEntries(array $entries): array
    {
        $seen = [];
        $out = [];

        foreach ($entries as $entry) {
            $price = $entry['price'] ?? null;
            $link = strtoupper(trim((string) ($entry['link'] ?? '')));
            $key = (string) $price.'|'.$link;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $entry;
        }

        usort($out, function ($a, $b) {
            $pa = ($a['price'] ?? null) !== null && $a['price'] !== '' ? (float) $a['price'] : PHP_FLOAT_MAX;
            $pb = ($b['price'] ?? null) !== null && $b['price'] !== '' ? (float) $b['price'] : PHP_FLOAT_MAX;

            return $pa <=> $pb;
        });

        return $out;
    }
}
