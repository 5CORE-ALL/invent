<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayThreeDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Ebay3UtilizedAdsController extends Controller
{
    public function ebay3OverUtilizedAdsView()
    {
        // Get chart data for last 30 days
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        $data = DB::table('ebay_3_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
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

        return view('campaign.ebay-three.over-utilized-ads', compact('dates', 'clicks', 'spend', 'acos', 'cvr'))
            ->with('ad_sales', $adSales)
            ->with('ad_sold', $adSold);
    }

    public function ebay3UnderUtilizedAdsView()
    {
        return view('campaign.ebay-three.under-utilized-ads');
    }

    public function ebay3CorrectlyUtilizedAdsView()
    {
        return view('campaign.ebay-three.correctly-utilized-ads');
    }

    public function getEbay3UtilizedAdsData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
            ->orderBy('report_range', 'asc')
            ->get();

        $result = [];
        $matchedCampaignIds = []; 

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $nrValue = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nrValue = $raw['NR'] ?? null;
                }
            }

            $matchedReports = $reports->filter(function ($item) use ($sku) {
                $campaignSku = strtoupper(trim($item->campaign_name ?? ''));
                return $campaignSku === $sku;
            });

            if ($matchedReports->isEmpty()) {
                continue;
            }

            foreach ($matchedReports as $campaign) {
                $matchedCampaignIds[] = $campaign->id;

                $row = [];
                $row['parent'] = $parent;
                $row['sku'] = $pm->sku;
                $row['report_range'] = $campaign->report_range;
                $row['campaign_id'] = $campaign->campaign_id ?? '';
                $row['campaignName'] = $campaign->campaign_name ?? '';
                $row['campaignBudgetAmount'] = $campaign->campaignBudgetAmount ?? 0;
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;

                $adFees = (float) str_replace('USD ', '', $campaign->cpc_ad_fees_payout_currency ?? 0);
                $sales  = (float) str_replace('USD ', '', $campaign->cpc_sale_amount_payout_currency ?? 0);
                $row['l7_spend'] = (float) str_replace('USD ', '', $campaign->report_range == 'L7' ? $campaign->cpc_ad_fees_payout_currency ?? 0 : 0);
                $row['l7_cpc'] = (float) str_replace('USD ', '', $campaign->report_range == 'L7' ? $campaign->cost_per_click ?? 0 : 0);
                $row['l1_spend'] = (float) str_replace('USD ', '', $campaign->report_range == 'L1' ? $campaign->cpc_ad_fees_payout_currency ?? 0 : 0);
                $row['l1_cpc'] = (float) str_replace('USD ', '', $campaign->report_range == 'L1' ? $campaign->cost_per_click ?? 0 : 0);

                $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
                if ($adFees > 0 && $sales == 0) {
                    $row['acos'] = 100;
                } else {
                    $row['acos'] = round($acos, 2);
                }

                $row['adFees'] = $adFees;
                $row['sales'] = $sales;
                $row['NR'] = $nrValue;

                if ($row['NR'] != 'NRA') {
                    $result[] = (object) $row;
                }
            }
        }

        return response()->json([
            'message' => 'fetched successfully',
            'data' => $result,
            'status' => 200,
        ]);
    }

    public function updateEbay3NrData(Request $request)
    {
        $sku   = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        $ebayDataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);

        $jsonData = $ebayDataView->value ?? [];

        $jsonData[$field] = $value;

        $ebayDataView->value = $jsonData;
        $ebayDataView->save();

        return response()->json([
            'status' => 200,
            'message' => "Field updated successfully",
            'updated_json' => $jsonData
        ]);
    }

    public function filterOverUtilizedAds(Request $request)
    {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $data = DB::table('ebay_3_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $startDate)
            ->whereDate('updated_at', '<=', $endDate)
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get();

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

        foreach ($data as $row) {
            $dates[] = $row->report_date;
            
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

            $totalClicks += $clicksVal;
            $totalSpend += $spendVal;
            $totalAdSales += $salesVal;
            $totalAdSold += $soldVal;
        }

        return response()->json([
            'dates' => $dates,
            'clicks' => $clicks,
            'spend' => $spend,
            'ad_sales' => $adSales,
            'ad_sold' => $adSold,
            'acos' => $acos,
            'cvr' => $cvr,
            'totals' => [
                'clicks' => $totalClicks,
                'spend' => $totalSpend,
                'ad_sales' => $totalAdSales,
                'ad_sold' => $totalAdSold,
            ]
        ]);
    }

    public function getCampaignChartData(Request $request)
    {
        $campaignName = $request->input('campaignName');

        $data = DB::table('ebay_3_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('campaign_name', $campaignName)
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', \Carbon\Carbon::now()->subDays(30))
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

        // Fill all 30 days with data or zeros
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
            'dates' => $dates,
            'clicks' => $clicks,
            'spend' => $spend,
            'ad_sales' => $adSales,
            'ad_sold' => $adSold,
            'acos' => $acos,
            'cvr' => $cvr
        ]);
    }

}
