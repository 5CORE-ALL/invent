<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Http\Controllers\ShopifyApiInventoryController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{

    protected $shopifyDomain;
    protected $shopifyApiKey;
    protected $shopifyPassword;

    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
        $this->shopifyDomain = env('SHOPIFY_STORE_URL');
        $this->shopifyApiKey = env('SHOPIFY_API_KEY');
        $this->shopifyPassword = env('SHOPIFY_PASSWORD');
    }

    /**
     * Make Shopify API call with aggressive retry on any error
     * Uses longer backoff for better reliability - especially for variant fetch
     */
    private function shopifyApiCall($method, $url, $data = [], $maxRetries = 5)
    {
        $attempt = 0;
        $response = null;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            
            // Longer wait times for better reliability: 0.5, 1, 2, 3, 4 seconds
            if ($attempt > 1) {
                $waitTime = max(0.5, $attempt - 0.5); // 0.5, 1, 2, 3, 4 seconds
                Log::warning("Shopify API retry attempt {$attempt}/{$maxRetries}, waiting {$waitTime}s", [
                    'url' => $url,
                    'method' => $method,
                    'attempt' => $attempt
                ]);
                usleep($waitTime * 1000000); // Use microseconds for more precision
            }
            
            try {
                // Use 10 second timeout for more reliable connections
                $request = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->shopifyPassword,
                    'Content-Type' => 'application/json',
                ])->timeout(10);
                
                if ($method === 'GET') {
                    $response = $request->get($url, $data);
                } else {
                    $response = $request->post($url, $data);
                }
                
                // Success - return immediately
                if ($response->successful()) {
                    Log::info("Shopify API call successful on attempt {$attempt}", [
                        'url' => $url,
                        'method' => $method,
                        'attempt' => $attempt
                    ]);
                    return $response;
                }
                
                // If rate limited (429), retry with backoff
                if ($response->status() === 429 && $attempt < $maxRetries) {
                    Log::warning("Rate limit hit (429) on attempt {$attempt}, will retry", [
                        'url' => $url,
                        'attempt' => $attempt
                    ]);
                    continue;
                }
                
                // If server error (5xx), always retry
                if ($response->status() >= 500 && $response->status() < 600 && $attempt < $maxRetries) {
                    Log::warning("Server error ({$response->status()}) on attempt {$attempt}, will retry", [
                        'url' => $url,
                        'status' => $response->status(),
                        'attempt' => $attempt
                    ]);
                    continue;
                }
                
                // If timeout-like error, retry
                if ($response->status() >= 502 && $response->status() <= 504 && $attempt < $maxRetries) {
                    Log::warning("Timeout error ({$response->status()}) on attempt {$attempt}, will retry", [
                        'url' => $url,
                        'attempt' => $attempt
                    ]);
                    continue;
                }
                
                // Other errors - return the response
                return $response;
                
            } catch (\Exception $e) {
                Log::warning("Exception in Shopify API call attempt {$attempt}: {$e->getMessage()}", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt
                ]);
                
                // Retry on any connection error
                if ($attempt < $maxRetries) {
                    continue;   
                }
                
                // All attempts failed
                Log::error("All Shopify API retry attempts failed for URL: {$url}", [
                    'total_attempts' => $attempt,
                    'last_error' => $e->getMessage()
                ]);
                throw $e;
            }
        }
        
        return $response;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::select('id', 'name')->get();
        $skus = ProductMaster::select('id','parent','sku')->get();

        return view('inventory-management.stock-adjustment-view', compact('warehouses', 'skus'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Set longer execution time for this operation, but less than PHP's default 30 seconds
        set_time_limit(25);
        
        $request->validate([
            'sku' => 'required|string',
            'parent' => 'required|string',
            'qty' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'adjustment' => ['required', Rule::in(['Add', 'Reduce'])],
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);

        $sku = trim($request->sku);
        $qty = (int) $request->qty;
        $adjustment = $request->adjustment;

        // Log the incoming request for debugging
        Log::info("Stock adjustment request received", [
            'sku' => $sku,
            'qty' => $qty,
            'adjustment' => $adjustment,
            'warehouse_id' => $request->warehouse_id,
            'user' => Auth::user()->name ?? 'Unknown'
        ]);

        try {
            // 1. Get variant_id from local shopify_skus table (instant - no API call needed)
            $shopifySku = ShopifySku::where('sku', $sku)->first();
            
            if (!$shopifySku || !$shopifySku->variant_id) {
                Log::error("SKU not found in shopify_skus table", [
                    'sku' => $sku,
                    'found_in_db' => $shopifySku ? 'yes' : 'no'
                ]);
                return response()->json([
                    'error' => 'SKU not found in Shopify inventory',
                    'details' => "The SKU '{$sku}' was not found in your local Shopify inventory table. Please sync your Shopify data first."
                ], 404);
            }

            $variantId = $shopifySku->variant_id;
            
            Log::info("SKU found in local database", [
                'sku' => $sku,
                'variant_id' => $variantId,
                'current_inventory' => $shopifySku->on_hand ?? $shopifySku->inv
            ]);

            $adjustValue = $adjustment === 'Add' ? $qty : -$qty;

            // Step 1: Get inventory_item_id from variant 
            // This is the most critical step - if SKU exists, this MUST succeed
            try {
                $variantResponse = $this->shopifyApiCall(
                    'GET',
                    "https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$variantId}.json",
                    [],
                    5 // More retries for variant fetch since it's critical and SKU definitely exists
                );
            } catch (\Exception $e) {
                Log::error("Exception fetching variant for SKU {$sku}", [
                    'error' => $e->getMessage(),
                    'variant_id' => $variantId
                ]);
                
                return response()->json([
                    'error' => 'Cannot connect to Shopify',
                    'details' => 'Network error connecting to Shopify. Please check your internet connection and try again.'
                ], 503);
            }

            if (!$variantResponse || !$variantResponse->successful()) {
                $status = $variantResponse ? $variantResponse->status() : 'No Response';
                Log::error("Failed to fetch variant after all retries", [
                    'status' => $status,
                    'body' => $variantResponse ? $variantResponse->body() : 'No response received',
                    'sku' => $sku,
                    'variant_id' => $variantId
                ]);
                
                // Return a more helpful error message
                return response()->json([
                    'error' => 'Failed to fetch product from Shopify',
                    'details' => 'Could not retrieve product data from Shopify after multiple attempts. This may be a temporary Shopify outage. Please wait a moment and try again.'
                ], 503);
            }

            $variant = $variantResponse->json('variant');
            $inventoryItemId = $variant['inventory_item_id'] ?? null;
            
            if (!$inventoryItemId) {
                return response()->json([
                    'error' => 'Invalid product data',
                    'details' => 'Could not find inventory item ID for this SKU'
                ], 500);
            }

            Log::info('Got inventory_item_id from Shopify', [
                'sku' => $sku,
                'variant_id' => $variantId,
                'inventory_item_id' => $inventoryItemId
            ]);

            // Step 2: Get location_id from inventory levels with retry
            $levelsResponse = $this->shopifyApiCall(
                'GET',
                "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json",
                ['inventory_item_ids' => $inventoryItemId]
            );

            if (!$levelsResponse->successful()) {
                Log::error("Failed to fetch inventory levels after retries", [
                    'status' => $levelsResponse->status(),
                    'body' => $levelsResponse->body(),
                    'sku' => $sku
                ]);
                
                return response()->json([
                    'error' => 'Failed to fetch inventory levels from Shopify',
                    'details' => 'Shopify API Error: ' . $levelsResponse->status() . '. Please try again.'
                ], 500);
            }

            $levels = $levelsResponse->json('inventory_levels');
            $locationId = $levels[0]['location_id'] ?? null;
            $currentAvailable = $levels[0]['available'] ?? 0;

            if (!$locationId) {
                return response()->json([
                    'error' => 'Location not found',
                    'details' => 'Could not find Shopify location for this SKU'
                ], 500);
            }

            Log::info('Got location and current inventory', [
                'sku' => $sku,
                'location_id' => $locationId,
                'current_available' => $currentAvailable,
                'adjustment' => $adjustValue
            ]);

            // Step 3: Adjust inventory using REST API with retry
            $adjustResponse = $this->shopifyApiCall(
                'POST',
                "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json",
                [
                    'inventory_item_id' => $inventoryItemId,
                    'location_id' => $locationId,
                    'available_adjustment' => $adjustValue,
                ]
            );

            if (!$adjustResponse->successful()) {
                Log::error("Failed to adjust inventory in Shopify after retries", [
                    'sku' => $sku,
                    'status' => $adjustResponse->status(),
                    'body' => $adjustResponse->body()
                ]);
                
                return response()->json([
                    'error' => 'Failed to update inventory in Shopify',
                    'details' => 'Shopify API Error: ' . $adjustResponse->status() . '. Please try again.'
                ], 500);
            }

            $adjustResult = $adjustResponse->json();
            $finalQuantity = $adjustResult['inventory_level']['available'] ?? ($currentAvailable + $adjustValue);
            
            Log::info('Successfully adjusted Shopify inventory', [
                'sku' => $sku,
                'adjustment' => $adjustValue,
                'final_quantity' => $finalQuantity
            ]);

            // Step 4: Only save to database AFTER successful Shopify update
            try {
                DB::beginTransaction();
                
                Inventory::create([
                    'sku' => $sku,
                    'verified_stock' => $qty,
                    'to_adjust' => $adjustValue,
                    'reason' => $request->reason,
                    'adjustment' => $request->adjustment,
                    'is_approved' => true,
                    'approved_by' => Auth::user()->name ?? 'N/A',
                    'approved_at' => Carbon::now('America/New_York'),
                    'type' => 'adjustment',
                    'warehouse_id' => $request->warehouse_id,
                ]);
                
                DB::commit();
                
                Log::info('Inventory adjustment saved to database', [
                    'sku' => $sku,
                    'adjustment' => $adjustValue
                ]);
                
            } catch (\Exception $dbException) {
                DB::rollBack();
                
                Log::error("Failed to save to database after successful Shopify update", [
                    'sku' => $sku,
                    'error' => $dbException->getMessage()
                ]);
                
                return response()->json([
                    'error' => 'Database Error',
                    'details' => 'Shopify inventory was updated successfully but database record could not be created. Please contact support.',
                    'shopify_updated' => true,
                    'new_stock_level' => $finalQuantity
                ], 500);
            }

            return response()->json([
                'message' => 'Stock adjustment completed successfully for ' . $sku . '. New quantity: ' . $finalQuantity,
                'new_stock_level' => $finalQuantity,
                'sku' => $sku
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Connection timeout for SKU $sku: " . $e->getMessage());
            return response()->json([
                'error' => 'Connection Timeout',
                'details' => 'Request took too long. Please try again or check your internet connection.'
            ], 504);
        } catch (\Exception $e) {
            Log::error("Stock adjustment failed for SKU $sku: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json([
                'error' => 'An unexpected error occurred',
                'details' => 'Please try again or contact support if the problem persists.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


    public function list()
    {
        $data = Inventory::with('warehouse')
            ->where('type', 'adjustment') // Only stock adjustment records
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'sku' => $item->sku,
                    'verified_stock' => $item->verified_stock,
                    'reason' => $item->reason,
                    'adjustment' => $item->adjustment,
                    'warehouse_name' => $item->warehouse->name ?? '',
                    'approved_by' => $item->approved_by,
                    'approved_at' =>  $item->approved_at
                        ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('m-d-Y')
                        : '',
                ];
            });

        return response()->json(['data' => $data]);
    }

    /**
     * Process bulk stock adjustment from CSV
     */
    public function processBulkCSV(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('csv_file');
            $csvData = [];
            $errors = [];
            $rowNumber = 0;

            // Read file content and handle encoding
            $content = file_get_contents($file->getRealPath());
            
            // Remove BOM if present
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            
            // Convert to UTF-8 if needed
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
            }
            
            // Create temporary file with clean content
            $tempFile = tempnam(sys_get_temp_dir(), 'csv');
            file_put_contents($tempFile, $content);

            if (($handle = fopen($tempFile, 'r')) !== false) {
                // Read header
                $header = fgetcsv($handle);
                
                if (!$header) {
                    fclose($handle);
                    unlink($tempFile);
                    return response()->json([
                        'success' => false,
                        'message' => 'CSV file is empty or invalid'
                    ], 422);
                }

                // Normalize header
                $header = array_map(function($col) {
                    return strtolower(trim($col));
                }, $header);

                // Find column indexes
                $skuIndex = $this->findColumnIndex($header, ['sku', 'item', 'product_sku']);
                $qtyIndex = $this->findColumnIndex($header, ['quantity', 'qty', 'stock_adjustment']);
                $warehouseIndex = $this->findColumnIndex($header, ['warehouse', 'warehouse_name']);
                $adjustmentIndex = $this->findColumnIndex($header, ['adjustment', 'adjustment_type', 'type']);
                $reasonIndex = $this->findColumnIndex($header, ['reason', 'notes']);

                if ($skuIndex === false || $qtyIndex === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'CSV must contain SKU and QUANTITY columns'
                    ], 422);
                }

                // Get all warehouses for lookup
                $warehouses = Warehouse::all();

                // Read data rows
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;
                    
                    if (empty(array_filter($row))) {
                        continue; // Skip empty rows
                    }

                    // Clean and decode values
                    $sku = isset($row[$skuIndex]) ? trim($row[$skuIndex]) : null;
                    $qty = isset($row[$qtyIndex]) ? trim($row[$qtyIndex]) : null;
                    $warehouseName = $warehouseIndex !== false && isset($row[$warehouseIndex]) ? trim($row[$warehouseIndex]) : null;
                    $adjustmentType = $adjustmentIndex !== false && isset($row[$adjustmentIndex]) ? trim($row[$adjustmentIndex]) : 'Add';
                    $reason = $reasonIndex !== false && isset($row[$reasonIndex]) ? trim($row[$reasonIndex]) : null;

                    if (empty($sku) || $qty === null || $qty === '') {
                        $errors[] = "Row {$rowNumber}: SKU or Quantity is empty";
                        continue;
                    }

                    // Validate quantity is numeric
                    if (!is_numeric($qty)) {
                        $errors[] = "Row {$rowNumber}: Quantity must be a number (SKU: {$sku})";
                        continue;
                    }

                    // Check if SKU exists in product master
                    $product = ProductMaster::where('sku', $sku)->first();
                    
                    if (!$product) {
                        $errors[] = "Row {$rowNumber}: SKU not found in Product Master ({$sku})";
                        continue;
                    }

                    // Check if SKU exists in Shopify
                    $shopifySku = ShopifySku::where('sku', $sku)->first();
                    if (!$shopifySku) {
                        $errors[] = "Row {$rowNumber}: SKU not found in Shopify ({$sku})";
                        continue;
                    }

                    // Find warehouse (case-insensitive, handles spelling variations)
                    $warehouse = null;
                    $warehouseId = null;
                    if ($warehouseName) {
                        // Try exact match first
                        $warehouse = $warehouses->firstWhere('name', $warehouseName);
                        
                        // If not found, try case-insensitive
                        if (!$warehouse) {
                            $warehouse = $warehouses->first(function($w) use ($warehouseName) {
                                return strcasecmp($w->name, $warehouseName) === 0;
                            });
                        }
                        
                        // If still not found, try partial match (handles "Godawn" vs "Godown")
                        if (!$warehouse) {
                            $warehouse = $warehouses->first(function($w) use ($warehouseName) {
                                return stripos($w->name, substr($warehouseName, 0, 8)) !== false ||
                                       stripos($warehouseName, substr($w->name, 0, 8)) !== false;
                            });
                        }
                        
                        $warehouseId = $warehouse ? $warehouse->id : null;
                    }

                    $csvData[] = [
                        'sku' => $sku,
                        'parent' => $product->parent ?? '',
                        'title' => $product->title ?? '',
                        'quantity' => (int)$qty,
                        'warehouse_name' => $warehouseName ?? '',
                        'warehouse_id' => $warehouseId,
                        'adjustment_type' => $adjustmentType,
                        'reason' => $reason,
                        'row' => $rowNumber
                    ];
                }

                fclose($handle);
                unlink($tempFile); // Delete temporary file
            }

            if (empty($csvData) && empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid data found in CSV file'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'CSV processed successfully',
                'data' => $csvData,
                'errors' => $errors,
                'total_rows' => $rowNumber,
                'valid_rows' => count($csvData),
                'error_rows' => count($errors)
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk CSV processing error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing CSV: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find column index by possible names
     */
    private function findColumnIndex($header, $possibleNames)
    {
        foreach ($possibleNames as $name) {
            $index = array_search(strtolower($name), $header);
            if ($index !== false) {
                return $index;
            }
        }
        return false;
    }
}
