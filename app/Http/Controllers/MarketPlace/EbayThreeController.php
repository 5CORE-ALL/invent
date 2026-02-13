<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\EbayThreeDataView;
use App\Models\EbayThreeListingStatus;
use App\Models\ADVMastersData;
use App\Models\Ebay3GeneralReport;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayPriorityReport;
use App\Models\EbayGeneralReport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\AmazonChannelSummary;

class EbayThreeController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function ebay3TabulatorView(Request $request)
    {
        // Calculate total KW and PMT spent for header display
        // Note: Use L30 report_range directly without date filtering for accuracy
        
        // KW Spent from priority reports (L30 range)
        $kwSpent = DB::table('ebay_3_priority_reports')
            ->where('report_range', 'L30')
            ->selectRaw('SUM(CAST(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "") AS DECIMAL(10,2))) as total_spend')
            ->value('total_spend') ?? 0;
        
        // PMT Spent from general reports (L30 range)
        $pmtSpent = DB::table('ebay_3_general_reports')
            ->where('report_range', 'L30')
            ->selectRaw('SUM(CAST(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "") AS DECIMAL(10,2))) as total_spend')
            ->value('total_spend') ?? 0;
        
        return view("market-places.ebay3_tabulator_view", [
            'kwSpent' => (float) $kwSpent,
            'pmtSpent' => (float) $pmtSpent,
        ]);
    }

    public function ebay3DataJson(Request $request)
    {
        try {
            $response = $this->getViewEbay3DataTabulator($request);
            $data = json_decode($response->getContent(), true);

            // Auto-save daily summary in background (non-blocking)
            $this->saveDailySummaryIfNeeded($data['data'] ?? []);

            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching eBay3 data for Tabulator: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getViewEbay3DataTabulator(Request $request)
    {
        // Use fixed margin of 0.85 (85%) as specified
        $percentage = 0.85;
        $adUpdates = 0;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master - filter out PARENT SKUs
        $allSkus = $productMasterRows->pluck('sku')->toArray();
        $nonParentSkus = array_filter($allSkus, function($sku) {
            return stripos($sku, 'PARENT') === false;
        });
        $skus = array_values($nonParentSkus);

        $ebayMetrics = Ebay3Metric::select('sku', 'ebay_price', 'ebay_l30', 'ebay_l60', 'ebay_stock', 'views', 'l7_views', 'item_id', 'lmp_data', 'lmp_link')->whereIn('sku', $allSkus)->get()->keyBy('sku');

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch Amazon data for price comparison
        $amazonData = \App\Models\AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values for these SKUs from EbayThreeDataView
        $ebayDataViews = EbayThreeDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Fetch NRL (nr_req) values from EbayThreeListingStatus - get most recent non-empty record
        $ebayListingStatuses = EbayThreeListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(function ($record) {
                $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
                return !empty($value) && isset($value['nr_req']);
            })
            ->keyBy('sku');
        
        $nrValues = [];
        $listedValues = [];
        $liveValues = [];
        $spriceValues = [];
        $spftValues = [];
        $sroiValues = [];
        $sgpftValues = [];
        $nrReqValues = [];
        $hideValues = [];

        foreach ($ebayDataViews as $sku => $dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
            $nrValues[$sku] = $value['NR'] ?? null;
            $listedValues[$sku] = isset($value['Listed']) ? (int) $value['Listed'] : false;
            $liveValues[$sku] = isset($value['Live']) ? (int) $value['Live'] : false;
            $spriceValues[$sku] = isset($value['SPRICE']) ? floatval($value['SPRICE']) : null;
            $spftValues[$sku] = isset($value['SPFT']) ? floatval($value['SPFT']) : null;
            $sroiValues[$sku] = isset($value['SROI']) ? floatval($value['SROI']) : null;
            $sgpftValues[$sku] = isset($value['SGPFT']) ? floatval($value['SGPFT']) : null;
            $hideValues[$sku] = isset($value['Hide']) ? filter_var($value['Hide'], FILTER_VALIDATE_BOOLEAN) : false;
            // Get NRL value from EbayThreeDataView
            $nrReqValues[$sku] = isset($value['NRL']) ? $value['NRL'] : 'REQ';
        }
        
        // Fallback: Fetch nr_req from EbayThreeListingStatus if not set
        foreach ($ebayListingStatuses as $sku => $listingStatus) {
            if (!isset($nrReqValues[$sku]) || $nrReqValues[$sku] === 'REQ') {
                $statusValue = is_array($listingStatus->value) ? $listingStatus->value : (json_decode($listingStatus->value, true) ?: []);
                if (isset($statusValue['nr_req'])) {
                    $nrReqValues[$sku] = $statusValue['nr_req'];
                }
            }
        }
        
        // Set default 'REQ' for SKUs not found
        foreach ($skus as $sku) {
            if (!isset($nrReqValues[$sku])) {
                $nrReqValues[$sku] = 'REQ';
            }
        }

        // Also extract NRL values from EbayThreeDataView for KW Ads section
        $nrlValues = [];
        foreach ($ebayDataViews as $sku => $dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
            $nrlValues[$sku] = $value['NRL'] ?? null;
        }

        // Pre-fetch all KW campaign data from Ebay3PriorityReport (L7, L1, L30)
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return trim($sku);
        };

        $allKwReports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->orderBy('report_range', 'asc')
            ->get();

        // Index reports by normalized campaign_name and report_range
        $kwReportsByName = [];
        foreach ($allKwReports as $report) {
            $normalizedName = $normalizeSku($report->campaign_name ?? '');
            if (!isset($kwReportsByName[$normalizedName])) {
                $kwReportsByName[$normalizedName] = [];
            }
            $range = $report->report_range;
            // Keep first match (RUNNING prioritized due to orderBy)
            if (!isset($kwReportsByName[$normalizedName][$range])) {
                $kwReportsByName[$normalizedName][$range] = $report;
            }
        }

        // Pre-fetch SBID data (last_sbid, sbid_m, apprSbid)
        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $lastSbidMap = [];
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

        $sbidMMap = [];
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
                return $report->report_range === $yesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');
        foreach ($sbidMReports as $campaignId => $reports) {
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->sbid_m)) {
                $sbidMMap[$report->campaign_id] = $report->sbid_m;
            }
        }

        $apprSbidMap = [];
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
                return $report->report_range === $yesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');
        foreach ($apprSbidReports as $campaignId => $reports) {
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->apprSbid)) {
                $apprSbidMap[$report->campaign_id] = $report->apprSbid;
            }
        }

        // === PMT Ads pre-fetch: campaign_ads_listings for bid_percentage/suggested_bid ===
        // Match Ebay3PmtAdsController approach: simple COST_PER_SALE filter, keyBy listing_id
        $campaignListingsMap = collect();
        try {
            $campaignListingsMap = DB::connection('apicentral')
                ->table('ebay3_campaign_ads_listings')
                ->select('listing_id', 'bid_percentage', 'suggested_bid')
                ->where('funding_strategy', 'COST_PER_SALE')
                ->get()
                ->keyBy('listing_id');
        } catch (\Exception $e) {
            // apicentral may be unavailable
        }

        // === PMT Ads pre-fetch: general reports for clicks L30 & L7 ===
        $itemIds = $ebayMetrics->pluck('item_id')->filter()->toArray();
        $ebay3GeneralReportsL30 = Ebay3GeneralReport::where('report_range', 'L30')
            ->whereIn('listing_id', $itemIds)
            ->get();
        $ebay3GeneralReportsL7 = Ebay3GeneralReport::where('report_range', 'L7')
            ->whereIn('listing_id', $itemIds)
            ->get();

        // Build item_id -> SKU map for PMT metrics aggregation
        $itemIdToSkuMap = [];
        foreach ($ebayMetrics as $metric) {
            if (!empty($metric->item_id)) {
                $itemIdToSkuMap[$metric->item_id] = strtoupper($metric->sku);
            }
        }

        // Aggregate PMT L30 metrics by SKU
        $pmtAdMetricsBySku = [];
        foreach ($ebay3GeneralReportsL30 as $report) {
            $reportSku = $itemIdToSkuMap[$report->listing_id] ?? null;
            if (!$reportSku) continue;
            $pmtAdMetricsBySku[$reportSku]['Clk'] = ($pmtAdMetricsBySku[$reportSku]['Clk'] ?? 0) + (int) $report->clicks;
            $pmtAdMetricsBySku[$reportSku]['Imp'] = ($pmtAdMetricsBySku[$reportSku]['Imp'] ?? 0) + (int) $report->impressions;
            $pmtAdMetricsBySku[$reportSku]['GENERAL_SPENT'] = ($pmtAdMetricsBySku[$reportSku]['GENERAL_SPENT'] ?? 0) + (float) str_replace('USD ', '', $report->ad_fees ?? 0);
        }

        // Aggregate PMT L7 metrics by SKU
        $pmtAdMetricsBySkuL7 = [];
        foreach ($ebay3GeneralReportsL7 as $report) {
            $reportSku = $itemIdToSkuMap[$report->listing_id] ?? null;
            if (!$reportSku) continue;
            $pmtAdMetricsBySkuL7[$reportSku]['Clk'] = ($pmtAdMetricsBySkuL7[$reportSku]['Clk'] ?? 0) + (int) $report->clicks;
        }

        // Extra clicks data by listing_id
        $extraClicksData = $ebay3GeneralReportsL30->pluck('clicks', 'listing_id')->toArray();

        // First pass: Calculate sums for each parent from child SKUs
        $parentSums = [];
        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;
            
            if (!$isParent) {
                $parentValue = $productMaster->parent ?? null;
                if ($parentValue) {
                    if (!isset($parentSums[$parentValue])) {
                        $parentSums[$parentValue] = [
                            'INV' => 0, 
                            'L30' => 0, 
                            'eBay L30' => 0, 
                            'eBay L60' => 0,
                            'eBay Stock' => 0,
                            'views' => 0,
                            'l7_views' => 0,
                            'totalPrice' => 0,
                            'priceCount' => 0,
                            'totalLP' => 0,
                            'totalShip' => 0,
                            'lpCount' => 0,
                            'Total_pft' => 0,
                            'T_Sale_l30' => 0,
                            'AD_Spend_L30' => 0,
                            'kw_spend_L30' => 0,
                            'pmt_spend_L30' => 0,
                            // PMT Ads aggregation fields
                            'pmt_bid_percentage' => null,
                            'pmt_suggested_bid' => null,
                            'pmt_clicks_l7' => 0,
                            'pmt_clicks_l30' => 0,
                        ];
                    }
                    
                    // Add INV and L30 from shopify
                    if (isset($shopifyData[$sku])) {
                        $parentSums[$parentValue]['INV'] += floatval($shopifyData[$sku]->inv ?? 0);
                        $parentSums[$parentValue]['L30'] += floatval($shopifyData[$sku]->quantity ?? 0);
                    }
                    
                    // Add eBay L30, L60, Stock, views and price from metrics
                    if (isset($ebayMetrics[$sku])) {
                        $parentSums[$parentValue]['eBay L30'] += floatval($ebayMetrics[$sku]->ebay_l30 ?? 0);
                        $parentSums[$parentValue]['eBay L60'] += floatval($ebayMetrics[$sku]->ebay_l60 ?? 0);
                        $parentSums[$parentValue]['eBay Stock'] += floatval($ebayMetrics[$sku]->ebay_stock ?? 0);
                        $parentSums[$parentValue]['views'] += floatval($ebayMetrics[$sku]->views ?? 0);
                        $parentSums[$parentValue]['l7_views'] += floatval($ebayMetrics[$sku]->l7_views ?? 0);
                        
                        // Track price for average calculation (only count non-zero prices)
                        $price = floatval($ebayMetrics[$sku]->ebay_price ?? 0);
                        if ($price > 0) {
                            $parentSums[$parentValue]['totalPrice'] += $price;
                            $parentSums[$parentValue]['priceCount']++;
                        }
                        
                        // PMT Ads: pick bid_percentage/suggested_bid from first child that has data
                        $childItemId = $ebayMetrics[$sku]->item_id ?? null;
                        if ($childItemId && $parentSums[$parentValue]['pmt_bid_percentage'] === null) {
                            if (isset($campaignListingsMap[$childItemId])) {
                                $parentSums[$parentValue]['pmt_bid_percentage'] = $campaignListingsMap[$childItemId]->bid_percentage ?? null;
                                $parentSums[$parentValue]['pmt_suggested_bid'] = $campaignListingsMap[$childItemId]->suggested_bid ?? null;
                            }
                        }
                        
                        // PMT clicks aggregation
                        $childSkuUpper = strtoupper($sku);
                        $childPmtMetrics = $pmtAdMetricsBySku[$childSkuUpper] ?? [];
                        $childPmtMetricsL7 = $pmtAdMetricsBySkuL7[$childSkuUpper] ?? [];
                        $childPmtClicks30 = $childPmtMetrics['Clk'] ?? 0;
                        if (isset($extraClicksData[$childItemId])) {
                            $childPmtClicks30 += (int) $extraClicksData[$childItemId];
                        }
                        $parentSums[$parentValue]['pmt_clicks_l30'] += $childPmtClicks30;
                        $parentSums[$parentValue]['pmt_clicks_l7'] += ($childPmtMetricsL7['Clk'] ?? 0);
                    }
                    
                    // Get LP and Ship from product master for averages
                    $childProductMaster = $productMasterRows[$sku] ?? null;
                    if ($childProductMaster) {
                        $childValues = $childProductMaster->Values ?: [];
                        $childLp = 0;
                        foreach ($childValues as $k => $v) {
                            if (strtolower($k) === "lp") {
                                $childLp = floatval($v);
                                break;
                            }
                        }
                        if ($childLp === 0 && isset($childProductMaster->lp)) {
                            $childLp = floatval($childProductMaster->lp);
                        }
                        $childShip = isset($childValues["ship"]) ? floatval($childValues["ship"]) : (isset($childProductMaster->ship) ? floatval($childProductMaster->ship) : 0);
                        
                        if ($childLp > 0) {
                            $parentSums[$parentValue]['totalLP'] += $childLp;
                            $parentSums[$parentValue]['totalShip'] += $childShip;
                            $parentSums[$parentValue]['lpCount']++;
                        }
                    }
                }
            }
        }

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;
            
            // Include parent rows for display

            $ebayMetric = $ebayMetrics[$sku] ?? null;

            // Initialize the data structure
            $row = [];
            $row['Parent'] = $productMaster->parent ?? null;
            $row['(Child) sku'] = $sku;

            // For PARENT rows, use sum of children; for child rows, use individual values
            if ($isParent) {
                // Get parent identifier from the SKU (e.g., "PARENT 5C DS CHRM" -> "5C DS CHRM")
                $parentKey = $row['Parent'] ?? preg_replace('/^PARENT\s*/i', '', $sku);
                $sums = $parentSums[$parentKey] ?? [
                    'INV' => 0, 'L30' => 0, 'eBay L30' => 0, 'eBay L60' => 0, 'eBay Stock' => 0, 'views' => 0, 
                    'totalPrice' => 0, 'priceCount' => 0,
                    'totalLP' => 0, 'totalShip' => 0, 'lpCount' => 0,
                    'Total_pft' => 0, 'T_Sale_l30' => 0,
                    'AD_Spend_L30' => 0, 'kw_spend_L30' => 0, 'pmt_spend_L30' => 0,
                    'pmt_bid_percentage' => null, 'pmt_suggested_bid' => null,
                    'pmt_clicks_l7' => 0, 'pmt_clicks_l30' => 0
                ];
                
                $row['INV'] = $sums['INV'];
                $row['L30'] = $sums['L30'];
                $row['eBay L30'] = $sums['eBay L30'];
                $row['eBay L60'] = $sums['eBay L60'] ?? 0;
                $row['eBay Stock'] = $sums['eBay Stock'] ?? 0;
                $row['views'] = $sums['views'];
                
                // Calculate average price for parent
                $parentPrice = $sums['priceCount'] > 0 ? round($sums['totalPrice'] / $sums['priceCount'], 2) : 0;
                $row['eBay Price'] = $parentPrice;
                
                // Calculate average LP and Ship for parent
                $parentLp = $sums['lpCount'] > 0 ? round($sums['totalLP'] / $sums['lpCount'], 2) : 0;
                $parentShip = $sums['lpCount'] > 0 ? round($sums['totalShip'] / $sums['lpCount'], 2) : 0;
                $row['LP_productmaster'] = $parentLp;
                $row['Ship_productmaster'] = $parentShip;
                
                // Calculate SCVR for parent based on summed values
                $row['SCVR'] = $sums['views'] > 0 ? round(($sums['eBay L30'] / $sums['views']) * 100, 2) : 0;
                
                // Calculate E Dil% = (L30 / INV)
                $row['E Dil%'] = $sums['INV'] > 0 ? round($sums['L30'] / $sums['INV'], 4) : 0;
                
                // Calculate parent AD spend based on parent SKU campaign
                $parentAdSpendL30 = 0;
                $parentKwSpendL30 = 0;
                $parentPmtSpendL30 = 0;
                
                // For eBay3, campaigns are named by PARENT SKU
                $matchedCampaignL30 = Ebay3PriorityReport::where('report_range', 'L30')
                    ->where(function($q) use ($parentKey) {
                        $q->where('campaign_name', 'LIKE', '%' . $parentKey . '%')
                          ->orWhere('campaign_name', 'LIKE', '%PARENT ' . $parentKey . '%');
                    })
                    ->first();
                
                $parentKwSpendL30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
                $parentAdSpendL30 = $parentKwSpendL30; // For parent, we mainly track keyword campaign spend
                
                $row['AD_Spend_L30'] = round($parentAdSpendL30, 2);
                $row['spend_l30'] = round($parentAdSpendL30, 2);
                $row['kw_spend_L30'] = round($parentKwSpendL30, 2);
                $row['pmt_spend_L30'] = round($parentPmtSpendL30, 2);
                
                // Calculate Sales L30 = avg_price * total_ebay_l30
                $parentSalesL30 = round($parentPrice * $sums['eBay L30'], 2);
                $row['T_Sale_l30'] = $parentSalesL30;
                $row['Sales L30'] = $parentSalesL30;
                
                // Calculate AD% = (AD Spend L30 / Sales L30) * 100
                $row['AD%'] = $parentSalesL30 > 0 ? round(($parentAdSpendL30 / $parentSalesL30) * 100, 4) : 0;
                
                // Calculate Total_pft = (avg_price * percentage - avg_lp - avg_ship) * total_ebay_l30
                $parentProfit = round(($parentPrice * $percentage - $parentLp - $parentShip) * $sums['eBay L30'], 2);
                $row['Total_pft'] = $parentProfit;
                $row['Profit'] = $parentProfit;
                
                // Calculate TacosL30 = AD Spend L30 / Total Sales L30
                $row['TacosL30'] = $parentSalesL30 > 0 ? round($parentAdSpendL30 / $parentSalesL30, 4) : 0;
                
                // Calculate GPFT% = ((Price * 0.85 - Ship - LP) / Price) * 100
                $parentGpft = $parentPrice > 0 ? (($parentPrice * $percentage - $parentShip - $parentLp) / $parentPrice) * 100 : 0;
                $row['GPFT%'] = round($parentGpft, 2);
                
                // Calculate PFT% = GPFT% - AD%
                $row['PFT %'] = round($parentGpft - $row['AD%'], 2);
                
                // Calculate ROI% = ((Price * percentage - LP - Ship) / LP) * 100
                $row['ROI%'] = round(
                    $parentLp > 0 ? (($parentPrice * $percentage - $parentLp - $parentShip) / $parentLp) * 100 : 0,
                    2
                );
                
                // SPRICE calculation for parent - defaults to avg eBay Price
                $row['SPRICE'] = $parentPrice;
                $row['has_custom_sprice'] = false;
                
                // Calculate SGPFT based on SPRICE
                $parentSgpft = $parentPrice > 0 ? (($parentPrice * $percentage - $parentShip - $parentLp) / $parentPrice) * 100 : 0;
                $row['SGPFT'] = round($parentSgpft, 2);
                
                // Calculate SPFT = SGPFT - AD%
                $row['SPFT'] = round($parentSgpft - $row['AD%'], 2);
                
                // Calculate SROI
                $row['SROI'] = round(
                    $parentLp > 0 ? (($parentPrice * $percentage - $parentLp - $parentShip) / $parentLp) * 100 : 0,
                    2
                );
                
                // Set defaults for PARENT rows
                $row['NR'] = null;
                $row['nr_req'] = 'REQ';
                $row['NRL'] = null;
                $row['Listed'] = false;
                $row['Live'] = false;
                $row['Hide'] = false;
                $row['eBay_item_id'] = null;
                $row['percentage'] = $percentage;
                $row['ad_updates'] = $adUpdates;
                $row['image_path'] = null;
                $row['lmp_price'] = null;
                $row['lmp_link'] = null;
                $row['l7_views'] = $sums['l7_views'] ?? 0;
                $row['kw_campaign_id'] = '';
                $row['kw_campaignBudgetAmount'] = 0;
                $row['kw_campaignStatus'] = '';
                $row['kw_l7_spend'] = 0;
                $row['kw_l7_cpc'] = 0;
                $row['kw_l1_spend'] = 0;
                $row['kw_l1_cpc'] = 0;
                $row['kw_acos'] = 0;
                $row['kw_clicks'] = 0;
                $row['kw_ad_sold'] = 0;
                $row['kw_cvr'] = 0;
                $row['kw_last_sbid'] = '';
                $row['kw_sbid_m'] = '';
                $row['kw_apprSbid'] = '';
                // PMT Ads fields for parent rows
                // Use PARENT's own Ebay3Metric data (not aggregated children) for PMT columns
                $parentOwnItemId = $ebayMetric->item_id ?? null;
                if ($parentOwnItemId && isset($campaignListingsMap[$parentOwnItemId])) {
                    $row['bid_percentage'] = $campaignListingsMap[$parentOwnItemId]->bid_percentage ?? null;
                    $row['suggested_bid'] = $campaignListingsMap[$parentOwnItemId]->suggested_bid ?? null;
                } else {
                    $row['bid_percentage'] = $sums['pmt_bid_percentage'] ?? null;
                    $row['suggested_bid'] = $sums['pmt_suggested_bid'] ?? null;
                }
                $row['pmt_clicks_l7'] = $sums['pmt_clicks_l7'] ?? 0;
                $row['pmt_clicks_l30'] = $sums['pmt_clicks_l30'] ?? 0;

                // PARENT's own eBay metric data for PMT section (matches Ebay3PmtAdsController behavior)
                $row['pmt_own_views'] = $ebayMetric->views ?? 0;
                $row['pmt_own_l7_views'] = $ebayMetric->l7_views ?? 0;
                $row['pmt_own_ebay_l30'] = $ebayMetric->ebay_l30 ?? 0;
                $parentSkuUpper = strtoupper($sku);
                $parentOwnPmtL30 = $pmtAdMetricsBySku[$parentSkuUpper] ?? [];
                $parentOwnPmtL7 = $pmtAdMetricsBySkuL7[$parentSkuUpper] ?? [];
                $row['pmt_own_clicks_l30'] = $parentOwnPmtL30['Clk'] ?? 0;
                if ($parentOwnItemId && isset($extraClicksData[$parentOwnItemId])) {
                    $row['pmt_own_clicks_l30'] += (int) $extraClicksData[$parentOwnItemId];
                }
                $row['pmt_own_clicks_l7'] = $parentOwnPmtL7['Clk'] ?? 0;
                $row['lmp_entries'] = [];
                
                // Amazon Price - set to 0 for parent rows
                $row['A Price'] = 0;
                
            } else {
                // Shopify data for child SKUs
                if (isset($shopifyData[$sku])) {
                    $shopifyItem = $shopifyData[$sku];
                    $row['INV'] = $shopifyItem->inv ?? 0;
                    $row['L30'] = $shopifyItem->quantity ?? 0;
                } else {
                    $row['INV'] = 0;
                    $row['L30'] = 0;
                }
                
                // eBay3 Metrics for child SKUs
                $row['eBay L30'] = $ebayMetric->ebay_l30 ?? 0;
                $row['views'] = $ebayMetric->views ?? 0;
                $row['eBay Price'] = $ebayMetric->ebay_price ?? 0;
            
                // eBay3 Metrics (common fields)
                $row['eBay L60'] = $ebayMetric->ebay_l60 ?? 0;
                $row['eBay Stock'] = $ebayMetric->ebay_stock ?? 0;
                $row['eBay_item_id'] = $ebayMetric->item_id ?? null;
                
                // LMP Data from ebay_3_metrics table
                $lmpData = $ebayMetric->lmp_data ?? null;
                $lmpEntries = [];
                $lmpPrice = null;
                
                if ($lmpData) {
                    if (is_string($lmpData)) {
                        $lmpEntries = json_decode($lmpData, true) ?? [];
                    } else {
                        $lmpEntries = $lmpData;
                    }
                    
                    // Get lowest price from lmp_data
                    if (!empty($lmpEntries)) {
                        $prices = array_column($lmpEntries, 'price');
                        if (!empty($prices)) {
                            $lmpPrice = min($prices);
                        }
                    }
                }
                
                $row['lmp_price'] = $lmpPrice;
                $row['lmp_link'] = $ebayMetric->lmp_link ?? null;
                $row['lmp_entries'] = $lmpEntries;

                // Add values from product_master
                $values = $productMaster->Values ?: [];
                $lp = 0;
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($productMaster->lp)) {
                    $lp = floatval($productMaster->lp);
                }
                $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($productMaster->ship) ? floatval($productMaster->ship) : 0);

                $row['LP_productmaster'] = $lp;
                $row['Ship_productmaster'] = $ship;

                // NR/REQ and other values
                $row['NR'] = $nrValues[$sku] ?? null;
                $row['nr_req'] = $nrReqValues[$sku] ?? 'REQ';
                $row['NRL'] = $nrlValues[$sku] ?? null;
                $row['Listed'] = $listedValues[$sku] ?? false;
                $row['Live'] = $liveValues[$sku] ?? false;
                $row['Hide'] = $hideValues[$sku] ?? false;
                $row['l7_views'] = $ebayMetric->l7_views ?? 0;

                // Calculate AD% and other metrics
                $price = floatval($row['eBay Price'] ?? 0);
                $ebayL30 = floatval($row['eBay L30'] ?? 0);
                $views = floatval($row['views'] ?? 0);
                
                // Get AD spend from reports if available
                // NOTE: eBay3 campaigns are named by PARENT SKU, so we need to search by parent
                $adSpendL30 = 0;
                $kw_spend_l30 = 0;
                $pmt_spend_l30 = 0;
                
                // KW Campaign data defaults
                $row['kw_campaign_id'] = '';
                $row['kw_campaignBudgetAmount'] = 0;
                $row['kw_campaignStatus'] = '';
                $row['kw_l7_spend'] = 0;
                $row['kw_l7_cpc'] = 0;
                $row['kw_l1_spend'] = 0;
                $row['kw_l1_cpc'] = 0;
                $row['kw_acos'] = 0;
                $row['kw_clicks'] = 0;
                $row['kw_ad_sold'] = 0;
                $row['kw_cvr'] = 0;
                $row['kw_last_sbid'] = '';
                $row['kw_sbid_m'] = '';
                $row['kw_apprSbid'] = '';
                // PMT Ads fields for child rows
                $row['bid_percentage'] = null;
                $row['suggested_bid'] = null;
                $row['pmt_clicks_l7'] = 0;
                $row['pmt_clicks_l30'] = 0;
                
                if ($ebayMetric && $ebayMetric->item_id) {
                    // For keyword campaigns (Ebay3PriorityReport), search by PARENT SKU
                    // Use pre-fetched data instead of per-SKU queries
                    $parentSku = $row['Parent'] ?? '';
                    $normalizedParent = $normalizeSku('PARENT ' . $parentSku);
                    $normalizedParentClean = $normalizeSku($parentSku);
                    
                    // Look up pre-fetched campaign data by normalized parent name
                    $kwReportsForSku = $kwReportsByName[$normalizedParent] ?? ($kwReportsByName[$normalizedParentClean] ?? null);
                    
                    // Fallback: try child SKU if no parent match found
                    if (!$kwReportsForSku) {
                        $normalizedChild = $normalizeSku($sku);
                        $kwReportsForSku = $kwReportsByName[$normalizedChild] ?? null;
                    }
                    
                    $matchedCampaignL30 = null;
                    if ($kwReportsForSku) {
                        $matchedCampaignL7 = $kwReportsForSku['L7'] ?? null;
                        $matchedCampaignL1 = $kwReportsForSku['L1'] ?? null;
                        $matchedCampaignL30 = $kwReportsForSku['L30'] ?? null;
                        
                        // Get campaign details from any available report
                        $anyReport = $matchedCampaignL30 ?? $matchedCampaignL7 ?? $matchedCampaignL1;
                        if ($anyReport) {
                            $row['kw_campaign_id'] = $anyReport->campaign_id ?? '';
                            $row['kw_campaignBudgetAmount'] = $anyReport->campaignBudgetAmount ?? 0;
                            $row['kw_campaignStatus'] = $anyReport->campaignStatus ?? '';
                        }
                        
                        // L7 data
                        if ($matchedCampaignL7) {
                            $row['kw_l7_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0');
                            $row['kw_l7_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cost_per_click ?? '0');
                        }
                        
                        // L1 data
                        if ($matchedCampaignL1) {
                            $row['kw_l1_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0');
                            $row['kw_l1_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cost_per_click ?? '0');
                        }
                        
                        // L30 data
                        if ($matchedCampaignL30) {
                            $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? '0');
                            $sales = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? '0');
                            $clicks = (int) ($matchedCampaignL30->cpc_clicks ?? 0);
                            $adSold = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);
                            
                            $kw_spend_l30 = $adFees;
                            $row['kw_clicks'] = $clicks;
                            $row['kw_ad_sold'] = $adSold;
                            
                            if ($clicks > 0) {
                                $row['kw_cvr'] = round(($adSold / $clicks) * 100, 2);
                            }
                            if ($sales > 0) {
                                $row['kw_acos'] = round(($adFees / $sales) * 100, 2);
                            } elseif ($adFees > 0) {
                                $row['kw_acos'] = 100;
                            }
                        }
                        
                        // SBID data
                        $campaignId = $row['kw_campaign_id'];
                        if (!empty($campaignId)) {
                            $row['kw_last_sbid'] = $lastSbidMap[$campaignId] ?? '';
                            $row['kw_sbid_m'] = $sbidMMap[$campaignId] ?? '';
                            $row['kw_apprSbid'] = $apprSbidMap[$campaignId] ?? '';
                        }
                    } else {
                        // Fallback for L30 spend only (old approach)
                        $matchedCampaignL30 = null;
                        if (!empty($parentSku)) {
                            $matchedCampaignL30 = Ebay3PriorityReport::where('report_range', 'L30')
                                ->where(function($q) use ($parentSku) {
                                    $q->where('campaign_name', 'LIKE', '%' . $parentSku . '%')
                                      ->orWhere('campaign_name', 'LIKE', '%' . str_replace('PARENT ', '', $parentSku) . '%');
                                })
                                ->first();
                        }
                        if (!$matchedCampaignL30) {
                            $matchedCampaignL30 = Ebay3PriorityReport::where('report_range', 'L30')
                                ->where('campaign_name', 'LIKE', '%' . $sku . '%')
                                ->first();
                        }
                        $kw_spend_l30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
                    }
                    
                    // Try to get from Ebay3GeneralReport (promoted listings) - this uses item_id, not parent
                    $matchedGeneralL30 = Ebay3GeneralReport::where('report_range', 'L30')
                        ->where('listing_id', $ebayMetric->item_id)
                        ->first();
                    
                    $pmt_spend_l30 = (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
                    $adSpendL30 = $kw_spend_l30 + $pmt_spend_l30;
                }
                
                // Add AD_Spend_L30 to row for frontend
                $row['AD_Spend_L30'] = round($adSpendL30, 2);
                $row['spend_l30'] = round($adSpendL30, 2);
                $row['kw_spend_L30'] = round($kw_spend_l30, 2);
                $row['pmt_spend_L30'] = round($pmt_spend_l30, 2);

                // === PMT Ads: bid_percentage, suggested_bid, clicks ===
                // Match Ebay3PmtAdsController: use item_id directly with keyBy collection
                if ($ebayMetric && $ebayMetric->item_id) {
                    if (isset($campaignListingsMap[$ebayMetric->item_id])) {
                        $row['bid_percentage'] = $campaignListingsMap[$ebayMetric->item_id]->bid_percentage ?? null;
                        $row['suggested_bid'] = $campaignListingsMap[$ebayMetric->item_id]->suggested_bid ?? null;
                    }
                    // PMT clicks from aggregated metrics
                    $skuUpper = strtoupper($sku);
                    $pmtMetrics = $pmtAdMetricsBySku[$skuUpper] ?? [];
                    $pmtMetricsL7 = $pmtAdMetricsBySkuL7[$skuUpper] ?? [];
                    $row['pmt_clicks_l30'] = $pmtMetrics['Clk'] ?? 0;
                    if (isset($extraClicksData[$ebayMetric->item_id])) {
                        $row['pmt_clicks_l30'] += (int) $extraClicksData[$ebayMetric->item_id];
                    }
                    $row['pmt_clicks_l7'] = $pmtMetricsL7['Clk'] ?? 0;
                }

                // Child's own PMT data (same as regular data for child rows)
                $row['pmt_own_views'] = $row['views'];
                $row['pmt_own_l7_views'] = $ebayMetric->l7_views ?? 0;
                $row['pmt_own_ebay_l30'] = $ebayMetric->ebay_l30 ?? 0;
                $row['pmt_own_clicks_l30'] = $row['pmt_clicks_l30'] ?? 0;
                $row['pmt_own_clicks_l7'] = $row['pmt_clicks_l7'] ?? 0;
                
                // Calculate AD% = (AD Spend L30 / (Price * eBay L30)) * 100
                $totalRevenue = $price * $ebayL30;
                $row['AD%'] = $totalRevenue > 0 ? round(($adSpendL30 / $totalRevenue) * 100, 4) : 0;
                
                // Calculate Profit and Sales L30
                $row['Total_pft'] = round(($price * $percentage - $lp - $ship) * $ebayL30, 2);
                $row['Profit'] = $row['Total_pft'];
                $row['T_Sale_l30'] = round($price * $ebayL30, 2);
                $row['Sales L30'] = $row['T_Sale_l30'];
                
                // Calculate TacosL30 = AD Spend L30 / Total Sales L30
                $row['TacosL30'] = $row['T_Sale_l30'] > 0 ? round($adSpendL30 / $row['T_Sale_l30'], 4) : 0;
                
                // Calculate GPFT% = ((Price * 0.85 - Ship - LP) / Price) * 100 (using 85% margin)
                $gpft = $price > 0 ? (($price * $percentage - $ship - $lp) / $price) * 100 : 0;
                $row['GPFT%'] = round($gpft, 2);
                
                // Calculate PFT% = GPFT% - AD%
                $row['PFT %'] = round($gpft - $row['AD%'], 2);
                
                // Calculate ROI% = ((Price * percentage - LP - Ship) / LP) * 100
                $row['ROI%'] = round(
                    $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
                    2
                );
                
                // Calculate SCVR = (eBay L30 / views) * 100
                $row['SCVR'] = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0;
                $cvr = $row['SCVR'] ?? 0;
                
                // Calculate E Dil% = (L30 / INV) if INV > 0
                $inv = floatval($row['INV'] ?? 0);
                $l30 = floatval($row['L30'] ?? 0);
                $row['E Dil%'] = $inv > 0 ? round($l30 / $inv, 4) : 0;

                $row['percentage'] = $percentage;
                $row['ad_updates'] = $adUpdates;

                // SPRICE calculation - default to eBay Price (no CVR condition)
                $calculatedSprice = null;
                if ($price > 0) {
                    // SPRICE defaults to eBay Price (CVR condition removed)
                    $calculatedSprice = round($price, 2);
                    
                    // Check for saved SPRICE
                    $savedSprice = $spriceValues[$sku] ?? null;
                    
                    // Check for SPRICE_STATUS in database (pushed/applied/error/account_restricted)
                    $savedStatus = null;
                    if (isset($ebayDataViews[$sku])) {
                        $raw = is_array($ebayDataViews[$sku]->value) 
                            ? $ebayDataViews[$sku]->value 
                            : (json_decode($ebayDataViews[$sku]->value, true) ?? []);
                        if (isset($raw['SPRICE_STATUS'])) {
                            $savedStatus = $raw['SPRICE_STATUS'];
                        }
                    }
                    
                    // Use saved SPRICE if it exists and differs from calculated
                    if ($savedSprice !== null && abs($savedSprice - $calculatedSprice) > 0.01) {
                        $row['SPRICE'] = $savedSprice;
                        $row['has_custom_sprice'] = true;
                        // Use saved status if exists (pushed/applied/error/account_restricted), otherwise 'saved'
                        $row['SPRICE_STATUS'] = $savedStatus ?: 'saved';
                    } else {
                        $row['SPRICE'] = $calculatedSprice;
                        $row['has_custom_sprice'] = false;
                        // Use saved status if exists, otherwise null
                        $row['SPRICE_STATUS'] = $savedStatus;
                    }
                    
                    // Calculate SGPFT based on actual SPRICE being used
                    $sprice = $row['SPRICE'];
                    $sgpft = round(
                        $sprice > 0 ? (($sprice * $percentage - $ship - $lp) / $sprice) * 100 : 0,
                        2
                    );
                    $row['SGPFT'] = $sgpft;
                    
                    // Calculate SPFT = SGPFT - AD%
                    $row['SPFT'] = $sgpft;
                    
                    // Calculate SROI
                    $row['SROI'] = round(
                        $lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0,
                        2
                    );
                } else {
                    $row['SPRICE'] = null;
                    $row['SPFT'] = null;
                    $row['SROI'] = null;
                    $row['SGPFT'] = null;
                    $row['has_custom_sprice'] = false;
                    $row['SPRICE_STATUS'] = null;
                }

                // Image
                $row['image_path'] = $shopifyData[$sku]->image_src ?? ($values['image_path'] ?? ($productMaster->image_path ?? null));
                
                // Amazon Price
                $row['A Price'] = isset($amazonData[$sku]) ? ($amazonData[$sku]->price ?? 0) : 0;
            }

            $processedData[] = (object) $row;
        }

        // Reorganize data into tree structure: Parents with children
        $parentRows = [];
        $childRowsByParent = [];
        $orphanRows = []; // Child rows without a matching parent row
        
        foreach ($processedData as $row) {
            $rowArray = (array) $row;
            $sku = $rowArray['(Child) sku'] ?? '';
            $parentValue = $rowArray['Parent'] ?? '';
            
            if (stripos($sku, 'PARENT') !== false) {
                // This is a PARENT row
                $parentRows[$parentValue] = $rowArray;
            } else {
                // This is a child row - group by parent
                if (!empty($parentValue)) {
                    if (!isset($childRowsByParent[$parentValue])) {
                        $childRowsByParent[$parentValue] = [];
                    }
                    $childRowsByParent[$parentValue][] = $rowArray;
                } else {
                    // Orphan rows (no parent)
                    $orphanRows[] = $rowArray;
                }
            }
        }
        
        // Build tree data: each parent row gets _children array
        $treeData = [];
        
        foreach ($parentRows as $parentValue => $parentRow) {
            $parentRow['_children'] = $childRowsByParent[$parentValue] ?? [];
            $treeData[] = $parentRow;
        }
        
        // Add any orphan child rows that don't have parent rows
        foreach ($childRowsByParent as $parentValue => $children) {
            if (!isset($parentRows[$parentValue])) {
                // Create a synthetic parent row for these orphan children with full calculations
                $syntheticParent = [
                    'Parent' => $parentValue,
                    '(Child) sku' => 'PARENT ' . $parentValue,
                    'INV' => 0,
                    'L30' => 0,
                    'eBay L30' => 0,
                    'eBay L60' => 0,
                    'views' => 0,
                    'eBay Price' => 0,
                    'totalPrice' => 0,
                    'priceCount' => 0,
                    'totalLP' => 0,
                    'totalShip' => 0,
                    'lpCount' => 0,
                    '_children' => $children
                ];
                
                // Calculate sums and averages for synthetic parent
                foreach ($children as $child) {
                    $syntheticParent['INV'] += floatval($child['INV'] ?? 0);
                    $syntheticParent['L30'] += floatval($child['L30'] ?? 0);
                    $syntheticParent['eBay L30'] += floatval($child['eBay L30'] ?? 0);
                    $syntheticParent['eBay L60'] += floatval($child['eBay L60'] ?? 0);
                    $syntheticParent['views'] += floatval($child['views'] ?? 0);
                    
                    $childPrice = floatval($child['eBay Price'] ?? 0);
                    if ($childPrice > 0) {
                        $syntheticParent['totalPrice'] += $childPrice;
                        $syntheticParent['priceCount']++;
                    }
                    
                    $childLp = floatval($child['LP_productmaster'] ?? 0);
                    $childShip = floatval($child['Ship_productmaster'] ?? 0);
                    if ($childLp > 0) {
                        $syntheticParent['totalLP'] += $childLp;
                        $syntheticParent['totalShip'] += $childShip;
                        $syntheticParent['lpCount']++;
                    }
                }
                
                // Calculate averages
                $avgPrice = $syntheticParent['priceCount'] > 0 ? round($syntheticParent['totalPrice'] / $syntheticParent['priceCount'], 2) : 0;
                $avgLp = $syntheticParent['lpCount'] > 0 ? round($syntheticParent['totalLP'] / $syntheticParent['lpCount'], 2) : 0;
                $avgShip = $syntheticParent['lpCount'] > 0 ? round($syntheticParent['totalShip'] / $syntheticParent['lpCount'], 2) : 0;
                
                $syntheticParent['eBay Price'] = $avgPrice;
                $syntheticParent['LP_productmaster'] = $avgLp;
                $syntheticParent['Ship_productmaster'] = $avgShip;
                
                // Calculate SCVR
                $syntheticParent['SCVR'] = $syntheticParent['views'] > 0 ? round(($syntheticParent['eBay L30'] / $syntheticParent['views']) * 100, 2) : 0;
                
                // Calculate E Dil%
                $syntheticParent['E Dil%'] = $syntheticParent['INV'] > 0 ? round($syntheticParent['L30'] / $syntheticParent['INV'], 4) : 0;
                
                // Calculate Sales L30
                $salesL30 = round($avgPrice * $syntheticParent['eBay L30'], 2);
                $syntheticParent['T_Sale_l30'] = $salesL30;
                $syntheticParent['Sales L30'] = $salesL30;
                
                // Calculate Profit
                $profit = round(($avgPrice * $percentage - $avgLp - $avgShip) * $syntheticParent['eBay L30'], 2);
                $syntheticParent['Total_pft'] = $profit;
                $syntheticParent['Profit'] = $profit;
                
                // Calculate GPFT%
                $gpft = $avgPrice > 0 ? (($avgPrice * $percentage - $avgShip - $avgLp) / $avgPrice) * 100 : 0;
                $syntheticParent['GPFT%'] = round($gpft, 2);
                
                // Calculate ROI%
                $syntheticParent['ROI%'] = $avgLp > 0 ? round((($avgPrice * $percentage - $avgLp - $avgShip) / $avgLp) * 100, 2) : 0;
                
                // AD spend for synthetic parent - try to find by parent name
                $matchedCampaignL30 = Ebay3PriorityReport::where('report_range', 'L30')
                    ->where(function($q) use ($parentValue) {
                        $q->where('campaign_name', 'LIKE', '%' . $parentValue . '%')
                          ->orWhere('campaign_name', 'LIKE', '%PARENT ' . $parentValue . '%');
                    })
                    ->first();
                
                $adSpendL30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
                $syntheticParent['AD_Spend_L30'] = round($adSpendL30, 2);
                $syntheticParent['spend_l30'] = round($adSpendL30, 2);
                $syntheticParent['kw_spend_L30'] = round($adSpendL30, 2);
                $syntheticParent['pmt_spend_L30'] = 0;
                // PMT Ads fields for synthetic parent
                $syntheticParent['bid_percentage'] = null;
                $syntheticParent['suggested_bid'] = null;
                $syntheticParent['pmt_clicks_l7'] = 0;
                $syntheticParent['pmt_clicks_l30'] = 0;
                $syntheticParent['pmt_own_views'] = 0;
                $syntheticParent['pmt_own_l7_views'] = 0;
                $syntheticParent['pmt_own_ebay_l30'] = 0;
                $syntheticParent['pmt_own_clicks_l7'] = 0;
                $syntheticParent['pmt_own_clicks_l30'] = 0;
                
                // Calculate AD%
                $syntheticParent['AD%'] = $salesL30 > 0 ? round(($adSpendL30 / $salesL30) * 100, 4) : 0;
                
                // Calculate TacosL30
                $syntheticParent['TacosL30'] = $salesL30 > 0 ? round($adSpendL30 / $salesL30, 4) : 0;
                
                // Calculate PFT %
                $syntheticParent['PFT %'] = round($gpft - $syntheticParent['AD%'], 2);
                
                // SPRICE calculations
                $syntheticParent['SPRICE'] = $avgPrice;
                $syntheticParent['has_custom_sprice'] = false;
                $syntheticParent['SGPFT'] = round($gpft, 2);
                $syntheticParent['SPFT'] = round($gpft - $syntheticParent['AD%'], 2);
                $syntheticParent['SROI'] = $avgLp > 0 ? round((($avgPrice * $percentage - $avgLp - $avgShip) / $avgLp) * 100, 2) : 0;
                
                // Set defaults
                $syntheticParent['NR'] = null;
                $syntheticParent['nr_req'] = 'REQ';
                $syntheticParent['Listed'] = false;
                $syntheticParent['Live'] = false;
                $syntheticParent['Hide'] = false;
                $syntheticParent['eBay_item_id'] = null;
                $syntheticParent['percentage'] = $percentage;
                $syntheticParent['ad_updates'] = $adUpdates;
                $syntheticParent['image_path'] = null;
                $syntheticParent['A Price'] = 0; // Amazon price for synthetic parent
                
                // Remove temp fields
                unset($syntheticParent['totalPrice'], $syntheticParent['priceCount'], $syntheticParent['totalLP'], $syntheticParent['totalShip'], $syntheticParent['lpCount']);
                
                $treeData[] = $syntheticParent;
            }
        }
        
        // Add orphan rows (rows without any parent)
        foreach ($orphanRows as $orphan) {
            $treeData[] = $orphan;
        }

        return response()->json([
            'message' => 'eBay3 Data Fetched Successfully',
            'data' => $treeData,
            'status' => 200
        ]);
    }

    public function getEbay3ColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay3_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setEbay3ColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay3_tabulator_column_visibility_{$userId}";
        
        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    public function pushEbay3Price(Request $request)
    {
        $sku = strtoupper(trim($request->input('sku')));
        $price = $request->input('price');

        if (empty($sku)) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'SKU is required.']]
            ], 400);
        }

        // Validate price
        $priceFloat = floatval($price);
        if (!is_numeric($price) || $priceFloat <= 0) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'Price must be a positive number.']]
            ], 400);
        }

        // Validate price range
        if ($priceFloat < 0.01 || $priceFloat > 10000) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'Price must be between $0.01 and $10,000.']]
            ], 400);
        }

        $priceFloat = round($priceFloat, 2);

        try {
            // Get item_id from Ebay3Metric
            $ebayMetric = Ebay3Metric::where('sku', $sku)->first();

            if (!$ebayMetric || !$ebayMetric->item_id) {
                $this->saveSpriceStatus($sku, 'error');
                Log::error('eBay3 item_id not found', ['sku' => $sku]);
                return response()->json([
                    'errors' => [['code' => 'NotFound', 'message' => 'eBay3 listing not found for SKU: ' . $sku]]
                ], 404);
            }

            // Push price to eBay using EbayThreeApiService
            $ebayService = new \App\Services\EbayThreeApiService();
            $result = $ebayService->reviseFixedPriceItem($ebayMetric->item_id, $priceFloat);

            if (isset($result['success']) && $result['success']) {
                $this->saveSpriceStatus($sku, 'pushed');
                Log::info('eBay3 price update successful', [
                    'sku' => $sku, 
                    'price' => $priceFloat, 
                    'item_id' => $ebayMetric->item_id,
                    'rlogId' => $result['rlogId'] ?? 'N/A',
                    'correlationId' => $result['correlationId'] ?? 'N/A'
                ]);
                return response()->json([
                    'success' => true, 
                    'message' => 'Price updated successfully',
                    'rlogId' => $result['rlogId'] ?? null,
                    'correlationId' => $result['correlationId'] ?? null,
                    'build' => $result['build'] ?? null,
                    'timestamp' => $result['timestamp'] ?? null
                ]);
            } else {
                // Check if account is restricted
                $isAccountRestricted = isset($result['accountRestricted']) && $result['accountRestricted'];
                
                if ($isAccountRestricted) {
                    $this->saveSpriceStatus($sku, 'account_restricted');
                } else {
                    $this->saveSpriceStatus($sku, 'error');
                }
                
                $errors = $result['errors'] ?? [['code' => 'UnknownError', 'message' => 'Failed to update price']];
                
                // Enhanced error handling for account restrictions
                $errorMessages = [];
                $hasLvisError = false;
                
                // Normalize errors to array format
                if (!is_array($errors)) {
                    $errors = [$errors];
                }
                
                foreach ($errors as $error) {
                    $errorCode = is_array($error) ? ($error['ErrorCode'] ?? '') : '';
                    $errorMsg = is_array($error) ? ($error['LongMessage'] ?? $error['ShortMessage'] ?? 'Unknown error') : (string)$error;
                    $errorParams = is_array($error) ? ($error['ErrorParameters'] ?? []) : [];
                    
                    // Extract error parameter messages
                    $paramMessages = [];
                    if (is_array($errorParams)) {
                        foreach ($errorParams as $param) {
                            if (is_array($param) && isset($param['Value'])) {
                                $paramMessages[] = strip_tags($param['Value']);
                            }
                        }
                    }
                    $fullErrorText = $errorMsg . ' ' . implode(' ', $paramMessages);
                    
                    // Check for account restriction errors
                    $isAccountRestricted = false;
                    $isEmbargoedCountry = false;
                    
                    if (stripos($fullErrorText, 'account is restricted') !== false || 
                        stripos($fullErrorText, 'restrictions on your account') !== false ||
                        stripos($fullErrorText, 'embargoed country') !== false) {
                        $isAccountRestricted = true;
                        $isEmbargoedCountry = stripos($fullErrorText, 'embargoed country') !== false;
                    }
                    
                    if ($errorCode == '21916293' || strpos($errorMsg, 'Lvis') !== false || $isAccountRestricted) {
                        $hasLvisError = true;
                        
                        if ($isAccountRestricted) {
                            if ($isEmbargoedCountry) {
                                $errorMessages[] = [
                                    'code' => $errorCode ?: 'AccountRestricted',
                                    'message' => 'ACCOUNT RESTRICTION: Your eBay account is restricted due to country/embargo restrictions. Please check your eBay Messages for "Your eBay account is restricted" and resolve the account restrictions before updating prices. This cannot be bypassed programmatically.'
                                ];
                            } else {
                                $errorMessages[] = [
                                    'code' => $errorCode ?: 'AccountRestricted',
                                    'message' => 'ACCOUNT RESTRICTION: Your eBay account has restrictions that prevent price updates. Please check your eBay Messages for "Your eBay account is restricted" and provide the requested information to remove restrictions. Contact eBay Customer Service if you believe this is an error.'
                                ];
                            }
                        } else {
                            $errorMessages[] = [
                                'code' => $errorCode ?: 'LvisBlocked',
                                'message' => 'Listing validation blocked: This listing may have policy violations or restrictions. Please check the listing status in eBay Seller Hub and resolve any issues before updating the price.'
                            ];
                        }
                    } else {
                        // Check for business policy warning (non-blocking)
                        if ($errorCode == '21919456' || stripos($errorMsg, 'business policies') !== false) {
                            Log::warning('eBay3 business policy warning (non-blocking)', [
                                'sku' => $sku,
                                'error' => $errorMsg
                            ]);
                            // Don't add to errorMessages as it's just a warning
                        } else {
                            $errorMessages[] = [
                                'code' => $errorCode ?: 'APIError',
                                'message' => $errorMsg
                            ];
                        }
                    }
                }
                
                Log::error('eBay3 price update failed', [
                    'sku' => $sku,
                    'price' => $priceFloat,
                    'item_id' => $ebayMetric->item_id,
                    'errors' => $errors,
                    'hasLvisError' => $hasLvisError,
                    'rlogId' => $result['rlogId'] ?? 'N/A',
                    'correlationId' => $result['correlationId'] ?? 'N/A'
                ]);
                
                return response()->json([
                    'errors' => $errorMessages,
                    'rlogId' => $result['rlogId'] ?? null,
                    'correlationId' => $result['correlationId'] ?? null,
                    'build' => $result['build'] ?? null,
                    'timestamp' => $result['timestamp'] ?? null
                ], 400);
            }
        } catch (\Exception $e) {
            $this->saveSpriceStatus($sku, 'error');
            Log::error('Exception in pushEbay3Price', ['sku' => $sku, 'price' => $priceFloat, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['errors' => [['code' => 'Exception', 'message' => 'An error occurred: ' . $e->getMessage()]]], 500);
        }
    }

    private function saveSpriceStatus($sku, $status)
    {
        try {
            $ebayThreeDataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);
            
            $existing = is_array($ebayThreeDataView->value)
                ? $ebayThreeDataView->value
                : (json_decode($ebayThreeDataView->value, true) ?: []);

            $merged = array_merge($existing, [
                'SPRICE_STATUS' => $status,
                'SPRICE_STATUS_UPDATED_AT' => now()->toDateTimeString()
            ]);

            $ebayThreeDataView->value = $merged;
            $ebayThreeDataView->save();
        } catch (\Exception $e) {
            Log::error('Error saving SPRICE_STATUS', ['sku' => $sku, 'status' => $status, 'error' => $e->getMessage()]);
        }
    }

    public function updateEbay3SpriceStatus(Request $request)
    {
        $sku = strtoupper(trim($request->input('sku')));
        $status = $request->input('status');

        if (empty($sku) || !in_array($status, ['pushed', 'applied', 'error'])) {
            return response()->json(['success' => false, 'error' => 'Invalid SKU or status'], 400);
        }

        $this->saveSpriceStatus($sku, $status);
        return response()->json(['success' => true, 'message' => 'Status updated successfully']);
    }

    public function overallthreeEbay(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        // });

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay3')->first();

        $ebayPercentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $ebayAdUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.ebayThreeAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayPercentage' => $ebayPercentage,
            'ebayAdUpdates' => $ebayAdUpdates
        ]);
    }

    public function getEbay3TotalSaleSaveData(Request $request)
    {
        return ADVMastersData::getEbay3TotalSaleSaveDataProceed($request);
    }

    public function ebayThreePricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $percentage = Cache::remember('Ebay3', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay3')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.ebayThreePricingCvr', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function getViewEbay3Data(Request $request)
    {
        // Get percentage and ad_updates from cache or database
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay3')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        $ebayMetrics = Ebay3Metric::select('sku', 'ebay_price', 'ebay_l30', 'ebay_l60', 'ebay_stock', 'views', 'item_id', 'lmp_data', 'lmp_link')->whereIn('sku', $skus)->get()->keyBy('sku');


        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch Amazon data for price comparison
        $amazonData = \App\Models\AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values for these SKUs from EbayThreeDataView
        $ebayDataViews = EbayThreeDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Fetch NRL (nr_req) values from EbayThreeListingStatus - get most recent non-empty record
        $ebayListingStatuses = EbayThreeListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(function ($record) {
                $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
                return !empty($value) && isset($value['nr_req']);
            })
            ->keyBy('sku');
        
        $nrValues = [];
        $listedValues = [];
        $liveValues = [];
        $spriceValues = [];
        $spftValues = [];
        $sroiValues = [];
        $sgpftValues = [];
        $nrReqValues = [];
        $hideValues = [];

        foreach ($ebayDataViews as $sku => $dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
            $nrValues[$sku] = $value['NR'] ?? null;
            $listedValues[$sku] = isset($value['Listed']) ? (int) $value['Listed'] : false;
            $liveValues[$sku] = isset($value['Live']) ? (int) $value['Live'] : false;
            $spriceValues[$sku] = isset($value['SPRICE']) ? floatval($value['SPRICE']) : null;
            $spftValues[$sku] = isset($value['SPFT']) ? floatval($value['SPFT']) : null;
            $sroiValues[$sku] = isset($value['SROI']) ? floatval($value['SROI']) : null;
            $sgpftValues[$sku] = isset($value['SGPFT']) ? floatval($value['SGPFT']) : null;
            $hideValues[$sku] = isset($value['Hide']) ? filter_var($value['Hide'], FILTER_VALIDATE_BOOLEAN) : false;
        }
        
        // Fetch nr_req from EbayThreeListingStatus
        foreach ($ebayListingStatuses as $sku => $listingStatus) {
            $statusValue = is_array($listingStatus->value) ? $listingStatus->value : (json_decode($listingStatus->value, true) ?: []);
            $nrReqValues[$sku] = $statusValue['nr_req'] ?? 'REQ';
        }
        
        // Set default 'REQ' for SKUs not in EbayThreeListingStatus
        foreach ($skus as $sku) {
            if (!isset($nrReqValues[$sku])) {
                $nrReqValues[$sku] = 'REQ';
            }
        }

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            $ebayMetric = $ebayMetrics[$productMaster->sku] ?? null;

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                '(Child) sku' => $sku,
                'Sku' => $sku, // Keep both for compatibility
                'R&A' => false, // Default value, can be updated as needed
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            //Start Ebay3 Data
            $processedItem['eBay L30'] = $ebayMetric->ebay_l30 ?? 0;
            $processedItem['eBay Price'] = $ebayMetric->ebay_price ?? 0;
            $processedItem['views'] = $ebayMetric->views ?? 0;

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem['LP'] = $values['lp'] ?? 0;
            $processedItem['Ship'] = $values['ship'] ?? 0;
            $processedItem['COGS'] = $values['cogs'] ?? 0;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            // Add image_path - check shopify first, then product master values, then product master image_path
            $processedItem['image_path'] = null;
            if (isset($shopifyData[$sku]) && isset($shopifyData[$sku]->image_src)) {
                $processedItem['image_path'] = $shopifyData[$sku]->image_src;
            } elseif (isset($values['image_path'])) {
                $processedItem['image_path'] = $values['image_path'];
            } elseif (isset($productMaster->image_path)) {
                $processedItem['image_path'] = $productMaster->image_path;
            }
            
            // Amazon Price
            $processedItem['A Price'] = isset($amazonData[$sku]) ? ($amazonData[$sku]->price ?? 0) : 0;

            // Fetch NR value if available
            $processedItem['NR'] = $nrValues[$sku] ?? null;
            $processedItem['nr_req'] = $nrReqValues[$sku] ?? 'REQ';
            $processedItem['Listed'] = $listedValues[$sku] ?? false;
            $processedItem['Live'] = $liveValues[$sku] ?? false;
            $processedItem['Hide'] = $hideValues[$sku] ?? false;

            // Fetch SPRICE, SPFT, SROI, SGPFT from database if available
            $processedItem['SPRICE'] = $spriceValues[$sku] ?? null;
            $processedItem['SPFT'] = $spftValues[$sku] ?? null;
            $processedItem['SROI'] = $sroiValues[$sku] ?? null;
            $processedItem['SGPFT'] = $sgpftValues[$sku] ?? null;

            // Calculate AD% and other metrics
            $price = floatval($processedItem['eBay Price'] ?? 0);
            $ebayL30 = floatval($processedItem['eBay L30'] ?? 0);
            $lp = floatval($processedItem['LP'] ?? 0);
            $ship = floatval($processedItem['Ship'] ?? 0);
            $views = floatval($processedItem['views'] ?? 0);
            
            // Get AD spend from reports if available
            $adSpendL30 = 0;
            $kw_spend_l30 = 0;
            $pmt_spend_l30 = 0;
            if ($ebayMetric && $ebayMetric->item_id) {
                // Try to get from EbayPriorityReport (keyword campaigns)
                $matchedCampaignL30 = Ebay3PriorityReport::where('report_range', 'L30')
                    ->where('campaign_name', 'LIKE', '%' . $sku . '%')
                    ->first();
                
                // Try to get from EbayGeneralReport (promoted listings)
                $matchedGeneralL30 = Ebay3GeneralReport::where('report_range', 'L30')
                    ->where('listing_id', $ebayMetric->item_id)
                    ->first();
                
                $kw_spend_l30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
                $pmt_spend_l30 = (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
                $adSpendL30 = $kw_spend_l30 + $pmt_spend_l30;
            }
            
            // Add AD_Spend_L30 to processedItem for frontend
            $processedItem['AD_Spend_L30'] = round($adSpendL30, 2);
            $processedItem['spend_l30'] = round($adSpendL30, 2);
            $processedItem['kw_spend_L30'] = round($kw_spend_l30, 2);
            $processedItem['pmt_spend_L30'] = round($pmt_spend_l30, 2);
            
            // Calculate AD% = (AD Spend L30 / (Price * eBay L30)) * 100
            $totalRevenue = $price * $ebayL30;
            $processedItem['AD%'] = $totalRevenue > 0 ? round(($adSpendL30 / $totalRevenue) * 100, 4) : 0;
            
            // Calculate Profit and Sales L30
            $processedItem['Total_pft'] = round(($price * $percentageValue - $lp - $ship) * $ebayL30, 2);
            $processedItem['Profit'] = $processedItem['Total_pft'];
            $processedItem['T_Sale_l30'] = round($price * $ebayL30, 2);
            $processedItem['Sales L30'] = $processedItem['T_Sale_l30'];
            
            // Calculate TacosL30 = AD Spend L30 / Total Sales L30
            $processedItem['TacosL30'] = $processedItem['T_Sale_l30'] > 0 ? round($adSpendL30 / $processedItem['T_Sale_l30'], 4) : 0;
            
            // Calculate GPFT% = ((Price * 0.86 - Ship - LP) / Price) * 100
            $gpft = $price > 0 ? (($price * 0.86 - $ship - $lp) / $price) * 100 : 0;
            $processedItem['GPFT%'] = round($gpft, 2);
            
            // Calculate PFT% = GPFT% - AD%
            $processedItem['PFT %'] = round($gpft - $processedItem['AD%'], 2);
            
            // Calculate ROI% = ((Price * percentage - LP - Ship) / LP) * 100
            $processedItem['ROI%'] = round(
                $lp > 0 ? (($price * $percentageValue - $lp - $ship) / $lp) * 100 : 0,
                2
            );
            
            // Calculate SCVR = (eBay L30 / views) * 100
            $processedItem['SCVR'] = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0;
            
            // Calculate E Dil% = (L30 / INV) if INV > 0
            $inv = floatval($processedItem['INV'] ?? 0);
            $l30 = floatval($processedItem['L30'] ?? 0);
            $processedItem['E Dil%'] = $inv > 0 ? round($l30 / $inv, 4) : 0;

            // Default values for other fields
            $processedItem['A L30'] = 0;
            $processedItem['Sess30'] = 0;
            $processedItem['price'] = 0;
            $processedItem['TOTAL PFT'] = $processedItem['Total_pft'];
            $processedItem['T Sales L30'] = $processedItem['T_Sale_l30'];
            $processedItem['Roi'] = $processedItem['ROI%'];
            $processedItem['percentage'] = $percentageValue;
            $processedItem['ad_updates'] = $adUpdates;
            $processedItem['LP_productmaster'] = $lp;
            $processedItem['Ship_productmaster'] = $ship;

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function updateAllEbay3Skus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');

            // Validate inputs
            if (empty($type)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Type parameter is required.'
                ], 400);
            }

            if ($value === null || $value === '') {
                return response()->json([
                    'status' => 400,
                    'message' => 'Value parameter is required.'
                ], 400);
            }

            // Current record fetch
            $marketplace = MarketplacePercentage::where('marketplace', 'Ebay3')->first();

            $percent = $marketplace ? $marketplace->percentage : 100;
            $adUpdates = $marketplace ? ($marketplace->ad_updates ?? 0) : 0;

            // Handle percentage update
            if ($type === 'percentage' || $request->has('percent')) {
                $percentValue = $request->input('percent') ?? $value;
                if (!is_numeric($percentValue) || $percentValue < 0 || $percentValue > 100) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid percentage value. Must be between 0 and 100.'
                    ], 400);
                }
                $percent = (float) $percentValue;
            }

            // Handle ad_updates update
            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid ad_updates value. Must be a positive number.'
                    ], 400);
                }
                $adUpdates = (float) $value;
            }

            // Save both fields - check for existing record including soft-deleted ones
            $marketplace = MarketplacePercentage::withTrashed()->where('marketplace', 'Ebay3')->first();
            
            if ($marketplace) {
                // If soft-deleted, restore it first
                if ($marketplace->trashed()) {
                    $marketplace->restore();
                }
                // Update existing record
                $marketplace->percentage = $percent;
                $marketplace->ad_updates = $adUpdates;
                $marketplace->save();
            } else {
                // Create new record
                $marketplace = MarketplacePercentage::create([
                    'marketplace' => 'Ebay3',
                    'percentage' => $percent,
                    'ad_updates' => $adUpdates,
                ]);
            }

            // Refresh the model to get the latest values
            $marketplace->refresh();

            // Store in cache
            Cache::put('Ebay3', $percent, now()->addDays(30));
            Cache::put('Ebay3_ad_updates', $adUpdates, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type ?? 'percentage') . ' updated successfully',
                'data' => [
                    'marketplace' => 'Ebay3',
                    'percentage' => (float) $marketplace->percentage,
                    'ad_updates' => (float) ($marketplace->ad_updates ?? 0)
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in updateAllEbay3Skus: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => 500,
                'message' => 'Error updating Ebay3 marketplace values',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $skus = $request->input("skus");
        $hideValues = $request->input("hideValues");
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
                $ebayDataView = EbayThreeDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?: []);
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
                $ebayDataView = EbayThreeDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?: []);
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

        $ebayDataView = EbayThreeDataView::firstOrNew(["sku" => $sku]);
        $value = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?: []);

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


    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = EbayThreeDataView::firstOrCreate(
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

    public function importEbayThreeAnalytics(Request $request)
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
                EbayThreeDataView::updateOrCreate(
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

    public function exportEbayThreeAnalytics()
    {
        $ebayData = EbayThreeDataView::all();

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
        $fileName = 'Ebay_Three_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Ebay_Three_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function saveSpriceToDatabase(Request $request)
    {
        Log::info('Saving eBay3 pricing data', $request->all());
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');
        $spft_percent = $request->input('spft_percent');
        $sroi_percent = $request->input('sroi_percent');

        if (!$sku || !$sprice) {
            Log::error('SKU or sprice missing', ['sku' => $sku, 'sprice' => $sprice]);
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        // Get current marketplace percentage for Ebay3
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay3')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        Log::info('Using percentage', ['percentage' => $percentage]);

        // Get ProductMaster for lp and ship
        $pm = ProductMaster::where('sku', $sku)->first();
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

        // Calculate profit - Use fixed 0.85 (85%) margin for eBay3 tabulator
        $fixedMargin = 0.85;
        $spriceFloat = floatval($sprice);
        $profit = ($spriceFloat * $fixedMargin - $lp - $ship);

        // Calculate SGPFT first with 0.85 margin
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * $fixedMargin - $ship - $lp) / $spriceFloat) * 100, 2) : 0;
        
        // Get AD% from the product (using Ebay3Metric)
        $adPercent = 0;
        $ebay3Metric = Ebay3Metric::where('sku', $sku)->first();
        
        // For Ebay3, we'll calculate AD% if we have the metric data
        // You may need to adjust this based on your actual data structure
        if ($ebay3Metric) {
            // Calculate AD% based on available data
            // This is a simplified version - adjust based on your actual requirements
            $adPercent = 0; // Default to 0 if no specific calculation available
        }
        
        // Use provided SPFT and SROI if available, otherwise calculate
        $spft = $spft_percent !== null ? floatval($spft_percent) : round($sgpft - $adPercent, 2);
        
        // SROI = ((SPRICE * (0.85 - AD%/100) - ship - lp) / lp) * 100 - using 0.85 margin
        $adDecimal = $adPercent / 100;
        $sroi = $sroi_percent !== null ? floatval($sroi_percent) : round(
            $lp > 0 ? (($spriceFloat * ($fixedMargin - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
            2
        );
        
        Log::info('Calculated values', ['sprice' => $spriceFloat, 'sgpft' => $sgpft, 'ad_percent' => $adPercent, 'spft' => $spft, 'sroi' => $sroi]);

        $ebayThreeDataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($ebayThreeDataView->value)
            ? $ebayThreeDataView->value
            : (json_decode($ebayThreeDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
            'SGPFT' => $sgpft,
        ]);

        $ebayThreeDataView->value = $merged;
        $ebayThreeDataView->save();
        Log::info('Data saved successfully to EbayThreeDataView', ['sku' => $sku]);

        return response()->json([
            'success' => true,
            'message' => 'SPRICE saved successfully for Ebay3',
            'data' => [
                'sku' => $sku,
                'sprice' => $spriceFloat,
                'spft' => $spft,
                'sroi' => $sroi,
                'sgpft' => $sgpft
            ]
        ]);
    }

    /**
     * Clear SPRICE-related fields from EbayThreeDataView table
     * Only removes: SPRICE, SPFT, SROI, SGPFT
     * Keeps other data: NR, Listed, Live, Hide, NRL, etc.
     */
    public function clearAllSprice(Request $request)
    {
        try {
            Log::info('Clearing SPRICE-related fields from EbayThreeDataView table');
            
            // Get all records
            $records = EbayThreeDataView::all();
            $clearedCount = 0;
            
            foreach ($records as $record) {
                // Decode the value column
                $value = is_array($record->value) 
                    ? $record->value 
                    : (json_decode($record->value, true) ?: []);
                
                // Check if any SPRICE-related fields exist
                $hasSprice = isset($value['SPRICE']) || isset($value['SPFT']) || 
                             isset($value['SROI']) || isset($value['SGPFT']);
                
                if ($hasSprice) {
                    // Remove only SPRICE-related fields
                    unset($value['SPRICE']);
                    unset($value['SPFT']);
                    unset($value['SROI']);
                    unset($value['SGPFT']);
                    
                    // Save the updated value (keeping NR, Listed, Live, Hide, NRL, etc.)
                    $record->value = $value;
                    $record->save();
                    $clearedCount++;
                }
            }
            
            Log::info('Cleared SPRICE fields from ' . $clearedCount . ' records');
            
            return response()->json([
                'success' => true,
                'message' => 'Cleared SPRICE data from ' . $clearedCount . ' records (other data preserved)'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error clearing SPRICE data: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to clear SPRICE data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-save daily eBay 3 summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            
            // Flatten tree structure for SKU-only view (matches JavaScript logic)
            $flatData = [];
            foreach ($products as $item) {
                // Convert to array if it's an object
                $parent = is_array($item) ? $item : (array) $item;
                
                if (isset($parent['_children']) && is_array($parent['_children'])) {
                    // Add only child rows, skip parent
                    foreach ($parent['_children'] as $child) {
                        $flatData[] = is_array($child) ? $child : (array) $child;
                    }
                } else {
                    // If no children, check if it's not a parent row
                    $sku = $parent['(Child) sku'] ?? '';
                    if (!str_contains(strtoupper($sku), 'PARENT')) {
                        $flatData[] = $parent;
                    }
                }
            }
            
            // Filter: INV > 0 && nr_req === 'REQ' (EXACT JavaScript logic after flattening)
            $filteredData = collect($flatData)->filter(function($p) {
                $invCheck = floatval($p['INV'] ?? 0) > 0;
                $reqCheck = ($p['nr_req'] ?? '') === 'REQ';
                
                return $invCheck && $reqCheck;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Get spend from database tables (same as view does)
            $totalKwSpendL30 = DB::table('ebay_3_priority_reports')
                ->where('report_range', 'L30')
                ->selectRaw('SUM(CAST(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "") AS DECIMAL(10,2))) as total_spend')
                ->value('total_spend') ?? 0;
            
            $totalPmtSpendL30 = DB::table('ebay_3_general_reports')
                ->where('report_range', 'L30')
                ->selectRaw('SUM(CAST(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "") AS DECIMAL(10,2))) as total_spend')
                ->value('total_spend') ?? 0;
            
            $totalSpendL30 = $totalKwSpendL30 + $totalPmtSpendL30;
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalSkuCount = $filteredData->count();
            $totalTcos = 0;
            $totalPftAmt = 0;
            $totalSalesAmt = 0;
            $totalLpAmt = 0;
            $totalFbaInv = 0;
            $totalFbaL30 = 0;
            $totalDilPercent = 0;
            $dilCount = 0;
            $zeroSoldCount = 0;
            $moreSoldCount = 0;
            $lessAmzCount = 0;
            $moreAmzCount = 0;
            $missingCount = 0;
            $mapCount = 0;
            $invStockCount = 0;
            $totalWeightedPrice = 0;
            $totalL30 = 0;
            $totalViews = 0;
            
            // Loop through each row (EXACT JavaScript forEach logic)
            foreach ($filteredData as $row) {
                $totalTcos += floatval($row['AD%'] ?? 0);
                $totalPftAmt += floatval($row['Total_pft'] ?? 0);
                $totalSalesAmt += floatval($row['T_Sale_l30'] ?? 0);
                $totalLpAmt += floatval($row['LP_productmaster'] ?? 0) * floatval($row['eBay L30'] ?? 0);
                $totalFbaInv += floatval($row['INV'] ?? 0);
                $totalFbaL30 += floatval($row['eBay L30'] ?? 0);
                
                // Don't sum spend from rows - we use database totals from above
                
                $l30 = floatval($row['eBay L30'] ?? 0);
                if ($l30 == 0) {
                    $zeroSoldCount++;
                } else {
                    $moreSoldCount++;
                }
                
                $dil = floatval($row['E Dil%'] ?? 0);
                if (!is_nan($dil) && $dil != 0) {
                    $totalDilPercent += $dil;
                    $dilCount++;
                }
                
                // Compare eBay Price with Amazon Price
                $ebayPrice = floatval($row['eBay Price'] ?? 0);
                $amazonPrice = floatval($row['A Price'] ?? 0);
                
                if ($amazonPrice > 0 && $ebayPrice > 0) {
                    if ($ebayPrice < $amazonPrice) {
                        $lessAmzCount++;
                    } elseif ($ebayPrice > $amazonPrice) {
                        $moreAmzCount++;
                    }
                }
                
                // Count Missing - no item_id
                $itemId = $row['eBay_item_id'] ?? '';
                if (!$itemId || $itemId === null || $itemId === '') {
                    $missingCount++;
                }
                
                // Stock comparison
                $ebayStock = floatval($row['eBay Stock'] ?? 0);
                $inv = floatval($row['INV'] ?? 0);
                
                if ($inv > 0 && $ebayStock > 0 && $inv == $ebayStock) {
                    $mapCount++;
                }
                
                if ($inv > 0 && $ebayStock > 0 && $inv != $ebayStock) {
                    $invStockCount++;
                }
                
                // Weighted price
                $totalWeightedPrice += $ebayPrice * $l30;
                $totalL30 += $l30;
                
                // Views
                $totalViews += floatval($row['views'] ?? 0);
            }
            
            // Calculate averages and percentages (EXACT JavaScript logic)
            $avgPrice = $totalL30 > 0 ? $totalWeightedPrice / $totalL30 : 0;
            $avgCVR = $totalViews > 0 ? ($totalL30 / $totalViews * 100) : 0;
            $tacosPercent = $totalSalesAmt > 0 ? (($totalSpendL30 / $totalSalesAmt) * 100) : 0;
            $groiPercent = $totalLpAmt > 0 ? (($totalPftAmt / $totalLpAmt) * 100) : 0;
            $avgGpft = $totalSalesAmt > 0 ? (($totalPftAmt / $totalSalesAmt) * 100) : 0;
            $npftPercent = $avgGpft - $tacosPercent;
            $nroiPercent = $groiPercent - $tacosPercent;
            $avgDil = $dilCount > 0 ? $totalDilPercent / $dilCount : 0;
            
            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'nmap_count' => $invStockCount,  // Not mapped (inventory mismatch)
                'inv_stock_count' => $invStockCount,  // Keep for backward compatibility
                'less_amz_count' => $lessAmzCount,
                'more_amz_count' => $moreAmzCount,
                
                // Financial Totals
                'total_kw_spend_l30' => round($totalKwSpendL30, 2),
                'total_pmt_spend_l30' => round($totalPmtSpendL30, 2),
                'total_spend_l30' => round($totalSpendL30, 2),
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
                'avg_dil' => round($avgDil, 2),
                
                // Averages
                'avg_price' => round($avgPrice, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters
                'filters_applied' => [
                    'view_mode' => 'sku',     // SKU only
                    'inventory' => 'more',    // INV > 0
                    'nrl' => 'REQ',          // REQ only
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'ebay3',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, REQ only, SKU only)',
                ]
            );
            
            Log::info("Daily eBay3 summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily eBay3 summary: ' . $e->getMessage());
        }
    }

    public function getCampaignDataBySku(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }
        $cleanSku = strtoupper(trim((string) $sku));

        $ebay3Metric = Ebay3Metric::where('sku', $sku)->first();
        if (!$ebay3Metric) {
            $ebay3Metric = Ebay3Metric::whereRaw('UPPER(TRIM(sku)) = ?', [$cleanSku])->first();
        }
        $itemId = $ebay3Metric && !empty($ebay3Metric->item_id) ? trim((string) $ebay3Metric->item_id) : null;

        // For PARENT SKUs, use sum of child SKUs' INV (matching utilized page logic exactly)
        // For regular SKUs, use the SKU's own INV
        $inv = 0.0;
        if (stripos($sku, 'PARENT') !== false) {
            // PARENT SKU: sum all child SKUs' INV values (matching Ebay3UtilizedAdsController logic)
            // Get the ProductMaster record for this PARENT SKU to get its parent field
            $parentPm = ProductMaster::where('sku', $sku)->first();
            if (!$parentPm) {
                $parentPm = ProductMaster::whereRaw('UPPER(TRIM(sku)) = ?', [$cleanSku])->first();
            }
            
            // Use the parent field from ProductMaster, or fallback to SKU itself
            $parentKey = $parentPm ? ($parentPm->parent ?? $sku) : $sku;
            
            // Get all child SKUs that have this parent (matching utilized page approach)
            $allProductMasters = ProductMaster::whereNull('deleted_at')->get();
            foreach ($allProductMasters as $pm) {
                // Skip PARENT SKUs
                if (stripos($pm->sku, 'PARENT') !== false) {
                    continue;
                }
                // Check if parent matches
                $pmParent = $pm->parent ?? '';
                if (!empty($pmParent) && strtoupper(trim($pmParent)) === strtoupper(trim($parentKey))) {
                    // Get child SKU's INV value
                    $childShopify = ShopifySku::where('sku', $pm->sku)->first();
                    if (!$childShopify) {
                        $childShopify = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($pm->sku))])->first();
                    }
                    $childInv = $childShopify ? (float) ($childShopify->inv ?? 0) : 0.0;
                    $inv += $childInv;
                }
            }
        } else {
            // Regular SKU: use the SKU's own INV
            $shopify = ShopifySku::where('sku', $sku)->first();
            if (!$shopify) {
                $shopify = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [$cleanSku])->first();
            }
            $inv = $shopify ? (float) ($shopify->inv ?? 0) : 0.0;
        }

        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $lastSbidMap = [];
        $lastSbidReports = Ebay3PriorityReport::where('report_range', $dayBeforeYesterday)
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
        $kwL30 = Ebay3PriorityReport::where('report_range', 'L30')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get();
        $kwL7 = Ebay3PriorityReport::where('report_range', 'L7')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $kwL1 = Ebay3PriorityReport::where('report_range', 'L1')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $price = $ebay3Metric ? (float) ($ebay3Metric->ebay_price ?? 0) : 0;

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
            // Match ebay-3/pmt/ads: fetch all COST_PER_SALE listings and map by listing_id (same as Ebay3PmtAdsController)
            $campaignListing = null;
            try {
                // Convert itemId to string for comparison (listing_id might be stored as string in DB)
                $itemIdStr = (string) $itemId;
                
                // Fetch all COST_PER_SALE listings and create a map (same approach as Ebay3PmtAdsController)
                $campaignListings = DB::connection('apicentral')
                    ->table('ebay3_campaign_ads_listings')
                    ->select('listing_id', 'bid_percentage', 'suggested_bid')
                    ->where('funding_strategy', 'COST_PER_SALE')
                    ->get()
                    ->keyBy(function($item) {
                        return (string) $item->listing_id;
                    });
                
                // Check if we have a match for this itemId
                if ($campaignListings->has($itemIdStr)) {
                    $campaignListing = $campaignListings->get($itemIdStr);
                } else {
                    // Fallback: try to get any row for this listing_id (not just COST_PER_SALE)
                    $campaignListing = DB::connection('apicentral')
                        ->table('ebay3_campaign_ads_listings')
                        ->where('listing_id', $itemId)
                        ->orWhere('listing_id', $itemIdStr)
                        ->select('listing_id', 'bid_percentage', 'suggested_bid')
                        ->orderByDesc('id')
                        ->first();
                }
            } catch (\Exception $e) {
                // apicentral may be unavailable
            }
            // Handle CBID and ES BID - preserve 0 values, only null if truly null or empty string
            // Same logic as Ebay3PmtAdsController: use ?? null
            $cbid = $campaignListing ? ($campaignListing->bid_percentage ?? null) : null;
            $esBid = $campaignListing ? ($campaignListing->suggested_bid ?? null) : null;
            $views = $ebay3Metric ? (float) ($ebay3Metric->views ?? 0) : 0;
            $l7Views = $ebay3Metric ? (float) ($ebay3Metric->l7_views ?? 0) : 0;
            $ebayL30 = $ebay3Metric ? (float) ($ebay3Metric->ebay_l30 ?? 0) : 0;
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

            $ptReports = Ebay3GeneralReport::where('report_range', 'L30')
                ->where('listing_id', $itemId)
                ->get();
            
            if ($ptReports->isEmpty()) {
                // If no reports but have views/bids, still show PMT data
                if ($views > 0 || ($cbid !== null && $cbid > 0) || ($esBid !== null && $esBid > 0)) {
                    $ptCampaigns[] = [
                        'campaign_name' => 'PMT - ' . ($itemId ?? 'N/A'),
                        'cbid' => $cbid,
                        'es_bid' => $esBid,
                        's_bid' => $sBid,
                        't_views' => $views > 0 ? $views : null,
                        'l7_views' => $l7Views,
                        'scvr' => $scvr,
                    ];
                }
            } else {
                // If reports exist, show all of them (like ebay1)
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
            // Price cap removed for PARENT SKUs (matching ebay3-utilized.blade.php)
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
}
