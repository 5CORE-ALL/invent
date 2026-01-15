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
use App\Models\AmazonDatasheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BestBuyPricingController extends Controller
{
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

        // 3. Related Models
        $shopifyData = ShopifySku::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");

        $bestbuyMetrics = BestbuyUsaProduct::whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        // Fetch price data from BestbuyPriceData table
        $priceDataCollection = BestbuyPriceData::whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        // Fetch Amazon pricing data
        $amazonData = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');

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

            $shopify = $shopifyData[$pm->sku] ?? null;
            $bestbuyMetric = $bestbuyMetrics[$pm->sku] ?? null;
            $listingStatus = $listingStatusData[strtolower($pm->sku)] ?? null;
            $priceData = $priceDataCollection[$pm->sku] ?? null;
            $amazon = $amazonData[$pm->sku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;

            // Shopify data
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;

            // Best Buy Metrics - Get price from BestbuyPriceData if available, otherwise from BestbuyUsaProduct
            $row["BB L30"] = $bestbuyMetric->m_l30 ?? 0;
            $row["BB Price"] = $priceData ? ($priceData->price ?? 0) : ($bestbuyMetric->price ?? 0);
            $row["BB INV"] = $bestbuyMetric->stock ?? 0; // Marketplace inventory/stock for mapping
            
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

            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

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

    public function saveSpriceToDatabase(Request $request)
    {
        Log::info('Saving Best Buy pricing data', $request->all());
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            return response()->json(['error' => 'SKU and SPRICE are required'], 400);
        }

        // Get marketplace percentage (80%)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.80;

        // Get ProductMaster for lp and ship
        $pm = ProductMaster::where('sku', $sku)->first();
        if (!$pm) {
            return response()->json(['error' => 'SKU not found in product master'], 404);
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

        // Calculate SGPFT
        $spriceFloat = floatval($sprice);
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * $percentage - $ship - $lp) / $spriceFloat) * 100, 2) : 0;

        // SPFT = SGPFT (no ads for Best Buy)
        $spft = $sgpft;

        // SROI
        $sroi = round(
            $lp > 0 ? (($spriceFloat * $percentage - $lp - $ship) / $lp) * 100 : 0,
            2
        );

        $dataView = BestbuyUSADataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?? []);

        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
            'SGPFT' => $sgpft,
        ]);

        $dataView->value = $merged;
        $dataView->save();

        return response()->json([
            'success' => true,
            'spft_percent' => $spft,
            'sroi_percent' => $sroi,
            'sgpft_percent' => $sgpft
        ]);
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

    public function getColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "bestbuy_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "bestbuy_tabulator_column_visibility_{$userId}";
        
        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
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

                $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

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

            $response = [
                'success' => true,
                'updated' => $updated,
                'message' => "Successfully saved {$updated} SPRICE update(s)"
            ];

            // Include calculated metrics for single updates (manual cell edits)
            if (isset($lastMetrics)) {
                $response = array_merge($response, $lastMetrics);
            }

            if (!empty($errors)) {
                $response['errors'] = $errors;
                $response['message'] .= ' with ' . count($errors) . ' error(s)';
            }

            Log::info("BestBuy SPRICE updates saved to bestbuy_usa_data_view: {$updated} records updated");

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving SPRICE updates: ' . $e->getMessage());
            return response()->json(['error' => 'Error saving updates: ' . $e->getMessage()], 500);
        }
    }
}
