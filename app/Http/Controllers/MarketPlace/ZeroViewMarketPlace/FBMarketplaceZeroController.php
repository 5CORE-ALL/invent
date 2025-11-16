<?php

namespace App\Http\Controllers\MarketPlace\ZeroViewMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\FBMarketplaceDataView;
use App\Models\FBMarketplaceListingStatus;
use App\Models\FbMarketplaceSheetdata;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FBMarketplaceZeroController extends Controller
{
    public function fbMarketplaceZeroview(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        return view('market-places.zero-market-places.fbMarketplaceZeroView', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }

    // public function getViewFBMarketplaceZeroData(Request $request)
    // {
    //     $productMasters = ProductMaster::orderBy('parent', 'asc')
    //         ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
    //         ->orderBy('sku', 'asc')
    //         ->get();

    //     $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
    //     $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $fbMarketplaceDataViews = FBMarketplaceDataView::whereIn('sku', $skus)->get()->keyBy('sku');

    //     $result = [];
    //     foreach ($productMasters as $pm) {
    //         $sku = $pm->sku;
    //         $parent = $pm->parent;
    //         $shopify = $shopifyData[$sku] ?? null;

    //         $inv = $shopify ? $shopify->inv : 0;
    //         $ov_l30 = $shopify ? $shopify->quantity : 0;
    //         $ov_dil = ($inv > 0) ? round($ov_l30 / $inv, 4) : 0;

    //         if ($inv > 0) {
    //             $fbView = $fbMarketplaceDataViews[$sku] ?? null;
    //             $value = $fbView ? $fbView->value : [];
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

     public function getViewFBMarketplaceZeroData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('fb_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'FBMarketplace')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch ProductMaster records excluding PARENT rows (do as much filtering in DB as possible)
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->whereNotNull('sku')
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->get();

        // Normalize SKUs (uppercase + trim) and unique
        $skus = $productMasters->pluck('sku')
            ->map(fn($s) => strtoupper(trim($s)))
            ->filter() // remove empty
            ->unique()
            ->values()
            ->toArray();

        if (empty($skus)) {
            return response()->json([
                'message' => 'No SKUs found',
                'data' => [],
                'status' => 200
            ]);
        }

        // Fetch related data keyed by normalized SKU
        $shopifyData = ShopifySku::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
            ->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuMetrics = FbMarketplaceSheetdata::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
            ->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuDataViews = FBMarketplaceDataView::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
            ->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $processedData = [];
        $slNo = 1;

        foreach ($productMasters as $productMaster) {
            $sku = strtoupper(trim($productMaster->sku));
            if ($sku === '') continue; // safety

            // Get Shopify data safely
            $shopify = $shopifyData[$sku] ?? null;
            $inv = $shopify->inv ?? 0;
            $quantity = $shopify->quantity ?? 0;

            // Skip items with no inventory
            if ($inv <= 0) continue;

            // Determine views (clicks) strictly:
            // - If metric->views exists (not null), cast to int and use it.
            // - Else if metric->value has 'views', cast to int and use it.
            // - Else: views is considered NULL => we must skip (we only want views === 0)
            $metric = $temuMetrics[$sku] ?? null;
            $views = null;

            if ($metric) {
                // prefer direct field if present and not null
                if (isset($metric->views) && $metric->views !== null && $metric->views !== '') {
                    // cast numeric-looking values to int; otherwise (non-numeric) attempt intval
                    $views = is_numeric($metric->views) ? (int)$metric->views : intval($metric->views);
                } else if (!empty($metric->value)) {
                    $metricValue = json_decode($metric->value, true);
                    if (is_array($metricValue) && array_key_exists('views', $metricValue)) {
                        $views = is_numeric($metricValue['views']) ? (int)$metricValue['views'] : intval($metricValue['views']);
                    }
                }
            }

            // Important: we only want views exactly equal to 0 (not null, not >0)
            if (!is_int($views) || $views !== 0) {
                // if views is null or not 0, skip this SKU
                continue;
            }

            // Fetch NR and A-Z Reason fields from DataView (if present)
            $dataViewRaw = $temuDataViews[$sku]->value ?? [];
            if (is_string($dataViewRaw)) {
                $dataView = json_decode($dataViewRaw, true) ?: [];
            } else {
                $dataView = is_array($dataViewRaw) ? $dataViewRaw : [];
            }

            $values = $productMaster->Values ?? [];

            $processedItem = [
                'parent' => $productMaster->parent ?? null,
                'SL No.' => $slNo++,
                'sku' => $sku,
                'inv' => $inv,
                'ov_l30' => $quantity,
                'views' => $views, // will be 0 here
                'product_impressions_l30' => 0, // (not available in your snippet)
                'LP' => $values['lp'] ?? 0,
                'Ship' => $values['ship'] ?? 0,
                'COGS' => $values['cogs'] ?? 0,
                'NR' => $dataView['NR'] ?? 'REQ',
                'A_Z_Reason' => $dataView['A_Z_Reason'] ?? null,
                'A_Z_ActionRequired' => $dataView['A_Z_ActionRequired'] ?? null,
                'A_Z_ActionTaken' => $dataView['A_Z_ActionTaken'] ?? null,
                'percentage' => $percentageValue,
            ];

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => array_values($processedData),
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

        $row = FBMarketplaceDataView::firstOrCreate(
            ['sku' => $sku],
            ['value' => json_encode([])]
        );

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

    public function getLivePendingAndZeroViewCounts()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();

        // Normalize SKUs (avoid case/space mismatch)
        $skus = $productMasters->pluck('sku')->map(fn($s) => strtoupper(trim($s)))->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $fbMarketplaceListingStatus = FBMarketplaceListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $fbMarketplaceDataViews = FBMarketplaceDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $fbMarketplaceMetrics = FbMarketplaceSheetdata::whereIn('sku', $skus)->get()
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
            $status = $fbMarketplaceListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $fbMarketplaceDataViews[$sku]->value ?? null;
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
            $metricRecord = $fbMarketplaceMetrics[$sku] ?? null;
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
            if ($inv > 0 && $views === 0 && !$hasNR) {
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
