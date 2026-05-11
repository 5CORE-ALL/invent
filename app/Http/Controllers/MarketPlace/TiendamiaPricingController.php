<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\TiendamiaProduct;
use App\Models\TiendamiaPriceUpload;
use App\Models\ShopifySku;
use App\Models\MarketplacePercentage;
use App\Models\TiendamiaDataView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TiendamiaPricingController extends Controller
{
    /**
     * Display Tiendamia Pricing Tabulator View
     */
    public function tiendamiaTabulatorView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Tiendamia')
            ->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 85;

        return view("market-places.tiendamia_tabulator_view", [
            "mode" => $mode,
            "demo" => $demo,
            "tiendamiaPercentage" => $percentage,
        ]);
    }

    /**
     * Get Tiendamia Data JSON for Tabulator
     */
    public function tiendamiaDataJson(Request $request)
    {
        try {
            $response = $this->getViewTiendamiaTabularData($request);
            $data = json_decode($response->getContent(), true);

            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching Tiendamia data for Tabulator: ' . $e->getMessage());

            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                return response()->json([
                    'error' => 'Tiendamia products table not found. Please run: php artisan migrate',
                ], 500);
            }

            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get Tiendamia Tabular Data
     */
    public function getViewTiendamiaTabularData(Request $request)
    {
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Tiendamia')
            ->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 80;
        $percentageValue = $percentage / 100;

        // Fetch all product master records (excluding parent rows)
        $productMasterRows = ProductMaster::all()
            ->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })
            ->keyBy("sku");

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck("sku")->toArray();
        
        // Create uppercase version for Tiendamia products lookup
        $skusUpper = array_map('strtoupper', $skus);

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Fetch Tiendamia product data (m_l30, m_l60, stock)
        $tiendamiaData = TiendamiaProduct::whereIn("sku", $skusUpper)
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        // Fetch Tiendamia price data from tiendamia_price_uploads
        // Get ALL price data since SKU formats may not match exactly
        $priceData = TiendamiaPriceUpload::select('product_sku', 'offer_sku', 'price', 'quantity', 'offer_state')
            ->whereNotNull('price')
            ->get();

        // Fetch Tiendamia view data for SPRICE
        $tiendamiaViewData = TiendamiaDataView::whereIn("sku", $skus)->get()->keyBy("sku");

        // Process data
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, "PARENT") !== false;

            // Initialize the data structure
            $processedItem = [
                "SL No." => $slNo++,
                "Parent" => $productMaster->parent ?? null,
                "(Child) sku" => $sku,
                "is_parent" => $isParent,
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem["LP_productmaster"] = $values["lp"] ?? 0;
            $ship = $values["ship"] ?? 0;
            $processedItem["Ship_productmaster"] = $ship;
            $processedItem["Tiendamia Ship"] = $ship;
            $processedItem["COGS"] = $values["cogs"] ?? 0;
            
            // Image path
            $processedItem["image_path"] = null;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem["INV"] = $shopifyItem->inv ?? 0;
                $processedItem["L30"] = $shopifyItem->quantity ?? 0;
                $processedItem["image_path"] = $shopifyItem->image_src ?? ($values["image_path"] ?? ($productMaster->image_path ?? null));
            } else {
                $processedItem["INV"] = 0;
                $processedItem["L30"] = 0;
                $processedItem["image_path"] = $values["image_path"] ?? ($productMaster->image_path ?? null);
            }

            $skuUpper = strtoupper($sku);

            // Add data from tiendamia_products (m_l30, m_l60, stock)
            if (isset($tiendamiaData[$skuUpper])) {
                $tiendamiaItem = $tiendamiaData[$skuUpper];
                $processedItem["M L30"] = $tiendamiaItem->m_l30 ?? 0;
                $processedItem["M L60"] = $tiendamiaItem->m_l60 ?? 0;
                $processedItem["Tiendamia Stock"] = $tiendamiaItem->stock ?? 0;
                $processedItem["Missing"] = '';
            } else {
                $processedItem["M L30"] = 0;
                $processedItem["M L60"] = 0;
                $processedItem["Tiendamia Stock"] = 0;
                $processedItem["Missing"] = 'M';
            }

            // Add price data from tiendamia_price_uploads
            // Try to find price data by matching the SKU
            $priceItem = null;
            
            // Debug: Log the SKU we're trying to match (only for first 5 items)
            if ($slNo <= 6) {
                Log::info("Tiendamia SKU matching for: {$sku} (uppercase: {$skuUpper})");
            }
            
            foreach ($priceData as $priceRow) {
                $offerSkuUpper = strtoupper(trim($priceRow->offer_sku ?? ''));
                $productSkuUpper = strtoupper(trim($priceRow->product_sku ?? ''));
                
                // Check if SKU matches either offer_sku or product_sku (exact match)
                if ($offerSkuUpper === $skuUpper || $productSkuUpper === $skuUpper) {
                    $priceItem = $priceRow;
                    if ($slNo <= 6) {
                        Log::info("Found exact match! offer_sku: {$offerSkuUpper}, product_sku: {$productSkuUpper}, price: {$priceRow->price}");
                    }
                    break;
                }
            }
            
            // If not found with exact match, try partial matches
            if (!$priceItem) {
                if ($slNo <= 6) {
                    Log::info("No exact match found, trying partial match...");
                }
                
                foreach ($priceData as $priceRow) {
                    $offerSkuUpper = strtoupper(trim($priceRow->offer_sku ?? ''));
                    $productSkuUpper = strtoupper(trim($priceRow->product_sku ?? ''));
                    
                    // Try partial matches (for cases like "04 CS" matching "CS 04 2W")
                    if (!empty($offerSkuUpper) && !empty($skuUpper)) {
                        if (strpos($offerSkuUpper, $skuUpper) !== false || 
                            strpos($skuUpper, $offerSkuUpper) !== false) {
                            $priceItem = $priceRow;
                            if ($slNo <= 6) {
                                Log::info("Found partial match with offer_sku! offer_sku: {$offerSkuUpper}, price: {$priceRow->price}");
                            }
                            break;
                        }
                    }
                    
                    if (!empty($productSkuUpper) && !empty($skuUpper)) {
                        if (strpos($productSkuUpper, $skuUpper) !== false || 
                            strpos($skuUpper, $productSkuUpper) !== false) {
                            $priceItem = $priceRow;
                            if ($slNo <= 6) {
                                Log::info("Found partial match with product_sku! product_sku: {$productSkuUpper}, price: {$priceRow->price}");
                            }
                            break;
                        }
                    }
                }
            }
            
            if (!$priceItem && $slNo <= 6) {
                Log::info("No match found at all for SKU: {$sku}");
            }
            
            if ($priceItem) {
                $processedItem["Tiendamia Price"] = $priceItem->price ?? 0;
                $processedItem["Price Quantity"] = $priceItem->quantity ?? 0;
                $processedItem["Offer State"] = $priceItem->offer_state ?? '';
            } else {
                $processedItem["Tiendamia Price"] = 0;
                $processedItem["Price Quantity"] = 0;
                $processedItem["Offer State"] = '';
            }

            // MAP: same tolerance as other marketplaces — |INV − Tiendamia Stock| ≤ 3 → Map
            $inv = (float) $processedItem["INV"];
            $tiendamiaStock = (float) $processedItem["Tiendamia Stock"];
            $delta = $inv - $tiendamiaStock;
            if (abs($delta) <= 3) {
                $processedItem["MAP"] = 'Map';
            } else {
                $processedItem["MAP"] = 'N Map|'.sprintf('%+g', $delta);
            }

            // Get SPRICE, SGPFT, SPFT, SROI from tiendamia_data_views
            $processedItem["SPRICE"] = 0;
            $processedItem["SGPFT"] = 0;
            $processedItem["SPFT"] = 0;
            $processedItem["SROI"] = 0;

            if (isset($tiendamiaViewData[$sku])) {
                $viewData = $tiendamiaViewData[$sku];
                $valuesArr = is_array($viewData->value) ? $viewData->value : (json_decode($viewData->value ?? '{}', true) ?: []);
                $processedItem["SPRICE"] = isset($valuesArr["SPRICE"]) ? floatval($valuesArr["SPRICE"]) : 0;
                $processedItem["SGPFT"] = isset($valuesArr["SGPFT"]) ? floatval($valuesArr["SGPFT"]) : 0;
                $processedItem["SPFT"] = isset($valuesArr["SPFT"]) ? floatval(str_replace("%", "", $valuesArr["SPFT"])) : 0;
                $processedItem["SROI"] = isset($valuesArr["SROI"]) ? floatval(str_replace("%", "", $valuesArr["SROI"])) : 0;
                $processedItem["NR"] = $valuesArr["NR"] ?? 'RA';
                $processedItem["nrp"] = $valuesArr["NRP"] ?? 'RA';
            } else {
                $processedItem["NR"] = 'RA';
                $processedItem["nrp"] = 'RA';
            }

            // Calculate profit metrics
            $processedItem["percentage"] = $percentageValue;

            $price = floatval($processedItem["Tiendamia Price"]);
            $lp = floatval($processedItem["LP_productmaster"]);
            $ship = floatval($processedItem["Ship_productmaster"]);

            // GPFT%
            if ($price > 0) {
                $gpft_percentage = (($price * $percentageValue - $lp - $ship) / $price) * 100;
                $processedItem["GPFT%"] = round($gpft_percentage, 2);
            } else {
                $processedItem["GPFT%"] = 0;
            }

            // ROI%
            if ($lp > 0) {
                $roi_percentage = (($price * $percentageValue - $lp - $ship) / $lp) * 100;
                $processedItem["ROI%"] = round($roi_percentage, 2);
            } else {
                $processedItem["ROI%"] = 0;
            }

            // Profit
            $processedItem["Profit"] = ($price * $percentageValue) - $lp - $ship;

            // Sales using M L30
            $processedItem["Sales M L30"] = $price * $processedItem["M L30"];

            // Sales using M L60
            $processedItem["Sales M L60"] = $price * $processedItem["M L60"];

            // Dil%
            $inv = $processedItem["INV"];
            $l30 = $processedItem["L30"];
            $processedItem["Tiendamia Dil%"] = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0;

            $processedData[] = $processedItem;
        }

        // Sort by Parent (null/empty last) so same-parent rows are consecutive for grouping
        usort($processedData, function ($a, $b) {
            $pa = $a['Parent'] ?? '';
            $pb = $b['Parent'] ?? '';
            $pa = ($pa !== null && $pa !== '') ? (string) $pa : '';
            $pb = ($pb !== null && $pb !== '') ? (string) $pb : '';
            if ($pa === '' && $pb === '') return 0;
            if ($pa === '') return 1;
            if ($pb === '') return -1;
            $cmp = strcmp($pa, $pb);
            if ($cmp !== 0) return $cmp;
            return strcmp($a['(Child) sku'] ?? '', $b['(Child) sku'] ?? '');
        });

        return response()->json([
            "message" => "Data fetched successfully",
            "data" => $processedData,
            "status" => 200,
        ]);
    }

    /**
     * Upload Tiendamia CSV file
     */
    public function uploadTiendamiaCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,tsv,xlsx,xls'
        ]);

        try {
            $file = $request->file('csv_file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            $imported = 0;
            $skipped = 0;
            $processedSkus = [];
            $rows = [];
            
            // Handle Excel files
            if (in_array($extension, ['xlsx', 'xls'])) {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
            } else {
                // Handle CSV/TXT/TSV files
                $handle = fopen($file->getPathname(), 'r');
                
                // Auto-detect delimiter (tab, comma, semicolon)
                $firstLine = fgets($handle);
                rewind($handle);
                
                $delimiter = ','; // default
                if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                    $delimiter = "\t";
                } elseif (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                    $delimiter = ';';
                }
                
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }
            
            if (empty($rows)) {
                throw new \Exception('No data found in file');
            }
            
            // Process header
            $header = array_shift($rows);
            $headerMap = [];
            if (is_array($header)) {
                foreach ($header as $idx => $col) {
                    $key = strtolower(trim((string)$col));
                    $key = str_replace([' ', '-'], '_', $key);
                    $headerMap[$key] = $idx;
                }
            }
            
            // Map Tiendamia export columns (try multiple column name variations)
            $offerSkuIndex = $headerMap['offer_sku'] ?? $headerMap['offersku'] ?? 0;
            $productSkuIndex = $headerMap['product_sku'] ?? $headerMap['productsku'] ?? 1;
            $priceIndex = $headerMap['price'] ?? 7;
            $quantityIndex = $headerMap['quantity'] ?? $headerMap['qty'] ?? $headerMap['stock'] ?? 9;
            $offerStateIndex = $headerMap['offer_state'] ?? $headerMap['offerstate'] ?? $headerMap['state'] ?? 6;
            
            // Generate unique batch ID for this upload
            $batchId = date('YmdHis') . '_' . uniqid();
            
            // Clear existing data before importing new data
            TiendamiaPriceUpload::truncate();
            
            // Process data rows
            foreach ($rows as $row) {
                $rawOfferSku = $row[$offerSkuIndex] ?? null;
                $rawProductSku = $row[$productSkuIndex] ?? null;
                
                if ($rawOfferSku !== null && trim((string)$rawOfferSku) !== '') {
                    $offerSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace("\xC2\xA0", ' ', $rawOfferSku))));
                    $productSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace("\xC2\xA0", ' ', $rawProductSku ?? ''))));
                    
                    $price = isset($row[$priceIndex]) ? floatval($row[$priceIndex]) : 0;
                    $quantity = isset($row[$quantityIndex]) ? intval($row[$quantityIndex]) : 0;
                    $offerState = isset($row[$offerStateIndex]) ? trim($row[$offerStateIndex]) : '';
                    
                    $uniqueKey = $offerSku . '|' . $productSku;
                    if (isset($processedSkus[$uniqueKey])) {
                        $skipped++;
                        continue;
                    }
                    
                    TiendamiaPriceUpload::create([
                        'upload_batch_id' => $batchId,
                        'offer_sku' => $offerSku,
                        'product_sku' => $productSku,
                        'price' => $price,
                        'quantity' => $quantity,
                        'offer_state' => $offerState,
                    ]);
                    
                    $processedSkus[$uniqueKey] = true;
                    $imported++;
                }
            }

            $message = "Successfully imported $imported Tiendamia price records!";
            
            $details = [];
            if ($imported > 0) {
                $details[] = "$imported records imported";
            }
            if ($skipped > 0) {
                $details[] = "$skipped duplicates skipped";
            }
            
            if (!empty($details)) {
                $message .= " (" . implode(', ', $details) . ")";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Tiendamia CSV Upload Error: ' . $e->getMessage());
            return back()->with('error', 'Error uploading CSV: ' . $e->getMessage());
        }
    }

    /**
     * Download Sample CSV
     */
    public function downloadSampleCsv()
    {
        // Return message that sample is not needed - use Tiendamia export directly
        return response()->json([
            'success' => false,
            'message' => 'Sample not available. Please upload the Tiendamia export file directly (tab-separated with columns: Offer SKU, Product SKU, Price, Quantity, Offer state)'
        ], 400);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Save SPRICE to tiendamia_data_views
     */
    public function saveSpriceUpdates(Request $request)
    {
        try {
            $updates = [];

            if ($request->has('updates')) {
                $updates = $request->input('updates', []);
            } elseif ($request->has('sku') && $request->has('sprice')) {
                $updates = [
                    [
                        'sku' => $request->input('sku'),
                        'sprice' => $request->input('sprice'),
                    ],
                ];
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Tiendamia')
                ->first();
            $marginPct = $marketplaceData ? (float) $marketplaceData->percentage : 80.0;
            $marginFactor = $marginPct / 100.0;

            $updatedCount = 0;
            $errors = [];

            foreach ($updates as $update) {
                $sku = $update['sku'] ?? null;
                $sprice = $update['sprice'] ?? null;

                if (! $sku || $sprice === null) {
                    $errors[] = "Invalid update data for SKU: ".($sku ?? 'unknown');
                    continue;
                }

                $view = TiendamiaDataView::firstOrNew(['sku' => $sku]);
                $values = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);

                $values['SPRICE'] = floatval($sprice);

                $productMaster = ProductMaster::where('sku', $sku)->first();
                if ($productMaster) {
                    $pmValues = $productMaster->Values ?: [];
                    $lp = $pmValues['lp'] ?? 0;
                    $ship = $pmValues['ship'] ?? 0;
                    if ($sprice > 0) {
                        $sgpft = (($sprice * $marginFactor - $lp - $ship) / $sprice) * 100;
                        $values['SGPFT'] = round($sgpft, 2);
                    } else {
                        $values['SGPFT'] = 0;
                    }

                    $values['SPFT'] = $values['SGPFT'].'%';

                    if ($lp > 0) {
                        $sroi = (($sprice * $marginFactor - $lp - $ship) / $lp) * 100;
                        $values['SROI'] = round($sroi, 2).'%';
                    } else {
                        $values['SROI'] = '0%';
                    }
                }

                $view->value = $values;
                $view->save();

                $updatedCount++;
            }

            if ($request->has('sku') && ! $request->has('updates')) {
                if ($updatedCount > 0 && count($updates) > 0) {
                    $update = $updates[0];
                    $sku = $update['sku'];

                    $view = TiendamiaDataView::where('sku', $sku)->first();
                    $values = $view ? (is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: [])) : [];

                    return response()->json([
                        'success' => true,
                        'sgpft_percent' => $values['SGPFT'] ?? 0,
                        'spft_percent' => floatval(str_replace('%', '', $values['SPFT'] ?? '0')),
                        'sroi_percent' => floatval(str_replace('%', '', $values['SROI'] ?? '0')),
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to save SPRICE',
                    ], 400);
                }
            } else {
                return response()->json([
                    'success' => true,
                    'updated' => $updatedCount,
                    'errors' => $errors,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error saving Tiendamia SPRICE updates: ".$e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get/Set Column Visibility
     */
    public function getColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "tiendamia_tabulator_column_visibility_{$userId}";

        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "tiendamia_tabulator_column_visibility_{$userId}";

        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    /**
     * Update utilized field (NR, video_uploaded, etc.) to tiendamia_data_views
     */
    public function updateUtilizedField(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $field = $request->input('field');
            $value = $request->input('value');

            if (!$sku || !$field) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU and field are required'
                ], 400);
            }

            // Only allow updating non-ads fields for Tiendamia
            $allowedFields = ['NR'];
            
            if (!in_array($field, $allowedFields)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Field not allowed for update'
                ], 400);
            }

            $view = TiendamiaDataView::firstOrNew(['sku' => $sku]);
            $values = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);
            
            $values[$field] = $value;
            
            $view->value = $values;
            $view->save();

            return response()->json([
                'success' => true,
                'message' => 'Field updated successfully',
                'updated_json' => $values
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating Tiendamia utilized field: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating field: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test NRP save/load functionality
     */
    public function testNrp(Request $request)
    {
        try {
            $testSku = '04 CS'; // Use the SKU from your screenshot
            
            // Step 1: Get first SKU from product_master if not provided
            if (!$testSku) {
                $testSku = \App\Models\ProductMaster::first()->sku ?? 'TEST-SKU';
            }
            
            // Step 2: Try to save NRP value
            $testValue = 'NRA';
            $tiendamiaDataView = TiendamiaDataView::where('sku', $testSku)->first();
            
            $jsonData = [];
            if ($tiendamiaDataView && $tiendamiaDataView->value !== null) {
                $existing = $tiendamiaDataView->value;
                $jsonData = is_array($existing) ? $existing : (is_string($existing) ? (json_decode($existing, true) ?: []) : []);
            }
            
            $jsonData['NRP'] = $testValue;
            
            $saved = TiendamiaDataView::updateOrCreate(
                ['sku' => $testSku],
                ['value' => $jsonData]
            );
            
            // Step 3: Retrieve it back
            $retrieved = TiendamiaDataView::where('sku', $testSku)->first();
            $retrievedValue = null;
            if ($retrieved) {
                $valuesArr = is_array($retrieved->value) ? $retrieved->value : (json_decode($retrieved->value, true) ?: []);
                $retrievedValue = $valuesArr['NRP'] ?? null;
            }
            
            // Step 4: Return detailed results
            return response()->json([
                'success' => true,
                'test_sku' => $testSku,
                'saved_value' => $testValue,
                'retrieved_value' => $retrievedValue,
                'match' => ($testValue === $retrievedValue),
                'saved_record' => $saved ? $saved->toArray() : null,
                'retrieved_record' => $retrieved ? $retrieved->toArray() : null,
                'model_fillable' => (new TiendamiaDataView())->getFillable(),
                'model_casts' => (new TiendamiaDataView())->getCasts(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Save NRP updates (same pattern as Amazon's updateNrNRLFba)
     */
    public function saveNrp(Request $request)
    {
        // Accept both JSON body and form input
        $sku   = trim((string) ($request->input('sku') ?? $request->json('sku') ?? ''));
        $field = $request->input('field') ?? $request->json('field');
        $value = $request->input('value') ?? $request->json('value');

        if ($sku === '' || $field === null || $field === '') {
            return response()->json([
                'status' => 422,
                'message' => 'SKU and field are required',
                'success' => false,
            ], 422);
        }

        if ($field !== 'NRP') {
            return response()->json([
                'status' => 422,
                'message' => 'Invalid field',
                'success' => false,
            ], 422);
        }

        $tiendamiaDataView = TiendamiaDataView::where('sku', $sku)->first();

        $jsonData = [];
        if ($tiendamiaDataView && $tiendamiaDataView->value !== null) {
            $existing = $tiendamiaDataView->value;
            $jsonData = is_array($existing) ? $existing : (is_string($existing) ? (json_decode($existing, true) ?: []) : []);
        }

        $jsonData[$field] = $value;

        TiendamiaDataView::updateOrCreate(
            ['sku' => $sku],
            ['value' => $jsonData]
        );

        return response()->json([
            'status' => 200,
            'message' => 'NRP saved successfully',
            'success' => true,
            'updated_json' => $jsonData,
        ]);
    }
}
