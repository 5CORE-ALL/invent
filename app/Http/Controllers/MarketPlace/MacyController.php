<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\MacyDataView;
use App\Models\MacyProduct;
use App\Models\MacysListingStatus;
use App\Models\MacysPriceData;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\MarketplacePercentage;
use App\Models\MiraklDailyData;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\MacySkuCompetitor;
use App\Models\LmpCompetitorHistory;
use App\Services\ChannelPromoPricingService;
use App\Services\LmpSkuGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MacysApiService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\AmazonChannelSummary;

class MacyController extends Controller
{
    protected $apiController;

    protected LmpSkuGroupService $lmpSkuGroupService;

    public function __construct(ApiController $apiController, LmpSkuGroupService $lmpSkuGroupService)
    {
        $this->apiController = $apiController;
        $this->lmpSkuGroupService = $lmpSkuGroupService;
    }

    // ==================== TABULATOR PRICING VIEW ====================
    
    public function macysTabulatorView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 80;

        return view("market-places.macys_tabulator_view", [
            "mode" => $mode,
            "demo" => $demo,
            "macysPercentage" => $percentage,
        ]);
    }

    public function macysDataJson(Request $request)
    {
        try {
            $response = $this->getViewMacysTabulatorData($request);
            $data = json_decode($response->getContent(), true);

            // Auto-save daily summary in background (non-blocking)
            $this->saveDailySummaryIfNeeded($data['data'] ?? []);

            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching Macys data for Tabulator: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getViewMacysTabulatorData(Request $request)
    {
        // 1. Base ProductMaster fetch
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        $productMasters = $productMasters->filter(function ($item) {
            return stripos($item->sku, 'PARENT') === false;
        })->values();

        // 2. SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Sku Link LMP groups + MacySkuCompetitor lookup (same as Reverb / Best Buy tabulators)
        try {
            $this->lmpSkuGroupService->prepareForSkus($skus);
        } catch (\Throwable $e) {
            Log::warning('LmpSkuGroupService prepareForSkus failed on macys pricing: '.$e->getMessage());
        }

        $lmpDetailsLookup = collect();
        try {
            $lmpLookups = MacySkuCompetitor::buildGroupedLookup('macy');
            $lmpDetailsLookup = $lmpLookups['details'];
        } catch (\Throwable $e) {
            Log::warning('Could not fetch LMP data from macy_sku_competitors: '.$e->getMessage());
        }

        // 3. Related Models
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // NBSP / unicode spaces in PM vs macy_products break plain whereIn + strtoupper match (SKU looks identical in UI)
        $macysByNormSku = $this->buildMacyProductLookupByNormalizedSku($skus);

        // Uploaded price sheet (macys_price_data) — SKUs stored uppercased on import.
        // Displayed price = sheet if present, else macy_products.price (same as Tiendamia).
        $priceDataCollection = MacysPriceData::whereIn('sku', $skus)
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        // NR/REQ + SPRICE data from MacyDataView
        $dataViews = MacyDataView::whereIn("sku", $skus)->pluck("value", "sku");

        // Fetch Amazon pricing data (key by uppercase for case-insensitive lookup)
        $amazonData = AmazonDatasheet::whereIn('sku', $skus)
            ->get()
            ->keyBy(function($item) {
                return strtoupper($item->sku);
            });

        // Std Prc — amazon_data_view.STANDARD_PRICE (same shared store as /amazon-tabulator-view)
        $amazonStandardPrices = [];
        foreach (AmazonDataView::whereIn('sku', $skus)->get(['sku', 'value']) as $adv) {
            $val = is_array($adv->value)
                ? $adv->value
                : (json_decode((string) ($adv->value ?? ''), true) ?: []);
            $std = $val['STANDARD_PRICE'] ?? null;
            if (is_numeric($std) && (float) $std > 0) {
                $amazonStandardPrices[strtoupper(trim((string) $adv->sku))] = round((float) $std, 2);
            }
        }

        // PRMT%/CPN%/DSC%/Appr/Push Prc — macys_promo_pricing (site-specific)
        $promoMap = app(ChannelPromoPricingService::class)->mapForSkus('macys', $skus);

        // Listing status data
        $listingStatusData = MacysListingStatus::whereIn("sku", $skus)
            ->get()
            ->mapWithKeys(function ($item) {
                return [strtolower($item->sku) => $item];
            });

        // Macy's Sales Quantity (sum from MiraklDailyData for L30)
        $salesQtyData = MiraklDailyData::where('channel_name', "Macy's, Inc.")
            ->whereIn('sku', $skus)
            ->where('period', 'l30')
            ->where('status', '!=', 'CLOSED')
            ->selectRaw('LOWER(sku) as sku_lower, SUM(quantity) as total_qty')
            ->groupBy('sku_lower')
            ->pluck('total_qty', 'sku_lower');

        // 4. Marketplace percentage (80% for Macys)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.80;

        // 5. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData->get($pm->sku);
            $pmSkuU = strtoupper((string) $pm->sku);
            $pmSkuNorm = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
            $macysMetric = $macysByNormSku[$pmSkuNorm] ?? null;
            $listingStatus = $listingStatusData[strtolower($pm->sku)] ?? null;
            $amazon = $amazonData[$pmSkuU] ?? null;
            $priceData = $priceDataCollection[$pmSkuU] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;

            // Shopify data
            $row["INV"] = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $row["L30"] = $shopify ? (int) ($shopify->quantity ?? 0) : 0;

            // MC L30 / MC INV from macy_products. MC Price: sheet first, else product table.
            $row["MC L30"] = $macysMetric->m_l30 ?? 0;
            $row["MC Price"] = $priceData
                ? floatval($priceData->price ?? 0)
                : ($macysMetric ? floatval($macysMetric->price ?? 0) : 0.0);
            $row["MC INV"] = $macysMetric ? (int) ($macysMetric->stock ?? 0) : 0;
            $row["Price Source"] = $priceData ? 'sheet' : (floatval($row["MC Price"]) > 0 ? 'product' : '');

            // Amazon price
            $row["A Price"] = $amazon ? floatval($amazon->price ?? 0) : null;

            // Macy's Sales Qty from MiraklDailyData (L30)
            $row["MC Sales Qty"] = $salesQtyData[strtolower($pm->sku)] ?? 0;

            // NR/REQ + Links from MacysListingStatus
            $row['nr_req'] = 'REQ';
            $row['B Link'] = '';
            $row['S Link'] = '';

            if ($listingStatus) {
                $statusValue = is_array($listingStatus->value)
                    ? $listingStatus->value
                    : (json_decode($listingStatus->value, true) ?? []);

                if (!empty($statusValue['nr_req'])) {
                    $row['nr_req'] = $statusValue['nr_req'];
                }
                if (!empty($statusValue['buyer_link'])) {
                    $row['B Link'] = $statusValue['buyer_link'];
                }
                if (!empty($statusValue['seller_link'])) {
                    $row['S Link'] = $statusValue['seller_link'];
                }
            }

            // Calculate DIL%
            $row["MC Dil%"] = ($row["MC L30"] && $row["INV"] > 0)
                ? round(($row["MC L30"] / $row["INV"]), 2)
                : 0;

            // Values: LP & Ship from ProductMaster
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === "lp") {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }

            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // Price and units for calculations
            $price = floatval($row["MC Price"] ?? 0);
            $units_ordered_l30 = floatval($row["MC L30"] ?? 0);

            // Profit/Sales calculations
            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["Profit"] = $row["Total_pft"];
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["Sales L30"] = $row["T_Sale_l30"];

            // GPFT% = ((price * percentage - ship - lp) / price) * 100
            $gpft = $price > 0 ? (($price * $percentage - $ship - $lp) / $price) * 100 : 0;
            $row["GPFT%"] = round($gpft, 2);

            // PFT% = GPFT% (no ads for Macys)
            $row["PFT %"] = round($gpft, 2);

            // ROI% = ((price * percentage - lp - ship) / lp) * 100
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );

            $row["percentage"] = $percentage;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;

            // NR & SPRICE data from dataview
            $row['NR'] = "";
            $row['Listed'] = null;
            $row['Live'] = null;
            
            if (isset($dataViews[$pm->sku])) {
                $raw = $dataViews[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['NRL'] = $raw['NRL'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // SPRICE calculation
            $calculatedSprice = $price > 0 ? round($price * 0.99, 2) : null;
            
            // Check for saved SPRICE
            $savedSprice = null;
            $savedStatus = null;
            $hasSavedSprice = false;
            if (isset($dataViews[$pm->sku])) {
                $raw = $dataViews[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    if (isset($raw['SPRICE'])) {
                        $savedSprice = floatval($raw['SPRICE']);
                        $hasSavedSprice = true;
                    }
                    if (isset($raw['SPRICE_STATUS'])) {
                        $savedStatus = $raw['SPRICE_STATUS'];
                    }
                }
            }

            // Use saved SPRICE if exists, otherwise use calculated or 0 if record exists without SPRICE
            if ($hasSavedSprice) {
                $row['SPRICE'] = $savedSprice;
                $row['has_custom_sprice'] = true;
                $row['SPRICE_STATUS'] = $savedStatus ?: 'saved';
            } else {
                // If record exists but no SPRICE, it was cleared - show 0
                $row['SPRICE'] = isset($dataViews[$pm->sku]) ? 0 : $calculatedSprice;
                $row['has_custom_sprice'] = false;
                $row['SPRICE_STATUS'] = $savedStatus;
            }

            // Calculate SGPFT based on SPRICE
            $sprice = $row['SPRICE'] ?? 0;
            $sgpft = round(
                $sprice > 0 ? (($sprice * $percentage - $ship - $lp) / $sprice) * 100 : 0,
                2
            );
            $row['SGPFT'] = $sgpft;
            $row['SPFT'] = $sgpft;

            // SROI: ((SPRICE * percentage - lp - ship) / lp) * 100
            $row['SROI'] = round(
                $lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );

            // Image
            $row["image_path"] = $shopify?->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null));

            // LMP — lowest total_price from macy_sku_competitors across Sku Link LMP group
            $linkedLmpSkus = $this->linkedLmpSkusForProduct((string) $pm->sku);
            $row['linked_lmp_skus'] = $linkedLmpSkus;

            // Std Prc — shared amazon_data_view.STANDARD_PRICE; inherit from Sku Link LMP siblings
            $stdPrc = $amazonStandardPrices[strtoupper(trim((string) $pm->sku))] ?? null;
            if ($stdPrc === null && ! empty($linkedLmpSkus)) {
                foreach ($linkedLmpSkus as $linkedSku) {
                    $linkedKey = strtoupper(trim((string) $linkedSku));
                    if ($linkedKey !== '' && isset($amazonStandardPrices[$linkedKey])) {
                        $stdPrc = $amazonStandardPrices[$linkedKey];
                        break;
                    }
                }
            }
            $row['STANDARD_PRICE'] = $stdPrc;

            // CVR% = MC L30 ÷ OV L30 (same as CVR% filter on this page)
            $ovL30 = (float) ($row['L30'] ?? 0);
            $mcL30 = (float) ($row['MC L30'] ?? 0);
            $row['CVR%'] = $ovL30 > 0 ? round(($mcL30 / $ovL30) * 100, 2) : 0;
            $row = app(ChannelPromoPricingService::class)->applyToRow($row, $promoMap, (string) $pm->sku);

            $allLmpEntries = collect();
            foreach ($linkedLmpSkus as $linkedSku) {
                foreach (MacySkuCompetitor::resolveLookupKeys((string) $linkedSku) as $lookupKey) {
                    $entries = $lmpDetailsLookup->get($lookupKey);
                    if ($entries instanceof \Illuminate\Support\Collection && $entries->isNotEmpty()) {
                        $allLmpEntries = $allLmpEntries->merge($entries);
                    }
                }
            }
            $allLmpEntries = MacySkuCompetitor::dedupeByItemId($allLmpEntries)
                ->filter(fn ($entry) => (float) ($entry->total_price ?? 0) > 0)
                ->sortBy(fn ($entry) => (float) ($entry->total_price ?? 0))
                ->values();
            $lowestLmp = $allLmpEntries->first(fn ($c) => empty($c->ignored)) ?: $allLmpEntries->first();

            $row['lmp_price'] = ($lowestLmp && isset($lowestLmp->total_price) && is_numeric($lowestLmp->total_price))
                ? floatval($lowestLmp->total_price)
                : null;
            $row['lmp_link'] = $lowestLmp->product_link ?? null;
            $row['lmp_item_id'] = $lowestLmp->item_id ?? null;
            $row['lmp_title'] = $lowestLmp->product_title ?? null;
            $row['lmp_entries'] = $allLmpEntries
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'item_id' => $entry->item_id,
                        'price' => floatval($entry->price ?? 0),
                        'shipping_cost' => floatval($entry->shipping_cost ?? 0),
                        'total_price' => floatval($entry->total_price ?? 0),
                        'ignored' => (bool) ($entry->ignored ?? false),
                        'link' => $entry->product_link,
                        'title' => $entry->product_title,
                        'image' => $entry->image ?? null,
                    ];
                })
                ->values()
                ->toArray();
            $row['lmp_entries_total'] = $allLmpEntries->count();

            $result[] = (object) $row;
        }

        return response()->json([
            "message" => "Macys Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }

    /**
     * Map / Miss / NMap for macys-pricing badges and all-marketplace-master Macys row.
     * Matches macys_tabulator_view updateSummary() with default filters: INV &gt; 0, REQ only,
     * exclude rows whose Parent starts with PARENT; |INV − MC INV| ≤ 3 → Map, else NMap.
     */
    public static function countMacysPricingBadgeTotals(iterable $rows): array
    {
        $map = 0;
        $miss = 0;
        $nmap = 0;

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row)) {
                continue;
            }

            $parent = (string) ($row['Parent'] ?? '');
            if ($parent !== '' && str_starts_with($parent, 'PARENT')) {
                continue;
            }

            $inv = (float) ($row['INV'] ?? 0);
            if ($inv <= 0) {
                continue;
            }

            $nrReq = strtoupper(trim((string) ($row['nr_req'] ?? '')));
            if ($nrReq !== 'REQ') {
                continue;
            }

            $mcInv = (float) ($row['MC INV'] ?? 0);
            $price = (float) ($row['MC Price'] ?? 0);

            if ($price == 0.0) {
                $miss++;
                continue;
            }

            if (abs($inv - $mcInv) <= 3) {
                $map++;
            } else {
                $nmap++;
            }
        }

        return ['map' => $map, 'miss' => $miss, 'nmap' => $nmap];
    }

    // Update NR/REQ for Tabulator
    public function updateNrReq(Request $request)
    {
        $sku = trim($request->input('sku'));
        $nrReq = $request->input('nr_req');

        $status = MacysListingStatus::where('sku', $sku)->first();
        
        if ($status) {
            $existing = is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []);
        } else {
            $existing = [];
        }
        
        $existing['nr_req'] = $nrReq;
        
        MacysListingStatus::where('sku', $sku)->delete();
        MacysListingStatus::create([
            'sku' => $sku,
            'value' => $existing
        ]);

        return response()->json(['success' => true, 'message' => 'NR/REQ updated']);
    }

    // Save SPRICE for Tabulator
    public function saveSpriceTabulator(Request $request)
    {
        Log::info('Saving Macys pricing data', $request->all());
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            return response()->json(['error' => 'SKU and SPRICE are required'], 400);
        }

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.80;

        $pm = ProductMaster::where('sku', $sku)->first();
        if (!$pm) {
            return response()->json(['error' => 'SKU not found in product master'], 404);
        }

        $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
        $lp = 0;
        foreach ($values as $k => $v) {
            if (strtolower($k) === "lp") {
                $lp = floatval($v);
                break;
            }
        }
        if ($lp === 0 && isset($pm->lp)) {
            $lp = floatval($pm->lp);
        }

        $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

        $spriceFloat = floatval($sprice);
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * $percentage - $ship - $lp) / $spriceFloat) * 100, 2) : 0;
        $spft = $sgpft;
        $sroi = round(
            $lp > 0 ? (($spriceFloat * $percentage - $lp - $ship) / $lp) * 100 : 0,
            2
        );

        $dataView = MacyDataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?? []);

        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
            'SGPFT' => $sgpft,
        ]);

        $dataView->value = $merged;
        $dataView->save();

        $pushResult = $this->pushPriceToMacy($sku, $spriceFloat);

        return response()->json([
            'success' => true,
            'spft_percent' => $spft,
            'sroi_percent' => $sroi,
            'sgpft_percent' => $sgpft,
            'price_push_success' => (bool) ($pushResult['success'] ?? false),
            'price_push_message' => (string) ($pushResult['message'] ?? ''),
            'price_push_status_code' => $pushResult['status_code'] ?? null,
        ]);
    }

    // Upload Price Data for Macys
    public function uploadPriceData(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file'
        ]);

        try {
            $file = $request->file('excel_file');
            $rows = $this->parseFile($file);

            if (empty($rows)) {
                return response()->json(['error' => 'File is empty'], 400);
            }

            $headers = array_shift($rows);
            $headers = array_map('trim', $headers);

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            MacysPriceData::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            Log::info('Macys Price Data table truncated before import');

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                foreach ($rows as $row) {
                    $row = array_map('trim', $row);
                    if (count(array_filter($row)) === 0) {
                        $skipped++;
                        continue;
                    }
                    $rowData = array_combine($headers, $row);
                    $sku = strtoupper($rowData['Offer SKU'] ?? $rowData['SKU'] ?? '');
                    if (empty($sku)) {
                        $skipped++;
                        continue;
                    }

                    MacysPriceData::create([
                        'sku' => $sku,
                        'offer_sku' => $rowData['Offer SKU'] ?? null,
                        'product_sku' => $rowData['Product SKU'] ?? null,
                        'category_code' => $rowData['Category code'] ?? null,
                        'category_label' => $rowData['Category label'] ?? null,
                        'brand' => $rowData['Brand'] ?? null,
                        'product_name' => $rowData['Product'] ?? null,
                        'offer_state' => $rowData['Offer state'] ?? null,
                        'price' => !empty($rowData['Price']) ? floatval($rowData['Price']) : null,
                        'original_price' => !empty($rowData['Original price']) ? floatval($rowData['Original price']) : null,
                        'quantity' => !empty($rowData['Quantity']) ? intval($rowData['Quantity']) : null,
                        'alert_threshold' => !empty($rowData['Alert threshold']) ? intval($rowData['Alert threshold']) : null,
                        'logistic_class' => $rowData['Logistic Class'] ?? null,
                        'activated' => isset($rowData['Activated']) ? filter_var($rowData['Activated'], FILTER_VALIDATE_BOOLEAN) : false,
                        'available_start_date' => !empty($rowData['Available Start Date']) ? date('Y-m-d', strtotime($rowData['Available Start Date'])) : null,
                        'available_end_date' => !empty($rowData['Available End Date']) ? date('Y-m-d', strtotime($rowData['Available End Date'])) : null,
                        'favorite_offer' => isset($rowData['Favorite Offer']) ? filter_var($rowData['Favorite Offer'], FILTER_VALIDATE_BOOLEAN) : false,
                        'discount_price' => !empty($rowData['Discount price']) ? floatval($rowData['Discount price']) : null,
                        'discount_start_date' => !empty($rowData['Discount Start Date']) ? date('Y-m-d', strtotime($rowData['Discount Start Date'])) : null,
                        'discount_end_date' => !empty($rowData['Discount End Date']) ? date('Y-m-d', strtotime($rowData['Discount End Date'])) : null,
                        'lead_time_to_ship' => !empty($rowData['Lead time to ship']) ? intval($rowData['Lead time to ship']) : null,
                        'upc' => $rowData['UPC'] ?? null,
                        'inactivity_reason' => $rowData['Inactivity reason'] ?? null,
                        'fulfillment_center_code' => $rowData['Fulfillment center code'] ?? null,
                    ]);
                    $imported++;
                }
                DB::commit();
                return response()->json([
                    'success' => "Successfully imported $imported price records (skipped $skipped)",
                    'imported' => $imported,
                    'skipped' => $skipped
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error importing Macys price data: ' . $e->getMessage());
            return response()->json(['error' => 'Error importing file: ' . $e->getMessage()], 500);
        }
    }

    // Parse different file formats
    private function parseFile($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (in_array($extension, ['csv', 'tsv', 'txt'])) {
            $delimiter = ($extension === 'tsv' || $extension === 'txt') ? "\t" : ',';
            $rows = [];
            if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
                while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
            return $rows;
        } else {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            return $sheet->toArray();
        }
    }

    // Save SPRICE updates in batch
    public function saveSpriceUpdates(Request $request)
    {
        try {
            // Handle both single SKU/SPRICE update and batch updates
            if ($request->has('sku') && $request->has('sprice')) {
                // Single update format (from manual cell edit)
                $updates = [[
                    'sku' => $request->input('sku'),
                    'sprice' => $request->input('sprice')
                ]];
            } else {
                // Batch update format (from decrease/increase mode or Amazon price)
                $updates = $request->input('updates', []);
            }
            
            if (empty($updates)) {
                return response()->json(['error' => 'No updates provided'], 400);
            }

            $updated = 0;
            $errors = [];
            $pricePushQueue = [];

            DB::beginTransaction();
            
            foreach ($updates as $update) {
                $sku = strtoupper(trim($update['sku'] ?? ''));
                $sprice = floatval($update['sprice'] ?? 0);
                
                if (empty($sku)) {
                    $errors[] = "Invalid SKU";
                    continue;
                }

                // Handle clearing SPRICE (when sprice = 0)
                if ($sprice == 0) {
                    $dataViewRecord = MacyDataView::where('sku', $sku)->first();
                    
                    if ($dataViewRecord) {
                        // Get existing value array and remove SPRICE fields
                        $existingValue = is_array($dataViewRecord->value) 
                            ? $dataViewRecord->value 
                            : (json_decode($dataViewRecord->value, true) ?? []);
                        
                        // Remove all SPRICE related fields
                        unset($existingValue['SPRICE']);
                        unset($existingValue['SPFT']);
                        unset($existingValue['SROI']);
                        unset($existingValue['SGPFT']);
                        unset($existingValue['sprice_updated_at']);
                        
                        // Update the record without SPRICE data
                        $dataViewRecord->update([
                            'value' => $existingValue,
                            'updated_at' => now()
                        ]);
                        
                        Log::info("Cleared SPRICE data for SKU: {$sku}");
                    }
                    
                    $updated++;
                    continue; // Skip the rest of the processing
                }

                // Get ProductMaster for lp and ship to calculate metrics
                $pm = ProductMaster::where('sku', $sku)->first();
                if (!$pm) {
                    $errors[] = "SKU not found in product master: {$sku}";
                    continue;
                }

                // Extract lp and ship
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $lp = 0;
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }

                $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

                // Get marketplace percentage (80%)
                $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
                $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.80;

                // Calculate SGPFT
                $sgpft = $sprice > 0 ? round((($sprice * $percentage - $ship - $lp) / $sprice) * 100, 2) : 0;

                // SPFT = SGPFT (no ads for Macys)
                $spft = $sgpft;

                // SROI
                $sroi = round(
                    $lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0,
                    2
                );

                // Update or create record in MacyDataView table
                $dataViewRecord = MacyDataView::where('sku', $sku)->first();
                
                if ($dataViewRecord) {
                    // Get existing value array and update it
                    $existingValue = is_array($dataViewRecord->value) 
                        ? $dataViewRecord->value 
                        : (json_decode($dataViewRecord->value, true) ?? []);
                    
                    $existingValue['SPRICE'] = $sprice;
                    $existingValue['SPFT'] = $spft;
                    $existingValue['SROI'] = $sroi;
                    $existingValue['SGPFT'] = $sgpft;
                    $existingValue['sprice_updated_at'] = now()->toDateTimeString();
                    
                    $dataViewRecord->update([
                        'value' => $existingValue,
                        'updated_at' => now()
                    ]);
                } else {
                    // Create new record
                    MacyDataView::create([
                        'sku' => $sku,
                        'value' => [
                            'SPRICE' => $sprice,
                            'SPFT' => $spft,
                            'SROI' => $sroi,
                            'SGPFT' => $sgpft,
                            'sprice_updated_at' => now()->toDateTimeString()
                        ]
                    ]);
                }
                $updated++;
                $pricePushQueue[] = ['sku' => $sku, 'sprice' => (float) $sprice];
                
                // Store last calculated metrics for single update response
                if (count($updates) === 1) {
                    $lastMetrics = [
                        'spft_percent' => $spft,
                        'sroi_percent' => $sroi,
                        'sgpft_percent' => $sgpft
                    ];
                }
            }

            DB::commit();

            $pricePushSuccess = 0;
            $pricePushFailed = 0;
            $pricePushErrors = [];
            $singlePushResult = null;
            foreach ($pricePushQueue as $pushItem) {
                $pushResult = $this->pushPriceToMacy($pushItem['sku'], (float) $pushItem['sprice']);
                if (count($pricePushQueue) === 1) {
                    $singlePushResult = $pushResult;
                }
                if (($pushResult['success'] ?? false) === true) {
                    $pricePushSuccess++;
                } else {
                    $pricePushFailed++;
                    $pricePushErrors[] = $pushItem['sku'] . ': ' . ($pushResult['message'] ?? 'Price push failed');
                }
            }

            $response = [
                'success' => true,
                'updated' => $updated,
                'message' => "Successfully saved {$updated} SPRICE update(s)",
                'price_push_success_count' => $pricePushSuccess,
                'price_push_failed_count' => $pricePushFailed,
            ];

            // Include calculated metrics for single updates (manual cell edits)
            if (isset($lastMetrics)) {
                $response = array_merge($response, $lastMetrics);
            }

            if (!empty($errors)) {
                $response['errors'] = $errors;
                $response['message'] .= ' with ' . count($errors) . ' error(s)';
            }
            if (!empty($pricePushErrors)) {
                $response['price_push_errors'] = $pricePushErrors;
            }
            if (is_array($singlePushResult)) {
                $response['price_push_success'] = (bool) ($singlePushResult['success'] ?? false);
                $response['price_push_message'] = (string) ($singlePushResult['message'] ?? '');
                $response['price_push_status_code'] = $singlePushResult['status_code'] ?? null;
            }

            Log::info("Macys SPRICE updates saved to macy_data_views: {$updated} records updated");

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving Macys SPRICE updates: ' . $e->getMessage());
            return response()->json(['error' => 'Error saving updates: ' . $e->getMessage()], 500);
        }
    }

    // Column Visibility for Tabulator
    // Persisted in the shared `channel_tabulator_column_settings` DB table
    // (channel = 'macys_tabulator'), matching every other tabulator page.
    // Previously used Cache, which is not durable across cache clears/drivers.
    public function getTabulatorColumnVisibility(Request $request)
    {
        $row = ChannelTabulatorColumnSetting::where('channel_name', 'macys_tabulator')->first();

        $visibility = $row && is_array($row->visibility) ? $row->visibility : [];

        return response()->json($visibility);
    }

    public function setTabulatorColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);

        // jQuery form-encodes booleans as the strings "true"/"false"; normalize to real bools.
        $normalized = [];
        foreach ((array) $visibility as $field => $val) {
            $field = (string) $field;
            if ($field === '' || strlen($field) > 190) {
                continue;
            }
            $normalized[$field] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }

        ChannelTabulatorColumnSetting::updateOrCreate(
            ['channel_name' => 'macys_tabulator'],
            ['visibility' => $normalized]
        );

        return response()->json(['success' => true]);
    }

    // ==================== ORIGINAL METHODS ====================

    public function macyView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('macys_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Macys')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.macys', [
            'mode' => $mode,
            'demo' => $demo,
            'macysPercentage' => $percentage
        ]);
    }


    public function macyPricingCvr(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('macys_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.macys_pricing_cvr', [
            'mode' => $mode,
            'demo' => $demo,
            'macysPercentage' => $percentage
        ]);
    }


    public function macyPricingIncreaseandDecrease(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('macys_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.macys_pricing_increase_decrease', [
            'mode' => $mode,
            'demo' => $demo,
            'macysPercentage' => $percentage
        ]);
    }


    public function getViewMacyData(Request $request)
    {
        $productMasters = ProductMaster::all();
        $skus = $productMasters->pluck('sku')->toArray();

        $shopifySkus = ShopifySku::mapByProductSkus($skus);
        $macyProducts = MacyProduct::whereIn('sku', $skus)->get()->keyBy('sku');
        $macyDataViews = MacyDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = $productMasters->map(function ($product) use ($shopifySkus, $macyProducts, $macyDataViews) {
            $sku = $product->sku;
            $shopifyRow = $shopifySkus->get($sku);

            // 🟢 Shopify + Macy base metrics
            $product->INV = $shopifyRow ? (int) ($shopifyRow->inv ?? 0) : 0;
            $product->L30 = $shopifyRow ? (int) ($shopifyRow->quantity ?? 0) : 0;
            $product->m_l30 = $macyProducts->has($sku) ? $macyProducts[$sku]->m_l30 : null;
            $product->m_l60 = $macyProducts->has($sku) ? $macyProducts[$sku]->m_l60 : null;
            $product->price = $macyProducts->has($sku) ? $macyProducts[$sku]->price : null;

            // 🟢 Default NR/flags
            $product->NR = '';
            $product->SPRICE = null;
            $product->SPFT = null;
            $product->SROI = null;
            $product->Listed = null;
            $product->Live = null;
            $product->APlus = null;

            // 🟢 MacyDataView enrichments
            if ($macyDataViews->has($sku)) {
                $value = $macyDataViews[$sku]->value;

                if (!is_array($value)) {
                    $value = json_decode($value, true);
                }

                if (is_array($value)) {
                    $product->NR = $value['NR'] ?? '';
                    $product->SPRICE = $value['SPRICE'] ?? null;
                    $product->SPFT = $value['SPFT'] ?? null;
                    $product->SROI = $value['SROI'] ?? null;
                    $product->Listed = isset($value['Listed']) ? filter_var($value['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $product->Live = isset($value['Live']) ? filter_var($value['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $product->APlus = isset($value['APlus']) ? filter_var($value['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // 🟡 LP and SHIP extraction
            $values = is_array($product->Values)
                ? $product->Values
                : (is_string($product->Values) ? json_decode($product->Values, true) : []);

            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($product->lp)) {
                $lp = floatval($product->lp);
            }

            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($product->ship) ? floatval($product->ship) : 0);

            // 🟡 Macy percentage (default 100%)
            $percentage = Cache::remember(
                'macy_marketplace_percentage',
                now()->addDays(30),
                function () {
                    return MarketplacePercentage::where('marketplace', 'Macy')->value('percentage') ?? 100;
                }
            ) / 100;

            $price = floatval($product->price ?? 0);
            $units_ordered_l30 = floatval($product->m_l30 ?? 0);

            // 🟢 Profitability calculations
            $product->Total_pft = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $product->T_Sale_l30 = round($price * $units_ordered_l30, 2);
            $product->PFT_percentage = round(
                $price > 0 ? (($price * $percentage - $lp - $ship) / $price) * 100 : 0,
                2
            );
            $product->ROI_percentage = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );
            $product->T_COGS = round($lp * $units_ordered_l30, 2);
            $product->LP_productmaster = $lp;
            $product->Ship_productmaster = $ship;
            $product->percentage = $percentage;

            return $product;
        })->values();

        return response()->json([
            'message' => 'Macy data fetched successfully (DB only)',
            'product_master_data' => $processedData,
            'status' => 200
        ]);
    }


    // public function getViewMacyData(Request $request)
    // {
    //     // Fetch data from the Google Sheet using the ApiController method
    //     $response = $this->apiController->fetchMacyListingData();

    //     // Check if the response is successful
    //     if ($response->getStatusCode() === 200) {
    //         $data = $response->getData(); // Get the JSON data from the response

    //         // Get all non-PARENT SKUs from the data to fetch from ShopifySku model
    //         $skus = collect($data->data)
    //             ->filter(function ($item) {
    //                 $childSku = $item->{'(Child) sku'} ?? '';
    //                 return !empty($childSku) && stripos($childSku, 'PARENT') === false;
    //             })
    //             ->pluck('(Child) sku')
    //             ->unique()
    //             ->toArray();

    //         // Fetch Shopify inventory data for non-PARENT SKUs
    //         $shopifyData = ShopifySku::whereIn('sku', $skus)
    //             ->get()
    //             ->keyBy('sku');

    //         // Fetch MacyProduct data for non-PARENT SKUs
    //         $macyProducts = MacyProduct::whereIn('sku', values: $skus)
    //             ->get()
    //             ->keyBy('sku');

    //         // Fetch all products from ProductMaster (parent and sku)
    //         $productMasters = ProductMaster::select('parent', 'sku', 'Values')->get();
    //         $skuToProduct = $productMasters->keyBy('sku');
    //         $parentToProduct = $productMasters->keyBy('parent');

    //         // Filter out rows where both Parent and (Child) sku are empty and process data
    //         $filteredData = array_filter($data->data, function ($item) {
    //             $parent = $item->Parent ?? '';
    //             $childSku = $item->{'(Child) sku'} ?? '';

    //             // Keep the row if either Parent or (Child) sku is not empty
    //             return !(empty(trim($parent)) && empty(trim($childSku)));
    //         });

    //         // Process the data to include Shopify inventory values, ProductMaster info, and MacyProduct "M L30"
    //         $processedData = array_map(function ($item) use ($shopifyData, $skuToProduct, $parentToProduct, $macyProducts) {
    //             $childSku = $item->{'(Child) sku'} ?? '';
    //             $parent = $item->Parent ?? '';

    //             // Only update INV and L30 if this is not a PARENT SKU
    //             if (!empty($childSku) && stripos($childSku, 'PARENT') === false) {
    //                 if ($shopifyData->has($childSku)) {
    //                     $item->INV = $shopifyData[$childSku]->inv;
    //                     $item->L30 = $shopifyData[$childSku]->quantity;
    //                 } else {
    //                     $item->INV = 0;
    //                     $item->L30 = 0;
    //                 }
    //             }
    //             // Attach ProductMaster info by SKU if available
    //             if (!empty($childSku) && $skuToProduct->has($childSku)) {
    //                 $item->product_master = $skuToProduct[$childSku];
    //             } elseif (!empty($parent) && $parentToProduct->has($parent)) {
    //                 $item->product_master = $parentToProduct[$parent];
    //             } else {
    //                 $item->product_master = null;
    //             }

    //             // Attach MacyProduct "M L30" value if available
    //             if (!empty($childSku) && $macyProducts->has($childSku)) {
    //                 $item->{'M L30'} = $macyProducts[$childSku]->m_l30;
    //                 $item->{'M L60'} = $macyProducts[$childSku]->m_l60;
    //             } else {
    //                 $item->{'M L30'} = null;
    //                 $item->{'M L60'} = null;
    //             }

    //             return $item;
    //         }, $filteredData);

    //         // Re-index the array after filtering
    //         $processedData = array_values($processedData);

    //         // Return the filtered data
    //         return response()->json([
    //             'message' => 'Data fetched successfully',
    //             'data' => $processedData,
    //             'status' => 200
    //         ]);
    //     } else {
    //         // Handle the error if the request failed
    //         return response()->json([
    //             'message' => 'Failed to fetch data from Google Sheet',
    //             'status' => $response->getStatusCode()
    //         ], $response->getStatusCode());
    //     }
    // }

    public function updateAllMacySkus(Request $request)
    {
        try {
            $percent = $request->input('percent');

            if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Invalid percentage value. Must be between 0 and 100.'
                ], 400);
            }

            // Update database
            MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'Macys'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('macys_marketplace_percentage', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'Macys',
                    'percentage' => $percent
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating percentage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nr = $request->input('nr');

        if (!$sku || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        $dataView = MacyDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        if ($nr !== null) {
            $value["NR"] = $nr;
        }
        $dataView->value = $value;
        $dataView->save();

        return response()->json(['success' => true, 'data' => $dataView]);
    }


    public function saveSpriceToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent']);

        if (!$sku || !$spriceData['sprice']) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        $macyDataView = MacyDataView::firstOrNew(['sku' => $sku]);
        // Decode value column safely
        $existing = is_array($macyDataView->value)
            ? $macyDataView->value
            : (json_decode($macyDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceData['sprice'],
            'SPFT' => $spriceData['spft_percent'],
            'SROI' => $spriceData['sroi_percent'],
        ]);

        $macyDataView->value = $merged;
        $macyDataView->save();

        $pushResult = $this->pushPriceToMacy((string) $sku, (float) $spriceData['sprice']);

        return response()->json([
            'message' => 'Data saved successfully.',
            'price_push_success' => (bool) ($pushResult['success'] ?? false),
            'price_push_message' => (string) ($pushResult['message'] ?? ''),
            'price_push_status_code' => $pushResult['status_code'] ?? null,
        ]);
    }

    /**
     * Push saved SPRICE to Macy marketplace API.
     *
     * @return array{success:bool,message:string}
     */
    private function pushPriceToMacy(string $sku, float $sprice): array
    {
        if ($sprice <= 0) {
            return ['success' => false, 'message' => 'Skipping push for non-positive price'];
        }

        try {
            return app(MacysApiService::class)->updatePrice($sku, $sprice);
        } catch (\Throwable $e) {
            Log::error('Macy price push call failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'Macys')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = MacyDataView::firstOrCreate(
            ['sku' => $request->sku],
            ['value' => []]
        );

        // Decode current value (ensure it's an array)
        $currentValue = is_array($product->value)
            ? $product->value
            : (json_decode($product->value, true) ?? []);

        // Store as actual boolean
        $currentValue[$request->field] = filter_var($request->value, FILTER_VALIDATE_BOOLEAN);

        // Save back to DB
        $product->value = $currentValue;
        $product->save();

        return response()->json(['success' => true]);
    }

    public function importMacysAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Clean headers
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            unset($rows[0]);

            $allSkus = [];
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $allSkus[] = $row[0];
                }
            }

            $existingSkus = ProductMaster::whereIn('sku', $allSkus)
                ->pluck('sku')
                ->toArray();

            $existingSkus = array_flip($existingSkus);

            $importCount = 0;
            foreach ($rows as $index => $row) {
                if (empty($row[0])) { // Check if SKU is empty
                    continue;
                }

                // Ensure row has same number of elements as headers
                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = array_combine($headers, $rowData);

                if (!isset($data['sku']) || empty($data['sku'])) {
                    continue;
                }

                // Only import SKUs that exist in product_masters (in-memory check)
                if (!isset($existingSkus[$data['sku']])) {
                    continue;
                }

                // Prepare values array
                $values = [];

                // Handle boolean fields
                if (isset($data['listed'])) {
                    $values['Listed'] = filter_var($data['listed'], FILTER_VALIDATE_BOOLEAN);
                }

                if (isset($data['live'])) {
                    $values['Live'] = filter_var($data['live'], FILTER_VALIDATE_BOOLEAN);
                }

                // Update or create record
                MacyDataView::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['value' => $values]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportMacysAnalytics()
    {
        $macyData = MacyDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($macyData as $data) {
            $values = is_array($data->value)
                ? $data->value
                : (json_decode($data->value, true) ?? []);

            $sheet->fromArray([
                $data->sku,
                isset($values['Listed']) ? ($values['Listed'] ? 'TRUE' : 'FALSE') : 'FALSE',
                isset($values['Live']) ? ($values['Live'] ? 'TRUE' : 'FALSE') : 'FALSE',
            ], NULL, 'A' . $rowIndex);

            $rowIndex++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Macy_Analytics_Export_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function downloadSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data
        $sampleData = [
            ['SKU001', 'TRUE', 'FALSE'],
            ['SKU002', 'FALSE', 'TRUE'],
            ['SKU003', 'TRUE', 'TRUE'],
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Macy_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Map normalized product-master SKU → MacyProduct (same Unicode space normalization as ShopifySku).
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, MacyProduct>
     */
    private function buildMacyProductLookupByNormalizedSku(array $productSkus): array
    {
        $byNorm = [];
        foreach (MacyProduct::whereIn('sku', $productSkus)->get() as $row) {
            $k = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
            if ($k !== '' && ! isset($byNorm[$k])) {
                $byNorm[$k] = $row;
            }
        }

        $missingNorm = [];
        foreach ($productSkus as $pmSku) {
            $k = ShopifySku::normalizeSkuForShopifyLookup((string) $pmSku);
            if ($k !== '' && ! isset($byNorm[$k])) {
                $missingNorm[$k] = true;
            }
        }

        if ($missingNorm === []) {
            return $byNorm;
        }

        MacyProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(2000, function ($rows) use (&$byNorm, &$missingNorm) {
                foreach ($rows as $row) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    if ($k !== '' && isset($missingNorm[$k]) && ! isset($byNorm[$k])) {
                        $byNorm[$k] = $row;
                        unset($missingNorm[$k]);
                    }
                }

                return count($missingNorm) > 0;
            });

        return $byNorm;
    }

    /**
     * Auto-save daily Macy's summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            
            // Filter: INV > 0 && nr_req === 'REQ' && not parent (EXACT JavaScript logic)
            $filteredData = collect($products)->filter(function($p) {
                $invCheck = floatval($p['INV'] ?? 0) > 0;
                $reqCheck = ($p['nr_req'] ?? '') === 'REQ';
                $notParent = !(isset($p['Parent']) && str_starts_with($p['Parent'], 'PARENT'));
                
                return $invCheck && $reqCheck && $notParent;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalSkuCount = $filteredData->count();
            $totalPft = 0;
            $totalSales = 0;
            $totalGpft = 0;
            $totalPrice = 0;
            $priceCount = 0;
            $totalInv = 0;
            $totalL30 = 0;
            $zeroSoldCount = 0;
            $totalDil = 0;
            $dilCount = 0;
            $totalCogs = 0;
            $totalRoi = 0;
            $roiCount = 0;
            $badgeCounts = self::countMacysPricingBadgeTotals($filteredData);
            $missingCount = $badgeCounts['miss'];
            $mappingCount = $badgeCounts['nmap'];
            
            // Loop through each row (EXACT JavaScript forEach logic)
            foreach ($filteredData as $row) {
                $totalPft += floatval($row['Profit'] ?? 0);
                $totalSales += floatval($row['Sales L30'] ?? 0);
                $totalGpft += floatval($row['GPFT%'] ?? 0);
                
                $price = floatval($row['MC Price'] ?? 0);
                $inv = floatval($row['INV'] ?? 0);
                $nrReq = $row['nr_req'] ?? 'REQ';
                $isMissing = ($price == 0);
                
                if ($price > 0) {
                    $totalPrice += $price;
                    $priceCount++;
                }
                
                $totalInv += $inv;
                $totalL30 += floatval($row['MC L30'] ?? 0);
                
                // Count zero sold
                if (floatval($row['MC L30'] ?? 0) == 0) {
                    $zeroSoldCount++;
                }
                
                $dil = floatval($row['MC Dil%'] ?? 0);
                if ($dil > 0) {
                    $totalDil += $dil;
                    $dilCount++;
                }
                
                // COGS = LP × MC L30
                $lp = floatval($row['LP_productmaster'] ?? 0);
                $l30 = floatval($row['MC L30'] ?? 0);
                $totalCogs += $lp * $l30;
                
                $roi = floatval($row['ROI%'] ?? 0);
                if ($roi != 0) {
                    $totalRoi += $roi;
                    $roiCount++;
                }
            }
            
            // Calculate averages and percentages (EXACT JavaScript logic)
            $avgGpft = $totalSkuCount > 0 ? $totalGpft / $totalSkuCount : 0; // Average GPFT% (not calculated from totals)
            $avgPrice = $priceCount > 0 ? $totalPrice / $priceCount : 0;
            $avgDil = $dilCount > 0 ? $totalDil / $dilCount : 0;
            $avgRoi = $roiCount > 0 ? $totalRoi / $roiCount : 0;
            
            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_sku_count' => $totalSkuCount,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'mapping_count' => $mappingCount,
                'nmap_count' => $mappingCount,
                
                // Financial Totals
                'total_pft' => round($totalPft, 2),
                'total_sales' => round($totalSales, 2),
                'total_cogs' => round($totalCogs, 2),
                
                // Inventory
                'total_inv' => round($totalInv, 2),
                'total_l30' => round($totalL30, 2),
                
                // Calculated Percentages & Averages
                'avg_gpft' => round($avgGpft, 2),
                'avg_dil' => round($avgDil, 2),
                'avg_roi' => round($avgRoi, 2),
                'avg_price' => round($avgPrice, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters
                'filters_applied' => [
                    'inventory' => 'more',  // INV > 0
                    'nrl' => 'REQ',        // REQ only
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'macys',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, REQ only)',
                ]
            );
            
            Log::info("Daily Macy's summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'zero_sold_count' => $zeroSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily Macys summary: ' . $e->getMessage());
        }
    }

    /**
     * Macy's LMP competitors for Master Analytics drawer (same shape as /ebay-lmp-data).
     */
    public function getMacyLmpData(Request $request)
    {
        try {
            $sku = trim((string) $request->input('sku'));
            $linkedSkus = $request->input('linked_lmp_skus', []);
            if ($sku === '') {
                return response()->json(['error' => 'SKU is required'], 400);
            }
            if (!is_array($linkedSkus)) {
                $linkedSkus = [];
            }

            $groupSkus = [$sku];
            try {
                $lmpGroupService = new LmpSkuGroupService();
                $seed = array_values(array_filter(array_map(
                    fn ($value) => trim((string) $value),
                    array_merge([$sku], $linkedSkus)
                )));
                $lmpGroupService->prepareForSkus($seed);
                $resolved = $lmpGroupService->groupContaining($sku);
                if (!empty($resolved)) {
                    $groupSkus = $resolved;
                }
            } catch (\Throwable $e) {
                Log::warning('LmpSkuGroupService in getMacyLmpData failed: ' . $e->getMessage());
            }

            $groupSkus = array_values(array_unique(array_filter(array_map(
                fn ($value) => trim((string) $value),
                array_merge($groupSkus, $linkedSkus, [$sku])
            ))));

            $competitors = collect();
            foreach ($groupSkus as $groupSku) {
                foreach (MacySkuCompetitor::resolveLookupKeys($groupSku) as $lookupSku) {
                    $found = MacySkuCompetitor::getCompetitorsForSku($lookupSku, 'macy');
                    if ($found->isNotEmpty()) {
                        $competitors = $competitors->merge($found);
                    }
                }
            }
            $competitors = MacySkuCompetitor::dedupeByItemId($competitors)
                ->filter(fn ($comp) => (float) ($comp->total_price ?? 0) > 0)
                ->sortBy(fn ($comp) => (float) ($comp->total_price ?? 0))
                ->values();

            $lowest = $competitors->first(fn ($c) => empty($c->ignored)) ?: $competitors->first();

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'competitors' => $competitors->map(function ($comp) {
                    return [
                        'id' => $comp->id,
                        'item_id' => $comp->item_id,
                        'price' => floatval($comp->price ?? 0),
                        'shipping_cost' => floatval($comp->shipping_cost ?? 0),
                        'total_price' => floatval($comp->total_price ?? 0),
                        'ignored' => (bool) ($comp->ignored ?? false),
                        'link' => $comp->product_link,
                        'title' => $comp->product_title,
                        'image' => $comp->image ?? null,
                        'created_at' => optional($comp->created_at)->format('Y-m-d H:i:s'),
                    ];
                }),
                'lowest_price' => $lowest ? floatval($lowest->total_price) : null,
                'total_count' => $competitors->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching Macy LMP data', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch LMP data: ' . $e->getMessage()], 500);
        }
    }

    public function addMacyLmp(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'item_id' => 'required|string',
                'price' => 'required|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'product_link' => 'nullable|string',
                'product_title' => 'nullable|string',
                'image' => 'nullable|string',
            ]);

            $sku = $validated['sku'];
            $itemId = trim((string) $validated['item_id']);
            $price = (float) $validated['price'];
            $shippingCost = (float) ($validated['shipping_cost'] ?? 0);
            $totalPrice = $price + $shippingCost;

            if (MacySkuCompetitor::where('sku', $sku)->where('item_id', $itemId)->exists()) {
                return response()->json([
                    'error' => 'This Macy item is already added as a competitor for this SKU',
                ], 409);
            }

            DB::beginTransaction();
            $lmp = MacySkuCompetitor::create([
                'sku' => $sku,
                'item_id' => $itemId,
                'price' => $price,
                'shipping_cost' => $shippingCost,
                'total_price' => $totalPrice,
                'marketplace' => 'macy',
                'product_link' => $validated['product_link'] ?? null,
                'product_title' => $validated['product_title'] ?? null,
                'image' => $validated['image'] ?? null,
            ]);

            $parent = ProductMaster::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim($sku))])
                ->value('parent');

            LmpCompetitorHistory::logAction(
                sku: $sku,
                action: 'added',
                itemId: $itemId,
                competitorId: (int) $lmp->id,
                productTitle: $validated['product_title'] ?? null,
                totalPrice: $totalPrice,
                parent: $parent ? (string) $parent : null,
                updatedBy: Auth::user()?->name ?? 'N/A',
            );
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Macy LMP added successfully',
                'data' => [
                    'id' => $lmp->id,
                    'sku' => $lmp->sku,
                    'item_id' => $lmp->item_id,
                    'price' => floatval($lmp->price),
                    'shipping_cost' => floatval($lmp->shipping_cost),
                    'total_price' => floatval($lmp->total_price),
                    'product_link' => $lmp->product_link,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding Macy LMP', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to add LMP: ' . $e->getMessage()], 500);
        }
    }

    public function updateMacyLmp(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
                'price' => 'required|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'product_link' => 'nullable|string',
                'item_id' => 'nullable|string',
            ]);

            $lmp = MacySkuCompetitor::find($validated['id']);
            if (!$lmp) {
                return response()->json(['error' => 'LMP entry not found'], 404);
            }

            $price = (float) $validated['price'];
            $shippingCost = array_key_exists('shipping_cost', $validated) && $validated['shipping_cost'] !== null
                ? (float) $validated['shipping_cost']
                : (float) ($lmp->shipping_cost ?? 0);

            DB::beginTransaction();
            $lmp->price = $price;
            $lmp->shipping_cost = $shippingCost;
            $lmp->total_price = $price + $shippingCost;
            if (array_key_exists('product_link', $validated)) {
                $lmp->product_link = $validated['product_link'] ?: null;
            }
            if (!empty($validated['item_id'])) {
                $lmp->item_id = $validated['item_id'];
            }
            $lmp->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Macy LMP updated successfully',
                'data' => [
                    'id' => $lmp->id,
                    'item_id' => $lmp->item_id,
                    'price' => floatval($lmp->price),
                    'total_price' => floatval($lmp->total_price),
                    'product_link' => $lmp->product_link,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating Macy LMP', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update LMP: ' . $e->getMessage()], 500);
        }
    }

    public function deleteMacyLmp(Request $request)
    {
        try {
            $id = $request->input('id');
            $requestItemId = trim((string) $request->input('item_id', ''));
            if (!$id && $requestItemId === '') {
                return response()->json(['error' => 'LMP ID is required'], 400);
            }

            $lmp = $id ? MacySkuCompetitor::find($id) : null;
            if (!$lmp && $requestItemId !== '') {
                $lmp = MacySkuCompetitor::query()
                    ->where('item_id', $requestItemId)
                    ->orderBy('id')
                    ->first();
            }
            if (!$lmp) {
                return response()->json(['error' => 'LMP entry not found'], 404);
            }

            DB::beginTransaction();
            $itemId = trim((string) ($lmp->item_id ?: $requestItemId));
            $toDelete = collect([$lmp]);
            if ($itemId !== '') {
                $toDelete = MacySkuCompetitor::query()->where('item_id', $itemId)->get();
            }

            $deletedIds = [];
            foreach ($toDelete as $row) {
                $parent = ProductMaster::query()
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim((string) $row->sku))])
                    ->value('parent');
                LmpCompetitorHistory::logAction(
                    sku: (string) $row->sku,
                    action: 'deleted',
                    itemId: (string) ($row->item_id ?: $itemId),
                    competitorId: (int) $row->id,
                    productTitle: $row->product_title,
                    totalPrice: is_numeric($row->total_price) ? (float) $row->total_price : null,
                    parent: $parent ? (string) $parent : null,
                    updatedBy: Auth::user()?->name ?? 'N/A',
                );
                $deletedIds[] = (int) $row->id;
                $row->delete();
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($deletedIds) > 1
                    ? ('Macy LMP deleted (' . count($deletedIds) . ' linked rows)')
                    : 'Macy LMP deleted successfully',
                'deleted_ids' => $deletedIds,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting Macy LMP', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to delete LMP: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @return list<string>
     */
    private function linkedLmpSkusForProduct(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $group = $this->lmpSkuGroupService->groupContaining($sku);

        return $this->normalizeLinkedSkuGroup($group !== [] ? $group : [$sku]);
    }

    /**
     * @param  list<string>  $group
     * @return list<string>
     */
    private function normalizeLinkedSkuGroup(array $group): array
    {
        $seen = [];
        $normalized = [];

        foreach ($group as $memberSku) {
            $display = trim((string) $memberSku);
            $norm = strtoupper($display);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $normalized[] = $display;
        }

        return $normalized;
    }
}
