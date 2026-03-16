<?php

namespace App\Http\Controllers\MarketPlace\ZeroViewMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\Ebay3Metric;
use App\Models\EbayListingStatus;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\EbayThreeDataView;
use App\Models\EbayThreeListingStatus;
use Illuminate\Http\Request;

class Ebay3ZeroController extends Controller
{
    public function ebay3Zeroview(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        return view('market-places.zero-market-places.ebay3ZeroView', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }

    public function getViewEbay3ZeroData(Request $request)
    {
        // 1. Fetch all ProductMaster rows
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // 2. Fetch ShopifySku for those SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $ebayMetrics = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy('sku');

        // 3. Fetch EbayThreeDataView for those SKUs
        $ebay3DataViews = EbayThreeDataView::whereIn('sku', $skus)->get()->keyBy('sku');


        $result = [];
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $parent = $pm->parent;
            $shopify = $shopifyData[$sku] ?? null;

            $inv = $shopify ? $shopify->inv : 0;
            $ov_l30 = $shopify ? $shopify->quantity : 0;
            $ov_dil = ($inv > 0) ? round($ov_l30 / $inv, 4) : 0;

            // Only include rows where inv > 0, SKU exists in Ebay3Metric, and views == 0
            if ($inv > 0 && isset($ebayMetrics[$sku])) {
                $views = $ebayMetrics[$sku]->views ?? null;
                if ($views !== null && intval($views) === 0) {

                    // Fetch DobaDataView values
                    $dobaView = $ebay3DataViews[$sku] ?? null;
                    $value = $dobaView ? $dobaView->value : [];
                    if (is_string($value)) {
                        $value = json_decode($value, true) ?: [];
                    }

                    $row = [
                        'parent' => $parent,
                        'sku' => $sku,
                        'inv' => $inv,
                        'ov_l30' => $ov_l30,
                        'ov_dil' => $ov_dil,
                        'views' => $views,
                        'NR' => isset($value['NR']) && in_array($value['NR'], ['REQ', 'NR']) ? $value['NR'] : 'REQ',
                        'A_Z_Reason' => $value['A_Z_Reason'] ?? '',
                        'A_Z_ActionRequired' => $value['A_Z_ActionRequired'] ?? '',
                        'A_Z_ActionTaken' => $value['A_Z_ActionTaken'] ?? '',
                    ];
                    $result[] = $row;
                }
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

        $row = EbayThreeDataView::firstOrCreate(
            ['sku' => $sku],
            ['value' => json_encode([])]
        );

        // Fix: decode value if it's a string
        $value = $row->value;
        if (is_string($value)) {
            $value = json_decode($value, true) ?: [];
        }

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

    public function saveEbayThreeZeroNR(Request $request)
    {
        $sku = $request->input('sku');
        $nrValue = $request->input('nr');

        if (!$sku) {
            return response()->json([
                'status' => 400,
                'message' => 'SKU is required.'
            ], 400);
        }

        $row = EbayThreeDataView::firstOrCreate(
            ['sku' => $sku],
            ['value' => json_encode([])]
        );

        $value = $row->value;
        if (is_string($value)) {
            $value = json_decode($value, true) ?: [];
        }

        // Save NR value
        $nrArray = json_decode($nrValue, true);
        if (is_array($nrArray)) {
            $value['NR'] = $nrArray['NR'] ?? '';
        }

        $row->value = $value;
        $row->save();

        return response()->json([
            'status' => 200,
            'message' => 'NR updated successfully.'
        ]);
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
    //     $ebayDataViews = EbayThreeListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $ebayMetrics = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy(fn($item) => strtoupper(trim($item->sku)));

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
    //         // if (floatval($inv) > 0 && $views !== null && intval($views) === 0) {
    //         //     $zeroViewCount++;
    //         // }
    //         if ($inv > 0) {
    //             if ($views === null) {
    //                 // Do nothing, ignore null
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

        $ebayListingStatus = EbayThreeListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $ebayDataViews = EbayThreeDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $ebayMetrics = Ebay3Metric::whereIn('sku', $skus)->get()
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