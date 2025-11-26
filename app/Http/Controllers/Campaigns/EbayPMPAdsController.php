<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbayGeneralReport;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    public function getEbayPmpAdsData()
    {
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        $skus = $productMasters->pluck("sku")->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");
        $ebayMetrics = EbayMetric::whereIn("sku", $skus)->get()->keyBy("sku");
        $nrValues = EbayDataView::whereIn("sku", $skus)->pluck("value", "sku");

        $itemIdToSku = $ebayMetrics->pluck('sku', 'item_id')->toArray();
        $campaignIdToSku = $ebayMetrics->pluck('sku', 'campaign_id')->toArray();

        $extraClicksData = EbayGeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->where('report_range', 'L30')
            ->pluck('clicks', 'listing_id')
            ->toArray();

        $generalReports = EbayGeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->whereIn('report_range', ['L60', 'L30', 'L7'])
            ->get();

        $priorityReports = EbayPriorityReport::whereIn('campaign_id', array_keys($campaignIdToSku))
            ->whereIn('report_range', ['L60', 'L30', 'L7'])
            ->get();

        $campaignListings = DB::connection('apicentral')
            ->table('ebay_campaign_ads_listings as t')
            ->join(DB::raw('(SELECT listing_id, MAX(id) AS max_id 
                            FROM ebay_campaign_ads_listings 
                            WHERE funding_strategy="COST_PER_SALE"
                            GROUP BY listing_id) x'), 
                't.id', '=', 'x.max_id')
            ->select('t.listing_id', 't.bid_percentage', 't.suggested_bid')
            ->get()
            ->keyBy('listing_id');


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

        foreach ($priorityReports as $report) {
            $sku = $campaignIdToSku[$report->campaign_id] ?? null;
            if (!$sku) continue;

            $range = strtoupper($report->report_range);

            $adMetricsBySku[$sku][$range]['PRIORITY_SPENT'] =
                ($adMetricsBySku[$sku][$range]['PRIORITY_SPENT'] ?? 0) + $this->extractNumber($report->cpc_ad_fees_payout_currency);
        }

        $marketplaceData = MarketplacePercentage::where("marketplace", "Ebay" )->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        $adPercentage = $marketplaceData ? ($marketplaceData->ad_updates / 100) : 1;

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;
            $ebayMetric = $ebayMetrics[$pm->sku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;
            $row['fba'] = $pm->fba;

            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;

            $row["eBay L30"] = $ebayMetric->ebay_l30 ?? 0;
            $row["eBay L60"] = $ebayMetric->ebay_l60 ?? 0;
            $row["eBay Price"] = $ebayMetric->ebay_price ?? 0;
            $row['price_lmpa'] = $ebayMetric->price_lmpa ?? null;
            $row['eBay_item_id'] = $ebayMetric->item_id ?? null;
            $row['ebay_views'] = $ebayMetric->views ?? 0;

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
                foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT', 'PRIORITY_SPENT'] as $suffix) {
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
            $prioritySpent = $adMetricsBySku[$sku]['L30']['PRIORITY_SPENT'] ?? 0;
            $denominator = ($price * $units_ordered_l30);
            $row["TacosL30"] = $denominator > 0 ? round((($generalSpent + $prioritySpent) / $denominator), 4) : 0;

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
            $row["TPFT"] = $row["PFT %"] + $adPercentage - $row["bid_percentage"];
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

            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL'] = $raw['NRL'] ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $row['SPFT'] = $raw['SPFT'] ?? null;
                    $row['SROI'] = $raw['SROI'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            $row["image_path"] = $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null));

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
