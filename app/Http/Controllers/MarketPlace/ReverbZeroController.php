<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ReverbProduct;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\ReverbListingStatus;
use App\Models\ReverbViewData;
use Illuminate\Support\Facades\Cache;

class ReverbZeroController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->input('mode', '');
        $demo = $request->input('demo', '');

        return view('market-places.reverbZeroView', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }
        
    public function getZeroViewData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('reverb_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Reverb')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch reverb data for these SKUs
        $reverbData = ReverbProduct::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch bump, S bump, s price from reverb_view_data
        $reverbViewData = ReverbViewData::whereIn('sku', $skus)->get()->keyBy('sku');

        $extraValues = [];
        foreach ($reverbViewData as $sku => $dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
            $extraValues[$sku] = [
                'NR' => $value['NR'] ?? 'REQ',
                'A_Z_Reason' => $value['A_Z_Reason'] ?? null,
                'A_Z_ActionRequired' => $value['A_Z_ActionRequired'] ?? null,
                'A_Z_ActionTaken' => $value['A_Z_ActionTaken'] ?? null,
            ];
        }

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'R&A' => false, // Default value, can be updated as needed
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

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

            // Add data from reverb_products if available
            if (isset($reverbData[$sku])) {
                $reverbItem = $reverbData[$sku];
                $reverbPrice = $reverbItem->price ?? 0;
                $ship = $values['ship'] ?? 0;

                $processedItem['price'] = ($reverbPrice > 0) ? ($reverbPrice + $ship) : 0;
                $processedItem['price_wo_ship'] = $reverbPrice;
                $processedItem['views'] = $reverbItem->views ?? 0;
                $processedItem['r_l30'] = $reverbItem->r_l30 ?? 0;
                $processedItem['r_l60'] = $reverbItem->r_l60 ?? 0;
            } else {
                $processedItem['price'] = 0;
                $processedItem['price_wo_ship'] = 0;
                $processedItem['views'] = 0;
                $processedItem['r_l30'] = 0;
                $processedItem['r_l60'] = 0;
            }

            // Add bump, S bump, s price from reverb_view_data if available
            if (isset($reverbViewData[$sku])) {
                $viewData = $reverbViewData[$sku];
                $valuesArr = $viewData->values ?: [];

                // Use consistent field names (all lowercase or all uppercase)
                $processedItem['Bump'] = $valuesArr['bump'] ?? null;
                $processedItem['s bump'] = $valuesArr['s_bump'] ?? null;
                $processedItem['S_price'] = $valuesArr['s_price'] ?? null;
                $processedItem['R&A'] = $valuesArr['R&A'] ?? false;

                // Fetch Reason/Action columns from ReverbViewData
                $processedItem['NR'] = $extraValues[$sku]['NR'] ?? 'REQ';
                $processedItem['A_Z_Reason'] = $valuesArr['A_Z_Reason'] ?? '';
                $processedItem['A_Z_ActionRequired'] = $valuesArr['A_Z_ActionRequired'] ?? '';
                $processedItem['A_Z_ActionTaken'] = $valuesArr['A_Z_ActionTaken'] ?? '';
            } else {
                $processedItem['Bump'] = null;
                $processedItem['s bump'] = null;
                $processedItem['S_price'] = null;
                $processedItem['R&A'] = false;
                // Default Reason/Action columns
                $processedItem['NR'] = 'REQ';
                $processedItem['A_Z_Reason'] = '';
                $processedItem['A_Z_ActionRequired'] = '';
                $processedItem['A_Z_ActionTaken'] = '';
            }

            // Default values for other fields
            $processedItem['A L30'] = 0;
            $processedItem['Sess30'] = 0;
            $processedItem['TOTAL PFT'] = 0;
            $processedItem['T Sales L30'] = 0;
            $processedItem['percentage'] = $percentageValue;

            $price = floatval($processedItem['price']);
            $percentage = floatval($processedItem['percentage']);
            $lp = floatval($processedItem['LP']);
            $ship = floatval($processedItem['Ship']);

            if ($price > 0) {
                $pft_percentage = (($price * $percentage) - $lp - $ship) / $price * 100;
                $processedItem['PFT_percentage'] = round($pft_percentage, 2);
            } else {
                $processedItem['PFT_percentage'] = 0;
            }

            if ($lp > 0) {
                $roi_percentage = (($price * $percentage) - $lp - $ship) / $lp * 100;
                $processedItem['ROI_percentage'] = round($roi_percentage, 2);
            } else {
                $processedItem['ROI_percentage'] = 0;
            }

            // Add image like Amazon (from ShopifySku or ProductMaster->Values)
            $processedItem['image_path'] = $shopifyData[$sku]->image_src ?? ($values['image_path'] ?? null);

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function getViewReverbZeroData(Request $request)
    {
        // 1. Fetch all ProductMaster rows
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // 2. Fetch ShopifySku for those SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // 3. Fetch DobaDataView for those SKUs
        $dobaDataViews = ReverbViewData::whereIn('sku', $skus)->get()->keyBy('sku');

         // 4. Fetch ReverbProduct for those SKUs (only views = 0)
        $reverbProducts = ReverbProduct::whereIn('sku', $skus)
            ->where('views', 0)
            ->get()
            ->keyBy('sku');

        $result = [];
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $parent = $pm->parent;
            $shopify = $shopifyData[$sku] ?? null;

            $inv = $shopify ? $shopify->inv : 0;
            $ov_l30 = $shopify ? $shopify->quantity : 0;
            $ov_dil = ($inv > 0) ? round($ov_l30 / $inv, 4) : 0;

            // Only include rows where inv > 0
            if ($inv > 0 && isset($reverbProducts[$sku])) {
                // Fetch DobaDataView values
                $dobaView = $dobaDataViews[$sku] ?? null;
                $value = $dobaView ? $dobaView->values : [];
                if (is_string($value)) {
                    $value = json_decode($value, true) ?: [];
                }

                $nrValue = $value['NR'] ?? $value['nr'] ?? null;

                $row = [
                    'parent' => $parent,
                    'sku' => $sku,
                    'inv' => $inv,
                    'ov_l30' => $ov_l30,
                    'ov_dil' => $ov_dil,
                    'NR' =>  ($nrValue && in_array(strtoupper($nrValue), ['REQ', 'NR']))
                                ? strtoupper($nrValue)
                                : 'REQ',
                    'A_Z_Reason' => $value['A_Z_Reason'] ?? '',
                    'A_Z_ActionRequired' => $value['A_Z_ActionRequired'] ?? '',
                    'A_Z_ActionTaken' => $value['A_Z_ActionTaken'] ?? '',
                ];
                $result[] = $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $result,
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

        $row = ReverbViewData::firstOrCreate(['sku' => $sku]);
        $values = $row->values ?? [];
        $values['A_Z_Reason'] = $reason;
        $values['A_Z_ActionRequired'] = $actionRequired;
        $values['A_Z_ActionTaken'] = $actionTaken;
        $row->values = $values; // <-- use 'values' not 'value'
        $row->save();

        return response()->json([
            'status' => 200,
            'message' => 'Reason and actions updated successfully.'
        ]);
    }


    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = ReverbViewData::whereIn('sku', $skus)->get()->keyBy('sku');

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

    
    public function getZeroViewCount()
    {
        // Fetch all ProductMaster records
        $productMasters = ProductMaster::all();
        $skus = $productMasters->pluck('sku')->toArray();

        // Fetch ShopifySku records for those SKUs
        $shopifySkus = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Only count SKUs where INV > 0 (zero view logic can be adjusted as needed)
        $zeroViewCount = $productMasters->filter(function ($product) use ($shopifySkus) {
            $sku = $product->sku;
            $inv = $shopifySkus->has($sku) ? $shopifySkus[$sku]->inv : 0;
            // If you have a "views" or "Sess30" field, add the check here
            return $inv > 0;
        })->count();

        return $zeroViewCount;
    }

    // public function getLivePendingAndZeroViewCounts()
    // {
    //     $productMasters = ProductMaster::whereNull('deleted_at')->get();
    //     $skus = $productMasters->pluck('sku')->unique()->toArray();

    //     $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $ebayDataViews = ReverbListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $ebayMetrics = ReverbProduct::whereIn('sku', $skus)->get()->keyBy('sku');

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
    //         $nrReq = $status['nr_req'] ?? null;

    //         // Listed count (for live pending)
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
    //         $nrReq = $status['nr_req'] ?? null;
    //         if (floatval($inv) > 0 && $views !== null && intval($views) === 0 && strtoupper($nrReq) !== 'NR') {
    //             $zeroViewCount++;
    //         }
    //     }

    //     // live pending = listed - 0-inv of listed - live
    //     $livePending = $listedCount - $zeroInvOfListed - $liveCount;

    //     return [
    //         'live_pending' => $livePending,
    //         'zero_view' => $zeroViewCount,
    //     ];
    // }


    // public function getLivePendingAndZeroViewCounts()
    // {
    //     $productMasters = ProductMaster::whereNull('deleted_at')->get();
    //     $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

    //     $shopifyData     = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $listingStatuses = ReverbListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $viewData        = ReverbViewData::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $reverbProducts  = ReverbProduct::whereIn('sku', $skus)->get()->keyBy('sku');

    //     $listedCount = 0;
    //     $zeroInvOfListed = 0;
    //     $liveCount = 0;
    //     $zeroViewCount = 0;

    //     foreach ($productMasters as $pm) {
    //         $sku = trim($pm->sku);
    //         if ($sku === '' || stripos($sku, 'PARENT') !== false) continue;

    //         $inv = floatval($shopifyData[$sku]->inv ?? 0);

    //         // --- LISTED from reverb_listing_statuses ---
    //         $listedRaw = $listingStatuses[$sku]->value ?? null;
    //         if (is_string($listedRaw)) {
    //             $decoded = json_decode($listedRaw, true);
    //             $listed = (json_last_error() === JSON_ERROR_NONE) ? ($decoded['listed'] ?? null) : null;
    //         } elseif (is_array($listedRaw)) {
    //             $listed = $listedRaw['listed'] ?? null;
    //         } else {
    //             $listed = null;
    //         }
    //         $listed = $listed ?? ($inv > 0 ? 'Pending' : 'Listed');

    //         // --- LIVE + NR from reverb_view_data ---
    //         $live = null;
    //         $nrReq = null;
    //         if (isset($viewData[$sku])) {
    //             $vdRaw = $viewData[$sku]->values ?? $viewData[$sku]->value ?? null;
    //             if (is_string($vdRaw)) {
    //                 $decoded = json_decode($vdRaw, true);
    //                 $vd = (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    //             } elseif (is_array($vdRaw)) {
    //                 $vd = $vdRaw;
    //             } else {
    //                 $vd = null;
    //             }

    //             if (is_array($vd)) {
    //                 $live = $vd['live'] ?? null;
    //                 $nrReq = $vd['NR'] ?? $vd['nr_req'] ?? $vd['nrReq'] ?? $vd['nr'] ?? null;
    //             }
    //         }

    //         // --- VIEWS from reverb_products ---
    //         $views = null;
    //         if (isset($reverbProducts[$sku])) {
    //             $raw = $reverbProducts[$sku]->views ?? null;
    //             $views = ($raw === '' || $raw === null) ? null : $raw;
    //         }

    //         // --- COUNTS ---
    //         // Listed count
    //         if ($listed === 'Listed') {
    //             $listedCount++;
    //             if ($inv <= 0) $zeroInvOfListed++;
    //         }

    //         // Live count
    //         if ($live === 'Live') $liveCount++;

    //         // Zero view: inv > 0, views == 0, NR not flagged
    //         $isNr = (is_string($nrReq) || is_numeric($nrReq)) && strtoupper((string)$nrReq) === 'NR';
    //         if ($inv > 0 && $views !== null && is_numeric($views) && intval($views) === 0 && !$isNr) {
    //             $zeroViewCount++;
    //         }
    //     }

    //     $livePending = $listedCount - $zeroInvOfListed - $liveCount;
    //     dd($livePending,$zeroViewCount,$listedCount, $liveCount);

    //     return [
    //         'live_pending' => $livePending,
    //         'zero_view'    => $zeroViewCount,
    //     ];
    // }


    public function getLivePendingAndZeroViewCounts()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();

        // Normalize SKUs (avoid case/space mismatch)
        $skus = $productMasters->pluck('sku')->map(fn($s) => strtoupper(trim($s)))->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $reverbListingStatus = ReverbListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $reverbDataViews = ReverbViewData::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $reverbMetrics = ReverbProduct::whereIn('sku', $skus)->get()
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

            // --- Amazon Listing Status ---
            $status = $reverbListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $reverbDataViews[$sku]->values ?? null;
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
            $metricRecord = $reverbMetrics[$sku] ?? null;
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

            // Count as zero-view if views are exactly 0 and inv > 0
            if ($inv > 0 && $views === 0) {
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
