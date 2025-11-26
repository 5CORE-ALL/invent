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
     * Make Shopify API call with automatic retry on rate limit
     */
    private function shopifyApiCall($method, $url, $data = [], $maxRetries = 3)
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            
            $request = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyPassword,
                'Content-Type' => 'application/json',
            ])->timeout(10);
            
            if ($method === 'GET') {
                $response = $request->get($url, $data);
            } else {
                $response = $request->post($url, $data);
            }
            
            // If rate limited, wait and retry
            if ($response->status() === 429 && $attempt < $maxRetries) {
                $waitTime = $attempt * 2; // 2, 4, 6 seconds
                Log::info("Rate limited, waiting {$waitTime}s before retry", [
                    'attempt' => $attempt,
                    'url' => $url
                ]);
                sleep($waitTime);
                continue;
            }
            
            return $response;
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

            // Step 1: Get inventory_item_id from variant (Single API call)
            sleep(1); // Rate limit protection
            $variantResponse = $this->shopifyApiCall(
                'GET',
                "https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$variantId}.json"
            );

            if (!$variantResponse->successful()) {
                Log::error("Failed to fetch variant", [
                    'status' => $variantResponse->status(),
                    'body' => $variantResponse->body()
                ]);
                
                return response()->json([
                    'error' => 'Failed to fetch product from Shopify',
                    'details' => 'Error: ' . $variantResponse->status()
                ], 500);
            }

            $variant = $variantResponse->json('variant');
            $inventoryItemId = $variant['inventory_item_id'] ?? null;
            
            if (!$inventoryItemId) {
                return response()->json([
                    'error' => 'Invalid product data',
                    'details' => 'Could not find inventory item ID'
                ], 500);
            }

            Log::info('Got inventory_item_id from Shopify', [
                'sku' => $sku,
                'variant_id' => $variantId,
                'inventory_item_id' => $inventoryItemId
            ]);

            // Step 2: Get location_id from inventory levels
            sleep(1); // Rate limit protection
            $levelsResponse = $this->shopifyApiCall(
                'GET',
                "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json",
                ['inventory_item_ids' => $inventoryItemId]
            );

            if (!$levelsResponse->successful()) {
                Log::error("Failed to fetch inventory levels", [
                    'status' => $levelsResponse->status(),
                    'body' => $levelsResponse->body()
                ]);
                
                return response()->json([
                    'error' => 'Failed to fetch inventory levels from Shopify',
                    'details' => 'Error: ' . $levelsResponse->status()
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

            // Step 3: Adjust inventory using REST API (Same as VerificationAdjustmentController)
            sleep(1); // Rate limit protection
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
                Log::error("Failed to adjust inventory in Shopify", [
                    'sku' => $sku,
                    'status' => $adjustResponse->status(),
                    'body' => $adjustResponse->body()
                ]);
                
                return response()->json([
                    'error' => 'Failed to update inventory in Shopify',
                    'details' => $adjustResponse->body()
                ], 500);
            }

            $adjustResult = $adjustResponse->json();
            $finalQuantity = $adjustResult['inventory_level']['available'] ?? ($currentAvailable + $adjustValue);
            
            Log::info('Successfully adjusted Shopify inventory', [
                'sku' => $sku,
                'adjustment' => $adjustValue,
                'final_quantity' => $finalQuantity,
                'response' => $adjustResult
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
                    'error' => 'Shopify updated successfully but failed to save to database',
                    'details' => 'Shopify inventory is at ' . $finalQuantity . ' but database record was not created',
                    'shopify_updated' => true,
                    'new_stock_level' => $finalQuantity
                ], 500);
            }

            return response()->json([
                'message' => 'Stock adjusted successfully in Shopify and saved to database',
                'new_stock_level' => $finalQuantity
            ]);

        } catch (\Exception $e) {
            Log::error("Stock adjustment failed for SKU $sku: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json([
                'error' => 'Something went wrong.',
                'details' => $e->getMessage(),
                'line' => $e->getLine()
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
