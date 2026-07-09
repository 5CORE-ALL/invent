<?php

namespace App\Http\Controllers\MarketPlace\ZeroViewMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\WalmartDataView;
use App\Models\WalmartListingStatus;
use App\Models\WalmartProductSheet;
use App\Models\ZendropDataView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\WalmartCampaignReport;

class WalmartZeroController extends Controller
{
    public function walmartZeroview(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        return view('market-places.zero-market-places.walmartZeroView', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }

    public function updateWalmartPrice(Request $request) {
        try {
            $validated = $request->validate([
                'sku' => 'required|exists:walmart_product_sheet,sku',
                'price' => 'required|numeric',
            ]);

            
            $walmartData = WalmartProductSheet::find($validated['sku']);

            $walmartData->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Walmart price and metrics updated successfully.',
                'data' => $walmartData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getViewWalmartZeroData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('walmart_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
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

        $temuMetrics = WalmartProductSheet::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
            ->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuDataViews = WalmartDataView::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
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
                'Parent' => $productMaster->parent ?? null,
                'SL No.' => $slNo++,
                'Sku' => $sku,
                'INV' => $inv,
                'L30' => $quantity,
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


    // public function getViewWalmartZeroData(Request $request)
    // {
    //     // Get percentage from cache or database
    //     $percentage = Cache::remember('walmart_marketplace_percentage', now()->addDays(30), function () {
    //         $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
    //         return $marketplaceData ? $marketplaceData->percentage : 100;
    //     });
    //     $percentageValue = $percentage / 100;

    //     // Fetch all ProductMaster records
    //     $productMasters = ProductMaster::whereNull('deleted_at')->get();

    //     // Normalize SKUs
    //     $skus = $productMasters->pluck('sku')->map(fn($s) => strtoupper(trim($s)))->unique()->toArray();

    //     // Fetch related data
    //     $shopifyData = ShopifySku::whereIn('sku', $skus)->get()
    //         ->keyBy(fn($s) => strtoupper(trim($s->sku)));

    //     $walmartMetrics = WalmartProductSheet::whereIn('sku', $skus)->get()
    //         ->keyBy(fn($s) => strtoupper(trim($s->sku)));

    //     $temuDataViews = WalmartDataView::whereIn('sku', $skus)->get()
    //         ->keyBy(fn($s) => strtoupper(trim($s->sku)));

    //     $processedData = [];
    //     $slNo = 1;

    //     foreach ($productMasters as $productMaster) {
    //         $sku = strtoupper(trim($productMaster->sku));
    //         $isParent = stripos($sku, 'PARENT') !== false;
    //         if ($isParent) continue;

    //         // Get inventory from ShopifySku
    //         $inv = $shopifyData->get($item->sku)?->inv ?? 0;
    //         $quantity = $shopifyData[$sku]->quantity ?? 0;

    //         // Skip items with no inventory
    //         if ($inv <= 0) {
    //             continue;
    //         }

    //         // Get views  from WalmartMetric
    //         $clicks = null;
    //         // $impressions = null;
    //         $metric = $walmartMetrics[$sku] ?? null;
    //         if ($metric) {
    //             $clicks = $metric->views ?? null;
    //             // $impressions = $metric->product_impressions_l30 ?? null;
    //             if ($clicks === null && !empty($metric->value)) {
    //                 $metricValue = json_decode($metric->value, true);
    //                 $clicks = $metricValue['views'] ?? null;
    //                 // $impressions = $metricValue['product_impressions_l30'] ?? null;
    //             }
    //         }

    //         // Skip items with clicks > 0 (only show zero-view items)
    //         if (!is_null($clicks) && $clicks > 0) {
    //             continue;
    //         }

    //         // Fetch NR and A-Z Reason fields
    //         $dataView = $temuDataViews[$sku]->value ?? [];
    //         if (is_string($dataView)) {
    //             $dataView = json_decode($dataView, true);
    //         }

    //         $values = $productMaster->Values ?? [];

    //         $processedItem = [
    //             'Parent' => $productMaster->parent ?? null,
    //             'SL No.' => $slNo++,
    //             'Sku' => $sku,
    //             'INV' => $inv,
    //             'L30' => $quantity, // Use Shopify quantity for L30
    //             'product_clicks_l30' => $clicks ?? 0,
    //             'product_impressions_l30' => $impressions ?? 0,
    //             'LP' => $values['lp'] ?? 0,
    //             'Ship' => $values['ship'] ?? 0,
    //             'COGS' => $values['cogs'] ?? 0,
    //             'NR' => $dataView['NR'] ?? 'REQ',
    //             'A_Z_Reason' => $dataView['A_Z_Reason'] ?? null,
    //             'A_Z_ActionRequired' => $dataView['A_Z_ActionRequired'] ?? null,
    //             'A_Z_ActionTaken' => $dataView['A_Z_ActionTaken'] ?? null,
    //             'percentage' => $percentageValue,
    //         ];

    //         $processedData[] = $processedItem;
    //     }

    //     return response()->json([
    //         'message' => 'Data fetched successfully',
    //         'data' => array_values($processedData),
    //         'status' => 200
    //     ]);
    // }

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

        $row = WalmartDataView::firstOrCreate(
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
        $row->value = json_encode($value);
        $row->save();

        return response()->json([
            'status' => 200,
            'message' => 'Reason and actions updated successfully.'
        ]);
    }

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
    //     $ebayDataViews = WalmartListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

    //     $ebayMetrics = DB::connection('apicentral')
    //         ->table('walmart_api_data as api')
    //         ->select(
    //             'api.sku',
    //             'api.price',
    //             DB::raw('COALESCE(m.l30, 0) as l30'),
    //             DB::raw('COALESCE(m.l60, 0) as l60')
    //         )
    //         ->leftJoin('walmart_metrics as m', 'api.sku', '=', 'm.sku')
    //         ->whereIn('api.sku', $skus)
    //         ->get()
    //         ->keyBy('sku');

    //     $walmartSheetViews = WalmartProductSheet::select('sku', 'views')
    //         ->whereIn('sku', $skus)
    //         ->get()
    //         ->keyBy('sku');


    //     $listedCount = 0;
    //     $zeroInvOfListed = 0;
    //     $liveCount = 0;
    //     $zeroViewCount = 0;

    //     foreach ($productMasters as $item) {
    //         $sku = trim($item->sku);
    //         $inv = $shopifyData->get($item->sku)?->inv ?? 0;
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
    //         $views = $walmartSheetViews[$sku]->views ?? null;
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

        $walmartListingStatus = WalmartListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $walmartDataViews = WalmartDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $walamrtMetrics = WalmartProductSheet::whereIn('sku', $skus)->get()
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
            $status = $walmartListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $walmartDataViews[$sku]->value ?? null;
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
            $metricRecord = $walamrtMetrics[$sku] ?? null;
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

            // Check NR status
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