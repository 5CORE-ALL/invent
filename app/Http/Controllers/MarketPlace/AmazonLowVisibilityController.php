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
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSkuDailyData;
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
            // Read from NRL field (synced with listingAmazon)
            $nrlValue = $value['NRL'] ?? '';
            // Map for display: 'NRL' -> 'NR', 'REQ' -> 'REQ', others -> ''
            $row['NRL'] = ($nrlValue === 'NRL') ? 'NR' : (($nrlValue === 'REQ') ? 'REQ' : '');
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
                $sess30 < 300;
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
            // Read from NRL field (synced with listingAmazon)
            $nrlValue = $value['NRL'] ?? '';
            // Map for display: 'NRL' -> 'NR', 'REQ' -> 'REQ', others -> ''
            $row['NRL'] = ($nrlValue === 'NRL') ? 'NR' : (($nrlValue === 'REQ') ? 'REQ' : '');

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
            // Read from NRL field (synced with listingAmazon)
            $nrlValue = $value['NRL'] ?? '';
            // Map for display: 'NRL' -> 'NR', 'REQ' -> 'REQ', others -> ''
            $row['NRL'] = ($nrlValue === 'NRL') ? 'NR' : (($nrlValue === 'REQ') ? 'REQ' : '');

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

    public function getCampaignClicksBySku(Request $request)
    {
        $sku = $request->input('sku');
        
        if (!$sku) {
            return response()->json([
                'status' => 400,
                'message' => 'SKU is required'
            ], 400);
        }

        $sku = strtoupper(trim($sku));
        
        // Get KW clicks_l30 (SPONSORED_PRODUCTS, campaignName matches SKU exactly, excluding PT)
        // Match logic from getAmazonKwAdsData: campaignName equals SKU (excluding PT campaigns)
        $kwCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function($q) use ($sku) {
                foreach ([$sku, $sku . '.'] as $skuVar) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $skuVar . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();
        
        // Filter to match campaignName exactly equals SKU (same logic as KW controller)
        $kwCampaign = $kwCampaigns->first(function($item) use ($sku) {
            $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
            $cleanSku = strtoupper(trim(rtrim($sku, '.')));
            return $campaignName === $cleanSku;
        });

        // Get PT clicks_l30 (SPONSORED_PRODUCTS, campaignName ends with "SKU PT")
        // Match logic from getAmazonPtAdsData: campaigns ending with "SKU PT" or "SKU PT."
        $ptCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function($q) use ($sku) {
                foreach ([$sku, $sku . '.'] as $skuVar) {
                    $q->orWhere('campaignName', 'LIKE', $skuVar . ' PT')
                      ->orWhere('campaignName', 'LIKE', $skuVar . ' PT.');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();
        
        // Filter to exclude FBA PT campaigns (same logic as PT controller)
        $ptCampaign = $ptCampaigns->first(function($item) use ($sku) {
            $cleanName = strtoupper(trim($item->campaignName));
            // Exclude FBA PT campaigns
            if (str_contains($cleanName, 'FBA PT')) {
                return false;
            }
            // Match campaigns ending with SKU PT or SKU PT.
            return (str_ends_with($cleanName, strtoupper($sku) . ' PT') || 
                    str_ends_with($cleanName, strtoupper($sku) . ' PT.'));
        });

        // Get HL clicks_l30 (SPONSORED_BRANDS, campaignName matches SKU or "SKU HEAD")
        // Match logic from getAmazonHlAdsData: campaignName equals SKU or "SKU HEAD"
        $hlCampaigns = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function($q) use ($sku) {
                foreach ([$sku, $sku . '.'] as $skuVar) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($skuVar) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();
        
        // Filter to match campaignName equals SKU or "SKU HEAD" (same logic as HL controller)
        $hlCampaign = $hlCampaigns->first(function($item) use ($sku) {
            $cleanName = strtoupper(trim($item->campaignName));
            $expected1 = strtoupper($sku);
            $expected2 = strtoupper($sku) . ' HEAD';
            return ($cleanName === $expected1 || $cleanName === $expected2);
        });

        // Get organic views from AmazonDatasheet (same as amazon-organic-views.blade.php)
        // Use exact same lookup method as OrganicViewsController:
        // OrganicViewsController line 54-56: 
        //   $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) { return strtoupper($item->sku); });
        // Line 98: $sku = strtoupper($pm->sku);  (NO trim! - uses ProductMaster SKU as-is)
        // Line 105: $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
        // Line 119: $row['organic_views'] = $amazonSheet->organic_views;
        
        // Since we receive SKU from request (which may be trimmed), we need to handle variations
        // The key is that OrganicViewsController keys by strtoupper WITHOUT trim
        // So we'll use case-insensitive comparison that handles spacing
        
        // Get original SKU from request (before trimming) to preserve spacing
        $skuOriginal = $request->input('sku');
        
        // Try multiple lookup strategies to match OrganicViewsController behavior
        $amazonDatasheet = null;
        
        // Strategy 1: Exact match (as stored in ProductMaster/DB)
        if ($skuOriginal) {
            $amazonDatasheet = AmazonDatasheet::where('sku', $skuOriginal)->first();
        }
        
        // Strategy 2: Case-insensitive match without trim (matching OrganicViewsController's keyBy pattern)
        if (!$amazonDatasheet) {
            // Use case-insensitive comparison - matches how OrganicViewsController keys by strtoupper($item->sku)
            $amazonDatasheet = AmazonDatasheet::whereRaw('UPPER(sku) = UPPER(?)', [$skuOriginal ?: $sku])->first();
        }
        
        // Strategy 3: Case-insensitive match with trim (fallback for trimmed SKUs from frontend)
        if (!$amazonDatasheet) {
            $amazonDatasheet = AmazonDatasheet::whereRaw('UPPER(TRIM(sku)) = UPPER(TRIM(?))', [$skuOriginal ?: $sku])->first();
        }
        
        // Get organic_views from the same field as OrganicViewsController line 119: $amazonSheet->organic_views
        $organicViews = $amazonDatasheet ? (int)($amazonDatasheet->organic_views ?? 0) : 0;

        return response()->json([
            'status' => 200,
            'data' => [
                'kw_clicks_l30' => $kwCampaign ? (int)($kwCampaign->clicks ?? 0) : 0,
                'kw_impressions_l30' => $kwCampaign ? (int)($kwCampaign->impressions ?? 0) : 0,
                'kw_spend_l30' => $kwCampaign ? (float)($kwCampaign->spend ?? 0) : 0,
                'kw_campaign_name' => $kwCampaign ? $kwCampaign->campaignName : null,
                
                'pt_clicks_l30' => $ptCampaign ? (int)($ptCampaign->clicks ?? 0) : 0,
                'pt_impressions_l30' => $ptCampaign ? (int)($ptCampaign->impressions ?? 0) : 0,
                'pt_spend_l30' => $ptCampaign ? (float)($ptCampaign->spend ?? 0) : 0,
                'pt_campaign_name' => $ptCampaign ? $ptCampaign->campaignName : null,
                
                'hl_clicks_l30' => $hlCampaign ? (int)($hlCampaign->clicks ?? 0) : 0,
                'hl_impressions_l30' => $hlCampaign ? (int)($hlCampaign->impressions ?? 0) : 0,
                'hl_spend_l30' => $hlCampaign ? (float)($hlCampaign->cost ?? 0) : 0,
                'hl_campaign_name' => $hlCampaign ? $hlCampaign->campaignName : null,
                
                // Organic views from amazon_datasheets.organic_views (same as amazon-organic-views.blade.php)
                'organic_views' => $organicViews,
            ]
        ]);
    }

    public function getDailyViewsData(Request $request)
    {
        $sku = $request->input('sku');
        
        if (!$sku) {
            return response()->json([
                'status' => 400,
                'message' => 'SKU is required'
            ], 400);
        }

        $skuOriginal = trim($sku);
        $sku = strtoupper(trim($sku));
        
        // Get AmazonDatasheet data using same lookup method as getCampaignClicksBySku
        $amazonDatasheet = null;
        
        // Strategy 1: Exact match
        if ($skuOriginal) {
            $amazonDatasheet = AmazonDatasheet::where('sku', $skuOriginal)->first();
        }
        
        // Strategy 2: Case-insensitive match
        if (!$amazonDatasheet) {
            $amazonDatasheet = AmazonDatasheet::whereRaw('UPPER(sku) = UPPER(?)', [$skuOriginal ?: $sku])->first();
        }
        
        // Strategy 3: Case-insensitive with trim
        if (!$amazonDatasheet) {
            $amazonDatasheet = AmazonDatasheet::whereRaw('UPPER(TRIM(sku)) = UPPER(TRIM(?))', [$skuOriginal ?: $sku])->first();
        }

        if (!$amazonDatasheet) {
            return response()->json([
                'status' => 404,
                'message' => 'SKU not found',
                'data' => [
                    'dates' => [],
                    'total_views' => [],
                    'l30_units' => [],
                    'organic_views' => []
                ]
            ], 404);
        }

        // Get daily data for last 30 days (v1-v30 for views, l1-l30 for units)
        // v1 is the oldest day (30 days ago), v30 is the most recent day (today)
        // l1 is the oldest day (30 days ago), l30 is the most recent day (today)
        $dates = [];
        $totalViews = [];
        $l30Units = [];
        $organicViews = [];

        // Get L30 totals as fallback
        $totalViewsL30 = (int)($amazonDatasheet->sessions_l30 ?? 0);
        $totalUnitsL30 = (int)($amazonDatasheet->units_ordered_l30 ?? 0);
        $totalOrganicViews = (int)($amazonDatasheet->organic_views ?? 0);
        
        // Try to get daily data from amazon_sku_daily_data table (has date-wise records)
        $startDate = now()->subDays(29)->startOfDay(); // 30 days including today
        $endDate = now()->endOfDay();
        
        // Use case-insensitive SKU matching to ensure we find the records
        $dailyDataRecords = AmazonSkuDailyData::whereRaw('UPPER(TRIM(sku)) = UPPER(TRIM(?))', [trim($sku)])
            ->whereBetween('record_date', [$startDate, $endDate])
            ->orderBy('record_date', 'asc')
            ->get()
            ->keyBy(function($item) {
                return $item->record_date->format('Y-m-d');
            });
        
        // Generate dates for last 30 days (oldest to newest)
        $dates = [];
        $totalViews = [];
        $l30Units = [];
        $organicViews = [];
        
        $today = now();
        $hasDateWiseData = $dailyDataRecords->isNotEmpty();
        
        for ($i = 1; $i <= 30; $i++) {
            $date = $today->copy()->subDays(30 - $i);
            $dateKey = $date->format('Y-m-d');
            $dates[] = $date->format('M d');
            
            if ($hasDateWiseData && isset($dailyDataRecords[$dateKey])) {
                // Use data from amazon_sku_daily_data table
                // This table stores L30 totals per day (views = sessions_l30, a_l30 = units_ordered_l30, organic_views)
                $record = $dailyDataRecords[$dateKey];
                $dailyDataRaw = $record->daily_data;
                
                // Handle JSON - Laravel model should auto-decode, but handle both cases
                if (is_string($dailyDataRaw)) {
                    $dailyData = json_decode($dailyDataRaw, true) ?? [];
                } else {
                    $dailyData = $dailyDataRaw ?? [];
                }
                
                // Extract views (this is sessions_l30 from that day's record)
                $views = isset($dailyData['views']) && $dailyData['views'] !== null ? (int)$dailyData['views'] : 0;
                $totalViews[] = $views;
                
                // Extract units (this is a_l30 from that day's record - L30 units ordered)
                $units = isset($dailyData['a_l30']) && $dailyData['a_l30'] !== null ? (int)$dailyData['a_l30'] : 0;
                $l30Units[] = $units;
                
                // Extract organic views from daily_data if available, otherwise calculate proportionally
                if (isset($dailyData['organic_views']) && $dailyData['organic_views'] !== null && $dailyData['organic_views'] !== '') {
                    // Use stored organic_views value
                    $dailyOrganicViews = (int)$dailyData['organic_views'];
                } else {
                    // Fallback: Calculate proportionally based on views ratio for old records without organic_views
                    if ($views > 0 && $totalViewsL30 > 0 && $totalOrganicViews > 0) {
                        $organicRatio = $totalOrganicViews / $totalViewsL30;
                        $dailyOrganicViews = (int)round($views * $organicRatio);
                    } else {
                        $dailyOrganicViews = 0;
                    }
                }
                $organicViews[] = $dailyOrganicViews;
            } else {
                // Fallback: Use current L30 totals from amazon_datsheets for missing dates
                // This shows the same value for days without records
                $totalViews[] = $totalViewsL30;
                $l30Units[] = $totalUnitsL30;
                $organicViews[] = $totalOrganicViews;
            }
        }

        return response()->json([
            'status' => 200,
            'data' => [
                'dates' => $dates,
                'total_views' => $totalViews,
                'l30_units' => $l30Units,
                'organic_views' => $organicViews,
                'debug' => [
                    'has_date_wise_data' => $hasDateWiseData,
                    'daily_records_count' => $dailyDataRecords->count(),
                    'total_views_l30' => $totalViewsL30,
                    'total_units_l30' => $totalUnitsL30,
                    'total_organic_views' => $totalOrganicViews,
                    'sku_found' => true,
                    'sku_searched' => strtoupper(trim($sku)),
                    'sample_daily_data' => $hasDateWiseData && $dailyDataRecords->first() ? ($dailyDataRecords->first()->daily_data ?? null) : null,
                    'first_5_total_views' => array_slice($totalViews, 0, 5),
                    'first_5_l30_units' => array_slice($l30Units, 0, 5),
                    'first_5_organic_views' => array_slice($organicViews, 0, 5),
                    'last_5_total_views' => array_slice($totalViews, -5),
                    'last_5_l30_units' => array_slice($l30Units, -5),
                    'last_5_organic_views' => array_slice($organicViews, -5),
                    'dates_range' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d')
                    ]
                ]
            ]
        ]);
    }

}
