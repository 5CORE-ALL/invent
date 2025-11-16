<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\AmazonDatasheet; // Add this at the top with other use statements
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\AmazonDataView; // Import the AmazonDataView model
use Illuminate\Support\Facades\DB;

class AmazonLowVisibilityController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function amazonLowVisibility(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.amazonLowVisibilityView', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

    public function getViewAmazonLowVisibilityData(Request $request)
    {
        // 1. Fetch all ProductMaster rows (base)
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Fetch AmazonDataView for all SKUs
        $amazonDataViews = AmazonDataView::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // 2. Fetch AmazonDatasheet and ShopifySku for those SKUs
        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });
        $shopifyData = ShopifySku::whereIn('sku', $skus)->where('inv', '>', 0)->get()->keyBy('sku');

        // 3. JungleScout Data (by parent)
        $parents = $productMasters->pluck('parent')->filter()->unique()->map('strtoupper')->values()->all();
        $jungleScoutData = JungleScoutProductData::whereIn('parent', $parents)
            ->get()
            ->groupBy(function ($item) {
                return strtoupper(trim($item->parent));
            });

        // 4. Marketplace percentage
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            return MarketplacePercentage::where('marketplace', 'Amazon')->value('percentage') ?? 100;
        });
        $percentage = $percentage / 100;

        // 5. Build final data
        $result = [];
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            if (!$shopify || $shopify->inv <= 0) {
                continue;
            }

            $row = [];
            $row['Parent'] = $parent;
            $row['(Child) sku'] = $pm->sku;

            // --- Add Reason, ActionRequired, ActionTaken ---
            $dataView = $amazonDataViews[$sku] ?? null;
            $value = $dataView ? $dataView->value : [];
            $row['A_Z_Reason'] = $value['A_Z_Reason'] ?? '';
            $row['A_Z_ActionRequired'] = $value['A_Z_ActionRequired'] ?? '';
            $row['A_Z_ActionTaken'] = $value['A_Z_ActionTaken'] ?? '';
            $row['NRL'] = $value['NR'] ?? '';
            $row['FBA'] = $value['FBA'] ?? '';

            // Add AmazonDatasheet fields if available
            if ($amazonSheet) {
                $row['A_L30'] = $row['A_L30'] ?? $amazonSheet->units_ordered_l30;
                $row['Sess30'] = $row['Sess30'] ?? $amazonSheet->sessions_l30;
                $row['price'] = $row['price'] ?? $amazonSheet->price;
                $row['sessions_l60'] = $row['sessions_l60'] ?? $amazonSheet->sessions_l60;
                $row['units_ordered_l60'] = $row['units_ordered_l60'] ?? $amazonSheet->units_ordered_l60;
            }

            // Add Shopify fields
            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;

            // LP and Ship from ProductMaster
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
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
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // Formulas
            $price = isset($row['price']) ? floatval($row['price']) : 0;
            $units_ordered_l30 = isset($row['A_L30']) ? floatval($row['A_L30']) : 0;
            $row['Total_pft'] = round((($price * $percentage) - $lp - $ship) * $units_ordered_l30, 2);
            $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
            $row['ROI_percentage'] = round($lp > 0 ? ((($price * $percentage) - $lp - $ship) / $lp) * 100 : 0, 2);
            $row['T_COGS'] = round($lp * $units_ordered_l30, 2);

            // JungleScout
            $parentKey = strtoupper($parent);
            if (!empty($parentKey) && $jungleScoutData->has($parentKey)) {
                $row['scout_data'] = $jungleScoutData[$parentKey];
            }

            // Percentage, LP, Ship
            $row['percentage'] = $percentage;
            $row['LP_productmaster'] = $lp;
            $row['Ship_productmaster'] = $ship;

            // Image path (from Shopify or ProductMaster)
            $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

            // --- Buyer Link & Seller Link Validation ---
            $buyerLink = $row['AMZ LINK BL'] ?? null;
            $sellerLink = $row['AMZ LINK SL'] ?? null;
            $row['AMZ LINK BL'] = (filter_var($buyerLink, FILTER_VALIDATE_URL)) ? $buyerLink : null;
            $row['AMZ LINK SL'] = (filter_var($sellerLink, FILTER_VALIDATE_URL)) ? $sellerLink : null;

            $result[] = (object) $row;
        }

        // 6. Apply the LowVisibility-specific filters
        $result = array_filter($result, function ($item) {
            $childSku = $item->{'(Child) sku'} ?? '';
            $sess30 = $item->Sess30 ?? 0;

            return
                stripos($childSku, 'PARENT') === false &&
                $sess30 >= 1 &&
                $sess30 <= 100;
        });

        $result = array_values($result);

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $result,
            'status' => 200,
            'debug' => [
                'jungle_scout_parents' => $jungleScoutData->keys()->take(5),
                'matched_parents' => collect($result)
                    ->filter(fn($item) => isset($item->scout_data))
                    ->pluck('Parent')
                    ->unique()
                    ->values()
            ]
        ]);
    }


    public function getViewAmazonLowVisibilityDataFba()
    {
        // 1. Fetch all ProductMaster rows (base)
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Fetch AmazonDataView for all SKUs with FBA filter
        $amazonDataViews = AmazonDataView::whereIn('sku', $skus)
            ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(value, '$.FBA'))"), 'FBA')
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        // Get only SKUs that have FBA data
        $fbaSkus = $amazonDataViews->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();

        // 2. Fetch AmazonDatasheet and ShopifySku for FBA SKUs only
        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $fbaSkus)
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        $shopifyData = ShopifySku::whereIn('sku', $fbaSkus)
            ->where('inv', '>', 0)
            ->get()
            ->keyBy('sku');

        // 3. REMOVED Google Sheet fetch

        // 4. JungleScout Data (by parent)
        $parents = $productMasters->whereIn('sku', $fbaSkus)
            ->pluck('parent')
            ->filter()
            ->unique()
            ->map('strtoupper')
            ->values()
            ->all();

        $jungleScoutData = JungleScoutProductData::whereIn('parent', $parents)
            ->get()
            ->groupBy(function ($item) {
                return strtoupper(trim($item->parent));
            });

        // 5. Marketplace percentage
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            return MarketplacePercentage::where('marketplace', 'Amazon')->value('percentage') ?? 100;
        });
        $percentage = $percentage / 100;

        // 6. Build final data
        $result = [];
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            // Skip if not an FBA SKU
            if (!in_array($sku, $fbaSkus)) {
                continue;
            }

            $parent = $pm->parent;
            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            if (!$shopify || $shopify->inv <= 0) {
                continue;
            }

            $row = [];
            $row['Parent'] = $parent;
            $row['(Child) sku'] = $pm->sku;

            // --- Add FBA and other data ---
            $dataView = $amazonDataViews[$sku];
            $value = $dataView->value ?? [];
            $row['FBA'] = $value['FBA'] ?? '';
            $row['A_Z_Reason'] = $value['A_Z_Reason'] ?? '';
            $row['A_Z_ActionRequired'] = $value['A_Z_ActionRequired'] ?? '';
            $row['A_Z_ActionTaken'] = $value['A_Z_ActionTaken'] ?? '';
            $row['NRL'] = $value['NR'] ?? '';

            // Merge AmazonDatasheet data if exists
            if ($amazonSheet) {
                $row['A_L30'] = $row['A_L30'] ?? $amazonSheet->units_ordered_l30;
                $row['Sess30'] = $row['Sess30'] ?? $amazonSheet->sessions_l30;
                $row['price'] = $row['price'] ?? $amazonSheet->price;
                $row['sessions_l60'] = $row['sessions_l60'] ?? $amazonSheet->sessions_l60;
                $row['units_ordered_l60'] = $row['units_ordered_l60'] ?? $amazonSheet->units_ordered_l60;
            }

            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;

            // LP and Ship calculations
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
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
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // Calculate formulas
            $price = isset($row['price']) ? floatval($row['price']) : 0;
            $units_ordered_l30 = isset($row['A_L30']) ? floatval($row['A_L30']) : 0;
            $row['Total_pft'] = round((($price * $percentage) - $lp - $ship) * $units_ordered_l30, 2);
            $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
            $row['ROI_percentage'] = round($lp > 0 ? ((($price * $percentage) - $lp - $ship) / $lp) * 100 : 0, 2);
            $row['T_COGS'] = round($lp * $units_ordered_l30, 2);

            // Add JungleScout data
            $parentKey = strtoupper($parent);
            if (!empty($parentKey) && $jungleScoutData->has($parentKey)) {
                $row['scout_data'] = $jungleScoutData[$parentKey];
            }

            // Additional data
            $row['percentage'] = $percentage;
            $row['LP_productmaster'] = $lp;
            $row['Ship_productmaster'] = $ship;
            $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

            // Validate links
            $buyerLink = $row['AMZ LINK BL'] ?? null;
            $sellerLink = $row['AMZ LINK SL'] ?? null;
            $row['AMZ LINK BL'] = (filter_var($buyerLink, FILTER_VALIDATE_URL)) ? $buyerLink : null;
            $row['AMZ LINK SL'] = (filter_var($sellerLink, FILTER_VALIDATE_URL)) ? $sellerLink : null;

            $result[] = (object) $row;
        }

        // Only filter for PARENT (no session filter for FBA view)
        $result = array_filter($result, function ($item) {
            $childSku = $item->{'(Child) sku'} ?? '';
            return stripos($childSku, 'PARENT') === false;
        });

        $result = array_values($result);

        return response()->json([
            'message' => 'FBA data fetched successfully',
            'data' => $result,
            'status' => 200,
            'debug' => [
                'total_fba_records' => count($result),
                'jungle_scout_parents' => $jungleScoutData->keys()->take(5),
                'matched_parents' => collect($result)
                    ->filter(fn($item) => isset($item->scout_data))
                    ->pluck('Parent')
                    ->unique()
                    ->values()
            ]
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

        $row = AmazonDataView::firstOrCreate(['sku' => $sku]);
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



    public function amazonLowVisibilityFba(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        // ✅ Get only rows where JSON column "FBA" = "FBA"
        $fbaData = AmazonDataView::where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(value, '$.FBA'))"), 'FBA')->get();

        return view('market-places.amazonLowVisibilityViewfba', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'fbaData' => $fbaData
        ]);
    }

    public function amazonLowVisibilityFbm(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        // ✅ Get only rows where JSON column "FBA" = "FBA"
        $fbaData = AmazonDataView::where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(value, '$.FBA'))"), 'FBA')->get();

        return view('market-places.amazonLowVisibilityViewfbm', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'fbaData' => $fbaData
        ]);
    }

    public function amazonLowVisibilityBoth(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        // ✅ Get only rows where JSON column "FBA" = "FBA"
        $fbaData = AmazonDataView::where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(value, '$.FBA'))"), 'FBA')->get();

        return view('market-places.amazonLowVisibilityViewboth', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'fbaData' => $fbaData
        ]);
    }


    public function getViewAmazonLowVisibilityDataBoth(Request $request)
    {
        // 1. Fetch all ProductMaster rows (base)
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Fetch AmazonDataView for all SKUs with BOTH filter
        $amazonDataViews = AmazonDataView::whereIn('sku', $skus)
            ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(value, '$.FBA'))"), 'BOTH')
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        // Get only SKUs that have BOTH data
        $fbaSkus = $amazonDataViews->pluck('sku')->toArray();

        // 2. Fetch AmazonDatasheet and ShopifySku for BOTH SKUs only
        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $fbaSkus)
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        $shopifyData = ShopifySku::whereIn('sku', $fbaSkus)
            ->where('inv', '>', 0)
            ->get()
            ->keyBy('sku');

        // 3. JungleScout Data (by parent)
        $parents = $productMasters->whereIn('sku', $fbaSkus)
            ->pluck('parent')
            ->filter()
            ->unique()
            ->map('strtoupper')
            ->values()
            ->all();

        $jungleScoutData = JungleScoutProductData::whereIn('parent', $parents)
            ->get()
            ->groupBy(function ($item) {
                return strtoupper(trim($item->parent));
            });

        // 4. Marketplace percentage
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            return MarketplacePercentage::where('marketplace', 'Amazon')->value('percentage') ?? 100;
        });
        $percentage = $percentage / 100;

        // 5. Build final data
        $result = [];
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            // Skip if not a BOTH SKU
            if (!in_array($sku, $fbaSkus)) {
                continue;
            }

            $parent = $pm->parent;
            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            if (!$shopify || $shopify->inv <= 0) {
                continue;
            }

            $row = [];
            $row['Parent'] = $parent;
            $row['(Child) sku'] = $pm->sku;

            // --- Add BOTH and other data ---
            $dataView = $amazonDataViews[$sku];
            $value = $dataView->value ?? [];
            $row['FBA'] = $value['BOTH'] ?? '';
            $row['A_Z_Reason'] = $value['A_Z_Reason'] ?? '';
            $row['A_Z_ActionRequired'] = $value['A_Z_ActionRequired'] ?? '';
            $row['A_Z_ActionTaken'] = $value['A_Z_ActionTaken'] ?? '';
            $row['NRL'] = $value['NR'] ?? '';

            if ($amazonSheet) {
                $row['A_L30'] = $row['A_L30'] ?? $amazonSheet->units_ordered_l30;
                $row['Sess30'] = $row['Sess30'] ?? $amazonSheet->sessions_l30;
                $row['price'] = $row['price'] ?? $amazonSheet->price;
                $row['sessions_l60'] = $row['sessions_l60'] ?? $amazonSheet->sessions_l60;
                $row['units_ordered_l60'] = $row['units_ordered_l60'] ?? $amazonSheet->units_ordered_l60;
            }

            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;

            // LP and Ship calculations
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
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
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // Calculate formulas
            $price = isset($row['price']) ? floatval($row['price']) : 0;
            $units_ordered_l30 = isset($row['A_L30']) ? floatval($row['A_L30']) : 0;
            $row['Total_pft'] = round((($price * $percentage) - $lp - $ship) * $units_ordered_l30, 2);
            $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
            $row['ROI_percentage'] = round($lp > 0 ? ((($price * $percentage) - $lp - $ship) / $lp) * 100 : 0, 2);
            $row['T_COGS'] = round($lp * $units_ordered_l30, 2);

            // Add JungleScout data
            $parentKey = strtoupper($parent);
            if (!empty($parentKey) && $jungleScoutData->has($parentKey)) {
                $row['scout_data'] = $jungleScoutData[$parentKey];
            }

            // Additional data
            $row['percentage'] = $percentage;
            $row['LP_productmaster'] = $lp;
            $row['Ship_productmaster'] = $ship;
            $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

            // Validate links
            $buyerLink = $row['AMZ LINK BL'] ?? null;
            $sellerLink = $row['AMZ LINK SL'] ?? null;
            $row['AMZ LINK BL'] = (filter_var($buyerLink, FILTER_VALIDATE_URL)) ? $buyerLink : null;
            $row['AMZ LINK SL'] = (filter_var($sellerLink, FILTER_VALIDATE_URL)) ? $sellerLink : null;

            $result[] = (object) $row;
        }

        // Only filter for PARENT (no session filter for BOTH view)
        $result = array_filter($result, function ($item) {
            $childSku = $item->{'(Child) sku'} ?? '';
            return stripos($childSku, 'PARENT') === false;
        });

        $result = array_values($result);

        return response()->json([
            'message' => 'BOTH data fetched successfully',
            'data' => $result,
            'status' => 200,
            'debug' => [
                'total_both_records' => count($result),
                'jungle_scout_parents' => $jungleScoutData->keys()->take(5),
                'matched_parents' => collect($result)
                    ->filter(fn($item) => isset($item->scout_data))
                    ->pluck('Parent')
                    ->unique()
                    ->values()
            ]
        ]);
    }

}
