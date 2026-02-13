<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\EbayMetric;
use App\Models\ShopifySku;
use App\Models\EbayDataView;
use Illuminate\Http\Request;
use App\Models\EbayGeneralReport;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\ADVMastersData;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster; 
use App\Models\EbaySkuDailyData;
use App\Models\AmazonDatasheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\EbayListingStatus;
use App\Services\EbayApiService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Exception;
use App\Models\AmazonChannelSummary;

class EbayController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function ebayView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;


        return view("market-places.ebay", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
            "ebayAdUpdates" => $adUpdates,
        ]);
    }

    public function ebayTabulatorView(Request $request)
    {
        return view("market-places.ebay_tabulator_view");
    }

       public function ebayViewData(Request $request)
    {
        return view("market-places.ebay_pricing_data");
    }

    public function ebayDataJson(Request $request)
    {
        try {
            $response = $this->getViewEbayData($request);
            $data = json_decode($response->getContent(), true);

            // Auto-save daily summary in background (non-blocking)
            $this->saveDailySummaryIfNeeded($data['data'] ?? []);

            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching eBay data for Tabulator: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getAdvEbayTotalSaveData(Request $request)
    {
        return ADVMastersData::getAdvEbayTotalSaveDataProceed($request);
    }

    public function ebayPricingCVR(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "ebay_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Ebay"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
            }
        );

        return view("market-places.ebay_pricing_cvr", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
        ]);
    }


    public function ebayPricingIncreaseDecrease(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "ebay_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Ebay"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
            }
        );

        $marketplaceData = MarketplacePercentage::where("marketplace", "Ebay")->first();
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        $listingStatus = EbayListingStatus::select("sku", "value")->get()->keyBy("sku");

        return view("market-places.ebay_pricing_increase_decrease", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
            "listingStatus" => $listingStatus,
            "ebayAdUpdates" => $adUpdates,
        ]);
    }
    
    public function ebayPricingIncrease(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "ebay_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Ebay"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100;
            }
        );

        return view("market-places.ebay_pricing_increase", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
        ]);
    }
    public function updateFbaStatusEbay(Request $request)
    {
        $sku = $request->input('shopify_id');
        $fbaStatus = $request->input('fba');

        if (!$sku || !is_numeric($fbaStatus)) {
            return response()->json(['error' => 'SKU and FBA status are required.'], 400);
        }
        $amazonData = DB::table('amazon_data_view')
            ->where('sku', $sku)
            ->first();

        if (!$amazonData) {
            return response()->json(['error' => 'SKU not found.'], 404);
        }
        DB::table('ebay_data_view')
            ->where('sku', $sku)
            ->update(['fba' => $fbaStatus]);
        $updatedData = DB::table('ebay_data_view')
            ->where('sku', $sku)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'FBA status updated successfully.',
            'data' => $updatedData
        ]);
    }

    public function getViewEbayData(Request $request)
    {
        // 1. Base ProductMaster fetch
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

            $productMasters = $productMasters->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })->values();


        // 2. SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

            $nonParentSkus = $skus;

        // 3. Related Models
        $shopifyData = ShopifySku::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");

        $ebayMetrics = EbayMetric::select(
                'sku',
                'ebay_l30',
                'ebay_l60',
                'ebay_l7',
                'ebay_price',
                'views',
                'l7_views',
                'item_id',
                'ebay_stock'
            )
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        // Fetch Amazon prices for comparison
        $amazonPrices = AmazonDatasheet::whereIn('sku', $skus)
            ->pluck('price', 'sku');

        // Prioritize COST_PER_SALE rows for bid_percentage (matching PMP Ads controller)
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
            ->keyBy('listing_id')
            ->toArray();

        // Latest NR/REQ + links from Listing eBay page (source of truth)
        $nrValues = EbayDataView::whereIn("sku", $skus)->pluck("value", "sku");
        
        // Legacy listing status data for nr_req field (used as fallback)
        // Key listing status by lowercase SKU for case-insensitive lookup (UI sends upper/lower mixed)
        $listingStatusData = EbayListingStatus::whereIn("sku", $skus)
            ->get()
            ->mapWithKeys(function ($item) {
                return [strtolower($item->sku) => $item];
            });

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        // Fetch L7 and L1 campaign reports for KW Ads columns
        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        // Fetch last_sbid from day-before-yesterday records (for KW Ads LBID column)
        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $lastSbidReports = EbayPriorityReport::where('report_range', $dayBeforeYesterday)
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $lastSbidMap = [];
        foreach ($lastSbidReports as $report) {
            if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                $lastSbidMap[$report->campaign_id] = $report->last_sbid;
            }
        }

        // Fetch sbid_m from yesterday's records first, then L1 as fallback
        $sbidMReports = EbayPriorityReport::where(function($q) use ($yesterday) {
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

        $sbidMMap = [];
        foreach ($sbidMReports as $campaignId => $reports) {
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->sbid_m)) {
                $sbidMMap[$report->campaign_id] = $report->sbid_m;
            }
        }

        // Fetch apprSbid from yesterday's records first, then L1 as fallback
        $apprSbidReports = EbayPriorityReport::where(function($q) use ($yesterday) {
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

        $apprSbidMap = [];
        foreach ($apprSbidReports as $campaignId => $reports) {
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->apprSbid)) {
                $apprSbidMap[$report->campaign_id] = $report->apprSbid;
            }
        }

        $itemIds = $ebayMetrics->pluck('item_id')->toArray();
        $ebayGeneralReportsL30 = EbayGeneralReport::where('report_range', 'L30')
            ->whereIn('listing_id', $itemIds)
            ->get();

        // Fetch L7 general reports for PMT Ads section
        $ebayGeneralReportsL7 = EbayGeneralReport::where('report_range', 'L7')
            ->whereIn('listing_id', $itemIds)
            ->get();

        // Build item_id → SKU map from ebayMetrics (matching PMP Ads controller logic)
        $itemIdToSkuMap = [];
        foreach ($ebayMetrics as $metric) {
            if (!empty($metric->item_id)) {
                $itemIdToSkuMap[$metric->item_id] = strtoupper($metric->sku);
            }
        }

        // Aggregate PMT metrics by SKU from ALL general reports (matching PMP Ads adMetricsBySku)
        $pmtAdMetricsBySku = [];
        foreach ($ebayGeneralReportsL30 as $report) {
            $reportSku = $itemIdToSkuMap[$report->listing_id] ?? null;
            if (!$reportSku) continue;
            $pmtAdMetricsBySku[$reportSku]['Clk'] = ($pmtAdMetricsBySku[$reportSku]['Clk'] ?? 0) + (int) $report->clicks;
            $pmtAdMetricsBySku[$reportSku]['Imp'] = ($pmtAdMetricsBySku[$reportSku]['Imp'] ?? 0) + (int) $report->impressions;
            $pmtAdMetricsBySku[$reportSku]['Sls'] = ($pmtAdMetricsBySku[$reportSku]['Sls'] ?? 0) + (int) $report->sales;
            $pmtAdMetricsBySku[$reportSku]['GENERAL_SPENT'] = ($pmtAdMetricsBySku[$reportSku]['GENERAL_SPENT'] ?? 0) + (float) str_replace('USD ', '', $report->ad_fees ?? 0);
            $pmtAdMetricsBySku[$reportSku]['SALE_AMOUNT'] = ($pmtAdMetricsBySku[$reportSku]['SALE_AMOUNT'] ?? 0) + (float) str_replace('USD ', '', $report->sale_amount ?? 0);
        }

        // Aggregate PMT L7 metrics by SKU
        $pmtAdMetricsBySkuL7 = [];
        foreach ($ebayGeneralReportsL7 as $report) {
            $reportSku = $itemIdToSkuMap[$report->listing_id] ?? null;
            if (!$reportSku) continue;
            $pmtAdMetricsBySkuL7[$reportSku]['Clk'] = ($pmtAdMetricsBySkuL7[$reportSku]['Clk'] ?? 0) + (int) $report->clicks;
            $pmtAdMetricsBySkuL7[$reportSku]['GENERAL_SPENT'] = ($pmtAdMetricsBySkuL7[$reportSku]['GENERAL_SPENT'] ?? 0) + (float) str_replace('USD ', '', $report->ad_fees ?? 0);
        }

        // Extra clicks data by listing_id (matching PMP Ads extraClicksData)
        $extraClicksData = $ebayGeneralReportsL30->pluck('clicks', 'listing_id')->toArray();

        // Fetch LMP data from ebay_sku_competitors table (disconnected from repricer)
        $lmpLowestLookup = collect();
        $lmpDetailsLookup = collect();
        try {
            // Fetch all competitors and group by normalized SKU (handle line breaks, spaces, case)
            $lmpRecords = \App\Models\EbaySkuCompetitor::where('marketplace', 'ebay')
                ->where('total_price', '>', 0)
                ->orderBy('total_price', 'asc')
                ->get()
                ->groupBy(function ($item) {
                    return strtoupper(preg_replace('/\s+/', ' ', trim($item->sku)));
                });

            $lmpDetailsLookup = $lmpRecords;
            $lmpLowestLookup = $lmpRecords->map(function ($items) {
                return $items->first();
            });
        } catch (\Exception $e) {
            Log::warning('Could not fetch LMP data from ebay_sku_competitors: ' . $e->getMessage());
        }

        // 5. Marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1; 
        $adUpdates  = $marketplaceData ? $marketplaceData->ad_updates : 0;   

        // 6. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;
            $ebayMetric = $ebayMetrics[$pm->sku] ?? null;
            $listingStatus = $listingStatusData[strtolower($pm->sku)] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;
            $row['fba'] = $pm->fba;

            // Shopify
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;
            
            // ==== Rating from EbayDataView ====
            $row['rating'] = null;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true) ?? [];
                }
                if (is_array($raw) && isset($raw['rating'])) {
                    $row['rating'] = floatval($raw['rating']);
                }
            }
            
            // ==== NRL/REQ + Links ====
            // Default values
            $row['nr_req'] = 'REQ';
            $row['B Link'] = '';
            $row['S Link'] = '';

            // 1) Prefer data from EbayDataView (Listing eBay page) via NRL field
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];

                if (!is_array($raw)) {
                    $raw = json_decode($raw, true) ?? [];
                }

                if (is_array($raw)) {
                    // NRL mapping: 'NRL' => NRL, 'REQ' => REQ
                    $nrlValue = $raw['NRL'] ?? null;
                    if ($nrlValue === 'NRL') {
                        $row['nr_req'] = 'NRL';
                    } elseif ($nrlValue === 'REQ') {
                        $row['nr_req'] = 'REQ';
                    }

                    // Buyer / Seller links from Listing eBay if present
                    if (!empty($raw['buyer_link'])) {
                        $row['B Link'] = $raw['buyer_link'];
                    }
                    if (!empty($raw['seller_link'])) {
                        $row['S Link'] = $raw['seller_link'];
                    }
                }
            }

            // 2) Fallback: Only use EbayListingStatus for buyer/seller links (not for nr_req)
            // nr_req should ONLY come from EbayDataView to match listingEbay page
            if ($listingStatus) {
                $statusValue = is_array($listingStatus->value)
                    ? $listingStatus->value
                    : (json_decode($listingStatus->value, true) ?? []);

                // Only use links from EbayListingStatus if not already set from EbayDataView
                if (empty($row['B Link']) && !empty($statusValue['buyer_link'])) {
                    $row['B Link'] = $statusValue['buyer_link'];
                }
                if (empty($row['S Link']) && !empty($statusValue['seller_link'])) {
                    $row['S Link'] = $statusValue['seller_link'];
                }
            }

            // eBay Metrics
            $row["eBay L30"] = $ebayMetric->ebay_l30 ?? 0;
            $row["eBay L60"] = $ebayMetric->ebay_l60 ?? 0;
            $row["eBay L7"] = $ebayMetric->ebay_l7 ?? 0;
            $row["eBay Price"] = $ebayMetric->ebay_price ?? 0;
            $row['eBay Stock'] = $ebayMetric->ebay_stock ?? 0;
            $row['price_lmpa'] = $ebayMetric->price_lmpa ?? null;
            $row['eBay_item_id'] = $ebayMetric->item_id ?? null;
            $row['views'] = $ebayMetric->views ?? 0;

            // Amazon Price for comparison
            $row['A Price'] = isset($amazonPrices[$pm->sku]) ? floatval($amazonPrices[$pm->sku]) : 0;

            // Get bid percentage from campaign listings
            if ($ebayMetric && isset($campaignListings[$ebayMetric->item_id])) {
                $row['bid_percentage'] = $campaignListings[$ebayMetric->item_id]->bid_percentage ?? null;
                $row['suggested_bid'] = $campaignListings[$ebayMetric->item_id]->suggested_bid ?? null;
            } else {
                $row['bid_percentage'] = null;
                $row['suggested_bid'] = null;
            }

            // LMP data - lowest entry plus all competitors
            // Use uppercase and trimmed SKU for lookup (case-insensitive)
            $skuLookupKey = strtoupper(trim($pm->sku));
            $lmpEntries = $lmpDetailsLookup->get($skuLookupKey);
            if (!$lmpEntries instanceof \Illuminate\Support\Collection) {
                $lmpEntries = collect();
            }

            $lowestLmp = $lmpLowestLookup->get($skuLookupKey);
            $row['lmp_price'] = ($lowestLmp && isset($lowestLmp->total_price))
                ? (is_numeric($lowestLmp->total_price) ? floatval($lowestLmp->total_price) : null)
                : null;
            $row['lmp_link'] = $lowestLmp->product_link ?? null;
            $row['lmp_item_id'] = $lowestLmp->item_id ?? null;
            $row['lmp_title'] = $lowestLmp->product_title ?? null;
            $row['lmp_entries'] = $lmpEntries
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'item_id' => $entry->item_id,
                        'price' => floatval($entry->price ?? 0),
                        'shipping_cost' => floatval($entry->shipping_cost ?? 0),
                        'total_price' => floatval($entry->total_price ?? 0),
                        'link' => $entry->product_link,
                        'title' => $entry->product_title,
                    ];
                })
                ->toArray();
            $row['lmp_entries_total'] = $lmpEntries->count();

            $row["E Dil%"] = ($row["eBay L30"] && $row["INV"] > 0)
                ? round(($row["eBay L30"] / $row["INV"]), 2)
                : 0;

            $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });

            $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebayMetric) {
                if (!$ebayMetric || empty($ebayMetric->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebayMetric->item_id);
            });

            $matchedGeneralL7 = $ebayGeneralReportsL7->first(function ($item) use ($ebayMetric) {
                if (!$ebayMetric || empty($ebayMetric->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebayMetric->item_id);
            });

            // Keyword campaign
            $kw_spend_l30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $kw_sales_l30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0);
            $kw_sold_l30  = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);

            // General ads (PMT) - use aggregated data matching PMP Ads controller
            $skuUpper = strtoupper($sku);
            $pmtMetrics = $pmtAdMetricsBySku[$skuUpper] ?? [];
            $pmt_spend_l30 = $pmtMetrics['GENERAL_SPENT'] ?? (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
            $pmt_sales_l30 = $pmtMetrics['SALE_AMOUNT'] ?? (float) str_replace('USD ', '', $matchedGeneralL30->sale_amount ?? 0);
            $pmt_sold_l30  = $pmtMetrics['Sls'] ?? (int) ($matchedGeneralL30->sales ?? 0);
            $pmt_impressions_l30 = $pmtMetrics['Imp'] ?? (int) ($matchedGeneralL30->impressions ?? 0);

            // PMT Clicks L30: aggregated SKU clicks + extra clicks from primary listing (matching PMP formula)
            $pmt_clicks_l30 = $pmtMetrics['Clk'] ?? 0;
            if ($ebayMetric && isset($extraClicksData[$ebayMetric->item_id])) {
                $pmt_clicks_l30 += (int) $extraClicksData[$ebayMetric->item_id];
            }

            // PMT L7 data - use aggregated data matching PMP Ads controller
            $pmtMetricsL7 = $pmtAdMetricsBySkuL7[$skuUpper] ?? [];
            $pmt_clicks_l7 = $pmtMetricsL7['Clk'] ?? (int) ($matchedGeneralL7->clicks ?? 0);
            $pmt_spend_l7 = $pmtMetricsL7['GENERAL_SPENT'] ?? (float) str_replace('USD ', '', $matchedGeneralL7->ad_fees ?? 0);

            // Final AD totals
            $AD_Spend_L30 = $kw_spend_l30 + $pmt_spend_l30;
            $AD_Sales_L30 = $kw_sales_l30 + $pmt_sales_l30;
            $AD_Units_L30 = $kw_sold_l30 + $pmt_sold_l30;

            $row["AD_Spend_L30"] = round($AD_Spend_L30, 2);
            $row["kw_spend_L30"] = round($kw_spend_l30, 2);
            $row["pmt_spend_L30"] = round($pmt_spend_l30, 2);
            $row["AD_Sales_L30"] = round($AD_Sales_L30, 2);
            $row["AD_Units_L30"] = $AD_Units_L30;

            // === PMT Ads section data ===
            $row['pmt_clicks_l30'] = $pmt_clicks_l30;
            $row['pmt_clicks_l7'] = $pmt_clicks_l7;
            $row['pmt_impressions_l30'] = $pmt_impressions_l30;
            $row['pmt_sold_l30'] = $pmt_sold_l30;
            $row['pmt_sales_l30'] = round($pmt_sales_l30, 2);
            $row['pmt_spend_l7'] = round($pmt_spend_l7, 2);

            // === KW Ads section data ===
            $row['l7_views'] = $ebayMetric->l7_views ?? 0;

            // Match L7 and L1 campaign reports
            $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });
            $matchedCampaignL1 = $ebayCampaignReportsL1->first(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });

            // KW Campaign budget
            $row['kw_campaignBudgetAmount'] = (float) ($matchedCampaignL30->campaignBudgetAmount ?? 0);
            $row['kw_campaignStatus'] = $matchedCampaignL30->campaignStatus ?? '';
            $row['kw_campaign_id'] = $matchedCampaignL30->campaign_id ?? '';

            // KW L30 clicks and ad_sold
            $kw_clicks_l30 = (int) ($matchedCampaignL30->cpc_clicks ?? 0);
            $row['kw_clicks'] = $kw_clicks_l30;
            $row['kw_ad_sold'] = $kw_sold_l30;

            // KW ACOS
            $row['kw_acos'] = $kw_sales_l30 > 0
                ? round(($kw_spend_l30 / $kw_sales_l30) * 100, 2)
                : ($kw_spend_l30 > 0 ? 100 : 0);

            // KW CVR (ad_sold / clicks * 100)
            $row['kw_cvr'] = $kw_clicks_l30 > 0
                ? round(($kw_sold_l30 / $kw_clicks_l30) * 100, 2)
                : 0;

            // KW L7 spend and CPC
            $kw_l7_spend = $matchedCampaignL7
                ? (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0')
                : 0;
            $kw_l7_cpc = $matchedCampaignL7
                ? (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cost_per_click ?? '0')
                : 0;
            $row['kw_l7_spend'] = round($kw_l7_spend, 2);
            $row['kw_l7_cpc'] = round($kw_l7_cpc, 2);

            // KW L1 spend and CPC
            $kw_l1_spend = $matchedCampaignL1
                ? (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0')
                : 0;
            $kw_l1_cpc = $matchedCampaignL1
                ? (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cost_per_click ?? '0')
                : 0;
            $row['kw_l1_spend'] = round($kw_l1_spend, 2);
            $row['kw_l1_cpc'] = round($kw_l1_cpc, 2);

            // KW last_sbid, sbid_m, apprSbid
            $kwCampaignId = $row['kw_campaign_id'];
            $row['kw_last_sbid'] = isset($lastSbidMap[$kwCampaignId]) ? $lastSbidMap[$kwCampaignId] : '';
            $row['kw_sbid_m'] = isset($sbidMMap[$kwCampaignId]) ? $sbidMMap[$kwCampaignId] : '';
            $row['kw_apprSbid'] = isset($apprSbidMap[$kwCampaignId]) ? $apprSbidMap[$kwCampaignId] : '';

            // AD% Formula = (spend_l30 / (price * ebay_l30)) * 100
            $price = floatval($row["eBay Price"] ?? 0);
            $ebay_l30 = floatval($row["eBay L30"] ?? 0);
            $totalRevenue = $price * $ebay_l30;

            $row["AD%"] = $totalRevenue > 0
                ? round(($AD_Spend_L30 / $totalRevenue) * 100, 4)
                : 0;


            // Initialize ad metrics with zero values since we're using EbayMetric data
            foreach (['L60', 'L30', 'L7'] as $range) {
                foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT', 'PRIORITY_SPENT'] as $suffix) {
                    $key = "Pmt{$suffix}{$range}";
                    $row[$key] = 0;
                }
            }

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

            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // Price and units for calculations
            $price = floatval($row["eBay Price"] ?? 0);

            $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);

            // Profit/Sales
            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["Profit"] = $row["Total_pft"]; // Add this for frontend compatibility
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["Sales L30"] = $row["T_Sale_l30"]; // Add this for frontend compatibility
            
            // Tacos Formula: TOTAL AD SPENT / TOTAL SALES
            // Total Sales = eBay L30 * eBay Price
            $totalSales = $row["T_Sale_l30"]; // Already calculated as price * units_ordered_l30
            $row["TacosL30"] = $totalSales > 0 ? round($AD_Spend_L30 / $totalSales, 4) : 0;
            
            // Calculate GPFT%
            $gpft = $price > 0 ? (($price * $percentage - $ship - $lp) / $price) * 100 : 0;
            
            // PFT% = GPFT% - AD%
            $row["PFT %"] = round($gpft - $row["AD%"], 2);
            $totalPercentage = $percentage + $adUpdates; 

            // ROI% = ((price * percentage - lp - ship) / lp) * 100
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );


            $row["GPFT%"] = round(
                $price  > 0 ? (($price * $percentage - $ship - $lp) / $price) * 100 : 0,
                2
            );
            $row["percentage"] = $percentage;
            $row['ad_updates'] = $adUpdates;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;

            // Calculate SCVR (CVR): (eBay L30 / views) * 100
            $views = floatval($row['views'] ?? 0);
            $ebayL30 = floatval($row["eBay L30"] ?? 0);
            $row['SCVR'] = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0;
            $cvr = $row['SCVR']; // Use SCVR for SPRICE calculation

            // NR & Hide (load from database, but not SPRICE/SPFT/SROI/SGPFT)
            $row['NR'] = "";
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;
            $row['spend_l30'] = null;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['NRL'] = $raw['NRL'] ?? null;
                    // Don't load SPRICE, SPFT, SROI, SGPFT from database - always calculate
                    $row['spend_l30'] = $raw['Spend_L30'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }
            
            // Always calculate SPRICE based on CVR (ignore saved values)
            $calculatedSprice = null;
            if ($price > 0) {
                // Determine multiplier based on CVR
                if ($cvr >= 0 && $cvr <= 1) {
                    // 0-1%: multiply by 0.99
                    $spriceMultiplier = 0.99;
                } elseif ($cvr > 1 && $cvr <= 3) {
                    // 1%-3%: multiply by 0.995
                    $spriceMultiplier = 0.995;
                } else {
                    // >3%: increase by 1% (multiply by 1.01)
                    $spriceMultiplier = 1.01;
                }
                
                $calculatedSprice = round($price * $spriceMultiplier, 2);
                
                // Check if there's a saved SPRICE that differs from calculated
                $savedSprice = null;
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw) && isset($raw['SPRICE'])) {
                        $savedSprice = floatval($raw['SPRICE']);
                    }
                }
                
                // Check for SPRICE_STATUS in database (pushed/applied/error)
                $savedStatus = null;
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw) && isset($raw['SPRICE_STATUS'])) {
                        $savedStatus = $raw['SPRICE_STATUS'];
                    }
                }
                
                // Use saved SPRICE if it exists, otherwise use calculated
                if ($savedSprice !== null && abs($savedSprice - $calculatedSprice) > 0.01) {
                    $row['SPRICE'] = $savedSprice;
                    $row['has_custom_sprice'] = true;
                    // Use saved status if exists (pushed/applied/error), otherwise 'saved'
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
                
                // Calculate SROI: ((SPRICE * percentage - lp - ship) / lp) * 100
                $row['SROI'] = round(
                    $lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0,
                    2
                );
            } else {
                // If price is 0, set all to null/0
                $row['SPRICE'] = null;
                $row['SPFT'] = null;
                $row['SROI'] = null;
                $row['SGPFT'] = null;
                $row['has_custom_sprice'] = false;
                $row['SPRICE_STATUS'] = null;
            }

            // Image
            $row["image_path"] = $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null));

            $result[] = (object) $row;
        }

        return response()->json([
            "message" => "eBay Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }


    // Helper function
    private function extractNumber($value)
    {
        if (empty($value)) return 0;
        return (float) preg_replace('/[^\d.]/', '', $value);
    }


    public function updateAllEbaySkus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');

            // Current record fetch
            $marketplace = MarketplacePercentage::where('marketplace', 'Ebay')->first();

            $percent = $marketplace->percentage ?? 0;
            $adUpdates = $marketplace->ad_updates ?? 0;

            // Handle percentage update
            if ($type === 'percentage') {
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return response()->json(['status' => 400, 'message' => 'Invalid percentage value'], 400);
                }
                $percent = $value;
            }

            // Handle ad_updates update
            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json(['status' => 400, 'message' => 'Invalid ad_updates value'], 400);
                }
                $adUpdates = $value;
            }

            // Save both fields
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

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $skus = $request->input("skus");
        $hideValues = $request->input("hideValues"); // <-- add this
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
                $ebayDataView = EbayDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?:
                        []);
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
                $ebayDataView = EbayDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?:
                        []);
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

        $ebayDataView = EbayDataView::firstOrNew(["sku" => $sku]);
        $value = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?:
                []);

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


    public function saveSpriceToDatabase(Request $request)
    {
        Log::info('Saving eBay pricing data', $request->all());
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            Log::error('SKU or sprice missing', ['sku' => $sku, 'sprice' => $sprice]);
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        // Get current marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
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

        // Calculate profit
        $spriceFloat = floatval($sprice);
        $profit = ($spriceFloat * $percentage - $lp - $ship);

        // Calculate SGPFT first - use marketplace percentage instead of hardcoded 0.86
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * $percentage - $ship - $lp) / $spriceFloat) * 100, 2) : 0;
        
        // Get AD% from the product
        $adPercent = 0;
        $ebayMetric = EbayMetric::where('sku', $sku)
            ->first();
        
        if ($ebayMetric) {
            $campaignListings = DB::connection('apicentral')
                ->table('ebay_campaign_ads_listings')
                ->where('listing_id', $ebayMetric->item_id)
                ->first();
            
            $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
                ->where('campaign_name', 'LIKE', '%' . $sku . '%')
                ->first();
            
            $ebayGeneralReportsL30 = EbayGeneralReport::where('report_range', 'L30')
                ->where('listing_id', $ebayMetric->item_id)
                ->first();
            
            $kw_spend_l30 = (float) str_replace('USD ', '', $ebayCampaignReportsL30->cpc_ad_fees_payout_currency ?? 0);
            $kw_sales_l30 = (float) str_replace('USD ', '', $ebayCampaignReportsL30->cpc_sale_amount_payout_currency ?? 0);
            $pmt_spend_l30 = (float) str_replace('USD ', '', $ebayGeneralReportsL30->ad_fees ?? 0);
            $pmt_sales_l30 = (float) str_replace('USD ', '', $ebayGeneralReportsL30->sale_amount ?? 0);
            
            $adDenominator = $kw_sales_l30 + $pmt_sales_l30;
            $adPercent = $adDenominator > 0 ? (($kw_spend_l30 + $pmt_spend_l30) / $adDenominator) * 100 : 0;
        }
        
        // SPFT = SGPFT - AD%
        $spft = round($sgpft - $adPercent, 2);
        // SROI = ((SPRICE * (percentage - AD%/100) - ship - lp) / lp) * 100
        $adDecimal = $adPercent / 100;
        $sroi = round(
            $lp > 0 ? (($spriceFloat * ($percentage - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
            2
        );
        Log::info('Calculated values', ['sprice' => $spriceFloat, 'sgpft' => $sgpft, 'ad_percent' => $adPercent, 'spft' => $spft, 'sroi' => $sroi]);

        $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
            'SGPFT' => $sgpft,
        ]);

        $ebayDataView->value = $merged;
        $ebayDataView->save();
        Log::info('Data saved successfully', ['sku' => $sku]);

        return response()->json([
            'message' => 'Data saved successfully.',
            'spft_percent' => $spft,
            'sroi_percent' => $sroi,
            'sgpft_percent' => $sgpft
        ]);
    }

    public function updateListedLive(Request $request)
    {
        // Handle NRL updates
        if ($request->has('nr_req')) {
            Log::info('NRL Update Request', $request->all());
            
            $request->validate([
                'sku'    => 'required|string',
                'nr_req' => 'required|in:REQ,NR,LATER',
            ]);

            // Update EbayListingStatus for NRL
            $listingStatus = EbayListingStatus::firstOrCreate(
                ['sku' => $request->sku],
                ['value' => []]
            );

            $currentValue = is_array($listingStatus->value)
                ? $listingStatus->value
                : (json_decode($listingStatus->value, true) ?? []);

            $currentValue['nr_req'] = $request->nr_req;
            $listingStatus->value = $currentValue;
            $listingStatus->save();

            Log::info('NRL Update Success', ['sku' => $request->sku, 'nr_req' => $request->nr_req]);
            return response()->json(['success' => true]);
        }

        // Original validation for Listed/Live
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = EbayDataView::firstOrCreate(
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

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'eBay')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function importEbayAnalytics(Request $request)
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
                EbayDataView::updateOrCreate(
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

    public function exportEbayAnalytics()
    {
        try {
            $ebayData = EbayDataView::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('eBay Analytics');

            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('eBay Analytics System')
                ->setTitle('eBay Analytics Export')
                ->setSubject('eBay Listing Data')
                ->setDescription('Export of eBay listing status data');

            // Header Row with styling
            $headers = ['SKU', 'Listed', 'Live'];
            $sheet->fromArray($headers, NULL, 'A1');

            // Style header row
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ];
            $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

            // Data Rows
            $rowIndex = 2;
            foreach ($ebayData as $data) {
                $values = is_array($data->value)
                    ? $data->value
                    : (json_decode($data->value, true) ?? []);

                // Convert boolean values to proper Excel format
                $listed = isset($values['Listed']) ? ($values['Listed'] ? 'TRUE' : 'FALSE') : 'FALSE';
                $live = isset($values['Live']) ? ($values['Live'] ? 'TRUE' : 'FALSE') : 'FALSE';

                $sheet->setCellValue('A' . $rowIndex, $data->sku);
                $sheet->setCellValue('B' . $rowIndex, $listed);
                $sheet->setCellValue('C' . $rowIndex, $live);

                // Apply data row styling
                $dataStyle = [
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ]
                ];
                $sheet->getStyle('A' . $rowIndex . ':C' . $rowIndex)->applyFromArray($dataStyle);

                // Alternate row colors
                if ($rowIndex % 2 == 0) {
                    $sheet->getStyle('A' . $rowIndex . ':C' . $rowIndex)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('F8F9FA'));
                }

                $rowIndex++;
            }

            // Set column widths and formatting
            $sheet->getColumnDimension('A')->setWidth(25);
            $sheet->getColumnDimension('B')->setWidth(12);
            $sheet->getColumnDimension('C')->setWidth(12);

            // Auto-filter for headers
            $sheet->setAutoFilter('A1:C' . ($rowIndex - 1));

            // Freeze header row
            $sheet->freezePane('A2');

            // Generate filename with timestamp
            $fileName = 'Ebay_Analytics_Export_' . date('Y-m-d_H-i-s') . '.xlsx';

            // Clear any output buffer
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Set proper headers for Excel download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            
            // Clean up memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
            exit;
            
        } catch (\Exception $e) {
            Log::error('Error exporting eBay analytics: ' . $e->getMessage());
            return back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }

    public function downloadSample()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Sample Data');

            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('eBay Analytics System')
                ->setTitle('eBay Analytics Sample')
                ->setSubject('Sample Import Format')
                ->setDescription('Sample file showing correct format for eBay analytics import');

            // Header Row
            $headers = ['SKU', 'Listed', 'Live'];
            $sheet->fromArray($headers, NULL, 'A1');

            // Style header row
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ];
            $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

            // Sample Data with proper cell setting
            $sampleData = [
                ['SKU001', 'TRUE', 'FALSE'],
                ['SKU002', 'FALSE', 'TRUE'],
                ['SKU003', 'TRUE', 'TRUE'],
                ['SKU004', 'FALSE', 'FALSE'],
                ['SKU005', 'TRUE', 'TRUE'],
            ];

            $rowIndex = 2;
            foreach ($sampleData as $row) {
                $sheet->setCellValue('A' . $rowIndex, $row[0]);
                $sheet->setCellValue('B' . $rowIndex, $row[1]);
                $sheet->setCellValue('C' . $rowIndex, $row[2]);

                // Apply styling to data rows
                $dataStyle = [
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ]
                ];
                $sheet->getStyle('A' . $rowIndex . ':C' . $rowIndex)->applyFromArray($dataStyle);

                $rowIndex++;
            }

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(25);
            $sheet->getColumnDimension('B')->setWidth(12);
            $sheet->getColumnDimension('C')->setWidth(12);

            // Auto-filter for headers
            $sheet->setAutoFilter('A1:C' . ($rowIndex - 1));

            // Freeze header row
            $sheet->freezePane('A2');

            // Add instructions in a comment
            $sheet->getComment('A1')->getText()->createTextRun('Instructions: Use TRUE/FALSE for Listed and Live columns. SKU must match existing products.');

            // Clear any output buffer
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Output Download
            $fileName = 'Ebay_Analytics_Sample_' . date('Y-m-d') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            
            // Clean up memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
            exit;
            
        } catch (\Exception $e) {
            Log::error('Error downloading sample file: ' . $e->getMessage());
            return back()->with('error', 'Failed to download sample: ' . $e->getMessage());
        }
    }

    public function getEbayColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setEbayColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay_tabulator_column_visibility_{$userId}";
        
        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    public function exportEbayPricingData(Request $request)
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
                '(Child) sku' => ['SKU', function($item) { return $item['(Child) sku'] ?? ''; }],
                'INV' => ['INV', function($item) { return $item['INV'] ?? 0; }],
                'L30' => ['L30', function($item) { return $item['L30'] ?? 0; }],
                'E Dil%' => ['Dil%', function($item) { 
                    return ($item['INV'] > 0) ? round(($item['L30'] / $item['INV']) * 100, 2) : 0; 
                }],
                'eBay L30' => ['eBay L30', function($item) { return $item['eBay L30'] ?? 0; }],
                'eBay L60' => ['eBay L60', function($item) { return $item['eBay L60'] ?? 0; }],
                'eBay Price' => ['eBay Price', function($item) { return number_format($item['eBay Price'] ?? 0, 2); }],
                'lmp_price' => ['LMP', function($item) { return $item['lmp_price'] ? number_format($item['lmp_price'], 2) : ''; }],
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
                'SGPFT' => ['SGPFT', function($item) { return $item['SGPFT'] ? number_format($item['SGPFT'], 0) : ''; }],
                'SCVR' => ['SCVR', function($item) { return number_format($item['SCVR'] ?? 0, 1); }],
                'kw_spend_L30' => ['KW Spend L30', function($item) { return number_format($item['kw_spend_L30'] ?? 0, 2); }],
                'pmt_spend_L30' => ['PMT Spend L30', function($item) { return number_format($item['pmt_spend_L30'] ?? 0, 2); }],
            ];

            // If no columns selected, export all
            if (empty($selectedColumns)) {
                $selectedColumns = array_keys($columnMap);
            }

            // Filter column map to only selected columns
            $selectedColumnMap = array_intersect_key($columnMap, array_flip($selectedColumns));

            // Set headers for CSV download
            $fileName = 'eBay_Pricing_Data_' . date('Y-m-d_H-i-s') . '.csv';
            
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
            Log::error('Error exporting eBay pricing data: ' . $e->getMessage());
            return back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }

    public function getMetricsHistory(Request $request)
    {
        $days = $request->input('days', 30); // Default to last 30 days
        $sku = $request->input('sku'); // Optional SKU filter
        
        // Ensure minimum 7 days if pulling from today
        $minDays = 7;
        if ($days < $minDays) {
            $days = $minDays;
        }
        
        // Use California timezone (America/Los_Angeles) - show data up to and including today in California
        $californiaToday = Carbon::now('America/Los_Angeles')->startOfDay();
        $endDate = $californiaToday; // Today in California time (e.g., Dec 18 when it's Dec 18 in California)
        $startDate = $endDate->copy()->subDays($days - 1); // Go back $days from end date
        
        $chartData = [];
        $dataByDate = []; // Store data by date for filling gaps
        
        try {
            // Try to use the new table for JSON format data
            $query = EbaySkuDailyData::where('record_date', '>=', $startDate)
                ->where('record_date', '<=', $endDate)
                ->orderBy('record_date', 'asc');
            
            // If SKU is provided, return data for specific SKU
            if ($sku) {
                $metricsData = $query->where('sku', strtoupper(trim($sku)))->get();
                
                foreach ($metricsData as $record) {
                    $data = $record->daily_data;
                    $dateKey = Carbon::parse($record->record_date)->format('Y-m-d');
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => Carbon::parse($record->record_date)->format('M d'),
                        'price' => round($data['price'] ?? 0, 2),
                        'views' => $data['views'] ?? 0,
                        'cvr_percent' => round($data['cvr_percent'] ?? 0, 2),
                        'ad_percent' => round($data['ad_percent'] ?? 0, 2),
                    ];
                }
            } else {
                // Aggregate data for all SKUs
                $metricsData = $query->get()->groupBy('record_date');
                
                foreach ($metricsData as $date => $records) {
                    $dateKey = Carbon::parse($date)->format('Y-m-d');
                    
                    // Calculate weighted average price (same as summary badge: price * ebay_l30 / sum ebay_l30)
                    $totalWeightedPrice = 0;
                    $totalL30 = 0;
                    foreach ($records as $record) {
                        $price = floatval($record->daily_data['price'] ?? 0);
                        $ebayL30 = floatval($record->daily_data['ebay_l30'] ?? 0);
                        $totalWeightedPrice += $price * $ebayL30;
                        $totalL30 += $ebayL30;
                    }
                    $avgPrice = $totalL30 > 0 ? ($totalWeightedPrice / $totalL30) : 0;
                    
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => Carbon::parse($date)->format('M d'),
                        'avg_price' => round($avgPrice, 2),
                        'total_views' => $records->sum(function($r) { return $r->daily_data['views'] ?? 0; }),
                        'avg_cvr_percent' => round($records->avg(function($r) { return $r->daily_data['cvr_percent'] ?? 0; }), 2),
                        'avg_ad_percent' => round($records->avg(function($r) { return $r->daily_data['ad_percent'] ?? 0; }), 2),
                    ];
                }
            }
            
            // If no data found in new table, try fallback to ebay_one_metrics
            if (empty($dataByDate)) {
                throw new \Exception('No data in new table, trying fallback');
            }
            
        } catch (\Exception $e) {
            // Fallback: Since EbayMetric doesn't have historical daily data,
            // we'll just return empty data and let the frontend handle it
            Log::info('No eBay daily metrics data available. Historical data will be populated by metrics collection command.');
        }

        // Fill in missing dates with zero values to ensure at least 7 days
        $currentDate = Carbon::parse($startDate);
        
        while ($currentDate->lte($endDate)) {
            $dateKey = $currentDate->format('Y-m-d');
            
            if (!isset($dataByDate[$dateKey])) {
                // Fill missing date with zero values
                if ($sku) {
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => $currentDate->format('M d'),
                        'price' => 0,
                        'views' => 0,
                        'cvr_percent' => 0,
                        'ad_percent' => 0,
                    ];
                } else {
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => $currentDate->format('M d'),
                        'avg_price' => 0,
                        'total_views' => 0,
                        'avg_cvr_percent' => 0,
                        'avg_ad_percent' => 0,
                    ];
                }
            }
            
            $currentDate->addDay();
        }
        
        // Sort by date and convert to array
        ksort($dataByDate);
        $chartData = array_values($dataByDate);

        return response()->json($chartData);
    }

    public function pushEbayPrice(Request $request)
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

        // Validate price range (e.g., between 0.01 and 10000)
        if ($priceFloat < 0.01 || $priceFloat > 10000) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'Price must be between $0.01 and $10,000.']]
            ], 400);
        }

        // Ensure price has max 2 decimal places
        $priceFloat = round($priceFloat, 2);

        try {
            // Get item_id from EbayMetric
            $ebayMetric = EbayMetric::where('sku', $sku)
                ->first();

            if (!$ebayMetric || !$ebayMetric->item_id) {
                $this->saveSpriceStatus($sku, 'error');
                Log::error('eBay item_id not found', ['sku' => $sku]);
                return response()->json([
                    'errors' => [['code' => 'NotFound', 'message' => 'eBay listing not found for SKU: ' . $sku]]
                ], 404);
            }

            // Push price to eBay using EbayApiService
            $ebayService = new EbayApiService();
            $result = $ebayService->reviseFixedPriceItem($ebayMetric->item_id, $priceFloat);

            if (isset($result['success']) && $result['success']) {
                $this->saveSpriceStatus($sku, 'pushed');
                Log::info('eBay price update successful', ['sku' => $sku, 'price' => $priceFloat, 'item_id' => $ebayMetric->item_id]);
                return response()->json(['success' => true, 'message' => 'Price updated successfully']);
            } else {
                // Check if account is restricted (don't save as error for retry - it won't help)
                $isAccountRestricted = isset($result['accountRestricted']) && $result['accountRestricted'];
                
                if ($isAccountRestricted) {
                    // Save with special status to prevent background retries
                    $this->saveSpriceStatus($sku, 'account_restricted');
                } else {
                    $this->saveSpriceStatus($sku, 'error');
                }
                
                $errors = $result['errors'] ?? [['code' => 'UnknownError', 'message' => 'Failed to update price']];
                
                // Check for Lvis error and provide user-friendly message
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
                                $paramMessages[] = strip_tags($param['Value']); // Remove HTML tags
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
                            // This is just a warning, not a blocking error
                            Log::warning('eBay business policy warning (non-blocking)', [
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
                
                Log::error('eBay price update failed', [
                    'sku' => $sku,
                    'price' => $priceFloat,
                    'item_id' => $ebayMetric->item_id,
                    'errors' => $errors,
                    'hasLvisError' => $hasLvisError
                ]);
                
                return response()->json(['errors' => $errorMessages], 400);
            }
        } catch (\Exception $e) {
            $this->saveSpriceStatus($sku, 'error');
            Log::error('Exception in pushEbayPrice', ['sku' => $sku, 'price' => $priceFloat, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['errors' => [['code' => 'Exception', 'message' => 'An error occurred: ' . $e->getMessage()]]], 500);
        }
    }

    private function saveSpriceStatus($sku, $status)
    {
        try {
            $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);
            
            $existing = is_array($ebayDataView->value)
                ? $ebayDataView->value
                : (json_decode($ebayDataView->value, true) ?: []);

            $merged = array_merge($existing, [
                'SPRICE_STATUS' => $status,
                'SPRICE_STATUS_UPDATED_AT' => now()->toDateTimeString()
            ]);

            $ebayDataView->value = $merged;
            $ebayDataView->save();
        } catch (\Exception $e) {
            Log::error('Error saving SPRICE_STATUS', ['sku' => $sku, 'status' => $status, 'error' => $e->getMessage()]);
        }
    }

    public function updateEbaySpriceStatus(Request $request)
    {
        $sku = strtoupper(trim($request->input('sku')));
        $status = $request->input('status');

        if (empty($sku) || !in_array($status, ['pushed', 'applied', 'error'])) {
            return response()->json(['success' => false, 'error' => 'Invalid SKU or status'], 400);
        }

        $this->saveSpriceStatus($sku, $status);
        return response()->json(['success' => true, 'message' => 'Status updated successfully']);
    }

    public function getEbayAdsSpend()
    {
        try {
            // Get the latest eBay ads spend from marketplace_daily_metrics
            $latestData = DB::table('marketplace_daily_metrics')
                ->where('channel', 'ebay')
                ->orderBy('date', 'desc')
                ->select('date', 'kw_spent', 'pmt_spent')
                ->first();

            return response()->json([
                'success' => true,
                'date' => $latestData->date ?? null,
                'kw_spent' => floatval($latestData->kw_spent ?? 0),
                'pmt_spent' => floatval($latestData->pmt_spent ?? 0),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching eBay ads spend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch ads spend data'
            ], 500);
        }
    }

    /**
     * Get KW and PMT spend totals from reports (matches KW/PMP ads pages exactly)
     * Uses the same queries as EbayKwAdsController and EbayPMPAdsController
     */
    public function getKwPmtSpendTotals()
    {
        try {
            $thirtyDaysAgo = Carbon::now()->subDays(30)->format('Y-m-d');

            // KW Spend: From ebay_priority_reports (L30 range, recent data only)
            // Matches ChannelMasterController::fetchAdMetricsFromTables() exactly
            $kwSpend = DB::table('ebay_priority_reports')
                ->where('report_range', 'L30')
                ->whereDate('updated_at', '>=', $thirtyDaysAgo)
                ->selectRaw('SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as total_spend')
                ->value('total_spend') ?? 0;

            // PMT Spend: From ebay_general_reports (L30 range, recent data only)
            // Matches ChannelMasterController::fetchAdMetricsFromTables() exactly
            $pmtSpend = DB::table('ebay_general_reports')
                ->where('report_range', 'L30')
                ->whereDate('updated_at', '>=', $thirtyDaysAgo)
                ->selectRaw('SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")) as total_spend')
                ->value('total_spend') ?? 0;

            $totalSpend = round(floatval($kwSpend) + floatval($pmtSpend), 2);

            return response()->json([
                'success' => true,
                'kw_spend' => round(floatval($kwSpend), 2),
                'pmt_spend' => round(floatval($pmtSpend), 2),
                'total_spend' => $totalSpend,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching eBay KW/PMT spend totals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch spend totals'
            ], 500);
        }
    }

    public function updateEbayRating(Request $request)
    {
        $sku = strtoupper(trim($request->input('sku')));
        $rating = $request->input('rating');

        // Validate rating
        if (!is_numeric($rating) || $rating < 0 || $rating > 5) {
            return response()->json([
                'success' => false,
                'error' => 'Rating must be a number between 0 and 5'
            ], 400);
        }

        try {
            // Find or create the data view record
            $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);
            
            // Decode existing value
            $currentValue = is_array($ebayDataView->value)
                ? $ebayDataView->value
                : (json_decode($ebayDataView->value, true) ?? []);
            
            // Update rating
            $currentValue['rating'] = floatval($rating);
            
            // Save
            $ebayDataView->value = $currentValue;
            $ebayDataView->save();

            return response()->json([
                'success' => true,
                'message' => 'Rating updated successfully',
                'rating' => floatval($rating)
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating eBay rating: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error updating rating'
            ], 500);
        }
    }

    public function downloadEbayRatingsSample()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $sheet->setCellValue('A1', 'sku');
            $sheet->setCellValue('B1', 'rating');

            // Add sample data
            $sheet->setCellValue('A2', 'SAMPLE-SKU-001');
            $sheet->setCellValue('B2', '4.5');
            $sheet->setCellValue('A3', 'SAMPLE-SKU-002');
            $sheet->setCellValue('B3', '4.0');
            $sheet->setCellValue('A4', 'SAMPLE-SKU-003');
            $sheet->setCellValue('B4', '3.5');

            // Style header row
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ];
            $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);

            // Auto-size columns
            foreach (range('A', 'B') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Generate file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'ebay_ratings_sample_' . date('Y-m-d') . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error generating eBay ratings sample: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate sample file');
        }
    }

    public function importEbayRatings(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx'
        ]);

        try {
            $file = $request->file('file');
            $imported = 0;
            $skipped = 0;

            // Check if it's CSV or Excel
            $extension = $file->getClientOriginalExtension();

            if ($extension === 'xlsx') {
                // Handle Excel file
                $spreadsheet = IOFactory::load($file->getRealPath());
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                // Remove header
                array_shift($rows);

                foreach ($rows as $row) {
                    if (empty($row[0])) {
                        $skipped++;
                        continue;
                    }

                    $sku = strtoupper(trim($row[0]));
                    $rating = isset($row[1]) ? floatval($row[1]) : null;

                    // Validate rating
                    if ($rating === null || $rating < 0 || $rating > 5) {
                        $skipped++;
                        continue;
                    }

                    // Update or create
                    $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);
                    $currentValue = is_array($ebayDataView->value)
                        ? $ebayDataView->value
                        : (json_decode($ebayDataView->value, true) ?? []);
                    
                    $currentValue['rating'] = $rating;
                    $ebayDataView->value = $currentValue;
                    $ebayDataView->save();

                    $imported++;
                }
            } else {
                // Handle CSV file
                $content = file_get_contents($file->getRealPath());
                $content = preg_replace('/^\x{FEFF}/u', '', $content); // Remove BOM
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
                $csvData = array_map('str_getcsv', explode("\n", $content));
                $csvData = array_filter($csvData, function($row) {
                    return count($row) > 0 && !empty(trim(implode('', $row)));
                });
                
                // Remove header
                array_shift($csvData);

                foreach ($csvData as $row) {
                    $row = array_map('trim', $row);
                    if (empty($row[0])) {
                        $skipped++;
                        continue;
                    }

                    $sku = strtoupper($row[0]);
                    $rating = isset($row[1]) ? floatval($row[1]) : null;

                    // Validate rating
                    if ($rating === null || $rating < 0 || $rating > 5) {
                        $skipped++;
                        continue;
                    }

                    // Update or create
                    $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);
                    $currentValue = is_array($ebayDataView->value)
                        ? $ebayDataView->value
                        : (json_decode($ebayDataView->value, true) ?? []);
                    
                    $currentValue['rating'] = $rating;
                    $ebayDataView->value = $currentValue;
                    $ebayDataView->save();

                    $imported++;
                }
            }

            return response()->json([
                'success' => 'Imported ' . $imported . ' ratings successfully' . 
                            ($skipped > 0 ? ', skipped ' . $skipped . ' invalid rows' : '')
            ]);
        } catch (\Exception $e) {
            Log::error('Error importing eBay ratings: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error importing ratings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-save daily eBay summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            // Uses updateOrCreate so it updates existing record for today
            
            // ALL data with INV > 0 (for grand totals)
            $allData = collect($products)->filter(function($p) {
                return floatval($p['INV'] ?? 0) > 0;
            });
            
            // Filtered data: INV > 0 && nr_req === 'REQ' (for other metrics)
            $filteredData = collect($products)->filter(function($p) {
                $invCheck = floatval($p['INV'] ?? 0) > 0;
                $reqCheck = ($p['nr_req'] ?? '') === 'REQ';
                return $invCheck && $reqCheck;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalSkuCount = $filteredData->count();
            $moreSoldCount = 0;   // eBay L30 > 0
            $zeroSoldCount = 0;   // eBay L30 = 0
            $missingCount = 0;    // No eBay item ID
            $mapCount = 0;        // INV = eBay Stock
            $notMapCount = 0;     // INV != eBay Stock
            $lessAmzCount = 0;    // eBay Price < Amazon Price
            $moreAmzCount = 0;    // eBay Price > Amazon Price
            $prcGtLmpCount = 0;   // eBay Price > LMP Price
            
            $totalPftAmt = 0;
            $totalSalesAmt = 0;
            $totalLpAmt = 0;
            $totalFbaInv = 0;
            $totalEbayL30 = 0;
            $totalWeightedPrice = 0;
            
            // Grand totals (from ALL data)
            $grandTotalKwSpend = 0;
            $grandTotalPmtSpend = 0;
            $grandTotalSpend = 0;
            
            // Calculate grand totals from ALL data (no REQ filter - matches JavaScript)
            foreach ($allData as $row) {
                $grandTotalKwSpend += floatval($row['kw_spend_L30'] ?? 0);
                $grandTotalPmtSpend += floatval($row['pmt_spend_L30'] ?? 0);
                $grandTotalSpend += floatval($row['AD_Spend_L30'] ?? 0);
            }
            
            // Loop through FILTERED data (with REQ filter - matches JavaScript)
            foreach ($filteredData as $row) {
                $inv = floatval($row['INV'] ?? 0);
                $ebayL30 = floatval($row['eBay L30'] ?? 0);
                
                $totalPftAmt += floatval($row['Total_pft'] ?? 0);
                $totalSalesAmt += floatval($row['T_Sale_l30'] ?? 0);
                $totalLpAmt += floatval($row['LP_productmaster'] ?? 0) * $ebayL30;
                $totalFbaInv += $inv;
                $totalEbayL30 += $ebayL30;
                
                // Count sold and 0-sold (EXACT JavaScript logic)
                if ($ebayL30 == 0) {  // Use == for proper float comparison
                    $zeroSoldCount++;
                } else {
                    $moreSoldCount++;
                }
                
                // Count Missing (no eBay item ID)
                $itemId = $row['eBay_item_id'] ?? '';
                if (!$itemId || $itemId === null || $itemId === '') {
                    $missingCount++;
                }
                
                // Count Map and N MP (only if exists in eBay)
                if ($itemId && $itemId !== null && $itemId !== '') {
                    $ebayStock = floatval($row['eBay Stock'] ?? 0);
                    if ($inv > 0 && $ebayStock > 0 && $inv === $ebayStock) {
                        $mapCount++;
                    } else if ($inv > 0 && ($ebayStock === 0 || ($ebayStock > 0 && $inv !== $ebayStock))) {
                        $notMapCount++;
                    }
                }
                
                // Count < Amz and > Amz
                $ebayPrice = floatval($row['eBay Price'] ?? 0);
                $amazonPrice = floatval($row['A Price'] ?? 0);
                if ($amazonPrice > 0 && $ebayPrice > 0) {
                    if ($ebayPrice < $amazonPrice) {
                        $lessAmzCount++;
                    } else if ($ebayPrice > $amazonPrice) {
                        $moreAmzCount++;
                    }
                }
                
                // Count Prc > LMP
                $lmpPrice = floatval($row['lmp_price'] ?? 0);
                if ($lmpPrice > 0 && $ebayPrice > $lmpPrice) {
                    $prcGtLmpCount++;
                }
                
                // Weighted price
                $totalWeightedPrice += $ebayPrice * $ebayL30;
            }
            
            // Calculate averages and percentages (EXACT JavaScript logic)
            $avgPrice = $totalEbayL30 > 0 ? $totalWeightedPrice / $totalEbayL30 : 0;
            $tcosPercent = $totalSalesAmt > 0 ? (($grandTotalSpend / $totalSalesAmt) * 100) : 0;
            $groiPercent = $totalLpAmt > 0 ? (($totalPftAmt / $totalLpAmt) * 100) : 0;
            $nroiPercent = $groiPercent - $tcosPercent;
            $gpftPercent = $totalSalesAmt > 0 ? (($totalSalesAmt - $totalLpAmt) / $totalSalesAmt * 100) : 0;
            
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
                'prc_gt_lmp_count' => $prcGtLmpCount,
                
                // Financial Totals
                'grand_total_kw_spend' => round($grandTotalKwSpend, 2),
                'grand_total_pmt_spend' => round($grandTotalPmtSpend, 2),
                'grand_total_spend' => round($grandTotalSpend, 2),
                'total_pft_amt' => round($totalPftAmt, 2),
                'total_sales_amt' => round($totalSalesAmt, 2),
                'total_lp_amt' => round($totalLpAmt, 2),
                
                // Inventory
                'total_fba_inv' => round($totalFbaInv, 2),
                'total_ebay_l30' => round($totalEbayL30, 2),
                
                // Calculated Percentages
                'tcos_percent' => round($tcosPercent, 2),
                'groi_percent' => round($groiPercent, 2),
                'nroi_percent' => round($nroiPercent, 2),
                'gpft_percent' => round($gpftPercent, 2),
                
                // Averages
                'avg_price' => round($avgPrice, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters (eBay specific)
                'filters_applied' => [
                    'inventory' => 'more',  // INV > 0
                    'nrl' => 'REQ',        // REQ only
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'ebay',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, REQ only)',
                ]
            );
            
            Log::info("Daily eBay summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily eBay summary: ' . $e->getMessage());
        }
    }

    /**
     * Get KW and PMT campaign data by SKU for ACOS modal (eBay tabulator view).
     */
    public function getCampaignDataBySku(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }
        $cleanSku = strtoupper(trim((string) $sku));

        $ebayMetric = EbayMetric::where('sku', $sku)->first();
        if (!$ebayMetric) {
            $ebayMetric = EbayMetric::whereRaw('UPPER(TRIM(sku)) = ?', [$cleanSku])->first();
        }
        $itemId = $ebayMetric && !empty($ebayMetric->item_id) ? trim((string) $ebayMetric->item_id) : null;

        $shopify = ShopifySku::where('sku', $sku)->first();
        if (!$shopify) {
            $shopify = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [$cleanSku])->first();
        }
        $inv = $shopify ? (float) ($shopify->inv ?? 0) : 0.0;

        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $lastSbidMap = [];
        $lastSbidReports = EbayPriorityReport::where('report_range', $dayBeforeYesterday)
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();
        foreach ($lastSbidReports as $report) {
            if (empty($report->campaign_id)) {
                continue;
            }
            $v = $report->last_sbid;
            if ($v === null || $v === '' || $v === '0' || $v === 0 || (is_numeric($v) && (float) $v === 0.0)) {
                continue;
            }
            $lastSbidMap[(string) $report->campaign_id] = $v;
        }

        $kwCampaigns = [];
        $kwL30 = EbayPriorityReport::where('report_range', 'L30')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get();
        $kwL7 = EbayPriorityReport::where('report_range', 'L7')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $kwL1 = EbayPriorityReport::where('report_range', 'L1')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $price = $ebayMetric ? (float) ($ebayMetric->ebay_price ?? 0) : 0;

        foreach ($kwL30 as $r) {
            $campaignId = $r->campaign_id ?? null;
            $cid = $campaignId !== null ? (string) $campaignId : null;
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

            $lastSbidRaw = $cid && isset($lastSbidMap[$cid]) ? $lastSbidMap[$cid] : ($r->last_sbid ?? $r->apprSbid ?? null);
            $lastSbid = null;
            if ($lastSbidRaw !== null && $lastSbidRaw !== '' && $lastSbidRaw !== '0') {
                $f = is_numeric($lastSbidRaw) ? (float) $lastSbidRaw : null;
                if ($f !== null && $f > 0) {
                    $lastSbid = $f;
                }
            }
            $l1CpcVal = $l1Cpc !== null ? (float) $l1Cpc : 0;
            $l7CpcVal = $l7Cpc !== null ? (float) $l7Cpc : 0;

            $sbid = $this->calculateSbidUtilized($ub7, $ub1, $inv, $bgt, $l1CpcVal, $l7CpcVal, $lastSbid, $price);

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

        $ptCampaigns = [];
        if ($itemId) {
            // Match ebay/pmp/ads: prefer COST_PER_SALE row per listing (EbayPMPAdsController)
            $campaignListing = null;
            try {
                $campaignListing = DB::connection('apicentral')
                    ->table('ebay_campaign_ads_listings')
                    ->where('listing_id', $itemId)
                    ->select('listing_id', 'bid_percentage', 'suggested_bid')
                    ->orderByRaw('CASE WHEN funding_strategy = "COST_PER_SALE" THEN 0 ELSE 1 END')
                    ->orderByDesc('id')
                    ->first();
            } catch (\Exception $e) {
                // apicentral may be unavailable
            }
            $cbid = $campaignListing ? (float) ($campaignListing->bid_percentage ?? 0) : null;
            $esBid = $campaignListing ? (float) ($campaignListing->suggested_bid ?? 0) : null;
            $views = $ebayMetric ? (float) ($ebayMetric->views ?? 0) : 0;
            $l7Views = $ebayMetric ? (float) ($ebayMetric->l7_views ?? 0) : 0;
            $ebayL30 = $ebayMetric ? (float) ($ebayMetric->ebay_l30 ?? 0) : 0;
            $scvr = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : null;

            $ptReports = EbayGeneralReport::where('report_range', 'L30')
                ->where('listing_id', $itemId)
                ->get();
            foreach ($ptReports as $r) {
                $ptCampaigns[] = [
                    'campaign_name' => 'PMT - ' . ($r->listing_id ?? 'N/A'),
                    'cbid' => $cbid,
                    'es_bid' => $esBid,
                    't_views' => $views > 0 ? $views : null,
                    'l7_views' => $l7Views,
                    'scvr' => $scvr,
                ];
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
            if ($l1Cpc > 1.25) {
                $sbid = floor($l1Cpc * 0.80 * 100) / 100;
            } elseif ($l1Cpc > 0) {
                $sbid = floor($l1Cpc * 0.90 * 100) / 100;
            } elseif ($l7Cpc > 0) {
                $sbid = floor($l7Cpc * 0.90 * 100) / 100;
            } else {
                $sbid = 0.0;
            }
            if ($price < 20 && $sbid > 0.20) {
                $sbid = 0.20;
            }
        } elseif ($under) {
            $baseBid = $lastSbid > 0 ? $lastSbid : ($l1Cpc > 0 ? $l1Cpc : ($l7Cpc > 0 ? $l7Cpc : 0));
            if ($baseBid > 0) {
                if ($ub1 < 33) {
                    $sbid = floor(($baseBid + 0.10) * 100) / 100;
                } elseif ($ub1 >= 33 && $ub1 < 66) {
                    $sbid = floor($baseBid * 1.10 * 100) / 100;
                } else {
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

    /**
     * Get eBay LMP data for a specific SKU
     */
    public function getEbayLmpData(Request $request)
    {
        try {
            $sku = $request->input('sku');
            
            if (!$sku) {
                return response()->json([
                    'error' => 'SKU is required'
                ], 400);
            }
            
            // Use 'ebay' to match database marketplace value
            $competitors = \App\Models\EbaySkuCompetitor::getCompetitorsForSku($sku, 'ebay');
            
            $lowestPrice = $competitors->first();
            
            return response()->json([
                'success' => true,
                'sku' => $sku,
                'competitors' => $competitors->map(function ($comp) {
                    return [
                        'id' => $comp->id,
                        'item_id' => $comp->item_id,
                        'price' => floatval($comp->price ?? 0),
                        'shipping_cost' => floatval($comp->shipping_cost ?? 0),
                        'total_price' => floatval($comp->total_price ?? 0),
                        'link' => $comp->product_link,
                        'title' => $comp->product_title,
                        'image' => $comp->image ?? null,
                        'created_at' => $comp->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'lowest_price' => $lowestPrice ? floatval($lowestPrice->total_price) : null,
                'total_count' => $competitors->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching eBay LMP data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch LMP data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add LMP from competitor data
     */
    public function addEbayLmp(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'item_id' => 'required|string',
                'price' => 'required|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'product_link' => 'nullable|string',
                'product_title' => 'nullable|string',
                'image' => 'nullable|string',
            ]);
            
            $sku = $validated['sku'];
            $itemId = $validated['item_id'];
            $price = $validated['price'];
            $shippingCost = $validated['shipping_cost'] ?? 0;
            $totalPrice = $price + $shippingCost;
            
            // Check if this item_id already exists for this SKU
            $exists = \App\Models\EbaySkuCompetitor::where('sku', $sku)
                ->where('item_id', $itemId)
                ->exists();
            
            if ($exists) {
                return response()->json([
                    'error' => 'This eBay item is already added as a competitor for this SKU'
                ], 409);
            }
            
            // Create new LMP entry
            DB::beginTransaction();
            
            $lmp = \App\Models\EbaySkuCompetitor::create([
                'sku' => $sku,
                'item_id' => $itemId,
                'price' => $price,
                'shipping_cost' => $shippingCost,
                'total_price' => $totalPrice,
                'marketplace' => 'ebay',
                'product_link' => $validated['product_link'] ?? null,
                'product_title' => $validated['product_title'] ?? null,
                'image' => $validated['image'] ?? null,
            ]);
            
            DB::commit();
            
            Log::info('eBay LMP added successfully', [
                'sku' => $sku,
                'item_id' => $itemId,
                'total_price' => $totalPrice
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'LMP added successfully',
                'data' => [
                    'id' => $lmp->id,
                    'sku' => $lmp->sku,
                    'item_id' => $lmp->item_id,
                    'price' => floatval($lmp->price),
                    'shipping_cost' => floatval($lmp->shipping_cost),
                    'total_price' => floatval($lmp->total_price),
                    'product_link' => $lmp->product_link,
                    'product_title' => $lmp->product_title,
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error adding eBay LMP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to add LMP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete LMP entry
     */
    public function deleteEbayLmp(Request $request)
    {
        try {
            $id = $request->input('id');
            
            Log::info('Delete eBay LMP request received', [
                'id' => $id,
                'all_input' => $request->all()
            ]);
            
            if (!$id) {
                return response()->json([
                    'error' => 'LMP ID is required'
                ], 400);
            }
            
            $lmp = \App\Models\EbaySkuCompetitor::find($id);
            
            if (!$lmp) {
                Log::warning('eBay LMP entry not found', ['id' => $id]);
                return response()->json([
                    'error' => 'LMP entry not found'
                ], 404);
            }
            
            DB::beginTransaction();
            
            $sku = $lmp->sku;
            $itemId = $lmp->item_id;
            $totalPrice = $lmp->total_price;
            
            $lmp->delete();
            
            DB::commit();
            
            Log::info('eBay LMP deleted successfully', [
                'id' => $id,
                'sku' => $sku,
                'item_id' => $itemId,
                'total_price' => $totalPrice
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'LMP deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting eBay LMP', [
                'id' => $id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to delete LMP: ' . $e->getMessage()
            ], 500);
        }
    }
}
