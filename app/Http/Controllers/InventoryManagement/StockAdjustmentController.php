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
}
