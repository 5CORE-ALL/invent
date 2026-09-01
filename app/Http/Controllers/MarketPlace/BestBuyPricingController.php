<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\ShopifySku;
use App\Models\BestbuyUSADataView;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\BestbuyUsaProduct;
use App\Models\BestbuyUSAListingStatus;
use App\Models\BestbuyPriceData;
use App\Models\BestbuySkuCompetitor;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\LmpCompetitorHistory;
use App\Services\ChannelPromoPricingService;
use App\Services\LmpSkuGroupService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BestBuyApiService;
use App\Support\ProductMasterShipBb;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\AmazonChannelSummary;
use App\Models\ChannelTabulatorColumnSetting;

class BestBuyPricingController extends Controller
{
    protected LmpSkuGroupService $lmpSkuGroupService;

    public function __construct(LmpSkuGroupService $lmpSkuGroupService)
    {
        $this->lmpSkuGroupService = $lmpSkuGroupService;
    }

    public function bestbuyPricingView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        $marketplaceData = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 80;

        return view("market-places.bestbuy_tabulator_view", [
            "mode" => $mode,
            "demo" => $demo,
            "bestbuyPercentage" => $percentage,
        ]);
    }

    public function bestbuyDataJson(Request $request)
    {
        try {
            $response = $this->getViewBestBuyData($request);
            $data = json_decode($response->getContent(), true);

            // Auto-save daily summary in background (non-blocking)
            $this->saveDailySummaryIfNeeded($data['data'] ?? []);

            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching Best Buy data for Tabulator: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getViewBestBuyData(Request $request)
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

        $promoMap = app(ChannelPromoPricingService::class)->mapForSkus('bestbuy', $skus);

        // Sku Link LMP groups + BestbuySkuCompetitor lookup (same as Reverb / Amazon tabulators)
        try {
            $this->lmpSkuGroupService->prepareForSkus($skus);
        } catch (\Throwable $e) {
            Log::warning('LmpSkuGroupService prepareForSkus failed on bestbuy pricing: '.$e->getMessage());
        }

        $lmpDetailsLookup = collect();
        try {
            $lmpLookups = BestbuySkuCompetitor::buildGroupedLookup('bestbuy');
            $lmpDetailsLookup = $lmpLookups['details'];
        } catch (\Throwable $e) {
            Log::warning('Could not fetch LMP data from bestbuy_sku_competitors: '.$e->getMessage());
        }

        // 3. Related Models
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        $bestbuyMetrics = BestbuyUsaProduct::whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        // Fetch price data from BestbuyPriceData table
        // Key by UPPERCASE sku because uploadPriceData() stores SKUs uppercased,
        // while ProductMaster keeps original casing. Without this, mixed-case
        // SKUs (e.g. "SS ECO 1PK BLK WoB") fail the lookup and fall back to
        // the stale BestbuyUsaProduct price instead of the uploaded price.
        $priceDataCollection = BestbuyPriceData::whereIn('sku', $skus)
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        // Fetch Amazon pricing data
        $amazonData = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');

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

        // NR/REQ + SPRICE data from BestbuyUSADataView
        $dataViews = BestbuyUSADataView::whereIn("sku", $skus)->pluck("value", "sku");

        // Listing status data
        $listingStatusData = BestbuyUSAListingStatus::whereIn("sku", $skus)
            ->get()
            ->mapWithKeys(function ($item) {
                return [strtolower($item->sku) => $item];
            });

        // 4. Marketplace percentage (80% for Best Buy)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.80;

        // 5. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData->get($pm->sku);
            $bestbuyMetric = $bestbuyMetrics[$pm->sku] ?? null;
            $listingStatus = $listingStatusData[strtolower($pm->sku)] ?? null;
            $priceData = $priceDataCollection[strtoupper($pm->sku)] ?? null;
            $amazon = $amazonData[$pm->sku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;

            // Shopify data
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;

            // Price: uploaded sheet first; if SKU not on sheet → bestbuy_usa_products.price (same as Tiendamia).
            $row["BB L30"] = $bestbuyMetric->m_l30 ?? 0;
            $row["BB Price"] = $priceData
                ? floatval($priceData->price ?? 0)
                : floatval($bestbuyMetric->price ?? 0);
            $row["BB INV"] = $bestbuyMetric->stock ?? 0; // Marketplace inventory/stock for mapping
            $row["Price Source"] = $priceData ? 'sheet' : (floatval($row["BB Price"]) > 0 ? 'product' : '');
            
            // Amazon Price
            $row["A Price"] = $amazon ? floatval($amazon->price ?? 0) : 0;

            // NR/REQ + Links from BestbuyUSAListingStatus (same as listing page)
            $row['nr_req'] = 'REQ';
            $row['B Link'] = '';
            $row['S Link'] = '';

            if ($listingStatus) {
                $statusValue = is_array($listingStatus->value)
                    ? $listingStatus->value
                    : (json_decode($listingStatus->value, true) ?? []);

                // Get NR/REQ from listing status table
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
            $row["BB Dil%"] = ($row["BB L30"] && $row["INV"] > 0)
                ? round(($row["BB L30"] / $row["INV"]), 2)
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

        // BestBuy uses Ship BB (slab + handling + o-size), matching Shipping Master.
        $ship = ProductMasterShipBb::forPricing(is_array($values) ? $values : [], $pm);

        // Price and units for calculations
            $price = floatval($row["BB Price"] ?? 0);
            $units_ordered_l30 = floatval($row["BB L30"] ?? 0);

            // Profit/Sales calculations
            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["Profit"] = $row["Total_pft"];
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["Sales L30"] = $row["T_Sale_l30"];

            // GPFT% = ((price * percentage - ship - lp) / price) * 100
            $gpft = $price > 0 ? (($price * $percentage - $ship - $lp) / $price) * 100 : 0;
            $row["GPFT%"] = round($gpft, 2);

            // PFT% = GPFT% (no ads for Best Buy)
            $row["PFT %"] = round($gpft, 2);

            // ROI% = ((price * percentage - lp - ship) / lp) * 100
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );

            $row["percentage"] = $percentage;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;
            $row["handling_charge"] = $values['handling_charge'] ?? null;
            $row["o_size_charge"] = $values['o_size_charge'] ?? null;

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

            // SPRICE calculation (no CVR for Best Buy, just use price directly)
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
            $row['SPFT'] = $sgpft; // No ads, so SPFT = SGPFT

            // SROI: ((SPRICE * percentage - lp - ship) / lp) * 100
            $row['SROI'] = round(
                $lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );

            // Image
            $row["image_path"] = $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null));

            // LMP — lowest total_price from bestbuy_sku_competitors across Sku Link LMP group
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

            $allLmpEntries = collect();
            foreach ($linkedLmpSkus as $linkedSku) {
                foreach (BestbuySkuCompetitor::resolveLookupKeys((string) $linkedSku) as $lookupKey) {
                    $entries = $lmpDetailsLookup->get($lookupKey);
                    if ($entries instanceof \Illuminate\Support\Collection && $entries->isNotEmpty()) {
                        $allLmpEntries = $allLmpEntries->merge($entries);
                    }
                }
            }
            $allLmpEntries = BestbuySkuCompetitor::dedupeByItemId($allLmpEntries)
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
            $row = app(ChannelPromoPricingService::class)->applyToRow($row, $promoMap, (string) $pm->sku);

            $result[] = (object) $row;
        }

        return response()->json([
            "message" => "Best Buy Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }

    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input("sku");
        $nr = $request->input("nr");

        if (!$sku || $nr === null) {
            return response()->json(["success" => false, "message" => "SKU and NR value required"], 400);
        }

        // Save to BestbuyUSAListingStatus (same table as listing page)
        $sku = trim($sku);
        
        // Delete existing and create fresh (same logic as listing page)
        BestbuyUSAListingStatus::where('sku', $sku)->delete();
        
        $status = BestbuyUSAListingStatus::create([
            'sku' => $sku,
            'value' => ['nr_req' => $nr]
        ]);

        return response()->json(["success" => true, "data" => $status, "message" => "NR updated successfully"]);
    }

    /**
     * Save buyer / seller links for a SKU into bestbuy_u_s_a_listing_statuses.value JSON.
     * Empty strings clear the link (URL validation only applies to non-empty values).
     */
    public function saveLinks(Request $request)
    {
        $sku = trim((string) $request->input('sku'));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }

        $buyerLink = trim((string) $request->input('buyer_link', ''));
        $sellerLink = trim((string) $request->input('seller_link', ''));

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $field => $val) {
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'Invalid URL for ' . $field], 422);
            }
        }

        // Preserve any existing values (e.g. nr_req) on the latest record
        $status = BestbuyUSAListingStatus::where('sku', $sku)->orderBy('updated_at', 'desc')->first();
        $existing = $status
            ? (is_array($status->value) ? $status->value : (json_decode($status->value, true) ?: []))
            : [];

        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;

        // Delete duplicates and recreate (mirrors NR save pattern on this page)
        BestbuyUSAListingStatus::where('sku', $sku)->delete();
        BestbuyUSAListingStatus::create([
            'sku' => $sku,
            'value' => $existing,
        ]);

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
    }

    /**
     * Legacy single-SKU save endpoint — delegates to saveSpriceUpdates()
     * which accepts both {sku,sprice} and {updates:[...]} payloads.
     */
    public function saveSpriceToDatabase(Request $request)
    {
        return $this->saveSpriceUpdates($request);
    }

    public function updateListedLive(Request $request)
    {
        // Handle NR/REQ updates - save to BestbuyUSAListingStatus (same as listing page)
        if ($request->has('nr_req')) {
            $sku = trim($request->input('sku'));
            $nrReq = $request->input('nr_req');

            // Get existing record or create new
            $status = BestbuyUSAListingStatus::where('sku', $sku)->first();
            
            if ($status) {
                $existing = is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []);
            } else {
                $existing = [];
            }
            
            $existing['nr_req'] = $nrReq;
            
            // Delete and recreate (same logic as listing page saveStatus)
            BestbuyUSAListingStatus::where('sku', $sku)->delete();
            BestbuyUSAListingStatus::create([
                'sku' => $sku,
                'value' => $existing
            ]);

            return response()->json(['success' => true, 'message' => 'NR/REQ updated']);
        }

        // Original validation for Listed/Live
        $request->validate([
            'sku' => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean'
        ]);

        $product = BestbuyUSADataView::firstOrCreate(
            ['sku' => $request->sku],
            ['value' => []]
        );

        $currentValue = is_array($product->value)
            ? $product->value
            : (json_decode($product->value, true) ?? []);

        $currentValue[$request->field] = filter_var($request->value, FILTER_VALIDATE_BOOLEAN);

        $product->value = $currentValue;
        $product->save();

        return response()->json(['success' => true]);
    }

    /**
     * Column visibility is persisted in the shared `channel_tabulator_column_settings`
     * DB table (channel = 'bestbuy_tabulator'), matching every other tabulator page.
     * Previously this used Cache, which is not durable across cache clears/drivers.
     */
    public function getColumnVisibility(Request $request)
    {
        $row = ChannelTabulatorColumnSetting::where('channel_name', 'bestbuy_tabulator')->first();

        $visibility = $row && is_array($row->visibility) ? $row->visibility : [];

        return response()->json($visibility);
    }

    public function setColumnVisibility(Request $request)
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
            ['channel_name' => 'bestbuy_tabulator'],
            ['visibility' => $normalized]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Upload Price Data - TRUNCATE and INSERT (same as Walmart)
     */
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
            
            // TRUNCATE TABLE
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            BestbuyPriceData::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            Log::info('BestBuy Price Data table truncated before import');

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                foreach ($rows as $row) {
                    $row = array_map('trim', $row);
                    
                    // Skip empty rows
                    if (count(array_filter($row)) === 0) {
                        $skipped++;
                        continue;
                    }
                    
                    $rowData = array_combine($headers, $row);
                    
                    // Use "Offer SKU" as the SKU field (first column in price file)
                    $sku = strtoupper($rowData['Offer SKU'] ?? '');
                    if (empty($sku)) {
                        $skipped++;
                        continue;
                    }

                    BestbuyPriceData::create([
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
                        'product_tax_code' => $rowData['Product tax code'] ?? null,
                        'discount_price' => !empty($rowData['Discount price']) ? floatval($rowData['Discount price']) : null,
                        'discount_start_date' => !empty($rowData['Discount Start Date']) ? date('Y-m-d', strtotime($rowData['Discount Start Date'])) : null,
                        'discount_end_date' => !empty($rowData['Discount End Date']) ? date('Y-m-d', strtotime($rowData['Discount End Date'])) : null,
                        'lead_time_to_ship' => !empty($rowData['Lead time to ship']) ? intval($rowData['Lead time to ship']) : null,
                        'gtin' => $rowData['GTIN'] ?? null,
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
            Log::error('Error importing BestBuy price data: ' . $e->getMessage());
            return response()->json(['error' => 'Error importing file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Parse file - supports CSV, TSV, Excel (.xlsx, .xls)
     */
    private function parseFile($file)
    {
        $fileName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Try Excel format first if extension suggests it
        if (in_array($extension, ['xlsx', 'xls'])) {
            try {
                $spreadsheet = IOFactory::load($file->getPathName());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
                
                // Filter out empty rows
                return array_filter($rows, function($row) {
                    return count(array_filter($row)) > 0;
                });
            } catch (\Exception $e) {
                Log::warning('Failed to parse as Excel, trying text format: ' . $e->getMessage());
            }
        }
        
        // Parse as text (CSV/TSV/Tab-separated)
        $content = file_get_contents($file->getRealPath());
        $content = preg_replace('/^\x{FEFF}/u', '', $content); // Remove BOM
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        // Detect delimiter (tab or comma)
        $firstLine = explode("\n", $content)[0] ?? '';
        $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
        
        // Parse with detected delimiter
        $rows = array_map(function($line) use ($delimiter) {
            return str_getcsv($line, $delimiter);
        }, explode("\n", $content));
        
        // Filter out empty rows
        return array_filter($rows, function($row) {
            return count($row) > 0 && count(array_filter($row)) > 0;
        });
    }

    /**
     * Save Amazon price updates to BestBuy pricing data (with 12-hour expiration)
     */
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

                // Update or create record in BestbuyUSADataView table
                $dataViewRecord = BestbuyUSADataView::where('sku', $sku)->first();
                
                // If sprice is 0, remove SPRICE data (clearing)
                if ($sprice == 0) {
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

                $ship = ProductMasterShipBb::forPricing(is_array($values) ? $values : [], $pm);

                // Get marketplace percentage (80%)
                $marketplaceData = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
                $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.80;

                // Calculate SGPFT
                $sgpft = $sprice > 0 ? round((($sprice * $percentage - $ship - $lp) / $sprice) * 100, 2) : 0;

                // SPFT = SGPFT (no ads for Best Buy)
                $spft = $sgpft;

                // SROI
                $sroi = round(
                    $lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0,
                    2
                );

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
                    BestbuyUSADataView::create([
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
                $pushResult = $this->pushPriceToBestBuy($pushItem['sku'], (float) $pushItem['sprice']);
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

            Log::info("BestBuy SPRICE updates saved to bestbuy_usa_data_view: {$updated} records updated");

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving SPRICE updates: ' . $e->getMessage());
            return response()->json(['error' => 'Error saving updates: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Push saved SPRICE to Best Buy marketplace API.
     *
     * @return array{success:bool,message:string}
     */
    private function pushPriceToBestBuy(string $sku, float $sprice): array
    {
        if ($sprice <= 0) {
            return ['success' => false, 'message' => 'Skipping push for non-positive price'];
        }

        try {
            return app(BestBuyApiService::class)->updatePrice($sku, $sprice);
        } catch (\Throwable $e) {
            Log::error('Best Buy price push call failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Auto-save daily BestBuy summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() (including |INV − BB INV| &gt; 3 for mapping issues, like Temu/Macys/eBay).
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
            $missingCount = 0;
            $mappingCount = 0;
            
            // Loop through each row (EXACT JavaScript forEach logic)
            foreach ($filteredData as $row) {
                $totalPft += floatval($row['Profit'] ?? 0);
                $totalSales += floatval($row['Sales L30'] ?? 0);
                $totalGpft += floatval($row['GPFT%'] ?? 0);
                
                $price = floatval($row['BB Price'] ?? 0);
                $inv = floatval($row['INV'] ?? 0);
                $nrReq = $row['nr_req'] ?? 'REQ';
                $isMissing = ($price == 0);
                
                if ($price > 0) {
                    $totalPrice += $price;
                    $priceCount++;
                } else {
                    // Only count missing prices for REQ items with INV > 0
                    if ($nrReq === 'REQ' && $inv > 0) {
                        $missingCount++;
                    }
                }
                
                $totalInv += $inv;
                $totalL30 += floatval($row['BB L30'] ?? 0);
                
                // Count zero sold
                if (floatval($row['BB L30'] ?? 0) == 0) {
                    $zeroSoldCount++;
                }
                
                $dil = floatval($row['BB Dil%'] ?? 0);
                if ($dil > 0) {
                    $totalDil += $dil;
                    $dilCount++;
                }
                
                // COGS = LP × BB L30
                $lp = floatval($row['LP_productmaster'] ?? 0);
                $l30 = floatval($row['BB L30'] ?? 0);
                $totalCogs += $lp * $l30;
                
                $roi = floatval($row['ROI%'] ?? 0);
                if ($roi != 0) {
                    $totalRoi += $roi;
                    $roiCount++;
                }
                
                // Count mapping issues when |INV − BB INV| > 3 (REQ, priced, INV > 0) — same tolerance as other marketplaces
                if ($nrReq === 'REQ' && $inv > 0 && ! $isMissing) {
                    $bbInv = floatval($row['BB INV'] ?? 0);
                    if (abs($inv - $bbInv) > 3) {
                        $mappingCount++;
                    }
                }
            }
            
            // Calculate averages (EXACT JavaScript logic)
            $avgGpft = $totalSkuCount > 0 ? $totalGpft / $totalSkuCount : 0;
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
                    'channel' => 'bestbuy',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, REQ only)',
                ]
            );
            
            Log::info("Daily BestBuy summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'zero_sold_count' => $zeroSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily BestBuy summary: ' . $e->getMessage());
        }
    }

    /**
     * BestBuy LMP competitors for Master Analytics drawer (same shape as /ebay-lmp-data).
     */
    public function getBestbuyLmpData(Request $request)
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
                Log::warning('LmpSkuGroupService in getBestbuyLmpData failed: ' . $e->getMessage());
            }

            $groupSkus = array_values(array_unique(array_filter(array_map(
                fn ($value) => trim((string) $value),
                array_merge($groupSkus, $linkedSkus, [$sku])
            ))));

            $competitors = collect();
            foreach ($groupSkus as $groupSku) {
                foreach (BestbuySkuCompetitor::resolveLookupKeys($groupSku) as $lookupSku) {
                    $found = BestbuySkuCompetitor::getCompetitorsForSku($lookupSku, 'bestbuy');
                    if ($found->isNotEmpty()) {
                        $competitors = $competitors->merge($found);
                    }
                }
            }
            $competitors = BestbuySkuCompetitor::dedupeByItemId($competitors)
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
            Log::error('Error fetching BestBuy LMP data', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch LMP data: ' . $e->getMessage()], 500);
        }
    }

    public function addBestbuyLmp(Request $request)
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

            if (BestbuySkuCompetitor::where('sku', $sku)->where('item_id', $itemId)->exists()) {
                return response()->json([
                    'error' => 'This BestBuy item is already added as a competitor for this SKU',
                ], 409);
            }

            DB::beginTransaction();
            $lmp = BestbuySkuCompetitor::create([
                'sku' => $sku,
                'item_id' => $itemId,
                'price' => $price,
                'shipping_cost' => $shippingCost,
                'total_price' => $totalPrice,
                'marketplace' => 'bestbuy',
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
                'message' => 'BestBuy LMP added successfully',
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
            Log::error('Error adding BestBuy LMP', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to add LMP: ' . $e->getMessage()], 500);
        }
    }

    public function updateBestbuyLmp(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
                'price' => 'required|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'product_link' => 'nullable|string',
                'item_id' => 'nullable|string',
            ]);

            $lmp = BestbuySkuCompetitor::find($validated['id']);
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
                'message' => 'BestBuy LMP updated successfully',
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
            Log::error('Error updating BestBuy LMP', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update LMP: ' . $e->getMessage()], 500);
        }
    }

    public function deleteBestbuyLmp(Request $request)
    {
        try {
            $id = $request->input('id');
            $requestItemId = trim((string) $request->input('item_id', ''));
            if (!$id && $requestItemId === '') {
                return response()->json(['error' => 'LMP ID is required'], 400);
            }

            $lmp = $id ? BestbuySkuCompetitor::find($id) : null;
            if (!$lmp && $requestItemId !== '') {
                $lmp = BestbuySkuCompetitor::query()
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
                $candidates = BestbuySkuCompetitor::query()->where('item_id', $itemId)->get();
                $filtered = LmpSkuGroupService::filterRowsToSkuGroup($candidates, (string) $lmp->sku);
                $toDelete = $filtered->isNotEmpty() ? $filtered : collect([$lmp]);
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
                    ? ('BestBuy LMP deleted (' . count($deletedIds) . ' linked rows)')
                    : 'BestBuy LMP deleted successfully',
                'deleted_ids' => $deletedIds,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting BestBuy LMP', ['error' => $e->getMessage()]);
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
