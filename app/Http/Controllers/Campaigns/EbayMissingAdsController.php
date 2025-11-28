<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbayListingStatus;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ADVMastersData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EbayMissingAdsController extends Controller
{
    public function index()
    {
        return view('campaign.ebay-missing-ads');
    }

    public function getEbayMissingSaveData(Request $request)
    {
        return ADVMastersData::getEbayMissingSaveDataProceed($request);
    }

    public function getEbayMissingAdsData()
    {
        try {
            $normalizeSku = fn($sku) => strtoupper(trim($sku));

            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                return response()->json([
                    'message' => 'No product masters found',
                    'data'    => [],
                    'status'  => 200,
                ]);
            }

            $skus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();

            // Get SKUs with 'NR' status from EbayListingStatus and exclude them
            $nrSkus = EbayListingStatus::whereIn('sku', $skus)
                ->get()
                ->filter(function($item) {
                    $value = is_array($item->value) ? $item->value : json_decode($item->value, true);
                    return isset($value['nr_req']) && $value['nr_req'] === 'NR';
                })
                ->pluck('sku')
                ->map($normalizeSku)
                ->toArray();

            // Remove 'nr' SKUs from the main SKU list
            $skus = array_diff($skus, $nrSkus);
            
            // Also filter ProductMasters to exclude NR SKUs
            $productMasters = $productMasters->filter(function($pm) use ($nrSkus) {
                return !in_array(strtoupper(trim($pm->sku)), $nrSkus);
            });

            // Fetch all required data
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
            $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');
            $ebayMetricData = EbayMetric::select('sku', 'ebay_price', 'item_id')
                ->whereIn('sku', $skus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->sku));

            // Fetch campaign reports and create efficient lookup
            $ebayCampaignReports = EbayPriorityReport::where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })->get();

            $campaignLookup = [];
            foreach ($ebayCampaignReports as $campaign) {
                foreach ($skus as $sku) {
                    if (strpos($campaign->campaign_name, $sku) !== false) {
                        if (!isset($campaignLookup[$sku])) {
                            $campaignLookup[$sku] = $campaign;
                        }
                    }
                }
            }

            $campaignListings = DB::connection('apicentral')
                ->table('ebay_campaign_ads_listings')
                ->select('listing_id', 'bid_percentage')
                ->where('funding_strategy', 'COST_PER_SALE')
                ->get()
                ->keyBy('listing_id');

            // Debug: Check specific SKU data
            $testSku = 'SP 12120 8OHMS 2PCS';
            if (in_array($testSku, $skus)) {
                $testEbayMetric = $ebayMetricData->get(strtoupper($testSku));
                $itemId = $testEbayMetric->item_id ?? '';
                
                // Check if the key exists in different formats
                $keyCheck = [
                    'direct_string' => isset($campaignListings[$itemId]),
                    'as_integer' => isset($campaignListings[intval($itemId)]),
                    'key_exists' => $campaignListings->has($itemId),
                    'found_keys_with_236379' => $campaignListings->keys()->filter(function($key) {
                        return strpos($key, '236379') !== false;
                    })->toArray()
                ];
                
                Log::info('Debug SP 12120 SKU', [
                    'sku' => $testSku,
                    'ebay_metric_found' => $testEbayMetric ? 'yes' : 'no',
                    'item_id' => $itemId,
                    'item_id_type' => gettype($itemId),
                    'key_check' => $keyCheck,
                    'listing_exists' => isset($campaignListings[$itemId]) ? 'yes' : 'no',
                    'bid_percentage' => $campaignListings[$itemId]->bid_percentage ?? 'none',
                    'campaign_listings_structure' => $campaignListings->take(2)->toArray()
                ]);
            }

            $result = [];

            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $shopify = $shopifyData->get($sku);
                $ebayMetric = $ebayMetricData->get($sku);
                $campaignReport = $campaignLookup[$sku] ?? null;
                
                $nrActual = null;
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $nrActual = $raw['NRA'] ?? null;
                    }
                }

                // Get PMT bid percentage
                $pmtBidPercentage = null;
                if ($ebayMetric && isset($ebayMetric->item_id) && isset($campaignListings[$ebayMetric->item_id])) {
                    $pmtBidPercentage = $campaignListings[$ebayMetric->item_id]->bid_percentage;
                }

                // Debug logging for specific SKU
                if (strpos($sku, 'SP 12120') !== false) {
                    $itemIdToCheck = $ebayMetric->item_id ?? '';
                    $campaignListingDetails = null;
                    if (isset($campaignListings[$itemIdToCheck])) {
                        $campaignListingDetails = $campaignListings[$itemIdToCheck];
                    }
                    
                    Log::info('PMT Matching Debug', [
                        'sku' => $sku,
                        'ebay_metric_exists' => $ebayMetric ? 'yes' : 'no',
                        'item_id' => $itemIdToCheck,
                        'item_id_type' => $ebayMetric ? gettype($ebayMetric->item_id) : 'none',
                        'listing_id_exists' => isset($campaignListings[$itemIdToCheck]) ? 'yes' : 'no',
                        'campaign_listing_details' => $campaignListingDetails,
                        'pmt_bid_percentage' => $pmtBidPercentage,
                        'campaign_listings_count' => count($campaignListings),
                        'campaign_listings_with_target_id' => $campaignListings->has($itemIdToCheck) ? 'yes' : 'no',
                        'all_listing_ids_containing_236379' => $campaignListings->keys()->filter(function($key) {
                            return strpos($key, '236379') !== false;
                        })->toArray()
                    ]);
                }

                $result[] = [
                    'sku' => $sku,
                    'parent' => $pm->parent,
                    'INV' => $shopify->inv ?? 0,
                    'L30' => $shopify->quantity ?? 0,
                    'NRA' => $nrActual,
                    'kw_campaign_name' => $campaignReport->campaign_name ?? null,
                    'pmt_bid_percentage' => $pmtBidPercentage,
                    'campaignStatus' => $campaignReport->campaignStatus ?? null,
                ];
            }

            return response()->json([
                'message' => 'Data fetched successfully',
                'data'    => $result,
                'status'  => 200,
            ]);
            
        } catch (\Exception $e) {
            Log::error('EbayMissingAdsController error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error fetching data: ' . $e->getMessage(),
                'data'    => [],
                'status'  => 500,
            ]);
        }
    }
}
