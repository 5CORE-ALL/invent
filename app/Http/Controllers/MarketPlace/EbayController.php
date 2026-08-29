<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\EbayMetric;
use App\Models\ShopifySku;
use App\Models\EbayDataView;
use Illuminate\Http\Request;
use App\Models\EbayGeneralReport;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ApiController;
use App\Models\LmpCompetitorHistory;
use App\Services\LmpSkuGroupService;
use App\Models\ChannelMaster;
use App\Models\ADVMastersData;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster; 
use App\Models\EbaySkuDailyData;
use App\Models\AmazonDataView;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Models\EbayListingStatus;
use App\Services\EbayApiService;
use App\Services\Ebay1PromotionService;
use App\Services\EbayPushService;
use App\Services\EbayLivePriceFetcher;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Exception;
use App\Models\AmazonChannelSummary;
use App\Models\ChannelMasterSummary;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Services\ChannelPromoPricingService;
use App\Support\Marketplace\ChannelMasterViewsGuard;
use App\Support\Marketplace\EbayListingEnded;

class EbayController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function ebayView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;


        return view("market-places.ebay", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
            "ebayAdUpdates" => $adUpdates,
        ]);
    }

    public function ebayTabulatorView(Request $request)
    {
        // Sales / Qty / PFT / COGS / GPFT% / GROI% — all derived from the SAME real-orders
        // rows /ebay/daily-sales builds (EbaySalesController::getData), so every summary
        // badge on this page agrees with that page. The per-SKU datasheet is tax-excluded,
        // lags the Orders API, and only reflects filtered rows, so it can't match.
        $agg = $this->fetchEbayL30OrdersAggregate();

        // Ads% badge = TACOS = channel Total Ad Spend (31-day KW+PMT, same source as
        // /ebay/campaign-ads) ÷ the SAME real-orders L30 sales shown in the Sales badge,
        // so the Ads% is consistent with this page's Sales (not the marketplace_daily_metrics
        // sales the /all-marketplace-master value uses).
        $ebayAdSpend = app(ChannelMasterController::class)->getEbayMasterAdSpend();
        $channelAdsPercent = $agg['sales'] > 0
            ? round(($ebayAdSpend / $agg['sales']) * 100, 1)
            : 0.0;

        // NROI% = (GPFT$ − Ad Spend) / COGS × 100 — same shape as Amazon NROI badge
        // (do not cut Ads% from GROI%).
        $ordersL30Nroi = $agg['cogs'] > 0
            ? round((($agg['pft'] - $ebayAdSpend) / $agg['cogs']) * 100, 1)
            : 0.0;

        return view("market-places.ebay_tabulator_view", [
            'ebayTakeHome'        => MarketplacePercentage::takeHomeDecimal('Ebay'),
            'channelAdsPercent'   => $channelAdsPercent,
            'ebayAdSpend'         => round((float) $ebayAdSpend, 2),
            'ordersL30TotalQty'   => $agg['qty'],
            'ordersL30TotalSales' => $agg['sales'],
            'ordersL30Gpft'       => $agg['gpft'],
            'ordersL30Groi'       => $agg['groi'],
            'ordersL30Pft'        => $agg['pft'],
            'ordersL30Cogs'       => $agg['cogs'],
            'ordersL30Nroi'       => $ordersL30Nroi,
            'lastGoodCvrViews'    => (float) (ChannelMasterViewsGuard::lastTrusted('ebay')['views'] ?? 0),
        ]);
    }

    /**
     * L30 Sales / Qty / PFT / COGS / GPFT% / GROI% computed from the exact same rows
     * /ebay/daily-sales renders (EbaySalesController::getData), aggregated the same way
     * that page's summary does. Guarantees the tabulator badges match /ebay/daily-sales:
     *   - Sales  = Σ per-order total (tax incl.), once per order, excl. CANCELED/FULLY_REFUNDED
     *   - Qty    = Σ item quantity (same exclusions)
     *   - GPFT%  = Σ T PFT / Σ (qty × unit price) × 100
     *   - GROI%  = Σ T PFT / Σ COGS × 100
     */
    private function fetchEbayL30OrdersAggregate(): array
    {
        $empty = ['sales' => 0.0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0, 'gpft' => 0.0, 'groi' => 0.0];
        try {
            if (! Schema::hasTable('ebay_orders') || ! Schema::hasTable('ebay_order_items')) {
                return $empty;
            }

            $orders = \App\Models\EbayOrder::with('items')->where('period', 'l30')->get();
            $skus = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    if (! empty($item->sku)) {
                        $skus[] = $item->sku;
                    }
                }
            }
            $skus = array_values(array_unique($skus));
            $productMasters = $skus !== []
                ? ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku')
                : collect();

            $qty = 0;
            $pft = 0.0;
            $cogs = 0.0;
            $l30Sales = 0.0;
            $orderSales = 0.0;

            foreach ($orders as $order) {
                $raw = is_array($order->raw_data)
                    ? $order->raw_data
                    : json_decode((string) $order->raw_data, true);
                if (is_array($raw)) {
                    $cancelState = $raw['cancelStatus']['cancelState'] ?? '';
                    $paymentStatus = $raw['orderPaymentStatus'] ?? '';
                    if ($cancelState === 'CANCELED' || $paymentStatus === 'FULLY_REFUNDED') {
                        continue;
                    }
                }

                $orderTotal = (float) ($order->total_amount ?? 0);
                if (is_array($raw)) {
                    $base = (float) ($raw['pricingSummary']['total']['value'] ?? 0);
                    $carTax = 0.0;
                    foreach (($raw['lineItems'] ?? []) as $li) {
                        foreach (($li['ebayCollectAndRemitTaxes'] ?? []) as $t) {
                            $carTax += (float) ($t['amount']['value'] ?? 0);
                        }
                    }
                    $computed = $base + $carTax;
                    if ($computed > 0) {
                        $orderTotal = $computed;
                    }
                }
                $orderSales += round($orderTotal, 2);

                foreach ($order->items as $item) {
                    $pm = $productMasters[$item->sku] ?? null;
                    $lp = 0.0;
                    $ship = 0.0;
                    $weightAct = 0.0;
                    if ($pm) {
                        $values = is_array($pm->Values)
                            ? $pm->Values
                            : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                        if (! is_array($values)) {
                            $values = [];
                        }
                        foreach ($values as $k => $v) {
                            if (strtolower((string) $k) === 'lp') {
                                $lp = (float) $v;
                                break;
                            }
                        }
                        if ($lp === 0.0 && isset($pm->lp)) {
                            $lp = (float) $pm->lp;
                        }
                        $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0.0);
                        $weightAct = isset($values['wt_act']) ? (float) $values['wt_act'] : 0.0;
                    }

                    $quantity = (float) ($item->quantity ?? 0);
                    $price = (float) ($item->price ?? 0);
                    $tWeight = $weightAct * $quantity;
                    if ($quantity == 1) {
                        $shipCost = $ship;
                    } elseif ($quantity > 1 && $tWeight < 20) {
                        $shipCost = $ship / $quantity;
                    } else {
                        $shipCost = $ship;
                    }
                    $unitPrice = $quantity > 0 ? ($price / $quantity) : 0.0;
                    $pftEach = ($unitPrice * 0.85) - $lp - $shipCost;

                    $qty += (int) $quantity;
                    $pft += $pftEach * $quantity;
                    $cogs += $lp * $quantity;
                    $l30Sales += $quantity * $unitPrice;
                }
            }

            return [
                'sales' => round($orderSales, 2),
                'qty'   => $qty,
                'pft'   => round($pft, 2),
                'cogs'  => round($cogs, 2),
                'gpft'  => $l30Sales > 0 ? round(($pft / $l30Sales) * 100, 1) : 0.0,
                'groi'  => $cogs > 0 ? round(($pft / $cogs) * 100, 1) : 0.0,
            ];
        } catch (\Throwable $e) {
            Log::warning('fetchEbayL30OrdersAggregate failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Σ ebay_order_items.quantity for orders with period='l30' — same query path
     * EbaySalesController::getData walks for /ebay/daily-sales.
     */
    private function fetchEbayL30OrderQty(): int
    {
        try {
            $total = 0;
            \App\Models\EbayOrder::with('items')
                ->where('period', 'l30')
                ->get()
                ->each(function ($order) use (&$total) {
                    foreach ($order->items as $item) {
                        $qty = (int) ($item->quantity ?? 0);
                        if ($qty > 0) $total += $qty;
                    }
                });
            return $total;
        } catch (\Throwable $e) {
            Log::warning('fetchEbayL30OrderQty failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Per-SKU and per-listing L30 units from ebay_orders (period=l30).
     * Same cancel/refund exclusions as fetchEbayL30OrdersAggregate / Qty badge.
     * Used to overlay the E L30 column so CVR vs CPN Apply sees real sales
     * when ebay_metrics.ebay_l30 is stale or still 0.
     *
     * @return array{sku: array<string,int>, item: array<string,int>}
     */
    private function fetchEbayL30OrderQtyMaps(): array
    {
        $bySku = [];
        $byItem = [];
        try {
            if (! Schema::hasTable('ebay_orders') || ! Schema::hasTable('ebay_order_items')) {
                return ['sku' => $bySku, 'item' => $byItem];
            }

            $orders = \App\Models\EbayOrder::with('items')->where('period', 'l30')->get();
            foreach ($orders as $order) {
                $raw = is_array($order->raw_data)
                    ? $order->raw_data
                    : json_decode((string) $order->raw_data, true);
                if (is_array($raw)) {
                    $cancelState = $raw['cancelStatus']['cancelState'] ?? '';
                    $paymentStatus = $raw['orderPaymentStatus'] ?? '';
                    if ($cancelState === 'CANCELED' || $paymentStatus === 'FULLY_REFUNDED') {
                        continue;
                    }
                }
                foreach ($order->items as $line) {
                    $qty = (int) ($line->quantity ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($line->sku ?? ''));
                    if ($norm !== '') {
                        $bySku[$norm] = ($bySku[$norm] ?? 0) + $qty;
                    }
                    $itemId = trim((string) ($line->item_id ?? ''));
                    if ($itemId !== '' && $itemId !== '0') {
                        $byItem[$itemId] = ($byItem[$itemId] ?? 0) + $qty;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('fetchEbayL30OrderQtyMaps failed: ' . $e->getMessage());
        }

        return ['sku' => $bySku, 'item' => $byItem];
    }

    /** Cast a request value to float, treating blanks/nulls as "no bound". */
    private function numOrNull($v)
    {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        return (float) $v;
    }

    /**
     * Previous same 7-day period for L7 Views.
     * Uses the rolling `l7_views` snapshot from ~7 days ago in ebay_sku_daily_data
     * (that snapshot = views in what is now days 8–14). California calendar.
     *
     * @param  array<int, string>  $skus
     * @return array<string, int|null>  normalized SKU => prior L7 (null = no snapshot yet)
     */
    private function previousPeriodL7ViewsBySku(array $skus): array
    {
        $out = [];
        $normalize = static fn ($s) => ShopifySku::normalizeSkuForShopifyLookup((string) $s);
        foreach ($skus as $s) {
            $k = $normalize($s);
            if ($k !== '') {
                $out[$k] = null;
            }
        }
        if ($out === []) {
            return $out;
        }

        $today = Carbon::now('America/Los_Angeles')->startOfDay();
        // Prefer exact day-7 snapshot; allow nearby days if that date is missing.
        $targetDate = $today->copy()->subDays(7)->toDateString();
        $loadStart = $today->copy()->subDays(10)->toDateString();
        $loadEnd = $today->copy()->subDays(5)->toDateString();

        $skuCandidates = array_values(array_unique(array_filter(array_merge(
            array_map('strval', $skus),
            array_keys($out)
        ))));

        /** @var array<string, array<string, int>> $bySku date => l7_views */
        $bySku = [];
        foreach (array_chunk($skuCandidates, 400) as $chunk) {
            $rows = DB::table('ebay_sku_daily_data')
                ->where('record_date', '>=', $loadStart)
                ->where('record_date', '<=', $loadEnd)
                ->whereIn('sku', $chunk)
                ->get(['sku', 'record_date', 'daily_data']);

            foreach ($rows as $r) {
                $canon = $normalize($r->sku);
                if ($canon === '' || ! array_key_exists($canon, $out)) {
                    continue;
                }
                $data = is_string($r->daily_data)
                    ? (json_decode($r->daily_data, true) ?: [])
                    : (array) ($r->daily_data ?? []);
                if (! array_key_exists('l7_views', $data)) {
                    continue; // older snapshots without L7 — ignore
                }
                $d = Carbon::parse($r->record_date)->toDateString();
                $bySku[$canon][$d] = (int) ($data['l7_views'] ?? 0);
            }
        }

        foreach ($bySku as $canon => $dates) {
            // All-zero window usually means l7_views was not collected yet — treat as no snapshot.
            $windowMax = max(array_values($dates));
            if ($windowMax <= 0) {
                continue;
            }

            if (isset($dates[$targetDate])) {
                $out[$canon] = $dates[$targetDate];
                continue;
            }
            // Nearest snapshot to day-7 (prefer earlier dates when tied).
            $bestDate = null;
            $bestDist = PHP_INT_MAX;
            foreach ($dates as $d => $_) {
                $dist = abs(Carbon::parse($d)->diffInDays(Carbon::parse($targetDate)));
                if ($dist < $bestDist || ($dist === $bestDist && ($bestDate === null || $d < $bestDate))) {
                    $bestDist = $dist;
                    $bestDate = $d;
                }
            }
            if ($bestDate !== null) {
                $out[$canon] = $dates[$bestDate];
            }
        }

        return $out;
    }

    /**
     * SBID Rule (slab builder) — a list of rules that decide the S Bid column on
     * /ebay-tabulator-view. Each rule is a For L7 Views min/max range plus the
     * S Bid to apply. Rules are evaluated top to bottom — the first rule whose
     * range matches a row wins.
     *
     * Stored (shared across users) under key `ebay1_sbid_slabs` in ebay_sbid_rules.
     */
    public function getSbidSlabRule()
    {
        $row = DB::table('ebay_sbid_rules')->where('key', 'ebay1_sbid_slabs')->first();
        $decoded = $row ? json_decode($row->rule, true) : null;
        $rules = is_array($decoded['rules'] ?? null) ? $decoded['rules'] : [];
        $esBid = $this->numOrNull($decoded['es_bid'] ?? null);

        if ($this->sbidSlabsNeedViewStepMigrate($rules)) {
            $rules = $this->defaultSbidSlabRules();
            DB::table('ebay_sbid_rules')->updateOrInsert(
                ['key' => 'ebay1_sbid_slabs'],
                ['rule' => json_encode(['rules' => $rules, 'es_bid' => $esBid]), 'updated_at' => now()]
            );
        }

        return response()->json([
            'rules' => $rules,
            'es_bid' => $esBid,
        ]);
    }

    public function saveSbidSlabRule(Request $request)
    {
        $rules = $request->input('rules', []);

        if (!is_array($rules)) {
            return response()->json(['error' => 'Invalid rule data'], 422);
        }

        $clean = [];
        foreach ($rules as $r) {
            if (!is_array($r)) continue;
            $clean[] = [
                'label'      => isset($r['label']) ? (string) $r['label'] : '',
                'cvr_min'    => $this->numOrNull($r['cvr_min']    ?? null),
                'cvr_max'    => $this->numOrNull($r['cvr_max']    ?? null),
                'l7_views_min' => $this->numOrNull($r['l7_views_min'] ?? null),
                'l7_views_max' => $this->numOrNull($r['l7_views_max'] ?? null),
                'sbid'       => $this->numOrNull($r['sbid'] ?? null) ?? 0,
            ];
        }

        // Empty save → restore built-in defaults (same as get when no row).
        if ($clean === []) {
            $clean = $this->defaultSbidSlabRules();
        }

        $rule = [
            'rules' => $clean,
            'es_bid' => $this->numOrNull($request->input('es_bid')),
        ];

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => 'ebay1_sbid_slabs'],
            ['rule' => json_encode($rule), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    /**
     * Default View VS SBID slabs: 0–100, 101–200, … 901–1000, then >1000.
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultSbidSlabRules(): array
    {
        $rules = [];
        $bid = 15;
        for ($i = 0; $i < 10; $i++) {
            $min = $i === 0 ? 0 : ($i * 100) + 1;
            $max = ($i + 1) * 100;
            $rules[] = [
                'label' => $min . '–' . $max,
                'l7_views_min' => $min,
                'l7_views_max' => $max,
                'sbid' => $bid,
            ];
            $bid--;
        }
        $rules[] = [
            'label' => '>1000',
            'l7_views_min' => 1001,
            'l7_views_max' => null,
            'sbid' => $bid,
        ];

        return $rules;
    }

    /** Only seed defaults when nothing is stored. Never overwrite a saved rule. */
    private function sbidSlabsNeedViewStepMigrate(array $rules): bool
    {
        return $rules === [];
    }

       public function ebayViewData(Request $request)
    {
        return view("market-places.ebay_pricing_data");
    }

    public function ebayDataJson(Request $request)
    {
        try {
            $obLevel = ob_get_level();
            ob_start();
            $response = $this->getViewEbayData($request);
            $leaked = '';
            while (ob_get_level() > $obLevel) {
                $leaked .= (string) ob_get_clean();
            }
            if ($leaked !== '') {
                Log::warning('ebayDataJson: discarded leaked output during data build', [
                    'bytes' => strlen($leaked),
                    'snippet' => substr($leaked, 0, 400),
                ]);
            }

            $data = json_decode($response->getContent(), true);
            if (!is_array($data)) {
                Log::error('ebayDataJson: getViewEbayData returned non-JSON or invalid JSON', [
                    'snippet' => substr((string) $response->getContent(), 0, 400),
                ]);
                return response()->json(['error' => 'Invalid data payload from server'], 500);
            }
            $rows = $data['data'] ?? [];
            if (!is_array($rows)) {
                $rows = [];
            }

            array_walk_recursive($rows, static function (&$value) {
                if (is_float($value) && !is_finite($value)) {
                    $value = null;
                }
            });

            // Save snapshot after the JSON is sent so the table is not blocked.
            // Buffer any notices/html so they cannot append after the JSON body.
            dispatch(function () use ($rows) {
                $level = ob_get_level();
                ob_start();
                try {
                    app(self::class)->saveDailySummaryIfNeeded($rows);
                } catch (\Throwable $e) {
                    Log::error('Error saving daily eBay summary: ' . $e->getMessage());
                } finally {
                    while (ob_get_level() > $level) {
                        ob_end_clean();
                    }
                }
            })->afterResponse();

            $json = json_encode(
                $rows,
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if ($json === false) {
                Log::error('ebayDataJson: json_encode failed', [
                    'error' => json_last_error_msg(),
                ]);
                return response()->json(['error' => 'Failed to encode data'], 500);
            }

            return response($json, 200)
                ->header('Content-Type', 'application/json; charset=UTF-8');
        } catch (\Throwable $e) {
            Log::error('Error fetching eBay data for Tabulator: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $message = config('app.debug') ? $e->getMessage() : 'Failed to fetch data';
            return response()->json(['error' => $message], 500);
        }
    }

    public function getAdvEbayTotalSaveData(Request $request)
    {
        return ADVMastersData::getAdvEbayTotalSaveDataProceed($request);
    }

    public function ebayPricingCVR(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "ebay_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Ebay"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100;
            }
        );

        return view("market-places.ebay_pricing_cvr", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
        ]);
    }


    public function ebayPricingIncreaseDecrease(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "ebay_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Ebay"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100;
            }
        );

        $marketplaceData = MarketplacePercentage::where("marketplace", "Ebay")->first();
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        $listingStatus = EbayListingStatus::select("sku", "value")->get()->keyBy("sku");

        return view("market-places.ebay_pricing_increase_decrease", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
            "listingStatus" => $listingStatus,
            "ebayAdUpdates" => $adUpdates,
        ]);
    }
    
    public function ebayPricingIncrease(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "ebay_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Ebay"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100;
            }
        );

        return view("market-places.ebay_pricing_increase", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
        ]);
    }
    public function updateFbaStatusEbay(Request $request)
    {
        $sku = $request->input('shopify_id');
        $fbaStatus = $request->input('fba');

        if (!$sku || !is_numeric($fbaStatus)) {
            return response()->json(['error' => 'SKU and FBA status are required.'], 400);
        }
        $amazonData = DB::table('amazon_data_view')
            ->where('sku', $sku)
            ->first();

        if (!$amazonData) {
            return response()->json(['error' => 'SKU not found.'], 404);
        }
        DB::table('ebay_data_view')
            ->where('sku', $sku)
            ->update(['fba' => $fbaStatus]);
        $updatedData = DB::table('ebay_data_view')
            ->where('sku', $sku)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'FBA status updated successfully.',
            'data' => $updatedData
        ]);
    }

    public function getViewEbayData(Request $request)
    {
        // 1. Base ProductMaster fetch
        $productMasters = ProductMaster::query()
            ->whereRaw("UPPER(TRIM(sku)) NOT LIKE 'PARENT%'")
            ->orderBy("parent", "asc")
            ->orderBy("sku", "asc")
            ->get();


        // 2. SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

            $nonParentSkus = $skus;

        if (empty($skus)) {
            return response()->json([
                'message' => 'eBay Data Fetched Successfully',
                'data' => [],
                'status' => 200,
            ]);
        }

        // 3. Related Models (NBSP / Unicode space–safe PM ↔ shopify_skus match)
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Forecast Analysis NRP (forecast_analysis.nr) — same source as /forecast.analysis NRP column.
        $normalizeSkuFa = static fn ($value) => strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));
        $forecastNrpBySku = [];
        $pmKeysForFa = collect($skus)->map(fn ($s) => $normalizeSkuFa($s))->unique()->filter(fn ($s) => $s !== '')->values();
        if ($pmKeysForFa->isNotEmpty()) {
            $faRows = DB::table('forecast_analysis')
                ->whereIn(DB::raw('UPPER(TRIM(sku))'), $pmKeysForFa->all())
                ->get(['sku', 'parent', 'nr', 'stage']);
            foreach ($faRows->groupBy(fn ($r) => $normalizeSkuFa($r->sku)) as $k => $group) {
                $withStage = $group->first(function ($r) {
                    return $r->stage !== null && trim((string) $r->stage) !== '';
                });
                if ($withStage) {
                    $forecastNrpBySku[$k] = $withStage;

                    continue;
                }
                $withNr = $group->first(function ($r) {
                    return $r->nr !== null && trim((string) $r->nr) !== '';
                });
                $forecastNrpBySku[$k] = $withNr ?? $group->first();
            }
        }

        // Key by NBSP / Unicode space–safe normalized SKU: ebay_metrics.sku can contain
        // non-breaking spaces (U+00A0) while product_masters.sku uses normal spaces, which
        // otherwise breaks the lookup (item_id/price missing → row wrongly shows as Missing L).
        $ebayMetrics = EbayMetric::select(EbayListingEnded::withStatusColumn('ebay_metrics', [
                'sku',
                'ebay_l30',
                'ebay_l60',
                'ebay_l7',
                'ebay_price',
                'views',
                'l7_views',
                'item_id',
                'ebay_stock',
            ]))
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(function ($metric) {
                return ShopifySku::normalizeSkuForShopifyLookup($metric->sku);
            });

        $orderL30Maps = $this->fetchEbayL30OrderQtyMaps();
        $orderL30BySku = $orderL30Maps['sku'];
        $orderL30ByItem = $orderL30Maps['item'];

        // Prior-day Price / INV / OV L30 (California) for green/red/gray trend dots.
        // Latest snapshot before today (exact yesterday when collect ran; otherwise last known day).
        $todayPt = Carbon::now('America/Los_Angeles')->toDateString();
        $priceYesterdayBySku = [];
        $priceYesterdayDateBySku = [];
        $spriceYesterdayBySku = [];
        $spriceYesterdayDateBySku = [];
        $invYesterdayBySku = [];
        $l30YesterdayBySku = [];
        $latestPriorRows = collect();
        foreach (array_chunk($skus, 400) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sub = '(SELECT sku, MAX(record_date) AS max_date FROM ebay_sku_daily_data WHERE record_date < ? AND sku IN ('.$placeholders.') GROUP BY sku) as x';
            $latestPriorRows = $latestPriorRows->concat(
                DB::table('ebay_sku_daily_data as d')
                    ->join(DB::raw($sub), function ($join) {
                        $join->on('d.sku', '=', 'x.sku')->on('d.record_date', '=', 'x.max_date');
                    })
                    ->addBinding(array_merge([$todayPt], array_values($chunk)), 'join')
                    ->whereIn('d.sku', $chunk)
                    ->select('d.sku', 'd.daily_data', 'd.record_date')
                    ->get()
            );
        }
        foreach ($latestPriorRows as $hist) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($hist->sku ?? ''));
            if ($norm === '') {
                continue;
            }
            $data = is_array($hist->daily_data ?? null)
                ? $hist->daily_data
                : (json_decode($hist->daily_data ?? '{}', true) ?: []);
            $priceYesterdayBySku[$norm] = round((float) ($data['price'] ?? 0), 2);
            $recDate = $hist->record_date ?? null;
            $priceYesterdayDateBySku[$norm] = $recDate
                ? Carbon::parse($recDate, 'America/Los_Angeles')->toDateString()
                : null;
            if (isset($data['sprice']) && is_numeric($data['sprice']) && (float) $data['sprice'] > 0) {
                $spriceYesterdayBySku[$norm] = round((float) $data['sprice'], 2);
                $spriceYesterdayDateBySku[$norm] = $priceYesterdayDateBySku[$norm];
            }
            if (array_key_exists('ovl30', $data)) {
                $l30YesterdayBySku[$norm] = (int) $data['ovl30'];
            }
            if (array_key_exists('inv', $data)) {
                $invYesterdayBySku[$norm] = (int) $data['inv'];
            }
        }

        // Prefer shopifysku_inventory_history.closing_inventory for INV prior-day
        // (true inventory snapshots; daily_data inv is used only as fallback).
        if (Schema::hasTable('shopifysku_inventory_history')) {
            $invHistRows = collect();
            foreach (array_chunk($skus, 400) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $sub = '(SELECT sku, MAX(snapshot_date) AS max_date FROM shopifysku_inventory_history WHERE snapshot_date < ? AND sku IN ('.$placeholders.') GROUP BY sku) as x';
                $invHistRows = $invHistRows->concat(
                    DB::table('shopifysku_inventory_history as h')
                        ->join(DB::raw($sub), function ($join) {
                            $join->on('h.sku', '=', 'x.sku')->on('h.snapshot_date', '=', 'x.max_date');
                        })
                        ->addBinding(array_merge([$todayPt], array_values($chunk)), 'join')
                        ->whereIn('h.sku', $chunk)
                        ->select('h.sku', 'h.closing_inventory')
                        ->get()
                );
            }
            foreach ($invHistRows as $hist) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($hist->sku ?? ''));
                if ($norm === '') {
                    continue;
                }
                $invYesterdayBySku[$norm] = (int) ($hist->closing_inventory ?? 0);
            }
        }

        // Prior same period for L7 Views (days 8–14) — for L7 % change column.
        $prevL7ViewsBySku = $this->previousPeriodL7ViewsBySku($skus);

        // Std Prc — amazon_data_view.STANDARD_PRICE (same shared store as /amazon-tabulator-view)
        $amazonStandardPrices = [];
        foreach (AmazonDataView::whereIn('sku', $skus)->get(['sku', 'value']) as $adv) {
            $val = is_array($adv->value)
                ? $adv->value
                : (json_decode((string) ($adv->value ?? ''), true) ?: []);
            $std = $val['STANDARD_PRICE'] ?? null;
            if (is_numeric($std) && (float) $std > 0) {
                $amazonStandardPrices[strtoupper(trim((string) $adv->sku))] = round((float) $std, 2);
            }
        }

        // PRMT%/CPN%/DSC%/Appr/Push Prc — ebay_data_view.value (Amazon-format PEF_* / PUSH_PRC_* keys)
        $ebay1PromoMap = app(ChannelPromoPricingService::class)->mapForSkus('ebay1', $skus);

        // Prioritize COST_PER_SALE rows for bid_percentage (matching PMP Ads controller)
        $campaignListings = [];
        try {
            $campaignListings = DB::connection('apicentral')
                ->table('ebay_campaign_ads_listings as t')
                ->join(DB::raw('(SELECT listing_id, 
                                        MAX(CASE WHEN funding_strategy = "COST_PER_SALE" THEN id END) AS max_cps_id,
                                        MAX(id) AS max_id
                                 FROM ebay_campaign_ads_listings 
                                 GROUP BY listing_id) x'), 
                    function($join) {
                        $join->on('t.id', '=', DB::raw('COALESCE(x.max_cps_id, x.max_id)'));
                    })
                ->select('t.listing_id', 't.bid_percentage', 't.suggested_bid')
                ->get()
                ->keyBy('listing_id')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('eBay getViewEbayData: apicentral campaign listings unavailable: ' . $e->getMessage());
        }

        // Same prioritization as /ebay/campaign-ads page: latest COST_PER_SALE row per listing
        // (fallback to overall latest), source is the local `ebay_campaign_ads` table — the page's
        // own data feed — so C Bid / ES Bid / Promote here mirror that page exactly.
        $ebayCampaignAdsByListing = [];
        try {
            $ebayCampaignAdsByListing = DB::table('ebay_campaign_ads as t')
                ->join(DB::raw('(SELECT listing_id,
                                        MAX(CASE WHEN funding_strategy = "COST_PER_SALE" THEN id END) AS max_cps_id,
                                        MAX(id) AS max_id
                                 FROM ebay_campaign_ads
                                 GROUP BY listing_id) x'),
                    function ($join) {
                        $join->on('t.id', '=', DB::raw('COALESCE(x.max_cps_id, x.max_id)'));
                    })
                ->select('t.listing_id', 't.bid_percentage', 't.suggested_bid', 't.promote_with_ad')
                ->get()
                ->keyBy('listing_id')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('eBay getViewEbayData: ebay_campaign_ads unavailable: ' . $e->getMessage());
        }

        // Latest NR/REQ + links from Listing eBay page (source of truth).
        // Key by a normalized SKU (UPPER + trim) so saved rows — which saveSpriceToDatabase
        // stores uppercased/trimmed — are still matched on read regardless of the SKU's
        // original case/spacing (otherwise manual SPRICE would appear "not saved" on refresh).
        // Use UPPER(TRIM(sku)) IN (…) so casing drift from auto-push / saves cannot hide SPRICE.
        $nrValues = collect();
        if (! empty($skus)) {
            $upperSkus = array_values(array_unique(array_map(
                static fn ($s) => strtoupper(trim((string) $s)),
                $skus
            )));
            $placeholders = implode(',', array_fill(0, count($upperSkus), '?'));
            $nrValues = EbayDataView::query()
                ->whereRaw('UPPER(TRIM(sku)) IN ('.$placeholders.')', $upperSkus)
                ->get()
                ->keyBy(fn ($r) => strtoupper(trim((string) $r->sku)))
                ->map(fn ($r) => $r->value);
        }
        
        // Legacy listing status data for nr_req field (used as fallback)
        // Key listing status by lowercase SKU for case-insensitive lookup (UI sends upper/lower mixed)
        $listingStatusData = EbayListingStatus::whereIn("sku", $skus)
            ->get()
            ->mapWithKeys(function ($item) {
                return [strtolower($item->sku) => $item];
            });

        $ebayCampaignReportsByRange = EbayPriorityReport::whereIn('report_range', ['L30', 'L7', 'L1'])
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();
        $ebayCampaignReportsL30 = [];
        $ebayCampaignReportsL7 = [];
        $ebayCampaignReportsL1 = [];
        foreach ($ebayCampaignReportsByRange as $report) {
            $key = strtoupper(trim((string) $report->campaign_name));
            if ($key === '') {
                continue;
            }
            $range = (string) $report->report_range;
            if ($range === 'L30' && ! isset($ebayCampaignReportsL30[$key])) {
                $ebayCampaignReportsL30[$key] = $report;
            } elseif ($range === 'L7' && ! isset($ebayCampaignReportsL7[$key])) {
                $ebayCampaignReportsL7[$key] = $report;
            } elseif ($range === 'L1' && ! isset($ebayCampaignReportsL1[$key])) {
                $ebayCampaignReportsL1[$key] = $report;
            }
        }

        // Fetch last_sbid from day-before-yesterday records (for KW Ads LBID column)
        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $lastSbidReports = EbayPriorityReport::where('report_range', $dayBeforeYesterday)
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $lastSbidMap = [];
        foreach ($lastSbidReports as $report) {
            if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                $lastSbidMap[$report->campaign_id] = $report->last_sbid;
            }
        }

        // Fetch sbid_m from yesterday's records first, then L1 as fallback
        $sbidMReports = EbayPriorityReport::where(function($q) use ($yesterday) {
                $q->where('report_range', $yesterday)
                  ->orWhere('report_range', 'L1');
            })
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->sortBy(function($report) use ($yesterday) {
                return $report->report_range === $yesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');

        $sbidMMap = [];
        foreach ($sbidMReports as $campaignId => $reports) {
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->sbid_m)) {
                $sbidMMap[$report->campaign_id] = $report->sbid_m;
            }
        }

        // Fetch apprSbid from yesterday's records first, then L1 as fallback
        $apprSbidReports = EbayPriorityReport::where(function($q) use ($yesterday) {
                $q->where('report_range', $yesterday)
                  ->orWhere('report_range', 'L1');
            })
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->sortBy(function($report) use ($yesterday) {
                return $report->report_range === $yesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');

        $apprSbidMap = [];
        foreach ($apprSbidReports as $campaignId => $reports) {
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->apprSbid)) {
                $apprSbidMap[$report->campaign_id] = $report->apprSbid;
            }
        }

        $itemIds = $ebayMetrics->pluck('item_id')->filter()->unique()->values()->all();
        $ebayGeneralReportsL30 = $itemIds !== []
            ? EbayGeneralReport::where('report_range', 'L30')->whereIn('listing_id', $itemIds)->get()
            : collect();
        $ebayGeneralReportsL7 = $itemIds !== []
            ? EbayGeneralReport::where('report_range', 'L7')->whereIn('listing_id', $itemIds)->get()
            : collect();
        $ebayGeneralReportsL30ByListing = $ebayGeneralReportsL30->keyBy(function ($r) {
            return trim((string) $r->listing_id);
        });
        $ebayGeneralReportsL7ByListing = $ebayGeneralReportsL7->keyBy(function ($r) {
            return trim((string) $r->listing_id);
        });

        // Build item_id → SKU map from ebayMetrics (matching PMP Ads controller logic)
        $itemIdToSkuMap = [];
        foreach ($ebayMetrics as $metric) {
            if (!empty($metric->item_id)) {
                $itemIdToSkuMap[$metric->item_id] = strtoupper($metric->sku);
            }
        }

        // Aggregate PMT metrics by SKU from ALL general reports (matching PMP Ads adMetricsBySku)
        $pmtAdMetricsBySku = [];
        foreach ($ebayGeneralReportsL30 as $report) {
            $reportSku = $itemIdToSkuMap[$report->listing_id] ?? null;
            if (!$reportSku) continue;
            $pmtAdMetricsBySku[$reportSku]['Clk'] = ($pmtAdMetricsBySku[$reportSku]['Clk'] ?? 0) + (int) $report->clicks;
            $pmtAdMetricsBySku[$reportSku]['Imp'] = ($pmtAdMetricsBySku[$reportSku]['Imp'] ?? 0) + (int) $report->impressions;
            $pmtAdMetricsBySku[$reportSku]['Sls'] = ($pmtAdMetricsBySku[$reportSku]['Sls'] ?? 0) + (int) $report->sales;
            $pmtAdMetricsBySku[$reportSku]['GENERAL_SPENT'] = ($pmtAdMetricsBySku[$reportSku]['GENERAL_SPENT'] ?? 0) + (float) str_replace('USD ', '', $report->ad_fees ?? 0);
            $pmtAdMetricsBySku[$reportSku]['SALE_AMOUNT'] = ($pmtAdMetricsBySku[$reportSku]['SALE_AMOUNT'] ?? 0) + (float) str_replace('USD ', '', $report->sale_amount ?? 0);
        }

        // Aggregate PMT L7 metrics by SKU
        $pmtAdMetricsBySkuL7 = [];
        foreach ($ebayGeneralReportsL7 as $report) {
            $reportSku = $itemIdToSkuMap[$report->listing_id] ?? null;
            if (!$reportSku) continue;
            $pmtAdMetricsBySkuL7[$reportSku]['Clk'] = ($pmtAdMetricsBySkuL7[$reportSku]['Clk'] ?? 0) + (int) $report->clicks;
            $pmtAdMetricsBySkuL7[$reportSku]['GENERAL_SPENT'] = ($pmtAdMetricsBySkuL7[$reportSku]['GENERAL_SPENT'] ?? 0) + (float) str_replace('USD ', '', $report->ad_fees ?? 0);
        }

        // Extra clicks data by listing_id (matching PMP Ads extraClicksData)
        $extraClicksData = $ebayGeneralReportsL30->pluck('clicks', 'listing_id')->toArray();

        // Fetch LMP data from ebay_sku_competitors table (disconnected from repricer)
        $lmpLowestLookup = collect();
        $lmpDetailsLookup = collect();
        try {
            // Fetch all competitors and group by normalized SKU (handle line breaks, spaces, case)
            $lmpRecords = \App\Models\EbaySkuCompetitor::where('marketplace', 'ebay')
                ->where('total_price', '>', 0)
                ->orderBy('total_price', 'asc')
                ->get()
                ->groupBy(function ($item) {
                    return strtoupper(preg_replace('/\s+/', ' ', trim($item->sku)));
                });

            $lmpDetailsLookup = $lmpRecords;
            // L1 = lowest non-ignored (EbaySkuCompetitor::buildGroupedLookup)
            $lmpLowestLookup = $lmpRecords->map(function ($items) {
                $active = $items->filter(fn ($item) => empty($item->ignored));

                return $active->isNotEmpty() ? $active->sortBy('total_price')->first() : null;
            });
        } catch (\Exception $e) {
            Log::warning('Could not fetch LMP data from ebay_sku_competitors: ' . $e->getMessage());
        }

        // Sku Link LMP — build the linked-SKU groups (shared lmp_sku_links table) so a
        // row's LMP merges competitors across every SKU linked to it (same as Amazon).
        $lmpGroupService = new \App\Services\LmpSkuGroupService();
        try {
            $lmpGroupService->prepareForSkus(
                $productMasters->pluck('sku')->filter()->map(fn($s) => (string) $s)->all()
            );
        } catch (\Throwable $e) {
            Log::warning('LmpSkuGroupService prepare failed (eBay): ' . $e->getMessage());
        }

        // 5. Marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();

        $percentage = MarketplacePercentage::takeHomeDecimal('Ebay');
        $adUpdates  = $marketplaceData ? $marketplaceData->ad_updates : 0; 

        // 6. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData->get($pm->sku);
            $ebayMetric = $ebayMetrics[ShopifySku::normalizeSkuForShopifyLookup($pm->sku)] ?? null;
            $listingStatus = $listingStatusData[strtolower($pm->sku)] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;
            $row['fba'] = $pm->fba;

            // Shopify
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;
            $pmNormInv = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
            $row['inv_yesterday'] = $invYesterdayBySku[$pmNormInv] ?? null;
            $row['l30_yesterday'] = $l30YesterdayBySku[$pmNormInv] ?? null;
            
            // ==== Rating from EbayDataView ====
            $row['rating'] = null;
            if (isset($nrValues[strtoupper(trim((string) $pm->sku))])) {
                $raw = $nrValues[strtoupper(trim((string) $pm->sku))];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true) ?? [];
                }
                if (is_array($raw) && isset($raw['rating'])) {
                    $row['rating'] = floatval($raw['rating']);
                }
            }
            
            // ==== NRL/REQ + Links ====
            // Default values
            $row['nr_req'] = 'REQ';
            $row['B Link'] = '';
            $row['S Link'] = '';

            // 1) Prefer data from EbayDataView (Listing eBay page) via NRL field
            if (isset($nrValues[strtoupper(trim((string) $pm->sku))])) {
                $raw = $nrValues[strtoupper(trim((string) $pm->sku))];

                if (!is_array($raw)) {
                    $raw = json_decode($raw, true) ?? [];
                }

                if (is_array($raw)) {
                    // NRL mapping: 'NRL' => NRL, 'REQ' => REQ
                    $nrlValue = $raw['NRL'] ?? null;
                    if ($nrlValue === 'NRL') {
                        $row['nr_req'] = 'NRL';
                    } elseif ($nrlValue === 'REQ') {
                        $row['nr_req'] = 'REQ';
                    }

                    // Buyer / Seller links from Listing eBay if present
                    if (!empty($raw['buyer_link'])) {
                        $row['B Link'] = $raw['buyer_link'];
                    }
                    if (!empty($raw['seller_link'])) {
                        $row['S Link'] = $raw['seller_link'];
                    }
                }
            }

            // 2) Fallback: Only use EbayListingStatus for buyer/seller links (not for nr_req)
            // nr_req should ONLY come from EbayDataView to match listingEbay page
            if ($listingStatus) {
                $statusValue = is_array($listingStatus->value)
                    ? $listingStatus->value
                    : (json_decode($listingStatus->value, true) ?? []);

                // Only use links from EbayListingStatus if not already set from EbayDataView
                if (empty($row['B Link']) && !empty($statusValue['buyer_link'])) {
                    $row['B Link'] = $statusValue['buyer_link'];
                }
                if (empty($row['S Link']) && !empty($statusValue['seller_link'])) {
                    $row['S Link'] = $statusValue['seller_link'];
                }
            }

            // eBay Metrics. E L30 prefers live ebay_orders (same source as the Qty badge)
            // so CVR vs CPN / Dil vs PRMT Apply can see sales when ebay_metrics.ebay_l30 is 0.
            $pmNorm = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
            $itemId = trim((string) ($ebayMetric?->item_id ?? ''));
            $orderSkuL30 = (float) ($orderL30BySku[$pmNorm] ?? 0);
            $orderItemL30 = ($itemId !== '' && $itemId !== '0')
                ? (float) ($orderL30ByItem[$itemId] ?? 0)
                : 0.0;
            $metricL30 = (float) ($ebayMetric?->ebay_l30 ?? 0);
            $row["eBay L30"] = $orderSkuL30 > 0
                ? $orderSkuL30
                : ($orderItemL30 > 0 ? $orderItemL30 : $metricL30);
            $row["eBay L60"] = $ebayMetric?->ebay_l60 ?? 0;
            $row["eBay L45"] = round((($row["eBay L30"] ?? 0) + ($row["eBay L60"] ?? 0)) / 2, 2);
            $row["eBay L7"] = $ebayMetric?->ebay_l7 ?? 0;
            $row["eBay Price"] = $ebayMetric?->ebay_price ?? 0;
            $row = array_merge($row, EbayListingEnded::fields($ebayMetric));
            $row['price_yesterday'] = $priceYesterdayBySku[$pmNorm] ?? null;
            $row['price_yesterday_date'] = $priceYesterdayDateBySku[$pmNorm] ?? null;
            $row['sprice_yesterday'] = $spriceYesterdayBySku[$pmNorm] ?? null;
            $row['sprice_yesterday_date'] = $spriceYesterdayDateBySku[$pmNorm] ?? null;
            // inv_yesterday / l30_yesterday already set above with INV / L30
            $row['eBay Stock'] = $ebayMetric?->ebay_stock ?? 0;
            $row['price_lmpa'] = $ebayMetric?->price_lmpa ?? null;
            $row['eBay_item_id'] = $ebayMetric?->item_id ?? null;
            $row['views'] = $ebayMetric?->views ?? 0;

            // Get bid percentage from campaign listings
            if ($ebayMetric && isset($campaignListings[$ebayMetric->item_id])) {
                $row['bid_percentage'] = $campaignListings[$ebayMetric->item_id]->bid_percentage ?? null;
                $row['suggested_bid'] = $campaignListings[$ebayMetric->item_id]->suggested_bid ?? null;
            } else {
                $row['bid_percentage'] = null;
                $row['suggested_bid'] = null;
            }

            // C BID / ES BID / PROMOTE — same source as /ebay/campaign-ads page (ebay_campaign_ads table).
            // Matched by listing_id (= ebay_metrics.item_id, i.e. SKU-wise via the metric row).
            // Rows whose SKU has no campaign-ads record stay visible with nulls — formatter renders '—'.
            $caRow = ($ebayMetric && isset($ebayCampaignAdsByListing[$ebayMetric->item_id]))
                ? $ebayCampaignAdsByListing[$ebayMetric->item_id]
                : null;
            $row['ca_bid_percentage'] = $caRow->bid_percentage ?? null;
            $row['ca_suggested_bid']  = $caRow->suggested_bid  ?? null;
            $row['ca_promote_with_ad'] = $caRow->promote_with_ad ?? null;

            // LMP data — merged across the Sku Link LMP group so linked SKUs share LMP.
            // Group members come from lmp_sku_links; competitor rows are matched with the
            // SAME normalization used to group them (uppercase + trim + collapse whitespace),
            // which also fixes SKUs like `WF 8"-890 4PC` that have irregular spacing.
            $linkedGroup = $lmpGroupService->groupContaining((string) $pm->sku);
            if (empty($linkedGroup)) {
                $linkedGroup = [(string) $pm->sku];
            }
            $seenLinked = [];
            $linkedLmpSkus = [];
            foreach ($linkedGroup as $member) {
                $display = trim((string) $member);
                $normMember = strtoupper($display);
                if ($normMember === '' || isset($seenLinked[$normMember])) {
                    continue;
                }
                $seenLinked[$normMember] = true;
                $linkedLmpSkus[] = $display;
            }
            $row['linked_lmp_skus'] = $linkedLmpSkus;

            // Std Prc — shared amazon_data_view.STANDARD_PRICE; inherit from Sku Link LMP siblings
            $stdPrc = $amazonStandardPrices[strtoupper(trim((string) $pm->sku))] ?? null;
            if ($stdPrc === null) {
                foreach ($linkedLmpSkus as $linkedSku) {
                    $linkedKey = strtoupper(trim((string) $linkedSku));
                    if ($linkedKey !== '' && isset($amazonStandardPrices[$linkedKey])) {
                        $stdPrc = $amazonStandardPrices[$linkedKey];
                        break;
                    }
                }
            }
            $row['STANDARD_PRICE'] = $stdPrc;

            // Site-specific promo columns from ebay_data_view (PEF_PRMT_PCT / PEF_CPN_PCT / PUSH_PRC_*)
            $row = app(ChannelPromoPricingService::class)->applyToRow($row, $ebay1PromoMap, (string) $pm->sku);

            $lmpEntries = collect();
            foreach ($linkedLmpSkus as $linkedSku) {
                $key = strtoupper(preg_replace('/\s+/', ' ', trim($linkedSku)));
                $entries = $lmpDetailsLookup->get($key);
                if ($entries instanceof \Illuminate\Support\Collection) {
                    $lmpEntries = $lmpEntries->merge($entries);
                }
            }
            // Dedupe by competitor item_id, then order by total_price ascending.
            $lmpEntries = $lmpEntries
                ->unique(fn($e) => $e->item_id ?? spl_object_id($e))
                ->sortBy('total_price')
                ->values();

            // L1 ignores flagged competitors
            $lowestLmp = $lmpEntries->first(fn ($e) => empty($e->ignored));
            $row['lmp_price'] = ($lowestLmp && isset($lowestLmp->total_price))
                ? (is_numeric($lowestLmp->total_price) ? floatval($lowestLmp->total_price) : null)
                : null;
            $row['lmp_link'] = $lowestLmp->product_link ?? null;
            $row['lmp_item_id'] = $lowestLmp->item_id ?? null;
            $row['lmp_title'] = $lowestLmp->product_title ?? null;
            $row['lmp_entries'] = $lmpEntries
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'item_id' => $entry->item_id,
                        'price' => floatval($entry->price ?? 0),
                        'shipping_cost' => floatval($entry->shipping_cost ?? 0),
                        'total_price' => floatval($entry->total_price ?? 0),
                        'ignored' => (bool) ($entry->ignored ?? false),
                        'link' => $entry->product_link,
                        'title' => $entry->product_title,
                    ];
                })
                ->toArray();
            $row['lmp_entries_total'] = $lmpEntries->count();

            $row["E Dil%"] = ($row["eBay L30"] && $row["INV"] > 0)
                ? round(($row["eBay L30"] / $row["INV"]), 2)
                : 0;

            $matchedCampaignL30 = $ebayCampaignReportsL30[strtoupper(trim((string) $sku))] ?? null;

            $listingKey = ($ebayMetric && ! empty($ebayMetric->item_id))
                ? trim((string) $ebayMetric->item_id)
                : '';
            $matchedGeneralL30 = $listingKey !== '' ? ($ebayGeneralReportsL30ByListing[$listingKey] ?? null) : null;
            $matchedGeneralL7 = $listingKey !== '' ? ($ebayGeneralReportsL7ByListing[$listingKey] ?? null) : null;

            // Keyword campaign
            $kw_spend_l30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $kw_sales_l30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0);
            $kw_sold_l30  = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);

            // General ads (PMT) - use aggregated data matching PMP Ads controller
            $skuUpper = strtoupper($sku);
            $pmtMetrics = $pmtAdMetricsBySku[$skuUpper] ?? [];
            $pmt_spend_l30 = $pmtMetrics['GENERAL_SPENT'] ?? (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
            $pmt_sales_l30 = $pmtMetrics['SALE_AMOUNT'] ?? (float) str_replace('USD ', '', $matchedGeneralL30->sale_amount ?? 0);
            $pmt_sold_l30  = $pmtMetrics['Sls'] ?? (int) ($matchedGeneralL30->sales ?? 0);
            $pmt_impressions_l30 = $pmtMetrics['Imp'] ?? (int) ($matchedGeneralL30->impressions ?? 0);

            // PMT Clicks L30: aggregated SKU clicks + extra clicks from primary listing (matching PMP formula)
            $pmt_clicks_l30 = $pmtMetrics['Clk'] ?? 0;
            if ($ebayMetric && isset($extraClicksData[$ebayMetric->item_id])) {
                $pmt_clicks_l30 += (int) $extraClicksData[$ebayMetric->item_id];
            }

            // PMT L7 data - use aggregated data matching PMP Ads controller
            $pmtMetricsL7 = $pmtAdMetricsBySkuL7[$skuUpper] ?? [];
            $pmt_clicks_l7 = $pmtMetricsL7['Clk'] ?? (int) ($matchedGeneralL7->clicks ?? 0);
            $pmt_spend_l7 = $pmtMetricsL7['GENERAL_SPENT'] ?? (float) str_replace('USD ', '', $matchedGeneralL7->ad_fees ?? 0);

            // Final AD totals
            $AD_Spend_L30 = $kw_spend_l30 + $pmt_spend_l30;
            $AD_Sales_L30 = $kw_sales_l30 + $pmt_sales_l30;
            $AD_Units_L30 = $kw_sold_l30 + $pmt_sold_l30;

            $row["AD_Spend_L30"] = round($AD_Spend_L30, 2);
            $row["kw_spend_L30"] = round($kw_spend_l30, 2);
            $row["pmt_spend_L30"] = round($pmt_spend_l30, 2);
            $row["AD_Sales_L30"] = round($AD_Sales_L30, 2);
            $row["AD_Units_L30"] = $AD_Units_L30;

            // === PMT Ads section data ===
            $row['pmt_clicks_l30'] = $pmt_clicks_l30;
            $row['pmt_clicks_l7'] = $pmt_clicks_l7;
            $row['pmt_impressions_l30'] = $pmt_impressions_l30;
            $row['pmt_sold_l30'] = $pmt_sold_l30;
            $row['pmt_sales_l30'] = round($pmt_sales_l30, 2);
            $row['pmt_spend_l7'] = round($pmt_spend_l7, 2);

            // === KW Ads section data ===
            $row['l7_views'] = $ebayMetric->l7_views ?? 0;
            // L7 % vs previous same period (days 8–14) — snapshot of l7_views from ~7 days ago.
            $skuNormKey = ShopifySku::normalizeSkuForShopifyLookup($pm->sku);
            $l7Cur = (int) ($row['l7_views'] ?? 0);
            $l7Prev = $prevL7ViewsBySku[$skuNormKey] ?? null;
            $row['l7_views_prev'] = $l7Prev; // null = no prior snapshot yet
            $row['l7_views_chg_pct'] = ($l7Prev !== null && (int) $l7Prev > 0)
                ? round((($l7Cur - (int) $l7Prev) / (int) $l7Prev) * 100, 1)
                : null;

            // Match L7 and L1 campaign reports
            $matchedCampaignL7 = $ebayCampaignReportsL7[strtoupper(trim((string) $sku))] ?? null;
            $matchedCampaignL1 = $ebayCampaignReportsL1[strtoupper(trim((string) $sku))] ?? null;

            // KW Campaign budget
            $row['kw_campaignBudgetAmount'] = (float) ($matchedCampaignL30->campaignBudgetAmount ?? 0);
            $row['kw_campaignStatus'] = $matchedCampaignL30->campaignStatus ?? '';
            $row['kw_campaign_id'] = $matchedCampaignL30->campaign_id ?? '';

            // KW L30 clicks and ad_sold
            $kw_clicks_l30 = (int) ($matchedCampaignL30->cpc_clicks ?? 0);
            $row['kw_clicks'] = $kw_clicks_l30;
            $row['kw_ad_sold'] = $kw_sold_l30;

            // KW ACOS
            $row['kw_acos'] = $kw_sales_l30 > 0
                ? round(($kw_spend_l30 / $kw_sales_l30) * 100, 2)
                : ($kw_spend_l30 > 0 ? 100 : 0);

            // KW CVR (ad_sold / clicks * 100)
            $row['kw_cvr'] = $kw_clicks_l30 > 0
                ? round(($kw_sold_l30 / $kw_clicks_l30) * 100, 2)
                : 0;

            // KW L7 spend and CPC
            $kw_l7_spend = $matchedCampaignL7
                ? (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0')
                : 0;
            $kw_l7_cpc = $matchedCampaignL7
                ? (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cost_per_click ?? '0')
                : 0;
            $row['kw_l7_spend'] = round($kw_l7_spend, 2);
            $row['kw_l7_cpc'] = round($kw_l7_cpc, 2);

            // KW L1 spend and CPC
            $kw_l1_spend = $matchedCampaignL1
                ? (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0')
                : 0;
            $kw_l1_cpc = $matchedCampaignL1
                ? (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cost_per_click ?? '0')
                : 0;
            $row['kw_l1_spend'] = round($kw_l1_spend, 2);
            $row['kw_l1_cpc'] = round($kw_l1_cpc, 2);

            // KW last_sbid, sbid_m, apprSbid
            $kwCampaignId = $row['kw_campaign_id'];
            $row['kw_last_sbid'] = isset($lastSbidMap[$kwCampaignId]) ? $lastSbidMap[$kwCampaignId] : '';
            $row['kw_sbid_m'] = isset($sbidMMap[$kwCampaignId]) ? $sbidMMap[$kwCampaignId] : '';
            $row['kw_apprSbid'] = isset($apprSbidMap[$kwCampaignId]) ? $apprSbidMap[$kwCampaignId] : '';

            // AD% Formula = (spend_l30 / (price * ebay_l30)) * 100
            $price = floatval($row["eBay Price"] ?? 0);
            $ebay_l30 = floatval($row["eBay L30"] ?? 0);
            $totalRevenue = $price * $ebay_l30;

            $row["AD%"] = $totalRevenue > 0
                ? round(($AD_Spend_L30 / $totalRevenue) * 100, 4)
                : 0;


            // Initialize ad metrics with zero values since we're using EbayMetric data
            foreach (['L60', 'L30', 'L7'] as $range) {
                foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT', 'PRIORITY_SPENT'] as $suffix) {
                    $key = "Pmt{$suffix}{$range}";
                    $row[$key] = 0;
                }
            }

            // Values: LP & Ship
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === "lp") {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }

            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // Price and units for calculations
            $price = floatval($row["eBay Price"] ?? 0);

            $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);

            // Profit/Sales
            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["Profit"] = $row["Total_pft"]; // Add this for frontend compatibility
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["Sales L30"] = $row["T_Sale_l30"]; // Add this for frontend compatibility
            
            // Tacos Formula: TOTAL AD SPENT / TOTAL SALES
            // Total Sales = eBay L30 * eBay Price
            $totalSales = $row["T_Sale_l30"]; // Already calculated as price * units_ordered_l30
            $row["TacosL30"] = $totalSales > 0 ? round($AD_Spend_L30 / $totalSales, 4) : 0;
            
            // Calculate GPFT%
            $gpft = $price > 0 ? (($price * $percentage - $ship - $lp) / $price) * 100 : 0;
            
            // PFT% = GPFT% - AD%
            $row["PFT %"] = round($gpft - $row["AD%"], 2);
            $totalPercentage = $percentage + $adUpdates; 

            // ROI% = ((price * percentage - lp - ship) / lp) * 100
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );


            $row["GPFT%"] = round(
                $price  > 0 ? (($price * $percentage - $ship - $lp) / $price) * 100 : 0,
                2
            );
            $row["percentage"] = $percentage;
            $row['ad_updates'] = $adUpdates;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;

            // Calculate CVR 30 (SCVR): (eBay L30 / views) * 100; CVR 45, CVR 60 for L45/L60
            $views = floatval($row['views'] ?? 0);
            $ebayL30 = floatval($row["eBay L30"] ?? 0);
            $ebayL45 = floatval($row["eBay L45"] ?? 0);
            $ebayL60 = floatval($row["eBay L60"] ?? 0);
            $row['SCVR'] = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0;
            $row['CVR_45'] = $views > 0 ? round(($ebayL45 / $views) * 100, 2) : 0;
            $row['CVR_60'] = $views > 0 ? round(($ebayL60 / $views) * 100, 2) : 0;
            $cvr = $row['SCVR']; // Use SCVR for SPRICE calculation

            // NR & Hide (load from database, but not SPRICE/SPFT/SROI/SGROI/SGPFT)
            $row['NR'] = "";
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;
            $row['spend_l30'] = null;
            if (isset($nrValues[strtoupper(trim((string) $pm->sku))])) {
                $raw = $nrValues[strtoupper(trim((string) $pm->sku))];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['NRL'] = $raw['NRL'] ?? null;
                    // Don't load SPRICE, SPFT, SROI, SGROI, SGPFT from database - always calculate
                    $row['spend_l30'] = $raw['Spend_L30'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }
            
            // Always calculate SPRICE based on CVR (ignore saved values)
            $calculatedSprice = null;
            if ($price > 0) {
                // Determine multiplier based on CVR
                if ($cvr >= 0 && $cvr <= 1) {
                    // 0-1%: multiply by 0.99
                    $spriceMultiplier = 0.99;
                } elseif ($cvr > 1 && $cvr <= 3) {
                    // 1%-3%: multiply by 0.995
                    $spriceMultiplier = 0.995;
                } else {
                    // >3%: increase by 1% (multiply by 1.01)
                    $spriceMultiplier = 1.01;
                }
                
                $calculatedSprice = round($price * $spriceMultiplier, 2);
                
                // Check if there's a saved SPRICE that differs from calculated
                $savedSprice = null;
                if (isset($nrValues[strtoupper(trim((string) $pm->sku))])) {
                    $raw = $nrValues[strtoupper(trim((string) $pm->sku))];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw) && isset($raw['SPRICE'])) {
                        $savedSprice = floatval($raw['SPRICE']);
                    }
                }
                
                // Check for SPRICE_STATUS in database (pushed/applied/error)
                $savedStatus = null;
                if (isset($nrValues[strtoupper(trim((string) $pm->sku))])) {
                    $raw = $nrValues[strtoupper(trim((string) $pm->sku))];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw) && isset($raw['SPRICE_STATUS'])) {
                        $savedStatus = $raw['SPRICE_STATUS'];
                    }
                }
                
                // Use saved SPRICE only if it exists in DB; otherwise show nothing (no default/calculated value)
                if ($savedSprice !== null && $savedSprice > 0) {
                    $row['SPRICE'] = $savedSprice;
                    $row['has_custom_sprice'] = true;
                    $row['SPRICE_STATUS'] = $savedStatus ?: 'saved';
                    $sprice = $savedSprice;
                } else {
                    // No saved SPRICE (cleared or never set) — do not show default calculated value
                    $row['SPRICE'] = null;
                    $row['has_custom_sprice'] = false;
                    $row['SPRICE_STATUS'] = $savedStatus;
                    $sprice = 0;
                }

                // S PRC = Std × (1 − (PRMT% + CPN%)/100). If both % are 0, S PRC = Std.
                $stdPrcForSprice = (float) ($row['STANDARD_PRICE'] ?? 0);
                if ($stdPrcForSprice > 0) {
                    $prmtPct = is_numeric($row['prmt_pct'] ?? null)
                        ? (float) $row['prmt_pct']
                        : (float) ($row['_prmt_pct_applied'] ?? 0);
                    $cpnPct = is_numeric($row['cpn_pct'] ?? null)
                        ? (float) $row['cpn_pct']
                        : (float) ($row['_cpn_pct_applied'] ?? 0);
                    $tPromo = min(99.99, max(0, $prmtPct + $cpnPct));
                    $formulaSprice = round($tPromo > 0 ? $stdPrcForSprice * (1 - $tPromo / 100) : $stdPrcForSprice, 2);
                    if ($formulaSprice >= 0.01) {
                        $row['SPRICE'] = $formulaSprice;
                        $row['has_custom_sprice'] = true;
                        $sprice = $formulaSprice;
                    }
                }
                
                $sgpft = $sprice > 0 ? round((($sprice * $percentage - $ship - $lp) / $sprice) * 100, 2) : 0;
                $row['SGPFT'] = $sprice > 0 ? $sgpft : null;
                $row['SPFT'] = $sprice > 0 ? $sgpft : null;
                $row['SGROI'] = $sprice > 0 ? round($lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0, 2) : null;
                $row['SROI'] = $sprice > 0 ? round($lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0, 2) : null;
            } else {
                // If price is 0, set all to null/0
                $row['SPRICE'] = null;
                $row['SPFT'] = null;
                $row['SROI'] = null;
                $row['SGROI'] = null;
                $row['SGPFT'] = null;
                $row['has_custom_sprice'] = false;
                $row['SPRICE_STATUS'] = null;
            }

            // Image
            $row["image_path"] = $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null));
            $row['_parent_sort'] = 0; // child row: keep before parent summary row when sorting

            $faRecNrp = $forecastNrpBySku[$normalizeSkuFa($pm->sku)] ?? null;
            $nrpOut = '';
            if ($faRecNrp && $faRecNrp->nr !== null && trim((string) $faRecNrp->nr) !== '') {
                $nrpOut = strtoupper(trim((string) $faRecNrp->nr));
                if (! in_array($nrpOut, ['REQ', 'NR', 'LATER'], true)) {
                    $nrpOut = 'REQ';
                }
            }
            $row['nrp'] = $nrpOut;

            $result[] = (object) $row;
        }

        // AD% = channel-level Ads% (same as /all-marketplace-master), every row identical
        $channelAdsPct = app(ChannelMasterController::class)->getEbayMasterAdsPercent();
        foreach ($result as $r) {
            $r->{'AD%'} = $channelAdsPct;
            $gpft = (float) ($r->{'GPFT%'} ?? 0);
            $r->{'PFT %'} = round($gpft - $channelAdsPct, 2);
            if (isset($r->SGPFT) && $r->SGPFT !== null && $r->SGPFT !== '') {
                $r->SPFT = round((float) $r->SGPFT - $channelAdsPct, 2);
                $sprice = (float) ($r->SPRICE ?? 0);
                $lp = (float) ($r->LP_productmaster ?? 0);
                $ship = (float) ($r->Ship_productmaster ?? 0);
                $pct = (float) ($r->percentage ?? $percentage);
                if ($sprice > 0 && $lp > 0) {
                    $grossPft = ($sprice * $pct) - $ship - $lp;
                    $adSpend = $sprice * ($channelAdsPct / 100);
                    $r->SROI = round((($grossPft - $adSpend) / $lp) * 100, 2);
                }
            }
        }

        // Inject parent summary rows so "View: Parent" filter shows them (one row per unique Parent)
        $byParent = [];
        foreach ($result as $r) {
            $p = isset($r->Parent) ? trim((string) $r->Parent) : '';
            if ($p !== '') {
                if (!isset($byParent[$p])) {
                    $byParent[$p] = [];
                }
                $byParent[$p][] = $r;
            }
        }
        $parentNames = array_keys($byParent);
        sort($parentNames);
        $final = [];
        foreach ($parentNames as $p) {
            $children = $byParent[$p];
            $first = $children[0];
            $parentRow = new \stdClass;
            $parentRow->Parent = 'PARENT ' . $p;
            $parentRow->{'(Child) sku'} = 'PARENT ' . $p;
            $parentRow->is_parent_summary = true;
            $parentRow->fba = $first->fba ?? null;
            $parentRow->INV = array_sum(array_map(function ($c) { return (int) ($c->INV ?? 0); }, $children));
            $parentRow->L30 = array_sum(array_map(function ($c) { return (int) ($c->L30 ?? 0); }, $children));
            $parentRow->rating = null;
            $parentRow->nr_req = 'REQ';
            $parentRow->{'B Link'} = '';
            $parentRow->{'S Link'} = '';
            $parentRow->{'eBay L30'} = array_sum(array_map(function ($c) { return (float) ($c->{'eBay L30'} ?? 0); }, $children));
            $parentRow->{'eBay L45'} = array_sum(array_map(function ($c) { return (float) ($c->{'eBay L45'} ?? 0); }, $children));
            $parentRow->{'eBay L60'} = array_sum(array_map(function ($c) { return (float) ($c->{'eBay L60'} ?? 0); }, $children));
            $parentRow->{'eBay L7'} = array_sum(array_map(function ($c) { return (float) ($c->{'eBay L7'} ?? 0); }, $children));
            $parentRow->{'eBay Price'} = 0;
            $parentRow->{'eBay Stock'} = 0;
            $parentRow->price_lmpa = null;
            $parentRow->eBay_item_id = null;
            $parentRow->views = array_sum(array_map(function ($c) { return (int) ($c->views ?? 0); }, $children));
            $parentRow->bid_percentage = null;
            $parentRow->suggested_bid = null;
            $parentRow->lmp_price = null;
            $parentRow->lmp_link = null;
            $parentRow->lmp_item_id = null;
            $parentRow->lmp_title = null;
            $parentRow->lmp_entries = [];
            $parentRow->lmp_entries_total = 0;
            $parentRow->linked_lmp_skus = [];
            $parentRow->{'E Dil%'} = 0;
            $parentRow->AD_Spend_L30 = array_sum(array_map(function ($c) { return (float) ($c->AD_Spend_L30 ?? 0); }, $children));
            $parentRow->kw_spend_L30 = array_sum(array_map(function ($c) { return (float) ($c->kw_spend_L30 ?? 0); }, $children));
            $parentRow->pmt_spend_L30 = array_sum(array_map(function ($c) { return (float) ($c->pmt_spend_L30 ?? 0); }, $children));
            $parentRow->AD_Sales_L30 = array_sum(array_map(function ($c) { return (float) ($c->AD_Sales_L30 ?? 0); }, $children));
            $parentRow->AD_Units_L30 = array_sum(array_map(function ($c) { return (int) ($c->AD_Units_L30 ?? 0); }, $children));
            $parentRow->pmt_clicks_l30 = 0;
            $parentRow->pmt_clicks_l7 = 0;
            $parentRow->pmt_impressions_l30 = 0;
            $parentRow->pmt_sold_l30 = 0;
            $parentRow->pmt_sales_l30 = 0;
            $parentRow->pmt_spend_l7 = 0;
            $parentRow->l7_views = array_sum(array_map(function ($c) { return (int) ($c->l7_views ?? 0); }, $children));
            $parentPrevVals = array_map(function ($c) {
                return $c->l7_views_prev;
            }, $children);
            $parentHasPrev = false;
            $parentL7Prev = 0;
            foreach ($parentPrevVals as $pv) {
                if ($pv !== null && $pv !== '') {
                    $parentHasPrev = true;
                    $parentL7Prev += (int) $pv;
                }
            }
            $parentRow->l7_views_prev = $parentHasPrev ? $parentL7Prev : null;
            $parentRow->l7_views_chg_pct = ($parentHasPrev && $parentL7Prev > 0)
                ? round((((int) $parentRow->l7_views - $parentL7Prev) / $parentL7Prev) * 100, 1)
                : null;
            $parentRow->kw_campaignBudgetAmount = 0;
            $parentRow->kw_campaignStatus = '';
            $parentRow->kw_campaign_id = '';
            $parentRow->kw_clicks = 0;
            $parentRow->kw_ad_sold = 0;
            $parentRow->kw_acos = 0;
            $parentRow->kw_cvr = 0;
            $parentRow->kw_l7_spend = 0;
            $parentRow->kw_l7_cpc = 0;
            $parentRow->kw_l1_spend = 0;
            $parentRow->kw_l1_cpc = 0;
            $parentRow->kw_last_sbid = '';
            $parentRow->kw_sbid_m = '';
            $parentRow->kw_apprSbid = '';
            $parentRow->{'AD%'} = $channelAdsPct;
            $parentRow->{'Total_pft'} = array_sum(array_map(function ($c) { return (float) ($c->{'Total_pft'} ?? 0); }, $children));
            $parentRow->Profit = $parentRow->{'Total_pft'};
            $parentRow->{'T_Sale_l30'} = array_sum(array_map(function ($c) { return (float) ($c->{'T_Sale_l30'} ?? 0); }, $children));
            $parentRow->{'Sales L30'} = $parentRow->{'T_Sale_l30'};
            $parentRow->TacosL30 = 0;
            $parentRow->{'PFT %'} = 0;
            $parentRow->{'ROI%'} = 0;
            $parentRow->{'GPFT%'} = 0;
            $parentRow->percentage = $percentage;
            $parentRow->ad_updates = $adUpdates;
            $parentRow->LP_productmaster = 0;
            $parentRow->Ship_productmaster = 0;
            $childCount = count($children);
            $parentRow->SCVR = $childCount > 0
                ? round(array_sum(array_map(function ($c) { return (float) ($c->SCVR ?? 0); }, $children)) / $childCount, 2)
                : 0;
            $parentRow->CVR_45 = $childCount > 0
                ? round(array_sum(array_map(function ($c) { return (float) ($c->CVR_45 ?? 0); }, $children)) / $childCount, 2)
                : 0;
            $parentRow->CVR_60 = $childCount > 0
                ? round(array_sum(array_map(function ($c) { return (float) ($c->CVR_60 ?? 0); }, $children)) / $childCount, 2)
                : 0;
            $parentRow->NR = '';
            $parentRow->NRL = 'REQ';
            $parentRow->Listed = null;
            $parentRow->Live = null;
            $parentRow->APlus = null;
            $parentRow->spend_l30 = null;
            $parentRow->SPRICE = null;
            $parentRow->SPFT = null;
            $parentRow->SROI = null;
            $parentRow->SGROI = null;
            $parentRow->SGPFT = null;
            $parentRow->has_custom_sprice = false;
            $parentRow->SPRICE_STATUS = null;
            $parentRow->image_path = $first->image_path ?? null;
            $parentRow->_parent_sort = 1; // parent summary row: always sort after children (bottom of group)
            foreach ($children as $c) {
                $final[] = $c;
            }
            $final[] = $parentRow;
        }
        // Rows with no parent (empty Parent) stay at the end
        foreach ($result as $r) {
            $p = isset($r->Parent) ? trim((string) $r->Parent) : '';
            if ($p === '') {
                $final[] = $r;
            }
        }

        return response()->json([
            "message" => "eBay Data Fetched Successfully",
            "data" => $final,
            "status" => 200,
        ]);
    }


    // Helper function
    private function extractNumber($value)
    {
        if (empty($value)) return 0;
        return (float) preg_replace('/[^\d.]/', '', $value);
    }


    public function updateAllEbaySkus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');

            // Current record fetch
            $marketplace = MarketplacePercentage::where('marketplace', 'Ebay')->first();

            $percent = $marketplace->percentage ?? 0;
            $adUpdates = $marketplace->ad_updates ?? 0;

            // Handle percentage update
            if ($type === 'percentage') {
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return response()->json(['status' => 400, 'message' => 'Invalid percentage value'], 400);
                }
                $percent = $value;
            }

            // Handle ad_updates update
            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json(['status' => 400, 'message' => 'Invalid ad_updates value'], 400);
                }
                $adUpdates = $value;
            }

            // Save both fields
            $marketplace = MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'Ebay'],
                [
                    'percentage' => $percent,
                    'ad_updates' => $adUpdates,
                ]
            );

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type) . ' updated successfully!',
                'data' => [
                    'percentage' => $marketplace->percentage,
                    'ad_updates' => $marketplace->ad_updates
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating Ebay marketplace values',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $skus = $request->input("skus");
        $hideValues = $request->input("hideValues"); // <-- add this
        $sku = $request->input("sku");
        $nr = $request->input("nr");
        $hide = $request->input("hide");

        // Decode hideValues if it's a JSON string
        if (is_string($hideValues)) {
            $hideValues = json_decode($hideValues, true);
        }

        // Bulk update with individual hide values
        if (is_array($skus) && is_array($hideValues)) {
            foreach ($skus as $skuItem) {
                $ebayDataView = EbayDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?:
                        []);
                // Use the value from hideValues for each SKU
                $value["Hide"] = filter_var(
                    $hideValues[$skuItem] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
                $ebayDataView->value = $value;
                $ebayDataView->save();
            }
            return response()->json([
                "success" => true,
                "updated" => count($skus),
            ]);
        }

        // Bulk update if 'skus' is present and 'hide' is a single value (legacy)
        if (is_array($skus) && $hide !== null) {
            foreach ($skus as $skuItem) {
                $ebayDataView = EbayDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?:
                        []);
                $value["Hide"] = filter_var($hide, FILTER_VALIDATE_BOOLEAN);
                $ebayDataView->value = $value;
                $ebayDataView->save();
            }
            return response()->json([
                "success" => true,
                "updated" => count($skus),
            ]);
        }

        // Single update (existing logic)
        if (!$sku || ($nr === null && $hide === null)) {
            return response()->json(
                ["error" => "SKU and at least one of NR or Hide is required."],
                400
            );
        }

        $ebayDataView = EbayDataView::firstOrNew(["sku" => $sku]);
        $value = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?:
                []);

        if ($nr !== null) {
            $value["NR"] = $nr;
        }

        if ($hide !== null) {
            $value["Hide"] = filter_var($hide, FILTER_VALIDATE_BOOLEAN);
        }

        $ebayDataView->value = $value;
        $ebayDataView->save();

        // Create a user-friendly message based on what was updated
        $message = "Data updated successfully";
        if ($nr !== null) {
            $message = $nr === 'NRL' ? "NRL updated" : ($nr === 'REQ' ? "REQ updated" : "NR updated to {$nr}");
        } elseif ($hide !== null) {
            $message = "Hide status updated";
        }

        return response()->json(["success" => true, "data" => $ebayDataView, "message" => $message]);
    }


    public function saveSpriceToDatabase(Request $request)
    {
        Log::info('Saving eBay pricing data', $request->all());
        $sku = strtoupper(trim($request->input('sku') ?? ''));
        $sprice = $request->input('sprice');

        if (!$sku) {
            Log::error('SKU missing', ['sku' => $sku]);
            return response()->json(['error' => 'SKU is required.'], 400);
        }

        $spriceFloat = $sprice !== null && $sprice !== '' ? floatval($sprice) : null;
        $isClear = ($spriceFloat === null || $spriceFloat <= 0);

        if ($isClear) {
            // Clear suggested price: remove SPRICE/SPFT/SROI/SGROI/SGPFT from stored value so refresh shows calculated price
            $ebayDataView = EbayDataView::whereRaw('LOWER(TRIM(sku)) = ?', [strtolower(trim($sku))])->first();
            if (!$ebayDataView) {
                $ebayDataView = new EbayDataView();
                $ebayDataView->sku = $sku;
            }
            $existing = is_array($ebayDataView->value)
                ? $ebayDataView->value
                : (json_decode($ebayDataView->value, true) ?: []);
            $merged = $existing;
            unset($merged['SPRICE'], $merged['SPFT'], $merged['SROI'], $merged['SGROI'], $merged['SGPFT'], $merged['SPRICE_STATUS'], $merged['SPRICE_STATUS_UPDATED_AT']);
            $ebayDataView->value = $merged;
            $ebayDataView->save();
            Log::info('SPRICE cleared for SKU', ['sku' => $sku]);
            return response()->json([
                'message' => 'SPRICE cleared.',
                'spft_percent' => null,
                'sroi_percent' => null,
                'sgroi_percent' => null,
                'sgpft_percent' => null
            ]);
        }

        // Get current marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
        $percentage = MarketplacePercentage::takeHomeDecimal('Ebay');
        Log::info('Using percentage', ['percentage' => $percentage]);

        // Get ProductMaster for lp and ship
        $pm = ProductMaster::whereRaw('UPPER(TRIM(sku)) = ?', [$sku])->first()
            ?? ProductMaster::where('sku', $sku)->first();
        if (!$pm) {
            Log::error('SKU not found in ProductMaster', ['sku' => $sku]);
            return response()->json(['error' => 'SKU not found in ProductMaster.'], 404);
        }

        // Extract lp and ship
        $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
        $lp = 0;
        foreach ($values as $k => $v) {
            if (strtolower($k) === "lp") {
                $lp = floatval($v);
                break;
            }
        }
        if ($lp === 0 && isset($pm->lp)) {
            $lp = floatval($pm->lp);
        }

        $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
        Log::info('LP and Ship', ['lp' => $lp, 'ship' => $ship]);

        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * $percentage - $ship - $lp) / $spriceFloat) * 100, 2) : 0;

        // Channel Ads% (TACOS) — same source as /ebay-tabulator-view Ads badge /
        // /all-marketplace-master eBay Ads% (not per-SKU ACOS).
        $adPercent = (float) app(ChannelMasterController::class)->getEbayMasterAdsPercent();

        $spft = round($sgpft - $adPercent, 2);
        $sgroi = round($lp > 0 ? (($spriceFloat * $percentage - $lp - $ship) / $lp) * 100 : 0, 2);
        $adDecimal = $adPercent / 100;
        $sroi = round(
            $lp > 0 ? ((($spriceFloat * $percentage - $ship - $lp) - ($spriceFloat * $adDecimal)) / $lp) * 100 : 0,
            2
        );
        Log::info('Calculated values', ['sprice' => $spriceFloat, 'sgpft' => $sgpft, 'sgroi' => $sgroi, 'ad_percent' => $adPercent, 'spft' => $spft, 'sroi' => $sroi]);

        // Lock + merge so concurrent Dil/CPN promo saves cannot wipe PEF_* / other keys.
        $this->syncEbay1DailySprice($sku, $spriceFloat);

        $saved = DB::transaction(function () use ($sku, $spriceFloat, $spft, $sroi, $sgroi, $sgpft) {
            $ebayDataView = EbayDataView::whereRaw('LOWER(TRIM(sku)) = ?', [strtolower(trim($sku))])
                ->lockForUpdate()
                ->first();
            if (! $ebayDataView) {
                $ebayDataView = new EbayDataView();
                $ebayDataView->sku = $sku;
            }

            $existing = is_array($ebayDataView->value)
                ? $ebayDataView->value
                : (json_decode($ebayDataView->value, true) ?: []);

            $merged = array_merge($existing, [
                'SPRICE' => $spriceFloat,
                'SPFT' => $spft,
                'SROI' => $sroi,
                'SGROI' => $sgroi,
                'SGPFT' => $sgpft,
            ]);

            $ebayDataView->value = $merged;
            $ebayDataView->save();

            return $merged;
        });
        Log::info('Data saved successfully', ['sku' => $sku]);

        $skipPush = $request->boolean('skip_push');
        $push = [
            'success' => false,
            'status' => $skipPush ? 'skipped' : 'error',
            'message' => $skipPush ? 'Push skipped' : '',
            'ebay_price' => null,
        ];
        if (! $skipPush) {
            $push = $this->pushEbay1PriceAndPullLive($sku, $spriceFloat);
            $this->saveSpriceStatus($sku, $push['success'] ? 'pushed' : ($push['status'] ?: 'error'));
        } else {
            $this->saveSpriceStatus($sku, 'saved');
        }

        return response()->json([
            'message' => 'Data saved successfully.',
            'spft_percent' => $spft,
            'sroi_percent' => $sroi,
            'sgroi_percent' => $sgroi,
            'sgpft_percent' => $sgpft,
            'price_push_success' => (bool) ($push['success'] ?? false),
            'price_push_status' => $push['status'] ?? null,
            'price_push_message' => $push['message'] ?? '',
            'ebay_price' => $push['ebay_price'] ?? null,
            'SPRICE_STATUS' => $push['success']
                ? 'pushed'
                : ($skipPush ? 'saved' : ($push['status'] ?: 'error')),
        ]);
    }

    /**
     * Clear SPRICE for selected SKUs (batch, same pattern as Amazon amazon-clear-sprice).
     */
    public function clearEbaySprice(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (is_object($updates)) {
                $updates = (array) $updates;
            }
            if (! is_array($updates)) {
                return response()->json(['error' => 'Invalid updates format'], 400);
            }

            if (empty($updates)) {
                return response()->json(['error' => 'No SKUs provided'], 400);
            }

            $clearedCount = 0;

            foreach ($updates as $update) {
                $update = (array) $update;
                $sku = trim($update['sku'] ?? '');
                if (empty($sku)) {
                    continue;
                }

                $ebayDataView = EbayDataView::whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($sku)])->first();
                if (!$ebayDataView) {
                    continue;
                }

                $existing = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value ?? '{}', true) ?? []);

                unset(
                    $existing['SPRICE'],
                    $existing['SPFT'],
                    $existing['SROI'],
                    $existing['SGROI'],
                    $existing['SGPFT'],
                    $existing['SPRICE_STATUS'],
                    $existing['SPRICE_STATUS_UPDATED_AT']
                );
                $ebayDataView->value = $existing;
                $ebayDataView->save();
                $clearedCount++;
            }

            Log::info('eBay SPRICE cleared', ['count' => $clearedCount]);

            return response()->json([
                'success' => true,
                'message' => "SPRICE cleared for {$clearedCount} SKU(s)",
                'cleared_count' => $clearedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error clearing eBay SPRICE', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to clear SPRICE: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateListedLive(Request $request)
    {
        // Handle NRL updates
        if ($request->has('nr_req')) {
            Log::info('NRL Update Request', $request->all());
            
            $request->validate([
                'sku'    => 'required|string',
                'nr_req' => 'required|in:REQ,NR,LATER',
            ]);

            // Update EbayListingStatus for NRL
            $listingStatus = EbayListingStatus::firstOrCreate(
                ['sku' => $request->sku],
                ['value' => []]
            );

            $currentValue = is_array($listingStatus->value)
                ? $listingStatus->value
                : (json_decode($listingStatus->value, true) ?? []);

            $currentValue['nr_req'] = $request->nr_req;
            $listingStatus->value = $currentValue;
            $listingStatus->save();

            Log::info('NRL Update Success', ['sku' => $request->sku, 'nr_req' => $request->nr_req]);
            return response()->json(['success' => true]);
        }

        // Original validation for Listed/Live
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = EbayDataView::firstOrCreate(
            ['sku' => $request->sku],
            ['value' => []]
        );

        // Decode current value (ensure it's an array)
        $currentValue = is_array($product->value)
            ? $product->value
            : (json_decode($product->value, true) ?? []);

        // Store as actual boolean
        $currentValue[$request->field] = filter_var($request->value, FILTER_VALIDATE_BOOLEAN);

        // Save back to DB
        $product->value = $currentValue;
        $product->save();

        return response()->json(['success' => true]);
    }

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'eBay')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function importEbayAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Clean headers
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            unset($rows[0]);

            $allSkus = [];
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $allSkus[] = $row[0];
                }
            }

            $existingSkus = ProductMaster::whereIn('sku', $allSkus)
                ->pluck('sku')
                ->toArray();

            $existingSkus = array_flip($existingSkus);

            $importCount = 0;
            foreach ($rows as $index => $row) {
                if (empty($row[0])) { // Check if SKU is empty
                    continue;
                }

                // Ensure row has same number of elements as headers
                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = array_combine($headers, $rowData);

                if (!isset($data['sku']) || empty($data['sku'])) {
                    continue;
                }

                // Only import SKUs that exist in product_masters (in-memory check)
                if (!isset($existingSkus[$data['sku']])) {
                    continue;
                }

                // Prepare values array
                $values = [];

                // Handle boolean fields
                if (isset($data['listed'])) {
                    $values['Listed'] = filter_var($data['listed'], FILTER_VALIDATE_BOOLEAN);
                }

                if (isset($data['live'])) {
                    $values['Live'] = filter_var($data['live'], FILTER_VALIDATE_BOOLEAN);
                }

                // Update or create record
                EbayDataView::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['value' => $values]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportEbayAnalytics()
    {
        try {
            $ebayData = EbayDataView::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('eBay Analytics');

            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('eBay Analytics System')
                ->setTitle('eBay Analytics Export')
                ->setSubject('eBay Listing Data')
                ->setDescription('Export of eBay listing status data');

            // Header Row with styling
            $headers = ['SKU', 'Listed', 'Live'];
            $sheet->fromArray($headers, NULL, 'A1');

            // Style header row
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ];
            $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

            // Data Rows
            $rowIndex = 2;
            foreach ($ebayData as $data) {
                $values = is_array($data->value)
                    ? $data->value
                    : (json_decode($data->value, true) ?? []);

                // Convert boolean values to proper Excel format
                $listed = isset($values['Listed']) ? ($values['Listed'] ? 'TRUE' : 'FALSE') : 'FALSE';
                $live = isset($values['Live']) ? ($values['Live'] ? 'TRUE' : 'FALSE') : 'FALSE';

                $sheet->setCellValue('A' . $rowIndex, $data->sku);
                $sheet->setCellValue('B' . $rowIndex, $listed);
                $sheet->setCellValue('C' . $rowIndex, $live);

                // Apply data row styling
                $dataStyle = [
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ]
                ];
                $sheet->getStyle('A' . $rowIndex . ':C' . $rowIndex)->applyFromArray($dataStyle);

                // Alternate row colors
                if ($rowIndex % 2 == 0) {
                    $sheet->getStyle('A' . $rowIndex . ':C' . $rowIndex)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('F8F9FA'));
                }

                $rowIndex++;
            }

            // Set column widths and formatting
            $sheet->getColumnDimension('A')->setWidth(25);
            $sheet->getColumnDimension('B')->setWidth(12);
            $sheet->getColumnDimension('C')->setWidth(12);

            // Auto-filter for headers
            $sheet->setAutoFilter('A1:C' . ($rowIndex - 1));

            // Freeze header row
            $sheet->freezePane('A2');

            // Generate filename with timestamp
            $fileName = 'Ebay_Analytics_Export_' . date('Y-m-d_H-i-s') . '.xlsx';

            // Clear any output buffer
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Set proper headers for Excel download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            
            // Clean up memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
            exit;
            
        } catch (\Exception $e) {
            Log::error('Error exporting eBay analytics: ' . $e->getMessage());
            return back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }

    public function downloadSample()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Sample Data');

            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('eBay Analytics System')
                ->setTitle('eBay Analytics Sample')
                ->setSubject('Sample Import Format')
                ->setDescription('Sample file showing correct format for eBay analytics import');

            // Header Row
            $headers = ['SKU', 'Listed', 'Live'];
            $sheet->fromArray($headers, NULL, 'A1');

            // Style header row
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ];
            $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

            // Sample Data with proper cell setting
            $sampleData = [
                ['SKU001', 'TRUE', 'FALSE'],
                ['SKU002', 'FALSE', 'TRUE'],
                ['SKU003', 'TRUE', 'TRUE'],
                ['SKU004', 'FALSE', 'FALSE'],
                ['SKU005', 'TRUE', 'TRUE'],
            ];

            $rowIndex = 2;
            foreach ($sampleData as $row) {
                $sheet->setCellValue('A' . $rowIndex, $row[0]);
                $sheet->setCellValue('B' . $rowIndex, $row[1]);
                $sheet->setCellValue('C' . $rowIndex, $row[2]);

                // Apply styling to data rows
                $dataStyle = [
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ]
                ];
                $sheet->getStyle('A' . $rowIndex . ':C' . $rowIndex)->applyFromArray($dataStyle);

                $rowIndex++;
            }

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(25);
            $sheet->getColumnDimension('B')->setWidth(12);
            $sheet->getColumnDimension('C')->setWidth(12);

            // Auto-filter for headers
            $sheet->setAutoFilter('A1:C' . ($rowIndex - 1));

            // Freeze header row
            $sheet->freezePane('A2');

            // Add instructions in a comment
            $sheet->getComment('A1')->getText()->createTextRun('Instructions: Use TRUE/FALSE for Listed and Live columns. SKU must match existing products.');

            // Clear any output buffer
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Output Download
            $fileName = 'Ebay_Analytics_Sample_' . date('Y-m-d') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            
            // Clean up memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
            exit;
            
        } catch (\Exception $e) {
            Log::error('Error downloading sample file: ' . $e->getMessage());
            return back()->with('error', 'Failed to download sample: ' . $e->getMessage());
        }
    }

    public function getEbayColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setEbayColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay_tabulator_column_visibility_{$userId}";
        
        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    public function exportEbayPricingData(Request $request)
    {
        try {
            $response = $this->getViewEbayData($request);
            $data = json_decode($response->getContent(), true);
            $ebayData = $data['data'] ?? [];

            // Get selected columns from request
            $selectedColumns = [];
            if ($request->has('columns')) {
                $columnsJson = $request->input('columns');
                $selectedColumns = json_decode($columnsJson, true) ?: [];
            }

            // Column mapping: field => [header_name, data_extractor]
            $columnMap = [
                'Parent' => ['Parent', function($item) { return $item['Parent'] ?? ''; }],
                '(Child) sku' => ['SKU', function($item) { return $item['(Child) sku'] ?? ''; }],
                'INV' => ['INV', function($item) { return $item['INV'] ?? 0; }],
                'L30' => ['L30', function($item) { return $item['L30'] ?? 0; }],
                'E Dil%' => ['Dil%', function($item) { 
                    return ($item['INV'] > 0) ? round(($item['L30'] / $item['INV']) * 100, 2) : 0; 
                }],
                'eBay L30' => ['eBay L30', function($item) { return $item['eBay L30'] ?? 0; }],
                'eBay L60' => ['eBay L60', function($item) { return $item['eBay L60'] ?? 0; }],
                'eBay Price' => ['eBay Price', function($item) { return number_format($item['eBay Price'] ?? 0, 2); }],
                'lmp_price' => ['LMP', function($item) { return $item['lmp_price'] ? number_format($item['lmp_price'], 2) : ''; }],
                'AD_Spend_L30' => ['AD Spend L30', function($item) { return number_format($item['AD_Spend_L30'] ?? 0, 2); }],
                'AD_Sales_L30' => ['AD Sales L30', function($item) { return number_format($item['AD_Sales_L30'] ?? 0, 2); }],
                'AD_Units_L30' => ['AD Units L30', function($item) { return $item['AD_Units_L30'] ?? 0; }],
                'AD%' => ['AD%', function($item) { return number_format(($item['AD%'] ?? 0) * 100, 2); }],
                'TacosL30' => ['TACOS L30', function($item) { return number_format(($item['TacosL30'] ?? 0) * 100, 2); }],
                'T_Sale_l30' => ['Total Sales L30', function($item) { return number_format($item['T_Sale_l30'] ?? 0, 2); }],
                'Total_pft' => ['Total Profit', function($item) { return number_format($item['Total_pft'] ?? 0, 2); }],
                'PFT %' => ['PFT %', function($item) { return number_format($item['PFT %'] ?? 0, 0); }],
                'ROI%' => ['ROI%', function($item) { return number_format($item['ROI%'] ?? 0, 0); }],
                'GPFT%' => ['GPFT%', function($item) { return number_format($item['GPFT%'] ?? 0, 0); }],
                'views' => ['Views', function($item) { return $item['views'] ?? 0; }],
                'nr_req' => ['NR/REQ', function($item) { return $item['nr_req'] ?? ''; }],
                'SPRICE' => ['SPRICE', function($item) { return $item['SPRICE'] ? number_format($item['SPRICE'], 2) : ''; }],
                'SPFT' => ['SPFT', function($item) { return $item['SPFT'] ? number_format($item['SPFT'], 0) : ''; }],
                'SROI' => ['SROI', function($item) { return $item['SROI'] ? number_format($item['SROI'], 0) : ''; }],
                'SGROI' => ['SGROI', function($item) { return $item['SGROI'] ? number_format($item['SGROI'], 0) : ''; }],
                'SGPFT' => ['SGPFT', function($item) { return $item['SGPFT'] ? number_format($item['SGPFT'], 0) : ''; }],
                'SCVR' => ['SCVR', function($item) { return number_format($item['SCVR'] ?? 0, 1); }],
                'kw_spend_L30' => ['KW Spend L30', function($item) { return number_format($item['kw_spend_L30'] ?? 0, 2); }],
                'pmt_spend_L30' => ['PMT Spend L30', function($item) { return number_format($item['pmt_spend_L30'] ?? 0, 2); }],
            ];

            // If no columns selected, export all
            if (empty($selectedColumns)) {
                $selectedColumns = array_keys($columnMap);
            }

            // Filter column map to only selected columns
            $selectedColumnMap = array_intersect_key($columnMap, array_flip($selectedColumns));

            // Set headers for CSV download
            $fileName = 'eBay_Pricing_Data_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');

            // Open output stream
            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header Row (only selected columns)
            $headers = array_column($selectedColumnMap, 0);
            fputcsv($output, $headers);

            // Data Rows
            foreach ($ebayData as $item) {
                $item = (array) $item;
                $row = [];
                
                foreach ($selectedColumnMap as $extractor) {
                    $row[] = $extractor[1]($item);
                }
                
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } catch (\Exception $e) {
            Log::error('Error exporting eBay pricing data: ' . $e->getMessage());
            return back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }

    /**
     * Daily snapshot series for eBay 1 summary badges (amazon_channel_summary_data.channel = ebay).
     * avg_l30_view = round(total_views / 30) — average daily L30 views.
     */
    public function getEbayBadgeChartData(Request $request)
    {
        try {
            $metric = $request->input('metric', 'avg_l30_view');
            $days = intval($request->input('days', 30));

            $allowedMetrics = [
                'zero_sold_count',
                'sold_count',
                'total_sales_amt',
                'total_ebay_l30', // Qty
                'gpft_percent',
                'groi_percent',
                'tcos_percent', // Ads%
                'npft_percent',
                'nroi_percent',
                'cvr_percent',
                'total_views',
                'avg_l30_view',
                'avg_l7_views',
                'missing_count', // M L
                'dil_ov_percent',
                'dil_eb1_percent',
            ];

            if (! in_array($metric, $allowedMetrics, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
            }

            // CVR / Views badges use E Stock > 0 listing views (collapse-guarded).
            // amazon_channel_summary_data still stores the older INV>0+REQ sum (~2×).
            if ($metric === 'cvr_percent') {
                return response()->json([
                    'success' => true,
                    'data' => $this->ebay1CvrChartSeries($days),
                    'metric' => $metric,
                ]);
            }
            if ($metric === 'total_views' || $metric === 'avg_l30_view') {
                return response()->json([
                    'success' => true,
                    'data' => $this->ebay1ViewsChartSeries($days, $metric === 'avg_l30_view'),
                    'metric' => $metric,
                ]);
            }

            $query = AmazonChannelSummary::where('channel', 'ebay')
                ->orderBy('snapshot_date', 'asc');

            $dilMetrics = ['dil_ov_percent', 'dil_eb1_percent'];
            // Dil Ov / Dil EB1: always load from the first saved day (no empty-day padding).
            if ($days > 0 && ! in_array($metric, $dilMetrics, true)) {
                // Inclusive window: 30 days = today + 29 prior days (not 31).
                $query->where('snapshot_date', '>=', now()->subDays(max(0, $days - 1))->toDateString());
            }

            $rows = $query->get();

            // Sales / Qty / profit % switched to real-orders L30 on 2026-07-30.
            // Older amazon_channel_summary_data rows still store Σ T_Sale_l30 (~2×).
            $ordersSwitchDate = '2026-07-30';
            $ordersBackedMetrics = [
                'total_sales_amt', 'total_ebay_l30', 'gpft_percent', 'groi_percent',
                'nroi_percent', 'npft_percent', 'tcos_percent',
            ];
            $mdmByDate = [];
            if (in_array($metric, $ordersBackedMetrics, true) && Schema::hasTable('marketplace_daily_metrics')) {
                $from = $days > 0
                    ? now()->subDays(max(0, $days - 1))->toDateString()
                    : null;
                $mdmQ = DB::table('marketplace_daily_metrics')->where('channel', 'eBay');
                if ($from) {
                    $mdmQ->where('date', '>=', $from);
                }
                foreach ($mdmQ->get() as $m) {
                    $mdmByDate[Carbon::parse($m->date)->toDateString()] = $m;
                }
            }

            $postSwitchVals = [];
            foreach ($rows as $row) {
                $sd = $row->snapshot_date;
                $d = $sd instanceof \DateTimeInterface
                    ? $sd->format('Y-m-d')
                    : date('Y-m-d', strtotime((string) $sd));
                if ($d < $ordersSwitchDate) {
                    continue;
                }
                $s = is_array($row->summary_data)
                    ? $row->summary_data
                    : (json_decode($row->summary_data ?? '{}', true) ?: []);
                $postSwitchVals[] = floatval($s[$metric] ?? 0);
            }
            $trusted = array_values(array_filter($postSwitchVals, fn ($v) => $v > 0));
            sort($trusted);
            $baseline = $trusted !== []
                ? $trusted[(int) floor((count($trusted) - 1) / 2)]
                : 0.0;

            $chartData = $rows->map(function ($row) use ($metric, $ordersSwitchDate, $ordersBackedMetrics, $mdmByDate, $baseline) {
                $summary = is_array($row->summary_data)
                    ? $row->summary_data
                    : (json_decode($row->summary_data ?? '{}', true) ?: []);
                $totalViews = floatval($summary['total_views'] ?? 0);

                if ($metric === 'avg_l30_view') {
                    $raw = array_key_exists('avg_l30_view', $summary)
                        ? $summary['avg_l30_view']
                        : round($totalViews / 30);
                } elseif ($metric === 'npft_percent') {
                    // Derive when older snapshots lack npft_percent
                    $raw = array_key_exists('npft_percent', $summary)
                        ? $summary['npft_percent']
                        : (floatval($summary['gpft_percent'] ?? 0) - floatval($summary['tcos_percent'] ?? 0));
                } else {
                    $raw = $summary[$metric] ?? 0;
                }

                if (in_array($metric, ['dil_ov_percent', 'dil_eb1_percent'], true)) {
                    if (! array_key_exists($metric, $summary) || floatval($raw) <= 0) {
                        return null;
                    }
                }

                $sd = $row->snapshot_date;
                $captureYmd = '';
                if ($sd) {
                    $captureYmd = $sd instanceof \DateTimeInterface
                        ? $sd->format('Y-m-d')
                        : date('Y-m-d', strtotime((string) $sd));
                }
                $asOf = $captureYmd !== ''
                    ? $this->ebay1ChartAsOfLabel($captureYmd)
                    : ['date' => '', 'full_date' => ''];
                $dateStr = $asOf['date'];
                $fullDate = $asOf['full_date'];
                $snapshotYmd = $captureYmd;

                $usedOrders = (($summary['sales_source'] ?? '') === 'orders_l30');
                $preSwitch = $snapshotYmd !== '' && $snapshotYmd < $ordersSwitchDate;
                if (in_array($metric, $ordersBackedMetrics, true)) {
                    $mdmVal = $this->ebay1MetricFromMarketplaceDaily($mdmByDate[$snapshotYmd] ?? $mdmByDate[$fullDate] ?? null, $metric);
                    if ($mdmVal !== null && $mdmVal > 0) {
                        $raw = $mdmVal;
                    }
                    // Drop pre-switch T_Sale_l30 / ebay_metrics figures (~2× real orders).
                    if ($preSwitch && ! $usedOrders && $baseline > 0 && (float) $raw > ($baseline * 1.35)) {
                        return null;
                    }
                    if ($preSwitch && ! $usedOrders && ($mdmVal === null || $mdmVal <= 0) && $baseline > 0) {
                        return null;
                    }
                }

                return [
                    'date' => $dateStr,
                    'full_date' => $fullDate,
                    'value' => floatval($raw ?? 0),
                ];
            })->filter()->values()->toArray();

            if ($days > 0 && count($chartData) > $days) {
                $chartData = array_slice($chartData, -$days);
            }

            return response()->json(['success' => true, 'data' => $chartData, 'metric' => $metric]);
        } catch (\Exception $e) {
            Log::error('getEbayBadgeChartData error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error fetching chart data'], 500);
        }
    }

    /**
     * Daily item qty from real eBay orders (L30 + L60), same exclusions as /ebay/daily-sales:
     * skip CANCELED and FULLY_REFUNDED. Keyed by Pacific calendar date.
     */
    private function ebay1DailyOrderQtyByDate(): array
    {
        $tz = 'America/Los_Angeles';
        $daily = [];
        if (! Schema::hasTable('ebay_orders') || ! Schema::hasTable('ebay_order_items')) {
            return $daily;
        }

        $orders = \App\Models\EbayOrder::with('items')
            ->whereIn('period', ['l30', 'l60'])
            ->get();

        foreach ($orders as $order) {
            $raw = is_array($order->raw_data)
                ? $order->raw_data
                : json_decode((string) $order->raw_data, true);
            if (is_array($raw)) {
                $cancelState = $raw['cancelStatus']['cancelState'] ?? '';
                $paymentStatus = $raw['orderPaymentStatus'] ?? '';
                if ($cancelState === 'CANCELED' || $paymentStatus === 'FULLY_REFUNDED') {
                    continue;
                }
            }
            $created = (is_array($raw) ? ($raw['creationDate'] ?? null) : null) ?: $order->order_date;
            if (! $created) {
                continue;
            }
            $day = Carbon::parse($created)->setTimezone($tz)->toDateString();
            $qty = 0;
            foreach ($order->items as $item) {
                $qty += (int) ($item->quantity ?? 0);
            }
            if ($qty > 0) {
                $daily[$day] = ($daily[$day] ?? 0) + $qty;
            }
        }

        return $daily;
    }

    /**
     * Daily sold qty for one SKU (Pacific day), same cancel/refund exclusions as channel totals.
     *
     * @return array<string, int> keyed by Y-m-d
     */
    private function ebay1SkuDailyOrderQty(string $skuNorm): array
    {
        $tz = 'America/Los_Angeles';
        $daily = [];
        if ($skuNorm === '' || ! Schema::hasTable('ebay_orders') || ! Schema::hasTable('ebay_order_items')) {
            return $daily;
        }

        $rows = DB::table('ebay_order_items as i')
            ->join('ebay_orders as o', 'o.id', '=', 'i.ebay_order_id')
            ->whereRaw('UPPER(TRIM(i.sku)) = ?', [$skuNorm])
            ->whereIn('o.period', ['l30', 'l60'])
            ->get(['i.quantity', 'o.order_date', 'o.raw_data']);

        foreach ($rows as $row) {
            $raw = is_array($row->raw_data)
                ? $row->raw_data
                : json_decode((string) $row->raw_data, true);
            if (is_array($raw)) {
                $cancelState = $raw['cancelStatus']['cancelState'] ?? '';
                $paymentStatus = $raw['orderPaymentStatus'] ?? '';
                if ($cancelState === 'CANCELED' || $paymentStatus === 'FULLY_REFUNDED') {
                    continue;
                }
            }
            $created = (is_array($raw) ? ($raw['creationDate'] ?? null) : null) ?: $row->order_date;
            if (! $created) {
                continue;
            }
            $day = Carbon::parse($created)->setTimezone($tz)->toDateString();
            $qty = (int) ($row->quantity ?? 0);
            if ($qty > 0) {
                $daily[$day] = ($daily[$day] ?? 0) + $qty;
            }
        }

        return $daily;
    }

    /**
     * Rewrite SKU chart CVR with the same formula as the CVR 30 column:
     * rolling L30 order qty ÷ listing views. Live ebay_metrics views are the
     * trusted denominator (what the table uses). Snapshot views are kept only
     * when they stay in band vs live — a lone collapsed snapshot (e.g. 18 vs
     * live 106) must not produce a 27% chart against a 4.7% table.
     *
     * @param  array<string, array<string, mixed>>  $dataByDate
     */
    private function ebay1ApplySkuOrderCvr(array &$dataByDate, string $skuNorm, string $asOfEnd): void
    {
        $dailyQty = $this->ebay1SkuDailyOrderQty($skuNorm);
        $live = EbayMetric::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
            ->first();
        $liveViews = $live ? (int) ($live->views ?? 0) : 0;
        $liveL30 = $live ? (int) ($live->ebay_l30 ?? 0) : 0;

        $trustedViews = $liveViews;
        if ($trustedViews <= 0) {
            foreach ($dataByDate as $row) {
                $v = (int) ($row['views'] ?? 0);
                if ($v > 0) {
                    $trustedViews = $v;
                    break;
                }
            }
        }

        foreach ($dataByDate as $dateKey => &$row) {
            $qty = $this->ebay1RollingL30OrderQty($dailyQty, $dateKey);
            $snapViews = (int) ($row['views'] ?? 0);
            $views = $trustedViews;
            if ($snapViews > 0 && $trustedViews > 0
                && ! ChannelMasterViewsGuard::isUnstable((float) $snapViews, (float) $trustedViews)) {
                $views = $snapViews;
            } elseif ($snapViews > 0 && $trustedViews <= 0) {
                $views = $snapViews;
            }
            if ($dateKey === $asOfEnd && $liveViews > 0) {
                $views = $liveViews;
                $qty = $liveL30;
            }
            if ($views > 0) {
                $row['cvr_percent'] = round(($qty / $views) * 100, 2);
                $row['ebay_l30'] = $qty;
                $row['views'] = $views;
            }
        }
        unset($row);
    }

    /**
     * After the last collected price snapshot, use the live table price
     * (same as the Price column). Avoids a 3-week stale plateau then a
     * last-day cliff when ebay:collect-metrics has not run.
     *
     * @param  array<string, array<string, mixed>>  $dataByDate
     */
    private function ebay1ApplySkuLivePrice(array &$dataByDate, string $skuNorm, string $asOfEnd, ?string $lastSnapAsOf): void
    {
        $live = EbayMetric::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
            ->first();
        $livePrice = $live ? round((float) ($live->ebay_price ?? 0), 2) : 0.0;
        if ($livePrice <= 0) {
            return;
        }

        foreach ($dataByDate as $dateKey => &$row) {
            if ($dateKey === $asOfEnd || ($lastSnapAsOf !== null && $dateKey > $lastSnapAsOf)) {
                $row['price'] = $livePrice;
            } elseif ($lastSnapAsOf === null) {
                $row['price'] = $livePrice;
            }
        }
        unset($row);
    }

    /** Rolling L30 qty as of $asOfDate — same window as app:fetch-ebay-orders (asOf − 30 days through asOf). */
    private function ebay1RollingL30OrderQty(array $dailyQty, string $asOfDate): int
    {
        $end = Carbon::parse($asOfDate, 'America/Los_Angeles')->startOfDay();
        $cursor = $end->copy()->subDays(30);
        $sum = 0;
        while ($cursor->lte($end)) {
            $sum += (int) ($dailyQty[$cursor->toDateString()] ?? 0);
            $cursor->addDay();
        }

        return $sum;
    }

    /**
     * Active Channel chart convention: snapshot/capture day D is labeled as
     * the last completed Pacific day (D − 1). Marketplace APIs only close
     * through yesterday PT.
     *
     * @return array{date: string, full_date: string}
     */
    private function ebay1ChartAsOfLabel(string $captureYmd): array
    {
        $asOf = Carbon::parse($captureYmd, 'America/Los_Angeles')->startOfDay()->subDay();

        return [
            'date' => $asOf->format('M d'),
            'full_date' => $asOf->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{prmt_pct: ?float, cpn_pct: ?float, push_prc: ?float, sprice: ?float}
     */
    private function ebay1PromoFromDaily(array $data): array
    {
        $push = null;
        if (isset($data['push_prc']) && is_numeric($data['push_prc'])) {
            $push = round((float) $data['push_prc'], 2);
        } elseif (isset($data['PUSH_PRC_VALUE']) && is_numeric($data['PUSH_PRC_VALUE'])) {
            $push = round((float) $data['PUSH_PRC_VALUE'], 2);
        }
        $sprice = null;
        if (isset($data['sprice']) && is_numeric($data['sprice']) && (float) $data['sprice'] > 0) {
            $sprice = round((float) $data['sprice'], 2);
        } elseif (isset($data['SPRICE']) && is_numeric($data['SPRICE']) && (float) $data['SPRICE'] > 0) {
            $sprice = round((float) $data['SPRICE'], 2);
        }

        return [
            'prmt_pct' => isset($data['prmt_pct']) && is_numeric($data['prmt_pct'])
                ? round((float) $data['prmt_pct'], 2)
                : null,
            'cpn_pct' => isset($data['cpn_pct']) && is_numeric($data['cpn_pct'])
                ? round((float) $data['cpn_pct'], 2)
                : null,
            'push_prc' => $push,
            'sprice' => $sprice,
        ];
    }

    /**
     * Current PEF promo % / S PRC from ebay_data_view (live overlay for the history graph).
     *
     * @return array{prmt_pct: ?float, cpn_pct: ?float, push_prc: ?float, sprice: ?float}
     */
    private function ebay1LivePromoPercents(string $skuNorm): array
    {
        $empty = ['prmt_pct' => null, 'cpn_pct' => null, 'push_prc' => null, 'sprice' => null];
        try {
            $row = EbayDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])->first();
            if (! $row) {
                return $empty;
            }
            $val = is_array($row->value)
                ? $row->value
                : (json_decode($row->value ?? '{}', true) ?: []);
            $sprice = isset($val['SPRICE']) && is_numeric($val['SPRICE']) && (float) $val['SPRICE'] > 0
                ? round((float) $val['SPRICE'], 2)
                : null;

            return [
                'prmt_pct' => isset($val['PEF_PRMT_PCT']) && is_numeric($val['PEF_PRMT_PCT'])
                    ? round((float) $val['PEF_PRMT_PCT'], 2)
                    : null,
                'cpn_pct' => isset($val['PEF_CPN_PCT']) && is_numeric($val['PEF_CPN_PCT'])
                    ? round((float) $val['PEF_CPN_PCT'], 2)
                    : null,
                'push_prc' => isset($val['PUSH_PRC_VALUE']) && is_numeric($val['PUSH_PRC_VALUE'])
                    ? round((float) $val['PUSH_PRC_VALUE'], 2)
                    : null,
                'sprice' => $sprice,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $dataByDate
     */
    private function ebay1OverlayLivePromo(array &$dataByDate, string $skuNorm, string $asOfEnd): void
    {
        $live = $this->ebay1LivePromoPercents($skuNorm);
        if ($live['prmt_pct'] === null && $live['cpn_pct'] === null && $live['push_prc'] === null && $live['sprice'] === null) {
            return;
        }
        if (! isset($dataByDate[$asOfEnd])) {
            return;
        }
        if ($live['prmt_pct'] !== null) {
            $dataByDate[$asOfEnd]['prmt_pct'] = $live['prmt_pct'];
        }
        if ($live['cpn_pct'] !== null) {
            $dataByDate[$asOfEnd]['cpn_pct'] = $live['cpn_pct'];
        }
        if ($live['push_prc'] !== null) {
            $dataByDate[$asOfEnd]['push_prc'] = $live['push_prc'];
        }
        if ($live['sprice'] !== null) {
            $dataByDate[$asOfEnd]['sprice'] = $live['sprice'];
        }
    }

    private function syncEbay1DailySprice(string $skuNorm, float $sprice): void
    {
        try {
            $today = Carbon::now('America/Los_Angeles')->toDateString();
            $daily = EbaySkuDailyData::firstOrNew([
                'sku' => $skuNorm,
                'record_date' => $today,
            ]);
            $payload = is_array($daily->daily_data) ? $daily->daily_data : [];
            $payload['sprice'] = round($sprice, 2);
            $daily->daily_data = $payload;
            $daily->save();
        } catch (\Throwable $e) {
            Log::warning('Could not sync eBay1 S PRC to daily history', [
                'sku' => $skuNorm,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Daily E Stock > 0 views + rolling L30 qty, collapse-carried.
     *
     * @return list<array{date: string, full_date: string, views: float, qty: float}>
     */
    private function ebay1GuardedDailyViews(int $days): array
    {
        $tz = 'America/Los_Angeles';
        $end = Carbon::now($tz)->startOfDay();
        $start = $days > 0 ? $end->copy()->subDays(max(0, $days - 1)) : null;
        $lookback = $start ? $start->copy()->subDays(14)->toDateString() : null;

        $viewsByDate = $this->ebay1CvrViewsByDate($lookback);
        $dailyQty = $this->ebay1DailyOrderQtyByDate();
        $liveQty = (float) ($this->fetchEbayL30OrdersAggregate()['qty'] ?? 0);

        if ($days <= 0) {
            $keys = array_keys($viewsByDate);
            sort($keys);
            if ($keys === []) {
                return [];
            }
            $cursor = Carbon::parse($keys[0], $tz)->startOfDay();
            $end = Carbon::parse(end($keys), $tz)->startOfDay();
            $windowStart = $cursor->toDateString();
        } else {
            $cursor = $start->copy();
            $windowStart = $start->toDateString();
        }

        $carryViews = null;
        $carryQty = null;
        foreach ($viewsByDate as $d => $v) {
            if ($d < $windowStart && $v > 0) {
                $carryViews = $v;
            }
        }

        $todayKey = $end->toDateString();
        $out = [];
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $qty = ($key === $todayKey && $liveQty > 0)
                ? $liveQty
                : $this->ebay1RollingL30OrderQty($dailyQty, $key);
            $dayViews = $viewsByDate[$key] ?? null;
            if ($dayViews !== null && $dayViews > 0) {
                if ($carryViews !== null && ChannelMasterViewsGuard::isUnstable((float) $dayViews, $carryViews, (float) $qty, $carryQty ?? 0.0)) {
                    $dayViews = $carryViews;
                } else {
                    $carryViews = $dayViews;
                    $carryQty = (float) $qty;
                }
            }
            $useViews = $dayViews ?: $carryViews;
            if ($useViews && $useViews > 0) {
                $asOf = $this->ebay1ChartAsOfLabel($key);
                $out[] = [
                    'date' => $asOf['date'],
                    'full_date' => $asOf['full_date'],
                    'views' => (float) $useViews,
                    'qty' => (float) $qty,
                ];
            }
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * eBay 1 CVR% history — same formula as the live badge and /all-marketplace-master:
     *   (real orders L30 qty) ÷ (E Stock > 0 listing views) × 100
     */
    private function ebay1CvrChartSeries(int $days): array
    {
        $out = [];
        foreach ($this->ebay1GuardedDailyViews($days) as $row) {
            if ($row['qty'] <= 0 || $row['views'] <= 0) {
                continue;
            }
            $out[] = [
                'date' => $row['date'],
                'full_date' => $row['full_date'],
                'value' => round(($row['qty'] / $row['views']) * 100, 1),
            ];
        }

        return $out;
    }

    /**
     * Views (or average daily L30 views) on the same guarded E Stock series as CVR.
     */
    private function ebay1ViewsChartSeries(int $days, bool $asDailyAvg = false): array
    {
        $out = [];
        foreach ($this->ebay1GuardedDailyViews($days) as $row) {
            $out[] = [
                'date' => $row['date'],
                'full_date' => $row['full_date'],
                'value' => $asDailyAvg
                    ? (int) round($row['views'] / 30)
                    : (float) $row['views'],
            ];
        }

        return $out;
    }

    /**
     * Listing views for CVR: channel_master (E Stock > 0 / REQ, collapse-repaired)
     * first, then amazon_channel_summary gaps.
     *
     * @return array<string, float> keyed by Y-m-d
     */
    private function ebay1CvrViewsByDate(?string $fromDate = null): array
    {
        $views = [];

        $masterQ = ChannelMasterSummary::query()
            ->where('channel', 'ebay')
            ->orderBy('snapshot_date');
        if ($fromDate) {
            $masterQ->whereDate('snapshot_date', '>=', $fromDate);
        }
        foreach ($masterQ->get() as $row) {
            $sd = ChannelMasterSummary::decodeSummaryData($row->summary_data ?? []);
            $v = (float) ($sd['total_views'] ?? 0);
            if ($v <= 0) {
                continue;
            }
            $d = $row->snapshot_date instanceof \DateTimeInterface
                ? $row->snapshot_date->format('Y-m-d')
                : date('Y-m-d', strtotime((string) $row->snapshot_date));
            $views[$d] = $v;
        }

        $snapQ = AmazonChannelSummary::where('channel', 'ebay')->orderBy('snapshot_date');
        if ($fromDate) {
            $snapQ->where('snapshot_date', '>=', $fromDate);
        }
        foreach ($snapQ->get() as $row) {
            $s = is_array($row->summary_data)
                ? $row->summary_data
                : (json_decode($row->summary_data ?? '{}', true) ?: []);
            $v = (float) ($s['total_views'] ?? 0);
            $d = $row->snapshot_date instanceof \DateTimeInterface
                ? $row->snapshot_date->format('Y-m-d')
                : date('Y-m-d', strtotime((string) $row->snapshot_date));
            if ($v <= 0 || isset($views[$d])) {
                continue;
            }
            // Skip the older INV>0+REQ tabulator sum (~2× E Stock views).
            $prior = 0.0;
            foreach ($views as $pd => $pv) {
                if ($pd < $d && $pv > 0) {
                    $prior = $pv;
                }
            }
            if ($prior > 0 && $v > $prior * 1.35) {
                continue;
            }
            $views[$d] = $v;
        }

        ksort($views);

        return $views;
    }

    /** Map marketplace_daily_metrics (channel eBay) onto tabulator badge chart keys. */
    private function ebay1MetricFromMarketplaceDaily($row, string $metric): ?float
    {
        if (! $row) {
            return null;
        }
        $map = [
            'total_sales_amt' => (float) ($row->total_sales ?? $row->l30_sales ?? 0),
            'total_ebay_l30' => (float) ($row->total_quantity ?? 0),
            'gpft_percent' => (float) ($row->pft_percentage ?? 0),
            'groi_percent' => (float) ($row->roi_percentage ?? 0),
            'tcos_percent' => (float) ($row->tacos_percentage ?? $row->ads_percentage ?? 0),
            'nroi_percent' => (float) ($row->n_roi ?? 0),
            'npft_percent' => (float) ($row->n_pft ?? 0),
        ];

        return array_key_exists($metric, $map) ? $map[$metric] : null;
    }

    /**
     * Previous-day eBay summary metrics for 3-color trend dots on summary badges.
     */
    public function getEbayBadgePrevDay(Request $request)
    {
        try {
            $today = now('America/Los_Angeles')->toDateString();
            $row = AmazonChannelSummary::where('channel', 'ebay')
                ->where('snapshot_date', '<', $today)
                ->orderBy('snapshot_date', 'desc')
                ->first();

            if (! $row) {
                return response()->json(['success' => true, 'date' => null, 'metrics' => null]);
            }

            $s = is_array($row->summary_data)
                ? $row->summary_data
                : (json_decode($row->summary_data ?? '{}', true) ?: []);

            $gpft = floatval($s['gpft_percent'] ?? 0);
            $tcos = floatval($s['tcos_percent'] ?? 0);
            $npft = array_key_exists('npft_percent', $s)
                ? floatval($s['npft_percent'])
                : ($gpft - $tcos);

            $prevDate = Carbon::parse($row->snapshot_date)->toDateString();
            $prevQty = $this->ebay1RollingL30OrderQty($this->ebay1DailyOrderQtyByDate(), $prevDate);
            $viewsByDate = $this->ebay1CvrViewsByDate(Carbon::parse($prevDate)->subDays(14)->toDateString());
            $prevViews = (float) ($viewsByDate[$prevDate] ?? ($s['total_views'] ?? 0));
            $trusted = ChannelMasterViewsGuard::lastTrusted('ebay', $prevDate);
            if ($trusted && ChannelMasterViewsGuard::isCollapsed($prevViews, $trusted['views'], (float) $prevQty, $trusted['qty'])) {
                $prevViews = $trusted['views'];
            }
            $prevCvr = ($prevViews > 0 && $prevQty > 0)
                ? round(($prevQty / $prevViews) * 100, 1)
                : floatval($s['cvr_percent'] ?? 0);

            return response()->json([
                'success' => true,
                'date' => $prevDate,
                'metrics' => [
                    'zero_sold_count' => floatval($s['zero_sold_count'] ?? 0),
                    'sold_count' => floatval($s['sold_count'] ?? 0),
                    'total_sales_amt' => floatval($s['total_sales_amt'] ?? 0),
                    'total_ebay_l30' => floatval($s['total_ebay_l30'] ?? 0),
                    'gpft_percent' => $gpft,
                    'groi_percent' => floatval($s['groi_percent'] ?? 0),
                    'tcos_percent' => $tcos,
                    'npft_percent' => $npft,
                    'nroi_percent' => floatval($s['nroi_percent'] ?? 0),
                    'cvr_percent' => $prevCvr,
                    'total_views' => $prevViews,
                    'avg_l30_view' => floatval($s['avg_l30_view'] ?? 0),
                    'avg_l7_views' => floatval($s['avg_l7_views'] ?? 0),
                    'missing_count' => floatval($s['missing_count'] ?? 0),
                    'dil_ov_percent' => floatval($s['dil_ov_percent'] ?? 0),
                    'dil_eb1_percent' => floatval($s['dil_eb1_percent'] ?? 0),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('getEbayBadgePrevDay error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error fetching previous day'], 500);
        }
    }

    public function getMetricsHistory(Request $request)
    {
        $days = (int) $request->input('days', 30); // Default to last 30 days; 0 = lifetime
        $sku = $request->input('sku'); // Optional SKU filter
        $skuNorm = $sku ? strtoupper(trim($sku)) : null;

        // California window, Active Channel as-of: last completed Pacific day (today − 1).
        $tz = 'America/Los_Angeles';
        $todayPt = Carbon::now($tz)->startOfDay();
        $endDate = $todayPt->copy()->subDay();
        if ($days === 0) {
            $startDate = null; // lifetime — no lower bound
        } else {
            if ($days < 7) {
                $days = 7;
            }
            $startDate = $endDate->copy()->subDays($days - 1);
        }

        $dataByDate = []; // Store data by date for filling gaps

        try {
            // Try to use the new table for JSON format data
            $query = EbaySkuDailyData::where('record_date', '<=', $todayPt->toDateString())
                ->orderBy('record_date', 'asc');
            if ($startDate) {
                // Extra lookback so the first window day can inherit the last known CVR/views.
                $query->where('record_date', '>=', $startDate->copy()->subDays(14)->toDateString());
            }

            // If SKU is provided, return data for specific SKU
            if ($skuNorm) {
                $metricsData = (clone $query)->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])->get();

                foreach ($metricsData as $record) {
                    $data = is_array($record->daily_data) ? $record->daily_data : (json_decode($record->daily_data ?? '{}', true) ?: []);
                    $asOf = $this->ebay1ChartAsOfLabel(
                        Carbon::parse($record->record_date, $tz)->toDateString()
                    );
                    $dateKey = $asOf['full_date'];
                    $views = (int) ($data['views'] ?? 0);
                    $ebayL30 = (int) ($data['ebay_l30'] ?? 0);
                    // Same formula as the CVR 30 column: eBay L30 ÷ views × 100
                    $cvr = $views > 0
                        ? round(($ebayL30 / $views) * 100, 2)
                        : round((float) ($data['cvr_percent'] ?? 0), 2);
                    $dataByDate[$dateKey] = array_merge([
                        'date' => $dateKey,
                        'date_formatted' => $asOf['date'],
                        'price' => round((float) ($data['price'] ?? 0), 2),
                        'views' => $views,
                        'l7_views' => (int) ($data['l7_views'] ?? 0),
                        'cvr_percent' => $cvr,
                        'ad_percent' => round((float) ($data['ad_percent'] ?? 0), 2),
                        'ebay_l30' => $ebayL30,
                        'recorded' => true,
                    ], $this->ebay1PromoFromDaily($data));
                }
            } else {
                // Aggregate data for all SKUs
                $metricsData = $query->get()->groupBy('record_date');

                foreach ($metricsData as $date => $records) {
                    $dateKey = Carbon::parse($date)->format('Y-m-d');

                    // Calculate weighted average price (same as summary badge: price * ebay_l30 / sum ebay_l30)
                    $totalWeightedPrice = 0;
                    $totalL30 = 0;
                    foreach ($records as $record) {
                        $daily = is_array($record->daily_data) ? $record->daily_data : (json_decode($record->daily_data ?? '{}', true) ?: []);
                        $price = floatval($daily['price'] ?? 0);
                        $ebayL30 = floatval($daily['ebay_l30'] ?? 0);
                        $totalWeightedPrice += $price * $ebayL30;
                        $totalL30 += $ebayL30;
                    }
                    $avgPrice = $totalL30 > 0 ? ($totalWeightedPrice / $totalL30) : 0;

                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => Carbon::parse($date)->format('M d'),
                        'avg_price' => round($avgPrice, 2),
                        'total_views' => $records->sum(function ($r) {
                            $daily = is_array($r->daily_data) ? $r->daily_data : (json_decode($r->daily_data ?? '{}', true) ?: []);
                            return $daily['views'] ?? 0;
                        }),
                        'avg_cvr_percent' => round($records->avg(function ($r) {
                            $daily = is_array($r->daily_data) ? $r->daily_data : (json_decode($r->daily_data ?? '{}', true) ?: []);
                            $views = (float) ($daily['views'] ?? 0);
                            $ebayL30 = (float) ($daily['ebay_l30'] ?? 0);
                            return $views > 0 ? (($ebayL30 / $views) * 100) : (float) ($daily['cvr_percent'] ?? 0);
                        }), 2),
                        'avg_ad_percent' => round($records->avg(function ($r) {
                            $daily = is_array($r->daily_data) ? $r->daily_data : (json_decode($r->daily_data ?? '{}', true) ?: []);
                            return $daily['ad_percent'] ?? 0;
                        }), 2),
                    ];
                }
            }

            // If no data found in new table, try fallback
            if (empty($dataByDate) && ! $skuNorm) {
                throw new \Exception('No data in new table, trying fallback');
            }

        } catch (\Exception $e) {
            // Fallback: historical rows missing — live overlay below can still return today's point
            Log::info('No eBay daily metrics data available. Historical data will be populated by metrics collection command.');
        }

        // Last real snapshot as-of (before live overlay) — days after this
        // have no collect, so Price must use the live table value, not a
        // 3-week-old carry that cliffs on the last day.
        $lastSnapAsOf = null;
        if ($skuNorm) {
            foreach ($dataByDate as $d => $row) {
                if (! empty($row['recorded']) && (float) ($row['price'] ?? 0) > 0) {
                    $lastSnapAsOf = $d;
                }
            }
        }

        // Overlay live price (and L7 only when it does not cliff) on the last
        // completed Pacific day. Views / CVR stay on the snapshot series —
        // ebay1ApplySkuOrderCvr recomputes them with the same guarded formula.
        if ($skuNorm) {
            $live = EbayMetric::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
                ->first();
            if ($live) {
                $asOfKey = $endDate->toDateString();
                $existing = $dataByDate[$asOfKey] ?? [];
                $price = round((float) ($live->ebay_price ?? 0), 2);
                $liveL7 = (int) ($live->l7_views ?? 0);
                $prevL7 = (int) ($existing['l7_views'] ?? 0);
                $l7 = $liveL7;
                if ($prevL7 > 0 && $liveL7 > 0
                    && ChannelMasterViewsGuard::isUnstable((float) $liveL7, (float) $prevL7)) {
                    $l7 = $prevL7;
                }

                $livePromo = $this->ebay1LivePromoPercents($skuNorm);
                $dataByDate[$asOfKey] = [
                    'date' => $asOfKey,
                    'date_formatted' => $endDate->format('M d'),
                    'price' => $price,
                    'views' => (int) ($existing['views'] ?? 0),
                    'l7_views' => $l7 > 0 ? $l7 : $prevL7,
                    'cvr_percent' => $existing['cvr_percent'] ?? null,
                    'ad_percent' => round((float) ($existing['ad_percent'] ?? 0), 2),
                    'ebay_l30' => (int) ($existing['ebay_l30'] ?? 0),
                    'recorded' => true,
                    'prmt_pct' => $livePromo['prmt_pct'] ?? ($existing['prmt_pct'] ?? null),
                    'cpn_pct' => $livePromo['cpn_pct'] ?? ($existing['cpn_pct'] ?? null),
                    'push_prc' => $livePromo['push_prc'] ?? ($existing['push_prc'] ?? null),
                    'sprice' => $livePromo['sprice'] ?? ($existing['sprice'] ?? null),
                ];
            }
        }

        // Seed carry-forward from the last snapshot before the window so early L30 days
        // still show the price that existed (instead of blank / $0).
        $carry = null;
        if ($skuNorm && $startDate) {
            $prior = EbaySkuDailyData::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
                ->where('record_date', '<', $startDate->toDateString())
                ->orderByDesc('record_date')
                ->first();
            if ($prior) {
                $priorData = is_array($prior->daily_data)
                    ? $prior->daily_data
                    : (json_decode($prior->daily_data ?? '{}', true) ?: []);
                $pViews = (int) ($priorData['views'] ?? 0);
                $pL30 = (int) ($priorData['ebay_l30'] ?? 0);
                $carry = array_merge([
                    'price' => round((float) ($priorData['price'] ?? 0), 2),
                    'views' => $pViews,
                    'l7_views' => (int) ($priorData['l7_views'] ?? 0),
                    'cvr_percent' => $pViews > 0
                        ? round(($pL30 / $pViews) * 100, 2)
                        : round((float) ($priorData['cvr_percent'] ?? 0), 2),
                    'ad_percent' => round((float) ($priorData['ad_percent'] ?? 0), 2),
                    'ebay_l30' => $pL30,
                    'recorded' => false,
                ], $this->ebay1PromoFromDaily($priorData));
            }
        }

        // Build the full requested window day-by-day. Missing collection days inherit the
        // last known daily values (forward-fill) so Price stays continuous — never invent $0 cliffs.
        if ($skuNorm && $startDate) {
            $windowStart = $startDate->toDateString();
            foreach ($dataByDate as $d => $row) {
                if ($d < $windowStart && ! empty($row['recorded'])) {
                    $carry = $row;
                }
            }
            $leadCarry = $carry;
            $filled = [];
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dateKey = $currentDate->format('Y-m-d');
                if (isset($dataByDate[$dateKey])) {
                    $carry = $dataByDate[$dateKey];
                    $filled[$dateKey] = $dataByDate[$dateKey];
                } elseif ($carry !== null) {
                    $filled[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => $currentDate->format('M d'),
                        'price' => (float) ($carry['price'] ?? 0),
                        'views' => (int) ($carry['views'] ?? 0),
                        'l7_views' => (int) ($carry['l7_views'] ?? 0),
                        'cvr_percent' => $carry['cvr_percent'] !== null
                            ? (float) $carry['cvr_percent']
                            : null,
                        'ad_percent' => (float) ($carry['ad_percent'] ?? 0),
                        'ebay_l30' => (int) ($carry['ebay_l30'] ?? 0),
                        'prmt_pct' => $carry['prmt_pct'] ?? null,
                        'cpn_pct' => $carry['cpn_pct'] ?? null,
                        'push_prc' => $carry['push_prc'] ?? null,
                        'sprice' => $carry['sprice'] ?? null,
                        'recorded' => false,
                    ];
                }
                $currentDate->addDay();
            }
            $dataByDate = $filled;
            // Leading holes: inherit the pre-window snapshot only — never the
            // live last-day price (that painted $19.67 onto Jul 16).
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dateKey = $cursor->format('Y-m-d');
                if (! isset($dataByDate[$dateKey]) && $leadCarry !== null) {
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => $cursor->format('M d'),
                        'price' => (float) ($leadCarry['price'] ?? 0),
                        'views' => (int) ($leadCarry['views'] ?? 0),
                        'l7_views' => (int) ($leadCarry['l7_views'] ?? 0),
                        'cvr_percent' => $leadCarry['cvr_percent'] !== null
                            ? (float) $leadCarry['cvr_percent']
                            : null,
                        'ad_percent' => (float) ($leadCarry['ad_percent'] ?? 0),
                        'ebay_l30' => (int) ($leadCarry['ebay_l30'] ?? 0),
                        'prmt_pct' => $leadCarry['prmt_pct'] ?? null,
                        'cpn_pct' => $leadCarry['cpn_pct'] ?? null,
                        'push_prc' => $leadCarry['push_prc'] ?? null,
                        'sprice' => $leadCarry['sprice'] ?? null,
                        'recorded' => false,
                    ];
                }
                $cursor->addDay();
            }
            $this->ebay1ApplySkuOrderCvr($dataByDate, $skuNorm, $endDate->toDateString());
            $this->ebay1ApplySkuLivePrice($dataByDate, $skuNorm, $endDate->toDateString(), $lastSnapAsOf);
        } elseif (! empty($dataByDate) && $startDate) {
            // Aggregate (no SKU) — fill interior gaps only, still no $0 invent for avg_price
            $realKeys = array_keys($dataByDate);
            sort($realKeys);
            $fillStart = Carbon::parse($realKeys[0], 'America/Los_Angeles')->startOfDay();
            $fillEnd = Carbon::parse($realKeys[count($realKeys) - 1], 'America/Los_Angeles')->startOfDay();
            if ($fillStart->lt($startDate)) {
                $fillStart = $startDate->copy();
            }
            if ($fillEnd->gt($endDate)) {
                $fillEnd = $endDate->copy();
            }
            $carryAgg = null;
            $currentDate = $fillStart->copy();
            $filledAgg = [];
            while ($currentDate->lte($fillEnd)) {
                $dateKey = $currentDate->format('Y-m-d');
                if (isset($dataByDate[$dateKey])) {
                    $carryAgg = $dataByDate[$dateKey];
                    $filledAgg[$dateKey] = $dataByDate[$dateKey];
                } elseif ($carryAgg !== null) {
                    $filledAgg[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => $currentDate->format('M d'),
                        'avg_price' => (float) ($carryAgg['avg_price'] ?? 0),
                        'total_views' => (int) ($carryAgg['total_views'] ?? 0),
                        'avg_cvr_percent' => (float) ($carryAgg['avg_cvr_percent'] ?? 0),
                        'avg_ad_percent' => (float) ($carryAgg['avg_ad_percent'] ?? 0),
                    ];
                }
                $currentDate->addDay();
            }
            $dataByDate = $filledAgg;
        }

        if ($skuNorm) {
            $this->ebay1OverlayLivePromo($dataByDate, $skuNorm, $endDate->toDateString());
        }

        // Sort by date and convert to array
        ksort($dataByDate);
        $chartData = array_values($dataByDate);

        return response()->json($chartData);
    }

    public function pushEbayPrice(Request $request)
    {
        $sku   = strtoupper(trim($request->input('sku')));
        $price = $request->input('price');

        // --- Input validation (kept identical to original) ---

        if (empty($sku)) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'SKU is required.']]
            ], 400);
        }

        $priceFloat = floatval($price);
        if (!is_numeric($price) || $priceFloat <= 0) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'Price must be a positive number.']]
            ], 400);
        }

        if ($priceFloat < 0.01 || $priceFloat > 10000) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'Price must be between $0.01 and $10,000.']]
            ], 400);
        }

        // Cap to two decimal places
        $priceFloat = round($priceFloat, 2);

        try {
            // Resolve eBay listing metadata from the local DB
            $ebayMetric = EbayMetric::where('sku', $sku)->first();

            if (!$ebayMetric || !$ebayMetric->item_id) {
                $this->saveSpriceStatus($sku, 'error');
                Log::error('[EbayController] eBay item_id not found', ['sku' => $sku]);
                return response()->json([
                    'errors' => [['code' => 'NotFound', 'message' => 'eBay listing not found for SKU: ' . $sku]]
                ], 404);
            }

            $pushed = $this->pushEbay1PriceAndPullLive($sku, $priceFloat);
            if ($pushed['success']) {
                $this->saveSpriceStatus($sku, 'pushed');
                Log::info('[EbayController] eBay price push successful via microservice', [
                    'sku'     => $sku,
                    'price'   => $priceFloat,
                    'item_id' => $ebayMetric->item_id,
                    'ebay_price' => $pushed['ebay_price'] ?? null,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => $pushed['message'] ?: 'Price updated successfully',
                    'ebay_price' => $pushed['ebay_price'] ?? $priceFloat,
                ]);
            }

            $result = [
                'success' => false,
                'accountRestricted' => ($pushed['status'] ?? '') === 'account_restricted',
                'errors' => $pushed['errors'] ?? [['code' => 'UnknownError', 'message' => $pushed['message'] ?? 'Failed to update price']],
            ];

            // --- Failure path ---

            // accountRestricted is forwarded from the microservice (which itself
            // forwards it from eBay's Trading API response).
            $isAccountRestricted = isset($result['accountRestricted']) && $result['accountRestricted'];

            $this->saveSpriceStatus($sku, $isAccountRestricted ? 'account_restricted' : 'error');

            // EbayPushService already normalizes errors to [{code, message}].
            // We also handle the raw eBay format (ErrorCode / LongMessage) in
            // case the microservice forwards it verbatim, so both shapes work.
            $errors = $result['errors'] ?? [['code' => 'UnknownError', 'message' => 'Failed to update price']];

            if (!is_array($errors)) {
                $errors = [$errors];
            }

            $errorMessages = [];
            $hasLvisError  = false;

            foreach ($errors as $error) {
                // Support both microservice normalized format {code, message}
                // and raw eBay Trading API format {ErrorCode, LongMessage, ...}
                $errorCode   = is_array($error) ? ($error['code'] ?? $error['ErrorCode'] ?? '') : '';
                $errorMsg    = is_array($error)
                    ? ($error['message'] ?? $error['LongMessage'] ?? $error['ShortMessage'] ?? 'Unknown error')
                    : (string) $error;
                $errorParams = is_array($error) ? ($error['ErrorParameters'] ?? []) : [];

                // Append any embedded parameter values into the full error text
                $paramMessages = [];
                if (is_array($errorParams)) {
                    foreach ($errorParams as $param) {
                        if (is_array($param) && isset($param['Value'])) {
                            $paramMessages[] = strip_tags($param['Value']);
                        }
                    }
                }
                $fullErrorText = trim($errorMsg . ' ' . implode(' ', $paramMessages));

                // Detect account-level restrictions that cannot be bypassed
                $isRestricted      = false;
                $isEmbargoedCountry = false;

                if (
                    stripos($fullErrorText, 'account is restricted') !== false ||
                    stripos($fullErrorText, 'restrictions on your account') !== false ||
                    stripos($fullErrorText, 'embargoed country') !== false ||
                    stripos($fullErrorText, 'ACCOUNT RESTRICTION') !== false
                ) {
                    $isRestricted       = true;
                    $isEmbargoedCountry = stripos($fullErrorText, 'embargoed country') !== false;
                }

                if ($errorCode === '21916293' || stripos($errorMsg, 'Lvis') !== false || $isRestricted) {
                    $hasLvisError = true;

                    if ($isRestricted) {
                        $errorMessages[] = [
                            'code'    => $errorCode ?: 'AccountRestricted',
                            'message' => $isEmbargoedCountry
                                ? 'ACCOUNT RESTRICTION: Your eBay account is restricted due to country/embargo restrictions. Please check your eBay Messages for "Your eBay account is restricted" and resolve the account restrictions before updating prices. This cannot be bypassed programmatically.'
                                : 'ACCOUNT RESTRICTION: Your eBay account has restrictions that prevent price updates. Please check your eBay Messages for "Your eBay account is restricted" and provide the requested information to remove restrictions. Contact eBay Customer Service if you believe this is an error.',
                        ];
                    } else {
                        $errorMessages[] = [
                            'code'    => $errorCode ?: 'LvisBlocked',
                            'message' => 'Listing validation blocked: This listing may have policy violations or restrictions. Please check the listing status in eBay Seller Hub and resolve any issues before updating the price.',
                        ];
                    }
                } else {
                    // Business-policy warnings (21919456) are non-blocking – log but do not surface to the UI
                    if ($errorCode === '21919456' || stripos($errorMsg, 'business policies') !== false) {
                        Log::warning('[EbayController] eBay business policy warning (non-blocking)', [
                            'sku'   => $sku,
                            'error' => $errorMsg,
                        ]);
                    } else {
                        $errorMessages[] = [
                            'code'    => $errorCode ?: 'APIError',
                            'message' => $errorMsg,
                        ];
                    }
                }
            }

            Log::error('[EbayController] eBay price push failed via microservice', [
                'sku'          => $sku,
                'price'        => $priceFloat,
                'item_id'      => $ebayMetric->item_id,
                'errors'       => $errors,
                'hasLvisError' => $hasLvisError,
            ]);

            return response()->json(['errors' => $errorMessages], 400);

        } catch (\Exception $e) {
            // Catch-all: log the failure but do not let it propagate and break
            // the UI. EbayPushService already catches its own exceptions; this
            // guard covers any unexpected error in the controller logic itself.
            $this->saveSpriceStatus($sku, 'error');
            Log::error('[EbayController] Exception in pushEbayPrice', [
                'sku'   => $sku,
                'price' => $priceFloat ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'errors' => [['code' => 'Exception', 'message' => 'An error occurred: ' . $e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Push S PRC to eBay, then GetItem and write the live price to ebay_metrics.
     *
     * @return array{success: bool, status: string, message: string, ebay_price: ?float, errors: array}
     */
    private function pushEbay1PriceAndPullLive(string $sku, float $priceFloat): array
    {
        $empty = [
            'success' => false,
            'status' => 'error',
            'message' => '',
            'ebay_price' => null,
            'errors' => [],
        ];

        $ebayMetric = EbayMetric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])->first()
            ?? EbayMetric::query()->where('sku', $sku)->first();

        if (! $ebayMetric || ! $ebayMetric->item_id) {
            return array_merge($empty, [
                'status' => 'not_listed',
                'message' => 'eBay listing not found for SKU: '.$sku,
                'errors' => [['code' => 'NotFound', 'message' => 'eBay listing not found for SKU: '.$sku]],
            ]);
        }

        $current = round((float) ($ebayMetric->ebay_price ?? 0), 2);
        if ($current > 0 && abs($current - $priceFloat) < 0.005) {
            return [
                'success' => true,
                'status' => 'already_live',
                'message' => 'eBay already at $'.number_format($priceFloat, 2),
                'ebay_price' => $current,
                'errors' => [],
            ];
        }

        try {
            $ebayService = new EbayApiService();
            $apiSku = trim((string) ($ebayMetric->sku ?: $sku));
            $result = Ebay1PromotionService::for('ebay1')->withPriceRevisionAllowed(
                $sku,
                fn () => $ebayService->reviseFixedPriceItem($ebayMetric->item_id, $priceFloat, null, $apiSku)
            );
        } catch (\Throwable $e) {
            Log::error('[EbayController] Auto-push S PRC failed', [
                'sku' => $sku,
                'price' => $priceFloat,
                'error' => $e->getMessage(),
            ]);

            return array_merge($empty, [
                'message' => $e->getMessage(),
                'errors' => [['code' => 'Exception', 'message' => $e->getMessage()]],
            ]);
        }

        if (! isset($result['success']) || ! $result['success']) {
            $isAccountRestricted = ! empty($result['accountRestricted']);
            $errors = $result['errors'] ?? [['code' => 'UnknownError', 'message' => $result['message'] ?? 'Failed to update price']];
            if (! is_array($errors)) {
                $errors = [$errors];
            }

            return [
                'success' => false,
                'status' => $isAccountRestricted ? 'account_restricted' : 'error',
                'message' => $this->ebay1FirstPushErrorMessage($errors),
                'ebay_price' => null,
                'errors' => $errors,
            ];
        }

        $live = $this->ebay1PullLivePriceAndUpdateMetric($ebayMetric, $sku, $ebayService);
        if ($live === null) {
            $live = $priceFloat;
            $ebayMetric->ebay_price = $live;
            $ebayMetric->save();
            $this->ebay1SyncDailyPrice($sku, $live);
        }

        Log::info('[EbayController] S PRC auto-pushed and live price pulled', [
            'sku' => $sku,
            'pushed' => $priceFloat,
            'ebay_price' => $live,
            'item_id' => $ebayMetric->item_id,
        ]);

        return [
            'success' => true,
            'status' => 'pushed',
            'message' => 'Price updated successfully',
            'ebay_price' => $live,
            'errors' => [],
        ];
    }

    /**
     * @param  array<int, mixed>  $errors
     */
    private function ebay1FirstPushErrorMessage(array $errors): string
    {
        foreach ($errors as $error) {
            if (is_array($error)) {
                $msg = (string) ($error['message'] ?? $error['LongMessage'] ?? $error['ShortMessage'] ?? '');
                if ($msg !== '') {
                    return $msg;
                }
            } elseif (is_string($error) && $error !== '') {
                return $error;
            }
        }

        return 'Failed to update price';
    }

    private function ebay1PullLivePriceAndUpdateMetric(EbayMetric $ebayMetric, string $sku, ?EbayApiService $ebayService = null): ?float
    {
        try {
            $ebayService = $ebayService ?: new EbayApiService();
            $info = $ebayService->getItem((string) $ebayMetric->item_id);
            $item = is_array($info['Item'] ?? null) ? $info['Item'] : [];
            if ($item === []) {
                return null;
            }
            $live = $this->ebay1LivePriceFromGetItem($item, $sku);
            if ($live === null || $live <= 0) {
                return null;
            }
            $ebayMetric->ebay_price = $live;
            $ebayMetric->save();
            $this->ebay1SyncDailyPrice($sku, $live);

            return $live;
        } catch (\Throwable $e) {
            Log::warning('[EbayController] GetItem after S PRC push failed', [
                'sku' => $sku,
                'item_id' => $ebayMetric->item_id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function ebay1LivePriceFromGetItem(array $item, string $sku): ?float
    {
        $skuNorm = strtoupper(trim($sku));
        $vars = $item['Variations']['Variation'] ?? null;
        if (is_array($vars) && $vars !== []) {
            if (isset($vars['SKU']) || isset($vars['StartPrice'])) {
                $vars = [$vars];
            }
            foreach ($vars as $variation) {
                if (! is_array($variation)) {
                    continue;
                }
                $vSku = strtoupper(trim((string) ($variation['SKU'] ?? '')));
                if ($vSku !== '' && $vSku === $skuNorm) {
                    $price = $this->ebay1ParseMoney($variation['StartPrice'] ?? null)
                        ?? $this->ebay1ParseMoney($variation['SellingStatus']['CurrentPrice'] ?? null);
                    if ($price !== null && $price > 0) {
                        return $price;
                    }
                }
            }
        }

        return $this->ebay1ParseMoney($item['StartPrice'] ?? null)
            ?? $this->ebay1ParseMoney($item['SellingStatus']['CurrentPrice'] ?? null);
    }

    private function ebay1ParseMoney(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            $n = round((float) $raw, 2);

            return $n > 0 ? $n : null;
        }
        if (is_array($raw)) {
            foreach (['@content', '#text', '_', 'value', 'Value'] as $key) {
                if (isset($raw[$key]) && is_numeric($raw[$key])) {
                    $n = round((float) $raw[$key], 2);

                    return $n > 0 ? $n : null;
                }
            }
            if (isset($raw[0]) && is_numeric($raw[0])) {
                $n = round((float) $raw[0], 2);

                return $n > 0 ? $n : null;
            }
            foreach ($raw as $val) {
                if (is_numeric($val)) {
                    $n = round((float) $val, 2);

                    return $n > 0 ? $n : null;
                }
            }
        }
        if (is_string($raw) && preg_match('/[\d.]+/', $raw, $m)) {
            $n = round((float) $m[0], 2);

            return $n > 0 ? $n : null;
        }

        return null;
    }

    private function ebay1SyncDailyPrice(string $skuNorm, float $price): void
    {
        try {
            $today = Carbon::now('America/Los_Angeles')->toDateString();
            $daily = EbaySkuDailyData::firstOrNew([
                'sku' => $skuNorm,
                'record_date' => $today,
            ]);
            $payload = is_array($daily->daily_data) ? $daily->daily_data : [];
            $payload['price'] = round($price, 2);
            $daily->daily_data = $payload;
            $daily->save();
        } catch (\Throwable $e) {
            Log::warning('Could not sync eBay1 live price to daily history', [
                'sku' => $skuNorm,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function saveSpriceStatus($sku, $status)
    {
        try {
            $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);
            
            $existing = is_array($ebayDataView->value)
                ? $ebayDataView->value
                : (json_decode($ebayDataView->value, true) ?: []);

            $merged = array_merge($existing, [
                'SPRICE_STATUS' => $status,
                'SPRICE_STATUS_UPDATED_AT' => now()->toDateTimeString()
            ]);

            $ebayDataView->value = $merged;
            $ebayDataView->save();
        } catch (\Exception $e) {
            Log::error('Error saving SPRICE_STATUS', ['sku' => $sku, 'status' => $status, 'error' => $e->getMessage()]);
        }
    }

    public function updateEbaySpriceStatus(Request $request)
    {
        $sku = strtoupper(trim($request->input('sku')));
        $status = $request->input('status');

        if (empty($sku) || !in_array($status, ['pushed', 'applied', 'error'])) {
            return response()->json(['success' => false, 'error' => 'Invalid SKU or status'], 400);
        }

        $this->saveSpriceStatus($sku, $status);
        return response()->json(['success' => true, 'message' => 'Status updated successfully']);
    }

    public function getEbayAdsSpend()
    {
        try {
            // Get the latest eBay ads spend from marketplace_daily_metrics
            $latestData = DB::table('marketplace_daily_metrics')
                ->where('channel', 'ebay')
                ->orderBy('date', 'desc')
                ->select('date', 'kw_spent', 'pmt_spent')
                ->first();

            return response()->json([
                'success' => true,
                'date' => $latestData->date ?? null,
                'kw_spent' => floatval($latestData->kw_spent ?? 0),
                'pmt_spent' => floatval($latestData->pmt_spent ?? 0),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching eBay ads spend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch ads spend data'
            ], 500);
        }
    }

    /**
     * Get KW and PMT spend totals from reports (matches KW/PMP ads pages exactly)
     * Uses the same queries as EbayKwAdsController and EbayPMPAdsController
     */
    public function getKwPmtSpendTotals()
    {
        try {
            // Use daily data sum (individual date report_ranges) instead of L30 aggregate
            // Daily data is closer to eBay Seller Hub dashboard values
            $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');

            // KW Spend: Sum daily reports from ebay_priority_reports
            // Matches ChannelMasterController::fetchAdMetricsFromTables() exactly
            $kwSpend = DB::table('ebay_priority_reports')
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->selectRaw('SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as total_spend')
                ->value('total_spend') ?? 0;

            // PMT Spend: Sum daily reports from ebay_general_reports
            // Matches ChannelMasterController::fetchAdMetricsFromTables() exactly
            $pmtSpend = DB::table('ebay_general_reports')
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->selectRaw('SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")) as total_spend')
                ->value('total_spend') ?? 0;

            $totalSpend = round(floatval($kwSpend) + floatval($pmtSpend), 2);

            return response()->json([
                'success' => true,
                'kw_spend' => round(floatval($kwSpend), 2),
                'pmt_spend' => round(floatval($pmtSpend), 2),
                'total_spend' => $totalSpend,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching eBay KW/PMT spend totals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch spend totals'
            ], 500);
        }
    }

    public function updateEbayRating(Request $request)
    {
        $sku = strtoupper(trim($request->input('sku')));
        $rating = $request->input('rating');

        // Validate rating
        if (!is_numeric($rating) || $rating < 0 || $rating > 5) {
            return response()->json([
                'success' => false,
                'error' => 'Rating must be a number between 0 and 5'
            ], 400);
        }

        try {
            // Find or create the data view record
            $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);
            
            // Decode existing value
            $currentValue = is_array($ebayDataView->value)
                ? $ebayDataView->value
                : (json_decode($ebayDataView->value, true) ?? []);
            
            // Update rating
            $currentValue['rating'] = floatval($rating);
            
            // Save
            $ebayDataView->value = $currentValue;
            $ebayDataView->save();

            return response()->json([
                'success' => true,
                'message' => 'Rating updated successfully',
                'rating' => floatval($rating)
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating eBay rating: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error updating rating'
            ], 500);
        }
    }

    public function downloadEbayRatingsSample()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $sheet->setCellValue('A1', 'sku');
            $sheet->setCellValue('B1', 'rating');

            // Add sample data
            $sheet->setCellValue('A2', 'SAMPLE-SKU-001');
            $sheet->setCellValue('B2', '4.5');
            $sheet->setCellValue('A3', 'SAMPLE-SKU-002');
            $sheet->setCellValue('B3', '4.0');
            $sheet->setCellValue('A4', 'SAMPLE-SKU-003');
            $sheet->setCellValue('B4', '3.5');

            // Style header row
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ];
            $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);

            // Auto-size columns
            foreach (range('A', 'B') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Generate file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'ebay_ratings_sample_' . date('Y-m-d') . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error generating eBay ratings sample: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate sample file');
        }
    }

    public function importEbayRatings(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx'
        ]);

        try {
            $file = $request->file('file');
            $imported = 0;
            $skipped = 0;

            // Check if it's CSV or Excel
            $extension = $file->getClientOriginalExtension();

            if ($extension === 'xlsx') {
                // Handle Excel file
                $spreadsheet = IOFactory::load($file->getRealPath());
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                // Remove header
                array_shift($rows);

                foreach ($rows as $row) {
                    if (empty($row[0])) {
                        $skipped++;
                        continue;
                    }

                    $sku = strtoupper(trim($row[0]));
                    $rating = isset($row[1]) ? floatval($row[1]) : null;

                    // Validate rating
                    if ($rating === null || $rating < 0 || $rating > 5) {
                        $skipped++;
                        continue;
                    }

                    // Update or create
                    $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);
                    $currentValue = is_array($ebayDataView->value)
                        ? $ebayDataView->value
                        : (json_decode($ebayDataView->value, true) ?? []);
                    
                    $currentValue['rating'] = $rating;
                    $ebayDataView->value = $currentValue;
                    $ebayDataView->save();

                    $imported++;
                }
            } else {
                // Handle CSV file
                $content = file_get_contents($file->getRealPath());
                $content = preg_replace('/^\x{FEFF}/u', '', $content); // Remove BOM
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
                $csvData = array_map('str_getcsv', explode("\n", $content));
                $csvData = array_filter($csvData, function($row) {
                    return count($row) > 0 && !empty(trim(implode('', $row)));
                });
                
                // Remove header
                array_shift($csvData);

                foreach ($csvData as $row) {
                    $row = array_map('trim', $row);
                    if (empty($row[0])) {
                        $skipped++;
                        continue;
                    }

                    $sku = strtoupper($row[0]);
                    $rating = isset($row[1]) ? floatval($row[1]) : null;

                    // Validate rating
                    if ($rating === null || $rating < 0 || $rating > 5) {
                        $skipped++;
                        continue;
                    }

                    // Update or create
                    $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);
                    $currentValue = is_array($ebayDataView->value)
                        ? $ebayDataView->value
                        : (json_decode($ebayDataView->value, true) ?? []);
                    
                    $currentValue['rating'] = $rating;
                    $ebayDataView->value = $currentValue;
                    $ebayDataView->save();

                    $imported++;
                }
            }

            return response()->json([
                'success' => 'Imported ' . $imported . ' ratings successfully' . 
                            ($skipped > 0 ? ', skipped ' . $skipped . ' invalid rows' : '')
            ]);
        } catch (\Exception $e) {
            Log::error('Error importing eBay ratings: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error importing ratings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-save daily eBay summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    public function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            // Uses updateOrCreate so it updates existing record for today
            
            // ALL data with INV > 0 (for grand totals)
            $allData = collect($products)->filter(function($p) {
                return floatval($p['INV'] ?? 0) > 0;
            });
            
            // Filtered data: INV > 0 && nr_req === 'REQ' (for other metrics)
            $filteredData = collect($products)->filter(function($p) {
                $invCheck = floatval($p['INV'] ?? 0) > 0;
                $reqCheck = ($p['nr_req'] ?? '') === 'REQ';
                return $invCheck && $reqCheck;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalSkuCount = $filteredData->count();
            $moreSoldCount = 0;   // eBay L30 > 0
            $zeroSoldCount = 0;   // eBay L30 = 0
            $missingCount = 0;    // No eBay item ID
            $mapCount = 0;        // INV = eBay Stock
            $notMapCount = 0;     // INV != eBay Stock
            $prcGtLmpCount = 0;   // eBay Price > LMP Price
            
            $totalPftAmt = 0;
            $totalSalesAmt = 0;
            $totalLpAmt = 0;
            $totalFbaInv = 0;
            $totalEbayL30 = 0;
            $totalWeightedPrice = 0;
            $totalViews = 0;
            $totalL7Views = 0;
            $l7ViewsCount = 0;
            
            // Grand totals (from ALL data)
            $grandTotalKwSpend = 0;
            $grandTotalPmtSpend = 0;
            $grandTotalSpend = 0;
            
            // Calculate grand totals from ALL data (no REQ filter - matches JavaScript)
            foreach ($allData as $row) {
                $grandTotalKwSpend += floatval($row['kw_spend_L30'] ?? 0);
                $grandTotalPmtSpend += floatval($row['pmt_spend_L30'] ?? 0);
                $grandTotalSpend += floatval($row['AD_Spend_L30'] ?? 0);
            }
            
            // Loop through FILTERED data (with REQ filter - matches JavaScript)
            foreach ($filteredData as $row) {
                $inv = floatval($row['INV'] ?? 0);
                $ebayL30 = floatval($row['eBay L30'] ?? 0);
                $ebayStock = floatval($row['eBay Stock'] ?? $row['E Stock'] ?? 0);
                
                $totalPftAmt += floatval($row['Total_pft'] ?? 0);
                $totalSalesAmt += floatval($row['T_Sale_l30'] ?? 0);
                $totalLpAmt += floatval($row['LP_productmaster'] ?? 0) * $ebayL30;
                $totalFbaInv += $inv;
                $totalEbayL30 += $ebayL30;

                // Avg L7 badge: same as JS — rows with E Stock > 0
                if ($ebayStock > 0) {
                    $totalL7Views += floatval($row['l7_views'] ?? 0);
                    $l7ViewsCount++;
                }
                
                // Count sold and 0-sold (EXACT JavaScript logic)
                if ($ebayL30 == 0) {  // Use == for proper float comparison
                    $zeroSoldCount++;
                } else {
                    $moreSoldCount++;
                }
                
                // Count Missing (no eBay item ID)
                $itemId = $row['eBay_item_id'] ?? '';
                if (!$itemId || $itemId === null || $itemId === '') {
                    $missingCount++;
                }
                
                // Count Map and N MP (only if exists in eBay)
                if ($itemId && $itemId !== null && $itemId !== '') {
                    $ebayStock = floatval($row['eBay Stock'] ?? 0);
                    if ($inv > 0 && $ebayStock > 0 && $inv === $ebayStock) {
                        $mapCount++;
                    } else if ($inv > 0 && ($ebayStock === 0 || ($ebayStock > 0 && $inv !== $ebayStock))) {
                        $notMapCount++;
                    }
                }
                
                $ebayPrice = floatval($row['eBay Price'] ?? 0);

                // Count Prc > LMP
                $lmpPrice = floatval($row['lmp_price'] ?? 0);
                if ($lmpPrice > 0 && $ebayPrice > $lmpPrice) {
                    $prcGtLmpCount++;
                }
                
                // Weighted price
                $totalWeightedPrice += $ebayPrice * $ebayL30;
            }
            
            // Calculate averages and percentages (EXACT JavaScript logic)
            $avgPrice = $totalEbayL30 > 0 ? $totalWeightedPrice / $totalEbayL30 : 0;
            $avgL7Views = $l7ViewsCount > 0 ? ($totalL7Views / $l7ViewsCount) : 0;
            $tcosPercent = $totalSalesAmt > 0 ? (($grandTotalSpend / $totalSalesAmt) * 100) : 0;
            $groiPercent = $totalLpAmt > 0 ? (($totalPftAmt / $totalLpAmt) * 100) : 0;
            // NROI% = (GPFT$ − Ad Spend) / COGS × 100 — same as Amazon / ebay-tabulator badge
            $nroiPercent = $totalLpAmt > 0 ? ((($totalPftAmt - $grandTotalSpend) / $totalLpAmt) * 100) : 0;
            $gpftPercent = $totalSalesAmt > 0 ? (($totalSalesAmt - $totalLpAmt) / $totalSalesAmt * 100) : 0;
            $npftPercent = $gpftPercent - $tcosPercent;
            // Views: same scope as the live CVR badge (E Stock > 0, non-parent).
            // INV>0+REQ used to include ended listings and inflated the denominator (~95k vs ~45k).
            foreach ($products as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $parent = trim((string) ($row['Parent'] ?? ''));
                $isParent = (($row['is_parent_summary'] ?? false) === true)
                    || ($parent !== '' && stripos($parent, 'PARENT') === 0);
                if ($isParent) {
                    continue;
                }
                $eStock = (float) ($row['eBay Stock'] ?? ($row['E Stock'] ?? 0));
                if ($eStock > 0) {
                    $totalViews += (float) ($row['views'] ?? 0);
                }
            }

            $cvrPercent = $totalViews > 0 ? (($totalEbayL30 / $totalViews) * 100) : 0;
            $listingEbayL30 = $totalEbayL30;
            $totalInvAvailable = $totalFbaInv;

            // Prefer real-orders L30 (same source as live summary badges) when available.
            try {
                $ordersAgg = $this->fetchEbayL30OrdersAggregate();
                $ebayAdSpend = (float) app(ChannelMasterController::class)->getEbayMasterAdSpend();
                if (($ordersAgg['sales'] ?? 0) > 0) {
                    $totalSalesAmt = (float) $ordersAgg['sales'];
                    $totalEbayL30 = (float) ($ordersAgg['qty'] ?? $totalEbayL30);
                    $totalPftAmt = (float) ($ordersAgg['pft'] ?? $totalPftAmt);
                    $totalLpAmt = (float) ($ordersAgg['cogs'] ?? $totalLpAmt);
                    $gpftPercent = (float) ($ordersAgg['gpft'] ?? $gpftPercent);
                    $groiPercent = (float) ($ordersAgg['groi'] ?? $groiPercent);
                    $tcosPercent = $totalSalesAmt > 0 ? (($ebayAdSpend / $totalSalesAmt) * 100) : 0;
                    $npftPercent = $gpftPercent - $tcosPercent;
                    $nroiPercent = $totalLpAmt > 0
                        ? ((($totalPftAmt - $ebayAdSpend) / $totalLpAmt) * 100)
                        : 0;
                    $cvrPercent = $totalViews > 0 ? (($totalEbayL30 / $totalViews) * 100) : 0;
                }
            } catch (\Throwable $e) {
                Log::warning('eBay summary: orders aggregate unavailable for snapshot', [
                    'error' => $e->getMessage(),
                ]);
            }

            $totalViews = ChannelMasterViewsGuard::stabilize('ebay', (float) $totalViews, (float) $totalEbayL30, $today);
            $cvrPercent = $totalViews > 0 ? (($totalEbayL30 / $totalViews) * 100) : 0;

            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'nmap_count' => $notMapCount,  // Renamed from not_map_count for consistency
                'not_map_count' => $notMapCount,  // Keep for backward compatibility
                'less_amz_count' => 0,
                'more_amz_count' => 0,
                'prc_gt_lmp_count' => $prcGtLmpCount,
                
                // Financial Totals
                'grand_total_kw_spend' => round($grandTotalKwSpend, 2),
                'grand_total_pmt_spend' => round($grandTotalPmtSpend, 2),
                'grand_total_spend' => round($grandTotalSpend, 2),
                'total_pft_amt' => round($totalPftAmt, 2),
                'total_sales_amt' => round($totalSalesAmt, 2),
                'total_lp_amt' => round($totalLpAmt, 2),
                
                // Inventory
                'total_fba_inv' => round($totalFbaInv, 2),
                'total_ebay_l30' => round($totalEbayL30, 2),
                'total_views' => (int) $totalViews,
                // Average daily L30 views (Σ L30 views / 30), rounded
                'avg_l30_view' => (int) round($totalViews / 30),
                // Avg L7 views across E Stock > 0 rows, rounded (matches L7 badge)
                'avg_l7_views' => (int) round($avgL7Views),
                
                // Calculated Percentages (match live summary badges)
                'tcos_percent' => round($tcosPercent, 2),
                'groi_percent' => round($groiPercent, 2),
                'nroi_percent' => round($nroiPercent, 2),
                'gpft_percent' => round($gpftPercent, 2),
                'npft_percent' => round($npftPercent, 2),
                'cvr_percent' => round($cvrPercent, 2),
                'total_inv' => round($totalInvAvailable, 2),
                'total_ebay_listing_l30' => round($listingEbayL30, 2),
                'dil_ov_percent' => $totalInvAvailable > 0
                    ? round(($totalEbayL30 / $totalInvAvailable) * 100, 2)
                    : 0,
                'dil_eb1_percent' => $totalInvAvailable > 0
                    ? round(($listingEbayL30 / $totalInvAvailable) * 100, 2)
                    : 0,
                
                // Averages
                'avg_price' => round($avgPrice, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                'sales_source' => 'orders_l30',
                
                // Active Filters (eBay specific)
                'filters_applied' => [
                    'inventory' => 'more',  // INV > 0
                    'nrl' => 'REQ',        // REQ only
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'ebay',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, REQ only)',
                ]
            );
            
            Log::info("Daily eBay summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily eBay summary: ' . $e->getMessage());
        }
    }

    /**
     * Get KW and PMT campaign data by SKU for ACOS modal (eBay tabulator view).
     */
    public function getCampaignDataBySku(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }
        $cleanSku = strtoupper(trim((string) $sku));

        $ebayMetric = EbayMetric::where('sku', $sku)->first();
        if (!$ebayMetric) {
            $ebayMetric = EbayMetric::whereRaw('UPPER(TRIM(sku)) = ?', [$cleanSku])->first();
        }
        $itemId = $ebayMetric && !empty($ebayMetric->item_id) ? trim((string) $ebayMetric->item_id) : null;

        $shopify = ShopifySku::firstForProductSku($sku);
        $inv = $shopify ? (float) ($shopify->inv ?? 0) : 0.0;

        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $lastSbidMap = [];
        $lastSbidReports = EbayPriorityReport::where('report_range', $dayBeforeYesterday)
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();
        foreach ($lastSbidReports as $report) {
            if (empty($report->campaign_id)) {
                continue;
            }
            $v = $report->last_sbid;
            if ($v === null || $v === '' || $v === '0' || $v === 0 || (is_numeric($v) && (float) $v === 0.0)) {
                continue;
            }
            $lastSbidMap[(string) $report->campaign_id] = $v;
        }

        $kwCampaigns = [];
        $kwL30 = EbayPriorityReport::where('report_range', 'L30')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get();
        $kwL7 = EbayPriorityReport::where('report_range', 'L7')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $kwL1 = EbayPriorityReport::where('report_range', 'L1')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $price = $ebayMetric ? (float) ($ebayMetric->ebay_price ?? 0) : 0;

        foreach ($kwL30 as $r) {
            $campaignId = $r->campaign_id ?? null;
            $cid = $campaignId !== null ? (string) $campaignId : null;
            $rL7 = $cid ? $kwL7->get($cid) : null;
            $rL1 = $cid ? $kwL1->get($cid) : null;
            if (!$rL7 && $cid) {
                $rL7 = $kwL7->first(fn ($x) => (string) ($x->campaign_id ?? '') === $cid);
            }
            if (!$rL1 && $cid) {
                $rL1 = $kwL1->first(fn ($x) => (string) ($x->campaign_id ?? '') === $cid);
            }

            $spend = (float) str_replace(['USD ', 'USD'], '', $r->cpc_ad_fees_payout_currency ?? '0');
            $sales = (float) str_replace(['USD ', 'USD'], '', $r->cpc_sale_amount_payout_currency ?? '0');
            $clicks = (int) ($r->cpc_clicks ?? 0);
            $sold = (int) ($r->cpc_attributed_sales ?? 0);
            $acos = ($sales > 0) ? (($spend / $sales) * 100) : (($spend > 0) ? 100 : 0);
            $adCvr = $clicks > 0 ? (($sold / $clicks) * 100) : 0;
            $bgt = (float) ($r->campaignBudgetAmount ?? 0);

            $l7Spend = $rL7 ? (float) str_replace(['USD ', 'USD'], '', $rL7->cpc_ad_fees_payout_currency ?? '0') : 0;
            $l1Spend = $rL1 ? (float) str_replace(['USD ', 'USD'], '', $rL1->cpc_ad_fees_payout_currency ?? '0') : 0;
            $l7Cpc = $rL7 ? (float) str_replace(['USD ', 'USD'], '', $rL7->cost_per_click ?? '0') : null;
            $l1Cpc = $rL1 ? (float) str_replace(['USD ', 'USD'], '', $rL1->cost_per_click ?? '0') : null;

            $ub7 = ($bgt > 0) ? (($l7Spend / ($bgt * 7)) * 100) : 0;
            $ub1 = ($bgt > 0) ? (($l1Spend / $bgt) * 100) : 0;

            // SBGT: ebay/utilized rule – ACOS-based only
            $acosForSbgt = $acos;
            if ($acosForSbgt === 0 && $spend > 0) {
                $acosForSbgt = 100;
            }
            if ($acosForSbgt < 4) {
                $sbgt = 9;
            } elseif ($acosForSbgt >= 4 && $acosForSbgt < 8) {
                $sbgt = 6;
            } else {
                $sbgt = 3;
            }

            $lastSbidRaw = $cid && isset($lastSbidMap[$cid]) ? $lastSbidMap[$cid] : ($r->last_sbid ?? $r->apprSbid ?? null);
            $lastSbid = null;
            if ($lastSbidRaw !== null && $lastSbidRaw !== '' && $lastSbidRaw !== '0') {
                $f = is_numeric($lastSbidRaw) ? (float) $lastSbidRaw : null;
                if ($f !== null && $f > 0) {
                    $lastSbid = $f;
                }
            }
            $l1CpcVal = $l1Cpc !== null ? (float) $l1Cpc : 0;
            $l7CpcVal = $l7Cpc !== null ? (float) $l7Cpc : 0;

            $sbid = $this->calculateSbidUtilized($ub7, $ub1, $inv, $bgt, $l1CpcVal, $l7CpcVal, $lastSbid, $price);

            $kwCampaigns[] = [
                'campaign_name' => $r->campaign_name ?? 'N/A',
                'bgt' => $bgt,
                'sbgt' => $sbgt,
                'acos' => $acos,
                'clicks' => $clicks,
                'ad_spend' => $spend,
                'ad_sales' => $sales,
                'ad_sold' => $sold,
                'ad_cvr' => $adCvr,
                '7ub' => $ub7,
                '1ub' => $ub1,
                'l7cpc' => $l7Cpc,
                'l1cpc' => $l1Cpc,
                'l_bid' => $lastSbid,
                'sbid' => $sbid,
            ];
        }

        $ptCampaigns = [];
        if ($itemId) {
            // Match ebay/pmp/ads: prefer COST_PER_SALE row per listing (EbayPMPAdsController)
            $campaignListing = null;
            try {
                $campaignListing = DB::connection('apicentral')
                    ->table('ebay_campaign_ads_listings')
                    ->where('listing_id', $itemId)
                    ->select('listing_id', 'bid_percentage', 'suggested_bid')
                    ->orderByRaw('CASE WHEN funding_strategy = "COST_PER_SALE" THEN 0 ELSE 1 END')
                    ->orderByDesc('id')
                    ->first();
            } catch (\Exception $e) {
                // apicentral may be unavailable
            }
            $cbid = $campaignListing ? (float) ($campaignListing->bid_percentage ?? 0) : null;
            $esBid = $campaignListing ? (float) ($campaignListing->suggested_bid ?? 0) : null;
            $views = $ebayMetric ? (float) ($ebayMetric->views ?? 0) : 0;
            $l7Views = $ebayMetric ? (float) ($ebayMetric->l7_views ?? 0) : 0;
            $ebayL30 = $ebayMetric ? (float) ($ebayMetric->ebay_l30 ?? 0) : 0;
            $scvr = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : null;

            $ptReports = EbayGeneralReport::where('report_range', 'L30')
                ->where('listing_id', $itemId)
                ->get();
            foreach ($ptReports as $r) {
                $ptCampaigns[] = [
                    'campaign_name' => 'PMT - ' . ($r->listing_id ?? 'N/A'),
                    'cbid' => $cbid,
                    'es_bid' => $esBid,
                    't_views' => $views > 0 ? $views : null,
                    'l7_views' => $l7Views,
                    'scvr' => $scvr,
                ];
            }
        }

        return response()->json([
            'kw_campaigns' => $kwCampaigns,
            'pt_campaigns' => $ptCampaigns,
        ]);
    }

    /**
     * SBID calculation – same logic as ebay/utilized (all mode, per-campaign).
     * No SBID when UB7/UB1 colors don't match (utilized formatter).
     * Under-utilized: !over && budget>0 && ub7<66 && ub1<66 && inv>0 (match EbayOverUtilizedBgtController).
     */
    private function calculateSbidUtilized(
        float $ub7,
        float $ub1,
        float $inv,
        float $bgt,
        float $l1Cpc,
        float $l7Cpc,
        ?float $lastSbid,
        float $price
    ): ?float {
        $getUbColor = function (float $ub): string {
            if ($ub >= 66 && $ub <= 99) {
                return 'green';
            }
            if ($ub > 99) {
                return 'pink';
            }
            return 'red';
        };

        if ($getUbColor($ub7) !== $getUbColor($ub1)) {
            return null;
        }

        $sbid = 0.0;
        $over = $ub7 > 99 && $ub1 > 99;
        $under = !$over && $bgt > 0 && $ub7 < 66 && $ub1 < 66 && $inv > 0;

        if ($over) {
            if ($l1Cpc > 1.25) {
                $sbid = floor($l1Cpc * 0.80 * 100) / 100;
            } elseif ($l1Cpc > 0) {
                $sbid = floor($l1Cpc * 0.90 * 100) / 100;
            } elseif ($l7Cpc > 0) {
                $sbid = floor($l7Cpc * 0.90 * 100) / 100;
            } else {
                $sbid = 0.0;
            }
            if ($price < 20 && $sbid > 0.20) {
                $sbid = 0.20;
            }
        } elseif ($under) {
            $baseBid = $lastSbid > 0 ? $lastSbid : ($l1Cpc > 0 ? $l1Cpc : ($l7Cpc > 0 ? $l7Cpc : 0));
            if ($baseBid > 0) {
                if ($ub1 < 33) {
                    $sbid = floor(($baseBid + 0.10) * 100) / 100;
                } elseif ($ub1 >= 33 && $ub1 < 66) {
                    $sbid = floor($baseBid * 1.10 * 100) / 100;
                } else {
                    $sbid = floor($baseBid * 100) / 100;
                }
            } else {
                $sbid = 0.0;
            }
        } else {
            if ($l1Cpc > 0) {
                $sbid = floor($l1Cpc * 0.90 * 100) / 100;
            } elseif ($l7Cpc > 0) {
                $sbid = floor($l7Cpc * 0.90 * 100) / 100;
            } else {
                $sbid = 0.0;
            }
        }

        return $sbid > 0 ? $sbid : null;
    }

    /**
     * Get eBay LMP data for a specific SKU
     * Merges competitors across the Sku Link LMP group so the modal matches the LMP column.
     */
    public function getEbayLmpData(Request $request)
    {
        try {
            $sku = trim((string) $request->input('sku'));
            $linkedSkus = $request->input('linked_lmp_skus', []);
            
            if ($sku === '') {
                return response()->json([
                    'error' => 'SKU is required'
                ], 400);
            }

            if (! is_array($linkedSkus)) {
                $linkedSkus = [];
            }

            // Resolve Sku Link LMP group (same source as /ebay-tabulator-view LMP column).
            $groupSkus = [$sku];
            try {
                $lmpGroupService = new \App\Services\LmpSkuGroupService();
                $seed = array_values(array_filter(array_map(
                    fn ($value) => trim((string) $value),
                    array_merge([$sku], $linkedSkus)
                )));
                $lmpGroupService->prepareForSkus($seed);
                $resolved = $lmpGroupService->groupContaining($sku);
                if (! empty($resolved)) {
                    $groupSkus = $resolved;
                }
            } catch (\Throwable $e) {
                Log::warning('LmpSkuGroupService in getEbayLmpData failed: ' . $e->getMessage());
            }

            $groupSkus = array_values(array_unique(array_filter(array_map(
                fn ($value) => trim((string) $value),
                array_merge($groupSkus, $linkedSkus, [$sku])
            ))));

            // Collect competitors from every linked SKU (not just the opened row).
            $competitors = collect();
            foreach ($groupSkus as $groupSku) {
                foreach (\App\Models\EbaySkuCompetitor::resolveLookupKeys($groupSku) as $lookupSku) {
                    $found = \App\Models\EbaySkuCompetitor::getCompetitorsForSku($lookupSku, 'ebay');
                    if ($found->isNotEmpty()) {
                        $competitors = $competitors->merge($found);
                    }
                }
            }
            $competitors = \App\Models\EbaySkuCompetitor::dedupeByItemId($competitors);

            // Live SerpApi refresh is opt-in (?refresh=1). Default is DB-only so LMP modal
            // opens quickly. Background `ebay:update-sku-prices` keeps prices fresh.
            // We DO NOT overwrite a stored price with a degenerate SerpApi response
            // (total_price <= 0 means listing ended / sold out / no price).
            if ($request->boolean('refresh')) {
                // Live refresh can take a while (1 SerpApi call per competitor).
                @set_time_limit(300);

                $fetcher = app(EbayLivePriceFetcher::class);

                foreach ($competitors as $competitor) {
                    try {
                        $listingId = $fetcher->resolveListingId($competitor->product_link, $competitor->item_id);
                        if (!$listingId) {
                            continue;
                        }

                        $live = $fetcher->fetchByListingId($listingId, $sku, $competitor->product_link);
                        if (!$live) {
                            continue;
                        }

                        $liveTotal = isset($live['total_price']) ? (float) $live['total_price'] : 0.0;
                        if ($liveTotal <= 0) {
                            continue;
                        }

                        $originalItemId = $competitor->item_id;
                        $competitor->update([
                            'item_id' => $listingId,
                            'price' => $live['price'],
                            'shipping_cost' => $live['shipping_cost'],
                            'total_price' => $liveTotal,
                            'product_title' => $live['title'] ?? $competitor->product_title,
                            'product_link' => $live['link'] ?? $competitor->product_link,
                            'image' => $live['image'] ?? $competitor->image,
                        ]);

                        // Best-effort cache sync — must not abort LMP pull on unique conflicts.
                        \App\Models\EbayCompetitorItem::syncLiveListingData(
                            (string) $listingId,
                            $originalItemId !== null ? (string) $originalItemId : null,
                            $live
                        );
                    } catch (\Throwable $e) {
                        Log::warning('getEbayLmpData refresh skipped competitor', [
                            'sku' => $sku,
                            'competitor_id' => $competitor->id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $competitors = $competitors
                    ->map(function ($comp) {
                        try {
                            return $comp->refresh();
                        } catch (\Throwable $e) {
                            return $comp;
                        }
                    });
            }

            $competitors = $competitors
                ->filter(function ($comp) {
                    return (float) ($comp->total_price ?? 0) > 0;
                })
                ->sortBy(function ($comp) { return (float) $comp->total_price; })
                ->values();
            // L1 = lowest non-ignored competitor
            $lowestPrice = $competitors->first(fn ($comp) => empty($comp->ignored));
            
            return response()->json([
                'success' => true,
                'sku' => $sku,
                'competitors' => $competitors->map(function ($comp) {
                    return [
                        'id' => $comp->id,
                        'item_id' => $comp->item_id,
                        'price' => floatval($comp->price ?? 0),
                        'shipping_cost' => floatval($comp->shipping_cost ?? 0),
                        'total_price' => floatval($comp->total_price ?? 0),
                        'ignored' => (bool) ($comp->ignored ?? false),
                        'link' => $comp->product_link,
                        'title' => $comp->product_title,
                        'image' => $comp->image ?? null,
                        'created_at' => $comp->created_at ? $comp->created_at->format('Y-m-d H:i:s') : null,
                    ];
                }),
                'lowest_price' => $lowestPrice ? floatval($lowestPrice->total_price) : null,
                'total_count' => $competitors->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching eBay LMP data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch LMP data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add LMP from competitor data
     */
    public function addEbayLmp(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'item_id' => 'required|string',
                'price' => 'required|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'product_link' => 'nullable|string',
                'product_title' => 'nullable|string',
                'image' => 'nullable|string',
            ]);
            
            $sku = $validated['sku'];
            $fetcher = app(EbayLivePriceFetcher::class);
            $itemId = $fetcher->resolveListingId(
                $validated['product_link'] ?? null,
                $validated['item_id']
            ) ?? $validated['item_id'];
            $price = $validated['price'];
            $shippingCost = $validated['shipping_cost'] ?? 0;
            $totalPrice = $price + $shippingCost;
            
            // Check if this item_id already exists for this SKU
            $exists = \App\Models\EbaySkuCompetitor::where('sku', $sku)
                ->where('item_id', $itemId)
                ->exists();
            
            if ($exists) {
                return response()->json([
                    'error' => 'This eBay item is already added as a competitor for this SKU'
                ], 409);
            }
            
            // Create new LMP entry
            DB::beginTransaction();
            
            $lmp = \App\Models\EbaySkuCompetitor::create([
                'sku' => $sku,
                'item_id' => $itemId,
                'price' => $price,
                'shipping_cost' => $shippingCost,
                'total_price' => $totalPrice,
                'marketplace' => 'ebay',
                'product_link' => $validated['product_link'] ?? null,
                'product_title' => $validated['product_title'] ?? null,
                'image' => $validated['image'] ?? null,
            ]);

            $parent = ProductMaster::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim($sku))])
                ->value('parent');

            LmpCompetitorHistory::logAction(
                sku: $sku,
                action: 'added',
                itemId: $itemId,
                competitorId: (int) $lmp->id,
                productTitle: $validated['product_title'] ?? null,
                totalPrice: $totalPrice,
                parent: $parent ? (string) $parent : null,
                updatedBy: Auth::user()?->name ?? 'N/A',
            );
            
            DB::commit();
            
            Log::info('eBay LMP added successfully', [
                'sku' => $sku,
                'item_id' => $itemId,
                'total_price' => $totalPrice
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'LMP added successfully',
                'data' => [
                    'id' => $lmp->id,
                    'sku' => $lmp->sku,
                    'item_id' => $lmp->item_id,
                    'price' => floatval($lmp->price),
                    'shipping_cost' => floatval($lmp->shipping_cost),
                    'total_price' => floatval($lmp->total_price),
                    'product_link' => $lmp->product_link,
                    'product_title' => $lmp->product_title,
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error adding eBay LMP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to add LMP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing eBay LMP competitor price/link.
     */
    public function updateEbayLmp(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
                'price' => 'required|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'product_link' => 'nullable|string',
                'item_id' => 'nullable|string',
            ]);

            $lmp = \App\Models\EbaySkuCompetitor::find($validated['id']);
            if (!$lmp) {
                return response()->json(['error' => 'LMP entry not found'], 404);
            }

            $price = (float) $validated['price'];
            $shippingCost = array_key_exists('shipping_cost', $validated) && $validated['shipping_cost'] !== null
                ? (float) $validated['shipping_cost']
                : (float) ($lmp->shipping_cost ?? 0);

            DB::beginTransaction();
            $lmp->price = $price;
            $lmp->shipping_cost = $shippingCost;
            $lmp->total_price = $price + $shippingCost;
            if (array_key_exists('product_link', $validated)) {
                $lmp->product_link = $validated['product_link'] ?: null;
            }
            if (!empty($validated['item_id'])) {
                $lmp->item_id = $validated['item_id'];
            }
            $lmp->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'LMP updated successfully',
                'data' => [
                    'id' => $lmp->id,
                    'item_id' => $lmp->item_id,
                    'price' => floatval($lmp->price),
                    'total_price' => floatval($lmp->total_price),
                    'product_link' => $lmp->product_link,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating eBay LMP', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to update LMP: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete LMP entry
     */
    public function deleteEbayLmp(Request $request)
    {
        try {
            $id = $request->input('id');
            $requestItemId = trim((string) $request->input('item_id', ''));
            
            Log::info('Delete eBay LMP request received', [
                'id' => $id,
                'item_id' => $requestItemId,
                'all_input' => $request->all()
            ]);
            
            if (!$id && $requestItemId === '') {
                return response()->json([
                    'error' => 'LMP ID is required'
                ], 400);
            }
            
            $lmp = $id ? \App\Models\EbaySkuCompetitor::find($id) : null;
            if (!$lmp && $requestItemId !== '') {
                $lmp = \App\Models\EbaySkuCompetitor::query()
                    ->where('item_id', $requestItemId)
                    ->orderBy('id')
                    ->first();
            }
            
            if (!$lmp) {
                Log::warning('eBay LMP entry not found', ['id' => $id, 'item_id' => $requestItemId]);
                return response()->json([
                    'error' => 'LMP entry not found'
                ], 404);
            }
            
            DB::beginTransaction();
            
            $sku = $lmp->sku;
            $itemId = trim((string) ($lmp->item_id ?: $requestItemId));
            $totalPrice = $lmp->total_price;

            // LMP modal merges Sku-Link group rows and dedupes by item_id.
            // Deleting only one row lets the same listing reappear from a linked SKU.
            $toDelete = collect([$lmp]);
            if ($itemId !== '') {
                $candidates = \App\Models\EbaySkuCompetitor::query()
                    ->where('item_id', $itemId)
                    ->get();
                $filtered = LmpSkuGroupService::filterRowsToSkuGroup($candidates, (string) $sku);
                $toDelete = $filtered->isNotEmpty() ? $filtered : collect([$lmp]);
            }

            $deletedIds = [];
            foreach ($toDelete as $row) {
                $parent = ProductMaster::query()
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim((string) $row->sku))])
                    ->value('parent');

                LmpCompetitorHistory::logAction(
                    sku: (string) $row->sku,
                    action: 'deleted',
                    itemId: (string) ($row->item_id ?: $itemId),
                    competitorId: (int) $row->id,
                    productTitle: $row->product_title,
                    totalPrice: is_numeric($row->total_price) ? (float) $row->total_price : (is_numeric($totalPrice) ? (float) $totalPrice : null),
                    parent: $parent ? (string) $parent : null,
                    updatedBy: Auth::user()?->name ?? 'N/A',
                );

                $deletedIds[] = (int) $row->id;
                $row->delete();
            }
            
            DB::commit();
            
            Log::info('eBay LMP deleted successfully', [
                'id' => $id,
                'sku' => $sku,
                'item_id' => $itemId,
                'total_price' => $totalPrice,
                'deleted_ids' => $deletedIds,
                'deleted_count' => count($deletedIds),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => count($deletedIds) > 1
                    ? ('LMP deleted successfully (' . count($deletedIds) . ' linked rows)')
                    : 'LMP deleted successfully',
                'deleted_ids' => $deletedIds,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting eBay LMP', [
                'id' => $id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to delete LMP: ' . $e->getMessage()
            ], 500);
        }
    }
}
