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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
        // Only get Parent SKUs
        $productMasters = ProductMaster::whereRaw("UPPER(sku) LIKE 'PARENT %'")
            ->orderBy('parent', 'asc')
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        
        // Get all child SKUs for these parents to calculate sums
        $parentValues = $productMasters->pluck('parent')->filter()->unique()->values()->all();
        $allChildSkus = ProductMaster::whereIn('parent', $parentValues)
            ->whereRaw("UPPER(sku) NOT LIKE 'PARENT %'")
            ->pluck('sku')
            ->toArray();
        
        // Get Shopify data for both parent and child SKUs
        $allSkusForShopify = array_merge($skus, $allChildSkus);
        $shopifyData = ShopifySku::whereIn('sku', $allSkusForShopify)->get()->keyBy('sku');
        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        $ebayMetricData = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Calculate sums for each parent from child SKUs
        $parentSums = [];
        $allChildProductMasters = ProductMaster::whereIn('parent', $parentValues)
            ->whereRaw("UPPER(sku) NOT LIKE 'PARENT %'")
            ->get();
        
        foreach ($allChildProductMasters as $childPm) {
            $childSku = $childPm->sku;
            $parentValue = $childPm->parent ?? null;
            if ($parentValue) {
                if (!isset($parentSums[$parentValue])) {
                    $parentSums[$parentValue] = [
                        'INV' => 0,
                        'L30' => 0,
                    ];
                }
                
                // Add INV and L30 from child SKU
                if (isset($shopifyData[$childSku])) {
                    $parentSums[$parentValue]['INV'] += (int)($shopifyData[$childSku]->inv ?? 0);
                    $parentSums[$parentValue]['L30'] += (int)($shopifyData[$childSku]->quantity ?? 0);
                }
            }
        }

            $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->orderBy('report_range', 'asc')
                ->get();

        $result = [];
        $campaignMap = []; // Group by campaign_id to avoid duplicates

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;
            $ebay = $ebayMetricData[$pm->sku] ?? null;

            $nrValue = '';
            $nrlValue = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nrValue = $raw['NR'] ?? null;
                    $nrlValue = $raw['NRL'] ?? null;
                }
            }

            // Skip if NR is NRA
            

            $matchedReports = $reports->filter(function ($item) use ($sku) {
                $campaignSku = strtoupper(trim($item->campaign_name ?? ''));
                return $campaignSku === $sku;
            });

            // Check if campaign exists
            $hasCampaign = false;
            $matchedCampaignL7 = null;
            $matchedCampaignL1 = null;
            $matchedCampaignL30 = null;
            $campaignId = '';
            $campaignName = '';
            $campaignBudgetAmount = 0;
            $campaignStatus = '';

            if (!$matchedReports->isEmpty()) {
                foreach ($matchedReports as $campaign) {
                    $tempCampaignId = $campaign->campaign_id ?? '';
                    if (!empty($tempCampaignId)) {
                        $hasCampaign = true;
                        $campaignId = $tempCampaignId;
                        $campaignName = $campaign->campaign_name ?? '';
                        $campaignBudgetAmount = $campaign->campaignBudgetAmount ?? 0;
                        $campaignStatus = $campaign->campaignStatus ?? '';

                        $reportRange = $campaign->report_range ?? '';
                        if ($reportRange == 'L7') {
                            $matchedCampaignL7 = $campaign;
                        }
                        if ($reportRange == 'L1') {
                            $matchedCampaignL1 = $campaign;
                        }
                        if ($reportRange == 'L30') {
                            $matchedCampaignL30 = $campaign;
                        }
                    }
                }
            }

            // Use SKU as key if no campaign, otherwise use campaignId
            $mapKey = !empty($campaignId) ? $campaignId : 'SKU_' . $sku;

            // Create or get existing row
            if (!isset($campaignMap[$mapKey])) {
                $ebayL30 = $ebay->ebay_l30 ?? 0;
                
                // Use child SKU sums for parent, or individual value if sum not available
                $parentKey = $parent ?? preg_replace('/^PARENT\s*/i', '', $pm->sku);
                $sums = $parentSums[$parentKey] ?? ['INV' => 0, 'L30' => 0];
                $invValue = $sums['INV'] > 0 ? $sums['INV'] : (($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0);
                $l30Value = $sums['L30'] > 0 ? $sums['L30'] : (($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0);
                
                // Get sbid_m from L30 report if available
                $sbidM = null;
                if ($matchedCampaignL30 && isset($matchedCampaignL30->sbid_m)) {
                    $sbidM = $matchedCampaignL30->sbid_m;
                } elseif ($matchedCampaignL7 && isset($matchedCampaignL7->sbid_m)) {
                    $sbidM = $matchedCampaignL7->sbid_m;
                } elseif ($matchedCampaignL1 && isset($matchedCampaignL1->sbid_m)) {
                    $sbidM = $matchedCampaignL1->sbid_m;
                }
                
                $campaignMap[$mapKey] = [
                    'parent' => $parent,
                    'sku' => $pm->sku,
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'campaignBudgetAmount' => $campaignBudgetAmount,
                    'campaignStatus' => $campaignStatus,
                    'INV' => $invValue,
                    'L30' => $l30Value,
                    'ebay_l30' => $ebayL30,
                    'l7_spend' => 0,
                    'l7_cpc' => 0,
                    'l1_spend' => 0,
                    'l1_cpc' => 0,
                    'acos' => 0,
                    'adFees' => 0,
                    'sales' => 0,
                    'views' => 0,
                    'clicks' => 0,
                    'ad_sold' => 0,
                    'cvr' => 0,
                    'NR' => $nrValue,
                    'NRL' => $nrlValue,
                    'hasCampaign' => $hasCampaign,
                    'sbid_m' => $sbidM,
                ];
            }

            // Add campaign data if exists
            if ($matchedCampaignL7) {
                $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0');
                $cpc = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cost_per_click ?? '0');
                $campaignMap[$mapKey]['l7_spend'] = $adFees;
                $campaignMap[$mapKey]['l7_cpc'] = $cpc;
            }

            if ($matchedCampaignL1) {
                $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0');
                $cpc = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cost_per_click ?? '0');
                $campaignMap[$mapKey]['l1_spend'] = $adFees;
                $campaignMap[$mapKey]['l1_cpc'] = $cpc;
            }

            if ($matchedCampaignL30) {
                $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? '0');
                $views = (int) ($matchedCampaignL30->cpc_clicks ?? 0);
                $attributedSales = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);
                $campaignMap[$mapKey]['adFees'] = $adFees;
                $campaignMap[$mapKey]['sales'] = $sales;
                $campaignMap[$mapKey]['views'] = $views;
                $campaignMap[$mapKey]['clicks'] = $views;
                $campaignMap[$mapKey]['ad_sold'] = $attributedSales;
                
                // Calculate CVR: (attributed_sales / clicks) * 100
                if ($views > 0) {
                    $campaignMap[$mapKey]['cvr'] = round(($attributedSales / $views) * 100, 2);
                } else {
                    $campaignMap[$mapKey]['cvr'] = 0;
                }
                
                if ($sales > 0) {
                    $campaignMap[$mapKey]['acos'] = round(($adFees / $sales) * 100, 2);
                } else if ($adFees > 0 && $sales == 0) {
                    $campaignMap[$mapKey]['acos'] = 100;
                }
            }
        }

        // Process campaigns that don't match ProductMaster SKUs (additional campaigns)
        $allCampaignIds = $reports->pluck('campaign_id')->unique();
        $processedCampaignIds = array_keys($campaignMap);
        
        foreach ($allCampaignIds as $campaignId) {
            if (in_array($campaignId, $processedCampaignIds)) {
                continue; // Already processed
            }

            $campaignReports = $reports->where('campaign_id', $campaignId);
            if ($campaignReports->isEmpty()) {
                continue;
            }

            $firstCampaign = $campaignReports->first();
            $campaignName = $firstCampaign->campaign_name ?? '';
            
            // Try to find matching SKU in ProductMaster for INV/L30 data
            $matchedSku = null;
            foreach ($productMasters as $pm) {
                if (strtoupper(trim($pm->sku)) === strtoupper(trim($campaignName))) {
                    $matchedSku = $pm->sku;
                    break;
                }
            }

            // Get NR value for unmatched campaign
            $nrValue = '';
            if ($matchedSku && isset($nrValues[$matchedSku])) {
                $raw = $nrValues[$matchedSku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nrValue = $raw['NR'] ?? null;
                }
            }

            // Skip if NRA
            

            $matchedEbay = $matchedSku && isset($ebayMetricData[$matchedSku]) ? $ebayMetricData[$matchedSku] : null;
            $ebayL30 = $matchedEbay ? ($matchedEbay->ebay_l30 ?? 0) : 0;
            
            // Get sbid_m from any report
            $sbidM = null;
            $l30Report = $campaignReports->where('report_range', 'L30')->first();
            $l7Report = $campaignReports->where('report_range', 'L7')->first();
            $l1Report = $campaignReports->where('report_range', 'L1')->first();
            
            if ($l30Report && isset($l30Report->sbid_m)) {
                $sbidM = $l30Report->sbid_m;
            } elseif ($l7Report && isset($l7Report->sbid_m)) {
                $sbidM = $l7Report->sbid_m;
            } elseif ($l1Report && isset($l1Report->sbid_m)) {
                $sbidM = $l1Report->sbid_m;
            }
            
            $campaignMap[$campaignId] = [
                'parent' => '',
                'sku' => $campaignName,
                'campaign_id' => $campaignId,
                'campaignName' => $campaignName,
                'campaignBudgetAmount' => $firstCampaign->campaignBudgetAmount ?? 0,
                'campaignStatus' => $firstCampaign->campaignStatus ?? '',
                'INV' => ($matchedSku && isset($shopifyData[$matchedSku])) ? (int)($shopifyData[$matchedSku]->inv ?? 0) : 0,
                'L30' => ($matchedSku && isset($shopifyData[$matchedSku])) ? (int)($shopifyData[$matchedSku]->quantity ?? 0) : 0,
                'ebay_l30' => $ebayL30,
                'l7_spend' => 0,
                'l7_cpc' => 0,
                'l1_spend' => 0,
                'l1_cpc' => 0,
                'acos' => 0,
                'adFees' => 0,
                'sales' => 0,
                'views' => 0,
                'clicks' => 0,
                'ad_sold' => 0,
                'cvr' => 0,
                'NR' => $nrValue,
                'NRL' => '',
                'hasCampaign' => true,
                'sbid_m' => $sbidM,
            ];

            foreach ($campaignReports as $campaign) {
                $reportRange = $campaign->report_range ?? '';
                $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
                $cpc = (float) str_replace(['USD ', ','], '', $campaign->cost_per_click ?? '0');

                if ($reportRange == 'L7') {
                    $campaignMap[$campaignId]['l7_spend'] = $adFees;
                    $campaignMap[$campaignId]['l7_cpc'] = $cpc;
                }

                if ($reportRange == 'L1') {
                    $campaignMap[$campaignId]['l1_spend'] = $adFees;
                    $campaignMap[$campaignId]['l1_cpc'] = $cpc;
                }

                if ($reportRange == 'L30') {
                    $views = (int) ($campaign->cpc_clicks ?? 0);
                    $attributedSales = (int) ($campaign->cpc_attributed_sales ?? 0);
                    $campaignMap[$campaignId]['adFees'] = $adFees;
                    $campaignMap[$campaignId]['sales'] = $sales;
                    $campaignMap[$campaignId]['views'] = $views;
                    $campaignMap[$campaignId]['clicks'] = $views;
                    $campaignMap[$campaignId]['ad_sold'] = $attributedSales;
                    
                    // Calculate CVR: (attributed_sales / clicks) * 100
                    if ($views > 0) {
                        $campaignMap[$campaignId]['cvr'] = round(($attributedSales / $views) * 100, 2);
                    } else {
                        $campaignMap[$campaignId]['cvr'] = 0;
                    }
                    
                    if ($sales > 0) {
                        $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                    } else if ($adFees > 0 && $sales == 0) {
                        $campaignMap[$campaignId]['acos'] = 100;
                    }
                }
            }
        }

        // Fetch last 30 days daily data from ebay_3_priority_reports
        // Daily data is stored with report_range as date (format: Y-m-d)
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');
        
        // Get all unique campaign IDs from campaignMap for efficient filtering
        $allCampaignIds = [];
        foreach ($campaignMap as $row) {
            if (!empty($row['campaign_id'])) {
                $allCampaignIds[] = $row['campaign_id'];
            }
        }
        $allCampaignIds = array_unique($allCampaignIds);
        
        // Calculate totals from ALL campaigns first (for stats cards)
        $dailyDataTotals = DB::table('ebay_3_priority_reports')
            ->select(
                DB::raw('SUM(cpc_clicks) as total_clicks'),
                DB::raw('SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as total_spend'),
                DB::raw('SUM(cpc_attributed_sales) as total_ad_sold')
            )
            ->whereRaw("report_range >= ? AND report_range <= ? AND report_range NOT IN ('L7', 'L1', 'L30')", [$thirtyDaysAgo, $today])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->first();
        
        $totalL30DailyClicks = (int) ($dailyDataTotals->total_clicks ?? 0);
        $totalL30DailySpend = (float) ($dailyDataTotals->total_spend ?? 0);
        $totalL30DailyAdSold = (int) ($dailyDataTotals->total_ad_sold ?? 0);
        
        // Fetch daily data for campaigns in campaignMap only for row-level data
        $dailyDataLast30Days = collect();
        if (!empty($allCampaignIds)) {
            $dailyDataLast30Days = DB::table('ebay_3_priority_reports')
                ->select(
                    'campaign_id',
                    'campaign_name',
                    DB::raw('SUM(cpc_clicks) as total_clicks'),
                    DB::raw('SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as total_spend'),
                    DB::raw('SUM(cpc_attributed_sales) as total_ad_sold')
                )
                ->whereRaw("report_range >= ? AND report_range <= ? AND report_range NOT IN ('L7', 'L1', 'L30')", [$thirtyDaysAgo, $today])
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->whereIn('campaign_id', $allCampaignIds)
                ->groupBy('campaign_id', 'campaign_name')
                ->get()
                ->keyBy('campaign_id');
        }
        
        // Add last 30 days data to each campaign in campaignMap
        foreach ($campaignMap as $key => $row) {
            $campaignId = $row['campaign_id'] ?? '';
            if (!empty($campaignId) && $dailyDataLast30Days->has($campaignId)) {
                $dailyData = $dailyDataLast30Days[$campaignId];
                $campaignMap[$key]['l30_daily_clicks'] = (int) ($dailyData->total_clicks ?? 0);
                $campaignMap[$key]['l30_daily_spend'] = (float) ($dailyData->total_spend ?? 0);
                $campaignMap[$key]['l30_daily_ad_sold'] = (int) ($dailyData->total_ad_sold ?? 0);
            } else {
                $campaignMap[$key]['l30_daily_clicks'] = 0;
                $campaignMap[$key]['l30_daily_spend'] = 0;
                $campaignMap[$key]['l30_daily_ad_sold'] = 0;
            }
        }

        // Fetch last_sbid from day-before-yesterday's date records
        // This ensures last_sbid shows the PREVIOUS day's calculated SBID, not the current day's
        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $lastSbidMap = [];
        $sbidMMap = [];
        $apprSbidMap = [];
        
        $lastSbidReports = Ebay3PriorityReport::where('report_range', $dayBeforeYesterday)
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

        // Fetch sbid_m from yesterday's records first, then L1 as fallback
        $sbidMReports = Ebay3PriorityReport::where(function($q) use ($yesterday) {
                $q->where('report_range', $yesterday)
                  ->orWhere('report_range', 'L1');
            })
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->sortBy(function($report) use ($yesterday) {
                // Prioritize yesterday's records over L1
                return $report->report_range === $yesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');
        
        foreach ($sbidMReports as $campaignId => $reports) {
            // Get the first report (prioritized by yesterday)
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->sbid_m)) {
                $sbidMMap[$report->campaign_id] = $report->sbid_m;
            }
        }

        // Fetch apprSbid from yesterday's records first, then L1 as fallback
        $apprSbidReports = Ebay3PriorityReport::where(function($q) use ($yesterday) {
                $q->where('report_range', $yesterday)
                  ->orWhere('report_range', 'L1');
            })
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->sortBy(function($report) use ($yesterday) {
                // Prioritize yesterday's records over L1
                return $report->report_range === $yesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');
        
        foreach ($apprSbidReports as $campaignId => $reports) {
            // Get the first report (prioritized by yesterday)
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->apprSbid)) {
                $apprSbidMap[$report->campaign_id] = $report->apprSbid;
            }
        }

        // Add last_sbid, sbid_m, and apprSbid to campaignMap
        foreach ($campaignMap as $key => $row) {
            $campaignId = $row['campaign_id'] ?? '';
            if (!empty($campaignId)) {
                if (isset($lastSbidMap[$campaignId])) {
                    $campaignMap[$key]['last_sbid'] = $lastSbidMap[$campaignId];
                } else {
                    $campaignMap[$key]['last_sbid'] = '';
                }
                
                if (isset($sbidMMap[$campaignId])) {
                    $campaignMap[$key]['sbid_m'] = $sbidMMap[$campaignId];
                } else {
                    $campaignMap[$key]['sbid_m'] = '';
                }
                
                if (isset($apprSbidMap[$campaignId])) {
                    $campaignMap[$key]['apprSbid'] = $apprSbidMap[$campaignId];
                } else {
                    $campaignMap[$key]['apprSbid'] = '';
                }
            } else {
                $campaignMap[$key]['last_sbid'] = '';
                $campaignMap[$key]['sbid_m'] = '';
                $campaignMap[$key]['apprSbid'] = '';
            }
        }

        // Convert map to array
        foreach ($campaignMap as $campaignId => $row) {
            $result[] = (object) $row;
        }

        // Calculate total ACOS from ALL campaigns (L30 report_range data)
        $allL30Campaigns = Ebay3PriorityReport::where('report_range', 'L30')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $totalSpendAll = 0;
        $totalSalesAll = 0;
        $totalClicksAll = 0;
        $totalAdSoldAll = 0;

        foreach ($allL30Campaigns as $campaign) {
            $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
            $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
            $clicks = (int) ($campaign->cpc_clicks ?? 0);
            $adSold = (int) ($campaign->cpc_attributed_sales ?? 0);
            $totalSpendAll += $adFees;
            $totalSalesAll += $sales;
            $totalClicksAll += $clicks;
            $totalAdSoldAll += $adSold;
        }

        $totalACOSAll = $totalSalesAll > 0 ? ($totalSpendAll / $totalSalesAll) * 100 : 0;

        // Calculate average ACOS and CVR from campaignMap
        $totalAcos = 0;
        $totalCvr = 0;
        $acosCount = 0;
        $cvrCount = 0;
        
        foreach ($campaignMap as $row) {
            if (isset($row['acos']) && $row['acos'] !== null) {
                $totalAcos += (float) $row['acos'];
                $acosCount++;
            }
            // Only count CVR for campaigns with clicks > 0 (CVR is meaningful only when clicks exist)
            if (isset($row['cvr']) && $row['cvr'] !== null && isset($row['clicks']) && $row['clicks'] > 0) {
                $totalCvr += (float) $row['cvr'];
                $cvrCount++;
            }
        }
        
        $avgAcos = $acosCount > 0 ? round($totalAcos / $acosCount, 2) : 0;
        $avgCvr = $cvrCount > 0 ? round($totalCvr / $cvrCount, 2) : 0;

        // Calculate total SKU count - only Parent SKUs (excluding deleted)
        $totalSkuCount = ProductMaster::whereNull('deleted_at')
            ->whereRaw("UPPER(sku) LIKE 'PARENT %'")
            ->count();

        // Calculate zero INV count - count Parent SKUs with INV <= 0 (excluding deleted and NRA)
        // Use same logic as total SKU count - only non-deleted Parent SKUs
        $productMastersForCount = ProductMaster::whereNull('deleted_at')
            ->whereRaw("UPPER(sku) LIKE 'PARENT %'")
            ->get();
        $zeroInvCount = 0;
        $processedZeroInvSkus = [];
        foreach ($productMastersForCount as $pm) {
            $sku = strtoupper($pm->sku);
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
            // Skip NRA SKUs (same logic as in data processing)
            
            $shopify = $shopifyData[$pm->sku] ?? null;
            $inv = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
            if ($inv <= 0 && !in_array($sku, $processedZeroInvSkus)) {
                $processedZeroInvSkus[] = $sku;
                $zeroInvCount++;
            }
        }

        // Calculate eBay SKU count - count all unique Parent SKUs from Ebay3Metric table
        $ebaySkuCount = Ebay3Metric::select('sku')
            ->distinct()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereRaw("UPPER(sku) LIKE 'PARENT %'")
            ->count();

        // Calculate total campaign count - count all distinct campaign names (without filtering by Parent SKUs)
        // This should match: SELECT COUNT(DISTINCT campaign_name) FROM ebay_3_priority_reports
        $totalCampaignCount = Ebay3PriorityReport::where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->distinct()
            ->count('campaign_name');

        // Save calculated SBID to last_sbid column (same as main eBay controller)
        // This is saved for tracking: to compare calculated SBID with what was actually updated on eBay
        // When cron runs and new data comes, page will refresh, so we need to save SBID to database
        try {
            $this->calculateAndSaveSBID($result);
        } catch (\Exception $e) {
            Log::error("Error calculating and saving SBID for eBay3: " . $e->getMessage());
            // Don't fail the entire request if SBID calculation fails
        }

        return response()->json([
            'message' => 'fetched successfully',
            'data' => $result,
            'total_l30_spend' => round($totalSpendAll, 2),
            'total_l30_sales' => round($totalSalesAll, 2),
            'total_l30_clicks' => $totalClicksAll,
            'total_l30_ad_sold' => $totalAdSoldAll,
            'total_acos' => round($totalACOSAll, 2),
            'avg_acos' => $avgAcos,
            'avg_cvr' => $avgCvr,
            'total_sku_count' => $totalSkuCount,
            'ebay_sku_count' => $ebaySkuCount,
            'total_campaign_count' => $totalCampaignCount,
            'zero_inv_count' => $zeroInvCount,
            'status' => 200,
        ]);
    }

    public function updateEbay3NrData(Request $request)
    {
        $sku   = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        $ebayDataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);

        // Decode existing value if it's a JSON string
        $jsonData = is_array($ebayDataView->value) 
            ? $ebayDataView->value 
            : (json_decode($ebayDataView->value ?? '{}', true) ?: []);

        // Save field value
        $jsonData[$field] = $value;

        // If NRL is set to "NRL" or "NR", automatically set NRA to "NRA" (always, regardless of current value)
        // Note: Dropdown sends 'NR' but database stores 'NRL', so handle both
        if ($field === 'NRL' && ($value === 'NRL' || $value === 'NR')) {
            // Always set NRA to "NRA" when NRL is "NRL" or "NR"
            $jsonData['NR'] = 'NRA';
            // Store as 'NRL' in database (normalize 'NR' to 'NRL')
            $jsonData['NRL'] = 'NRL';
        }

        $ebayDataView->value = $jsonData;
        $ebayDataView->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Field updated successfully",
            'updated_json' => $jsonData
        ]);
    }

    public function updateEbay3SbidM(Request $request)
    {
        try {
            $campaignId = $request->input('campaign_id');
            $sbidM = $request->input('sbid_m');

            if (!$campaignId || !$sbidM) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Campaign ID and SBID M are required'
                ], 400);
            }

            $sbidM = floatval($sbidM);
            if ($sbidM <= 0) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M must be greater than 0'
                ], 400);
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));

            // Update eBay3 campaigns - try yesterday first, then L1, L7, L30 as fallback
            // Clear apprSbid when sbid_m is updated so new bid can be pushed
            // First try yesterday's date
            $updated = DB::table('ebay_3_priority_reports')
                ->where('campaign_id', $campaignId)
                ->where('report_range', $yesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->update([
                    'sbid_m' => (string)$sbidM,
                    'apprSbid' => '' // Clear apprSbid to allow new bid push
                ]);
            
            // If no record found for yesterday, try L1
            if ($updated === 0) {
                $updated = DB::table('ebay_3_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->where('report_range', 'L1')
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'sbid_m' => (string)$sbidM,
                        'apprSbid' => '' // Clear apprSbid to allow new bid push
                    ]);
            }

            // Also update L7 and L30 records for consistency (don't fail if they don't exist)
            if ($updated > 0) {
                DB::table('ebay_3_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->whereIn('report_range', ['L7', 'L30'])
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'sbid_m' => (string)$sbidM,
                        'apprSbid' => '' // Clear apprSbid to allow new bid push
                    ]);
                
                return response()->json([
                    'status' => 200,
                    'message' => 'SBID M saved successfully',
                    'sbid_m' => $sbidM
                ]);
            } else {
                // Log for debugging
                Log::error('SBID M save failed', [
                    'campaign_id' => $campaignId,
                    'yesterday' => $yesterday,
                    'sbid_m' => $sbidM
                ]);
                
                return response()->json([
                    'status' => 404,
                    'message' => 'Campaign not found. Please ensure the campaign exists for yesterday\'s date or L1.'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error saving eBay3 SBID M: ' . $e->getMessage(), [
                'campaign_id' => $request->input('campaign_id'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Error saving SBID M: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdateEbay3SbidM(Request $request)
    {
        try {
            $campaignIds = $request->input('campaign_ids', []);
            $sbidM = $request->input('sbid_m');

            // Filter out invalid campaign IDs
            $campaignIds = array_filter($campaignIds, function($id) {
                return !empty($id) && $id !== null && $id !== '';
            });
            $campaignIds = array_values($campaignIds); // Re-index array

            if (empty($campaignIds)) {
                Log::warning('bulkUpdateEbay3SbidM: No valid campaign IDs provided after filtering.', ['input_campaign_ids' => $request->input('campaign_ids')]);
                return response()->json([
                    'status' => 400,
                    'message' => 'No valid campaign IDs provided'
                ], 400);
            }

            if (!$sbidM) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M is required'
                ], 400);
            }

            $sbidM = floatval($sbidM);
            if ($sbidM <= 0) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M must be greater than 0'
                ], 400);
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $sbidMString = (string)$sbidM;

            Log::info('bulkUpdateEbay3SbidM: Attempting to update ' . count($campaignIds) . ' campaigns.', ['campaign_ids' => $campaignIds, 'sbid_m' => $sbidM]);

            // Define common conditions for the queries
            $commonConditions = function ($query) {
                $query->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%');
            };

            // 1. Update for yesterday's date (most common case)
            DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->where('report_range', $yesterday)
                ->where($commonConditions)
                ->update([
                    'sbid_m' => $sbidMString,
                    'apprSbid' => '' // Clear apprSbid to allow new bid push
                ]);

            // 2. Get campaign IDs that were successfully updated for yesterday
            $updatedYesterdayCampaignIds = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->where('report_range', $yesterday)
                ->where($commonConditions)
                ->where('sbid_m', $sbidMString) // Verify it was updated with the new value
                ->pluck('campaign_id')
                ->toArray();

            $remainingCampaignIdsForL1 = array_diff($campaignIds, $updatedYesterdayCampaignIds);

            // 3. Update for L1 (fallback for campaigns not found in yesterday)
            if (!empty($remainingCampaignIdsForL1)) {
                DB::table('ebay_3_priority_reports')
                    ->whereIn('campaign_id', $remainingCampaignIdsForL1)
                    ->where('report_range', 'L1')
                    ->where($commonConditions)
                    ->update([
                        'sbid_m' => $sbidMString,
                        'apprSbid' => '' // Clear apprSbid to allow new bid push
                    ]);
            }

            // 4. Update L7 and L30 records for all original selected campaigns (for consistency)
            DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->whereIn('report_range', ['L7', 'L30'])
                ->where($commonConditions)
                ->update([
                    'sbid_m' => $sbidMString,
                    'apprSbid' => '' // Clear apprSbid to allow new bid push
                ]);

            // Count total updated campaigns (yesterday + L1)
            $totalUpdatedCount = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->whereIn('report_range', [$yesterday, 'L1'])
                ->where($commonConditions)
                ->where('sbid_m', $sbidMString)
                ->distinct('campaign_id')
                ->count('campaign_id');

            Log::info('bulkUpdateEbay3SbidM: Successfully updated ' . $totalUpdatedCount . ' out of ' . count($campaignIds) . ' requested campaigns.', ['campaign_ids' => $campaignIds, 'updated_count' => $totalUpdatedCount]);

            return response()->json([
                'status' => 200,
                'message' => "SBID M saved successfully for {$totalUpdatedCount} campaign(s)",
                'updated_count' => $totalUpdatedCount,
                'total_count' => count($campaignIds)
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay3 SBID M bulk: ' . $e->getMessage(), [
                'campaign_ids' => $request->input('campaign_ids'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error saving SBID M: ' . $e->getMessage()
            ], 500);
        }
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

    /**
     * Get eBay3 access token
     */
    private function getEbay3AccessToken()
    {
        if (Cache::has('ebay3_access_token')) {
            return Cache::get('ebay3_access_token');
        }

        $clientId = env('EBAY_3_APP_ID');
        $clientSecret = env('EBAY_3_CERT_ID');

        $scope = implode(' ', [
            'https://api.ebay.com/oauth/api_scope',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
            'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.stores',
            'https://api.ebay.com/oauth/api_scope/sell.finances',
            'https://api.ebay.com/oauth/api_scope/sell.marketing',
            'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly'
        ]);

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => env('EBAY_3_REFRESH_TOKEN'),
                    'scope' => $scope,
                ]);

            if ($response->successful()) {
                $accessToken = $response->json()['access_token'];
                $expiresIn = $response->json()['expires_in'] ?? 7200;
                
                Cache::put('ebay3_access_token', $accessToken, $expiresIn - 60);
                Log::info('eBay3 token', ['response' => 'Token generated!']);
                
                return $accessToken;
            }

            Log::error('eBay3 token refresh error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            Log::error('eBay3 token refresh exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get ad groups for a campaign
     */
    private function getAdGroups($campaignId)
    {
        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            Log::error("No access token available for fetching ad groups");
            return ['adGroups' => []];
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(120)
                ->retry(3, 5000)
                ->get("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad_group");

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Successfully fetched ad groups for campaign {$campaignId}", [
                    'ad_groups_count' => count($data['adGroups'] ?? [])
                ]);
                return $data;
            }

            // If token expired, try refreshing
            if ($response->status() === 401) {
                Log::info("Token expired, refreshing for campaign {$campaignId}");
                Cache::forget('ebay3_access_token');
                $accessToken = $this->getEbay3AccessToken();
                if ($accessToken) {
                    $response = Http::withToken($accessToken)
                        ->timeout(120)
                        ->retry(3, 5000)
                        ->get("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad_group");
                    if ($response->successful()) {
                        $data = $response->json();
                        Log::info("Successfully fetched ad groups after token refresh for campaign {$campaignId}");
                        return $data;
                    }
                }
            }

            Log::error("Failed to fetch ad groups for campaign {$campaignId}", [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error("Exception fetching ad groups for campaign {$campaignId}: " . $e->getMessage());
        }

        return ['adGroups' => []];
    }

    /**
     * Get keywords for an ad group
     */
    private function getKeywords($campaignId, $adGroupId)
    {
        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            return [];
        }

        $keywords = [];
        $offset = 0;
        $limit = 200;

        do {
            try {
                $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/keyword?ad_group_ids={$adGroupId}&keyword_status=ACTIVE&limit={$limit}&offset={$offset}";
                
                $response = Http::withToken($accessToken)
                    ->timeout(120)
                    ->retry(3, 5000)
                    ->get($endpoint);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['keywords']) && is_array($data['keywords'])) {
                        foreach ($data['keywords'] as $k) {
                            $keywords[] = $k['keywordId'] ?? $k['id'] ?? null;
                        }
                    }

                    $total = $data['total'] ?? count($keywords);
                    $offset += $limit;
                    
                    Log::debug("Fetched keywords for campaign {$campaignId}, ad group {$adGroupId}", [
                        'offset' => $offset - $limit,
                        'count' => count($data['keywords'] ?? []),
                        'total' => $total
                    ]);
                } else {
                    // If token expired, try refreshing
                    if ($response->status() === 401) {
                        Cache::forget('ebay3_access_token');
                        $accessToken = $this->getEbay3AccessToken();
                        if (!$accessToken) {
                            break;
                        }
                        continue;
                    }
                    break;
                }
            } catch (\Exception $e) {
                Log::error("Exception fetching keywords for campaign {$campaignId}, ad group {$adGroupId}: " . $e->getMessage());
                break;
            }
        } while ($offset < ($data['total'] ?? 0));

        return array_filter($keywords);
    }

    /**
     * Update keyword bids for eBay3 campaigns (for automated command)
     */
    public function updateAutoKeywordsBidDynamic(array $campaignIds, array $newBids)
    {
        // Set longer timeout for API operations (10 minutes per batch)
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            return response()->json([
                'message' => 'Failed to retrieve eBay3 access token',
                'status' => 500
            ]);
        }

        $results = [];
        $hasError = false;

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            Log::info("Processing campaign {$campaignId} with bid {$newBid}");

            $adGroups = $this->getAdGroups($campaignId);
            if (!isset($adGroups['adGroups']) || empty($adGroups['adGroups'])) {
                Log::warning("No ad groups found for campaign {$campaignId}");
                $results[] = [
                    "campaign_id" => $campaignId,
                    "status"      => "error",
                    "message"     => "No ad groups found",
                ];
                continue;
            }

            Log::info("Found " . count($adGroups['adGroups']) . " ad groups for campaign {$campaignId}");

            foreach ($adGroups['adGroups'] as $adGroup) {
                $adGroupId = $adGroup['adGroupId'];
                $keywords = $this->getKeywords($campaignId, $adGroupId);

                if (empty($keywords)) {
                    Log::warning("No keywords found for campaign {$campaignId}, ad group {$adGroupId}");
                    continue;
                }

                Log::info("Found " . count($keywords) . " keywords for campaign {$campaignId}, ad group {$adGroupId}");

                foreach (array_chunk($keywords, 100) as $keywordChunk) {
                    $payload = [
                        "requests" => []
                    ];

                    foreach ($keywordChunk as $keywordId) {
                        $payload["requests"][] = [
                            "bid" => [
                                "currency" => "USD",
                                "value"    => $newBid,
                            ],
                            "keywordId" => $keywordId,
                            "keywordStatus" => "ACTIVE"
                        ];
                    }

                    $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_keyword";

                    Log::info("Updating " . count($keywordChunk) . " keywords for campaign {$campaignId}, ad group {$adGroupId} with bid {$newBid}");

                    try {
                        $response = Http::timeout(120) // 2 minute timeout per request
                            ->withHeaders([
                                'Authorization' => "Bearer {$accessToken}",
                                'Content-Type'  => 'application/json',
                            ])->post($endpoint, $payload);

                        Log::info("API Response for campaign {$campaignId}: Status " . $response->status(), [
                            'response_body' => $response->body()
                        ]);

                        if ($response->successful()) {
                            $respData = $response->json();
                            
                            // Log the response structure
                            Log::info("Successful response structure", ['response' => $respData]);
                            
                            // Handle different response structures
                            if (isset($respData['responses']) && is_array($respData['responses'])) {
                                // Response has individual keyword responses
                                foreach ($respData['responses'] as $r) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $r['keywordId'] ?? null,
                                        "status"      => $r['status'] ?? "success",
                                        "message"     => $r['message'] ?? "Updated",
                                    ];
                                }
                            } elseif (isset($respData['status']) && $respData['status'] === 'SUCCESS') {
                                // Response indicates success but no individual responses
                                // This means all keywords were updated successfully
                                foreach ($keywordChunk as $keywordId) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $keywordId,
                                        "status"      => "success",
                                        "message"     => "Updated",
                                    ];
                                }
                                Log::info("Bulk update successful for " . count($keywordChunk) . " keywords");
                            } else {
                                // If response structure is different, assume success and log it
                                Log::warning("Unexpected response structure, assuming success", ['response' => $respData]);
                                foreach ($keywordChunk as $keywordId) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $keywordId,
                                        "status"      => "success",
                                        "message"     => "Bulk update completed",
                                    ];
                                }
                            }
                        } else {
                            // If token expired, try refreshing
                            if ($response->status() === 401) {
                                Cache::forget('ebay3_access_token');
                                $accessToken = $this->getEbay3AccessToken();
                                if ($accessToken) {
                                    $response = Http::withHeaders([
                                        'Authorization' => "Bearer {$accessToken}",
                                        'Content-Type'  => 'application/json',
                                    ])->post($endpoint, $payload);
                                    
                                    if ($response->successful()) {
                                        $respData = $response->json();
                                        foreach ($respData['responses'] ?? [] as $r) {
                                            $results[] = [
                                                "campaign_id" => $campaignId,
                                                "ad_group_id" => $adGroupId,
                                                "keyword_id"  => $r['keywordId'] ?? null,
                                                "status"      => $r['status'] ?? "unknown",
                                                "message"     => $r['message'] ?? "Updated",
                                            ];
                                        }
                                        continue;
                                    }
                                }
                            }

                            $hasError = true;
                            $errorBody = $response->json();
                            $errorMessage = "Unknown error";
                            $statusCode = $response->status();
                            
                            if (isset($errorBody['errors']) && is_array($errorBody['errors']) && !empty($errorBody['errors'])) {
                                $errorMessage = $errorBody['errors'][0]['message'] ?? $errorBody['errors'][0]['longMessage'] ?? "Unknown error";
                            } elseif (isset($errorBody['message'])) {
                                $errorMessage = $errorBody['message'];
                            }
                            
                            // Special handling for premium ads campaigns (409 error)
                            if ($statusCode === 409 && str_contains(strtolower($errorMessage), 'premium ads')) {
                                $errorMessage = "Campaign uses Premium Ads (beta feature). Bid updates are not available for this campaign type.";
                                Log::warning("Premium ads campaign detected - bid updates not supported", [
                                    'campaign_id' => $campaignId,
                                    'error' => $errorMessage
                                ]);
                            }
                            
                            Log::error("Failed to update keywords for campaign {$campaignId}", [
                                'status' => $statusCode,
                                'error' => $errorMessage,
                                'response' => $errorBody
                            ]);
                            
                            $results[] = [
                                "campaign_id" => $campaignId,
                                "ad_group_id" => $adGroupId,
                                "status"      => "error",
                                "message"     => $errorMessage,
                                "http_code"   => $statusCode,
                            ];
                        }

                    } catch (\Exception $e) {
                        $hasError = true;
                        Log::error("Exception updating keywords for campaign {$campaignId}", [
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $results[] = [
                            "campaign_id" => $campaignId,
                            "ad_group_id" => $adGroupId,
                            "status"      => "error",
                            "message"     => $e->getMessage(),
                        ];
                    }
                }
            }
            
            // Track successful campaigns for apprSbid update
            if ($campaignSuccess && $newBid > 0) {
                $successfulCampaigns[$campaignId] = $newBid;
            }
        }

        // Save apprSbid for successfully updated campaigns
        if (!empty($successfulCampaigns)) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            foreach ($successfulCampaigns as $campaignId => $bidValue) {
                // Try to update yesterday's records first
                $updated = DB::table('ebay_3_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->where('report_range', $yesterday)
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'apprSbid' => (string)$bidValue
                    ]);
                
                // If no record found for yesterday, try L1
                if ($updated === 0) {
                    DB::table('ebay_3_priority_reports')
                        ->where('campaign_id', $campaignId)
                        ->where('report_range', 'L1')
                        ->where('campaignStatus', 'RUNNING')
                        ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                        ->where('campaign_name', 'NOT LIKE', 'General - %')
                        ->where('campaign_name', 'NOT LIKE', 'Default%')
                        ->update([
                            'apprSbid' => (string)$bidValue
                        ]);
                }
                
                // Also update L7 and L30 records for consistency
                DB::table('ebay_3_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->whereIn('report_range', ['L7', 'L30'])
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'apprSbid' => (string)$bidValue
                    ]);
            }
        }

        return response()->json([
            "status" => $hasError ? 207 : 200,
            "message" => $hasError ? "Some keywords failed to update" : "All keyword bids updated successfully",
            "data" => $results
        ]);
    }

    /**
     * Update keyword bids for eBay3 campaigns (for frontend requests)
     */
    public function updateKeywordsBidDynamic(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');
        
        $campaignIds = $request->input('campaign_ids', []);
        $newBids = $request->input('bids', []);

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            return response()->json([
                'message' => 'Failed to retrieve eBay3 access token',
                'status' => 500
            ]);
        }

        $results = [];
        $hasError = false;

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            Log::info("Processing campaign {$campaignId} with bid {$newBid}");

            $adGroups = $this->getAdGroups($campaignId);
            if (!isset($adGroups['adGroups']) || empty($adGroups['adGroups'])) {
                Log::warning("No ad groups found for campaign {$campaignId}");
                $results[] = [
                    "campaign_id" => $campaignId,
                    "status"      => "error",
                    "message"     => "No ad groups found",
                ];
                continue;
            }

            Log::info("Found " . count($adGroups['adGroups']) . " ad groups for campaign {$campaignId}");

            foreach ($adGroups['adGroups'] as $adGroup) {
                $adGroupId = $adGroup['adGroupId'];
                $keywords = $this->getKeywords($campaignId, $adGroupId);

                if (empty($keywords)) {
                    Log::warning("No keywords found for campaign {$campaignId}, ad group {$adGroupId}");
                    continue;
                }

                Log::info("Found " . count($keywords) . " keywords for campaign {$campaignId}, ad group {$adGroupId}");

                foreach (array_chunk($keywords, 100) as $keywordChunk) {
                    $payload = [
                        "requests" => []
                    ];

                    foreach ($keywordChunk as $keywordId) {
                        $payload["requests"][] = [
                            "bid" => [
                                "currency" => "USD",
                                "value"    => $newBid,
                            ],
                            "keywordId" => $keywordId,
                            "keywordStatus" => "ACTIVE"
                        ];
                    }

                    $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_keyword";

                    Log::info("Updating " . count($keywordChunk) . " keywords for campaign {$campaignId}, ad group {$adGroupId} with bid {$newBid}");

                    try {
                        $response = Http::timeout(120) // 2 minute timeout per request
                            ->withHeaders([
                                'Authorization' => "Bearer {$accessToken}",
                                'Content-Type'  => 'application/json',
                            ])->post($endpoint, $payload);

                        Log::info("API Response for campaign {$campaignId}: Status " . $response->status(), [
                            'response_body' => $response->body()
                        ]);

                        if ($response->successful()) {
                            $campaignSuccess = true;
                            $respData = $response->json();
                            
                            // Log the response structure
                            Log::info("Successful response structure", ['response' => $respData]);
                            
                            // Handle different response structures
                            if (isset($respData['responses']) && is_array($respData['responses'])) {
                                // Response has individual keyword responses
                                foreach ($respData['responses'] as $r) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $r['keywordId'] ?? null,
                                        "status"      => $r['status'] ?? "success",
                                        "message"     => $r['message'] ?? "Updated",
                                    ];
                                }
                            } elseif (isset($respData['status']) && $respData['status'] === 'SUCCESS') {
                                // Response indicates success but no individual responses
                                // This means all keywords were updated successfully
                                foreach ($keywordChunk as $keywordId) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $keywordId,
                                        "status"      => "success",
                                        "message"     => "Updated",
                                    ];
                                }
                                Log::info("Bulk update successful for " . count($keywordChunk) . " keywords");
                            } else {
                                // If response structure is different, assume success and log it
                                Log::warning("Unexpected response structure, assuming success", ['response' => $respData]);
                                foreach ($keywordChunk as $keywordId) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $keywordId,
                                        "status"      => "success",
                                        "message"     => "Bulk update completed",
                                    ];
                                }
                            }
                        } else {
                            // If token expired, try refreshing
                            if ($response->status() === 401) {
                                Cache::forget('ebay3_access_token');
                                $accessToken = $this->getEbay3AccessToken();
                                if ($accessToken) {
                                    $response = Http::withHeaders([
                                        'Authorization' => "Bearer {$accessToken}",
                                        'Content-Type'  => 'application/json',
                                    ])->post($endpoint, $payload);
                                    
                                    if ($response->successful()) {
                                        $campaignSuccess = true;
                                        $respData = $response->json();
                                        if (isset($respData['responses']) && is_array($respData['responses'])) {
                                            foreach ($respData['responses'] as $r) {
                                                $results[] = [
                                                    "campaign_id" => $campaignId,
                                                    "ad_group_id" => $adGroupId,
                                                    "keyword_id"  => $r['keywordId'] ?? null,
                                                    "status"      => $r['status'] ?? "success",
                                                    "message"     => $r['message'] ?? "Updated",
                                                ];
                                            }
                                        } else {
                                            foreach ($keywordChunk as $keywordId) {
                                                $results[] = [
                                                    "campaign_id" => $campaignId,
                                                    "ad_group_id" => $adGroupId,
                                                    "keyword_id"  => $keywordId,
                                                    "status"      => "success",
                                                    "message"     => "Updated",
                                                ];
                                            }
                                        }
                                        continue;
                                    }
                                }
                            }

                            $hasError = true;
                            $errorBody = $response->json();
                            $errorMessage = "Unknown error";
                            $statusCode = $response->status();
                            
                            if (isset($errorBody['errors']) && is_array($errorBody['errors']) && !empty($errorBody['errors'])) {
                                $errorMessage = $errorBody['errors'][0]['message'] ?? $errorBody['errors'][0]['longMessage'] ?? "Unknown error";
                            } elseif (isset($errorBody['message'])) {
                                $errorMessage = $errorBody['message'];
                            }
                            
                            // Special handling for premium ads campaigns (409 error)
                            if ($statusCode === 409 && str_contains(strtolower($errorMessage), 'premium ads')) {
                                $errorMessage = "Campaign uses Premium Ads (beta feature). Bid updates are not available for this campaign type.";
                                Log::warning("Premium ads campaign detected - bid updates not supported", [
                                    'campaign_id' => $campaignId,
                                    'error' => $errorMessage
                                ]);
                            }
                            
                            Log::error("Failed to update keywords for campaign {$campaignId}", [
                                'status' => $statusCode,
                                'error' => $errorMessage,
                                'response' => $errorBody
                            ]);
                            
                            $results[] = [
                                "campaign_id" => $campaignId,
                                "ad_group_id" => $adGroupId,
                                "status"      => "error",
                                "message"     => $errorMessage,
                                "http_code"   => $statusCode,
                            ];
                        }

                    } catch (\Exception $e) {
                        $hasError = true;
                        Log::error("Exception updating keywords for campaign {$campaignId}", [
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $results[] = [
                            "campaign_id" => $campaignId,
                            "ad_group_id" => $adGroupId,
                            "status"      => "error",
                            "message"     => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        return response()->json([
            "status" => $hasError ? 207 : 200,
            "message" => $hasError ? "Some keywords failed to update" : "All keyword bids updated successfully",
            "data" => $results
        ]);
    }

    /**
     * Test method to check bid update for a specific campaign
     */
    public function testBidUpdate($campaignId, $bid)
    {
        Log::info("Testing bid update for campaign {$campaignId} with bid {$bid}");
        
        // Get access token
        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve eBay3 access token'
            ];
        }
        
        // Get ad groups
        $adGroups = $this->getAdGroups($campaignId);
        Log::info("Ad groups for campaign {$campaignId}:", ['ad_groups' => $adGroups]);
        
        if (!isset($adGroups['adGroups']) || empty($adGroups['adGroups'])) {
            return [
                'success' => false,
                'message' => 'No ad groups found for this campaign',
                'campaign_id' => $campaignId
            ];
        }
        
        $allKeywords = [];
        $adGroupDetails = [];
        
        // Get keywords for each ad group
        foreach ($adGroups['adGroups'] as $adGroup) {
            $adGroupId = $adGroup['adGroupId'];
            $keywords = $this->getKeywords($campaignId, $adGroupId);
            
            $adGroupDetails[] = [
                'ad_group_id' => $adGroupId,
                'ad_group_name' => $adGroup['adGroupName'] ?? 'N/A',
                'keyword_count' => count($keywords)
            ];
            
            $allKeywords = array_merge($allKeywords, $keywords);
        }
        
        Log::info("Total keywords found: " . count($allKeywords));
        
        if (empty($allKeywords)) {
            return [
                'success' => false,
                'message' => 'No keywords found for this campaign',
                'campaign_id' => $campaignId,
                'ad_groups' => $adGroupDetails
            ];
        }
        
        // Test update with first 5 keywords only (for testing)
        $testKeywords = array_slice($allKeywords, 0, 5);
        
        $payload = [
            "requests" => []
        ];
        
        foreach ($testKeywords as $keywordId) {
            $payload["requests"][] = [
                "bid" => [
                    "currency" => "USD",
                    "value"    => floatval($bid),
                ],
                "keywordId" => $keywordId,
                "keywordStatus" => "ACTIVE"
            ];
        }
        
        $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_keyword";
        
        Log::info("Testing bid update with payload:", ['endpoint' => $endpoint, 'payload' => $payload]);
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ])->post($endpoint, $payload);
            
            $responseData = $response->json();
            $statusCode = $response->status();
            
            Log::info("API Response:", [
                'status_code' => $statusCode,
                'response' => $responseData
            ]);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Bid update test successful',
                    'campaign_id' => $campaignId,
                    'bid' => $bid,
                    'keywords_tested' => count($testKeywords),
                    'total_keywords' => count($allKeywords),
                    'ad_groups' => $adGroupDetails,
                    'response' => $responseData
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Bid update failed',
                    'campaign_id' => $campaignId,
                    'status_code' => $statusCode,
                    'error' => $responseData['errors'][0]['message'] ?? 'Unknown error',
                    'response' => $responseData
                ];
            }
        } catch (\Exception $e) {
            Log::error("Exception during bid update test: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception occurred',
                'error' => $e->getMessage()
            ];
        }
    }

    public function ebay3UtilizedView()
    {
        return view('campaign.ebay-three.ebay3-utilized');
    }

    public function getEbay3UtilizationCounts(Request $request)
    {
        try {
            $today = now()->format('Y-m-d');
            $skuKey = 'EBAY3_UTILIZATION_' . $today;

            // Check if data exists for today (stored by command)
            $record = EbayThreeDataView::where('sku', $skuKey)->first();

            if ($record) {
                $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
                return response()->json([
                    'over_utilized' => $value['over_utilized'] ?? 0,
                    'under_utilized' => $value['under_utilized'] ?? 0,
                    'correctly_utilized' => $value['correctly_utilized'] ?? 0,
                    'status' => 200,
                ]);
            }

            // If not found, calculate on the fly - only Parent SKUs
            $productMasters = ProductMaster::whereRaw("UPPER(sku) LIKE 'PARENT %'")
                ->orderBy('parent', 'asc')
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->orderBy('report_range', 'asc')
                ->get();

            $campaignMap = [];

            // Process campaigns matching ProductMaster SKUs
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
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

                if ($nrValue == 'NRA') {
                    continue;
                }

                $matchedReports = $reports->filter(function ($item) use ($sku) {
                    $campaignSku = strtoupper(trim($item->campaign_name ?? ''));
                    return $campaignSku === $sku;
                });

                if ($matchedReports->isEmpty()) {
                    continue;
                }

                foreach ($matchedReports as $campaign) {
                    $campaignId = $campaign->campaign_id ?? '';
                    if (empty($campaignId)) {
                        continue;
                    }

                    if (!isset($campaignMap[$campaignId])) {
                        $campaignMap[$campaignId] = [
                            'campaign_id' => $campaignId,
                            'campaignBudgetAmount' => $campaign->campaignBudgetAmount ?? 0,
                            'l7_spend' => 0,
                            'l1_spend' => 0,
                            'acos' => 0,
                            'INV' => ($shopifyData[$pm->sku] ?? null) ? (int)($shopifyData[$pm->sku]->inv ?? 0) : 0,
                            'L30' => ($shopifyData[$pm->sku] ?? null) ? (int)($shopifyData[$pm->sku]->quantity ?? 0) : 0,
                        ];
                    }

                    $reportRange = $campaign->report_range ?? '';
                    $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                    $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');

                    if ($reportRange == 'L7') {
                        $campaignMap[$campaignId]['l7_spend'] = $adFees;
                    }

                    if ($reportRange == 'L1') {
                        $campaignMap[$campaignId]['l1_spend'] = $adFees;
                    }

                    if ($reportRange == 'L30') {
                        if ($sales > 0) {
                            $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                        } else if ($adFees > 0 && $sales == 0) {
                            $campaignMap[$campaignId]['acos'] = 100;
                        }
                    }
                }
            }

            // Process campaigns that don't match ProductMaster SKUs (additional campaigns)
            $allCampaignIds = $reports->pluck('campaign_id')->unique();
            $processedCampaignIds = array_keys($campaignMap);
            
            foreach ($allCampaignIds as $campaignId) {
                if (in_array($campaignId, $processedCampaignIds)) {
                    continue; // Already processed
                }

                $campaignReports = $reports->where('campaign_id', $campaignId);
                if ($campaignReports->isEmpty()) {
                    continue;
                }

                $firstCampaign = $campaignReports->first();
                $campaignName = $firstCampaign->campaign_name ?? '';
                
                // Try to find matching SKU in ProductMaster for INV/L30 data
                $matchedSku = null;
                foreach ($productMasters as $pm) {
                    if (strtoupper(trim($pm->sku)) === strtoupper(trim($campaignName))) {
                        $matchedSku = $pm->sku;
                        break;
                    }
                }

                $campaignMap[$campaignId] = [
                    'campaign_id' => $campaignId,
                    'campaignBudgetAmount' => $firstCampaign->campaignBudgetAmount ?? 0,
                    'l7_spend' => 0,
                    'l1_spend' => 0,
                    'acos' => 0,
                    'INV' => ($matchedSku && isset($shopifyData[$matchedSku])) ? (int)($shopifyData[$matchedSku]->inv ?? 0) : 0,
                    'L30' => ($matchedSku && isset($shopifyData[$matchedSku])) ? (int)($shopifyData[$matchedSku]->quantity ?? 0) : 0,
                ];

                foreach ($campaignReports as $campaign) {
                    $reportRange = $campaign->report_range ?? '';
                    $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                    $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');

                    if ($reportRange == 'L7') {
                        $campaignMap[$campaignId]['l7_spend'] = $adFees;
                    }

                    if ($reportRange == 'L1') {
                        $campaignMap[$campaignId]['l1_spend'] = $adFees;
                    }

                    if ($reportRange == 'L30') {
                        if ($sales > 0) {
                            $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                        } else if ($adFees > 0 && $sales == 0) {
                            $campaignMap[$campaignId]['acos'] = 100;
                        }
                    }
                }
            }

            // Process campaigns that don't match ProductMaster SKUs (additional campaigns)
            $allCampaignIds = $reports->pluck('campaign_id')->unique();
            $processedCampaignIds = array_keys($campaignMap);
            
            foreach ($allCampaignIds as $campaignId) {
                if (in_array($campaignId, $processedCampaignIds)) {
                    continue; // Already processed
                }

                $campaignReports = $reports->where('campaign_id', $campaignId);
                if ($campaignReports->isEmpty()) {
                    continue;
                }

                $firstCampaign = $campaignReports->first();
                $campaignName = $firstCampaign->campaign_name ?? '';
                
                // Try to find matching SKU in ProductMaster for INV/L30 data
                $matchedSku = null;
                foreach ($productMasters as $pm) {
                    if (strtoupper(trim($pm->sku)) === strtoupper(trim($campaignName))) {
                        $matchedSku = $pm->sku;
                        break;
                    }
                }

                $campaignMap[$campaignId] = [
                    'campaign_id' => $campaignId,
                    'campaignBudgetAmount' => $firstCampaign->campaignBudgetAmount ?? 0,
                    'l7_spend' => 0,
                    'l1_spend' => 0,
                    'acos' => 0,
                    'INV' => ($matchedSku && isset($shopifyData[$matchedSku])) ? (int)($shopifyData[$matchedSku]->inv ?? 0) : 0,
                    'L30' => ($matchedSku && isset($shopifyData[$matchedSku])) ? (int)($shopifyData[$matchedSku]->quantity ?? 0) : 0,
                ];

                foreach ($campaignReports as $campaign) {
                    $reportRange = $campaign->report_range ?? '';
                    $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                    $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');

                    if ($reportRange == 'L7') {
                        $campaignMap[$campaignId]['l7_spend'] = $adFees;
                    }

                    if ($reportRange == 'L1') {
                        $campaignMap[$campaignId]['l1_spend'] = $adFees;
                    }

                    if ($reportRange == 'L30') {
                        if ($sales > 0) {
                            $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                        } else if ($adFees > 0 && $sales == 0) {
                            $campaignMap[$campaignId]['acos'] = 100;
                        }
                    }
                }
            }

            // Calculate total ACOS from ALL campaigns
            $allL30Campaigns = Ebay3PriorityReport::where('report_range', 'L30')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->get();

            $totalSpendAll = 0;
            $totalSalesAll = 0;

            foreach ($allL30Campaigns as $campaign) {
                $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
                $totalSpendAll += $adFees;
                $totalSalesAll += $sales;
            }

            $totalACOSAll = $totalSalesAll > 0 ? ($totalSpendAll / $totalSalesAll) * 100 : 0;

            // Count campaigns by utilization type (mutually exclusive with priority)
            $overUtilizedCount = 0;
            $underUtilizedCount = 0;
            $correctlyUtilizedCount = 0;

            foreach ($campaignMap as $campaign) {
                $budget = $campaign['campaignBudgetAmount'] ?? 0;
                $l7_spend = $campaign['l7_spend'] ?? 0;
                $l1_spend = $campaign['l1_spend'] ?? 0;
                $acos = $campaign['acos'] ?? 0;
                $l30 = $campaign['L30'] ?? 0;
                $inv = $campaign['INV'] ?? 0;

                $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

                $rowAcos = $acos;
                if ($rowAcos == 0) {
                    $rowAcos = 100;
                }

                // Check DIL color (exclude pink for over and under)
                $dilDecimal = (is_numeric($l30) && is_numeric($inv) && $inv !== 0) ? ($l30 / $inv) : 0;
                $dilPercent = $dilDecimal * 100;
                $isPink = ($dilPercent >= 50);

                // Categorize campaigns with priority: Over > Under > Correctly (mutually exclusive)
                $categorized = false;
                
                // Over-utilized: (rowAcos > totalACOSAll && ub7 > 33) || (rowAcos <= totalACOSAll && ub7 > 90)
                if ($totalACOSAll > 0 && !$isPink) {
                    $condition1 = ($rowAcos > $totalACOSAll && $ub7 > 33);
                    $condition2 = ($rowAcos <= $totalACOSAll && $ub7 > 90);
                    if ($condition1 || $condition2) {
                        $overUtilizedCount++;
                        $categorized = true;
                    }
                }

                // Under-utilized: ub7 < 70 && ub1 < 70 (only if not already over-utilized)
                if (!$categorized && $ub7 < 70 && $ub1 < 70 && !$isPink) {
                    $underUtilizedCount++;
                    $categorized = true;
                }

                // Correctly-utilized: (ub7 >= 70 && ub7 <= 90) && (ub1 >= 70 && ub1 <= 90) (only if not already categorized)
                if (!$categorized && (($ub7 >= 70 && $ub7 <= 90) && ($ub1 >= 70 && $ub1 <= 90))) {
                    $correctlyUtilizedCount++;
                    $categorized = true;
                }
            }

            return response()->json([
                'over_utilized' => $overUtilizedCount,
                'under_utilized' => $underUtilizedCount,
                'correctly_utilized' => $correctlyUtilizedCount,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting Ebay3 utilization counts: " . $e->getMessage());
            return response()->json([
                'over_utilized' => 0,
                'under_utilized' => 0,
                'correctly_utilized' => 0,
                'status' => 500,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getEbay3UtilizationChartData(Request $request)
    {
        try {
            // Get data from ebay3_data_view table (stored by command)
            $data = EbayThreeDataView::where('sku', 'LIKE', 'EBAY3_UTILIZATION_%')
                ->orderBy('sku', 'desc')
                ->limit(30)
                ->get();
            
            $data = $data->map(function ($item) {
                $value = is_array($item->value) ? $item->value : json_decode($item->value, true);
                
                // Extract date from SKU format: EBAY3_UTILIZATION_YYYY-MM-DD
                $date = str_replace('EBAY3_UTILIZATION_', '', $item->sku);
                
                return [
                    'date' => $date,
                    'over_utilized' => $value['over_utilized'] ?? 0,
                    'under_utilized' => $value['under_utilized'] ?? 0,
                    'correctly_utilized' => $value['correctly_utilized'] ?? 0,
                ];
            })
            ->reverse()
            ->values();

            // Fill in missing dates with zeros (last 30 days)
            $today = \Carbon\Carbon::today();
            $filledData = [];
            $dataByDate = $data->keyBy('date');
            
            for ($i = 29; $i >= 0; $i--) {
                $date = $today->copy()->subDays($i)->format('Y-m-d');
                
                if (isset($dataByDate[$date])) {
                    $filledData[] = $dataByDate[$date];
                } else {
                    $filledData[] = [
                        'date' => $date,
                        'over_utilized' => 0,
                        'under_utilized' => 0,
                        'correctly_utilized' => 0,
                    ];
                }
            }

            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $filledData,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting Ebay3 utilization chart data: " . $e->getMessage());
            return response()->json([
                'status' => 500,
                'error' => $e->getMessage(),
                'data' => []
            ]);
        }
    }

    /**
     * Calculate and save SBID to last_sbid column for eBay3 campaigns
     * This matches the frontend SBID calculation logic from ebay3-utilized.blade.php
     */
    private function calculateAndSaveSBID($result)
    {
        // Save to yesterday's date because we're calculating SBID for yesterday's report data
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Prepare batch updates
        $updates = [];
        
        foreach ($result as $row) {
            // Skip if no campaign_id
            if (empty($row->campaign_id)) {
                continue;
            }

            // Check if NRA (🔴) is selected - skip if NRA
            $nraValue = isset($row->NR) ? trim($row->NR) : '';
            if ($nraValue === 'NRA') {
                continue; // Skip update if NRA is selected
            }

            $l1Cpc = floatval($row->l1_cpc ?? 0);
            $l7Cpc = floatval($row->l7_cpc ?? 0);
            $budget = floatval($row->campaignBudgetAmount ?? 0);
            $l7Spend = floatval($row->l7_spend ?? 0);
            $l1Spend = floatval($row->l1_spend ?? 0);
            $inv = floatval($row->INV ?? 0);

            // Calculate UB7 and UB1
            $ub7 = 0;
            $ub1 = 0;
            if ($budget > 0) {
                $ub7 = ($l7Spend / ($budget * 7)) * 100;
                $ub1 = ($l1Spend / $budget) * 100;
            }

            // Calculate SBID using the same logic as ebay3-utilized.blade.php (no price field)
            $sbid = 0;
            
            // Special rule: If UB7 = 0% and UB1 = 0%, set SBID to 0.50
            if ($ub7 == 0 && $ub1 == 0) {
                $sbid = 0.50;
            } 
            // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90
            elseif ($ub7 > 99 && $ub1 > 99) {
                if ($l1Cpc > 0) {
                    $sbid = floor($l1Cpc * 0.90 * 100) / 100;
                } elseif ($l7Cpc > 0) {
                    $sbid = floor($l7Cpc * 0.90 * 100) / 100;
                } else {
                    $sbid = 0;
                }
            } 
            // For 'all' utilization type, determine individual campaign's utilization status
            else {
                // Determine utilization status (same logic as combinedFilter in blade)
                $isOverUtilized = false;
                $isUnderUtilized = false;
                
                // Check over-utilized first (priority 1)
                if ($ub7 > 99 && $ub1 > 99) {
                    $isOverUtilized = true;
                }
                
                // Check under-utilized (priority 2: only if not over-utilized)
                if (!$isOverUtilized && $ub7 < 66 && $ub1 < 66 && $inv > 0) {
                    $isUnderUtilized = true;
                }
                
                // Apply SBID logic based on determined status
                if ($isOverUtilized) {
                    // If L1 CPC > 1.25, use L1 CPC * 0.80, otherwise use L1 CPC * 0.90
                    $cpcToUse = ($l1Cpc && !is_nan($l1Cpc) && $l1Cpc > 0) ? $l1Cpc : (($l7Cpc && !is_nan($l7Cpc) && $l7Cpc > 0) ? $l7Cpc : 0);
                    if ($cpcToUse > 1.25) {
                        $sbid = floor($cpcToUse * 0.80 * 100) / 100;
                    } elseif ($cpcToUse > 0) {
                        $sbid = floor($cpcToUse * 0.90 * 100) / 100;
                    } else {
                        $sbid = 0.50; // Fallback when both CPCs are 0
                    }
                } elseif ($isUnderUtilized) {
                    // Check if UB7 and UB1 are both 0% (already handled above, but keep for consistency)
                    if ($ub7 == 0 && $ub1 == 0) {
                        $sbid = 0.50;
                    } else {
                        // Use L1CPC if available (not 0, not NaN), otherwise use L7CPC
                        $cpcToUse = ($l1Cpc && !is_nan($l1Cpc) && $l1Cpc > 0) ? $l1Cpc : (($l7Cpc && !is_nan($l7Cpc) && $l7Cpc > 0) ? $l7Cpc : 0);
                        if ($cpcToUse > 0) {
                            // Ensure numeric comparison
                            $cpcToUse = floatval($cpcToUse);
                            if ($cpcToUse < 0.10) {
                                $sbid = floor($cpcToUse * 2.00 * 100) / 100;
                            } elseif ($cpcToUse >= 0.10 && $cpcToUse <= 0.20) {
                                $sbid = floor($cpcToUse * 1.50 * 100) / 100;
                            } elseif ($cpcToUse >= 0.21 && $cpcToUse <= 0.30) {
                                $sbid = floor($cpcToUse * 1.25 * 100) / 100;
                            } else {
                                $sbid = floor($cpcToUse * 1.10 * 100) / 100;
                            }
                        } else {
                            $sbid = 0.50; // Fallback when both CPCs are 0
                        }
                    }
                } else {
                    // Correctly-utilized: use L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                    if ($l1Cpc > 0) {
                        $sbid = floor($l1Cpc * 0.90 * 100) / 100;
                    } elseif ($l7Cpc > 0) {
                        $sbid = floor($l7Cpc * 0.90 * 100) / 100;
                    } else {
                        $sbid = 0.50; // Fallback when both CPCs are 0
                    }
                }
            }
            
            // Only save if SBID > 0
            if ($sbid > 0) {
                $sbidValue = (string)$sbid;
                $updates[$row->campaign_id] = $sbidValue;
            }
        }

        // Perform efficient bulk updates using WHERE IN
        // Update only yesterday's actual date records (not L1, L7, L30) for tracking purposes
        if (!empty($updates)) {
            // Check if last_sbid column exists in the table
            $columnExists = false;
            try {
                $columns = DB::select("SHOW COLUMNS FROM ebay_3_priority_reports LIKE 'last_sbid'");
                $columnExists = !empty($columns);
            } catch (\Exception $e) {
                Log::warning("Could not check for last_sbid column in ebay_3_priority_reports: " . $e->getMessage());
            }
            
            if (!$columnExists) {
                Log::warning("last_sbid column does not exist in ebay_3_priority_reports table. Skipping SBID save.");
                return; // Skip update if column doesn't exist
            }
            
            // Update in batches of 50 to avoid query size limits
            $chunks = array_chunk($updates, 50, true);
            foreach ($chunks as $chunk) {
                $campaignIds = array_keys($chunk);
                
                // Build CASE statement for bulk update
                $cases = [];
                $bindings = [];
                foreach ($chunk as $campaignId => $sbidValue) {
                    $cases[] = "WHEN ? THEN ?";
                    $bindings[] = $campaignId;
                    $bindings[] = $sbidValue;
                }
                
                $caseSql = implode(' ', $cases);
                $placeholders = str_repeat('?,', count($campaignIds) - 1) . '?';
                
                // Single query to update all records - only for yesterday's date (Y-m-d format)
                // Save to last_sbid column for tracking purposes
                // report_range should be the date in Y-m-d format (not L1, L7, L30)
                try {
                    DB::statement("
                        UPDATE ebay_3_priority_reports 
                        SET last_sbid = CASE campaign_id {$caseSql} END
                        WHERE campaign_id IN ({$placeholders})
                        AND report_range = ?
                        AND report_range NOT IN ('L7', 'L1', 'L30')
                        AND campaignStatus = 'RUNNING'
                    ", array_merge($bindings, $campaignIds, [$yesterday]));
                } catch (\Exception $e) {
                    Log::error("Error updating last_sbid in ebay_3_priority_reports: " . $e->getMessage());
                }
            }
        }
    }

}
