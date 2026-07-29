<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Models\TiktokCampaignReport;
use App\Models\ShopifySku;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\ReverbViewData;
use App\Models\TiktokShopDataView;
use App\Models\TiktokTwoShopDataView;
use App\Models\TiktokShopListingStatus;
use App\Models\TiktokSkuCompetitor;
use App\Models\TiktokSkuDailyData;
use App\Services\LmpSkuGroupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\AmazonChannelSummary;

class TikTokPricingController extends Controller
{
    /**
     * Display TikTok Pricing Tabulator View
     */
    public function tiktokTabulatorView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Use TiktokShop marketplace key (fallback to TikTok for legacy rows)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'TiktokShop')
            ->orWhere('marketplace', 'TikTok')
            ->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 85;

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
            $this->saveDailySummaryIfNeeded($rows, 'tiktok2');
            $this->saveSkuDailySnapshotsIfNeeded($rows, 'tiktok2');

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

        $marketplaceData = MarketplacePercentage::where('marketplace', 'TiktokShop')
            ->orWhere('marketplace', 'TikTok')
            ->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 85;

        return view('market-places.tiktok_tabulator_view', [
            'mode' => $mode,
            'demo' => $demo,
            'tiktokPercentage' => $percentage,
            'tiktokPageTitle' => 'TikTok 2 Shop - Analytics',
            'tiktokUploadPath' => '/tiktok-2-upload-csv',
            'tiktokDownloadSamplePath' => '/tiktok-download-sample-csv',
            'tiktokPricingClientConfig' => [
                'dataJson' => '/tiktok-2-data-json',
                'badgeChart' => route('tiktok2.badge.chart.data'),
                'metricsHistory' => route('tiktok2.metrics.history'),
                'saveSprice' => '/tiktok-2-save-sprice',
                'saveNrp' => route('tiktok2.save.nrp'),
                'saveLinks' => '/tiktok-2-save-links',
                // Shared DB-backed column visibility (same endpoint ebay-tabulator-view uses).
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
            // Auto-save daily summary + per-SKU price snapshots (for Price chart)
            $this->saveDailySummaryIfNeeded($rows, 'tiktok');
            $this->saveSkuDailySnapshotsIfNeeded($rows, 'tiktok');

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
     * L30 sold quantities from tiktok_sales_two (TikTok 2 upload) — 30 days ending on latest order_date.
     */
    private function getTiktokTwoL30SoldDataBySku(): array
    {
        $latestRaw = DB::table('tiktok_sales_two')->whereNotNull('order_date')->max('order_date');
        if (! $latestRaw) {
            return [];
        }

        $latestDateCarbon = \Carbon\Carbon::parse($latestRaw, 'America/Los_Angeles');
        $startDate = $latestDateCarbon->copy()->subDays(29);

        $rows = DB::table('tiktok_sales_two')
            ->whereBetween('order_date', [$startDate, $latestDateCarbon->copy()->endOfDay()])
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->where(function ($q) {
                $q->whereNotIn('order_status', ['Canceled', 'Cancelled', 'canceled', 'cancelled'])
                    ->orWhereNull('order_status');
            })
            ->selectRaw('UPPER(TRIM(seller_sku)) as u_sku, SUM(quantity) as total_sold')
            ->groupByRaw('UPPER(TRIM(seller_sku))')
            ->get();

        $soldData = [];
        foreach ($rows as $row) {
            if (! empty($row->u_sku)) {
                $soldData[$row->u_sku] = (int) $row->total_sold;
            }
        }

        return $soldData;
    }

    /**
     * Get TikTok Tabular Data (similar to Reverb)
     */
    public function getViewTikTokTabularData(Request $request, string $variant = 'v1')
    {
        $isTiktokTwo = $variant === 'v2';
        // Use TiktokShop marketplace key (fallback to TikTok for legacy rows)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'TiktokShop')
            ->orWhere('marketplace', 'TikTok')
            ->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 80;
        $percentageValue = $percentage / 100;

        // Fetch all product master records (excluding parent rows)
        $productMasterRows = ProductMaster::all()
            ->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })
            ->keyBy("sku");

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck("sku")->toArray();
        
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
        // Key by normalized SKU so Product Master casing matches shop rows (mirrors Wayfair UPPER(TRIM) lookup).
        $normSkuList = collect($skus)
            ->map(fn ($s) => strtoupper(str_replace("\u{00a0}", ' ', trim((string) $s))))
            ->unique()
            ->filter()
            ->values()
            ->all();
        $ttShopDataByNormSku = [];
        if ($normSkuList !== []) {
            $ttShopRows = $isTiktokTwo
                ? TiktokTwoShopDataView::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $normSkuList)
                    ->get()
                : TiktokShopDataView::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $normSkuList)
                    ->get();
            foreach ($ttShopRows as $row) {
                $k = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $row->sku)));
                if ($k !== '') {
                    $ttShopDataByNormSku[$k] = $row;
                }
            }
        }

        // Buyer / Seller links from TiktokShopListingStatus.value JSON (keyed by normalized SKU) — TikTok 1 only
        $ttListingLinksByNormSku = [];
        if (!$isTiktokTwo && $normSkuList !== []) {
            $linkRows = TiktokShopListingStatus::query()
                ->whereIn(DB::raw('UPPER(TRIM(sku))'), $normSkuList)
                ->get();
            foreach ($linkRows as $lr) {
                $k = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $lr->sku)));
                if ($k !== '') {
                    $ttListingLinksByNormSku[$k] = $lr;
                }
            }
        }

        // L30: tiktok_orders API (TikTok 1) or uploaded orders in tiktok_sales_two (TikTok 2)
        $soldData = $isTiktokTwo
            ? $this->getTiktokTwoL30SoldDataBySku()
            : $this->getTiktokL30SoldDataBySku();

        // Campaign map and metrics for utilized/ads columns (same as tiktok/utilized)
        $campaignMapBySku = [];
        $campaignMetricsBySku = [];
        try {
            $allCampaignsL30 = TiktokCampaignReport::where('report_range', 'L30')
                ->where('creative_type', 'Product card')
                ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                ->whereNotNull('product_id')->where('product_id', '!=', '')
                ->select('product_id', 'campaign_name', 'campaign_id', 'creative_type')
                ->get();
            $allCampaignsL7 = TiktokCampaignReport::where('report_range', 'L7')
                ->where('creative_type', 'Product card')
                ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                ->whereNotNull('product_id')->where('product_id', '!=', '')
                ->select('product_id', 'campaign_name', 'campaign_id', 'creative_type')
                ->get();
            $allCampaigns = $allCampaignsL30->concat($allCampaignsL7);

            $campaignMetricsL30 = TiktokCampaignReport::where('report_range', 'L30')
                ->where('creative_type', 'Product card')
                ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                ->whereNotNull('product_id')->where('product_id', '!=', '')
                ->get()
                ->groupBy(function ($item) { return strtoupper(trim($item->campaign_name)); })
                ->map(function ($group) {
                    $first = $group->first();
                    return (object)[
                        'sku_upper' => strtoupper(trim($group->first()->campaign_name)),
                        'total_cost' => $group->sum('cost'),
                        'total_clicks' => $group->sum('product_ad_clicks'),
                        'total_revenue' => $group->sum('gross_revenue'),
                        'total_sku_orders' => $group->sum('sku_orders'),
                        'avg_roi' => $first && $first->roi !== null ? (float)$first->roi : 0,
                        'avg_in_roas' => $first && $first->in_roas !== null ? (float)$first->in_roas : 0,
                        'custom_status' => $first && $first->custom_status ? $first->custom_status : null,
                        'budget' => $first && $first->budget !== null ? (float)$first->budget : null,
                    ];
                });
            $campaignMetricsL7 = TiktokCampaignReport::where('report_range', 'L7')
                ->where('creative_type', 'Product card')
                ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                ->whereNotNull('product_id')->where('product_id', '!=', '')
                ->get()
                ->groupBy(function ($item) { return strtoupper(trim($item->campaign_name)); })
                ->map(function ($group) {
                    $first = $group->first();
                    return (object)[
                        'sku_upper' => strtoupper(trim($group->first()->campaign_name)),
                        'total_cost' => $group->sum('cost'),
                        'total_clicks' => $group->sum('product_ad_clicks'),
                        'total_revenue' => $group->sum('gross_revenue'),
                        'total_sku_orders' => $group->sum('sku_orders'),
                        'avg_roi' => $first && $first->roi !== null ? (float)$first->roi : 0,
                        'avg_in_roas' => $first && $first->in_roas !== null ? (float)$first->in_roas : 0,
                        'custom_status' => $first && $first->custom_status ? $first->custom_status : null,
                        'budget' => $first && $first->budget !== null ? (float)$first->budget : null,
                    ];
                });

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
                if (!empty($campaign->campaign_name)) {
                    $cn = strtoupper(trim($campaign->campaign_name));
                    if (!isset($campaignMapBySku[$cn])) $campaignMapBySku[$cn] = [];
                    if (!in_array($campaign->campaign_name, $campaignMapBySku[$cn])) $campaignMapBySku[$cn][] = $campaign->campaign_name;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TikTok pricing: campaign/ads data fetch failed: ' . $e->getMessage());
        }

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
            // TikTok 1 → tt_ship only (no fallback). TikTok 2 → Ship BB (Values ship_bb) only.
            $ttShip = $isTiktokTwo
                ? (isset($values["ship_bb"]) ? floatval($values["ship_bb"]) : (isset($productMaster->ship_bb) ? floatval($productMaster->ship_bb) : 0))
                : ($values["tt_ship"] ?? 0);
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
                $processedItem["video_views"] = intval($tiktokItem->video_views ?? $tiktokItem->views ?? 0);
                $processedItem["ads_views"] = intval($tiktokItem->ads_views ?? 0);
                $processedItem["affl_views"] = intval($tiktokItem->affl_views ?? 0);
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
                if (array_key_exists('video_views', $tiktokValArr)) {
                    $processedItem["video_views"] = intval($tiktokValArr["video_views"]);
                }
                if (array_key_exists('ads_views', $tiktokValArr)) {
                    $processedItem["ads_views"] = intval($tiktokValArr["ads_views"]);
                }
                if (array_key_exists('affl_views', $tiktokValArr)) {
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
            // If INV is 0 or negative, show NRA and auto-save to the per-channel data view
            $inv = (float)($processedItem["INV"] ?? 0);
            if ($inv <= 0) {
                $processedItem["NR"] = 'NRA';
                if ($isTiktokTwo) {
                    $view = TiktokTwoShopDataView::firstOrNew(['sku' => $sku]);
                } else {
                    $view = TiktokShopDataView::firstOrNew(['sku' => $sku]);
                }
                $values = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);
                $values['NR'] = 'NRA';
                $view->value = $values;
                $view->save();
            }
            $processedItem["ads_price"] = $processedItem["TT Price"] ?? 0;
            $processedItem["budget"] = isset($metrics['budget']) && $metrics['budget'] !== null ? round((float)$metrics['budget'], 2) : null;
            $processedItem["spend"] = round((float)($metrics['cost'] ?? 0), 2);
            $processedItem["spend_l30"] = round((float)($metrics['cost_l30'] ?? 0), 2);
            $processedItem["spend_l7"] = round((float)($metrics['cost_l7'] ?? 0), 2);
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
            $processedItem['lmp_entries'] = $mergedLmpEntries
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'product_id' => $entry->product_id ?? null,
                        'price' => is_numeric($entry->price) ? floatval($entry->price) : null,
                        'shipping_cost' => is_numeric($entry->shipping_cost ?? null) ? floatval($entry->shipping_cost) : 0,
                        'min_price' => $entry->min_price !== null && is_numeric($entry->min_price) ? floatval($entry->min_price) : null,
                        'max_price' => $entry->max_price !== null && is_numeric($entry->max_price) ? floatval($entry->max_price) : null,
                        'link' => $entry->product_link ?? null,
                        'product_link' => $entry->product_link ?? null,
                        'title' => $entry->product_title ?? null,
                        'product_title' => $entry->product_title ?? null,
                        'image' => $entry->image ?? null,
                        'seller_name' => $entry->seller_name ?? null,
                        'brand_name' => $entry->brand_name ?? null,
                        'marketplace' => $entry->marketplace ?? 'tiktok',
                        'region' => $entry->region ?? 'US',
                        'rating' => $entry->rating ?? null,
                        'reviews' => $entry->reviews ?? null,
                        'sold_count' => $entry->sold_count ?? null,
                    ];
                })
                ->toArray();
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

    private function buildTikTokParentRow(string $parentName, array $childRows): array
    {
        $sumInv = 0;
        $sumL30 = 0;
        $sumSpend = 0;
        $sumAdSales = 0;
        $sumTSales = 0;
        $sumAdSold = 0;
        $sumAdClicks = 0;
        $sumCogs = 0;

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
        }

        $dilPct = $sumInv > 0 ? round(($sumL30 / $sumInv) * 100, 2) : 0;
        $adCvrPct = $sumAdClicks > 0 ? round(($sumAdSold / $sumAdClicks) * 100, 2) : null;
        $acosPct = $sumAdSales > 0 ? round(($sumSpend / $sumAdSales) * 100, 2) : 0;
        $tacosPct = $sumTSales > 0 ? round(($sumSpend / $sumTSales) * 100, 2) : ($sumSpend > 0 ? 100 : 0);
        $parentProfit = $sumTSales - $sumCogs;
        $gpftPct = $sumTSales > 0 ? round(($parentProfit / $sumTSales) * 100, 2) : 0;
        $roiPct = $sumCogs > 0 ? round(($parentProfit / $sumCogs) * 100, 2) : 0;

        $parentKey = 'PARENT ' . $parentName;
        $dash = '-';
        return [
            'SL No.' => $dash,
            'Parent' => $parentKey,
            '(Child) sku' => $parentKey,
            'Child_sku' => $parentKey,
            'is_parent' => true,
            'image_path' => $dash,
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
     * Upload CSV for TikTok 2 — stores rows in tiktok_products_two.
     */
    public function uploadTikTok2Csv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $file = $request->file('csv_file');
            $handle = fopen($file->getPathname(), 'r');

            $header = fgetcsv($handle);
            $headerMap = [];
            if (is_array($header)) {
                foreach ($header as $idx => $col) {
                    $key = strtolower(trim((string) $col));
                    $key = str_replace([' ', '-'], '_', $key);
                    $headerMap[$key] = $idx;
                }
            }
            $skuIndex = $headerMap['sku'] ?? 0;
            $priceIndex = $headerMap['price'] ?? 1;
            $stockIndex = $headerMap['inv'] ?? ($headerMap['stock'] ?? 2);
            $videoViewsIndex = $headerMap['video_views'] ?? ($headerMap['views'] ?? null);
            $adsViewsIndex = $headerMap['ads_views'] ?? null;
            $afflViewsIndex = $headerMap['affl_views'] ?? ($headerMap['affiliate_views'] ?? null);

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $processedSkus = [];

            while (($row = fgetcsv($handle)) !== false) {
                $rawSku = $row[$skuIndex] ?? null;
                if ($rawSku !== null && trim((string) $rawSku) !== '') {
                    $sku = $rawSku;
                    $sku = str_replace("\xA0", ' ', $sku);
                    $sku = str_replace("\xC2\xA0", ' ', $sku);
                    $sku = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $sku);
                    $sku = preg_replace('/\s+/', ' ', trim($sku));
                    $sku = strtoupper($sku);

                    $price = isset($row[$priceIndex]) ? floatval($row[$priceIndex]) : 0;
                    $stock = isset($row[$stockIndex]) ? intval($row[$stockIndex]) : 0;
                    $videoViews = ($videoViewsIndex !== null && isset($row[$videoViewsIndex])) ? intval($row[$videoViewsIndex]) : null;
                    $adsViews = ($adsViewsIndex !== null && isset($row[$adsViewsIndex])) ? intval($row[$adsViewsIndex]) : null;
                    $afflViews = ($afflViewsIndex !== null && isset($row[$afflViewsIndex])) ? intval($row[$afflViewsIndex]) : null;

                    if (isset($processedSkus[$sku])) {
                        $skipped++;
                        continue;
                    }

                    $existingRecord = TikTokProductTwo::where('sku', $sku)->first();

                    $productUpdateData = [
                        'price' => $price,
                        'stock' => $stock,
                        'sold' => 0,
                    ];
                    if ($videoViews !== null) {
                        $productUpdateData['views'] = $videoViews;
                        $productUpdateData['video_views'] = $videoViews;
                    }
                    if ($adsViews !== null) {
                        $productUpdateData['ads_views'] = $adsViews;
                    }
                    if ($afflViews !== null) {
                        $productUpdateData['affl_views'] = $afflViews;
                    }
                    TikTokProductTwo::updateOrCreate(
                        ['sku' => $sku],
                        $productUpdateData
                    );

                    $view = TiktokTwoShopDataView::firstOrNew(['sku' => $sku]);
                    $values = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);
                    if ($videoViews !== null) {
                        $values['video_views'] = $videoViews;
                    }
                    if ($adsViews !== null) {
                        $values['ads_views'] = $adsViews;
                    }
                    if ($afflViews !== null) {
                        $values['affl_views'] = $afflViews;
                    }
                    $view->value = $values;
                    $view->save();
                    
                    $processedSkus[$sku] = true;
                    
                    if ($existingRecord) {
                        $updated++;
                    } else {
                        $imported++;
                    }
                }
            }

            fclose($handle);

            $total = $imported + $updated;
            $message = "TikTok 2: successfully processed $total product rows!";
            $details = [];
            if ($imported > 0) {
                $details[] = "$imported new";
            }
            if ($updated > 0) {
                $details[] = "$updated updated";
            }
            if ($skipped > 0) {
                $details[] = "$skipped duplicates skipped";
            }
            if (! empty($details)) {
                $message .= ' ('.implode(', ', $details).')';
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            Log::error('TikTok 2 CSV Upload Error: '.$e->getMessage());

            return back()->with('error', 'Error uploading CSV: '.$e->getMessage());
        }
    }

    /**
     * Download Sample CSV
     */
    public function downloadSampleCsv()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['sku', 'price', 'Inv', 'Video Views', 'Ads Views', 'Affl Views'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data (from tiktok file)
        $sampleData = [
            ['20R WoB', '25.99', '6', '1250', '420', '95'],
            ['6R', '16.99', '10', '980', '310', '60'],
            ['HW 1 SKY BLU', '14.47', '1', '340', '110', '15'],
            ['SUH-400 1Pc', '50.19', '99', '2100', '760', '180'],
            ['HW 1', '14.24', '0', '560', '190', '25'],
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(12);

        // Output Download
        $fileName = 'TikTok_Sample.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
        $writer->save('php://output');
        exit;
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

            $marketplaceData = MarketplacePercentage::where('marketplace', 'TiktokShop')
                ->orWhere('marketplace', 'TikTok')
                ->first();
            $marginPct = $marketplaceData ? (float) $marketplaceData->percentage : 80.0;
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
                    // TikTok 1 → tt_ship only (no fallback). TikTok 2 → Ship BB (Values ship_bb) only.
                    $ttShip = $isTiktokTwo
                        ? (isset($pmValues['ship_bb']) ? floatval($pmValues['ship_bb']) : (isset($productMaster->ship_bb) ? floatval($productMaster->ship_bb) : 0))
                        : ($pmValues['tt_ship'] ?? 0);
                    $ship = $ttShip;
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
                if (! empty($row['is_parent']) || (isset($row['Parent']) && str_starts_with((string) $row['Parent'], 'PARENT'))) {
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
            
            // No cache - always update when page loads
            
            // Match JS updateSummary(): all non-parent SKU rows (default "All INV" badge set).
            // Do NOT restrict to INV > 0 — that under-counted sales/PFT vs the live ROI%/GPFT badges.
            $filteredData = collect($products)->filter(function ($p) {
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
            $lowest = $competitors->first();

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
                'product_id'    => 'required|string',
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
            $productId = trim($validated['product_id']);
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
                'product_id'    => 'required|string',
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

            $productId = trim($validated['product_id']);
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
            if (! empty($row['is_parent'])) {
                continue;
            }
            $parent = trim((string) ($row['Parent'] ?? ''));
            if ($parent !== '' && str_starts_with(strtoupper($parent), 'PARENT')) {
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
}

