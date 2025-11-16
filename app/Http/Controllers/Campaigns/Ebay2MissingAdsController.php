<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\EbayTwoDataView;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ADVMastersData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Ebay2MissingAdsController extends Controller
{
    public function index()
    {
        return view('campaign.ebay-two.ebay2_missing_ads');
    }

    public function getAdvEbay2MissingSaveData(Request $request)
    {
        return ADVMastersData::getAdvEbay2MissingSaveDataProceed($request);
    }

    public function getEbay2MissingAdsData()
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
            $nrValues = EbayTwoDataView::whereIn('sku', $skus)->pluck('value', 'sku');
            $ebayMetricData = DB::connection('apicentral')->table('ebay2_metrics')
                ->select('sku', 'ebay_price', 'item_id')
                ->whereIn('sku', $skus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->sku));

            $campaignListings = DB::connection('apicentral')
                ->table('ebay2_campaign_ads_listings')
                ->select('listing_id', 'bid_percentage')
                ->get()
                ->keyBy('listing_id')
                ->toArray();

            $result = [];

            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $shopify = $shopifyData->get($sku);
                $ebayMetric = $ebayMetricData->get($sku);
                
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
                    'pmt_bid_percentage' => ($ebayMetric && isset($ebayMetric->item_id) && isset($campaignListings[$ebayMetric->item_id])) 
                        ? $campaignListings[$ebayMetric->item_id]->bid_percentage 
                        : null,
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
