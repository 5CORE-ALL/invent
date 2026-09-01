<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Models\TiktokCampaignReport;
use App\Models\TiktokGmvAd;
use App\Models\ShopifySku;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\ReverbViewData;
use App\Models\TiktokShopDataView;
use App\Models\TiktokTwoShopDataView;
use App\Models\TiktokShopListingStatus;
use App\Models\TiktokSkuCompetitor;
use App\Models\TiktokSkuDailyData;
use App\Services\ChannelPromoPricingService;
use App\Support\TikTokAdsSkuResolver;
use App\Services\LmpSkuGroupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\TikTok2ShopService;
use App\Models\AmazonChannelSummary;
use App\Models\AmazonDataView;
use App\Support\ProductMasterShip;

class TikTokPricingController extends Controller
{
    /**
     * Display TikTok Pricing Tabulator View
     */
    public function tiktokTabulatorView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // marketplace_percentages.marketplace = TiktokShop
        $marketplaceData = MarketplacePercentage::where('marketplace', 'TiktokShop')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 0;

        return view("market-places.tiktok_tabulator_view", [
            "mode" => $mode,
            "demo" => $demo,
            "tiktokPercentage" => $percentage,
            "tiktokPageTitle" => "TikTok 1 Shop - Analytics",
        ]);
    }

    /**
     * Get TikTok Data JSON for Tabulator
     */
    public function tiktok2DataJson(Request $request)
    {
        try {
            $response = $this->getViewTikTokTabularData($request, 'v2');
            $data = json_decode($response->getContent(), true);

            $rows = $data['data'] ?? [];
            $this->deferTiktokSnapshotSaves($rows, 'tiktok2');

            return response()->json($rows);
        } catch (\Exception $e) {
            Log::error('Error fetching TikTok 2 data for Tabulator: ' . $e->getMessage());

            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                return response()->json([
                    'error' => 'TikTok 2 products table not found. Please run: php artisan migrate',
                ], 500);
            }

            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    public function tiktok2TabulatorView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Same margin as TikTok 1: marketplace_percentages.marketplace = TiktokShop
        $marketplaceData = MarketplacePercentage::where('marketplace', 'TiktokShop')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 0;

        return view('market-places.tiktok_tabulator_view', [
            'mode' => $mode,
            'demo' => $demo,
            'tiktokPercentage' => $percentage,
            'tiktokPageTitle' => 'TikTok 2 Shop - Analytics',
            // API-only (no CSV/sheet upload) — same model as TikTok 1.
            'tiktokPricingClientConfig' => [
                'dataJson' => '/tiktok-2-data-json',
                'badgeChart' => route('tiktok2.badge.chart.data'),
                'metricsHistory' => route('tiktok2.metrics.history'),
                'saveSprice' => '/tiktok-2-save-sprice',
                'updateSpriceStatus' => '/tiktok-2-update-sprice-status',
                'saveNrp' => route('tiktok2.save.nrp'),
                'saveLinks' => '/tiktok-2-save-links',
                'syncFromApi' => route('tiktok2.sync.from.api'),
                'connectUrl' => url('/tiktok2/connect'),
                
                'columnGet' => '/tabulator-column-visibility',
                'columnSet' => '/tabulator-column-visibility',
                'columnChannel' => 'tiktok2_pricing',
                'distinctCampaign' => '/tiktok-distinct-campaign-count',
                'summaryChannel' => 'tiktok2',
            ],
        ]);
    }

    public function tiktokDataJson(Request $request)
    {
        try {
            $response = $this->getViewTikTokTabularData($request, 'v1');
            $data = json_decode($response->getContent(), true);

            $rows = $data['data'] ?? [];
            $this->deferTiktokSnapshotSaves($rows, 'tiktok');

            // Tabulator expects an array; totalDistinctCampaigns is fetched separately via /tiktok-distinct-campaign-count
            return response()->json($rows);
        } catch (\Exception $e) {
            Log::error('Error fetching TikTok data for Tabulator: ' . $e->getMessage());
            
            // Check if it's a table doesn't exist error
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                return response()->json([
                    'error' => 'TikTok products table not found. Please run: php artisan migrate'
                ], 500);
            }
            
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Return total distinct campaign count for Ads section badge (matches COUNT(DISTINCT campaign_name) in tiktok_campaign_reports).
     */
    public function tiktokDistinctCampaignCount(Request $request)
    {
        try {
            $totalDistinctCampaigns = (int) DB::table('tiktok_campaign_reports')
                ->whereNotNull('campaign_name')
                ->where('campaign_name', '!=', '')
                ->selectRaw('COUNT(DISTINCT campaign_name) as cnt')
                ->value('cnt');
            return response()->json(['totalDistinctCampaigns' => $totalDistinctCampaigns]);
        } catch (\Exception $e) {
            Log::error('Error fetching TikTok distinct campaign count: ' . $e->getMessage());
            return response()->json(['totalDistinctCampaigns' => 0]);
        }
    }

    /**
     * Return daily badge chart data from saved TikTok snapshots.
     */
    public function tiktokBadgeChartData(Request $request)
    {
        try {
            $metric = (string) $request->input('metric', 'total_pft');
            $days = max(0, intval($request->input('days', 30)));

            $metricMap = [
                'total_pft' => 'total_pft',
                'total_sales' => 'total_sales',
                'avg_gpft' => 'avg_gpft',
                'avg_price' => 'avg_price',
                'total_l30' => 'total_l30',
                'zero_sold_count' => 'zero_sold_count',
                'sold_count' => 'sold_count',
                'avg_dil' => 'avg_dil',
                'total_cogs' => 'total_cogs',
                'avg_roi' => 'avg_roi',
                'missing_count' => 'missing_count',
                'map_count' => 'map_count',
                'nmap_count' => 'nmap_count',
                'inv_tt_stock_count' => 'inv_tt_stock_count',
                'total_spend_30' => 'total_spend_30',
                'total_spend_1' => 'total_spend_1',
                'total_ads_views_30' => 'total_ads_views_30',
                'total_ads_clicks_30' => 'total_ads_clicks_30',
                'total_ads_views_1' => 'total_ads_views_1',
                'total_ads_clicks_1' => 'total_ads_clicks_1',
                'ads_cvr_30' => 'ads_cvr_30',
                'ads_roas' => 'ads_roas',
                'avg_target_roas' => 'avg_target_roas',
                'ads_acos_pct' => 'ads_acos_pct',
                'total_gmv_ad_sold_l30' => 'total_gmv_ad_sold_l30',
                'total_gmv_ad_sold_l1' => 'total_gmv_ad_sold_l1',
                'total_gmv_ad_sales_l30' => 'total_gmv_ad_sales_l30',
                'total_gmv_ad_sales_l1' => 'total_gmv_ad_sales_l1',
                'total_gmv_spend_l30' => 'total_gmv_spend_l30',
                'total_gmv_spend_l1' => 'total_gmv_spend_l1',
                'total_gmv_budget' => 'total_gmv_budget',
            ];

            $summaryKey = $metricMap[$metric] ?? null;
            if (!$summaryKey) {
                return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
            }

            $ch = (string) $request->input('channel', 'tiktok');
            if (! in_array($ch, ['tiktok', 'tiktok2'], true)) {
                $ch = 'tiktok';
            }

            $query = AmazonChannelSummary::where('channel', $ch)
                ->orderBy('snapshot_date', 'asc');

            if ($days > 0) {
                $startDate = now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $startDate);
            }

            $rows = $query->get(['snapshot_date', 'summary_data']);

            $data = [];
            foreach ($rows as $row) {
                $summaryData = is_array($row->summary_data)
                    ? $row->summary_data
                    : (json_decode($row->summary_data ?? '{}', true) ?: []);

                $value = floatval($summaryData[$summaryKey] ?? 0);
                $data[] = [
                    'date' => optional($row->snapshot_date)->format('M d'),
                    'value' => $value,
                ];
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Error fetching TikTok badge chart data: ' . $e->getMessage());
            return response()->json(['success' => false, 'data' => []], 500);
        }
    }

    /**
     * Daily badge chart for TikTok 2 pricing (always uses channel tiktok2).
     */
    public function tiktok2BadgeChartData(Request $request)
    {
        $request->merge(['channel' => 'tiktok2']);

        return $this->tiktokBadgeChartData($request);
    }

    /**
     * Per-SKU Price history for /tiktok-pricing (same role as /ebay-metrics-history).
     */
    public function tiktokMetricsHistory(Request $request)
    {
        return $this->getTiktokMetricsHistory($request, 'tiktok');
    }

    /**
     * Per-SKU Price history for /tiktok-2-pricing.
     */
    public function tiktok2MetricsHistory(Request $request)
    {
        return $this->getTiktokMetricsHistory($request, 'tiktok2');
    }

    /**
     * Return daily price points for one SKU from tiktok_sku_daily_data,
     * overlaying today's live product price (California calendar day).
     */
    private function getTiktokMetricsHistory(Request $request, string $channel = 'tiktok')
    {
        if (! in_array($channel, ['tiktok', 'tiktok2'], true)) {
            $channel = 'tiktok';
        }

        $days = (int) $request->input('days', 30);
        $sku = trim((string) $request->input('sku', ''));
        $skuNorm = $sku !== '' ? strtoupper($sku) : null;

        if (! $skuNorm) {
            return response()->json([]);
        }

        $endDate = Carbon::now('America/Los_Angeles')->startOfDay();
        $startDate = null;
        if ($days !== 0) {
            if ($days < 7) {
                $days = 7;
            }
            $startDate = $endDate->copy()->subDays($days - 1);
        }

        $dataByDate = [];

        try {
            if (Schema::hasTable('tiktok_sku_daily_data')) {
                $query = TiktokSkuDailyData::query()
                    ->where('channel', $channel)
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
                    ->where('record_date', '<=', $endDate->toDateString())
                    ->orderBy('record_date', 'asc');
                if ($startDate) {
                    $query->where('record_date', '>=', $startDate->toDateString());
                }

                foreach ($query->get() as $record) {
                    $data = is_array($record->daily_data)
                        ? $record->daily_data
                        : (json_decode($record->daily_data ?? '{}', true) ?: []);
                    $dateKey = Carbon::parse($record->record_date)->format('Y-m-d');
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => Carbon::parse($record->record_date)->format('M d'),
                        'price' => round((float) ($data['price'] ?? 0), 2),
                        'stock' => (int) ($data['stock'] ?? 0),
                        'sold' => (int) ($data['sold'] ?? 0),
                        'tt_l30' => (int) ($data['tt_l30'] ?? $data['sold'] ?? 0),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TikTok metrics history read failed: ' . $e->getMessage());
        }

        // Overlay live product price for California today (matches Prc column).
        try {
            $liveModel = $channel === 'tiktok2' ? TikTokProductTwo::class : TikTokProduct::class;
            $live = $liveModel::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
                ->first();
            if ($live) {
                $todayKey = $endDate->toDateString();
                $dataByDate[$todayKey] = [
                    'date' => $todayKey,
                    'date_formatted' => $endDate->format('M d'),
                    'price' => round((float) ($live->price ?? 0), 2),
                    'stock' => (int) ($live->stock ?? 0),
                    'sold' => (int) ($live->sold ?? 0),
                    'tt_l30' => (int) ($live->sold ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('TikTok live price overlay failed: ' . $e->getMessage());
        }

        ksort($dataByDate);

        return response()->json(array_values($dataByDate));
    }

    /**
     * L30 sold from tiktok_orders — last 30 California calendar days (America/Los_Angeles).
     */
    private function getTiktokL30SoldDataBySku(): array
    {
        return \App\Models\TiktokOrder::soldQtyL30(null, 30);
    }

    /**
     * L30 sold for TikTok 2 — API only (tiktok2_orders). No sheet/CSV fallback.
     */
    private function getTiktokTwoL30SoldDataBySku(): array
    {
        if (! \App\Models\Tiktok2Order::tableReady()) {
            return [];
        }

        return \App\Models\Tiktok2Order::soldQtyL30(null, 30);
    }

    /**
     * Get TikTok Tabular Data (similar to Reverb)
     */
    public function getViewTikTokTabularData(Request $request, string $variant = 'v1')
    {
        $isTiktokTwo = $variant === 'v2';
        // TikTok 1 & TikTok 2 → same margin: marketplace_percentages.marketplace = TiktokShop
        $marketplaceData = MarketplacePercentage::where('marketplace', 'TiktokShop')->first();
        $percentage = $marketplaceData ? (float) $marketplaceData->percentage : 0;
        $percentageValue = $percentage / 100;

        // Child SKUs only — avoid ProductMaster::all() (also loaded PARENT rows).
        $productMasterRows = ProductMaster::query()
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->get()
            ->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck("sku")->toArray();

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

        $promoChannel = $isTiktokTwo ? 'tiktok2' : 'tiktok';
        $promoService = app(ChannelPromoPricingService::class);
        $promoMap = $promoService->mapForSkus($promoChannel, $skus);
        
        // Create uppercase version for TikTok products lookup
        $skusUpper = array_map('strtoupper', $skus);

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Fetch TikTok (or TikTok 2) product data - use uppercase SKUs for query and normalize keys
        if ($isTiktokTwo) {
            $tiktokData = TikTokProductTwo::whereIn("sku", $skusUpper)
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
        } else {
            $tiktokData = TikTokProduct::whereIn("sku", $skusUpper)
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
        }

        // Fetch reverb view data for SPRICE
        $reverbViewData = ReverbViewData::whereIn("sku", $skus)->get()->keyBy("sku");
        // TikTok 1 / 2 shop JSON (same merge model as eBay ebay_data_view: one row per SKU, value JSON).
        $ttShopDataByNormSku = [];
        $ttShopRows = $isTiktokTwo
            ? TiktokTwoShopDataView::query()->get(['sku', 'value'])
            : TiktokShopDataView::query()->get(['sku', 'value']);
        foreach ($ttShopRows as $row) {
            $k = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $row->sku)));
            if ($k !== '') {
                $ttShopDataByNormSku[$k] = $row;
            }
        }

        // Buyer / Seller links from TiktokShopListingStatus.value JSON (keyed by normalized SKU) — TikTok 1 only
        $ttListingLinksByNormSku = [];
        if (!$isTiktokTwo) {
            $linkRows = TiktokShopListingStatus::query()->get(['sku', 'value']);
            foreach ($linkRows as $lr) {
                $k = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $lr->sku)));
                if ($k !== '') {
                    $ttListingLinksByNormSku[$k] = $lr;
                }
            }
        }

        // L30: TikTok 1 → tiktok_orders API; TikTok 2 → tiktok2_orders API (no sheet)
        $soldData = $isTiktokTwo
            ? $this->getTiktokTwoL30SoldDataBySku()
            : $this->getTiktokL30SoldDataBySku();

        // Campaign map and metrics for utilized/ads columns (same as tiktok/utilized)
        $campaignMapBySku = [];
        $campaignMetricsBySku = [];
        try {
            $campaignBase = TiktokCampaignReport::query()
                ->whereIn('report_range', ['L30', 'L7'])
                ->where('creative_type', 'Product card')
                ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                ->whereNotNull('product_id')->where('product_id', '!=', '')
                ->get([
                    'report_range', 'campaign_name', 'product_id', 'cost',
                    'product_ad_clicks', 'gross_revenue', 'sku_orders',
                    'roi', 'in_roas', 'custom_status', 'budget',
                ]);
            $allCampaigns = $campaignBase;
            $summarizeCampaigns = function ($group) {
                $first = $group->first();
                return (object) [
                    'sku_upper' => strtoupper(trim((string) $group->first()->campaign_name)),
                    'total_cost' => $group->sum('cost'),
                    'total_clicks' => $group->sum('product_ad_clicks'),
                    'total_revenue' => $group->sum('gross_revenue'),
                    'total_sku_orders' => $group->sum('sku_orders'),
                    'avg_roi' => $first && $first->roi !== null ? (float) $first->roi : 0,
                    'avg_in_roas' => $first && $first->in_roas !== null ? (float) $first->in_roas : 0,
                    'custom_status' => $first && $first->custom_status ? $first->custom_status : null,
                    'budget' => $first && $first->budget !== null ? (float) $first->budget : null,
                ];
            };
            $campaignMetricsL30 = $campaignBase->where('report_range', 'L30')
                ->groupBy(fn ($item) => TikTokAdsSkuResolver::skuFor($item->product_id, $item->campaign_name))
                ->map($summarizeCampaigns);
            $campaignMetricsL7 = $campaignBase->where('report_range', 'L7')
                ->groupBy(fn ($item) => TikTokAdsSkuResolver::skuFor($item->product_id, $item->campaign_name))
                ->map($summarizeCampaigns);

            foreach ($campaignMetricsL30 as $skuUpper => $metrics) {
                $campaignMetricsBySku[$skuUpper] = [
                    'cost' => (float)($metrics->total_cost ?? 0),
                    'cost_l30' => (float)($metrics->total_cost ?? 0),
                    'cost_l7' => 0,
                    'clicks' => (int)($metrics->total_clicks ?? 0),
                    'revenue' => (float)($metrics->total_revenue ?? 0),
                    'sku_orders' => (int)($metrics->total_sku_orders ?? 0),
                    'roi' => (float)($metrics->avg_roi ?? 0),
                    'in_roas' => (float)($metrics->avg_in_roas ?? 0),
                    'custom_status' => $metrics->custom_status ?? null,
                    'budget' => $metrics->budget !== null ? (float)$metrics->budget : null,
                ];
            }
            foreach ($campaignMetricsL7 as $skuUpper => $metrics) {
                if (isset($campaignMetricsBySku[$skuUpper])) {
                    $campaignMetricsBySku[$skuUpper]['cost'] += (float)($metrics->total_cost ?? 0);
                    $campaignMetricsBySku[$skuUpper]['clicks'] += (int)($metrics->total_clicks ?? 0);
                    $campaignMetricsBySku[$skuUpper]['revenue'] += (float)($metrics->total_revenue ?? 0);
                    $campaignMetricsBySku[$skuUpper]['sku_orders'] += (int)($metrics->total_sku_orders ?? 0);
                    if ($campaignMetricsBySku[$skuUpper]['roi'] == 0) $campaignMetricsBySku[$skuUpper]['roi'] = (float)($metrics->avg_roi ?? 0);
                    if ($campaignMetricsBySku[$skuUpper]['in_roas'] == 0) $campaignMetricsBySku[$skuUpper]['in_roas'] = (float)($metrics->avg_in_roas ?? 0);
                    if (empty($campaignMetricsBySku[$skuUpper]['custom_status'])) $campaignMetricsBySku[$skuUpper]['custom_status'] = $metrics->custom_status ?? null;
                    if ($campaignMetricsBySku[$skuUpper]['budget'] === null && $metrics->budget !== null) $campaignMetricsBySku[$skuUpper]['budget'] = (float)$metrics->budget;
                } else {
                    // For SKUs only in L7, use L7 cost (fallback only)
                    $campaignMetricsBySku[$skuUpper] = [
                        'cost' => (float)($metrics->total_cost ?? 0),
                        'cost_l30' => 0,
                        'cost_l7' => (float)($metrics->total_cost ?? 0),
                        'clicks' => (int)($metrics->total_clicks ?? 0),
                        'revenue' => (float)($metrics->total_revenue ?? 0),
                        'sku_orders' => (int)($metrics->total_sku_orders ?? 0),
                        'roi' => (float)($metrics->avg_roi ?? 0),
                        'in_roas' => (float)($metrics->avg_in_roas ?? 0),
                        'custom_status' => $metrics->custom_status ?? null,
                        'budget' => $metrics->budget !== null ? (float)$metrics->budget : null,
                    ];
                }
            }
            foreach ($allCampaigns as $campaign) {
                $cn = TikTokAdsSkuResolver::skuFor($campaign->product_id, $campaign->campaign_name);
                if ($cn === '') {
                    continue;
                }
                if (!isset($campaignMapBySku[$cn])) $campaignMapBySku[$cn] = [];
                $label = trim((string) ($campaign->campaign_name ?? '')) ?: $cn;
                if (!in_array($label, $campaignMapBySku[$cn])) $campaignMapBySku[$cn][] = $label;
            }
        } catch (\Throwable $e) {
            Log::warning('TikTok pricing: campaign/ads data fetch failed: ' . $e->getMessage());
        }

        // Unfiltered tiktok_campaign_reports (same source as /tiktok-1-ads-raw-data).
        $rawAdsBySku = $isTiktokTwo ? [] : $this->tiktok1RawAdsMetricsBySku();

        // GMV ads from cache only — do not call TikTok Shop API on page load.

        // GMV ads by SKU (API L30/L1 when present, else latest upload batch).
        $gmvAdsBySku = $isTiktokTwo ? [] : $this->tiktok1GmvAdsMetricsBySku();

        // LMP (Lowest Marketplace Price) competitor lookup from tiktok_sku_competitors.
        // Mirrors the Amazon flow in OverallAmazonController: keep one bulk query
        // and attach lmp_price / lmp_entries / lmp_entries_total to each SKU row
        // so the front-end can render the LMP column and modal without N+1.
        $lmpDetailsLookup = collect();
        try {
            $lmpLookups = TiktokSkuCompetitor::buildGroupedLookup('tiktok');
            $lmpDetailsLookup = $lmpLookups['details'];
        } catch (\Throwable $e) {
            Log::warning('Could not fetch LMP data from tiktok_sku_competitors: ' . $e->getMessage());
        }

        // Sku Link LMP — same shared lmp_sku_links groups as /shein-pricing / ebay-tabulator-view
        $lmpGroupService = new LmpSkuGroupService();
        try {
            $prepSkus = [];
            foreach ($productMasterRows as $pm) {
                $pmSku = trim((string) ($pm->sku ?? ''));
                if ($pmSku !== '' && stripos($pmSku, 'PARENT') === false) {
                    $prepSkus[] = $pmSku;
                }
            }
            $lmpGroupService->prepareForSkus($prepSkus);
        } catch (\Throwable $e) {
            Log::warning('LmpSkuGroupService prepare failed (TikTok): ' . $e->getMessage());
        }

        // Process data
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, "PARENT") !== false;

            // Initialize the data structure
            $processedItem = [
                "SL No." => $slNo++,
                "Parent" => $productMaster->parent ?? null,
                "(Child) sku" => $sku,
                "is_parent" => $isParent,
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem["LP_productmaster"] = $values["lp"] ?? 0;
            // Shipping Master "Ship" column (ship_base + charges). Never ship_bb / BB Ship.
            $ttShip = ProductMasterShip::forPricing(is_array($values) ? $values : [], $productMaster);
            $processedItem["Ship_productmaster"] = $ttShip;
            $processedItem["TT Ship"] = $ttShip;
            $processedItem["COGS"] = $values["cogs"] ?? 0;
            
            // Image path
            $processedItem["image_path"] = null;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem["INV"] = $shopifyItem->inv ?? 0;
                $processedItem["L30"] = $shopifyItem->quantity ?? 0;
                $processedItem["image_path"] = $shopifyItem->image_src ?? ($values["image_path"] ?? ($productMaster->image_path ?? null));
            } else {
                $processedItem["INV"] = 0;
                $processedItem["L30"] = 0;
                $processedItem["image_path"] = $values["image_path"] ?? ($productMaster->image_path ?? null);
            }

            // Add data from tiktok_products if available
            $skuUpper = strtoupper($sku);
            if (isset($tiktokData[$skuUpper])) {
                $tiktokItem = $tiktokData[$skuUpper];
                $processedItem["TT Price"] = $tiktokItem->price ?? 0;
                $processedItem["TT Stock"] = $tiktokItem->stock ?? 0;
                $viewCounts = $this->tiktokListingViewCounts($tiktokItem);
                $processedItem["video_views"] = $viewCounts['video_views'];
                $processedItem["ads_views"] = $viewCounts['ads_views'];
                $processedItem["affl_views"] = $viewCounts['affl_views'];
                $processedItem["Missing"] = ''; // SKU exists in TikTok
            } else {
                $processedItem["TT Price"] = 0;
                $processedItem["TT Stock"] = 0;
                $processedItem["video_views"] = 0;
                $processedItem["ads_views"] = 0;
                $processedItem["affl_views"] = 0;
                $processedItem["Missing"] = 'M'; // SKU NOT in TikTok - mark as Missing
            }

            // Get L30 sold from tiktok_orders (API)
            $processedItem["TT L30"] = isset($soldData[$skuUpper]) ? $soldData[$skuUpper] : 0;

            // MAP: |INV − TT Stock| ≤ 3 → Map; else signed N Map. Missing L rows have no MAP.
            // Negative Shopify INV + marketplace stock 0 = perfect Map.
            $inv = (float) $processedItem["INV"];
            $ttStock = (float) $processedItem["TT Stock"];
            $delta = $inv - $ttStock;
            if ($processedItem["Missing"] === 'M') {
                $processedItem["MAP"] = '';
            } elseif ($inv < 0 && $ttStock == 0.0) {
                $processedItem["MAP"] = 'Map';
            } elseif (abs($delta) <= 3) {
                $processedItem["MAP"] = 'Map';
            } else {
                $processedItem["MAP"] = 'N Map|'.sprintf('%+g', $delta);
            }

            // Get SPRICE, SGPFT, SPFT, SROI, NR from per-channel shop data view; fallback to reverb_view_data
            $processedItem["SPRICE"] = 0;
            $processedItem["SGPFT"] = 0;
            $processedItem["SPFT"] = 0;
            $processedItem["SROI"] = 0;
            $processedItem["has_custom_sprice"] = false;
            $processedItem["SPRICE_STATUS"] = null;
            $processedItem["SPRICE_STATUS_UPDATED_AT"] = null;
            $processedItem["SPRICE_PUSHED_VALUE"] = null;
            $processedItem["SPRICE_PUSHED_BY"] = null;
            $tiktokValArr = [];

            $skuNorm = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $sku)));

            // Buyer / Seller links default (TikTok 2 reads from its shop data view value below)
            $processedItem['B Link'] = '';
            $processedItem['S Link'] = '';
            if (!$isTiktokTwo) {
                $linkRecord = $ttListingLinksByNormSku[$skuNorm] ?? null;
                $linkVal = ($linkRecord && is_array($linkRecord->value))
                    ? $linkRecord->value
                    : ($linkRecord ? (json_decode($linkRecord->value, true) ?: []) : []);
                $processedItem['B Link'] = $linkVal['buyer_link'] ?? '';
                $processedItem['S Link'] = $linkVal['seller_link'] ?? '';
            }

            $ttShopRow = $ttShopDataByNormSku[$skuNorm] ?? null;
            if ($ttShopRow) {
                $tiktokVal = $ttShopRow->value;
                $tiktokValArr = is_array($tiktokVal) ? $tiktokVal : (json_decode($tiktokVal ?? '{}', true) ?: []);
                if ($isTiktokTwo) {
                    $processedItem['B Link'] = $tiktokValArr['buyer_link'] ?? '';
                    $processedItem['S Link'] = $tiktokValArr['seller_link'] ?? '';
                }
                $processedItem["SPRICE"] = isset($tiktokValArr["SPRICE"]) ? floatval($tiktokValArr["SPRICE"]) : 0;
                $processedItem["SGPFT"] = isset($tiktokValArr["SGPFT"]) ? floatval($tiktokValArr["SGPFT"]) : 0;
                $processedItem["SPFT"] = isset($tiktokValArr["SPFT"]) ? floatval(str_replace("%", "", $tiktokValArr["SPFT"])) : 0;
                $processedItem["SROI"] = isset($tiktokValArr["SROI"]) ? floatval(str_replace("%", "", $tiktokValArr["SROI"])) : 0;
                // Shop JSON often stores video_views/ads_views/affl_views as 0 from SPRICE saves.
                // Never let those zeros wipe live listing views from tiktok_products.views.
                if (intval($tiktokValArr['video_views'] ?? 0) > 0) {
                    $processedItem["video_views"] = intval($tiktokValArr["video_views"]);
                }
                if (intval($tiktokValArr['ads_views'] ?? 0) > 0) {
                    $processedItem["ads_views"] = intval($tiktokValArr["ads_views"]);
                }
                if (intval($tiktokValArr['affl_views'] ?? 0) > 0) {
                    $processedItem["affl_views"] = intval($tiktokValArr["affl_views"]);
                }
                if (array_key_exists('NR', $tiktokValArr)) {
                    $nrVal = $tiktokValArr['NR'];
                    $processedItem["NR"] = is_bool($nrVal) ? ($nrVal ? 'RA' : 'NRA') : (string) $nrVal;
                }
                if (array_key_exists('variation_req', $tiktokValArr)) {
                    $processedItem["variation_req"] = (string) $tiktokValArr['variation_req'];
                }
                if (array_key_exists('video_req', $tiktokValArr)) {
                    $processedItem["video_req"] = (string) $tiktokValArr['video_req'];
                }
                if (array_key_exists('video_uploaded', $tiktokValArr)) {
                    $v = $tiktokValArr['video_uploaded'];
                    $processedItem["video_uploaded"] = ($v === true || $v === 1 || $v === '1') ? 1 : 0;
                }
                $processedItem["SPRICE_STATUS"] = $tiktokValArr["SPRICE_STATUS"] ?? null;
                $processedItem["SPRICE_STATUS_UPDATED_AT"] = $tiktokValArr["SPRICE_STATUS_UPDATED_AT"] ?? null;
                $processedItem["SPRICE_PUSHED_VALUE"] = isset($tiktokValArr["SPRICE_PUSHED_VALUE"])
                    ? floatval($tiktokValArr["SPRICE_PUSHED_VALUE"])
                    : null;
                $processedItem["SPRICE_PUSHED_BY"] = $tiktokValArr["SPRICE_PUSHED_BY"] ?? null;
                $processedItem["has_custom_sprice"] = floatval($processedItem["SPRICE"] ?? 0) > 0;
            }
            if (!isset($processedItem["NR"]) && isset($reverbViewData[$sku])) {
                $valuesArr = $reverbViewData[$sku]->values ?: [];
                $processedItem["NR"] = $valuesArr["NR"] ?? 'RA';
            }
            if (!isset($processedItem["NR"])) $processedItem["NR"] = 'RA';
            if (!isset($processedItem["variation_req"])) $processedItem["variation_req"] = 'Not Req';
            if (!isset($processedItem["video_req"])) $processedItem["video_req"] = 'Not Req';
            if (!isset($processedItem["video_uploaded"])) $processedItem["video_uploaded"] = 0;

            // NRP (REQ / NR / LATER) from shop JSON: prefer NRP; legacy REQ|NR may live under NR (ads use RA/NRA/LATER).
            $processedItem['nrp'] = $this->tiktokNrpStatusFromShopValue($tiktokValArr);

            if (! $ttShopRow && isset($reverbViewData[$sku])) {
                $viewData = $reverbViewData[$sku];
                $valuesArr = $viewData->values ?: [];
                $processedItem["SPRICE"] = isset($valuesArr["SPRICE"]) ? floatval($valuesArr["SPRICE"]) : 0;
                $processedItem["SGPFT"] = isset($valuesArr["SGPFT"]) ? floatval($valuesArr["SGPFT"]) : 0;
                $processedItem["SPFT"] = isset($valuesArr["SPFT"]) ? floatval(str_replace("%", "", $valuesArr["SPFT"])) : 0;
                $processedItem["SROI"] = isset($valuesArr["SROI"]) ? floatval(str_replace("%", "", $valuesArr["SROI"])) : 0;
                $processedItem["SPRICE_STATUS"] = $valuesArr["SPRICE_STATUS"] ?? null;
                $processedItem["SPRICE_STATUS_UPDATED_AT"] = $valuesArr["SPRICE_STATUS_UPDATED_AT"] ?? null;
                $processedItem["SPRICE_PUSHED_VALUE"] = isset($valuesArr["SPRICE_PUSHED_VALUE"])
                    ? floatval($valuesArr["SPRICE_PUSHED_VALUE"])
                    : null;
                $processedItem["SPRICE_PUSHED_BY"] = $valuesArr["SPRICE_PUSHED_BY"] ?? null;
                $processedItem["has_custom_sprice"] = floatval($processedItem["SPRICE"] ?? 0) > 0;
            }

            // Calculate profit metrics
            $processedItem["percentage"] = $percentageValue;

            $price = floatval($processedItem["TT Price"]);
            $lp = floatval($processedItem["LP_productmaster"]);
            $ship = floatval($processedItem["Ship_productmaster"]);

            // GPFT%
            if ($price > 0) {
                $gpft_percentage = (($price * $percentageValue - $lp - $ship) / $price) * 100;
                $processedItem["GPFT%"] = round($gpft_percentage, 2);
            } else {
                $processedItem["GPFT%"] = 0;
            }

            // TACOS% and PFT % calculated after spend is set below

            // ROI%
            if ($lp > 0) {
                $roi_percentage = (($price * $percentageValue - $lp - $ship) / $lp) * 100;
                $processedItem["ROI%"] = round($roi_percentage, 2);
            } else {
                $processedItem["ROI%"] = 0;
            }

            // Profit
            $processedItem["Profit"] = ($price * $percentageValue) - $lp - $ship;

            // Sales L30
            $processedItem["Sales L30"] = $price * $processedItem["TT L30"];

            // Dil%
            $inv = $processedItem["INV"];
            $l30 = $processedItem["L30"];
            $processedItem["TT Dil%"] = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0;

            // Ads/utilized columns (for "Show Utilized Columns" button)
            $skuUpper = strtoupper(trim($sku));
            $hasCampaign = isset($campaignMapBySku[$skuUpper]) && !empty($campaignMapBySku[$skuUpper]);
            $processedItem["campaign_name"] = $hasCampaign ? implode(', ', array_unique($campaignMapBySku[$skuUpper])) : '';
            $processedItem["hasCampaign"] = $hasCampaign;
            $metrics = $campaignMetricsBySku[$skuUpper] ?? [
                'cost' => 0, 'cost_l30' => 0, 'cost_l7' => 0, 'clicks' => 0, 'revenue' => 0, 'sku_orders' => 0, 'roi' => 0, 'in_roas' => 0, 'custom_status' => null, 'budget' => null,
            ];
            $outRoas = (float)($metrics['roi'] ?? 0);
            $inRoas = (float)($metrics['in_roas'] ?? 0);
            $customStatus = $metrics['custom_status'] ?? null;
            if ($hasCampaign && (empty($customStatus) || $customStatus === null)) $customStatus = 'Active';
            elseif (empty($customStatus) || $customStatus === null) $customStatus = 'Not Created';
            $processedItem["NR"] = $processedItem["NR"] ?? 'RA';
            // Display-only: do not write NRA on every /tiktok-data-json load (was N+1 firstOrNew/save).
            $inv = (float)($processedItem["INV"] ?? 0);
            if ($inv <= 0) {
                $processedItem["NR"] = 'NRA';
            }
            $processedItem["ads_price"] = $processedItem["TT Price"] ?? 0;
            $processedItem["budget"] = isset($metrics['budget']) && $metrics['budget'] !== null ? round((float)$metrics['budget'], 2) : null;
            $processedItem["spend"] = round((float)($metrics['cost'] ?? 0), 2);
            $processedItem["spend_l30"] = round((float)($metrics['cost_l30'] ?? 0), 2);
            $processedItem["spend_l7"] = round((float)($metrics['cost_l7'] ?? 0), 2);
            $rawAds = $rawAdsBySku[$skuUpper] ?? [
                'spend_30' => 0.0,
                'spend_1' => 0.0,
                'ads_views_30' => 0,
                'ads_clicks_30' => 0,
                'ads_views_1' => 0,
                'ads_clicks_1' => 0,
                'ads_sold_30' => 0,
                'ads_cvr_30' => 0.0,
                'ads_revenue_30' => 0.0,
                'ads_roas' => 0.0,
                'target_roas' => 0.0,
                'ads_acos_pct' => 0.0,
            ];
            $processedItem['spend_30'] = $rawAds['spend_30'];
            $processedItem['spend_1'] = $rawAds['spend_1'];
            $processedItem['ads_views_30'] = $rawAds['ads_views_30'];
            $processedItem['ads_clicks_30'] = $rawAds['ads_clicks_30'];
            $processedItem['ads_views_1'] = $rawAds['ads_views_1'];
            $processedItem['ads_clicks_1'] = $rawAds['ads_clicks_1'];
            $processedItem['ads_sold_30'] = $rawAds['ads_sold_30'];
            $processedItem['ads_cvr_30'] = $rawAds['ads_cvr_30'];
            $processedItem['ads_revenue_30'] = $rawAds['ads_revenue_30'];
            $processedItem['ads_roas'] = $rawAds['ads_roas'];
            $processedItem['target_roas'] = $rawAds['target_roas'];
            $processedItem['ads_acos_pct'] = $rawAds['ads_acos_pct'];
            $gmvAds = $gmvAdsBySku[$skuUpper] ?? [
                'gmv_ad_sold_l30' => 0,
                'gmv_ad_sold_l1' => 0,
                'gmv_ad_sales_l30' => 0.0,
                'gmv_ad_sales_l1' => 0.0,
                'gmv_spend_l30' => 0.0,
                'gmv_spend_l1' => 0.0,
                'gmv_budget' => null,
                'gmv_status' => null,
                'gmv_approval' => null,
            ];
            $processedItem['gmv_ad_sold_l30'] = $gmvAds['gmv_ad_sold_l30'];
            $processedItem['gmv_ad_sold_l1'] = $gmvAds['gmv_ad_sold_l1'];
            $processedItem['gmv_ad_sales_l30'] = $gmvAds['gmv_ad_sales_l30'];
            $processedItem['gmv_ad_sales_l1'] = $gmvAds['gmv_ad_sales_l1'];
            $processedItem['gmv_spend_l30'] = $gmvAds['gmv_spend_l30'];
            $processedItem['gmv_spend_l1'] = $gmvAds['gmv_spend_l1'];
            $processedItem['gmv_budget'] = $gmvAds['gmv_budget'];
            $processedItem['gmv_status'] = $gmvAds['gmv_status'];
            $processedItem['gmv_approval'] = $gmvAds['gmv_approval'];
            if ((float) $processedItem['spend_30'] <= 0 && (float) $gmvAds['gmv_spend_l30'] > 0) {
                $processedItem['spend_30'] = $gmvAds['gmv_spend_l30'];
            }
            if ((float) $processedItem['spend_1'] <= 0 && (float) $gmvAds['gmv_spend_l1'] > 0) {
                $processedItem['spend_1'] = $gmvAds['gmv_spend_l1'];
            }
            if ((int) $processedItem['ads_sold_30'] <= 0 && (int) $gmvAds['gmv_ad_sold_l30'] > 0) {
                $processedItem['ads_sold_30'] = $gmvAds['gmv_ad_sold_l30'];
            }
            if ((float) ($processedItem['ads_revenue_30'] ?? 0) <= 0 && (float) $gmvAds['gmv_ad_sales_l30'] > 0) {
                $processedItem['ads_revenue_30'] = $gmvAds['gmv_ad_sales_l30'];
            }
            // Listing CVR% = TT L30 ÷ T views (video + ads + affl), same as /tiktok-pricing CVR filter.
            $tViews = (int) ($processedItem['video_views'] ?? 0)
                + (int) ($processedItem['ads_views'] ?? 0)
                + (int) ($processedItem['affl_views'] ?? 0);
            $processedItem['t_views'] = $tViews;
            $ttL30ForCvr = (float) ($processedItem['TT L30'] ?? 0);
            $listingCvr = $tViews > 0 ? round(($ttL30ForCvr / $tViews) * 100, 2) : 0.0;
            $processedItem['cvr'] = $listingCvr;
            $processedItem['CVR%'] = $listingCvr;

            // TACOS% = (spend / (TT L30 * TT Price)) * 100
            $spend = (float)$processedItem["spend"];
            $ttL30 = (float)($processedItem["TT L30"] ?? 0);
            $ttPrice = (float)($processedItem["TT Price"] ?? 0);
            $salesValue = $ttL30 * $ttPrice;
            $processedItem["TACOS%"] = $salesValue > 0 ? round(($spend / $salesValue) * 100, 2) : ($spend > 0 ? 100 : 0);
            $processedItem["PFT %"] = round($processedItem["GPFT%"] - $processedItem["TACOS%"], 2);
            // SPFT = SGPFT - TACOS%
            $processedItem["SPFT"] = round($processedItem["SGPFT"] - $processedItem["TACOS%"], 2);
            $processedItem["ad_sold"] = (int)($metrics['sku_orders'] ?? 0);
            $processedItem["ad_clicks"] = (int)($metrics['clicks'] ?? 0);
            $adSold = $processedItem["ad_sold"];
            $adClicks = $processedItem["ad_clicks"];
            $processedItem["ad_cvr_pct"] = $adClicks > 0 ? round(($adSold / $adClicks) * 100, 2) : null;
            $processedItem["acos"] = $outRoas > 0 ? round(100 / $outRoas) : 0;
            $processedItem["out_roas"] = round($outRoas, 2);
            $processedItem["in_roas"] = round($inRoas, 2);
            $processedItem["status"] = $customStatus;

            // Attach LMP (lowest competitor on TikTok Shop) merged across Sku Link LMP group.
            // Same shared lmp_sku_links groups as /shein-pricing.
            $linkedLmpSkus = $isParent
                ? []
                : $this->tiktokLinkedLmpSkusFor($lmpGroupService, (string) $sku);
            $processedItem['linked_lmp_skus'] = $linkedLmpSkus;

            // Std Prc — shared amazon_data_view.STANDARD_PRICE; inherit from Sku Link LMP siblings
            $stdPrc = $amazonStandardPrices[strtoupper(trim((string) $sku))] ?? null;
            if ($stdPrc === null && ! empty($linkedLmpSkus)) {
                foreach ($linkedLmpSkus as $linkedSku) {
                    $linkedKey = strtoupper(trim((string) $linkedSku));
                    if ($linkedKey !== '' && isset($amazonStandardPrices[$linkedKey])) {
                        $stdPrc = $amazonStandardPrices[$linkedKey];
                        break;
                    }
                }
            }
            $processedItem['STANDARD_PRICE'] = $stdPrc;
            $processedItem['Price'] = $processedItem['TT Price'] ?? 0;
            $processedItem = $promoService->applyToRow($processedItem, $promoMap, (string) $sku);

            $mergedLmpEntries = collect();
            $seenLmp = [];
            $skusForLmp = $linkedLmpSkus !== [] ? $linkedLmpSkus : [$sku];
            foreach ($skusForLmp as $linkedSku) {
                $linkedKey = TiktokSkuCompetitor::normalizeSkuKey($linkedSku);
                $groupEntries = $lmpDetailsLookup->get($linkedKey);
                if (!$groupEntries instanceof \Illuminate\Support\Collection) {
                    continue;
                }
                foreach ($groupEntries as $entry) {
                    $dedupeKey = ((string) ($entry->id ?? '')) . '|'
                        . ((string) ($entry->product_id ?? '')) . '|'
                        . strtoupper(trim((string) ($entry->product_link ?? '')));
                    if (isset($seenLmp[$dedupeKey])) {
                        continue;
                    }
                    $seenLmp[$dedupeKey] = true;
                    $mergedLmpEntries->push($entry);
                }
            }
            $mergedLmpEntries = TiktokSkuCompetitor::sortCollectionByNumericPrice($mergedLmpEntries);
            $lowestLmp = TiktokSkuCompetitor::lowestFromCollection($mergedLmpEntries);

            // Outer LMP = price + shipping (landed), so Diff / color compare total cost.
            $lmpBase = ($lowestLmp && is_numeric($lowestLmp->price ?? null))
                ? floatval($lowestLmp->price)
                : null;
            $lmpShip = ($lowestLmp && is_numeric($lowestLmp->shipping_cost ?? null))
                ? floatval($lowestLmp->shipping_cost)
                : 0.0;
            $processedItem['lmp_price'] = $lmpBase !== null ? round($lmpBase + $lmpShip, 2) : null;
            $processedItem['lmp_base_price'] = $lmpBase;
            $processedItem['lmp_shipping'] = $lmpShip;
            $processedItem['lmp_link'] = $lowestLmp->product_link ?? null;
            $processedItem['lmp_product_id'] = $lowestLmp->product_id ?? null;
            $processedItem['lmp_title'] = $lowestLmp->product_title ?? null;
            $processedItem['lmp_seller'] = $lowestLmp->seller_name ?? null;
            $processedItem['lmp_region'] = $lowestLmp->region ?? null;
            // Modal loads full competitor list via /tiktok/competitors — omit lmp_entries from grid JSON.
            $processedItem['lmp_entries_total'] = $mergedLmpEntries->count();

            $processedData[] = $processedItem;
        }

        // Sort by Parent (null/empty last) so same-parent rows are consecutive for grouping
        usort($processedData, function ($a, $b) {
            $pa = $a['Parent'] ?? '';
            $pb = $b['Parent'] ?? '';
            $pa = ($pa !== null && $pa !== '') ? (string) $pa : '';
            $pb = ($pb !== null && $pb !== '') ? (string) $pb : '';
            if ($pa === '' && $pb === '') return 0;
            if ($pa === '') return 1;
            if ($pb === '') return -1;
            $cmp = strcmp($pa, $pb);
            if ($cmp !== 0) return $cmp;
            return strcmp($a['(Child) sku'] ?? '', $b['(Child) sku'] ?? '');
        });

        // Insert parent summary rows after each group of children with the same Parent
        $processedData = $this->insertTikTokParentRows($processedData);

        return response()->json([
            "message" => "Data fetched successfully",
            "data" => $processedData,
            "status" => 200,
        ]);
    }

    /**
     * Build parent summary rows: group by Parent, insert one row per group after its children.
     * Parent row: sum INV, sum L30, Dil% = L30 sum/Inv sum, sum Ad Spend, Acos% = (sum spend/sum ad sales)*100, Avg TACOS% = (sum spend/sum T sales)*100.
     */
    private function insertTikTokParentRows(array $rows): array
    {
        $result = [];
        $group = [];
        $currentParent = null;

        foreach ($rows as $row) {
            $p = $row['Parent'] ?? null;
            $p = ($p !== null && $p !== '') ? (string) $p : null;

            if ($p === null) {
                if (!empty($group)) {
                    foreach ($group as $r) {
                        $result[] = $r;
                    }
                    $result[] = $this->buildTikTokParentRow($currentParent, $group);
                    $group = [];
                    $currentParent = null;
                }
                $result[] = $row;
                continue;
            }

            if ($p !== $currentParent) {
                if (!empty($group)) {
                    foreach ($group as $r) {
                        $result[] = $r;
                    }
                    $result[] = $this->buildTikTokParentRow($currentParent, $group);
                    $group = [];
                }
                $currentParent = $p;
            }
            $group[] = $row;
        }

        if (!empty($group)) {
            foreach ($group as $r) {
                $result[] = $r;
            }
            $result[] = $this->buildTikTokParentRow($currentParent, $group);
        }

        return $result;
    }

    /**
     * Listing views: video_views if set, else tiktok_products.views (API sync writes views only).
     *
     * @param  object|null  $tiktokItem
     * @return array{video_views: int, ads_views: int, affl_views: int}
     */
    private function tiktokListingViewCounts($tiktokItem): array
    {
        $video = (int) ($tiktokItem->video_views ?? 0);
        if ($video <= 0) {
            $video = (int) ($tiktokItem->views ?? 0);
        }

        return [
            'video_views' => $video,
            'ads_views' => (int) ($tiktokItem->ads_views ?? 0),
            'affl_views' => (int) ($tiktokItem->affl_views ?? 0),
        ];
    }

    private function buildTikTokParentRow(string $parentName, array $childRows): array
    {
        $parentName = trim(preg_replace('/^PARENT\s+/i', '', $parentName) ?? $parentName);
        $sumInv = 0;
        $sumL30 = 0;
        $sumSpend = 0;
        $sumAdSales = 0;
        $sumTSales = 0;
        $sumAdSold = 0;
        $sumAdClicks = 0;
        $sumCogs = 0;
        $sumSpend30 = 0.0;
        $sumSpend1 = 0.0;
        $sumAdsViews30 = 0;
        $sumAdsClicks30 = 0;
        $sumAdsViews1 = 0;
        $sumAdsClicks1 = 0;
        $sumAdsSold30 = 0;
        $sumAdsRevenue30 = 0.0;
        $sumTargetRoas = 0.0;
        $targetRoasCount = 0;
        $sumGmvAdSoldL30 = 0;
        $sumGmvAdSoldL1 = 0;
        $sumGmvAdSalesL30 = 0.0;
        $sumGmvAdSalesL1 = 0.0;
        $sumGmvSpendL30 = 0.0;
        $sumGmvSpendL1 = 0.0;
        $sumGmvBudget = 0.0;
        $sumTtL30 = 0.0;
        $sumVideoViews = 0;
        $sumAdsViews = 0;
        $sumAfflViews = 0;

        foreach ($childRows as $r) {
            $sumInv += (float)($r['INV'] ?? 0);
            $sumL30 += (float)($r['L30'] ?? 0);
            $spend = (float)($r['spend'] ?? 0);
            $sumSpend += $spend;
            $sumAdSold += (int)($r['ad_sold'] ?? 0);
            $sumAdClicks += (int)($r['ad_clicks'] ?? 0);
            $outRoas = (float)($r['out_roas'] ?? 0);
            $sumAdSales += $outRoas > 0 ? ($spend * $outRoas) : 0;
            $ttL30 = (float)($r['TT L30'] ?? 0);
            $ttPrice = (float)($r['TT Price'] ?? 0);
            $sumTSales += $ttL30 * $ttPrice;
            $lp = (float)($r['LP_productmaster'] ?? 0);
            $sumCogs += $ttL30 * $lp;
            $sumSpend30 += (float) ($r['spend_30'] ?? 0);
            $sumSpend1 += (float) ($r['spend_1'] ?? 0);
            $sumAdsViews30 += (int) ($r['ads_views_30'] ?? 0);
            $sumAdsClicks30 += (int) ($r['ads_clicks_30'] ?? 0);
            $sumAdsViews1 += (int) ($r['ads_views_1'] ?? 0);
            $sumAdsClicks1 += (int) ($r['ads_clicks_1'] ?? 0);
            $sumAdsSold30 += (int) ($r['ads_sold_30'] ?? 0);
            $sumAdsRevenue30 += (float) ($r['ads_revenue_30'] ?? 0);
            $tRoas = (float) ($r['target_roas'] ?? 0);
            if ($tRoas > 0) {
                $sumTargetRoas += $tRoas;
                $targetRoasCount++;
            }
            $sumGmvAdSoldL30 += (int) ($r['gmv_ad_sold_l30'] ?? 0);
            $sumGmvAdSoldL1 += (int) ($r['gmv_ad_sold_l1'] ?? 0);
            $sumGmvAdSalesL30 += (float) ($r['gmv_ad_sales_l30'] ?? 0);
            $sumGmvAdSalesL1 += (float) ($r['gmv_ad_sales_l1'] ?? 0);
            $sumGmvSpendL30 += (float) ($r['gmv_spend_l30'] ?? 0);
            $sumGmvSpendL1 += (float) ($r['gmv_spend_l1'] ?? 0);
            $sumGmvBudget += (float) ($r['gmv_budget'] ?? 0);
            $sumTtL30 += $ttL30;
            $sumVideoViews += (int) ($r['video_views'] ?? 0);
            $sumAdsViews += (int) ($r['ads_views'] ?? 0);
            $sumAfflViews += (int) ($r['affl_views'] ?? 0);
        }
        $sumTViews = $sumVideoViews + $sumAdsViews + $sumAfflViews;

        $dilPct = $sumInv > 0 ? round(($sumL30 / $sumInv) * 100, 2) : 0;
        $adCvrPct = $sumAdClicks > 0 ? round(($sumAdSold / $sumAdClicks) * 100, 2) : null;
        $acosPct = $sumAdSales > 0 ? round(($sumSpend / $sumAdSales) * 100, 2) : 0;
        $tacosPct = $sumTSales > 0 ? round(($sumSpend / $sumTSales) * 100, 2) : ($sumSpend > 0 ? 100 : 0);
        $parentProfit = $sumTSales - $sumCogs;
        $gpftPct = $sumTSales > 0 ? round(($parentProfit / $sumTSales) * 100, 2) : 0;
        $roiPct = $sumCogs > 0 ? round(($parentProfit / $sumCogs) * 100, 2) : 0;

        $parentKey = 'PARENT ' . $parentName;
        $imagePath = '';
        foreach ($childRows as $r) {
            $img = trim((string) ($r['image_path'] ?? ''));
            if ($img !== '' && $img !== '-') {
                $imagePath = $img;
                break;
            }
        }
        $dash = '-';
        return [
            'SL No.' => $dash,
            'Parent' => $parentName,
            'parent' => $parentName,
            '(Child) sku' => $parentKey,
            'Child_sku' => $parentKey,
            'is_parent' => true,
            'is_parent_summary' => true,
            'image_path' => $imagePath,
            'INV' => $sumInv,
            'L30' => $sumL30,
            'TT Dil%' => $dilPct,
            'TT L30' => $dash,
            'TT Stock' => $dash,
            'TT Ship' => $dash,
            'TT Price' => $dash,
            'Missing' => $dash,
            'NR' => $dash,
            'variation_req' => $dash,
            'video_req' => $dash,
            'video_uploaded' => $dash,
            'ad_cvr_pct' => $adCvrPct,
            'ads_price' => $dash,
            'budget' => $dash,
            'spend' => $sumSpend,
            'ad_sold' => $dash,
            'ad_clicks' => $dash,
            'acos' => $acosPct,
            'out_roas' => $dash,
            'in_roas' => $dash,
            'status' => $dash,
            'campaign_name' => $dash,
            'MAP' => $dash,
            'video_views' => $sumVideoViews,
            'ads_views' => $sumAdsViews,
            'affl_views' => $sumAfflViews,
            't_views' => $sumTViews,
            'cvr' => $sumTViews > 0 ? round(($sumTtL30 / $sumTViews) * 100, 2) : 0.0,
            'CVR%' => $sumTViews > 0 ? round(($sumTtL30 / $sumTViews) * 100, 2) : 0.0,
            'GPFT%' => $gpftPct,
            'TACOS%' => $tacosPct,
            'PFT %' => $gpftPct,
            'Missing_count' => $dash,
            'LP_productmaster' => $dash,
            'Ship_productmaster' => $dash,
            '_select' => $dash,
            'SPRICE' => $dash,
            'SGPFT' => $dash,
            'SPFT' => $dash,
            'SROI' => $dash,
            'percentage' => $dash,
            'Profit' => $parentProfit,
            'Sales L30' => $sumTSales,
            'ROI%' => $roiPct,
            'hasCampaign' => $dash,
            'spend_30' => round($sumSpend30, 2),
            'spend_1' => round($sumSpend1, 2),
            'ads_views_30' => $sumAdsViews30,
            'ads_clicks_30' => $sumAdsClicks30,
            'ads_views_1' => $sumAdsViews1,
            'ads_clicks_1' => $sumAdsClicks1,
            'ads_sold_30' => $sumAdsSold30,
            'ads_cvr_30' => $sumAdsClicks30 > 0 ? round(($sumAdsSold30 / $sumAdsClicks30) * 100, 2) : 0.0,
            'ads_revenue_30' => round($sumAdsRevenue30, 2),
            'ads_roas' => $sumSpend30 > 0 ? round($sumAdsRevenue30 / $sumSpend30, 2) : 0.0,
            'target_roas' => $targetRoasCount > 0 ? round($sumTargetRoas / $targetRoasCount, 2) : 0.0,
            'ads_acos_pct' => $sumAdsRevenue30 > 0 ? round(($sumSpend30 / $sumAdsRevenue30) * 100, 2) : 0.0,
            'gmv_ad_sold_l30' => $sumGmvAdSoldL30,
            'gmv_ad_sold_l1' => $sumGmvAdSoldL1,
            'gmv_ad_sales_l30' => round($sumGmvAdSalesL30, 2),
            'gmv_ad_sales_l1' => round($sumGmvAdSalesL1, 2),
            'gmv_spend_l30' => round($sumGmvSpendL30, 2),
            'gmv_spend_l1' => round($sumGmvSpendL1, 2),
            'gmv_budget' => round($sumGmvBudget, 2),
            'gmv_status' => $dash,
            'gmv_approval' => $dash,
            'has_custom_sprice' => false,
            'SPRICE_STATUS' => $dash,
            'nrp' => $dash,
            'linked_lmp_skus' => [],
            'lmp_price' => null,
            'lmp_base_price' => null,
            'lmp_shipping' => 0,
            'lmp_link' => null,
            'lmp_entries' => [],
            'lmp_entries_total' => 0,
        ];
    }

    /**
     * Sku Link LMP group for a TikTok row — same shared service as /shein-pricing.
     *
     * @return list<string>
     */
    private function tiktokLinkedLmpSkusFor(LmpSkuGroupService $lmpGroupService, string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        try {
            $group = $lmpGroupService->groupContaining($sku);
        } catch (\Throwable $e) {
            $group = [];
        }

        $members = $group !== [] ? $group : [$sku];
        $seen = [];
        $out = [];
        foreach ($members as $member) {
            $display = trim((string) $member);
            $norm = strtoupper($display);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = $display;
        }

        return $out;
    }

    /**
     * Save SPRICE to tiktok_shop_data_views (TikTok Shop 1)
     */
    public function saveSpriceUpdates(Request $request)
    {
        return $this->saveSpriceUpdatesToModel($request, TiktokShopDataView::class, 'TikTok', false);
    }

    /**
     * Save SPRICE to tiktok_two_shop_data_views (TikTok 2, separate from Shop 1)
     */
    public function saveSpriceTiktokTwoUpdates(Request $request)
    {
        return $this->saveSpriceUpdatesToModel($request, TiktokTwoShopDataView::class, 'TikTok 2', true);
    }

    /**
     * Mark SPRICE push status as applied (double-click ✓✓) — TikTok Shop 1.
     */
    public function updateSpriceStatus(Request $request)
    {
        return $this->updateSpriceStatusToModel($request, TiktokShopDataView::class);
    }

    /**
     * Mark SPRICE push status as applied (double-click ✓✓) — TikTok Shop 2.
     */
    public function updateSpriceTiktokTwoStatus(Request $request)
    {
        return $this->updateSpriceStatusToModel($request, TiktokTwoShopDataView::class);
    }

    private function updateSpriceStatusToModel(Request $request, string $viewModel)
    {
        $sku = strtoupper(trim((string) $request->input('sku')));
        $status = (string) $request->input('status');

        if ($sku === '' || ! in_array($status, ['pushed', 'applied', 'error'], true)) {
            return response()->json(['success' => false, 'error' => 'Invalid SKU or status'], 400);
        }

        $view = $viewModel::whereRaw('UPPER(TRIM(sku)) = ?', [$sku])->first()
            ?: $viewModel::firstOrNew(['sku' => $sku]);
        $values = is_array($view->value) ? $view->value : (json_decode($view->value ?? '{}', true) ?: []);
        if (! is_array($values)) {
            $values = [];
        }

        $values['SPRICE_STATUS'] = $status;
        $values['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
        if (auth()->check()) {
            $values['SPRICE_PUSHED_BY'] = auth()->user()->name ?? auth()->user()->email;
            $values['SPRICE_PUSHED_BY_ID'] = auth()->id();
        }

        $view->sku = $view->sku ?: $sku;
        $view->value = $values;
        $view->save();

        return response()->json(['success' => true, 'message' => 'Status updated successfully']);
    }

    /**
     * Persist NRP (REQ | NR | LATER) to tiktok_shop_data_views.value["NRP"] (TikTok Shop 1).
     */
    public function saveTiktokShopNrp(Request $request)
    {
        return $this->saveTiktokNrpToShopDataView($request, TiktokShopDataView::class);
    }

    /**
     * Persist NRP (REQ | NR | LATER) to tiktok_two_shop_data_views.value["NRP"] (TikTok Shop 2).
     */
    public function saveTiktokTwoNrp(Request $request)
    {
        return $this->saveTiktokNrpToShopDataView($request, TiktokTwoShopDataView::class);
    }

    /**
     * Save buyer / seller links for a SKU into tiktok_shop_listing_statuses.value JSON.
     * Empty strings clear the link (URL validation only applies to non-empty values).
     */
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

        $normalized = strtoupper(str_replace("\u{00a0}", ' ', $sku));
        $status = TiktokShopListingStatus::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])
            ->orderBy('updated_at', 'desc')
            ->first();

        $existing = $status
            ? (is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []))
            : [];

        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;

        // Delete duplicates and create a fresh record (mirrors listing save pattern)
        TiktokShopListingStatus::whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])->delete();
        TiktokShopListingStatus::create([
            'sku' => $sku,
            'value' => $existing,
        ]);

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
    }

    /**
     * Save buyer / seller links for TikTok 2 into tiktok_two_shop_data_views.value JSON (kept separate from TikTok 1).
     */
    public function saveTiktokTwoLinks(Request $request)
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

        $normalized = strtoupper(str_replace("\u{00a0}", ' ', $sku));
        $view = TiktokTwoShopDataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])
            ->first();
        if (!$view) {
            $view = new TiktokTwoShopDataView();
            $view->sku = $sku;
        }

        $existing = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);
        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;
        $view->value = $existing;
        $view->save();

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
    }

    private function saveTiktokNrpToShopDataView(Request $request, string $viewModelClass)
    {
        // Same flow as eBay update-ebay-nr-data: JSON body { sku, field, value } or legacy { sku, nrp }.
        $skuRaw = trim((string) $request->input('sku'));
        $field = $request->input('field');
        $nrp = null;
        if ($field !== null && strtoupper(trim((string) $field)) === 'NRP' && $request->has('value')) {
            $nrp = $request->input('value');
        }
        if ($nrp === null || $nrp === '') {
            $nrp = $request->input('nrp', $request->input('value'));
        }
        if ($skuRaw === '' || $nrp === null || $nrp === '') {
            return response()->json(['success' => false, 'message' => 'SKU and nrp (or field NRP + value) are required.'], 400);
        }
        $t = strtoupper(trim((string) $nrp));
        if (! in_array($t, ['REQ', 'NR', 'LATER'], true)) {
            return response()->json(['success' => false, 'message' => 'nrp must be REQ, NR, or LATER.'], 422);
        }

        $normalized = strtoupper(str_replace("\u{00a0}", ' ', $skuRaw));

        $view = $viewModelClass::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])
            ->first();

        if (! $view) {
            $view = new $viewModelClass;
            $view->sku = $skuRaw;
        }

        $values = is_array($view->value)
            ? $view->value
            : (json_decode($view->value ?? '{}', true) ?: []);
        $values['NRP'] = $t;
        $view->value = $values;
        $view->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Field updated successfully',
            'updated_json' => $values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $val
     */
    private function tiktokNrpStatusFromShopValue(array $val): string
    {
        foreach (['NRP', 'nrp'] as $key) {
            if (! isset($val[$key]) || ! is_string($val[$key])) {
                continue;
            }
            $t = strtoupper(trim($val[$key]));
            if (in_array($t, ['REQ', 'NR', 'LATER'], true)) {
                return $t;
            }
        }

        // Legacy: REQ|NR sometimes stored under value["NR"] before NRP key existed (ads NR uses RA/NRA/LATER).
        if (isset($val['NR']) && is_string($val['NR'])) {
            $t = strtoupper(trim($val['NR']));
            if (in_array($t, ['REQ', 'NR'], true)) {
                return $t;
            }
        }

        return '';
    }

    private function saveSpriceUpdatesToModel(Request $request, string $viewModel, string $logLabel, bool $isTiktokTwo = false)
    {
        try {
            $updates = [];

            if ($request->has('updates')) {
                $updates = $request->input('updates', []);
            } elseif ($request->has('sku') && $request->has('sprice')) {
                $updates = [
                    [
                        'sku' => $request->input('sku'),
                        'sprice' => $request->input('sprice'),
                    ],
                ];
            }

            // TikTok 1 & TikTok 2 SPRICE save → same margin: TiktokShop
            $marketplaceData = MarketplacePercentage::where('marketplace', 'TiktokShop')->first();
            $marginPct = $marketplaceData ? (float) $marketplaceData->percentage : 0.0;
            $marginFactor = $marginPct / 100.0;

            $updatedCount = 0;
            $errors = [];

            foreach ($updates as $update) {
                $sku = $update['sku'] ?? null;
                $sprice = $update['sprice'] ?? null;

                if (! $sku || $sprice === null) {
                    $errors[] = "Invalid update data for SKU: ".($sku ?? 'unknown');
                    continue;
                }

                $view = $viewModel::firstOrNew(['sku' => $sku]);
                $values = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);

                $values['SPRICE'] = floatval($sprice);

                $productMaster = ProductMaster::where('sku', $sku)->first();
                if ($productMaster) {
                    $pmValues = $productMaster->Values ?: [];
                    $lp = $pmValues['lp'] ?? 0;
                    // Shipping Master "Ship" column (not BB Ship / ship_bb).
                    $ship = ProductMasterShip::forPricing(is_array($pmValues) ? $pmValues : [], $productMaster);
                    if ($sprice > 0) {
                        $sgpft = (($sprice * $marginFactor - $lp - $ship) / $sprice) * 100;
                        $values['SGPFT'] = round($sgpft, 2);
                    } else {
                        $values['SGPFT'] = 0;
                    }

                    $values['SPFT'] = $values['SGPFT'].'%';

                    if ($lp > 0) {
                        $sroi = (($sprice * $marginFactor - $lp - $ship) / $lp) * 100;
                        $values['SROI'] = round($sroi, 2).'%';
                    } else {
                        $values['SROI'] = '0%';
                    }
                }

                $view->value = $values;
                $view->save();

                $updatedCount++;
            }

            if ($request->has('sku') && ! $request->has('updates')) {
                if ($updatedCount > 0 && count($updates) > 0) {
                    $update = $updates[0];
                    $sku = $update['sku'];

                    $view = $viewModel::where('sku', $sku)->first();
                    $values = $view ? (is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: [])) : [];

                    return response()->json([
                        'success' => true,
                        'sgpft_percent' => $values['SGPFT'] ?? 0,
                        'spft_percent' => floatval(str_replace('%', '', $values['SPFT'] ?? '0')),
                        'sroi_percent' => floatval(str_replace('%', '', $values['SROI'] ?? '0')),
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to save SPRICE',
                    ], 400);
                }
            } else {
                return response()->json([
                    'success' => true,
                    'updated' => $updatedCount,
                    'errors' => $errors,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error saving {$logLabel} SPRICE updates: ".$e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get/Set Column Visibility
     */
    public function getColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $ch = (string) $request->input('channel', 'tiktok');
        $prefix = $ch === 'tiktok2' ? 'tiktok2_tabulator_column_visibility' : 'tiktok_tabulator_column_visibility';
        $key = "{$prefix}_{$userId}";

        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $ch = (string) $request->input('channel', 'tiktok');
        $prefix = $ch === 'tiktok2' ? 'tiktok2_tabulator_column_visibility' : 'tiktok_tabulator_column_visibility';
        $key = "{$prefix}_{$userId}";

        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    /**
     * All tiktok_campaign_reports rows (no Product-card / product_id filter),
     * keyed by campaign_name (= SKU). Same table as /tiktok-1-ads-raw-data.
     * *30 = L30. *1 = L1 when present, otherwise L7 (the short-range upload).
     *
     * @return array<string, array{spend_30: float, spend_1: float, ads_views_30: int, ads_clicks_30: int, ads_views_1: int, ads_clicks_1: int, ads_sold_30: int, ads_cvr_30: float}>
     */
    private function tiktok1RawAdsMetricsBySku(): array
    {
        if (! Schema::hasTable('tiktok_campaign_reports')) {
            return [];
        }

        $buckets = [];
        try {
            $rows = TiktokCampaignReport::query()
                ->whereNotNull('campaign_name')
                ->where('campaign_name', '!=', '')
                ->get([
                    'campaign_name',
                    'product_id',
                    'report_range',
                    'cost',
                    'product_ad_impressions',
                    'product_ad_clicks',
                    'sku_orders',
                    'gross_revenue',
                    'roi',
                    'in_roas',
                ]);
        } catch (\Throwable $e) {
            Log::warning('TikTok 1 raw ads metrics failed: '.$e->getMessage());

            return [];
        }

        foreach ($rows as $row) {
            $sku = TikTokAdsSkuResolver::skuFor($row->product_id, $row->campaign_name);
            if ($sku === '') {
                continue;
            }
            $range = $this->normalizeTiktokAdsReportRange((string) ($row->report_range ?? ''));
            if ($range === null) {
                continue;
            }
            if (! isset($buckets[$sku][$range])) {
                $buckets[$sku][$range] = [
                    'cost' => 0.0,
                    'impressions' => 0,
                    'clicks' => 0,
                    'orders' => 0,
                    'revenue' => 0.0,
                    'roi_sum' => 0.0,
                    'roi_n' => 0,
                    'in_roas' => null,
                ];
            }
            $buckets[$sku][$range]['cost'] += (float) ($row->cost ?? 0);
            $buckets[$sku][$range]['impressions'] += (int) ($row->product_ad_impressions ?? 0);
            $buckets[$sku][$range]['clicks'] += (int) ($row->product_ad_clicks ?? 0);
            $buckets[$sku][$range]['orders'] += (int) ($row->sku_orders ?? 0);
            $buckets[$sku][$range]['revenue'] += (float) ($row->gross_revenue ?? 0);
            if ($row->roi !== null && $row->roi !== '') {
                $buckets[$sku][$range]['roi_sum'] += (float) $row->roi;
                $buckets[$sku][$range]['roi_n']++;
            }
            if ($row->in_roas !== null && $row->in_roas !== '') {
                $buckets[$sku][$range]['in_roas'] = (float) $row->in_roas;
            }
        }

        $out = [];
        foreach ($buckets as $sku => $byRange) {
            $emptyBucket = ['cost' => 0, 'impressions' => 0, 'clicks' => 0, 'orders' => 0, 'revenue' => 0, 'roi_sum' => 0, 'roi_n' => 0, 'in_roas' => null];
            $l30 = $byRange['L30'] ?? $emptyBucket;
            $l1 = $byRange['L1'] ?? null;
            $l7 = $byRange['L7'] ?? $emptyBucket;
            $short = $l1;
            if ($short === null || (
                (float) ($short['cost'] ?? 0) == 0.0
                && (int) ($short['impressions'] ?? 0) === 0
                && (int) ($short['clicks'] ?? 0) === 0
            )) {
                $short = $l7;
            }
            $clicks30 = (int) ($l30['clicks'] ?? 0);
            $orders30 = (int) ($l30['orders'] ?? 0);
            $cost30 = (float) ($l30['cost'] ?? 0);
            $rev30 = (float) ($l30['revenue'] ?? 0);
            $roas = $cost30 > 0 && $rev30 > 0
                ? $rev30 / $cost30
                : (((int) ($l30['roi_n'] ?? 0) > 0) ? ((float) $l30['roi_sum'] / (int) $l30['roi_n']) : 0.0);
            $acos = $rev30 > 0
                ? ($cost30 / $rev30) * 100
                : ($roas > 0 ? 100 / $roas : 0.0);
            $targetRoas = $l30['in_roas'] ?? $short['in_roas'] ?? $l7['in_roas'] ?? 0.0;
            $out[$sku] = [
                'spend_30' => round($cost30, 2),
                'spend_1' => round((float) ($short['cost'] ?? 0), 2),
                'ads_views_30' => (int) ($l30['impressions'] ?? 0),
                'ads_clicks_30' => $clicks30,
                'ads_views_1' => (int) ($short['impressions'] ?? 0),
                'ads_clicks_1' => (int) ($short['clicks'] ?? 0),
                'ads_sold_30' => $orders30,
                'ads_cvr_30' => $clicks30 > 0 ? round(($orders30 / $clicks30) * 100, 2) : 0.0,
                'ads_revenue_30' => round($rev30, 2),
                'ads_roas' => round((float) $roas, 2),
                'target_roas' => round((float) $targetRoas, 2),
                'ads_acos_pct' => round((float) $acos, 2),
            ];
        }

        return $out;
    }

    /**
     * GMV ads keyed by SKU. Prefers API rows with report_range L30/L1
     * (from TikTokGmvAdsSyncService). Falls back to the latest upload batch.
     *
     * @return array<string, array{gmv_ad_sold_l30: int, gmv_ad_sold_l1: int, gmv_ad_sales_l30: float, gmv_ad_sales_l1: float, gmv_spend_l30: float, gmv_spend_l1: float, gmv_budget: ?float, gmv_status: ?string, gmv_approval: ?string}>
     */
    private function tiktok1GmvAdsMetricsBySku(): array
    {
        if (! Schema::hasTable('tiktok_gmv_ads')) {
            return [];
        }

        try {
            $rows = TiktokGmvAd::query()->get();
        } catch (\Throwable $e) {
            Log::warning('TikTok 1 GMV ads metrics failed: '.$e->getMessage());

            return [];
        }

        $hasRange = Schema::hasColumn('tiktok_gmv_ads', 'report_range');
        $out = [];
        $empty = [
            'gmv_ad_sold_l30' => 0,
            'gmv_ad_sold_l1' => 0,
            'gmv_ad_sales_l30' => 0.0,
            'gmv_ad_sales_l1' => 0.0,
            'gmv_spend_l30' => 0.0,
            'gmv_spend_l1' => 0.0,
            'gmv_budget' => null,
            'gmv_status' => null,
            'gmv_approval' => null,
        ];

        $ranged = $hasRange && $rows->contains(fn ($r) => in_array(strtoupper(trim((string) ($r->report_range ?? ''))), ['L30', 'L1'], true));
        if ($ranged) {
            foreach ($rows as $row) {
                $sku = strtoupper(trim((string) ($row->sku ?? '')));
                if ($sku === '' || preg_match('/^\d{1,6}$/', $sku)) {
                    continue;
                }
                $range = strtoupper(trim((string) ($row->report_range ?? '')));
                if (! isset($out[$sku])) {
                    $out[$sku] = $empty;
                }
                if ($range === 'L30') {
                    $out[$sku]['gmv_ad_sold_l30'] += (int) ($row->ad_sold ?? 0);
                    $out[$sku]['gmv_ad_sales_l30'] += (float) ($row->ad_sales ?? 0);
                    $out[$sku]['gmv_spend_l30'] += (float) ($row->spend ?? 0);
                } elseif ($range === 'L1') {
                    $out[$sku]['gmv_ad_sold_l1'] += (int) ($row->ad_sold ?? 0);
                    $out[$sku]['gmv_ad_sales_l1'] += (float) ($row->ad_sales ?? 0);
                    $out[$sku]['gmv_spend_l1'] += (float) ($row->spend ?? 0);
                }
                if ($out[$sku]['gmv_status'] === null && $row->status) {
                    $out[$sku]['gmv_status'] = (string) $row->status;
                }
                if ($out[$sku]['gmv_approval'] === null && $row->approval) {
                    $out[$sku]['gmv_approval'] = (string) $row->approval;
                }
                if ($out[$sku]['gmv_budget'] === null && $row->budget !== null && $row->budget !== '') {
                    $out[$sku]['gmv_budget'] = (float) $row->budget;
                }
            }
            foreach ($out as $sku => $metrics) {
                $out[$sku]['gmv_ad_sales_l30'] = round((float) $metrics['gmv_ad_sales_l30'], 2);
                $out[$sku]['gmv_ad_sales_l1'] = round((float) $metrics['gmv_ad_sales_l1'], 2);
                $out[$sku]['gmv_spend_l30'] = round((float) $metrics['gmv_spend_l30'], 2);
                $out[$sku]['gmv_spend_l1'] = round((float) $metrics['gmv_spend_l1'], 2);
            }

            return $out;
        }

        $grouped = [];
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row->sku ?? '')));
            if ($sku === '') {
                continue;
            }
            $grouped[$sku][] = $row;
        }

        foreach ($grouped as $sku => $list) {
            $latestAt = null;
            foreach ($list as $row) {
                $at = $row->created_at ? $row->created_at->getTimestamp() : 0;
                if ($latestAt === null || $at > $latestAt) {
                    $latestAt = $at;
                }
            }

            $adSold = 0;
            $adSales = 0.0;
            $spend = 0.0;
            $budget = null;
            $status = null;
            $approval = null;
            foreach ($list as $row) {
                $at = $row->created_at ? $row->created_at->getTimestamp() : 0;
                if ($at !== $latestAt) {
                    continue;
                }
                $adSold += (int) ($row->ad_sold ?? 0);
                $adSales += (float) ($row->ad_sales ?? 0);
                $spend += (float) ($row->spend ?? 0);
                if ($budget === null && $row->budget !== null && $row->budget !== '') {
                    $budget = (float) $row->budget;
                }
                if ($status === null && $row->status) {
                    $status = (string) $row->status;
                }
                if ($approval === null && $row->approval) {
                    $approval = (string) $row->approval;
                }
            }

            $out[$sku] = [
                'gmv_ad_sold_l30' => $adSold,
                'gmv_ad_sold_l1' => 0,
                'gmv_ad_sales_l30' => round($adSales, 2),
                'gmv_ad_sales_l1' => 0.0,
                'gmv_spend_l30' => round($spend, 2),
                'gmv_spend_l1' => 0.0,
                'gmv_budget' => $budget !== null ? round($budget, 2) : null,
                'gmv_status' => $status,
                'gmv_approval' => $approval,
            ];
        }

        return $out;
    }

    private function normalizeTiktokAdsReportRange(string $range): ?string
    {
        $r = strtoupper(trim($range));
        if (in_array($r, ['L30', '30', 'LAST 30 DAYS', 'LAST30'], true)) {
            return 'L30';
        }
        if (in_array($r, ['L7', '7', 'LAST 7 DAYS', 'LAST7'], true)) {
            return 'L7';
        }
        if (in_array($r, ['L1', '1', 'LAST 1 DAY', 'LAST1', 'YESTERDAY', 'TODAY'], true)) {
            return 'L1';
        }

        return null;
    }

    /**
     * Write daily summary / SKU snapshots after the JSON response is sent
     * so /tiktok-data-json is not blocked by thousands of updateOrCreate calls.
     */
    private function deferTiktokSnapshotSaves(array $rows, string $channel): void
    {
        app()->terminating(function () use ($rows, $channel) {
            try {
                $this->saveDailySummaryIfNeeded($rows, $channel);
                $this->saveSkuDailySnapshotsIfNeeded($rows, $channel);
            } catch (\Throwable $e) {
                Log::warning('TikTok deferred snapshot save failed: '.$e->getMessage());
            }
        });
    }

    /**
     * Snapshot per-SKU TT Price (and stock/L30) for Price charts — California day.
     * Called on tabulator data load so charts work even before the nightly cron.
     */
    private function saveSkuDailySnapshotsIfNeeded($products, string $channel = 'tiktok'): void
    {
        if (! in_array($channel, ['tiktok', 'tiktok2'], true)) {
            $channel = 'tiktok';
        }
        if (! Schema::hasTable('tiktok_sku_daily_data')) {
            return;
        }

        try {
            $today = Carbon::now('America/Los_Angeles')->toDateString();
            $cacheKey = "tiktok_sku_daily_snap_{$channel}_{$today}";
            // Refresh at most once per 30 minutes per channel (page may reload often).
            if (Cache::has($cacheKey)) {
                return;
            }

            $saved = 0;
            foreach ($products as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (! empty($row['is_parent']) || ! empty($row['is_parent_summary']) || (isset($row['Parent']) && str_starts_with((string) $row['Parent'], 'PARENT'))) {
                    continue;
                }
                $sku = strtoupper(trim((string) ($row['(Child) sku'] ?? $row['sku'] ?? '')));
                if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }

                $dailyData = [
                    'price' => round((float) ($row['TT Price'] ?? 0), 2),
                    'stock' => (int) ($row['TT Stock'] ?? 0),
                    'sold' => (int) ($row['TT L30'] ?? 0),
                    'tt_l30' => (int) ($row['TT L30'] ?? 0),
                ];

                TiktokSkuDailyData::updateOrCreate(
                    [
                        'sku' => $sku,
                        'channel' => $channel,
                        'record_date' => $today,
                    ],
                    [
                        'daily_data' => $dailyData,
                    ]
                );
                $saved++;
            }

            Cache::put($cacheKey, 1, now()->addMinutes(30));
            Log::info("TikTok SKU daily snapshots saved for {$today} ({$channel})", ['sku_count' => $saved]);
        } catch (\Throwable $e) {
            Log::warning('TikTok SKU daily snapshot save failed: ' . $e->getMessage());
        }
    }

    /**
     * Auto-save daily TikTok summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    private function saveDailySummaryIfNeeded($products, string $channel = 'tiktok')
    {
        if (! in_array($channel, ['tiktok', 'tiktok2'], true)) {
            $channel = 'tiktok';
        }
        try {
            $today = now()->toDateString();
            $cacheKey = "tiktok_daily_summary_{$channel}_{$today}";
            if (Cache::has($cacheKey)) {
                return;
            }

            // Match JS updateSummary(): all non-parent SKU rows (default "All INV" badge set).
            // Do NOT restrict to INV > 0 — that under-counted sales/PFT vs the live ROI%/GPFT badges.
            $filteredData = collect($products)->filter(function ($p) {
                if (! empty($p['is_parent']) || ! empty($p['is_parent_summary']) || ! empty($p['is_parent_row'])) {
                    return false;
                }
                $sku = strtoupper(trim((string) ($p['(Child) sku'] ?? $p['sku'] ?? '')));
                if (str_starts_with($sku, 'PARENT ')) {
                    return false;
                }
                return !(isset($p['Parent']) && str_starts_with((string) $p['Parent'], 'PARENT'));
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (mirror JS updateSummary — L30-weighted like Tiendamia/Amazon)
            $totalSkuCount = $filteredData->count();
            $totalPft = 0;
            $totalSales = 0;
            $totalPrice = 0;
            $priceCount = 0;
            $totalInv = 0;
            $totalL30 = 0;
            $zeroSoldCount = 0;
            $moreSoldCount = 0;
            $totalDil = 0;
            $dilCount = 0;
            $totalCogs = 0;
            $missingCount = 0;
            $mapCount = 0;
            $invTTStockCount = 0;
            $totalSpend30 = 0.0;
            $totalSpend1 = 0.0;
            $totalAdsViews30 = 0;
            $totalAdsClicks30 = 0;
            $totalAdsViews1 = 0;
            $totalAdsClicks1 = 0;
            $totalAdsSold30 = 0;
            $totalAdsRevenue30 = 0.0;
            $totalTargetRoas = 0.0;
            $targetRoasCount = 0;
            $totalGmvAdSoldL30 = 0;
            $totalGmvAdSoldL1 = 0;
            $totalGmvAdSalesL30 = 0.0;
            $totalGmvAdSalesL1 = 0.0;
            $totalGmvSpendL30 = 0.0;
            $totalGmvSpendL1 = 0.0;
            $totalGmvBudget = 0.0;
            
            // Loop through each row (mirror JavaScript updateSummary logic)
            foreach ($filteredData as $row) {
                $profit = floatval($row['Profit'] ?? 0);
                $l30 = floatval($row['TT L30'] ?? 0);
                $lp = floatval($row['LP_productmaster'] ?? 0);
                $totalPft += ($l30 * $profit);
                $totalSales += floatval($row['Sales L30'] ?? 0);
                $totalCogs += $lp * $l30;
                
                $price = floatval($row['TT Price'] ?? 0);
                if ($price > 0) {
                    $totalPrice += $price;
                    $priceCount++;
                }
                
                $totalInv += floatval($row['INV'] ?? 0);
                $totalL30 += $l30;
                $totalSpend30 += (float) ($row['spend_30'] ?? 0);
                $totalSpend1 += (float) ($row['spend_1'] ?? 0);
                $totalAdsViews30 += (int) ($row['ads_views_30'] ?? 0);
                $totalAdsClicks30 += (int) ($row['ads_clicks_30'] ?? 0);
                $totalAdsViews1 += (int) ($row['ads_views_1'] ?? 0);
                $totalAdsClicks1 += (int) ($row['ads_clicks_1'] ?? 0);
                $totalAdsSold30 += (int) ($row['ads_sold_30'] ?? 0);
                $totalAdsRevenue30 += (float) ($row['ads_revenue_30'] ?? 0);
                $rowTargetRoas = (float) ($row['target_roas'] ?? 0);
                if ($rowTargetRoas > 0) {
                    $totalTargetRoas += $rowTargetRoas;
                    $targetRoasCount++;
                }
                $totalGmvAdSoldL30 += (int) ($row['gmv_ad_sold_l30'] ?? 0);
                $totalGmvAdSoldL1 += (int) ($row['gmv_ad_sold_l1'] ?? 0);
                $totalGmvAdSalesL30 += (float) ($row['gmv_ad_sales_l30'] ?? 0);
                $totalGmvAdSalesL1 += (float) ($row['gmv_ad_sales_l1'] ?? 0);
                $totalGmvSpendL30 += (float) ($row['gmv_spend_l30'] ?? 0);
                $totalGmvSpendL1 += (float) ($row['gmv_spend_l1'] ?? 0);
                $totalGmvBudget += (float) ($row['gmv_budget'] ?? 0);
                
                if ($l30 == 0) {
                    $zeroSoldCount++;
                } else {
                    $moreSoldCount++;
                }
                
                $dil = floatval($row['TT Dil%'] ?? 0);
                if ($dil > 0) {
                    $totalDil += $dil;
                    $dilCount++;
                }
                
                $isMissing = (strtoupper(trim((string)($row['Missing'] ?? ''))) === 'M');
                if ($isMissing) {
                    $missingCount++;
                }

                // Count Map / N Map (|INV − TT Stock| ≤ 3 = Map; > 3 = N Map; listed rows only)
                $mapValue = $row['MAP'] ?? '';
                if (! $isMissing && $mapValue) {
                    $absDiff = null;
                    if ($mapValue === 'Map') {
                        $absDiff = 0;
                    } elseif (str_starts_with($mapValue, 'N Map|')) {
                        $rest = substr($mapValue, strlen('N Map|'));
                        $f = 0.0;
                        if (sscanf((string) $rest, '%f', $f) === 1) {
                            $absDiff = abs($f);
                        }
                    } elseif (str_starts_with($mapValue, 'Diff|')) {
                        $rest = substr($mapValue, strlen('Diff|'));
                        $f = 0.0;
                        if (sscanf((string) $rest, '%f', $f) === 1) {
                            $absDiff = abs($f);
                        }
                    }
                    if ($absDiff !== null) {
                        if ($absDiff <= 3) {
                            $mapCount++;
                        } else {
                            $invTTStockCount++;
                        }
                    }
                }
            }
            
            // Aggregate % (same as Tiendamia / Amazon / Newegg badges — not simple avg of row %)
            $avgGpft = $totalSales > 0 ? ($totalPft / $totalSales) * 100 : 0;
            $avgPrice = $priceCount > 0 ? $totalPrice / $priceCount : 0;
            $avgDil = $dilCount > 0 ? $totalDil / $dilCount : 0;
            $avgRoi = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;
            
            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'nmap_count' => $invTTStockCount,  // Not mapped (inventory mismatch)
                'inv_tt_stock_count' => $invTTStockCount,  // Keep for backward compatibility
                
                // Financial Totals
                'total_pft' => round($totalPft, 2),
                'total_sales' => round($totalSales, 2),
                'total_cogs' => round($totalCogs, 2),
                
                // Inventory
                'total_inv' => round($totalInv, 2),
                'total_l30' => round($totalL30, 2),
                
                // Calculated Percentages & Averages
                'avg_gpft' => round($avgGpft, 2),
                'avg_dil' => round($avgDil, 2),
                'avg_roi' => round($avgRoi, 2),
                'avg_price' => round($avgPrice, 2),
                'total_spend_30' => round($totalSpend30, 2),
                'total_spend_1' => round($totalSpend1, 2),
                'total_ads_views_30' => $totalAdsViews30,
                'total_ads_clicks_30' => $totalAdsClicks30,
                'total_ads_views_1' => $totalAdsViews1,
                'total_ads_clicks_1' => $totalAdsClicks1,
                'ads_cvr_30' => $totalAdsClicks30 > 0 ? round(($totalAdsSold30 / $totalAdsClicks30) * 100, 2) : 0.0,
                'ads_roas' => $totalSpend30 > 0 ? round($totalAdsRevenue30 / $totalSpend30, 2) : 0.0,
                'avg_target_roas' => $targetRoasCount > 0 ? round($totalTargetRoas / $targetRoasCount, 2) : 0.0,
                'ads_acos_pct' => $totalAdsRevenue30 > 0 ? round(($totalSpend30 / $totalAdsRevenue30) * 100, 2) : 0.0,
                'total_gmv_ad_sold_l30' => $totalGmvAdSoldL30,
                'total_gmv_ad_sold_l1' => $totalGmvAdSoldL1,
                'total_gmv_ad_sales_l30' => round($totalGmvAdSalesL30, 2),
                'total_gmv_ad_sales_l1' => round($totalGmvAdSalesL1, 2),
                'total_gmv_spend_l30' => round($totalGmvSpendL30, 2),
                'total_gmv_spend_l1' => round($totalGmvSpendL1, 2),
                'total_gmv_budget' => round($totalGmvBudget, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters
                'filters_applied' => [
                    'inventory' => 'all', // matches default badge filter (All INV)
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => $channel,
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (All INV, L30-weighted GPFT/ROI)',
                ]
            );

            Cache::put($cacheKey, 1, now()->addMinutes(30));
            
            Log::info("Daily TikTok summary snapshot saved for {$today} ({$channel})", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily TikTok summary: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  LMP (Lowest Marketplace Price) — modal endpoints for /tiktok-pricing
    //  Mirror of OverallAmazonController::getAmazonCompetitors / addAmazonLmp
    //  / deleteAmazonLmp, but talking to tiktok_sku_competitors instead.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /tiktok/competitors?sku=XXX&linked_lmp_skus[]=...
     * Returns competitors for the SKU and its Sku Link LMP group, sorted by
     * landed price. Resolves the group server-side (same as outer LMP column)
     * so the modal stays correct even when the client omits linked SKUs.
     */
    public function getTiktokCompetitors(Request $request)
    {
        try {
            $sku = trim((string) $request->input('sku'));
            $linkedSkus = $request->input('linked_lmp_skus', []);

            if ($sku === '') {
                return response()->json(['error' => 'SKU is required'], 400);
            }

            if (! is_array($linkedSkus)) {
                $linkedSkus = $linkedSkus !== null && $linkedSkus !== ''
                    ? [trim((string) $linkedSkus)]
                    : [];
            }

            // Resolve Sku Link LMP group (same source as /tiktok-pricing LMP column).
            $groupSkus = [$sku];
            try {
                $lmpGroupService = new LmpSkuGroupService();
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
                Log::warning('LmpSkuGroupService in getTiktokCompetitors failed: ' . $e->getMessage());
            }

            $groupSkus = array_values(array_unique(array_filter(array_map(
                fn ($value) => trim((string) $value),
                array_merge($groupSkus, $linkedSkus, [$sku])
            ))));

            $competitors = TiktokSkuCompetitor::getCompetitorsForSkus($groupSkus, 'tiktok');
            $lowest = $competitors->first(fn ($comp) => empty($comp->ignored));

            return response()->json([
                'success' => true,
                'competitors' => $competitors->map(function ($comp) {
                    return [
                        'id' => $comp->id,
                        'sku' => $comp->sku,
                        'product_id' => $comp->product_id,
                        'marketplace' => $comp->marketplace,
                        'region' => $comp->region,
                        'image' => $comp->image,
                        'product_link' => $comp->product_link,
                        'link' => $comp->product_link,
                        'product_title' => $comp->product_title,
                        'title' => $comp->product_title,
                        'seller_name' => $comp->seller_name,
                        'brand_name' => $comp->brand_name,
                        'price' => floatval($comp->price),
                        'ignored' => (bool) ($comp->ignored ?? false),
                        'shipping_cost' => floatval($comp->shipping_cost ?? 0),
                        'min_price' => $comp->min_price !== null ? floatval($comp->min_price) : null,
                        'max_price' => $comp->max_price !== null ? floatval($comp->max_price) : null,
                        'rating' => $comp->rating !== null ? floatval($comp->rating) : null,
                        'reviews' => $comp->reviews !== null ? (int) $comp->reviews : null,
                        'sold_count' => $comp->sold_count !== null ? (int) $comp->sold_count : null,
                        'created_at' => $comp->created_at ? $comp->created_at->format('Y-m-d H:i:s') : null,
                        'updated_at' => $comp->updated_at ? $comp->updated_at->format('Y-m-d H:i:s') : null,
                    ];
                }),
                'lowest_price' => $lowest ? TiktokSkuCompetitor::landedPrice($lowest) : null,
                'total_count' => $competitors->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching TikTok competitors', [
                'sku' => $sku ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Failed to fetch competitors: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /tiktok/competitors
     * Add one manually-entered competitor for the given SKU. Used by the
     * "Add New Competitor" form inside the LMP modal.
     */
    public function addTiktokCompetitor(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku'           => 'required|string',
                'product_id'    => 'nullable|string',
                'price'         => 'required|numeric|min:0.01',
                'shipping_cost' => 'nullable|numeric|min:0',
                'product_link'  => 'nullable|string',
                'product_title' => 'nullable|string',
                'image'         => 'nullable|string',
                'seller_name'   => 'nullable|string',
                'brand_name'    => 'nullable|string',
                'region'        => 'nullable|string|max:8',
                'marketplace'   => 'nullable|string',
            ]);

            $sku = trim($validated['sku']);
            $productId = $this->extractTiktokProductId(
                $validated['product_id'] ?? null,
                $validated['product_link'] ?? null
            );
            if ($productId === '') {
                return response()->json(['error' => 'Product ID is required'], 422);
            }
            $marketplace = strtolower($validated['marketplace'] ?? 'tiktok');
            $region = strtoupper($validated['region'] ?? 'US');

            $existing = TiktokSkuCompetitor::where('sku', $sku)
                ->where('product_id', $productId)
                ->where('marketplace', $marketplace)
                ->where('region', $region)
                ->first();

            if ($existing) {
                return response()->json([
                    'error' => 'This competitor is already saved for this SKU/region',
                ], 409);
            }

            DB::beginTransaction();
            $lmp = TiktokSkuCompetitor::create([
                'sku'           => $sku,
                'product_id'    => $productId,
                'marketplace'   => $marketplace,
                'region'        => $region,
                'price'         => $validated['price'],
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'product_link'  => $validated['product_link'] ?? null,
                'product_title' => $validated['product_title'] ?? null,
                'image'         => $validated['image'] ?? null,
                'seller_name'   => $validated['seller_name'] ?? null,
                'brand_name'    => $validated['brand_name'] ?? null,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'TikTok competitor added',
                'data'    => [
                    'id'            => $lmp->id,
                    'sku'           => $lmp->sku,
                    'product_id'    => $lmp->product_id,
                    'price'         => floatval($lmp->price),
                    'shipping_cost' => floatval($lmp->shipping_cost ?? 0),
                    'product_link'  => $lmp->product_link,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error adding TikTok competitor', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Failed to add competitor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /tiktok/competitors/update
     * Update an existing competitor mapping from the LMP modal edit form.
     */
    public function updateTiktokCompetitor(Request $request)
    {
        try {
            $validated = $request->validate([
                'id'            => 'required|integer',
                'product_id'    => 'nullable|string',
                'price'         => 'required|numeric|min:0.01',
                'shipping_cost' => 'nullable|numeric|min:0',
                'product_link'  => 'nullable|string',
                'product_title' => 'nullable|string',
                'image'         => 'nullable|string',
                'seller_name'   => 'nullable|string',
                'brand_name'    => 'nullable|string',
                'region'        => 'nullable|string|max:8',
            ]);

            $lmp = TiktokSkuCompetitor::find($validated['id']);
            if (!$lmp) {
                return response()->json(['error' => 'Competitor not found'], 404);
            }

            $productId = $this->extractTiktokProductId(
                $validated['product_id'] ?? $lmp->product_id,
                $validated['product_link'] ?? $lmp->product_link
            );
            if ($productId === '') {
                return response()->json(['error' => 'Product ID is required'], 422);
            }
            $region = strtoupper($validated['region'] ?? ($lmp->region ?: 'US'));
            $marketplace = $lmp->marketplace ?: 'tiktok';

            $duplicate = TiktokSkuCompetitor::where('sku', $lmp->sku)
                ->where('product_id', $productId)
                ->where('marketplace', $marketplace)
                ->where('region', $region)
                ->where('id', '!=', $lmp->id)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'error' => 'Another competitor with this Product ID already exists for this SKU/region',
                ], 409);
            }

            $lmp->update([
                'product_id'    => $productId,
                'region'        => $region,
                'price'         => $validated['price'],
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'product_link'  => $validated['product_link'] ?? null,
                'product_title' => $validated['product_title'] ?? null,
                'image'         => array_key_exists('image', $validated) ? ($validated['image'] ?? null) : $lmp->image,
                'seller_name'   => array_key_exists('seller_name', $validated) ? ($validated['seller_name'] ?? null) : $lmp->seller_name,
                'brand_name'    => array_key_exists('brand_name', $validated) ? ($validated['brand_name'] ?? null) : $lmp->brand_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'TikTok competitor updated',
                'data'    => [
                    'id'            => $lmp->id,
                    'sku'           => $lmp->sku,
                    'product_id'    => $lmp->product_id,
                    'price'         => floatval($lmp->price),
                    'shipping_cost' => floatval($lmp->shipping_cost ?? 0),
                    'product_link'  => $lmp->product_link,
                    'region'        => $lmp->region,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error updating TikTok competitor', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Failed to update competitor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Prefer an explicit product_id; otherwise pull the numeric id from a TikTok Shop URL.
     */
    private function extractTiktokProductId(?string $productId, ?string $productLink): string
    {
        $id = trim((string) $productId);
        if ($id !== '' && preg_match('/^\d{8,}$/', $id)) {
            return substr($id, 0, 64);
        }
        if ($id !== '') {
            return substr($id, 0, 64);
        }

        $link = trim((string) $productLink);
        if ($link === '') {
            return '';
        }
        if (preg_match('/\/(?:pdp|product)\/(?:[^\/?]+\/)?(\d{8,})/i', $link, $m)
            || preg_match('/[?&]product_id=(\d{8,})/i', $link, $m)
            || preg_match('/(\d{15,})/', $link, $m)
        ) {
            return substr($m[1], 0, 64);
        }

        return '';
    }

    /**
     * POST /tiktok/competitors/delete  (id in body)
     * Remove a single mapping.
     */
    public function deleteTiktokCompetitor(Request $request)
    {
        try {
            $id = $request->input('id');
            if (!$id || !is_numeric($id)) {
                return response()->json(['error' => 'Valid ID is required'], 400);
            }
            $lmp = TiktokSkuCompetitor::find($id);
            if (!$lmp) {
                return response()->json(['error' => 'Competitor not found'], 404);
            }
            $lmp->delete();
            return response()->json([
                'success' => true,
                'message' => 'Competitor deleted',
                'deleted_id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error deleting TikTok competitor', [
                'id' => $id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Failed to delete competitor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * N Map SKUs matching /tiktok-pricing badge:
     * non-parent, not Missing L, |INV − TT Stock| > 3.
     * Negative Shopify INV + marketplace stock 0 is Map (not N Map).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{sku: string, channel_sku: string, inv: float, channel_inv: float, diff: float}>
     */
    public static function nmapSkuRowsFromTabular(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row)) {
                continue;
            }
            if (! empty($row['is_parent']) || ! empty($row['is_parent_summary']) || ! empty($row['is_parent_row'])) {
                continue;
            }
            $parent = trim((string) ($row['Parent'] ?? ''));
            if ($parent !== '' && str_starts_with(strtoupper($parent), 'PARENT ')) {
                continue;
            }
            $rowSku = strtoupper(trim((string) ($row['(Child) sku'] ?? $row['sku'] ?? '')));
            if (str_starts_with($rowSku, 'PARENT ')) {
                continue;
            }
            if (strtoupper(trim((string) ($row['Missing'] ?? ''))) === 'M') {
                continue;
            }

            $sku = trim((string) ($row['(Child) sku'] ?? $row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $inv = (float) ($row['INV'] ?? 0);
            $ttStock = (float) ($row['TT Stock'] ?? 0);

            // Negative Shopify INV + marketplace 0 = perfect Map
            if ($inv < 0 && $ttStock == 0.0) {
                continue;
            }

            $diff = abs($inv - $ttStock);

            $mapValue = (string) ($row['MAP'] ?? '');
            if ($mapValue === 'Map') {
                continue;
            }
            if (str_starts_with($mapValue, 'N Map|') || str_starts_with($mapValue, 'Diff|')) {
                $rest = str_starts_with($mapValue, 'N Map|')
                    ? substr($mapValue, strlen('N Map|'))
                    : substr($mapValue, strlen('Diff|'));
                $parsed = 0.0;
                if (sscanf((string) $rest, '%f', $parsed) === 1) {
                    $diff = abs($parsed);
                }
            }

            if ($diff <= 3) {
                continue;
            }

            $out[] = [
                'sku' => $sku,
                'channel_sku' => $sku,
                'inv' => $inv,
                'channel_inv' => $ttStock,
                'diff' => $diff,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function countNmapFromTabular(array $rows): int
    {
        return count(self::nmapSkuRowsFromTabular($rows));
    }

    /**
     * Sync TikTok 2 products (and optionally orders) from Shop API.
     * POST /tiktok-2-sync-from-api  body: products=1, orders=1
     */
    public function syncTikTok2FromApi(Request $request)
    {
        $doProducts = filter_var($request->input('products', true), FILTER_VALIDATE_BOOLEAN);
        $doOrders = filter_var($request->input('orders', true), FILTER_VALIDATE_BOOLEAN);
        $days = max(1, (int) $request->input('days', 60));

        $svc = app(TikTok2ShopService::class);
        if (! $svc->isAuthenticated()) {
            $access = config('services.tiktok2.access_token');
            $refresh = config('services.tiktok2.refresh_token');
            if ($access) {
                $svc->setTokens((string) $access, $refresh ? (string) $refresh : null);
            }
        }

        if (! $svc->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'TikTok 2 is not connected. Open /tiktok2/connect and authorize the shop first.',
                'connect_url' => url('/tiktok2/connect'),
            ], 401);
        }

        $results = [];
        try {
            if ($doProducts) {
                $exit = Artisan::call('sync:tiktok-api-data', ['--channel' => 'tiktok2']);
                $results['products'] = [
                    'exit' => $exit,
                    'output' => trim(Artisan::output()),
                    'count' => (int) TikTokProductTwo::query()->whereNotNull('sku')->where('sku', '!=', '')->count(),
                ];
                if ($exit !== 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product sync failed. See output.',
                        'results' => $results,
                    ], 500);
                }
            }

            if ($doOrders) {
                $exit = Artisan::call('tiktok:fetch-orders', [
                    '--channel' => 'tiktok2',
                    '--days' => $days,
                ]);
                $results['orders'] = [
                    'exit' => $exit,
                    'output' => trim(Artisan::output()),
                ];
                if ($exit !== 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order sync failed. See output.',
                        'results' => $results,
                    ], 500);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'TikTok 2 API sync completed.',
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok 2 syncFromApi failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: '.$e->getMessage(),
                'results' => $results,
            ], 500);
        }
    }
}

