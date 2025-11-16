<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\EbayDataView;
use App\Models\EbayListingStatus;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\EbayPriorityReport;

class EbayZeroController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function adcvrEbay(){
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        
        return view('market-places.adcvrEbay', [
            'ebayPercentage' => $percentage,
            'ebayAdUpdates' => $adUpdates
        ]);
    }

    public function adcvrEbayData() {
        ini_set('max_execution_time', 600);

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values();

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;

        $ebayDatasheetsBySku = DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->whereIn('sku', $skus)
            ->get()
            ->map(fn($item) => (object)(array)$item)
            ->keyBy(fn($item) => strtoupper($item->sku));

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $allCampaigns = EbayPriorityReport::whereIn('report_range', ['L90', 'L30', 'L7'])->get();

        $campaignsByRange = [
            'L90' => $allCampaigns->where('report_range', 'L90')->keyBy(fn($r) => strtoupper(trim(rtrim($r->campaign_name, '.')))),
            'L30' => $allCampaigns->where('report_range', 'L30')->keyBy(fn($r) => strtoupper(trim(rtrim($r->campaign_name, '.')))),
            'L7'  => $allCampaigns->where('report_range', 'L7')->keyBy(fn($r) => strtoupper(trim(rtrim($r->campaign_name, '.')))),
        ];

        $lmpData = DB::connection('repricer')
            ->table('lmp_data')
            ->whereIn('sku', $skus)
            ->where('price', '>', 0)
            ->orderBy('price', 'asc')
            ->get()
            ->groupBy('sku');

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $ebaySheet = $ebayDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL90 = $campaignsByRange['L90'][$sku] ?? null;
            $matchedCampaignL30 = $campaignsByRange['L30'][$sku] ?? null;
            $matchedCampaignL7  = $campaignsByRange['L7'][$sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L90']  = $ebaySheet->ebay_l90 ?? 0;
            $row['A_L30']  = $ebaySheet->ebay_l30 ?? 0;
            $row['A_L7']   = $ebaySheet->ebay_l7 ?? 0;

            $row['campaign_id']         = $matchedCampaignL90->campaign_id ?? '';
            $row['campaignName']        = $matchedCampaignL90->campaign_name ?? '';
            $row['campaignStatus']      = $matchedCampaignL90->campaignStatus ?? '';
            $row['campaignBudgetAmount']= $matchedCampaignL90->campaignBudgetAmount ?? 0;
            $row['spend_l90']           = $matchedCampaignL90->cpc_return_on_ad_spend ?? 0;
            $row['ad_sales_l90']        = $matchedCampaignL90->cpc_attributed_sales ?? 0;
            $row['spend_30']            = $matchedCampaignL30->cpc_return_on_ad_spend ?? 0;
            $row['ad_sales_l30']        = $matchedCampaignL30->cpc_attributed_sales ?? 0;
            $row['spend_l7']            = $matchedCampaignL7->cpc_return_on_ad_spend ?? 0;
            $row['ad_sales_l7']         = $matchedCampaignL7->cpc_attributed_sales ?? 0;

            if ($ebaySheet) {
                $row['Sess30'] = $ebaySheet->views ?? 0;
                $row['price'] = $ebaySheet->ebay_price ?? 0;
                $row['sessions_l60'] = $ebaySheet->views ?? 0;
                $row['units_ordered_l60'] = $ebaySheet->ebay_l60 ?? 0;
            }

            $values = is_array($pm->Values)
                ? $pm->Values
                : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }

            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }

            $ship = isset($values['ship'])
                ? floatval($values['ship'])
                : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $row['SHIP'] = $ship;
            $row['LP'] = $lp;

            $price = isset($row['price']) ? floatval($row['price']) : 0;

            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);

            $sales90 = $matchedCampaignL90->cpc_attributed_sales ?? 0;
            $spend90 = $matchedCampaignL90->cpc_return_on_ad_spend ?? 0;

            $sales30 = $matchedCampaignL30->cpc_attributed_sales ?? 0;
            $spend30 = $matchedCampaignL30->cpc_return_on_ad_spend ?? 0;

            $sales7 = $matchedCampaignL7->cpc_attributed_sales ?? 0;
            $spend7 = $matchedCampaignL7->cpc_return_on_ad_spend ?? 0;

            $row['acos_L90'] = $sales90 > 0 ? round(($spend90 / $sales90) * 100, 2) : ($spend90 > 0 ? 100 : 0);
            $row['acos_L30'] = $sales30 > 0 ? round(($spend30 / $sales30) * 100, 2) : ($spend30 > 0 ? 100 : 0);
            $row['acos_L7']  = $sales7 > 0  ? round(($spend7 / $sales7) * 100, 2)  : ($spend7 > 0 ? 100 : 0);

            $row['clicks_L90'] = $matchedCampaignL90->cpc_clicks ?? 0;
            $row['clicks_L30'] = $matchedCampaignL30->cpc_clicks ?? 0;
            $row['clicks_L7']  = $matchedCampaignL7->cpc_clicks ?? 0;

            $row['cvr_l90'] = ($row['clicks_L90'] == 0) ? null : number_format(($row['A_L90'] / $row['clicks_L90']) * 100, 2);

            $row['NRL'] = $row['NRA'] = $row['FBA'] = $row['TPFT'] = '';

            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA']  = $raw['NRA'] ?? null;
                    $row['FBA']  = $raw['FBA'] ?? null;
                    $row['TPFT'] = $raw['TPFT'] ?? null;
                }
            }

            $row['ebay_price'] = $ebaySheet ? ($ebaySheet->price ?? 0) : 0;
            $row['ebay_pft'] = $ebaySheet && ($ebaySheet->price ?? 0) > 0
                ? (($ebaySheet->price * 0.70 - $lp - $ship) / $ebaySheet->price)
                : 0;
            $row['ebay_roi'] = $ebaySheet && $lp > 0 && ($ebaySheet->price ?? 0) > 0
                ? (($ebaySheet->price * 0.70 - $lp - $ship) / $lp)
                : 0;

            $prices = isset($lmpData[$sku])
                ? $lmpData[$sku]->pluck('price')->toArray()
                : [];

            for ($i = 0; $i <= 11; $i++) {
                if ($i == 0) {
                    $row['lmp'] = $prices[$i] ?? 0;
                } else {
                    $row['lmp_' . $i] = $prices[$i] ?? 0;
                }
            }

            $result[] = (object)$row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function updateEbayPrice(Request $request) {
        try {
            $validated = $request->validate([
            'sku' => 'required|exists:apicentral.ebay_one_metrics,sku',
            'price' => 'required|numeric',
        ]);

        $ebayData = DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->where('sku', $validated['sku'])
            ->first();

        if (!$ebayData) {
            return response()->json([
                'status' => 'error',
                'message' => 'SKU not found in ebay_one_metrics.',
            ], 404);
        }

        DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->where('sku', $validated['sku'])
            ->update([
                'ebay_price' => $validated['price'],
            ]);

        $updatedData = DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->where('sku', $validated['sku'])
            ->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Ebay price and metrics updated successfully.',
            'data' => $updatedData,
        ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function ebayZero(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('ebay_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'eBay')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.ebayZeroView', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

    public function getVieweBayZeroData(Request $request)
    {
        // 1. Fetch all ProductMaster rows (base)
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch data from the Google Sheet using the ApiController method
        // Prepare SKU list for related models
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        // Fetch related data
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');
        // Fetch all EbayDataView rows for these SKUs
        $ebayDataViews = EbayDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // Build the result using ProductMaster as the base
        $processedData = [];
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $parentSku = $pm->parent;
            $imagePath = null;

            // Try to get image from Shopify first
            $shopify = $shopifyData[$sku] ?? null;
            if ($shopify && !empty($shopify->image_src)) {
                $imagePath = $shopify->image_src;
            } else {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (isset($values['image_path'])) {
                    $imagePath = $values['image_path'];
                } elseif (isset($pm->image_path)) {
                    $imagePath = $pm->image_path;
                }
            }
            $buyerLink = $item->{'B Link'} ?? null;
            $sellerLink = $item->{'S Link'} ?? null;
            // Create base item object
            $item = (object) [
                'Parent' => $parentSku,
                '(Child) sku' => $sku,
                'B Link' => null,
                'S Link' => null,
                'INV' => $shopify ? $shopify->inv : 0,
                'L30' => $shopify ? $shopify->quantity : 0,
                'eBay L30' => 0,
                'eBay L60' => 0,
                'eBay Price' => 0,
                'OV CLICKS L30' => 0,
                'views' => $ebayMetric->views ?? 0,
                'image' => $imagePath,
                'A_Z_Reason' => '',
                'A_Z_ActionRequired' => '',
                'A_Z_ActionTaken' => '',
                'NR' => 'REQ',
                'B Link' => (filter_var($buyerLink, FILTER_VALIDATE_URL)) ? $buyerLink : null,
                'S Link' => (filter_var($sellerLink, FILTER_VALIDATE_URL)) ? $sellerLink : null,
            ];

            // eBay metrics
            if ($ebayMetrics->has($sku)) {
                $ebayMetric = $ebayMetrics[$sku];
                $item->{'eBay L30'} = $ebayMetric->ebay_l30;
                $item->{'eBay L60'} = $ebayMetric->ebay_l60;
                $item->{'eBay Price'} = $ebayMetric->ebay_price;
                $item->{'views'} = $ebayMetric->views ?? 0;
                $inv = $shopify->inv ?? 0;
                $eBayL30 = $item->{'eBay L30'} ?? 0;
                $item->{'E Dil%'} = ($inv > 0) ? round($eBayL30 / $inv, 2) : 0;
            }

            // EbayDataView
            $dataView = $ebayDataViews->get($sku);
            $value = $dataView ? $dataView->value : [];
            $item->{'A_Z_Reason'} = $value['A_Z_Reason'] ?? '';
            $item->{'A_Z_ActionRequired'} = $value['A_Z_ActionRequired'] ?? '';
            $item->{'A_Z_ActionTaken'} = $value['A_Z_ActionTaken'] ?? '';
            $item->{'NR'} = $value['NR'] ?? 'REQ';

            $processedData[] = $item;
        }

        // Filter: Only show rows with 0 views
        $filteredResults = array_filter($processedData, function ($item) {
            $childSku = $item->{'(Child) sku'} ?? '';
            $inv = $item->INV ?? 0;
            $views = $item->views ?? 1;
            return stripos($childSku, 'PARENT') === false && $inv > 0 && intval($views) === 0;
        });

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => array_values($filteredResults),
            'status' => 200
        ]);

    }

    public function updateReasonAction(Request $request)
    {
        $sku = $request->input('sku');
        $reason = $request->input('reason');
        $actionRequired = $request->input('action_required');
        $actionTaken = $request->input('action_taken');

        if (!$sku) {
            return response()->json([
                'status' => 400,
                'message' => 'SKU is required.'
            ], 400);
        }

        $row = EbayDataView::firstOrCreate(['sku' => $sku]);
        $value = $row->value ?? [];
        $value['A_Z_Reason'] = $reason;
        $value['A_Z_ActionRequired'] = $actionRequired;
        $value['A_Z_ActionTaken'] = $actionTaken;
        $row->value = $value;
        $row->save();

        return response()->json([
            'status' => 200,
            'message' => 'Reason and actions updated successfully.'
        ]);
    }

    public function getZeroViewCount()
    {
        // Use the same logic as getVieweBayZeroData: INV > 0, views == 0, not parent SKU
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->unique()->toArray();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');

        $zeroCount = 0;
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;
            $views = $ebayMetrics[$sku]->views ?? 0;
            if (!$isParent && $inv > 0 && intval($views) === 0) {
                $zeroCount++;
            }
        }
        return $zeroCount;
    }

    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = EbayDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        $reqCount = 0;
        $nrCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // NR/REQ logic
            $nrReq = $status['NR'] ?? (floatval($inv) > 0 ? 'REQ' : 'NR');
            if ($nrReq === 'REQ') {
                $reqCount++;
            } elseif ($nrReq === 'NR') {
                $nrCount++;
            }

            // Listed/Pending logic
            $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            if ($listed === 'Listed') {
                $listedCount++;
            } elseif ($listed === 'Pending') {
                $pendingCount++;
            }
        }

        return [
            'NR'  => $nrCount,
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ];
    }

    // public function getLivePendingAndZeroViewCounts()
    // {
    //     $productMasters = ProductMaster::whereNull('deleted_at')->get();
    //     $skus = $productMasters->pluck('sku')->unique()->toArray();

    //     $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $ebayDataViews = EbayListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku'); 

    //     $listedCount = 0;
    //     $zeroInvOfListed = 0;
    //     $liveCount = 0;
    //     $zeroViewCount = 0;

    //     foreach ($productMasters as $item) {
    //         $sku = trim($item->sku);
    //         $inv = $shopifyData[$sku]->inv ?? 0;
    //         $isParent = stripos($sku, 'PARENT') !== false;
    //         if ($isParent) continue;

    //         $status = $ebayDataViews[$sku]->value ?? null;
    //         if (is_string($status)) {
    //             $status = json_decode($status, true);
    //         }
    //         $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
    //         $live = $status['live'] ?? null;

    //         // Listed count 
    //         if ($listed === 'Listed') {
    //             $listedCount++;
    //             if (floatval($inv) <= 0) {
    //                 $zeroInvOfListed++;
    //             }
    //         }

    //         // Live count
    //         if ($live === 'Live') {
    //             $liveCount++;
    //         }

    //         // Zero view: INV > 0, views == 0 (from ebay_metric table), not parent SKU (NR ignored)
    //         $views = $ebayMetrics[$sku]->views ?? null;
    //         // if (floatval($inv) > 0 && $views !== null && intval($views) === 0) {
    //         //     $zeroViewCount++;
    //         // }
    //         if ($inv > 0) {
    //             if ($views === null) {
    //             } elseif (intval($views) === 0) {
    //                 $zeroViewCount++;
    //             }
    //         }
    //     }

    //     // live pending = listed - 0-inv of listed - live
    //     $livePending = $listedCount - $zeroInvOfListed - $liveCount;

    //     return [
    //         'live_pending' => $livePending,
    //         'zero_view' => $zeroViewCount,
    //     ];
    // }


    public function getLivePendingAndZeroViewCounts()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();

        // Normalize SKUs (avoid case/space mismatch)
        $skus = $productMasters->pluck('sku')->map(fn($s) => strtoupper(trim($s)))->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $ebayListingStatus = EbayListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $ebayDataViews = EbayDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $listedCount = 0;
        $zeroInvOfListed = 0;
        $liveCount = 0;
        $zeroViewCount = 0;

        foreach ($productMasters as $item) {
            $sku = strtoupper(trim($item->sku));
            $inv = $shopifyData[$sku]->inv ?? 0;

            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false) continue;

            // --- eBay Listing Status ---
            $status = $ebayListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $ebayDataViews[$sku]->value ?? null;
            if (is_string($dataView)) {
                $dataView = json_decode($dataView, true);
            }
            // $live = ($dataView['Live'] ?? false) === true ? 'Live' : null;
            $live = (!empty($dataView['Live']) && $dataView['Live'] === true) ? 'Live' : null;


            // --- Listed count ---
            if ($listed === 'Listed') {
                $listedCount++;
                if (floatval($inv) <= 0) {
                    $zeroInvOfListed++;
                }
            }

            // --- Live count ---
            if ($live === 'Live') {
                $liveCount++;
            }

            // --- Views / Zero-View logic ---
            $metricRecord = $ebayMetrics[$sku] ?? null;
            $views = null;

            if ($metricRecord) {
                // Direct field
                if (!empty($metricRecord->views) || $metricRecord->views === "0" || $metricRecord->views === 0) {
                    $views = (int)$metricRecord->views;
                }
                // Or inside JSON column `value`
                elseif (!empty($metricRecord->value)) {
                    $metricData = json_decode($metricRecord->value, true);
                    if (isset($metricData['views'])) {
                        $views = (int)$metricData['views'];
                    }
                }
            }

            // Normalize $inv to numeric
            $inv = floatval($inv);

            $hasNR = !empty($dataView['NR']) && strtoupper($dataView['NR']) === 'NR';

            // Count as zero-view if views are exactly 0 and inv > 0
            if ($inv > 0 && $views === 0  && !$hasNR) {
                $zeroViewCount++;
            }

        }

        $livePending = $listedCount - $liveCount;

        return [
            'live_pending' => $livePending,
            'zero_view' => $zeroViewCount,
        ];
    }
}