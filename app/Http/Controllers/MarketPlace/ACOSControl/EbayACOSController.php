<?php

namespace App\Http\Controllers\MarketPlace\ACOSControl;

use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EbayACOSController extends Controller
{
    public function ebayOverUtiAcosPink(){
        return view('campaign.ebay-over-uti-acos-pink');
    }

    public function ebayOverUtiAcosGreen(){
        return view('campaign.ebay-over-uti-acos-green');
    }

    public function ebayOverUtiAcosRed(){
        return view('campaign.ebay-over-uti-acos-red');
    }

    public function ebayUnderUtiAcosPink(){
        return view('campaign.ebay-under-uti-acos-pink');
    }

    public function ebayUnderUtiAcosGreen(){
        return view('campaign.ebay-under-uti-acos-green');
    }

    public function ebayUnderUtiAcosRed(){
        return view('campaign.ebay-under-uti-acos-red');
    }

    public function getEbayUtilisationAcosData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');

        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;
            $ebay = $ebayMetricData[$pm->sku] ?? null;

            $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($sku) {
                return stripos($item->campaign_name, $sku) !== false;
            });

            $matchedCampaignL1 = $ebayCampaignReportsL1->first(function ($item) use ($sku) {
                return stripos($item->campaign_name, $sku) !== false;
            });

            $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                return stripos($item->campaign_name, $sku) !== false;
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['price']  = $ebay->ebay_price ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaign_name ?? ($matchedCampaignL1->campaign_name ?? '');
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');

            $adFees   = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $sales    = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0 );

            $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
            
            if($adFees > 0 && $sales === 0){
                $row['acos'] = 100;
            }else{
                $row['acos'] = $acos;
            }

            $row['l7_spend'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0);
            $row['l7_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0);
            $row['l1_spend'] = (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0);
            $row['l1_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0);

            $row['NR'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                }
            }

            // Only show items with price >= 30 and NR !== 'NRA'
            if ($row['NR'] !== 'NRA' && $row['price'] >= 30) {
                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function getCampaignChartData(Request $request)
    {
        try {
            $campaignName = $request->get('campaignName');
            $startDate = $request->get('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
            
            Log::info('Campaign Chart Request', [
                'campaign' => $campaignName,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            // Initialize arrays
            $dates = [];
            $clicks = [];
            $spend = [];
            $ad_sales = [];
            $ad_sold = [];
            $acos = [];
            $cvr = [];
        
        // Generate date range
        $period = Carbon::createFromFormat('Y-m-d', $startDate);
        $end = Carbon::createFromFormat('Y-m-d', $endDate);
        
        while ($period->lte($end)) {
            $currentDate = $period->format('Y-m-d');
            $dates[] = $currentDate;
            
            // For now, let's use simple data from ebay_priority_reports
            $report = DB::table('ebay_priority_reports')
                ->where('campaign_name', $campaignName)
                ->where('report_range', 'L1') // Use daily data
                ->whereDate('updated_at', $currentDate)
                ->first();
            
            if ($report) {
                $clicks[] = (int) ($report->cpc_clicks ?? 0);
                $currentSpend = (float) str_replace(['USD ', '$', ','], '', $report->cpc_ad_fees_payout_currency ?? '0');
                $spend[] = $currentSpend;
                $currentSales = (float) str_replace(['USD ', '$', ','], '', $report->cpc_sale_amount_payout_currency ?? '0');
                $ad_sales[] = $currentSales;
                $ad_sold[] = (int) ($report->cpc_attributed_sales ?? 0);
                
                // Calculate ACOS (Advertising Cost of Sales)
                $currentAcos = $currentSales > 0 ? ($currentSpend / $currentSales) * 100 : 0;
                $acos[] = round($currentAcos, 2);
                
                // Calculate CVR
                $currentClicks = (int) ($report->cpc_clicks ?? 0);
                $currentSold = (int) ($report->cpc_attributed_sales ?? 0);
                $currentCvr = $currentClicks > 0 ? ($currentSold / $currentClicks) * 100 : 0;
                $cvr[] = round($currentCvr, 2);
            } else {
                // Fill with zeros if no data found
                $clicks[] = 0;
                $spend[] = 0;
                $ad_sales[] = 0;
                $ad_sold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }
            
            $period->addDay();
        }
        
            return response()->json([
                'dates' => $dates,
                'clicks' => $clicks,
                'spend' => $spend,
                'ad_sales' => $ad_sales,
                'ad_sold' => $ad_sold,
                'acos' => $acos,
                'cvr' => $cvr
            ]);
        } catch (\Exception $e) {
            Log::error('Campaign Chart Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load campaign data'], 500);
        }
    }

    public function filterUnderUtilizedAds(Request $request)
    {
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        
        // Generate date-wise filtered data based on date range
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        
        $dates = [];
        $clicks = [];
        $spend = [];
        $ad_sales = [];
        $ad_sold = [];
        $acos = [];
        $cvr = [];
        
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $currentDate = $date->format('Y-m-d');
            $dates[] = $currentDate;
            
            // Get data from ebay_metrics for this specific date
            $dayData = DB::table('ebay_metrics')
                ->where('report_date', $currentDate)
                ->selectRaw('
                    SUM(views) as total_views,
                    SUM(organic_clicks) as total_clicks,
                    COUNT(*) as total_items,
                    AVG(ebay_price) as avg_price
                ')
                ->first();
            
            // Get data from ebay_general_reports (using L1 as daily proxy)
            $reportData = DB::table('ebay_general_reports')
                ->where('report_range', 'L1')
                ->selectRaw('
                    SUM(clicks) as report_clicks,
                    SUM(impressions) as report_impressions,
                    SUM(sales) as report_sales,
                    SUM(CASE 
                        WHEN ad_fees IS NOT NULL 
                        THEN CAST(REPLACE(REPLACE(ad_fees, "USD ", ""), "$", "") AS DECIMAL(10,2)) 
                        ELSE 0 
                    END) as report_spend
                ')
                ->first();
            
            // Combine and calculate metrics
            $dailyClicks = ($dayData->total_clicks ?? 0) + ($reportData->report_clicks ?? 0);
            $dailySpend = $reportData->report_spend ?? 0;
            $dailySales = ($reportData->report_sales ?? 0) * ($dayData->avg_price ?? 0);
            $dailySold = $reportData->report_sales ?? 0;
            
            $clicks[] = $dailyClicks;
            $spend[] = $dailySpend;
            $ad_sales[] = $dailySales;
            $ad_sold[] = $dailySold;
            $acos[] = $dailySales > 0 ? (($dailySpend / $dailySales) * 100) : 0;
            $cvr[] = $dailyClicks > 0 ? (($dailySold / $dailyClicks) * 100) : 0;
        }
        
        // Calculate totals for cards
        $totalClicks = array_sum($clicks);
        $totalSpend = array_sum($spend);
        $totalAdSales = array_sum($ad_sales);
        $totalAdSold = array_sum($ad_sold);
        
        return response()->json([
            'dates' => $dates,
            'clicks' => $clicks,
            'spend' => $spend,
            'ad_sales' => $ad_sales,
            'ad_sold' => $ad_sold,
            'acos' => $acos,
            'cvr' => $cvr,
            'totals' => [
                'clicks' => $totalClicks,
                'spend' => $totalSpend,
                'ad_sales' => $totalAdSales,
                'ad_sold' => $totalAdSold
            ]
        ]);
    }
}
