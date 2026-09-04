<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\EbayTwoDataView;
use App\Services\Ebay2ApiService;
use App\Services\Ebay1PromotionService;
use App\Services\EbayPushService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\Ebay2GeneralReport;
use App\Models\Ebay2Metric;
use App\Models\Ebay2PriorityReport;
use App\Models\EbayTwoListingStatus;
use App\Models\Ebay2Order;
use App\Models\Ebay2OrderItem;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\EbaySkuCompetitor;
use App\Services\ChannelPromoPricingService;
use App\Services\PefEbayPricePullService;
use App\Services\LmpSkuGroupService;
use App\Support\Marketplace\EbayListingEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use App\Models\AmazonChannelSummary;

class EbayTwoController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function updateEbayPricing(Request $request)
    {

        $service = new Ebay2ApiService();

        $itemID = $request["sku"];
        $newPrice = $request["price"];
        $response = $service->reviseFixedPriceItem(
            itemId: $itemID,
            price: $newPrice,
        );

        return response()->json(['status' => 200, 'data' => $response]);
    }

    public function overallEbay(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage directly from database
        $marketplaceData = MarketplacePercentage::where('marketplace', 'EbayTwo')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;

        return view('market-places.ebayTwoAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayTwoPercentage' => $percentage
        ]);
    }

    public function ebay2TabulatorView(Request $request)
    {
        // Sales / Qty / PFT / COGS / GPFT% / GROI% — all derived from the SAME real-orders
        // rows /ebay2/daily-sales builds (Ebay2SalesController::getData), so every summary
        // badge on this page agrees with that page (the per-SKU datasheet is tax-excluded,
        // lags the Orders API, and only reflects filtered rows, so it can't match).
        $agg = $this->fetchEbay2L30OrdersAggregate();

        // Ads% = TACOS = channel Total Ad Spend ÷ the SAME real-orders L30 sales shown in
        // the Sales badge, so the Ads% stays consistent with this page's Sales.
        $ebayAdSpend = app(ChannelMasterController::class)->getEbaytwoMasterAdSpend();
        $channelAdsPercent = $agg['sales'] > 0
            ? round(($ebayAdSpend / $agg['sales']) * 100, 1)
            : 0.0;

        // NROI% = (GPFT$ − Ad Spend) / COGS × 100 — same shape as Amazon / ebay1 NROI badge
        // (do not cut Ads% from GROI%).
        $ordersL30Nroi = $agg['cogs'] > 0
            ? round((($agg['pft'] - $ebayAdSpend) / $agg['cogs']) * 100, 1)
            : 0.0;

        return view('market-places.ebay2_tabulator_view', [
            'ebayTakeHome'         => MarketplacePercentage::takeHomeDecimal('EbayTwo'),
            'channelAdsPercent'    => $channelAdsPercent,
            'ebayAdSpend'          => round((float) $ebayAdSpend, 2),
            'ordersL30TotalQty'    => $agg['qty'],
            'ordersL30TotalSales'  => $agg['sales'],
            'ordersL30Gpft'        => $agg['gpft'],
            'ordersL30Groi'        => $agg['groi'],
            'ordersL30Pft'         => $agg['pft'],
            'ordersL30Cogs'        => $agg['cogs'],
            'ordersL30Nroi'        => $ordersL30Nroi,
        ]);
    }

    /**
     * L30 Sales / Qty / PFT / COGS / GPFT% / GROI% computed from the exact same rows
     * /ebay2/daily-sales renders (Ebay2SalesController::getData), aggregated the same way
     * that page's summary does — guarantees the tabulator badges match /ebay2/daily-sales.
     */
    private function fetchEbay2L30OrdersAggregate(): array
    {
        $empty = ['sales' => 0.0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0, 'gpft' => 0.0, 'groi' => 0.0];
        try {
            $rows = app(\App\Http\Controllers\Sales\Ebay2SalesController::class)
                ->getData(request())->getData(true);
            if (!is_array($rows)) return $empty;

            $qty = 0; $pft = 0.0; $cogs = 0.0; $l30Sales = 0.0; $orderSales = 0.0;
            $seenOrders = [];
            foreach ($rows as $r) {
                $sku = $r['sku'] ?? '';
                $orderId = $r['order_id'] ?? '';
                if ($sku === '' || $orderId === '') continue;

                if (!isset($seenOrders[$orderId])) {
                    $seenOrders[$orderId] = true;
                    $orderSales += (float) ($r['total_amount'] ?? 0);
                }

                $q = (int) ($r['quantity'] ?? 0);
                $qty += $q;
                $pft += (float) ($r['pft'] ?? 0);
                $cogs += (float) ($r['cogs'] ?? 0);
                $l30Sales += $q * (float) ($r['price'] ?? 0);
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
            \Log::warning('fetchEbay2L30OrdersAggregate failed: ' . $e->getMessage());
            return $empty;
        }
    }

    public function ebay2opTabulatorView(Request $request)
    {
        return view("market-places.ebay2op_tabulator_view", [
            'ebayTakeHome' => MarketplacePercentage::takeHomeDecimal('EbayTwo'),
        ]);
    }

    public function EbayTwoPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage directly from database
        $marketplaceData = MarketplacePercentage::where('marketplace', 'EbayTwo')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        return view('market-places.EbayTwoPricingCvr', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayTwoPercentage' => $percentage
        ]);
    }

    public function getViewEbayData(Request $request)
    {
        // 1. Base ProductMaster fetch
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get()
            ->keyBy("sku");

        // 2. SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Fetch ALL ebay2_metrics (including Open Box items not in product_masters).
        // Key by NBSP / Unicode space–safe normalized SKU: ebay2_metrics.sku can contain
        // non-breaking spaces (U+00A0) while product_masters.sku uses normal spaces, which
        // otherwise breaks the lookup (item_id/price missing → row wrongly shows as Missing L).
        $ebayMetrics = Ebay2Metric::select(EbayListingEnded::withStatusColumn('ebay_2_metrics', [
            'id', 'sku', 'ebay_price', 'ebay_l30', 'ebay_l60', 'views', 'l7_views', 'item_id', 'ebay_stock',
        ]))
            ->orderBy('id')
            ->get()
            ->groupBy(function ($metric) {
                return ShopifySku::normalizeSkuForShopifyLookup($metric->sku);
            })
            ->map(function ($group) {
                return EbayListingEnded::preferLiveMetric($group);
            })
            ->filter();

        // Prior-day Price / INV / OV L30 (California) for green/red/gray trend dots.
        $todayPt = Carbon::now('America/Los_Angeles')->toDateString();
        $priceYesterdayBySku = [];
        $invYesterdayBySku = [];
        $l30YesterdayBySku = [];
        if (Schema::hasTable('ebay2_sku_daily_data')) {
            $latestPriorRows = DB::table('ebay2_sku_daily_data as d')
                ->join(DB::raw('(SELECT sku, MAX(record_date) AS max_date FROM ebay2_sku_daily_data WHERE record_date < ? GROUP BY sku) as x'), function ($join) {
                    $join->on('d.sku', '=', 'x.sku')->on('d.record_date', '=', 'x.max_date');
                })
                ->addBinding($todayPt, 'join')
                ->select('d.sku', 'd.daily_data')
                ->get();
            foreach ($latestPriorRows as $hist) {
                $data = is_array($hist->daily_data ?? null)
                    ? $hist->daily_data
                    : (json_decode($hist->daily_data ?? '{}', true) ?: []);
                $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($hist->sku ?? ''));
                if ($norm === '') {
                    continue;
                }
                $priceYesterdayBySku[$norm] = round((float) ($data['price'] ?? 0), 2);
                if (array_key_exists('ovl30', $data)) {
                    $l30YesterdayBySku[$norm] = (int) $data['ovl30'];
                }
                if (array_key_exists('inv', $data)) {
                    $invYesterdayBySku[$norm] = (int) $data['inv'];
                }
            }
        }
        // Prefer shopifysku_inventory_history.closing_inventory for INV prior-day
        if (Schema::hasTable('shopifysku_inventory_history')) {
            $invHistRows = DB::table('shopifysku_inventory_history as h')
                ->join(DB::raw('(SELECT sku, MAX(snapshot_date) AS max_date FROM shopifysku_inventory_history WHERE snapshot_date < ? GROUP BY sku) as x'), function ($join) {
                    $join->on('h.sku', '=', 'x.sku')->on('h.snapshot_date', '=', 'x.max_date');
                })
                ->addBinding($todayPt, 'join')
                ->select('h.sku', 'h.closing_inventory')
                ->get();
            foreach ($invHistRows as $hist) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($hist->sku ?? ''));
                if ($norm === '') {
                    continue;
                }
                $invYesterdayBySku[$norm] = (int) ($hist->closing_inventory ?? 0);
            }
        }
        
        // Fetch Amazon prices for comparison
        $amazonPrices = AmazonDatasheet::whereIn('sku', $skus)->pluck('price', 'sku');
        
        // Add OPEN BOX and USED items from ebay2_metrics to processing list.
        // Always keep ebay2_metrics.sku casing (original listing SKU); never the uppercase keyBy key.
        $pmKeyByNorm = [];
        foreach ($productMasters->keys() as $pmKey) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $pmKey);
            if ($norm !== '' && ! isset($pmKeyByNorm[$norm])) {
                $pmKeyByNorm[$norm] = (string) $pmKey;
            }
        }
        foreach ($ebayMetrics as $metric) {
            $sku = (string) ($metric->sku ?? '');
            if ($sku === '') {
                continue;
            }

            $skuNorm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            // Skip if already in product masters (case-insensitive)
            if ($skuNorm !== '' && isset($pmKeyByNorm[$skuNorm])) {
                continue;
            }

            // Check if this is OPEN BOX or USED item
            $isOpenBox = stripos($sku, 'OPEN BOX') !== false;
            $isUsed = stripos($sku, 'USED') !== false;

            if ($isOpenBox || $isUsed) {
                // Extract base SKU
                $baseSku = $sku;
                if ($isOpenBox) {
                    $baseSku = trim(str_ireplace('OPEN BOX', '', $baseSku));
                } elseif ($isUsed) {
                    $baseSku = trim(str_ireplace('USED', '', $baseSku));
                }

                $baseNorm = ShopifySku::normalizeSkuForShopifyLookup($baseSku);
                $basePmKey = ($baseNorm !== '' && isset($pmKeyByNorm[$baseNorm]))
                    ? $pmKeyByNorm[$baseNorm]
                    : null;

                // Check if base SKU exists in product masters
                if ($basePmKey !== null) {
                    // Create a pseudo product master entry for this OPEN BOX/USED item
                    $baseProduct = $productMasters[$basePmKey];
                    $pseudoProduct = clone $baseProduct;
                    $pseudoProduct->sku = $sku;
                    $productMasters[$sku] = $pseudoProduct;
                    if ($skuNorm !== '') {
                        $pmKeyByNorm[$skuNorm] = $sku;
                    }
                }
            }
        }

        // OPEN BOX / USED rows use listing SKUs; reload SKU list so EbayTwoDataView & listing status load for them
        $skus = $productMasters->pluck('sku')
            ->filter()
            ->unique()
            ->values()
            ->all();

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

        // PRMT%/CPN%/DSC%/Appr/Push Prc — ebay_two_data_views.value (Amazon-format PEF_* / PUSH_PRC_*)
        $promoChannel = str_contains($request->path(), 'ebay2op') ? 'ebay2op' : 'ebay2';
        $ebayPromoMap = app(ChannelPromoPricingService::class)->mapForSkus($promoChannel, $skus);

        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Forecast Analysis NRP (forecast_analysis.nr) — same as Faire / TikTok pricing.
        $normalizeSkuFa = static fn ($value) => strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));
        $forecastNrpBySku = [];
        $pmKeysForFa = $productMasters->keys()->map(fn ($s) => $normalizeSkuFa($s))->unique()->filter(fn ($s) => $s !== '')->values();
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

        $nrValues = EbayTwoDataView::whereIn("sku", $skus)
            ->get(['sku', 'value'])
            ->mapWithKeys(function ($row) {
                return [strtoupper(trim((string) $row->sku)) => $row->value];
            });
        
        // Fetch listing status data for nr_req field
        // Key listing status by lowercase SKU for case-insensitive lookup (UI sends upper/lower mixed)
        $listingStatusData = EbayTwoListingStatus::whereIn("sku", $skus)
            ->get()
            ->mapWithKeys(function ($item) {
                return [strtolower($item->sku) => $item];
            });

        // Mapping: item_id → sku
        $itemIdToSku = $ebayMetrics->pluck('sku', 'item_id')->toArray();

        // ✅ Fetch L30 Clicks directly from ebay2_general_reports
        $extraClicksData = Ebay2GeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->where('report_range', 'L30')
            ->pluck('clicks', 'listing_id')
            ->toArray();

        // 3b. Fetch KW campaign data from Ebay2PriorityReport
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return trim($sku);
        };

        $kwCampaignReports = Ebay2PriorityReport::whereIn('report_range', ['L30', 'L7', 'L1'])
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        // Build KW campaign map by normalized SKU
        $kwCampaignBySku = [];
        foreach ($kwCampaignReports as $report) {
            $campaignName = $normalizeSku($report->campaign_name ?? '');
            if (empty($campaignName)) continue;

            if (!isset($kwCampaignBySku[$campaignName])) {
                $kwCampaignBySku[$campaignName] = [
                    'campaign_id' => $report->campaign_id ?? '',
                    'campaignBudgetAmount' => $report->campaignBudgetAmount ?? 0,
                    'campaignStatus' => $report->campaignStatus ?? '',
                    'L30' => null, 'L7' => null, 'L1' => null,
                ];
            }

            $range = $report->report_range;
            $kwCampaignBySku[$campaignName][$range] = $report;
        }

        // Fetch last_sbid from day-before-yesterday
        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $lastSbidMap = [];
        $lastSbidReports = Ebay2PriorityReport::where('report_range', $dayBeforeYesterday)
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();
        foreach ($lastSbidReports as $report) {
            if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                $lastSbidMap[$report->campaign_id] = $report->last_sbid;
            }
        }

        // Fetch sbid_m from yesterday or L1
        $sbidMMap = [];
        $sbidMReports = Ebay2PriorityReport::where(function($q) use ($yesterday) {
                $q->where('report_range', $yesterday)->orWhere('report_range', 'L1');
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
        foreach ($sbidMReports as $campaignId => $reports) {
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->sbid_m)) {
                $sbidMMap[$report->campaign_id] = $report->sbid_m;
            }
        }

        // Fetch apprSbid
        $apprSbidMap = [];
        $apprSbidReports = Ebay2PriorityReport::where(function($q) use ($yesterday) {
                $q->where('report_range', $yesterday)->orWhere('report_range', 'L1');
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
        foreach ($apprSbidReports as $campaignId => $reports) {
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->apprSbid)) {
                $apprSbidMap[$report->campaign_id] = $report->apprSbid;
            }
        }

        // 3d. Same prioritization as /ebay2/campaign-ads page: latest COST_PER_SALE row per listing
        // (fallback to overall latest), source is the local `ebay2_campaign_ads` table — the page's
        // own data feed — so ES BID / C BID / PROMOTE / PMT bid fields here mirror that page exactly.
        // Key by string listing_id so PHP int-cast of numeric keys cannot miss ebay_2_metrics.item_id.
        $ebay2CampaignAdsByListing = [];
        try {
            $ebay2CampaignAdsByListing = $this->indexEbay2CampaignAdsByListingId(
                DB::table('ebay2_campaign_ads as t')
                    ->join(DB::raw('(SELECT listing_id,
                                            MAX(CASE WHEN funding_strategy = "COST_PER_SALE" THEN id END) AS max_cps_id,
                                            MAX(id) AS max_id
                                     FROM ebay2_campaign_ads
                                     GROUP BY listing_id) x'),
                        function ($join) {
                            $join->on('t.id', '=', DB::raw('COALESCE(x.max_cps_id, x.max_id)'));
                        })
                    ->select('t.listing_id', 't.bid_percentage', 't.suggested_bid', 't.promote_with_ad')
                    ->get()
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ebay2_campaign_ads unavailable: ' . $e->getMessage());
        }

        // 4. Fetch General Reports (listing_id → sku)
        $generalReports = Ebay2GeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->whereIn('report_range', ['L60', 'L30', 'L7'])
            ->get();

        $adMetricsBySku = [];

        // General Reports
        foreach ($generalReports as $report) {
            $sku = $itemIdToSku[$report->listing_id] ?? null;
            if (!$sku) continue;

            $range = strtoupper($report->report_range);

            $adMetricsBySku[$sku][$range]['GENERAL_SPENT'] =
                ($adMetricsBySku[$sku][$range]['GENERAL_SPENT'] ?? 0) + $this->extractNumber($report->ad_fees);

            $adMetricsBySku[$sku][$range]['Imp'] =
                ($adMetricsBySku[$sku][$range]['Imp'] ?? 0) + (int) $report->impressions;

            $adMetricsBySku[$sku][$range]['Clk'] =
                ($adMetricsBySku[$sku][$range]['Clk'] ?? 0) + (int) $report->clicks;

            $adMetricsBySku[$sku][$range]['Ctr'] =
                ($adMetricsBySku[$sku][$range]['Ctr'] ?? 0) + (float) $report->ctr;

            $adMetricsBySku[$sku][$range]['Sls'] =
                ($adMetricsBySku[$sku][$range]['Sls'] ?? 0) + (int) $report->sales;
        }

        // Same take-home as eBay 1 (MarketplacePercentage Ebay / 100). Ads stay eBay 2.
        $percentage = $this->ebay1StyleTakeHomePercent();
        $pmtAds = 0; // No PMT ads updates tracking for eBay2

        // 5b. Read PMT-specific percentage from DB (matching Ebay2PMTAdController)
        $pmtMarketplaceData = \App\Models\MarketplacePercentage::where('marketplace', 'Ebay2')->first();
        $pmtPercentage = $pmtMarketplaceData ? ($pmtMarketplaceData->percentage / 100) : 1;
        $pmtAdPercentage = $pmtMarketplaceData ? ($pmtMarketplaceData->ad_updates / 100) : 0;

        $lmpLookups = EbaySkuCompetitor::buildGroupedLookup('ebay');
        $lmpDetailsLookup = $lmpLookups['details'];

        // Sku Link LMP — same shared lmp_sku_links groups as /ebay-tabulator-view
        $lmpGroupService = new LmpSkuGroupService();
        try {
            $prepSkus = $productMasters->pluck('sku')->filter()->map(fn ($s) => (string) $s)->all();
            foreach ($ebayMetrics as $metricSku => $_metric) {
                $prepSkus[] = (string) $metricSku;
            }
            $lmpGroupService->prepareForSkus($prepSkus);
        } catch (\Throwable $e) {
            Log::warning('LmpSkuGroupService prepare failed (eBay2): ' . $e->getMessage());
        }

        $resolveLinkedLmpSkus = static function (string $sku) use ($lmpGroupService): array {
            $sku = trim($sku);
            if ($sku === '') {
                return [];
            }
            try {
                $linkedGroup = $lmpGroupService->groupContaining($sku);
            } catch (\Throwable $e) {
                $linkedGroup = [];
            }
            if (empty($linkedGroup)) {
                $linkedGroup = [$sku];
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

            return $linkedLmpSkus;
        };

        // 6. Build Result
        $result = [];

        $resolveProductMasterKey = static function (string $candidate) use ($productMasters): ?string {
            if ($productMasters->has($candidate)) {
                return $candidate;
            }
            $found = $productMasters->keys()->first(function ($k) use ($candidate) {
                return strcasecmp((string) $k, $candidate) === 0;
            });

            return $found !== null ? (string) $found : null;
        };

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData->get($pm->sku);
            $ebayMetric = $ebayMetrics[ShopifySku::normalizeSkuForShopifyLookup($pm->sku)] ?? null;
            // Try both lowercase and original case for listing status lookup
            $listingStatus = $listingStatusData[strtolower($pm->sku)] ?? $listingStatusData[$pm->sku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;
            $row['base_sku'] = '';
            $row['base_inv'] = 0;
            if (stripos((string) $pm->sku, 'OPEN BOX') !== false) {
                $baseCandidate = trim(str_ireplace('OPEN BOX', '', (string) $pm->sku));
                if ($baseCandidate !== '') {
                    $pmKey = $resolveProductMasterKey($baseCandidate);
                    if ($pmKey !== null) {
                        $row['base_sku'] = (string) $productMasters[$pmKey]->sku;
                        $baseShopify = $shopifyData->get($pmKey) ?? ShopifySku::firstForProductSku($pmKey);
                        $row['base_inv'] = $baseShopify ? (float) ($baseShopify->inv ?? 0) : 0;
                    }
                }
            }
            $row['fba'] = $pm->fba;

            // Shopify
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;
            $pmNormInv = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
            $row['inv_yesterday'] = $invYesterdayBySku[$pmNormInv] ?? null;
            $row['l30_yesterday'] = $l30YesterdayBySku[$pmNormInv] ?? null;
            
            // NR/REQ status from listing status
            if ($listingStatus) {
                $statusValue = is_array($listingStatus->value) ? $listingStatus->value : json_decode($listingStatus->value, true);
                $row['nr_req'] = $statusValue['nr_req'] ?? 'REQ';
                $row['B Link'] = $statusValue['buyer_link'] ?? '';
                $row['S Link'] = $statusValue['seller_link'] ?? '';
            } else {
                $row['nr_req'] = 'REQ';
                $row['B Link'] = '';
                $row['S Link'] = '';
            }

            // eBay2 Metrics
            $row["eBay L30"] = $ebayMetric->ebay_l30 ?? 0;
            $row["eBay L60"] = $ebayMetric->ebay_l60 ?? 0;
            $row["eBay Price"] = $ebayMetric->ebay_price ?? 0;
            $row = array_merge($row, EbayListingEnded::fields($ebayMetric));
            $pmNorm = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
            $row['price_yesterday'] = $priceYesterdayBySku[$pmNorm] ?? null;
            $row['views'] = $ebayMetric->views ?? 0;
            $row['l7_views'] = $ebayMetric->l7_views ?? 0;
            $row['eBay_item_id'] = $ebayMetric->item_id ?? null;
            $row['E Stock'] = $ebayMetric->ebay_stock ?? 0;
            
            // Amazon Price for comparison
            $row['A Price'] = isset($amazonPrices[$pm->sku]) ? floatval($amazonPrices[$pm->sku]) : 0;

            // LMP — merged across the Sku Link LMP group so linked SKUs share LMP (same as /ebay-tabulator-view).
            $linkedLmpSkus = $resolveLinkedLmpSkus((string) $pm->sku);
            EbaySkuCompetitor::applyLinkedGroupToRow(
                $row,
                (string) $pm->sku,
                $lmpDetailsLookup,
                $linkedLmpSkus,
                $row['base_sku'] ?: null
            );

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

            // Site-specific promo columns (PRMT%/CPN%/DSC%/Appr/Push Prc)
            $row = app(ChannelPromoPricingService::class)->applyToRow($row, $ebayPromoMap, (string) $pm->sku);

            $ebayL30ForDil = floatval($row["eBay L30"] ?? 0);
            $viewsForDil = floatval($row['views'] ?? 0);
            $row["E Dil%"] = $viewsForDil > 0
                ? round(($ebayL30ForDil / $viewsForDil) * 100, 2)
                : 0;

            // Ad Metrics (only GENERAL from ebay2_general_reports)
            $pmtData = $adMetricsBySku[$sku] ?? [];
            foreach (['L60', 'L30', 'L7'] as $range) {
                $metrics = $pmtData[$range] ?? [];
                foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT'] as $suffix) {
                    $key = "Pmt{$suffix}{$range}";
                    $row[$key] = $metrics[$suffix] ?? 0;
                }
            }

            // ✅ Merge Extra Clicks (L30 only)
            if ($ebayMetric && isset($extraClicksData[$ebayMetric->item_id])) {
                $row["PmtClkL30"] += (int) $extraClicksData[$ebayMetric->item_id];
            }

            // Calculate AD_Spend_L30 from GENERAL_SPENT (L30)
            $pmt_spend_l30 = $adMetricsBySku[$sku]['L30']['GENERAL_SPENT'] ?? 0;
            $row["AD_Spend_L30"] = round($pmt_spend_l30, 2);
            $row["spend_l30"] = round($pmt_spend_l30, 2);
            $row["pmt_spend_L30"] = round($pmt_spend_l30, 2);

            // KW Campaign Data
            $normalizedSku = $normalizeSku($pm->sku);
            $kwData = $kwCampaignBySku[$normalizedSku] ?? null;
            if ($kwData) {
                $kwCampaignId = $kwData['campaign_id'];
                $row['kw_campaign_id'] = $kwCampaignId;
                $row['kw_campaignBudgetAmount'] = (float) $kwData['campaignBudgetAmount'];
                $row['kw_campaignStatus'] = $kwData['campaignStatus'];

                $matchedL30 = $kwData['L30'];
                $matchedL7 = $kwData['L7'];
                $matchedL1 = $kwData['L1'];

                $kw_spend_l30 = $matchedL30 ? (float) str_replace(['USD ', ','], '', $matchedL30->cpc_ad_fees_payout_currency ?? '0') : 0;
                $kw_sales_l30 = $matchedL30 ? (float) str_replace(['USD ', ','], '', $matchedL30->cpc_sale_amount_payout_currency ?? '0') : 0;
                $kw_clicks_l30 = $matchedL30 ? (int) ($matchedL30->cpc_clicks ?? 0) : 0;
                $kw_ad_sold = $matchedL30 ? (int) ($matchedL30->cpc_attributed_sales ?? 0) : 0;

                $row['kw_spend_L30'] = round($kw_spend_l30, 2);
                $row['kw_clicks'] = $kw_clicks_l30;
                $row['kw_ad_sold'] = $kw_ad_sold;
                $row['kw_acos'] = $kw_sales_l30 > 0 ? round(($kw_spend_l30 / $kw_sales_l30) * 100, 2) : ($kw_spend_l30 > 0 ? 100 : 0);
                $row['kw_cvr'] = $kw_clicks_l30 > 0 ? round(($kw_ad_sold / $kw_clicks_l30) * 100, 2) : 0;

                $row['kw_l7_spend'] = $matchedL7 ? round((float) str_replace(['USD ', ','], '', $matchedL7->cpc_ad_fees_payout_currency ?? '0'), 2) : 0;
                $row['kw_l7_cpc'] = $matchedL7 ? round((float) str_replace(['USD ', ','], '', $matchedL7->cost_per_click ?? '0'), 2) : 0;
                $row['kw_l1_spend'] = $matchedL1 ? round((float) str_replace(['USD ', ','], '', $matchedL1->cpc_ad_fees_payout_currency ?? '0'), 2) : 0;
                $row['kw_l1_cpc'] = $matchedL1 ? round((float) str_replace(['USD ', ','], '', $matchedL1->cost_per_click ?? '0'), 2) : 0;

                $row['kw_last_sbid'] = $lastSbidMap[$kwCampaignId] ?? '';
                $row['kw_sbid_m'] = $sbidMMap[$kwCampaignId] ?? '';
                $row['kw_apprSbid'] = $apprSbidMap[$kwCampaignId] ?? '';
            } else {
                $row['kw_campaign_id'] = '';
                $row['kw_campaignBudgetAmount'] = 0;
                $row['kw_campaignStatus'] = '';
                $row['kw_spend_L30'] = 0;
                $row['kw_clicks'] = 0;
                $row['kw_ad_sold'] = 0;
                $row['kw_acos'] = 0;
                $row['kw_cvr'] = 0;
                $row['kw_l7_spend'] = 0;
                $row['kw_l7_cpc'] = 0;
                $row['kw_l1_spend'] = 0;
                $row['kw_l1_cpc'] = 0;
                $row['kw_last_sbid'] = '';
                $row['kw_sbid_m'] = '';
                $row['kw_apprSbid'] = '';
            }

            // NRL from data view — also drives nr_req for Missing L (same as /listing-ebaytwo)
            $row['NRL'] = '';
            $dataViewSkuKey = strtoupper(trim((string) $pm->sku));
            if ($nrValues->has($dataViewSkuKey)) {
                $raw = $nrValues->get($dataViewSkuKey);
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL'] = $raw['NRL'] ?? '';
                }
            }
            $row['nr_req'] = \App\Support\Marketplace\EbayTwoListingCounts::nrReqFromDataView(
                $nrValues->has($dataViewSkuKey) ? $nrValues->get($dataViewSkuKey) : null
            );

            // PMT + ES BID / C BID / PROMOTE — same local ebay2_campaign_ads source as /ebay2/campaign-ads.
            // Matched by listing_id (= ebay_2_metrics.item_id, i.e. SKU-wise via the metric row).
            // Rows whose SKU has no campaign-ads record stay visible with nulls — formatter renders '—'.
            $caRow = $this->lookupEbay2CampaignAd($ebay2CampaignAdsByListing, $ebayMetric->item_id ?? null);
            $row['bid_percentage'] = $caRow?->bid_percentage;
            $row['suggested_bid'] = $caRow?->suggested_bid;
            $row = array_merge($row, $this->ebay2CampaignAdsFields($caRow));

            // PMT clicks
            $row['pmt_clicks_l30'] = $row['PmtClkL30'] ?? 0;
            $row['pmt_clicks_l7'] = $row['PmtClkL7'] ?? 0;

            $row["AD_Sales_L30"] = 0;
            $row["AD_Units_L30"] = 0;

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

            // Same normal ship as eBay 1 (Values['ship']), not ebay2_ship
            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // Price and units for calculations
            $price = floatval($row["eBay Price"] ?? 0);
            $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);
            $row["PmtClkL30"] = $adMetricsBySku[$sku]['L30']['Clk'] ?? 0;
            
            // Calculate AD% = (AD Spend L30 / (Price * eBay L30)) * 100
            $totalRevenue = $price * $units_ordered_l30;
            $row["AD%"] = $totalRevenue > 0 ? round(($pmt_spend_l30 / $totalRevenue) * 100, 4) : 0;
            
            // Profit/Sales
            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["Profit"] = $row["Total_pft"]; // Add for frontend compatibility
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["Sales L30"] = $row["T_Sale_l30"]; // Add for frontend compatibility
            
            // Calculate TacosL30 = AD Spend L30 / Total Sales L30
            $row["TacosL30"] = $row["T_Sale_l30"] > 0 ? round($pmt_spend_l30 / $row["T_Sale_l30"], 4) : 0;
            
            // Calculate GPFT% = ((Price * $percentage - Ship - LP) / Price) * 100
            $gpft = $price > 0 ? (($price * $percentage - $ship - $lp) / $price) * 100 : 0;
            $row["GPFT%"] = round($gpft, 2);
            
            // PFT% = GPFT% - AD%
            $row["PFT %"] = round($gpft - $row["AD%"], 2);
            
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );
            
            // Calculate SCVR = (eBay L30 / views) * 100, CVR_45, CVR_60
            $views = floatval($row['views'] ?? 0);
            $ebayL30 = floatval($row["eBay L30"] ?? 0);
            $ebayL60 = floatval($row["eBay L60"] ?? 0);
            $row['SCVR'] = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0;
            $row["eBay L45"] = round(($ebayL30 + $ebayL60) / 2, 2);
            $ebayL45 = $row["eBay L45"];
            $row['CVR_45'] = $views > 0 ? round(($ebayL45 / $views) * 100, 2) : 0;
            $row['CVR_60'] = $views > 0 ? round(($ebayL60 / $views) * 100, 2) : 0;
            
            $row["percentage"] = $percentage;
            $row["pmt_ads"] = $pmtAds;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;
            // Keep column key for UI; value is normal ship (same as eBay 1)
            $row["ebay2_ship"] = $ship;

            // PMT-specific PFT/ROI — same normal ship as eBay 1
            $pmtShip = $ship;
            $row["pmt_pft_val"] = round(
                $price > 0 ? (($price * $pmtPercentage - $lp - $pmtShip) / $price) : 0,
                2
            );
            $row["pmt_roi_val"] = round(
                $lp > 0 ? (($price * $pmtPercentage - $lp - $pmtShip) / $lp) : 0,
                2
            );
            $cbid = floatval($row['bid_percentage'] ?? 0);
            $row["pmt_tpft_val"] = round($row["pmt_pft_val"] + $pmtAdPercentage - $cbid, 2);
            $row["pmt_troi_val"] = round($row["pmt_roi_val"] + $pmtAdPercentage - $cbid, 2);
            $row["pmt_ad_percentage"] = $pmtAdPercentage;

            // NR & Hide
            $row['NR'] = "";
            $row['SPRICE'] = null;
            $row['SGPFT'] = null;
            $row['SPFT'] = null;
            $row['SROI'] = null;
            $row['SGROI'] = null;
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;
            if ($nrValues->has($dataViewSkuKey)) {
                $raw = $nrValues->get($dataViewSkuKey);
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $spriceSaved = floatval($raw['SPRICE'] ?? 0);
                    if ($spriceSaved > 0) {
                        $row['SGPFT'] = round((($spriceSaved * $percentage - $ship - $lp) / $spriceSaved) * 100, 2);
                        $row['SGROI'] = $lp > 0
                            ? round((($spriceSaved * $percentage - $lp - $ship) / $lp) * 100, 2)
                            : 0;
                        $row['SROI'] = $row['SGROI'];
                        $row['SPFT'] = $row['SGPFT'];
                    }
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // Image
            $row["image_path"] = $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null));

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

        // Add Open Box and other items from ebay2_metrics that don't exist in product_masters.
        // $ebayMetrics is keyed by uppercase-normalized SKU — never expose that key as (Child) sku.
        // Compare case-insensitively so "… WoB OPEN BOX" already in $result is not re-added as "… WOB OPEN BOX".
        $processedSkusNorm = [];
        foreach ($result as $existingRow) {
            $existingSku = is_object($existingRow)
                ? ($existingRow->{'(Child) sku'} ?? '')
                : ($existingRow['(Child) sku'] ?? '');
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $existingSku);
            if ($norm !== '') {
                $processedSkusNorm[$norm] = true;
            }
        }
        foreach ($ebayMetrics as $metricNormKey => $metric) {
            $originalSku = trim((string) ($metric->sku ?? ''));
            $displaySku = $originalSku !== '' ? $originalSku : (string) $metricNormKey;
            $normKey = ShopifySku::normalizeSkuForShopifyLookup($displaySku);
            if ($normKey === '' || isset($processedSkusNorm[$normKey])) {
                continue;
            }
            $processedSkusNorm[$normKey] = true;

                // This SKU exists in ebay2_metrics but not in product_masters (e.g., Open Box items)
                $row = [];
                $row["Parent"] = "";
                $row["(Child) sku"] = $displaySku;
                $row['base_sku'] = '';
                $row['base_inv'] = 0;
                if (stripos($displaySku, 'OPEN BOX') !== false) {
                    $baseCandidate = trim(str_ireplace('OPEN BOX', '', $displaySku));
                    if ($baseCandidate !== '') {
                        $pmKey = $resolveProductMasterKey($baseCandidate);
                        if ($pmKey !== null) {
                            $row['base_sku'] = (string) $productMasters[$pmKey]->sku;
                            $baseShopify = $shopifyData->get($pmKey) ?? ShopifySku::firstForProductSku($pmKey);
                            $row['base_inv'] = $baseShopify ? (float) ($baseShopify->inv ?? 0) : 0;
                        }
                    }
                }
                $row['fba'] = "";
                $row["INV"] = 0;
                $row["L30"] = 0;
                $row['inv_yesterday'] = $invYesterdayBySku[$normKey] ?? null;
                $row['l30_yesterday'] = $l30YesterdayBySku[$normKey] ?? null;
                $row['nr_req'] = 'REQ';
                $row['B Link'] = '';
                $row['S Link'] = '';
                
                // eBay2 Metrics from ebay_2_metrics
                $row["eBay L30"] = $metric->ebay_l30 ?? 0;
                $row["eBay L60"] = $metric->ebay_l60 ?? 0;
                $row["eBay Price"] = $metric->ebay_price ?? 0;
                $row = array_merge($row, EbayListingEnded::fields($metric));
                $row['price_yesterday'] = $priceYesterdayBySku[$normKey] ?? null;
                $row['views'] = $metric->views ?? 0;
                $row['l7_views'] = $metric->l7_views ?? 0;
                $row['eBay_item_id'] = $metric->item_id ?? null;
                $row['E Stock'] = $metric->ebay_stock ?? 0;

                EbaySkuCompetitor::applyLinkedGroupToRow(
                    $row,
                    $displaySku,
                    $lmpDetailsLookup,
                    $resolveLinkedLmpSkus($displaySku),
                    $row['base_sku'] ?: null
                );

                $ebayL30ForDilM = floatval($row["eBay L30"] ?? 0);
                $viewsForDilM = floatval($row['views'] ?? 0);
                $row["E Dil%"] = $viewsForDilM > 0
                    ? round(($ebayL30ForDilM / $viewsForDilM) * 100, 2)
                    : 0;
                
                // Initialize ad metrics
                foreach (['L60', 'L30', 'L7'] as $range) {
                    foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT'] as $suffix) {
                        $key = "Pmt{$suffix}{$range}";
                        $row[$key] = 0;
                    }
                }
                
                $row["AD_Spend_L30"] = 0;
                $row["spend_l30"] = 0;
                $row["pmt_spend_L30"] = 0;
                $row["kw_spend_L30"] = 0;
                $row["AD_Sales_L30"] = 0;
                $row["AD_Units_L30"] = 0;

                // KW campaign defaults
                $row['kw_campaign_id'] = '';
                $row['kw_campaignBudgetAmount'] = 0;
                $row['kw_campaignStatus'] = '';
                $row['kw_clicks'] = 0;
                $row['kw_ad_sold'] = 0;
                $row['kw_acos'] = 0;
                $row['kw_cvr'] = 0;
                $row['kw_l7_spend'] = 0;
                $row['kw_l7_cpc'] = 0;
                $row['kw_l1_spend'] = 0;
                $row['kw_l1_cpc'] = 0;
                $row['kw_last_sbid'] = '';
                $row['kw_sbid_m'] = '';
                $row['kw_apprSbid'] = '';
                $row['NRL'] = '';

                // PMT detail defaults
                $row['bid_percentage'] = null;
                $row['suggested_bid'] = null;
                $row['pmt_clicks_l30'] = 0;
                $row['pmt_clicks_l7'] = 0;

                // Campaign-Ads — same listing_id = item_id lookup as product-master SKUs.
                $row = array_merge($row, $this->ebay2CampaignAdsFields(
                    $this->lookupEbay2CampaignAd($ebay2CampaignAdsByListing, $metric->item_id ?? null)
                ));
                
                $price = floatval($row["eBay Price"] ?? 0);
                $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);
                $row["AD%"] = 0;
                $row["Total_pft"] = 0;
                $row["Profit"] = 0;
                $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
                $row["Sales L30"] = $row["T_Sale_l30"];
                $row["TacosL30"] = 0;
                $row["GPFT%"] = 0;
                $row["PFT %"] = 0;
                $row["ROI%"] = 0;
                $row['SCVR'] = 0;
                $row['CVR_45'] = 0;
                $row['CVR_60'] = 0;
                $row["eBay L45"] = 0;
                $row["percentage"] = $percentage;
                $row["pmt_ads"] = 0;
                $row["LP_productmaster"] = 0;
                $row["Ship_productmaster"] = 0;
                $row["ebay2_ship"] = 0;
                $row["pmt_pft_val"] = 0;
                $row["pmt_roi_val"] = 0;
                $row["pmt_tpft_val"] = 0;
                $row["pmt_troi_val"] = 0;
                $row["pmt_ad_percentage"] = $pmtAdPercentage;
                $row['NR'] = "";
                $row['SPRICE'] = null;
                $row['SGPFT'] = null;
                $row['SPFT'] = null;
                $row['SROI'] = null;
                $row['SGROI'] = null;
                $row['Listed'] = null;
                $row['Live'] = null;
                $row['APlus'] = null;
                $row["image_path"] = null;

                $faRecNrpOb = $forecastNrpBySku[$normalizeSkuFa($displaySku)] ?? null;
                $nrpOutOb = '';
                if ($faRecNrpOb && $faRecNrpOb->nr !== null && trim((string) $faRecNrpOb->nr) !== '') {
                    $nrpOutOb = strtoupper(trim((string) $faRecNrpOb->nr));
                    if (! in_array($nrpOutOb, ['REQ', 'NR', 'LATER'], true)) {
                        $nrpOutOb = 'REQ';
                    }
                }
                $row['nrp'] = $nrpOutOb;

                $dvKeyOrphan = $normKey;
                if ($nrValues->has($dvKeyOrphan)) {
                    $rawOb = $nrValues->get($dvKeyOrphan);
                    if (! is_array($rawOb)) {
                        $rawOb = json_decode($rawOb, true);
                    }
                    if (is_array($rawOb)) {
                        $row['NR'] = $rawOb['NR'] ?? $row['NR'];
                        $row['SPRICE'] = $rawOb['SPRICE'] ?? null;
                        $row['SGPFT'] = $rawOb['SGPFT'] ?? null;
                        $row['SPFT'] = $rawOb['SPFT'] ?? null;
                        $row['SROI'] = $rawOb['SROI'] ?? null;
                        $row['SGROI'] = $rawOb['SGROI'] ?? null;
                        $row['Listed'] = isset($rawOb['Listed']) ? filter_var($rawOb['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                        $row['Live'] = isset($rawOb['Live']) ? filter_var($rawOb['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                        $row['APlus'] = isset($rawOb['APlus']) ? filter_var($rawOb['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                        if (! empty($rawOb['NRL'] ?? '')) {
                            $row['NRL'] = $rawOb['NRL'];
                        }
                    }
                }
                // Missing L / nr_req — same source as /listing-ebaytwo
                $row['nr_req'] = \App\Support\Marketplace\EbayTwoListingCounts::nrReqFromDataView(
                    $nrValues->has($dvKeyOrphan) ? $nrValues->get($dvKeyOrphan) : null
                );

                $result[] = (object) $row;
        }

        // PARENT rows: aggregate child INV/L30/views/price (same idea as /ebay3-tabulator-view)
        // so Parents view is not empty under default INV > 0. Also create missing PARENT * rows.
        if (! $request->boolean('open_box_only')) {
            $result = $this->ensureEbay2ParentPrefixRows($result, $percentage);
        }

        // AD% = channel-level Ads% (same as /all-marketplace-master), every row identical
        $channelAdsPct = app(ChannelMasterController::class)->getEbaytwoMasterAdsPercent();
        foreach ($result as $row) {
            if (! is_object($row)) {
                continue;
            }
            $row->{'AD%'} = $channelAdsPct;
            $gpft = (float) ($row->{'GPFT%'} ?? 0);
            $row->{'PFT %'} = round($gpft - $channelAdsPct, 2);
            if (isset($row->SGPFT) && $row->SGPFT !== null && $row->SGPFT !== '') {
                $row->SPFT = round((float) $row->SGPFT - $channelAdsPct, 2);
                $sprice = (float) ($row->SPRICE ?? 0);
                $lp = (float) ($row->LP_productmaster ?? 0);
                $ship = (float) ($row->Ship_productmaster ?? 0);
                $pct = (float) ($row->percentage ?? 0);
                if ($pct <= 0) {
                    $pct = $this->ebay1StyleTakeHomePercent();
                }
                if ($sprice > 0 && $lp > 0) {
                    $grossPft = ($sprice * $pct) - $ship - $lp;
                    $row->SGROI = round(($grossPft / $lp) * 100, 2);
                    $adSpend = $sprice * ($channelAdsPct / 100);
                    $row->SROI = round((($grossPft - $adSpend) / $lp) * 100, 2);
                }
            }
        }

        // Auto-save daily summary in background (non-blocking); skip for filtered views
        if (! $request->boolean('open_box_only')) {
            $this->saveDailySummaryIfNeeded($result);
        }

        if ($request->boolean('open_box_only')) {
            $result = array_values(array_filter($result, function ($row) {
                $sku = is_object($row)
                    ? ($row->{'(Child) sku'} ?? '')
                    : ($row['(Child) sku'] ?? '');

                return stripos((string) $sku, 'OPEN BOX') !== false;
            }));
        }

        return response()->json([
            "message" => "eBay2 Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }

    /**
     * Ensure every parent group has a "PARENT {key}" row with child aggregates
     * (INV, L30, eBay L30/L60, views, avg price/LP/ship, derived CVR/GPFT/ROI),
     * matching /ebay3-tabulator-view parent-row behavior. Flat list (no _children tree).
     *
     * @param  array<int, object|array<string, mixed>>  $result
     * @return array<int, object>
     */
    private function ensureEbay2ParentPrefixRows(array $result, float $percentage): array
    {
        if ($percentage <= 0) {
            $percentage = MarketplacePercentage::takeHomeDecimal('EbayTwo');
        }

        $rows = [];
        foreach ($result as $row) {
            $rows[] = is_object($row) ? (array) $row : $row;
        }

        $parentRowsByKey = []; // UPPER(key) => row
        $parentKeyDisplay = []; // UPPER(key) => display key
        $childRowsByParent = []; // UPPER(key) => child rows
        $passthrough = [];

        foreach ($rows as $row) {
            $sku = (string) ($row['(Child) sku'] ?? '');
            $parentValue = trim((string) ($row['Parent'] ?? ''));

            if (stripos($sku, 'PARENT') !== false) {
                $key = $parentValue !== ''
                    ? $parentValue
                    : trim((string) preg_replace('/^PARENT\s+/i', '', $sku));
                if ($key === '') {
                    $passthrough[] = $row;
                    continue;
                }
                $ukey = strtoupper($key);
                $row['Parent'] = $key;
                $row['(Child) sku'] = 'PARENT ' . $key;
                $row['is_parent_row'] = true;
                $parentRowsByKey[$ukey] = $row;
                $parentKeyDisplay[$ukey] = $key;
                continue;
            }

            if ($parentValue !== '') {
                $ukey = strtoupper($parentValue);
                if (! isset($childRowsByParent[$ukey])) {
                    $childRowsByParent[$ukey] = [];
                }
                $childRowsByParent[$ukey][] = $row;
                if (! isset($parentKeyDisplay[$ukey])) {
                    $parentKeyDisplay[$ukey] = $parentValue;
                }
            } else {
                $passthrough[] = $row;
            }
        }

        $aggregateChildren = static function (array $children) use ($percentage): array {
            $inv = 0.0;
            $l30 = 0.0;
            $ebayL30 = 0.0;
            $ebayL60 = 0.0;
            $eStock = 0.0;
            $views = 0.0;
            $l7Views = 0.0;
            $totalPrice = 0.0;
            $priceCount = 0;
            $totalLp = 0.0;
            $totalShip = 0.0;
            $lpCount = 0;
            $imagePath = null;

            foreach ($children as $child) {
                $inv += (float) ($child['INV'] ?? 0);
                $l30 += (float) ($child['L30'] ?? 0);
                $ebayL30 += (float) ($child['eBay L30'] ?? 0);
                $ebayL60 += (float) ($child['eBay L60'] ?? 0);
                $eStock += (float) ($child['E Stock'] ?? 0);
                $views += (float) ($child['views'] ?? 0);
                $l7Views += (float) ($child['l7_views'] ?? 0);

                $price = (float) ($child['eBay Price'] ?? 0);
                if ($price > 0) {
                    $totalPrice += $price;
                    $priceCount++;
                }

                $lp = (float) ($child['LP_productmaster'] ?? 0);
                $ship = (float) ($child['Ship_productmaster'] ?? $child['ebay2_ship'] ?? 0);
                if ($lp > 0) {
                    $totalLp += $lp;
                    $totalShip += $ship;
                    $lpCount++;
                }

                if ($imagePath === null && ! empty($child['image_path'])) {
                    $imagePath = $child['image_path'];
                }
            }

            $avgPrice = $priceCount > 0 ? round($totalPrice / $priceCount, 2) : 0.0;
            $avgLp = $lpCount > 0 ? round($totalLp / $lpCount, 2) : 0.0;
            $avgShip = $lpCount > 0 ? round($totalShip / $lpCount, 2) : 0.0;
            $ebayL45 = round(($ebayL30 + $ebayL60) / 2, 2);
            $salesL30 = round($avgPrice * $ebayL30, 2);
            $gpft = $avgPrice > 0
                ? (($avgPrice * $percentage - $avgShip - $avgLp) / $avgPrice) * 100
                : 0.0;
            $roi = $avgLp > 0
                ? (($avgPrice * $percentage - $avgLp - $avgShip) / $avgLp) * 100
                : 0.0;
            $profit = round(($avgPrice * $percentage - $avgLp - $avgShip) * $ebayL30, 2);

            return [
                'INV' => $inv,
                'L30' => $l30,
                'eBay L30' => $ebayL30,
                'eBay L60' => $ebayL60,
                'eBay L45' => $ebayL45,
                'E Stock' => $eStock,
                'views' => $views,
                'l7_views' => $l7Views,
                'eBay Price' => $avgPrice,
                'LP_productmaster' => $avgLp,
                'Ship_productmaster' => $avgShip,
                'ebay2_ship' => $avgShip,
                'SCVR' => $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0,
                'CVR_45' => $views > 0 ? round(($ebayL45 / $views) * 100, 2) : 0,
                'CVR_60' => $views > 0 ? round(($ebayL60 / $views) * 100, 2) : 0,
                'E Dil%' => $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0,
                'T_Sale_l30' => $salesL30,
                'Sales L30' => $salesL30,
                'Total_pft' => $profit,
                'Profit' => $profit,
                'GPFT%' => round($gpft, 2),
                'ROI%' => round($roi, 2),
                'percentage' => $percentage,
                'image_path' => $imagePath,
            ];
        };

        $pickChildCampaignAds = static function (array $children): array {
            $picked = null;
            foreach ($children as $child) {
                $has = ($child['ca_bid_percentage'] ?? null) !== null
                    || ($child['ca_suggested_bid'] ?? null) !== null
                    || ! empty($child['ca_promote_with_ad']);
                if (! $has) {
                    continue;
                }
                if (($child['ca_promote_with_ad'] ?? '') === 'AD_ALREADY_CREATED') {
                    $picked = $child;
                    break;
                }
                if ($picked === null) {
                    $picked = $child;
                }
            }
            if ($picked === null) {
                return [];
            }

            $fields = [
                'ca_bid_percentage' => $picked['ca_bid_percentage'] ?? null,
                'ca_suggested_bid' => $picked['ca_suggested_bid'] ?? null,
                'ca_promote_with_ad' => $picked['ca_promote_with_ad'] ?? null,
            ];
            if (empty($picked['eBay_item_id'] ?? null)) {
                return $fields;
            }
            $fields['eBay_item_id'] = $picked['eBay_item_id'];

            return $fields;
        };

        $applyAggToParent = static function (array $parentRow, array $agg, string $displayKey, array $children) use ($percentage, $pickChildCampaignAds): array {
            $parentRow['Parent'] = $displayKey;
            $parentRow['(Child) sku'] = 'PARENT ' . $displayKey;
            $parentRow['is_parent_row'] = true;

            foreach ($agg as $k => $v) {
                // Keep existing image if parent already has one
                if ($k === 'image_path' && ! empty($parentRow['image_path'])) {
                    continue;
                }
                $parentRow[$k] = $v;
            }

            // Campaign-ads live on child listings. Default view is Parents — copy ES BID /
            // C BID / PROMOTE from the in-campaign child (else first child with ad data)
            // so S BID can use ES Bid when family EL30 = 0.
            foreach ($pickChildCampaignAds($children) as $caKey => $caVal) {
                if ($caKey === 'eBay_item_id' && ! empty($parentRow['eBay_item_id'])) {
                    continue;
                }
                $parentRow[$caKey] = $caVal;
            }

            // SPRICE defaults to avg eBay price when blank (same as ebay3 parent rows)
            $sprice = $parentRow['SPRICE'] ?? null;
            $spriceNum = is_numeric($sprice) ? (float) $sprice : 0.0;
            if ($spriceNum <= 0) {
                $parentRow['SPRICE'] = $agg['eBay Price'];
                $avgLp = (float) $agg['LP_productmaster'];
                $avgShip = (float) $agg['Ship_productmaster'];
                $avgPrice = (float) $agg['eBay Price'];
                $sgpft = $avgPrice > 0
                    ? (($avgPrice * $percentage - $avgShip - $avgLp) / $avgPrice) * 100
                    : 0.0;
                $parentRow['SGPFT'] = round($sgpft, 2);
                $parentRow['SGROI'] = $avgLp > 0
                    ? round((($avgPrice * $percentage - $avgLp - $avgShip) / $avgLp) * 100, 2)
                    : 0;
                $parentRow['SROI'] = $parentRow['SGROI'];
            }

            if (! isset($parentRow['nr_req']) || $parentRow['nr_req'] === '' || $parentRow['nr_req'] === null) {
                $parentRow['nr_req'] = 'REQ';
            }

            return $parentRow;
        };

        $enrichedParents = [];
        $allParentKeys = array_unique(array_merge(array_keys($parentRowsByKey), array_keys($childRowsByParent)));
        sort($allParentKeys);

        foreach ($allParentKeys as $ukey) {
            $displayKey = $parentKeyDisplay[$ukey] ?? $ukey;
            $children = $childRowsByParent[$ukey] ?? [];
            $agg = $aggregateChildren($children);

            if (isset($parentRowsByKey[$ukey])) {
                $enrichedParents[] = $applyAggToParent($parentRowsByKey[$ukey], $agg, $displayKey, $children);
            } else {
                // Synthetic PARENT row when product_masters has children but no PARENT sku
                $synthetic = [
                    'Parent' => $displayKey,
                    '(Child) sku' => 'PARENT ' . $displayKey,
                    'is_parent_row' => true,
                    'nr_req' => 'REQ',
                    'NRL' => '',
                    'NR' => '',
                    'Listed' => null,
                    'Live' => null,
                    'APlus' => null,
                    'SPRICE' => null,
                    'SGPFT' => null,
                    'SPFT' => null,
                    'SROI' => null,
                    'SGROI' => null,
                    'eBay_item_id' => null,
                    'Missing' => null,
                    'fba' => '',
                    'base_sku' => '',
                    'base_inv' => 0,
                    'A Price' => 0,
                    'AD_Spend_L30' => 0,
                    'spend_l30' => 0,
                    'pmt_spend_L30' => 0,
                    'kw_spend_L30' => 0,
                    'AD%' => 0,
                    'PFT %' => 0,
                    'TacosL30' => 0,
                    'bid_percentage' => null,
                    'suggested_bid' => null,
                    'ca_bid_percentage' => null,
                    'ca_suggested_bid' => null,
                    'ca_promote_with_ad' => null,
                    'pmt_clicks_l30' => 0,
                    'pmt_clicks_l7' => 0,
                    'nrp' => '',
                ];
                $enrichedParents[] = $applyAggToParent($synthetic, $agg, $displayKey, $children);
            }
        }

        // Flat output: parents first (like ebay3 tree roots), then children, then orphans
        $out = [];
        foreach ($enrichedParents as $p) {
            $out[] = (object) $p;
        }
        foreach ($childRowsByParent as $children) {
            foreach ($children as $c) {
                $out[] = (object) $c;
            }
        }
        foreach ($passthrough as $r) {
            $out[] = (object) $r;
        }

        return $out;
    }

    /**
     * Index campaign-ads rows by string listing_id so PHP does not int-cast numeric keys
     * and miss ebay_2_metrics.item_id (string) lookups.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, object>
     */
    private function indexEbay2CampaignAdsByListingId($rows): array
    {
        $byListing = [];
        foreach ($rows as $row) {
            $listingId = trim((string) ($row->listing_id ?? ''));
            if ($listingId === '') {
                continue;
            }
            $byListing[$listingId] = $row;
        }

        return $byListing;
    }

    private function lookupEbay2CampaignAd(array $byListing, $itemId): ?object
    {
        $key = trim((string) ($itemId ?? ''));
        if ($key === '' || ! isset($byListing[$key])) {
            return null;
        }
        $row = $byListing[$key];

        return is_object($row) ? $row : (object) $row;
    }

    /**
     * @return array{ca_bid_percentage: mixed, ca_suggested_bid: mixed, ca_promote_with_ad: mixed}
     */
    private function ebay2CampaignAdsFields(?object $caRow): array
    {
        if ($caRow === null) {
            return [
                'ca_bid_percentage' => null,
                'ca_suggested_bid' => null,
                'ca_promote_with_ad' => null,
            ];
        }

        return [
            'ca_bid_percentage' => $caRow->bid_percentage ?? $caRow->ca_bid_percentage ?? null,
            'ca_suggested_bid' => $caRow->suggested_bid ?? $caRow->ca_suggested_bid ?? null,
            'ca_promote_with_ad' => $caRow->promote_with_ad ?? $caRow->ca_promote_with_ad ?? null,
        ];
    }

    public function updateAllEbay2Skus(Request $request)
    {
        try {
            $percent = $request->input('percent');

            if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Invalid percentage value. Must be between 0 and 100.'
                ], 400);
            }

            // Update database
            MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'EbayTwo'],
                ['percentage' => $percent]
            );

            // No caching needed for instant results
            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'EbayTwo',   // ✅ Fix here
                    'percentage' => $percent
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating percentage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $sku = $this->resolveCanonicalEbayTwoSku((string) $request->input('sku'));
        $nr = $request->input('nr');

        if ($sku === '' || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        $dataView = $this->findOrNewEbayTwoDataView($sku);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        $value['NR'] = $nr;
        $dataView->value = $value;
        $dataView->save();

        return response()->json(['success' => true, 'data' => $dataView]);
    }

    /**
     * Save buyer / seller links for a SKU into ebay2_listing_statuses.value JSON.
     * Empty strings clear the link (URL validation only applies to non-empty values).
     */
    public function saveLinks(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }

        $buyerLink = trim((string) $request->input('buyer_link', ''));
        $sellerLink = trim((string) $request->input('seller_link', ''));

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $field => $val) {
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'Invalid URL for ' . $field], 422);
            }
        }

        $status = EbayTwoListingStatus::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->first();
        if (!$status) {
            $status = new EbayTwoListingStatus();
            $status->sku = $sku;
        }

        $existing = is_array($status->value)
            ? $status->value
            : (json_decode($status->value, true) ?: []);

        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;

        $status->value = $existing;
        $status->save();

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
    }


    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = EbayTwoDataView::firstOrCreate(
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
    function extractNumber($value)
    {
        if (is_null($value)) {
            return 0;
        }

        // Handle string values like "USD 10.50" or "10.50"
        if (is_string($value)) {
            // Remove currency symbols and text, keep numbers and decimal point
            $value = str_replace('USD ', '', $value);
            $value = preg_replace('/[^0-9.]/', '', $value);
        }

        return floatval($value) ?? 0;
    }

    /**
     * Product master row for LP/ship when saving SPRICE. Listing SKUs (e.g. "… OPEN BOX") are not
     * stored in product_master; use the base SKU's row like getViewEbayData does.
     */
    private function resolveProductMasterForEbayTwoListingSku(string $listingSku): ?ProductMaster
    {
        $listingSku = trim($listingSku);
        if ($listingSku === '') {
            return null;
        }

        $pm = ProductMaster::where('sku', $listingSku)->first();
        if ($pm) {
            return $pm;
        }

        $pm = ProductMaster::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($listingSku)])->first();
        if ($pm) {
            return $pm;
        }

        $base = '';
        if (stripos($listingSku, 'OPEN BOX') !== false) {
            $base = trim(str_ireplace('OPEN BOX', '', $listingSku));
        } elseif (stripos($listingSku, 'USED') !== false) {
            $base = trim(str_ireplace('USED', '', $listingSku));
        }

        if ($base === '') {
            return null;
        }

        $pm = ProductMaster::where('sku', $base)->first();
        if ($pm) {
            return $pm;
        }

        return ProductMaster::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($base)])->first();
    }

    /**
     * Canonical listing SKU casing from ebay_2_metrics (source of truth from eBay),
     * falling back to an existing data-view row, then the request SKU as typed.
     */
    private function resolveCanonicalEbayTwoSku(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }

        $metric = Ebay2Metric::where('sku', $sku)->first();
        if ($metric && trim((string) $metric->sku) !== '') {
            return (string) $metric->sku;
        }

        $metric = Ebay2Metric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
        if ($metric && trim((string) $metric->sku) !== '') {
            return (string) $metric->sku;
        }

        $dataView = EbayTwoDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
        if ($dataView && trim((string) $dataView->sku) !== '') {
            return (string) $dataView->sku;
        }

        return $sku;
    }

    /**
     * Load/create EbayTwoDataView using eBay-original SKU casing (never force uppercase).
     */
    private function findOrNewEbayTwoDataView(string $sku): EbayTwoDataView
    {
        $canonical = $this->resolveCanonicalEbayTwoSku($sku);
        if ($canonical === '') {
            $dataView = new EbayTwoDataView();
            $dataView->sku = '';

            return $dataView;
        }

        $dataView = EbayTwoDataView::where('sku', $canonical)->first();
        if (! $dataView) {
            $dataView = EbayTwoDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($canonical)])->first();
        }

        if ($dataView) {
            // MySQL CI collations treat WoB == WOB; force binary update so eBay casing sticks.
            if ((string) $dataView->sku !== $canonical) {
                DB::update(
                    'UPDATE ebay_two_data_views SET sku = ?, updated_at = ? WHERE id = ? AND BINARY sku != ?',
                    [$canonical, now(), $dataView->id, $canonical]
                );
                $dataView->sku = $canonical;
            }

            return $dataView;
        }

        $dataView = new EbayTwoDataView();
        $dataView->sku = $canonical;

        return $dataView;
    }

    public function saveSpriceToDatabase(Request $request)
    {
        $sku = $this->resolveCanonicalEbayTwoSku((string) $request->input('sku'));

        if ($sku === '') {
            return response()->json(['error' => 'SKU is required.'], 400);
        }

        if (! $request->exists('sprice')) {
            return response()->json(['error' => 'sprice is required.'], 400);
        }

        $sprice = $request->input('sprice');

        $percentage = $this->ebay1StyleTakeHomePercent();

        // LP/ship from base product when listing SKU is OPEN BOX / USED / case variant
        $pm = $this->resolveProductMasterForEbayTwoListingSku($sku);
        if (! $pm) {
            return response()->json(['error' => 'SKU not found in ProductMaster.'], 404);
        }

        // Extract LP and Ship
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

        // Same normal ship as eBay 1 (Values['ship']), not ebay2_ship
        $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

        $spriceFloat = floatval($sprice);
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * $percentage - $ship - $lp) / $spriceFloat) * 100, 2) : 0;

        // Ads stay eBay 2 (not eBay 1).
        $adPercent = (float) app(ChannelMasterController::class)->getEbaytwoMasterAdsPercent();

        $spft = round($sgpft - $adPercent, 2);
        $sgroi = round($lp > 0 ? (($spriceFloat * $percentage - $lp - $ship) / $lp) * 100 : 0, 2);
        $adDecimal = $adPercent / 100;
        $sroi = round(
            $lp > 0 ? ((($spriceFloat * $percentage - $ship - $lp) - ($spriceFloat * $adDecimal)) / $lp) * 100 : 0,
            2
        );

        Log::info('SPRICE calculated', [
            'sku' => $sku,
            'sprice' => $spriceFloat,
            'sgpft' => $sgpft,
            'sgroi' => $sgroi,
            'ad_percent' => $adPercent,
            'spft' => $spft,
            'sroi' => $sroi,
        ]);

        $ebayDataView = $this->findOrNewEbayTwoDataView($sku);

        // Decode value column safely
        $existing = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
            'SGROI' => $sgroi,
            'SGPFT' => $sgpft,
        ]);

        $ebayDataView->value = $merged;
        $ebayDataView->save();

        return response()->json([
            'message' => 'Data saved successfully.',
            'spft_percent' => $spft,
            'sroi_percent' => $sroi,
            'sgroi_percent' => $sgroi,
            'sgpft_percent' => $sgpft,
        ]);
    }

    public function importEbayTwoAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $allowedExtensions = ['xlsx', 'xls', 'csv'];
                    
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail('The excel file must be a file of type: xlsx, xls, csv.');
                    }
                }
            ]
        ]);

        try {
            $file = $request->file('excel_file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Handle CSV files differently
            if ($extension === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $reader->setSheetIndex(0);
                $spreadsheet = $reader->load($file->getPathName());
            } else {
            $spreadsheet = IOFactory::load($file->getPathName());
            }
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
                EbayTwoDataView::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['value' => $values]
                );

                $importCount++;
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported $importCount records!",
                    'count' => $importCount
                ]);
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error importing file: ' . $e->getMessage()
                ], 400);
            }
            
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportEbayTwoAnalytics()
    {
        $ebayData = EbayTwoDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($ebayData as $data) {
            $values = is_array($data->value)
                ? $data->value
                : (json_decode($data->value, true) ?? []);

            $sheet->fromArray([
                $data->sku,
                isset($values['Listed']) ? ($values['Listed'] ? 'TRUE' : 'FALSE') : 'FALSE',
                isset($values['Live']) ? ($values['Live'] ? 'TRUE' : 'FALSE') : 'FALSE',
            ], NULL, 'A' . $rowIndex);

            $rowIndex++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Ebay_Two_Analytics_Export_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function downloadSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data
        $sampleData = [
            ['SKU001', 'TRUE', 'FALSE'],
            ['SKU002', 'FALSE', 'TRUE'],
            ['SKU003', 'TRUE', 'TRUE'],
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Ebay_Two_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function getEbay2ColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay2_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setEbay2ColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay2_tabulator_column_visibility_{$userId}";
        
        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    public function getEbay2opColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay2op_tabulator_column_visibility_{$userId}";

        return response()->json(Cache::get($key, []));
    }

    public function setEbay2opColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay2op_tabulator_column_visibility_{$userId}";
        Cache::put($key, $request->input('visibility', []), now()->addDays(365));

        return response()->json(['success' => true]);
    }

    public function exportEbay2PricingData(Request $request)
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
                'base_sku' => ['Base SKU (PM)', function($item) { return $item['base_sku'] ?? ''; }],
                'base_inv' => ['Base INV', function($item) { return $item['base_inv'] ?? 0; }],
                '(Child) sku' => ['SKU', function($item) { return $item['(Child) sku'] ?? ''; }],
                'INV' => ['INV', function($item) { return $item['INV'] ?? 0; }],
                'L30' => ['L30', function($item) { return $item['L30'] ?? 0; }],
                'E Dil%' => ['Dil%', function($item) {
                    $views = (float) ($item['views'] ?? 0);
                    $el30 = (float) ($item['eBay L30'] ?? 0);
                    return $views > 0 ? round(($el30 / $views) * 100, 2) : 0;
                }],
                'eBay L30' => ['eBay L30', function($item) { return $item['eBay L30'] ?? 0; }],
                'eBay L60' => ['eBay L60', function($item) { return $item['eBay L60'] ?? 0; }],
                'eBay Price' => ['eBay Price', function($item) { return number_format($item['eBay Price'] ?? 0, 2); }],
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
                'SCVR' => ['SCVR', function($item) { return number_format($item['SCVR'] ?? 0, 1); }],
                'pmt_spend_L30' => ['PMT Spend L30', function($item) { return number_format($item['pmt_spend_L30'] ?? 0, 2); }],
            ];

            // If no columns selected, export all
            if (empty($selectedColumns)) {
                $selectedColumns = array_keys($columnMap);
            }

            // Filter column map to only selected columns
            $selectedColumnMap = array_intersect_key($columnMap, array_flip($selectedColumns));

            // Set headers for CSV download
            $fileName = 'eBay2_Pricing_Data_' . date('Y-m-d_H-i-s') . '.csv';
            
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
            return back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }

    /**
     * Per-SKU Price / CVR / Views history for /ebay2-tabulator-view charts.
     * Same response shape as /ebay-metrics-history (ebay1): price, views, l7_views, cvr_percent.
     */
    public function getMetricsHistory(Request $request)
    {
        $days = (int) $request->input('days', 30); // Default 30; 0 = lifetime
        $sku = $request->input('sku');
        $skuNorm = $sku ? strtoupper(trim($sku)) : null;

        // California (America/Los_Angeles) window — include today PT so live CVR 30 matches the table.
        $endDate = Carbon::now('America/Los_Angeles')->startOfDay();
        if ($days === 0) {
            $startDate = null; // lifetime — no lower bound
        } else {
            if ($days < 7) {
                $days = 7;
            }
            $startDate = $endDate->copy()->subDays($days - 1);
        }

        $dataByDate = [];

        try {
            // Optional historical snapshots (same role as ebay_sku_daily_data for ebay1).
            if ($skuNorm && Schema::hasTable('ebay2_sku_daily_data')) {
                $query = DB::table('ebay2_sku_daily_data')
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
                    ->where('record_date', '<=', $endDate->toDateString())
                    ->orderBy('record_date', 'asc');
                if ($startDate) {
                    $query->where('record_date', '>=', $startDate->toDateString());
                }

                foreach ($query->get() as $record) {
                    $data = is_array($record->daily_data ?? null)
                        ? $record->daily_data
                        : (json_decode($record->daily_data ?? '{}', true) ?: []);
                    $dateKey = Carbon::parse($record->record_date)->format('Y-m-d');
                    $views = (int) ($data['views'] ?? 0);
                    $ebayL30 = (int) ($data['ebay_l30'] ?? 0);
                    $cvr = $views > 0
                        ? round(($ebayL30 / $views) * 100, 2)
                        : round((float) ($data['cvr_percent'] ?? 0), 2);
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => Carbon::parse($record->record_date)->format('M d'),
                        'price' => round((float) ($data['price'] ?? 0), 2),
                        'views' => $views,
                        'l7_views' => (int) ($data['l7_views'] ?? 0),
                        'cvr_percent' => $cvr,
                        'ad_percent' => round((float) ($data['ad_percent'] ?? 0), 2),
                        'ebay_l30' => $ebayL30,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::info('No eBay2 daily metrics data available. Historical data will be populated by metrics collection command.');
        }

        // Overlay live ebay_2_metrics for California today so the chart matches CVR 30 / Prc on the tabulator.
        if ($skuNorm) {
            $live = Ebay2Metric::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
                ->first();
            if ($live) {
                $todayKey = $endDate->toDateString();
                $views = (int) ($live->views ?? 0);
                $ebayL30 = (int) ($live->ebay_l30 ?? 0);
                $price = round((float) ($live->ebay_price ?? 0), 2);
                $cvr = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0;

                $dataByDate[$todayKey] = [
                    'date' => $todayKey,
                    'date_formatted' => $endDate->format('M d'),
                    'price' => $price,
                    'views' => $views,
                    'l7_views' => (int) ($live->l7_views ?? 0),
                    'cvr_percent' => $cvr,
                    'ad_percent' => round((float) ($dataByDate[$todayKey]['ad_percent'] ?? 0), 2),
                    'ebay_l30' => $ebayL30,
                ];
            }
        }

        // Seed carry-forward from last snapshot before the window (same as ebay1).
        $carry = null;
        if ($skuNorm && $startDate && Schema::hasTable('ebay2_sku_daily_data')) {
            $prior = DB::table('ebay2_sku_daily_data')
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
                ->where('record_date', '<', $startDate->toDateString())
                ->orderByDesc('record_date')
                ->first();
            if ($prior) {
                $priorData = is_array($prior->daily_data ?? null)
                    ? $prior->daily_data
                    : (json_decode($prior->daily_data ?? '{}', true) ?: []);
                $pViews = (int) ($priorData['views'] ?? 0);
                $pL30 = (int) ($priorData['ebay_l30'] ?? 0);
                $carry = [
                    'price' => round((float) ($priorData['price'] ?? 0), 2),
                    'views' => $pViews,
                    'l7_views' => (int) ($priorData['l7_views'] ?? 0),
                    'cvr_percent' => $pViews > 0
                        ? round(($pL30 / $pViews) * 100, 2)
                        : round((float) ($priorData['cvr_percent'] ?? 0), 2),
                    'ad_percent' => round((float) ($priorData['ad_percent'] ?? 0), 2),
                    'ebay_l30' => $pL30,
                ];
            }
        }

        // Full window day-by-day with forward-fill (never invent $0 price cliffs).
        if ($skuNorm && $startDate) {
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
                        'cvr_percent' => (float) ($carry['cvr_percent'] ?? 0),
                        'ad_percent' => (float) ($carry['ad_percent'] ?? 0),
                        'ebay_l30' => (int) ($carry['ebay_l30'] ?? 0),
                    ];
                }
                $currentDate->addDay();
            }
            $dataByDate = $filled;
        }

        ksort($dataByDate);

        return response()->json(array_values($dataByDate));
    }

    public function pushEbay2Price(Request $request)
    {
        $sku   = $this->resolveCanonicalEbayTwoSku((string) $request->input('sku'));
        $price = $request->input('price');

        if ($sku === '') {
            $this->saveSpriceStatus($sku, 'failed');
            return response()->json(['success' => false, 'message' => 'SKU is required'], 400);
        }

        $priceFloat = floatval($price);
        if (!is_numeric($price) || $priceFloat <= 0) {
            $this->saveSpriceStatus($sku, 'failed');
            return response()->json(['success' => false, 'message' => 'Invalid price value'], 400);
        }

        if ($priceFloat < 0.01 || $priceFloat > 10000) {
            $this->saveSpriceStatus($sku, 'failed');
            return response()->json(['success' => false, 'message' => 'Price must be between $0.01 and $10,000.'], 400);
        }

        $priceFloat = round($priceFloat, 2);

        try {
            $ebayMetric = EbayListingEnded::preferredRow(Ebay2Metric::class, $sku);

            if (!$ebayMetric || !$ebayMetric->item_id) {
                $this->saveSpriceStatus($sku, 'failed');
                Log::error('[EbayTwoController] eBay2 item_id not found', ['sku' => $sku]);
                return response()->json(['success' => false, 'message' => 'Item ID not found for SKU: ' . $sku], 404);
            }

            if (EbayListingEnded::isEnded($ebayMetric->listing_status ?? null)) {
                $this->saveSpriceStatus($sku, 'failed');
                return response()->json([
                    'success' => false,
                    'message' => 'Listing is ended — skipped eBay revise',
                ], 422);
            }

            $current = round((float) ($ebayMetric->ebay_price ?? 0), 2);
            if ($current > 0 && abs($current - $priceFloat) < 0.005) {
                $this->saveSpriceStatus($sku, 'pushed');
                return response()->json([
                    'success' => true,
                    'message' => 'eBay already at $'.number_format($priceFloat, 2),
                    'new_price' => $current,
                    'price' => $current,
                    'ebay_price' => $current,
                ]);
            }

            // Push price DIRECTLY to eBay via the local Ebay2ApiService (no microservice).
            // Pass variation SKU — multi-variation listings ignore item-level StartPrice.
            $service = new Ebay2ApiService();
            $apiSku = trim((string) ($ebayMetric->sku ?: $sku));
            $result = Ebay1PromotionService::for('ebay2')->withPriceRevisionAllowed(
                $sku,
                fn () => $service->reviseFixedPriceItem(itemId: $ebayMetric->item_id, price: $priceFloat, sku: $apiSku)
            );

            if (isset($result['success']) && $result['success']) {
                $live = $this->ebay2PullLivePrice($sku, $priceFloat);
                $ebayMetric->ebay_price = $live;
                $ebayMetric->save();

                $this->saveSpriceStatus($sku, 'pushed');
                Log::info('[EbayTwoController] eBay2 price push successful and live price pulled', [
                    'sku'     => $sku,
                    'price'   => $priceFloat,
                    'ebay_price' => $live,
                    'item_id' => $ebayMetric->item_id,
                ]);
                return response()->json([
                    'success'   => true,
                    'message'   => 'Price updated successfully on eBay2',
                    'new_price' => $live,
                    'price'     => $live,
                    'ebay_price'=> $live,
                ]);
            }

            // Failure — forward normalized errors from microservice
            $isAccountRestricted = (bool) ($result['accountRestricted'] ?? false);
            $this->saveSpriceStatus($sku, $isAccountRestricted ? 'account_restricted' : 'failed');

            $errors = $result['errors'] ?? [];
            if ($errors !== [] && ! isset($errors[0]) && is_array($errors)) {
                $errors = [$errors];
            }
            $first = is_array($errors[0] ?? null) ? $errors[0] : [];
            $message = (string) ($result['message']
                ?? $first['message']
                ?? $first['LongMessage']
                ?? $first['ShortMessage']
                ?? 'Failed to update price on eBay2');
            if (! empty($first['ErrorCode']) && ! str_contains($message, (string) $first['ErrorCode'])) {
                $message = '[eBay #'.$first['ErrorCode'].'] '.$message;
            }

            Log::error('[EbayTwoController] eBay2 price push failed via microservice', [
                'sku'    => $sku,
                'price'  => $priceFloat,
                'errors' => $errors,
            ]);

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ], 400);

        } catch (\Exception $e) {
            $this->saveSpriceStatus($sku, 'failed');
            Log::error('[EbayTwoController] Exception in pushEbay2Price', [
                'sku'   => $sku,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function ebay2PullLivePrice(string $sku, float $fallback): float
    {
        try {
            $pulled = app(PefEbayPricePullService::class)->pullOne($sku, 'ebay2');
            $live = isset($pulled['price']) ? (float) $pulled['price'] : 0;
            if (! empty($pulled['success']) && $live > 0 && abs($live - $fallback) < 0.05) {
                return round($live, 2);
            }
            if (! empty($pulled['success']) && $live > 0) {
                Log::info('[EbayTwoController] GetItem after push still stale — keeping revised price', [
                    'sku' => $sku,
                    'pushed' => $fallback,
                    'getitem' => $live,
                ]);
            }
            Log::warning('[EbayTwoController] GetItem after S PRC push failed', [
                'sku' => $sku,
                'message' => $pulled['message'] ?? 'pull failed',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[EbayTwoController] GetItem after S PRC push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        return round($fallback, 2);
    }

    private function saveSpriceStatus($sku, $status)
    {
        try {
            $ebayDataView = $this->findOrNewEbayTwoDataView((string) $sku);
            if (trim((string) $ebayDataView->sku) === '') {
                return;
            }

            $value = is_array($ebayDataView->value)
                ? $ebayDataView->value
                : (is_string($ebayDataView->value) ? json_decode($ebayDataView->value, true) : []);
            
            $value['sprice_push_status'] = $status;
            $value['sprice_push_time'] = now()->toDateTimeString();
            
            $ebayDataView->value = $value;
            $ebayDataView->save();
        } catch (\Exception $e) {
            \Log::error('Error saving eBay2 sprice status: ' . $e->getMessage());
        }
    }

    public function updateEbay2SpriceStatus(Request $request)
    {
        $sku = $request->input('sku');
        $status = $request->input('status');

        $this->saveSpriceStatus($sku, $status);

        return response()->json(['success' => true]);
    }

    public function getEbay2AdsSpend()
    {
        try {
            // Get ad spend from ebay2_general_reports for L30 (last 30 days only)
            $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);
            $generalReports = Ebay2GeneralReport::where('report_range', 'L30')
                ->whereDate('updated_at', '>=', $thirtyDaysAgo)
                ->get();
            
            $adsSpend = 0;
            foreach ($generalReports as $report) {
                $adsSpend += $this->extractNumber($report->ad_fees);
            }

            return response()->json([
                'success' => true,
                'ads_spend' => round($adsSpend, 2)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-save daily eBay 2 summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            
            // Filter: INV > 0 && nr_req === 'REQ' (EXACT JavaScript logic)
            $filteredData = collect($products)->filter(function($p) {
                $invCheck = floatval($p->INV ?? 0) > 0;
                $reqCheck = ($p->nr_req ?? '') === 'REQ';
                
                return $invCheck && $reqCheck;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalSkuCount = $filteredData->count();
            $totalPmtSpendL30 = 0;
            $totalPftAmt = 0;
            $totalSalesAmt = 0;
            $totalLpAmt = 0;
            $totalFbaInv = 0;
            $totalFbaL30 = 0;
            $zeroSoldCount = 0;
            $moreSoldCount = 0;
            $missingCount = 0;
            $mapCount = 0;
            $notMapCount = 0;
            $lessAmzCount = 0;
            $moreAmzCount = 0;
            $totalWeightedPrice = 0;
            $totalL30 = 0;
            $totalViews = 0;
            
            // Loop through each row (EXACT JavaScript forEach logic)
            foreach ($filteredData as $row) {
                $inv = floatval($row->INV ?? 0);
                $ebayL30 = floatval($row->{'eBay L30'} ?? 0);
                
                $totalPftAmt += floatval($row->Total_pft ?? 0);
                $totalSalesAmt += floatval($row->T_Sale_l30 ?? 0);
                $totalLpAmt += floatval($row->LP_productmaster ?? 0) * $ebayL30;
                $totalFbaInv += $inv;
                $totalFbaL30 += $ebayL30;
                $totalPmtSpendL30 += floatval($row->pmt_spend_L30 ?? 0);
                
                // Count sold and 0-sold
                if ($ebayL30 == 0) {
                    $zeroSoldCount++;
                } else {
                    $moreSoldCount++;
                }
                
                // Count Missing (exclude NR items)
                $ebayPrice = floatval($row->{'eBay Price'} ?? 0);
                $itemId = $row->eBay_item_id ?? '';
                $nrReq = $row->nr_req ?? '';
                if ($ebayPrice == 0 && (!$itemId || $itemId === null || $itemId === '') && $nrReq !== 'NR' && $nrReq !== 'NRL') {
                    $missingCount++;
                }
                
                // Count Map and N MP (|INV − E Stock| ≤ 3 → map; same as ebay_tabulator_view MAP column)
                if ($itemId && $itemId !== null && $itemId !== '') {
                    $ebayStock = floatval($row->{'E Stock'} ?? 0);
                    if ($inv > 0) {
                        if ($ebayStock == 0) {
                            $notMapCount++;
                        } elseif (abs($inv - $ebayStock) <= 3) {
                            $mapCount++;
                        } else {
                            $notMapCount++;
                        }
                    }
                }
                
                // Count < Amz and > Amz
                $amazonPrice = floatval($row->{'A Price'} ?? 0);
                if ($amazonPrice > 0 && $ebayPrice > 0) {
                    if ($ebayPrice < $amazonPrice) {
                        $lessAmzCount++;
                    } elseif ($ebayPrice > $amazonPrice) {
                        $moreAmzCount++;
                    }
                }
                
                // Weighted price
                $totalWeightedPrice += $ebayPrice * $ebayL30;
                $totalL30 += $ebayL30;
                
                // Views — same scope as ebay2-tabulator Views badge / all-marketplace-master
                // column: rows with E Stock > 0 (live listing traffic).
                $ebayStockForViews = floatval($row->{'E Stock'} ?? ($row->{'eBay Stock'} ?? 0));
                if ($ebayStockForViews > 0) {
                    $totalViews += floatval($row->views ?? 0);
                }
            }
            
            // Calculate averages and percentages (EXACT JavaScript logic)
            $avgPrice = $totalL30 > 0 ? $totalWeightedPrice / $totalL30 : 0;
            $avgCVR = $totalViews > 0 ? ($totalL30 / $totalViews * 100) : 0;
            $tacosPercent = $totalSalesAmt > 0 ? (($totalPmtSpendL30 / $totalSalesAmt) * 100) : 0;
            $groiPercent = $totalLpAmt > 0 ? (($totalPftAmt / $totalLpAmt) * 100) : 0;
            $avgGpft = $totalSalesAmt > 0 ? (($totalPftAmt / $totalSalesAmt) * 100) : 0; // GPFT = (PFT/Sales)*100
            $npftPercent = $avgGpft - $tacosPercent;
            // NROI% = (GPFT$ − Ad Spend) / COGS × 100 — same as Amazon / ebay2-tabulator badge
            $nroiPercent = $totalLpAmt > 0 ? ((($totalPftAmt - $totalPmtSpendL30) / $totalLpAmt) * 100) : 0;
            
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
                'less_amz_count' => $lessAmzCount,
                'more_amz_count' => $moreAmzCount,
                
                // Financial Totals
                'total_pmt_spend_l30' => round($totalPmtSpendL30, 2),
                'total_pft_amt' => round($totalPftAmt, 2),
                'total_sales_amt' => round($totalSalesAmt, 2),
                'total_lp_amt' => round($totalLpAmt, 2),
                
                // Inventory
                'total_fba_inv' => round($totalFbaInv, 2),
                'total_ebay_l30' => round($totalFbaL30, 2),
                'total_views' => $totalViews,
                
                // Calculated Percentages
                'tcos_percent' => round($tacosPercent, 2),
                'groi_percent' => round($groiPercent, 2),
                'nroi_percent' => round($nroiPercent, 2),
                'cvr_percent' => round($avgCVR, 2),
                'gpft_percent' => round($avgGpft, 2),
                'npft_percent' => round($npftPercent, 2),
                
                // Averages
                'avg_price' => round($avgPrice, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters
                'filters_applied' => [
                    'inventory' => 'more',  // INV > 0
                    'nrl' => 'REQ',        // REQ only
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'ebay2',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, REQ only)',
                ]
            );
            
            Log::info("Daily eBay2 summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily eBay2 summary: ' . $e->getMessage());
        }
    }

    public function getCampaignDataBySku(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }
        $cleanSku = strtoupper(trim((string) $sku));

        $ebay2Metric = Ebay2Metric::where('sku', $sku)->first();
        if (!$ebay2Metric) {
            $ebay2Metric = Ebay2Metric::whereRaw('UPPER(TRIM(sku)) = ?', [$cleanSku])->first();
        }
        $itemId = $ebay2Metric && !empty($ebay2Metric->item_id) ? trim((string) $ebay2Metric->item_id) : null;

        $shopify = ShopifySku::firstForProductSku($sku);
        $inv = $shopify ? (float) ($shopify->inv ?? 0) : 0.0;

        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $lastSbidMap = [];
        $lastSbidReports = Ebay2PriorityReport::where('report_range', $dayBeforeYesterday)
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();
        // Build lastSbidMap (matching utilized page logic exactly)
        // Utilized page: if (!empty($report->campaign_id) && !empty($report->last_sbid)) { $lastSbidMap[$report->campaign_id] = $report->last_sbid; }
        foreach ($lastSbidReports as $report) {
            if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                $lastSbidMap[(string) $report->campaign_id] = $report->last_sbid;
            }
        }

        $kwCampaigns = [];
        $kwL30 = Ebay2PriorityReport::where('report_range', 'L30')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get();
        $kwL7 = Ebay2PriorityReport::where('report_range', 'L7')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $kwL1 = Ebay2PriorityReport::where('report_range', 'L1')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $price = $ebay2Metric ? (float) ($ebay2Metric->ebay_price ?? 0) : 0;

        foreach ($kwL30 as $r) {
            $campaignId = $r->campaign_id ?? null;
            $cid = $campaignId !== null ? (string) $campaignId : null;
            
            // Skip if no valid campaign_id (not a real campaign)
            if (empty($cid) || $cid === '' || $cid === '0') {
                continue;
            }
            
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

            // Get last_sbid from day-before-yesterday map only (matching utilized page logic)
            // Utilized page: if (isset($lastSbidMap[$campaignId])) { $campaignMap[$key]['last_sbid'] = $lastSbidMap[$campaignId]; } else { $campaignMap[$key]['last_sbid'] = ''; }
            // Do NOT use $r->last_sbid or $r->apprSbid as fallback - only use day-before-yesterday map
            $lastSbid = null;
            if ($cid && isset($lastSbidMap[$cid]) && !empty($lastSbidMap[$cid])) {
                $lastSbidRaw = $lastSbidMap[$cid];
                if ($lastSbidRaw !== null && $lastSbidRaw !== '' && $lastSbidRaw !== '0') {
                    $f = is_numeric($lastSbidRaw) ? (float) $lastSbidRaw : null;
                    if ($f !== null && $f > 0) {
                        $lastSbid = $f;
                    }
                }
            }
            $l1CpcVal = $l1Cpc !== null ? (float) $l1Cpc : 0;
            $l7CpcVal = $l7Cpc !== null ? (float) $l7Cpc : 0;

            $sbid = $this->calculateSbidUtilized($ub7, $ub1, $inv, $bgt, $l1CpcVal, $l7CpcVal, $lastSbid, $price);

            // Only include campaigns that have activity (spend, clicks, sales, or budget)
            if ($spend > 0 || $clicks > 0 || $sales > 0 || $bgt > 0) {
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
        }

        $ptCampaigns = [];
        if ($itemId) {
            // Match ebay/pmp/ads: prefer COST_PER_SALE row per listing (EbayPMPAdsController)
            $campaignListing = null;
            try {
                $campaignListing = DB::table('ebay2_campaign_ads')
                    ->where('listing_id', $itemId)
                    ->select('listing_id', 'bid_percentage', 'suggested_bid')
                    ->orderByRaw('CASE WHEN funding_strategy = "COST_PER_SALE" THEN 0 ELSE 1 END')
                    ->orderByDesc('id')
                    ->first();
            } catch (\Exception $e) {
                // ebay2_campaign_ads may be unavailable
            }
            $cbid = $campaignListing ? (($campaignListing->bid_percentage !== null && $campaignListing->bid_percentage !== '') ? (float) $campaignListing->bid_percentage : null) : null;
            $esBid = $campaignListing ? (($campaignListing->suggested_bid !== null && $campaignListing->suggested_bid !== '') ? (float) $campaignListing->suggested_bid : null) : null;
            $views = $ebay2Metric ? (float) ($ebay2Metric->views ?? 0) : 0;
            $l7Views = $ebay2Metric ? (float) ($ebay2Metric->l7_views ?? 0) : 0;
            $ebayL30 = $ebay2Metric ? (float) ($ebay2Metric->ebay_l30 ?? 0) : 0;
            $scvr = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : null;
            
            // Calculate SBID for PMT based on L7_VIEWS (same logic as ebay/pmp/ads page)
            $sBid = null;
            if ($l7Views >= 0 && $l7Views < 50) {
                // 0-50: use ESBID
                $sBid = $esBid !== null ? (float) $esBid : null;
            } elseif ($l7Views >= 50 && $l7Views < 100) {
                $sBid = 9.0;
            } elseif ($l7Views >= 100 && $l7Views < 150) {
                $sBid = 8.0;
            } elseif ($l7Views >= 150 && $l7Views < 200) {
                $sBid = 7.0;
            } elseif ($l7Views >= 200 && $l7Views < 250) {
                $sBid = 6.0;
            } elseif ($l7Views >= 250 && $l7Views < 300) {
                $sBid = 5.0;
            } elseif ($l7Views >= 300 && $l7Views < 350) {
                $sBid = 4.0;
            } elseif ($l7Views >= 350 && $l7Views < 400) {
                $sBid = 3.0;
            } elseif ($l7Views >= 400) {
                $sBid = 2.0;
            } else {
                // Fallback: use ESBID
                $sBid = $esBid !== null ? (float) $esBid : null;
            }
            // Cap sbidValue to maximum of 15
            if ($sBid !== null && $sBid > 15) {
                $sBid = 15.0;
            }

            $ptReports = Ebay2GeneralReport::where('report_range', 'L30')
                ->where('listing_id', $itemId)
                ->get();
            
            // Only include PMT campaigns that have views or valid bids
            if ($views > 0 || ($cbid !== null && $cbid > 0) || ($esBid !== null && $esBid > 0)) {
                if ($ptReports->isEmpty()) {
                    // If no reports but have views/bids, still show PMT data
                    $ptCampaigns[] = [
                        'campaign_name' => 'PMT - ' . ($itemId ?? 'N/A'),
                        'cbid' => $cbid,
                        'es_bid' => $esBid,
                        's_bid' => $sBid,
                        't_views' => $views > 0 ? $views : null,
                        'l7_views' => $l7Views,
                        'scvr' => $scvr,
                    ];
                } else {
                    foreach ($ptReports as $r) {
                        $ptCampaigns[] = [
                            'campaign_name' => 'PMT - ' . ($r->listing_id ?? 'N/A'),
                            'cbid' => $cbid,
                            'es_bid' => $esBid,
                            's_bid' => $sBid,
                            't_views' => $views > 0 ? $views : null,
                            'l7_views' => $l7Views,
                            'scvr' => $scvr,
                        ];
                    }
                }
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
            // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90 (matching utilized page logic)
            // Note: Always use 0.90 multiplier, not 0.80 even if L1_CPC > 1.25
            if ($l1Cpc > 0) {
                $sbid = floor($l1Cpc * 0.90 * 100) / 100;
            } elseif ($l7Cpc > 0) {
                $sbid = floor($l7Cpc * 0.90 * 100) / 100;
            } else {
                $sbid = 0.0;
            }
            // Price cap: If price < $20, cap SBID at 0.20 (matching ebay2-utilized.blade.php)
            if ($price < 20 && $sbid > 0.20) {
                $sbid = 0.20;
            }
        } elseif ($under) {
            // New UB1-based bid increase rules (matching utilized page logic exactly)
            // Get base bid from last_sbid, fallback to L1_CPC or L7_CPC if last_sbid is 0
            $baseBid = 0;
            
            // Parse last_sbid, treat empty/0/null as 0 (matching utilized page logic exactly)
            // Utilized page: if (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw === '0' || lastSbidRaw === 0)
            if ($lastSbid === null || $lastSbid === '' || $lastSbid === '0' || $lastSbid === 0 || $lastSbid <= 0) {
                $baseBid = 0;
            } else {
                $baseBid = (float) $lastSbid;
                if (is_nan($baseBid) || $baseBid <= 0) {
                    $baseBid = 0;
                }
            }
            
            // If last_sbid is 0, use L1_CPC or L7_CPC as fallback (matching utilized page logic)
            // Utilized page: if (baseBid === 0) { baseBid = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0); }
            if ($baseBid === 0 || $baseBid <= 0) {
                $baseBid = ($l1Cpc > 0) ? $l1Cpc : (($l7Cpc > 0) ? $l7Cpc : 0);
            }
            
            if ($baseBid > 0) {
                // If UB1 < 33%: increase bid by 0.10
                if ($ub1 < 33) {
                    $sbid = floor(($baseBid + 0.10) * 100) / 100;
                }
                // If UB1 is 33% to 66%: increase bid by 10%
                elseif ($ub1 >= 33 && $ub1 < 66) {
                    $sbid = floor($baseBid * 1.10 * 100) / 100;
                } else {
                    // For UB1 >= 66%, use base bid (no increase)
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

    /** Take-home from marketplace_percentages EbayTwo. Default 100 if missing. */
    private function ebay1StyleTakeHomePercent(): float
    {
        return MarketplacePercentage::takeHomeDecimal('EbayTwo');
    }
}
