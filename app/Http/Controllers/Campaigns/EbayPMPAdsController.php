<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbaySkuDailyData;
use App\Models\EbayGeneralReport;
use App\Models\EbayListingStatus;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EbayPMPAdsController extends Controller
{
    public function index(){
        $marketplaceData = MarketplacePercentage::where("marketplace", "Ebay" )->first();
        $ebayPercentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $ebayAdPercentage = $marketplaceData ? $marketplaceData->ad_updates : 100;

        // Get chart data for last 30 days
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        $data = DB::table('ebay_general_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(clicks) as clicks,
                SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")) as ad_sales,
                SUM(sales) as ad_sold
            ')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get()
            ->keyBy('report_date');

        // Create array for all 30 days with data or zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;

            if (isset($data[$date])) {
                $row = $data[$date];
                $clicksVal = (int) $row->clicks;
                $spendVal = (float) $row->spend;
                $salesVal = (float) $row->ad_sales;
                $soldVal = (int) $row->ad_sold;

                $clicks[] = $clicksVal;
                $spend[] = $spendVal;
                $adSales[] = $salesVal;
                $adSold[] = $soldVal;

                // ACOS = (Spend / Sales) * 100
                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                // CVR = (Ad Sold / Clicks) * 100
                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                $clicks[] = 0;
                $spend[] = 0;
                $adSales[] = 0;
                $adSold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }
        }

        return view('campaign.ebay-pmp-ads', compact('ebayPercentage','ebayAdPercentage', 'dates', 'clicks', 'spend', 'adSales', 'adSold', 'acos', 'cvr'));
    }

    public function filterEbayPmpAds(Request $request)
    {
        $start = \Carbon\Carbon::parse($request->startDate);
        $end   = \Carbon\Carbon::parse($request->endDate);

        $data = DB::table('ebay_general_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(clicks) as clicks,
                SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")) as ad_sales,
                SUM(sales) as ad_sold
            ')
            ->where('report_range', 'L30')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get()
            ->keyBy('report_date');

        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];
        
        $totalClicks = 0;
        $totalSpend = 0;
        $totalAdSales = 0;
        $totalAdSold = 0;

        $currentDate = $start->copy();
        while ($currentDate->lte($end)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dates[] = $dateStr;

            if (isset($data[$dateStr])) {
                $row = $data[$dateStr];
                $clicksVal = (int) $row->clicks;
                $spendVal = (float) $row->spend;
                $salesVal = (float) $row->ad_sales;
                $soldVal = (int) $row->ad_sold;

                $clicks[] = $clicksVal;
                $spend[] = $spendVal;
                $adSales[] = $salesVal;
                $adSold[] = $soldVal;

                $totalClicks += $clicksVal;
                $totalSpend += $spendVal;
                $totalAdSales += $salesVal;
                $totalAdSold += $soldVal;

                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                $clicks[] = 0;
                $spend[] = 0;
                $adSales[] = 0;
                $adSold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }

            $currentDate->addDay();
        }

        return response()->json([
            'dates'  => $dates,
            'clicks' => $clicks,
            'spend'  => $spend,
            'ad_sales'  => $adSales,
            'ad_sold' => $adSold,
            'acos' => $acos,
            'cvr' => $cvr,
            'totals' => [
                'clicks' => $totalClicks,
                'spend'  => $totalSpend,
                'ad_sales'  => $totalAdSales,
                'ad_sold' => $totalAdSold,
            ]
        ]);
    }

    public function getEbayPmpAdsData(Request $request)
    {
        Log::info('ebay_pmp_ads_data.start');

        try {
            return $this->buildEbayPmpAdsDataResponse();
        } catch (\Throwable $e) {
            Log::error('ebay_pmp_ads_data.failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load eBay PMT Ads data',
                'data' => [],
                'status' => 500,
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Core JSON builder for PMT Ads grid (wrapped by {@see getEbayPmpAdsData} try/catch).
     */
    private function buildEbayPmpAdsDataResponse()
    {
        // SKU normalization function to handle spaces and whitespace
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse multiple spaces to single space
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace characters
            return trim($sku);
        };
        
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        $skus = $productMasters->pluck("sku")->filter()->unique()->values()->all();

        // Daily view windows: load all recent ebay_sku_daily_data (see computeEbayDailyViewAggregates).
        try {
            $viewWindowBySku = $this->computeEbayDailyViewAggregates($skus);
        } catch (\Throwable $e) {
            Log::error('ebay_pmp_view_aggregates_outer_failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $viewWindowBySku = $this->emptyViewAggregatesForSkus($skus);
        }

        // Fetch Shopify data with normalized SKU matching
        $shopifyDataRaw = ShopifySku::whereIn("sku", $skus)->get();
        $shopifyData = [];
        foreach ($shopifyDataRaw as $shopify) {
            // Store with normalized key for matching
            $normalizedKey = $normalizeSku($shopify->sku);
            $shopifyData[$normalizedKey] = $shopify;
        }
        
        $ebayMetrics = EbayMetric::whereIn("sku", $skus)->get();
        
        // Normalize SKUs by replacing non-breaking spaces with regular spaces for matching
        $ebayMetricsNormalized = $ebayMetrics->mapWithKeys(function($item) {
            $normalizedSku = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $item->sku);
            return [$normalizedSku => $item];
        });
        
        $nrValues = EbayListingStatus::whereIn("sku", $skus)->pluck("value", "sku");
        $ebayDataValues = EbayDataView::whereIn("sku", $skus)->pluck("value", "sku");

        $itemIdToSku = $ebayMetrics->pluck('sku', 'item_id')->toArray();
        $campaignIdToSku = $ebayMetrics->pluck('sku', 'campaign_id')->toArray();

        $extraClicksData = EbayGeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->where('report_range', 'L30')
            ->pluck('clicks', 'listing_id')
            ->toArray();

        // Get campaign_id for each listing_id
        $listingToCampaignId = EbayGeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->where('report_range', 'L30')
            ->pluck('campaign_id', 'listing_id')
            ->toArray();

        $generalReports = EbayGeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->whereIn('report_range', ['L60', 'L30', 'L7'])
            ->get();

        // Get campaign listings with bid_percentage. Prioritize COST_PER_SALE rows
        // since they have bid_percentage, but fallback to latest row if no COST_PER_SALE exists.
        $campaignListings = collect();
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
                ->keyBy('listing_id');
        } catch (\Throwable $e) {
            Log::warning('ebay_pmp_apicentral_campaign_listings_unavailable', [
                'message' => $e->getMessage(),
            ]);
        }

        $adMetricsBySku = [];

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

        $marketplaceData = MarketplacePercentage::where("marketplace", "Ebay" )->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        $adPercentage = $marketplaceData ? ($marketplaceData->ad_updates / 100) : 1;

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            // Normalize the SKU for lookup
            $normalizedSku = $normalizeSku($pm->sku);
            
            $shopify = $shopifyData[$normalizedSku] ?? null;
            $ebayMetric = $ebayMetricsNormalized[$pm->sku] ?? null;
            
            // Fallback: If exact match not found, try partial match
            // Prefer longer SKU matches to avoid matching "DS CH YLW" when looking for "DS CH YLW REST-LVR"
            if (!$ebayMetric) {
                $candidates = [];
                foreach ($ebayMetricsNormalized as $metric) {
                    if (stripos($metric->sku, $pm->sku) === 0 || stripos($pm->sku, $metric->sku) === 0) {
                        $candidates[] = $metric;
                    }
                }
                // Sort by SKU length descending and pick the longest match
                if (!empty($candidates)) {
                    usort($candidates, function($a, $b) {
                        return strlen($b->sku) - strlen($a->sku);
                    });
                    $ebayMetric = $candidates[0];
                }
            }

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;
            $row['fba'] = $pm->fba;

            $row["INV"] = $shopify ? ((int) ($shopify->inv ?? 0)) : 0;
            $row["L30"] = $shopify ? ((int) ($shopify->quantity ?? 0)) : 0;

            $row["eBay L30"] = $ebayMetric ? (int) ($ebayMetric->ebay_l30 ?? 0) : 0;
            $row["eBay L60"] = $ebayMetric ? (int) ($ebayMetric->ebay_l60 ?? 0) : 0;
            $row["eBay Price"] = $ebayMetric ? (float) ($ebayMetric->ebay_price ?? 0) : 0;
            $row['price_lmpa'] = $ebayMetric ? $ebayMetric->price_lmpa : null;
            $row['eBay_item_id'] = $ebayMetric ? $ebayMetric->item_id : null;
            $row['ebay_views'] = $ebayMetric ? (int) ($ebayMetric->views ?? 0) : 0;
            $ebayL7 = $ebayMetric ? (int) ($ebayMetric->l7_views ?? 0) : 0;
            $vw = $viewWindowBySku[$normalizedSku] ?? [];
            $row['l60_views'] = (int) ($vw['l60_views'] ?? 0);
            $row['l30_views'] = (int) ($vw['l30_views'] ?? 0);
            $row['l45_views'] = (int) ($vw['l45_views'] ?? 0);
            $row['l7_views'] = (int) ($vw['l7_views'] ?? 0);
            if ($row['l7_views'] === 0 && $ebayL7 > 0
                && (int) ($row['l60_views'] ?? 0) === 0 && (int) ($row['l30_views'] ?? 0) === 0) {
                $row['l7_views'] = $ebayL7;
            }
            $row['yesterday_views'] = (int) ($vw['yesterday_views'] ?? 0);
            $row['views_metrics_fallback'] = false;
            $this->applyEbayMetricsViewsFallback($row);

            $l60v = (int) ($row['l60_views'] ?? 0);
            $l30v = (int) ($row['l30_views'] ?? 0);
            $l1545v = (int) ($row['l45_views'] ?? 0);
            $row['l60_vs_l1545'] = $l1545v === $l60v ? 'NEUTRAL' : ($l1545v > $l60v ? 'GREEN' : 'RED');
            $row['l1545_vs_l30'] = $l1545v === $l30v ? 'NEUTRAL' : ($l1545v < $l30v ? 'GREEN' : 'RED');

            $row['campaign_id'] = ($ebayMetric && isset($listingToCampaignId[$ebayMetric->item_id])) 
                ? $listingToCampaignId[$ebayMetric->item_id] : null;

            if ($ebayMetric && $campaignListings->has($ebayMetric->item_id)) {
                $listing = $campaignListings->get($ebayMetric->item_id);
                $row['bid_percentage'] = $listing->bid_percentage ?? null;
                $row['suggested_bid']  = $listing->suggested_bid ?? null;
            } else {
                $row['bid_percentage'] = null;
                $row['suggested_bid']  = null;
            }


            $row["E Dil%"] = ($row["eBay L30"] && $row["INV"] > 0)
                ? round(($row["eBay L30"] / $row["INV"]), 2)
                : 0;

            $pmtData = $adMetricsBySku[$sku] ?? [];
            foreach (['L60', 'L30', 'L7'] as $range) {
                $metrics = $pmtData[$range] ?? [];
                foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT'] as $suffix) {
                    $key = "Pmt{$suffix}{$range}";
                    $row[$key] = $metrics[$suffix] ?? 0;
                }
            }

            $row["PmtClkL30"] = $adMetricsBySku[$sku]['L30']['Clk'] ?? 0;
            if ($ebayMetric && isset($extraClicksData[$ebayMetric->item_id])) {
                $row["PmtClkL30"] += (int) $extraClicksData[$ebayMetric->item_id];
            }

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

            $price = floatval($row["eBay Price"] ?? 0);
            $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);

            $generalSpent = $adMetricsBySku[$sku]['L30']['GENERAL_SPENT'] ?? 0;
            $denominator = ($price * $units_ordered_l30);
            $row["TacosL30"] = $denominator > 0 ? round(($generalSpent / $denominator), 4) : 0;

            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["PFT %"] = round(
                $price > 0 ? (($price * $percentage - $lp - $ship) / $price) : 0,
                2
            );
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) : 0,
                2
            );
            $bidPct = $row["bid_percentage"];
            $row["TPFT"] = $row["PFT %"] + $adPercentage - (is_numeric($bidPct) ? (float) $bidPct : 0);
            $row["percentage"] = $percentage;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;

            $row['NRL'] = "";
            $row['SPRICE'] = null;
            $row['SPFT'] = null;
            $row['SROI'] = null;
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;

            // Get nr_req from EbayListingStatus
            if (isset($nrValues[$pm->sku])) {
                $nrRaw = $nrValues[$pm->sku];
                if (!is_array($nrRaw)) {
                    $nrRaw = json_decode($nrRaw, true);
                }
                if (is_array($nrRaw)) {
                    $row['NRL'] = $nrRaw['nr_req'] ?? null;
                }
            }

            // Get other fields from EbayDataView
            if (isset($ebayDataValues[$pm->sku])) {
                $ebayRaw = $ebayDataValues[$pm->sku];
                if (!is_array($ebayRaw)) {
                    $ebayRaw = json_decode($ebayRaw, true);
                }
                if (is_array($ebayRaw)) {
                    $row['SPRICE'] = $ebayRaw['SPRICE'] ?? null;
                    $row['SPFT'] = $ebayRaw['SPFT'] ?? null;
                    $row['SROI'] = $ebayRaw['SROI'] ?? null;
                    $row['Listed'] = isset($ebayRaw['Listed']) ? filter_var($ebayRaw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($ebayRaw['Live']) ? filter_var($ebayRaw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($ebayRaw['APlus']) ? filter_var($ebayRaw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            $row["image_path"] = $shopify ? ($shopify->image_src ?? null) : null;
            $row["image_path"] = $row["image_path"] ?? ($values["image_path"] ?? ($pm->image_path ?? null));

            if($row['NRL'] !== 'NRL' && stripos($pm->sku, 'PARENT') === false){
                $result[] = (object) $row;
            }
        }

        return response()->json([
            "message" => "eBay Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }

    /**
     * @param  array<int, string|null>  $skus
     * @return array<string, array{l60_views: int, l30_views: int, l45_views: int, l7_views: int, yesterday_views: int}>
     */
    private function emptyViewAggregatesForSkus(array $skus): array
    {
        $normalizeSku = function ($sku) {
            if (empty($sku)) {
                return '';
            }
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);

            return trim($sku);
        };
        $out = [];
        foreach (array_values(array_unique(array_filter(array_map($normalizeSku, $skus)))) as $s) {
            $out[$s] = [
                'l60_views' => 0,
                'l30_views' => 0,
                'l45_views' => 0,
                'l7_views' => 0,
                'yesterday_views' => 0,
            ];
        }

        return $out;
    }

    /**
     * Per-SKU view metrics from ebay_sku_daily_data (cumulative listing views per snapshot).
     *
     * Uses (1) sum of day-over-day deltas when the counter updates daily, and (2) cumulative
     * boundary math (last snapshot in window minus last snapshot before window) when the
     * counter is flat for many days (delta sum would be 0 incorrectly).
     *
     * Rows are indexed by canonical SKU (alphanumeric only) so ProductMaster SKUs match
     * daily rows even when spacing differs.
     *
     * Date windows use app timezone (config app.timezone). Rolling windows exclude
     * "today" (only complete calendar days through yesterday): L60 = days -60..-1,
     * L30 = -30..-1, L7 = -7..-1, L1 = yesterday. L15-45 (stored as l45_views) =
     * max(0, L60 - L30), i.e. views in days -60..-31 (the 30 days before the L30 window).
     *
     * @param  array<int, string|null>  $skus
     * @return array<string, array{l60_views: int, l30_views: int, l45_views: int, l7_views: int, yesterday_views: int}>
     */
    private function computeEbayDailyViewAggregates(array $skus): array
    {
        $normalizeSku = function ($sku) {
            if (empty($sku)) {
                return '';
            }
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);

            return trim($sku);
        };

        $normalizedSkus = array_values(array_unique(array_filter(array_map($normalizeSku, $skus))));
        $out = [];
        foreach ($normalizedSkus as $s) {
            $out[$s] = [
                'l60_views' => 0,
                'l30_views' => 0,
                'l45_views' => 0,
                'l7_views' => 0,
                'yesterday_views' => 0,
            ];
        }

        if ($normalizedSkus === []) {
            return $out;
        }

        if (! Schema::hasTable('ebay_sku_daily_data')) {
            Log::warning('ebay_pmp_missing_table_ebay_sku_daily_data');

            return $out;
        }

        $today = \Carbon\Carbon::now(config('app.timezone'))->startOfDay();
        $minDate = $today->copy()->subDays(70);
        $minDateStr = $minDate->format('Y-m-d');

        // Only load daily rows for SKUs we need (avoids OOM when the table has all eBay SKUs).
        $skuCandidates = $this->expandSkuCandidatesForDailyDataLookup($normalizedSkus);
        if ($skuCandidates === []) {
            return $out;
        }

        /** @var array<string, array<string, int>> $byCanonical date => cumulative views */
        $byCanonical = [];
        $rowsLoaded = 0;

        try {
            foreach (array_chunk($skuCandidates, 400) as $skuChunk) {
                $q = DB::table('ebay_sku_daily_data')
                    ->where('record_date', '>=', $minDateStr)
                    ->whereIn('sku', $skuChunk)
                    ->orderBy('sku')
                    ->orderBy('record_date');

                foreach ($q->cursor() as $r) {
                    $rowsLoaded++;
                    $canon = $this->ebaySkuCanonicalKey((string) $r->sku);
                    if ($canon === '') {
                        continue;
                    }
                    $d = $this->formatSqlDateString($r->record_date);
                    $views = $this->extractDailyDataViews($r->daily_data ?? null);
                    if (! isset($byCanonical[$canon])) {
                        $byCanonical[$canon] = [];
                    }
                    $byCanonical[$canon][$d] = max($byCanonical[$canon][$d] ?? 0, $views);
                }
            }
        } catch (\Throwable $e) {
            Log::error('ebay_pmp_daily_data_query_failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->emptyViewAggregatesForSkus($skus);
        }

        $yesterday = $today->copy()->subDay()->format('Y-m-d');
        // Rolling windows: last N complete days through yesterday (exclude calendar "today").
        $l60Start = $today->copy()->subDays(60)->format('Y-m-d');
        $l60End = $yesterday;
        $l30Start = $today->copy()->subDays(30)->format('Y-m-d');
        $l30End = $yesterday;
        $l7Start = $today->copy()->subDays(7)->format('Y-m-d');
        $l7End = $yesterday;

        foreach ($normalizedSkus as $skuKey) {
            $canon = $this->ebaySkuCanonicalKey($skuKey);
            $dates = $byCanonical[$canon] ?? [];
            if ($dates === []) {
                continue;
            }
            ksort($dates);
            $dateKeys = array_keys($dates);
            $dailyDeltas = [];
            for ($i = 0; $i < count($dateKeys); $i++) {
                $dk = $dateKeys[$i];
                $cum = $dates[$dk];
                if ($i === 0) {
                    $dailyDeltas[$dk] = 0;
                    continue;
                }
                $prevCum = $dates[$dateKeys[$i - 1]];
                $dailyDeltas[$dk] = max(0, $cum - $prevCum);
            }

            $sumDeltas = function (string $startStr, string $endStr) use ($dailyDeltas) {
                $sum = 0;
                $ts = strtotime($startStr.' 00:00:00');
                $endTs = strtotime($endStr.' 23:59:59');
                if ($ts === false || $endTs === false) {
                    return 0;
                }
                while ($ts <= $endTs) {
                    $ds = date('Y-m-d', $ts);
                    $sum += (int) ($dailyDeltas[$ds] ?? 0);
                    $ts = strtotime('+1 day', $ts);
                }

                return $sum;
            };

            $deltaL60 = $sumDeltas($l60Start, $l60End);
            $deltaL30 = $sumDeltas($l30Start, $l30End);
            $deltaL7 = $sumDeltas($l7Start, $l7End);
            $deltaYest = (int) ($dailyDeltas[$yesterday] ?? 0);

            $boundaryL60 = $this->viewsGainFromCumulativeSeries($dates, $l60Start, $l60End);
            $boundaryL30 = $this->viewsGainFromCumulativeSeries($dates, $l30Start, $l30End);
            $boundaryL7 = $this->viewsGainFromCumulativeSeries($dates, $l7Start, $l7End);
            $boundaryYest = $this->yesterdayViewsFromCumulativeSeries($dates, $yesterday);

            $l60 = (int) max($deltaL60, $boundaryL60);
            $l30 = (int) max($deltaL30, $boundaryL30);
            $l7 = (int) max($deltaL7, $boundaryL7);
            $l1 = (int) max($deltaYest, $boundaryYest);

            if ($l30 > $l60) {
                Log::warning('ebay_pmp_views_l30_exceeds_l60', [
                    'sku' => $skuKey,
                    'l30_raw' => $l30,
                    'l60_raw' => $l60,
                ]);
                $l30 = $l60;
            }
            if ($l7 > $l30) {
                Log::warning('ebay_pmp_views_l7_exceeds_l30', [
                    'sku' => $skuKey,
                    'l7_raw' => $l7,
                    'l30' => $l30,
                ]);
                $l7 = $l30;
            }
            if ($l1 > $l7) {
                Log::warning('ebay_pmp_views_l1_exceeds_l7', [
                    'sku' => $skuKey,
                    'l1_raw' => $l1,
                    'l7' => $l7,
                ]);
                $l1 = $l7;
            }

            $l1545 = max(0, $l60 - $l30);

            $out[$skuKey]['l60_views'] = $l60;
            $out[$skuKey]['l30_views'] = $l30;
            $out[$skuKey]['l45_views'] = $l1545;
            $out[$skuKey]['l7_views'] = $l7;
            $out[$skuKey]['yesterday_views'] = $l1;
        }

        if (filter_var(env('EBAY_PMP_VIEWS_DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
            Log::info('ebay_pmp_views_daily_aggregate', [
                'timezone' => (string) config('app.timezone'),
                'today' => $today->toDateString(),
                'yesterday' => $yesterday,
                'l60_window' => [$l60Start, $l60End],
                'l30_window' => [$l30Start, $l30End],
                'l7_window' => [$l7Start, $l7End],
                'l1545_derived' => 'max(0, l60 - l30) === views in days -60..-31',
                'daily_rows_loaded' => $rowsLoaded,
                'canonical_series_count' => count($byCanonical),
                'sku_candidate_count' => count($skuCandidates),
            ]);
        }

        return $out;
    }

    /**
     * When daily snapshots are missing, stale, or cumulative counters are flat (day deltas all 0),
     * estimate windows from ebay_metrics already on the row (ebay_views, l7_views).
     * Sets views_metrics_fallback = true so the UI can distinguish from true daily-derived values.
     *
     * @param  array<string, mixed>  $row
     */
    private function applyEbayMetricsViewsFallback(array &$row): void
    {
        $l60 = (int) ($row['l60_views'] ?? 0);
        $l30 = (int) ($row['l30_views'] ?? 0);
        if ($l60 > 0 || $l30 > 0) {
            $row['l45_views'] = max(0, $l60 - $l30);

            return;
        }

        $l7 = (int) ($row['l7_views'] ?? 0);
        $tv = (int) ($row['ebay_views'] ?? 0);
        if ($l7 <= 0 && $tv <= 0) {
            return;
        }

        if ($l7 > 0) {
            $rate = $l7 / 7.0;
            $row['yesterday_views'] = (int) max(0, round($rate));
            $row['l30_views'] = (int) max(0, round($rate * 30));
            $row['l60_views'] = (int) max(0, round($rate * 60));
            $row['l45_views'] = max(0, $row['l60_views'] - $row['l30_views']);
        } else {
            $rate = $tv / 30.0;
            $row['yesterday_views'] = (int) max(0, round($rate));
            $row['l30_views'] = (int) max(0, round($rate * 30));
            $row['l60_views'] = (int) max(0, round($rate * 60));
            $row['l45_views'] = max(0, $row['l60_views'] - $row['l30_views']);
        }

        $row['views_metrics_fallback'] = true;
    }

    /**
     * Canonical SKU for matching ProductMaster, ebay_metrics, and ebay_sku_daily_data rows.
     */
    private function ebaySkuCanonicalKey(string $sku): string
    {
        $s = strtoupper(trim($sku));
        $s = preg_replace('/\s+/u', ' ', $s);

        return preg_replace('/[^A-Z0-9]/', '', $s);
    }

    /**
     * Possible `sku` column values in ebay_sku_daily_data for ProductMaster SKUs (spacing/dash variants).
     *
     * @param  array<int, string>  $normalizedSkus
     * @return array<int, string>
     */
    private function expandSkuCandidatesForDailyDataLookup(array $normalizedSkus): array
    {
        $skuCandidates = [];
        foreach ($normalizedSkus as $ns) {
            if ($ns === '') {
                continue;
            }
            $skuCandidates[] = $ns;
            $skuCandidates[] = str_replace(' ', '-', $ns);
            $skuCandidates[] = str_replace('-', ' ', $ns);
            $skuCandidates[] = preg_replace('/\s+/u', '', $ns);
            $compact = preg_replace('/\s+/u', ' ', $ns);
            $skuCandidates[] = preg_replace('/-+/', '-', $compact);
        }

        return array_values(array_unique(array_filter($skuCandidates)));
    }

    /**
     * @param  mixed  $recordDate
     */
    private function formatSqlDateString($recordDate): string
    {
        if ($recordDate instanceof \DateTimeInterface) {
            return $recordDate->format('Y-m-d');
        }
        $s = (string) $recordDate;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s, $m)) {
            return substr($s, 0, 10);
        }

        return $s !== '' ? substr($s, 0, 10) : '';
    }

    /**
     * @param  mixed  $dailyData  JSON string or decoded array from DB
     */
    private function extractDailyDataViews($dailyData): int
    {
        if ($dailyData === null || $dailyData === '') {
            return 0;
        }
        if (is_string($dailyData)) {
            $decoded = json_decode($dailyData, true);
        } else {
            $decoded = is_array($dailyData) ? $dailyData : [];
        }

        return isset($decoded['views']) ? (int) $decoded['views'] : 0;
    }

    /**
     * Approximate views gained in [rangeStart, rangeEnd] from cumulative snapshots (date => total views).
     */
    private function viewsGainFromCumulativeSeries(array $dateToCum, string $rangeStart, string $rangeEnd): int
    {
        if ($dateToCum === []) {
            return 0;
        }
        ksort($dateToCum);
        $vBefore = null;
        foreach ($dateToCum as $d => $v) {
            if ($d < $rangeStart) {
                $vBefore = (int) $v;
            }
        }
        $vEnd = null;
        foreach ($dateToCum as $d => $v) {
            if ($d >= $rangeStart && $d <= $rangeEnd) {
                $vEnd = (int) $v;
            }
        }
        if ($vEnd === null) {
            return 0;
        }
        if ($vBefore === null) {
            $firstV = null;
            foreach ($dateToCum as $d => $v) {
                if ($d >= $rangeStart && $d <= $rangeEnd) {
                    $firstV = (int) $v;
                    break;
                }
            }

            return max(0, $vEnd - ($firstV ?? $vEnd));
        }

        return max(0, $vEnd - $vBefore);
    }

    /**
     * Views on calendar yesterday: last cumulative before yesterday vs snapshot on yesterday (if any).
     */
    private function yesterdayViewsFromCumulativeSeries(array $dateToCum, string $yesterday): int
    {
        if ($dateToCum === []) {
            return 0;
        }
        ksort($dateToCum);
        $before = null;
        foreach ($dateToCum as $d => $v) {
            if ($d < $yesterday) {
                $before = (int) $v;
            }
        }
        $onDay = $dateToCum[$yesterday] ?? null;
        if ($onDay === null || $before === null) {
            return 0;
        }

        return max(0, (int) $onDay - $before);
    }

    /**
     * Debug-only: raw cumulative series + computed windows for a SKU (canonical match).
     */
    public function debugEbaySkuViews(Request $request, string $sku)
    {
        if (! config('app.debug') && ! filter_var(env('EBAY_PMP_VIEWS_DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
            abort(404);
        }

        $normalizeSku = function ($s) {
            if (empty($s)) {
                return '';
            }
            $s = strtoupper(trim($s));
            $s = preg_replace('/\s+/u', ' ', $s);

            return trim($s);
        };

        $normalized = $normalizeSku($sku);
        $canon = $this->ebaySkuCanonicalKey($normalized);
        $today = \Carbon\Carbon::now(config('app.timezone'))->startOfDay();
        $minDate = $today->copy()->subDays(120);

        $rows = EbaySkuDailyData::query()
            ->where('record_date', '>=', $minDate->format('Y-m-d'))
            ->where(function ($q) use ($sku, $normalized, $canon) {
                $q->where('sku', $sku)
                    ->orWhere('sku', $normalized)
                    ->orWhereRaw('REPLACE(REPLACE(UPPER(TRIM(sku)), "-", ""), " ", "") = ?', [$canon]);
            })
            ->orderBy('record_date')
            ->get(['sku', 'record_date', 'daily_data', 'updated_at']);

        $series = [];
        foreach ($rows as $r) {
            $d = $r->record_date instanceof \DateTimeInterface
                ? $r->record_date->format('Y-m-d')
                : \Carbon\Carbon::parse($r->record_date)->format('Y-m-d');
            $v = isset($r->daily_data['views']) ? (int) $r->daily_data['views'] : 0;
            $series[$d] = max($series[$d] ?? 0, $v);
        }
        ksort($series);

        $yesterday = $today->copy()->subDay()->format('Y-m-d');
        $l60Start = $today->copy()->subDays(60)->format('Y-m-d');
        $l60End = $yesterday;
        $l30Start = $today->copy()->subDays(30)->format('Y-m-d');
        $l30End = $yesterday;
        $l7Start = $today->copy()->subDays(7)->format('Y-m-d');
        $l7End = $yesterday;

        $l60 = $this->viewsGainFromCumulativeSeries($series, $l60Start, $l60End);
        $l30 = $this->viewsGainFromCumulativeSeries($series, $l30Start, $l30End);
        $l7 = $this->viewsGainFromCumulativeSeries($series, $l7Start, $l7End);
        $l1 = $this->yesterdayViewsFromCumulativeSeries($series, $yesterday);
        if ($l30 > $l60) {
            $l30 = $l60;
        }
        if ($l7 > $l30) {
            $l7 = $l30;
        }
        if ($l1 > $l7) {
            $l1 = $l7;
        }
        $l1545 = max(0, $l60 - $l30);

        return response()->json([
            'input' => $sku,
            'normalized' => $normalized,
            'canonical' => $canon,
            'timezone' => config('app.timezone'),
            'today' => $today->toDateString(),
            'windows' => [
                'l60' => [$l60Start, $l60End],
                'l30' => [$l30Start, $l30End],
                'l7' => [$l7Start, $l7End],
                'l1545_derived' => 'max(0, l60 - l30)',
                'yesterday' => $yesterday,
            ],
            'row_count' => $rows->count(),
            'series' => $series,
            'computed' => [
                'l60' => $l60,
                'l30' => $l30,
                'l1545' => $l1545,
                'l7' => $l7,
                'l1' => $l1,
            ],
        ]);
    }

    private function extractNumber($value)
    {
        if (empty($value)) return 0;
        return (float) preg_replace('/[^\d.]/', '', $value);
    }

    public function saveEbayPMTSpriceToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent']);

        if (!$sku || !$spriceData['sprice']) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }


        $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);

        $existing = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?: []);

        $merged = array_merge($existing, [
            'SPRICE' => $spriceData['sprice'],
            'SPFT' => $spriceData['spft_percent'],
            'SROI' => $spriceData['sroi_percent'],
        ]);

        $ebayDataView->value = $merged;
        $ebayDataView->save();

        return response()->json(['message' => 'Data saved successfully.']);
    }

    public function getCampaignChartData(Request $request)
    {
        $campaignId = $request->input('campaign_id');
        
        if (!$campaignId) {
            return response()->json(['error' => 'Campaign ID is required'], 400);
        }

        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        $data = DB::table('ebay_general_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(clicks) as clicks,
                SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")) as ad_sales,
                SUM(sales) as ad_sold
            ')
            ->where('campaign_id', $campaignId)
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get()
            ->keyBy('report_date');

        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;

            if (isset($data[$date])) {
                $row = $data[$date];
                $clicksVal = (int) $row->clicks;
                $spendVal = (float) $row->spend;
                $salesVal = (float) $row->ad_sales;
                $soldVal = (int) $row->ad_sold;

                $clicks[] = $clicksVal;
                $spend[] = $spendVal;
                $adSales[] = $salesVal;
                $adSold[] = $soldVal;

                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                $clicks[] = 0;
                $spend[] = 0;
                $adSales[] = 0;
                $adSold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }
        }

        return response()->json([
            'dates'  => $dates,
            'clicks' => $clicks,
            'spend'  => $spend,
            'ad_sales'  => $adSales,
            'ad_sold' => $adSold,
            'acos' => $acos,
            'cvr' => $cvr,
        ]);
    }

    public function updateEbayPercentage(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');

            $marketplace = MarketplacePercentage::where('marketplace', 'Ebay')->first();

            $percent = $marketplace->percentage ?? 0;
            $adUpdates = $marketplace->ad_updates ?? 0;

            if ($type === 'percentage') {
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return response()->json(['status' => 400, 'message' => 'Invalid percentage value'], 400);
                }
                $percent = $value;
            }

            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json(['status' => 400, 'message' => 'Invalid ad_updates value'], 400);
                }
                $adUpdates = $value;
            }

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

}
