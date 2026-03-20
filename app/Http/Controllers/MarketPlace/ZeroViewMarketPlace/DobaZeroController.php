<?php

namespace App\Http\Controllers\MarketPlace\ZeroViewMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\DobaDataView;
use App\Models\DobaListingStatus;
use App\Models\DobaMetric;
use App\Models\DobaSheetdata;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DobaZeroController extends Controller
{

    public function dobaZeroview(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('market-places.zero-market-places.dobaZeroView', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }

    // public function getViewDobaZeroData(Request $request)
    // {
    //             // 1. Fetch all ProductMaster rows
    //     $productMasters = ProductMaster::whereNull('deleted_at')->get();

    //     // Normalize SKUs (avoid case/space mismatch)
    //     $skus = $productMasters->pluck('sku')->map(fn($s) => strtoupper(trim($s)))->unique()->toArray();

    //     // 2. Fetch ShopifySku for those SKUs
    //     $shopifyData = ShopifySku::whereIn('sku', $skus)->get()
    //         ->keyBy(fn($s) => strtoupper(trim($s->sku)));

    //     // 3. Fetch DobaSheetdata for views
    //     $dobaSheetData = DobaSheetdata::whereIn('sku', $skus)->get()
    //         ->keyBy(fn($s) => strtoupper(trim($s->sku)));

    //     // 4. Fetch DobaMetric for remaining data
    //     $dobaMetrics = DobaMetric::whereIn('sku', $skus)->get()
    //         ->keyBy(fn($s) => strtoupper(trim($s->sku)));

    //     // 5. Fetch DobaDataView for status
    //     $dobaDataViews = DobaDataView::whereIn('sku', $skus)->get()
    //         ->keyBy(fn($s) => strtoupper(trim($s->sku)));

    //     $result = [];
    //     foreach ($productMasters as $pm) {
    //         $sku = strtoupper(trim($pm->sku));
    //         $parent = $pm->parent;

    //         // Skip parent SKUs
    //         if (stripos($sku, 'PARENT') !== false) continue;

    //         $shopify = $shopifyData[$sku] ?? null;

    //         $inv = $shopify ? $shopify->inv : 0;
    //         $inv = floatval($inv);
    //         $ov_l30 = $shopify ? $shopify->quantity : 0;
    //         $ov_dil = ($inv > 0) ? round($ov_l30 / $inv, 4) : 0;

    //         // Get views from DobaSheetdata
    //         $sheet = $dobaSheetData[$sku] ?? null;
    //         $views = null;

    //         if ($sheet) {
    //             // Direct field
    //             if (!empty($sheet->views) || $sheet->views === "0" || $sheet->views === 0) {
    //                 $views = (int)$sheet->views;
    //             }
    //             // Or inside JSON column `value`
    //             elseif (!empty($sheet->value)) {
    //                 $sheetData = json_decode($sheet->value, true);
    //                 if (isset($sheetData['views'])) {
    //                     $views = (int)$sheetData['views'];
    //                 }
    //             }
    //         }

    //         // Get remaining data from DobaMetric
    //         $metric = $dobaMetrics[$sku] ?? null;
    //         $quantity_l30 = $metric ? $metric->quantity_l30 : 0;
    //         $quantity_l60 = $metric ? $metric->quantity_l60 : 0;
    //         $impressions = $metric ? $metric->impressions : 0;
    //         $clicks = $metric ? $metric->clicks : 0;
    //         $anticipated_income = $metric ? $metric->anticipated_income : 0;

    //         // Only include rows where inv > 0 and views == 0 (zero view)
    //         if ($inv > 0 && $views === 0) {
    //             // Fetch DobaDataView values
    //             $dobaView = $dobaDataViews[$sku] ?? null;
    //             $value = $dobaView ? $dobaView->value : [];
    //             if (is_string($value)) {
    //                 $value = json_decode($value, true) ?: [];
    //             }

    //             $row = [
    //                 'parent' => $parent,
    //                 'sku' => $sku,
    //                 'inv' => $inv,
    //                 'ov_l30' => $ov_l30,
    //                 'ov_dil' => $ov_dil,
    //                 'views' => $views,
    //                 'quantity_l30' => $quantity_l30,
    //                 'quantity_l60' => $quantity_l60,
    //                 'impressions' => $impressions,
    //                 'clicks' => $clicks,
    //                 'anticipated_income' => $anticipated_income,
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

    public function getViewDobaZeroData(Request $request)
    {
        // Get percentage directly from database (no cache)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Doba')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
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

        $temuMetrics = DobaSheetdata::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
            ->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuDataViews = DobaDataView::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
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

        $row = DobaDataView::firstOrCreate(
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

    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = DobaDataView::whereIn('sku', $skus)->get()->keyBy('sku');

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
    //     $ebayDataViews = DobaListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
    //     // $ebayMetrics = Ebay2Metric::whereIn('sku', $skus)->get()->keyBy('sku');

    //     $ebayMetrics = DB::connection('apicentral')
    //         ->table('doba_api_data as api_doba')
    //         ->select(
    //             'api_doba.spu as sku',
    //             'api_doba.sellPrice as doba_price',
    //             DB::raw('COALESCE(doba_m.l30, 0) as l30'),
    //             DB::raw('COALESCE(doba_m.l60, 0) as l60')
    //         )
    //         ->leftJoin('doba_metrics as doba_m', 'api_doba.spu', '=', 'doba_m.sku')
    //         ->whereIn('api_doba.spu', $skus)
    //         ->get()
    //         ->keyBy('sku');


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

        $dobaListingStatus = DobaListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $dobaDataViews = DobaDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $dobaMetrics = DobaSheetdata::whereIn('sku', $skus)->get()
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
            $status = $dobaListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $dobaDataViews[$sku]->value ?? null;
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
            $metricRecord = $dobaMetrics[$sku] ?? null;
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

            // $metricRecord = $dobaMetrics[$sku] ?? null;
            // $views = null;

            // if ($metricRecord) {
            //     // Direct field (if column exists)
            //     if (!empty($metricRecord->views)) {
            //         $views = $metricRecord->views;
            //     }
            //     // Or inside JSON column `value`
            //     elseif (!empty($metricRecord->value)) {
            //         $metricData = json_decode($metricRecord->value, true);
            //         $views = $metricData['views'] ?? null;
            //     }
            // }

            // if ($inv > 0 && $views !== null && intval($views) === 0) {
            //     $zeroViewCount++;
            // }
        }

        $livePending = $listedCount - $liveCount;

        return [
            'live_pending' => $livePending,
            'zero_view' => $zeroViewCount,
        ];
    }
}