<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\WayfairDataView;
use App\Models\WayfairListingStatus;
use App\Models\WayfairProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\WaifairProductSheet;
use Illuminate\Support\Facades\DB;

class WayfairZeroController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function wayfairZeroView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('wayfair_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.WayfairZeroview', [
            'mode' => $mode,
            'demo' => $demo,
            'wayfairPercentage' => $percentage

        ]);
    }

    // public function getViewWayfairZeroData(Request $request)
    // {
    //     // 1. Fetch all ProductMaster rows as the base
    //     $productMasters = ProductMaster::orderBy('parent', 'asc')
    //         ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
    //         ->orderBy('sku', 'asc')
    //         ->get();

    //     // 2. Fetch Google Sheet data
    //     $response = $this->apiController->fetchDataFromWayfairMasterGoogleSheet();

    //     if ($response->getStatusCode() === 200) {
    //         $sheetData = $response->getData();
    //         // Index sheet data by SKU for fast lookup
    //         $sheetSkuMap = [];
    //         foreach ($sheetData->data as $item) {
    //             $sku = $item->{'(Child) sku'} ?? '';
    //             if (!empty($sku)) {
    //                 $sheetSkuMap[$sku] = $item;
    //             }
    //         }

    //         // 3. Prepare SKU list for related models
    //         $skus = $productMasters->pluck('sku')->unique()->toArray();

    //         // 4. Fetch related data
    //         $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
    //         $nrValues = WayfairDataView::pluck('value', 'sku');
    //         $jungleScoutData = JungleScoutProductData::all()
    //             ->groupBy('parent')
    //             ->map(function ($group) {
    //                 $validPrices = $group->filter(function ($item) {
    //                     $price = $item->data['price'] ?? null;
    //                     return is_numeric($price) && $price > 0;
    //                 })->pluck('data.price');
    //                 return [
    //                     'scout_parent' => $group->first()->parent,
    //                     'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
    //                     'product_count' => $group->count(),
    //                     'all_data' => $group->map(function ($item) {
    //                         $data = $item->data;
    //                         if (isset($data['price'])) {
    //                             $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
    //                         }
    //                         return $data;
    //                     })->toArray()
    //                 ];
    //             });

    //         // 5. Build the result using ProductMaster as the base
    //         $processedData = [];
    //         foreach ($productMasters as $pm) {
    //             $sku = $pm->sku;
    //             $parentSku = $pm->parent;

    //             // Start with ProductMaster base
    //             $item = [
    //                 'Parent' => $parentSku,
    //                 'sku' => $sku,
    //             ];

    //             // Merge Google Sheet data if exists
    //             if (isset($sheetSkuMap[$sku])) {
    //                 foreach ((array)$sheetSkuMap[$sku] as $key => $val) {
    //                     $item[$key] = $val;
    //                 }
    //             }

    //             // Add JungleScout data if parent ASIN matches
    //             if (!empty($parentSku) && $jungleScoutData->has($parentSku)) {
    //                 $scoutData = $jungleScoutData[$parentSku];
    //                 $item['scout_data'] = [
    //                     'scout_parent' => $scoutData['scout_parent'],
    //                     'min_price' => $scoutData['min_price'],
    //                     'product_count' => $scoutData['product_count'],
    //                     'all_data' => $scoutData['all_data']
    //                 ];
    //             }

    //             // Shopify data
    //             $item['INV'] = $shopifyData->has($sku) ? $shopifyData[$sku]->inv : 0;
    //             $item['L30'] = $shopifyData->has($sku) ? $shopifyData[$sku]->quantity : 0;

    //             // NR and A_Z fields
    //             $item['NR'] = 'REQ';
    //             $item['A_Z_Reason'] = null;
    //             $item['A_Z_ActionRequired'] = null;
    //             $item['A_Z_ActionTaken'] = null;
    //             if (isset($nrValues[$sku])) {
    //                 $val = $nrValues[$sku];
    //                 if (is_array($val)) {
    //                     $item['NR'] = $val['NR'] ?? 'REQ';
    //                     $item['A_Z_Reason'] = $val['A_Z_Reason'] ?? null;
    //                     $item['A_Z_ActionRequired'] = $val['A_Z_ActionRequired'] ?? null;
    //                     $item['A_Z_ActionTaken'] = $val['A_Z_ActionTaken'] ?? null;
    //                 } else {
    //                     $decoded = json_decode($val, true);
    //                     $item['NR'] = $decoded['NR'] ?? 'REQ';
    //                     $item['A_Z_Reason'] = $decoded['A_Z_Reason'] ?? null;
    //                     $item['A_Z_ActionRequired'] = $decoded['A_Z_ActionRequired'] ?? null;
    //                     $item['A_Z_ActionTaken'] = $decoded['A_Z_ActionTaken'] ?? null;
    //                 }
    //             }

    //             $processedData[] = (object)$item;
    //         }

    //         // 6. Filter: INV > 0 and SESS L30 == 0 (or your metric)
    //         $filteredResults = array_filter($processedData, function ($item) {
    //             $childSku = $item->{'(Child) sku'} ?? '';
    //             $inv = $item->INV ?? 0;
    //             $sessL30 = isset($item->{'Sess30'}) ? (int)$item->{'Sess30'} : 0;
    //             return
    //                 stripos($childSku, 'PARENT') === false &&
    //                 $inv > 0 &&
    //                 $sessL30 == 0;
    //         });

    //         // 7. Return the processed data with filters applied
    //         return response()->json([
    //             'message' => 'Data fetched successfully',
    //             'data' => array_values($filteredResults),
    //             'status' => 200
    //         ]);
    //     } else {
    //         return response()->json([
    //             'message' => 'Failed to fetch data from Google Sheet',
    //             'status' => $response->getStatusCode()
    //         ], $response->getStatusCode());
    //     }
    // } 

    public function getViewWayfairZeroData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('wayfair_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
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

        $temuMetrics = WaifairProductSheet::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
            ->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuDataViews = WayfairDataView::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
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

        $row = WayfairDataView::firstOrCreate(['sku' => $sku]);
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
        // 1. Fetch all ProductMaster rows as the base
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // 2. Fetch Google Sheet data
        $response = $this->apiController->fetchDataFromWayfairMasterGoogleSheet();

        $sheetSkuMap = [];
        if ($response->getStatusCode() === 200) {
            $sheetData = $response->getData();
            foreach ($sheetData->data as $item) {
                $sku = $item->{'(Child) sku'} ?? '';
                if (!empty($sku)) {
                    $sheetSkuMap[$sku] = $item;
                }
            }
        }

        // 3. Prepare SKU list for related models
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        // 4. Fetch related data
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // 5. Count SKUs where INV > 0 and Sess30 == 0
        $zeroViewCount = 0;
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $inv = $shopifyData->has($sku) ? $shopifyData[$sku]->inv : 0;
            $sess30 = isset($sheetSkuMap[$sku]) ? (int)($sheetSkuMap[$sku]->{'Sess30'} ?? 0) : 0;
            if ($inv > 0 && $sess30 == 0) {
                $zeroViewCount++;
            }
        }

        return $zeroViewCount;
    }


    // public function getLivePendingAndZeroViewCounts()
    // {
    //     $productMasters = ProductMaster::whereNull('deleted_at')->get();
    //     $skus = $productMasters->pluck('sku')->unique()->toArray();

    //     $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $ebayDataViews = WayfairListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

    //     $ebayMetrics = WaifairProductSheet::whereIn('sku', $skus)->get()->keyBy('sku');


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

        $wayfairListingStatus = WayfairListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $wayfairDataViews = WayfairDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $wayfairMetrics = WaifairProductSheet::whereIn('sku', $skus)->get()
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
            $status = $wayfairListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $wayfairDataViews[$sku]->value ?? null;
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
            $metricRecord = $wayfairMetrics[$sku] ?? null;
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