<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayThreeDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ADVMastersData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Ebay3MissingAdsController extends Controller
{
    public function index()
    {
        return view('campaign.ebay-three.ebay3_missing_ads');
    }

    public function getEbay3MissingDataSave(Request $request)
    {
        return ADVMastersData::getEbay3MissingDataSaveProceed($request);
    }

    public function getEbay3MissingAdsData()
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

            // Fetch all required data
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
            $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');
            $ebayMetricData = DB::table('ebay_3_metrics')
                ->select('sku', 'ebay_price', 'item_id')
                ->whereIn('sku', $skus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->sku));

            // Fetch campaign reports and create efficient lookup
            $ebayCampaignReports = Ebay3PriorityReport::where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })->get();

            $campaignLookup = [];
            foreach ($ebayCampaignReports as $campaign) {
                foreach ($skus as $sku) {
                    $normalizedCampaignName = strtoupper(trim($campaign->campaign_name)); 
                    if (stripos($normalizedCampaignName, $sku) !== false) { 
                        if (!isset($campaignLookup[$sku])) {
                            $campaignLookup[$sku] = $campaign;
                        }
                    }
                }
            }

            $campaignListings = DB::connection('apicentral')
                ->table('ebay_campaign_ads_listings')
                ->select('listing_id', 'bid_percentage')
                ->get()
                ->keyBy('listing_id')
                ->toArray();

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

                $result[] = [
                    'sku' => $sku,
                    'parent' => $pm->parent,
                    'INV' => $shopify->inv ?? 0,
                    'L30' => $shopify->quantity ?? 0,
                    'NRA' => $nrActual,
                    'kw_campaign_name' => $campaignReport->campaign_name ?? null,
                    'pmt_bid_percentage' => ($ebayMetric && isset($ebayMetric->item_id) && isset($campaignListings[$ebayMetric->item_id])) 
                        ? $campaignListings[$ebayMetric->item_id]->bid_percentage 
                        : null,
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
