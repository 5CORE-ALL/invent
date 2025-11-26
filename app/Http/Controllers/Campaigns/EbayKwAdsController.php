<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EbayKwAdsController extends Controller
{
    public function index(){
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);
        $today = \Carbon\Carbon::now();

        // Get aggregated data by date from ebay_priority_reports
        $data = DB::table('ebay_priority_reports')
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

                // ACOS = (Spend / Sales) * 100
                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                // CVR = (Ad Sold / Clicks) * 100
                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                // No data for this date, fill with zeros
                $clicks[] = 0;
                $spend[] = 0;
                $adSales[] = 0;
                $adSold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }
        }

        return view('campaign.ebay-kw-ads', compact('dates', 'clicks', 'spend', 'adSales', 'adSold', 'acos', 'cvr'));
    }

    public function filterEbayKwAds(Request $request)
    {
        $start = \Carbon\Carbon::parse($request->startDate);
        $end   = \Carbon\Carbon::parse($request->endDate);

        $data = DB::table('ebay_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('report_range', 'L30')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get()
            ->keyBy('report_date');

        // Create array for all days in range with data or zeros
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

                // ACOS = (Spend / Sales) * 100
                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                // CVR = (Ad Sold / Clicks) * 100
                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                // No data for this date, fill with zeros
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

    public function getEbayKwAdsData(){

        $productMasters = ProductMaster::whereNull('deleted_at')
            ->orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $productMasterSkus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Get additional RUNNING campaigns that are not in ProductMaster but are valid SKUs (not "Campaign Date" format)
        $additionalRunningCampaigns = EbayPriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->pluck('campaign_name')
            ->unique()
            ->filter(function($name) use ($productMasterSkus) {
                $nameUpper = strtoupper(trim($name));
                return !in_array($nameUpper, array_map('strtoupper', $productMasterSkus));
            })
            ->values()
            ->all();

        // Merge both lists
        $skus = array_merge($productMasterSkus, $additionalRunningCampaigns);

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $periods = ['L7', 'L15', 'L30', 'L60'];
        $campaignReports = [];
        foreach ($periods as $period) {
            $campaignReports[$period] = EbayPriorityReport::where('report_range', $period)
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
                ->get();
        }

        $result = [];

        // Process ProductMasters first
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [
                'parent' => $parent,
                'sku'    => $pm->sku,
                'INV'    => $shopify->inv ?? 0,
                'L30'    => $shopify->quantity ?? 0,
                'NR'     => ''
            ];

            // Find matching campaigns, prioritize RUNNING status
            $matchedCampaigns = $campaignReports['L30']->filter(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });
            
            $matchedCampaignL30 = $matchedCampaigns->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaigns->first();

            $row['campaignName'] = $matchedCampaignL30->campaign_name ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? 0;
            $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? '';

            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                }
            }

            foreach ($periods as $period) {
                $matchedCampaign = $campaignReports[$period]->first(function ($item) use ($sku) {
                    return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
                });
                
                if (!$matchedCampaign) {
                    $row["impressions_" . strtolower($period)] = 0;
                    $row["clicks_" . strtolower($period)]      = 0;
                    $row["ad_sales_" . strtolower($period)]    = 0;
                    $row["ad_sold_" . strtolower($period)]     = 0;
                    $row["spend_" . strtolower($period)]       = 0;
                    $row["acos_" . strtolower($period)]        = 0;
                    $row["cpc_" . strtolower($period)]         = 0;
                    continue;
                }

                $adFees = (float) str_replace('USD ', '', $matchedCampaign->cpc_ad_fees_payout_currency ?? 0);
                $sales  = (float) str_replace('USD ', '', $matchedCampaign->cpc_sale_amount_payout_currency ?? 0);
                $clicks = (float) ($matchedCampaign->cpc_clicks ?? 0);
                $spend  = (float) ($matchedCampaign->cpc_cost ?? $adFees);
                $cpc    = $clicks > 0 ? ($spend / $clicks) : 0;
                $acos   = $sales > 0 ? ($adFees / $sales) * 100 : 0;

                if ($adFees > 0 && $sales === 0) {
                    $acos = 100;
                }

                $row["impressions_" . strtolower($period)] = $matchedCampaign->cpc_impressions ?? 0;
                $row["clicks_" . strtolower($period)]      = $matchedCampaign->cpc_clicks ?? 0;
                $row["ad_sales_" . strtolower($period)]    = $sales;
                $row["ad_sold_" . strtolower($period)]     = $matchedCampaign->cpc_attributed_sales ?? 0;
                $row["spend_" . strtolower($period)]       = $adFees;
                $row["acos_" . strtolower($period)]        = $acos;
                $row["cpc_" . strtolower($period)]         = $cpc;
            }

            // Only show rows with campaign
            if ($row['campaignName'] !== '') {
                $result[] = (object) $row;
            }
    
        }

        // Now process additional RUNNING campaigns that are not in ProductMaster
        foreach ($additionalRunningCampaigns as $campaignSku) {
            $sku = strtoupper($campaignSku);
            $shopify = $shopifyData[$campaignSku] ?? null;

            $row = [
                'parent' => '',
                'sku'    => $campaignSku,
                'INV'    => $shopify->inv ?? 0,
                'L30'    => $shopify->quantity ?? 0,
                'NR'     => ''
            ];

            $matchedCampaignL30 = $campaignReports['L30']->first(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });

            $row['campaignName'] = $matchedCampaignL30->campaign_name ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? 0;
            $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? '';

            if (isset($nrValues[$campaignSku])) {
                $raw = $nrValues[$campaignSku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                }
            }

            foreach ($periods as $period) {
                $matchedCampaign = $campaignReports[$period]->first(function ($item) use ($sku) {
                    return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
                });
                
                if (!$matchedCampaign) {
                    $row["impressions_" . strtolower($period)] = 0;
                    $row["clicks_" . strtolower($period)]      = 0;
                    $row["ad_sales_" . strtolower($period)]    = 0;
                    $row["ad_sold_" . strtolower($period)]     = 0;
                    $row["spend_" . strtolower($period)]       = 0;
                    $row["acos_" . strtolower($period)]        = 0;
                    $row["cpc_" . strtolower($period)]         = 0;
                    continue;
                }

                $adFees = (float) str_replace('USD ', '', $matchedCampaign->cpc_ad_fees_payout_currency ?? 0);
                $sales  = (float) str_replace('USD ', '', $matchedCampaign->cpc_sale_amount_payout_currency ?? 0);
                $clicks = (float) ($matchedCampaign->cpc_clicks ?? 0);
                $spend  = (float) ($matchedCampaign->cpc_cost ?? $adFees);
                $cpc    = $clicks > 0 ? ($spend / $clicks) : 0;
                $acos   = $sales > 0 ? ($adFees / $sales) * 100 : 0;

                if ($adFees > 0 && $sales === 0) {
                    $acos = 100;
                }

                $row["impressions_" . strtolower($period)] = $matchedCampaign->cpc_impressions ?? 0;
                $row["clicks_" . strtolower($period)]      = $matchedCampaign->cpc_clicks ?? 0;
                $row["ad_sales_" . strtolower($period)]    = $sales;
                $row["ad_sold_" . strtolower($period)]     = $matchedCampaign->cpc_attributed_sales ?? 0;
                $row["spend_" . strtolower($period)]       = $adFees;
                $row["acos_" . strtolower($period)]        = $acos;
                $row["cpc_" . strtolower($period)]         = $cpc;
            }

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function ebayPriceLessThanTwentyAdsView(){
        return view('campaign.ebay-less-twenty-kw-ads');
    }

    public function ebayPriceLessThanTwentyAdsData()
    {
        $normalizeSku = fn($sku) => strtoupper(trim($sku));

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$sku] ?? null;

            $ebay = $ebayMetricData[$sku] ?? null;

            $matchedCampaignsL7 = $ebayCampaignReportsL7->filter(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });
            $matchedCampaignL7 = $matchedCampaignsL7->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL7->first();

            $matchedCampaignsL1 = $ebayCampaignReportsL1->filter(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });
            $matchedCampaignL1 = $matchedCampaignsL1->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL1->first();

            $matchedCampaignsL30 = $ebayCampaignReportsL30->filter(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });
            $matchedCampaignL30 = $matchedCampaignsL30->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL30->first();

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['e_l30']  = $ebay->ebay_l30 ?? 0;
            $row['price']  = $ebay->ebay_price ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaign_name ?? ($matchedCampaignL1->campaign_name ?? '');
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');

            $adFees   = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $sales    = (float) ($matchedCampaignL30 ? ($matchedCampaignL30->cpc_attributed_sales ?? 0) : 0);

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
            $row['sbid'] = 0;

            $row['NR'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? '';
                }
            }

            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);
            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            
            // Calculate SBID based on budget utilization
            if($ub7 < 70){
                // Under-utilized: increase bid by 5%
                $row['sbid'] = floor($l7_cpc * 1.05 * 100) / 100;
            }else if($ub7 > 90){
                // Over-utilized: decrease bid by 10%
                $row['sbid'] = floor($l1_cpc * 0.90 * 100) / 100;
            }else{
                // Correctly utilized (70-90): keep current bid
                $row['sbid'] = floor($l7_cpc * 100) / 100;
            }
            
            // Apply price-based SBID caps - this runs AFTER the ub7 calculation
            if($row['price'] < 30 && $row['campaignName'] !== ''){
                if($row['price'] <= 10){
                    $row['sbid'] = max(0.10, min($row['sbid'], 0.10));
                }
                elseif($row['price'] > 10 && $row['price'] <= 20){
                    $row['sbid'] = max(0.20, min($row['sbid'], 0.20));
                }
                elseif($row['price'] > 20 && $row['price'] <= 30){
                    $row['sbid'] = max(0.30, min($row['sbid'], 0.30));
                }
            }
            
            // Only show data under price 30, exclude PARENT SKUs, and only show items with campaigns
            if($row['price'] < 30 && stripos($row['sku'], 'PARENT') === false && $row['campaignName'] !== ''){
                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }
}
