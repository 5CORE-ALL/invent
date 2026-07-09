<?php

namespace App\Http\Controllers\MarketPlace\ZeroViewMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\AliexpressDataView;
use App\Models\AliexpressListingStatus;
use App\Models\AliExpressSheetData;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AliexpressZeroController extends Controller
{
    // public function getViewAliexpressZeroData(Request $request)
    // {
    //     $productMasters = ProductMaster::orderBy('parent', 'asc')
    //         ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
    //         ->orderBy('sku', 'asc')
    //         ->get();

    //     $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
    //     $shopifyData = ShopifySku::mapByProductSkus($skus);
    //     $aliexpressDataViews = AliexpressDataView::whereIn('sku', $skus)->get()->keyBy('sku');

    //     $result = [];
    //     foreach ($productMasters as $pm) {
    //         $sku = $pm->sku;
    //         $parent = $pm->parent;
    //         $shopify = $shopifyData[$sku] ?? null;

    //         $inv = $shopify ? $shopify->inv : 0;
    //         $ov_l30 = $shopify ? $shopify->quantity : 0;
    //         $ov_dil = ($inv > 0) ? round($ov_l30 / $inv, 4) : 0;

    //         if ($inv > 0) {
    //             $aliexpressView = $aliexpressDataViews[$sku] ?? null;
    //             $value = $aliexpressView ? $aliexpressView->value : [];
    //             if (is_string($value)) {
    //                 $value = json_decode($value, true) ?: [];
    //             }

    //             $row = [
    //                 'parent' => $parent,
    //                 'sku' => $sku,
    //                 'inv' => $inv,
    //                 'ov_l30' => $ov_l30,
    //                 'ov_dil' => $ov_dil,
    //                 'NR' => isset($value['NR']) && in_array($value['NR'], ['REQ', 'NR']) ? $value['NR'] : 'REQ',
    //                 'A_Z_Reason' => $value['A_Z_Reason'] ?? '',
    //                 'A_Z_ActionRequired' => $value['A_Z_ActionRequired'] ?? '',
    //                 'A_Z_ActionTaken' => $value['A_Z_ActionTaken'] ?? '',
    //             ];
    //             $result[] = $row;
    //         }
    //     }

    //     return response()->json([
    //         'message' => 'Data fetched successfully',
    //         'data' => $result,
    //         'status' => 200
    //     ]);
    // }

    public function getZeroViewCount()
    {
        // Fetch all ProductMaster records
        $productMasters = ProductMaster::all();
        $skus = $productMasters->pluck('sku')->toArray();

        // Fetch ShopifySku records for those SKUs
        $shopifySkus = ShopifySku::mapByProductSkus($skus);

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

    //     $shopifyData = ShopifySku::mapByProductSkus($skus);
    //     $aliexpressDataViews = AliexpressListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $aliexpressMetrics = AliExpressSheetData::whereIn('sku', $skus)->get()->keyBy('sku');

    //     $listedCount = 0;
    //     $zeroInvOfListed = 0;
    //     $liveCount = 0;
    //     $zeroViewCount = 0;

    //     foreach ($productMasters as $item) {
    //         $sku = trim($item->sku);
    //         $inv = $shopifyData->get($item->sku)?->inv ?? 0;
    //         $isParent = stripos($sku, 'PARENT') !== false;
    //         if ($isParent) continue;

    //         $status = $aliexpressDataViews[$sku]->value ?? null;
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
    //         $views = $aliexpressMetrics[$sku]->views ?? null;
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

        $shopifyData = ShopifySku::mapByProductSkus($productMasters->pluck('sku')->filter()->unique()->values()->all());

        $aliexpressListingStatus = AliexpressListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $aliexpressDataViews = AliexpressDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $aliexpressMetrics = AliExpressSheetData::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $listedCount = 0;
        $zeroInvOfListed = 0;
        $liveCount = 0;
        $zeroViewCount = 0;

        foreach ($productMasters as $item) {
            $sku = strtoupper(trim($item->sku));
            $inv = $shopifyData->get($item->sku)?->inv ?? 0;

            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false) continue;

            // --- Amazon Listing Status ---
            $status = $aliexpressListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $aliexpressDataViews[$sku]->value ?? null;
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
            $metricRecord = $aliexpressMetrics[$sku] ?? null;
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
