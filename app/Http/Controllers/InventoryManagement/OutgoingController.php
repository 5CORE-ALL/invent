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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OutgoingController extends Controller
{

    protected $shopifyDomain;
    protected $shopifyApiKey;
    protected $shopifyPassword;

    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
        $this->shopifyDomain = config('services.shopify.store_url');
        $this->shopifyApiKey = config('services.shopify.api_key');
        $this->shopifyPassword = config('services.shopify.password');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::select('id', 'name')->get();
        // $skus = ProductMaster::select('id','parent','sku')->get();
        $skus = ProductMaster::select('product_master.id', 'product_master.parent', 'product_master.sku', 'shopify_skus.inv as available_quantity')
        ->leftJoin('shopify_skus', 'product_master.sku', '=', 'shopify_skus.sku')
        ->get();

        return view('inventory-management.outgoing-view', compact('warehouses', 'skus'));
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
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);

        $sku = trim($request->sku);
        $outgoingQty = (int) $request->qty;
        $normalizedSku = strtoupper(preg_replace('/\s+/u', ' ', $sku));

        $inventoryItemId = null;
        $pageInfo = null;

        // Fast path: try local shopify_skus table for variant_id
        try {
            $shopifyRow = ShopifySku::whereRaw('LOWER(sku) = ?', [strtolower($normalizedSku)])->first();

            if ($shopifyRow && !empty($shopifyRow->variant_id)) {
                $variantResp = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                    ->timeout(30)
                    ->retry(3, 2000)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$shopifyRow->variant_id}.json");

                if ($variantResp->successful()) {
                    $inventoryItemId = $variantResp->json('variant.inventory_item_id') ?? null;
                    Log::info('Outgoing: Found inventory_item_id from variant', [
                        'sku' => $normalizedSku,
                        'variant_id' => $shopifyRow->variant_id,
                        'inventory_item_id' => $inventoryItemId
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Outgoing: Error fetching variant, will search products', ['error' => $e->getMessage()]);
        }

        // Fallback: search all products if inventory_item_id not found
        if (!$inventoryItemId) {
            Log::info('Outgoing: Starting product search for inventory_item_id', ['sku' => $normalizedSku]);
            
            try {
                do {
                    $queryParams = ['limit' => 250, 'fields' => 'variants'];
                    if ($pageInfo) $queryParams['page_info'] = $pageInfo;

                    $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                        ->timeout(30)
                        ->retry(3, 2000)
                        ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

                    if (!$response->successful()) {
                        Log::error('Outgoing: Failed to fetch products from Shopify', [
                            'status' => $response->status(),
                            'response' => $response->body()
                        ]);
                        return response()->json(['error' => 'Failed to fetch products from Shopify'], 500);
                    }

                    $products = $response->json('products');

                    foreach ($products as $product) {
                        foreach ($product['variants'] as $variant) {
                            $variantSku = strtoupper(preg_replace('/\s+/u', ' ', trim($variant['sku'] ?? '')));
                            if ($variantSku === $normalizedSku) {
                                $inventoryItemId = $variant['inventory_item_id'];
                                Log::info('Outgoing: Found inventory_item_id via product search', [
                                    'sku' => $normalizedSku,
                                    'inventory_item_id' => $inventoryItemId
                                ]);
                                break 2;
                            }
                        }
                    }

                    $linkHeader = $response->header('Link');
                    $pageInfo = null;
                    if ($linkHeader && preg_match('/<([^>]+page_info=([^&>]+)[^>]*)>; rel="next"/', $linkHeader, $matches)) {
                        $pageInfo = $matches[2];
                    }
                } while (!$inventoryItemId && $pageInfo);
            } catch (\Exception $e) {
                Log::error('Outgoing: Exception during product search', [
                    'sku' => $normalizedSku,
                    'error' => $e->getMessage()
                ]);
                return response()->json(['error' => 'Error searching for SKU: ' . $e->getMessage()], 500);
            }
        }

        if (!$inventoryItemId) {
            Log::error('Outgoing: Inventory Item ID not found for SKU', ['sku' => $normalizedSku]);
            return response()->json(['error' => 'SKU not found in Shopify. Please sync inventory first.'], 404);
        }

        // Get location ID
        try {
            $invLevelResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->timeout(30)
                ->retry(3, 2000)
                ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
                    'inventory_item_ids' => $inventoryItemId,
                ]);

            if (!$invLevelResponse->successful()) {
                Log::error('Outgoing: Failed to fetch inventory levels', [
                    'inventory_item_id' => $inventoryItemId,
                    'status' => $invLevelResponse->status(),
                    'response' => $invLevelResponse->body()
                ]);
                return response()->json(['error' => 'Failed to fetch inventory levels from Shopify'], 500);
            }

            $levels = $invLevelResponse->json('inventory_levels');
            $locationId = $levels[0]['location_id'] ?? null;

            if (!$locationId) {
                Log::error('Outgoing: Location ID not found', [
                    'inventory_item_id' => $inventoryItemId,
                    'levels_response' => $levels
                ]);
                return response()->json(['error' => 'Shopify location not found for this SKU'], 404);
            }

            Log::info('Outgoing: Attempting to adjust Shopify inventory', [
                'sku' => $normalizedSku,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'adjustment' => -$outgoingQty
            ]);

            // Adjust inventory (decrease for outgoing)
            $adjustResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->timeout(30)
                ->retry(3, 2000)
                ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                    'inventory_item_id' => $inventoryItemId,
                    'location_id' => $locationId,
                    'available_adjustment' => -$outgoingQty,
                ]);

            if (!$adjustResponse->successful()) {
                Log::error('Outgoing: Failed to update Shopify inventory', [
                    'sku' => $normalizedSku,
                    'status' => $adjustResponse->status(),
                    'response' => $adjustResponse->body()
                ]);
                return response()->json(['error' => 'Failed to update Shopify inventory: ' . $adjustResponse->body()], 500);
            }

            Log::info('Outgoing: Successfully updated Shopify inventory', [
                'sku' => $normalizedSku,
                'adjustment' => -$outgoingQty,
                'response' => $adjustResponse->json()
            ]);

        } catch (\Exception $e) {
            Log::error('Outgoing: Exception during Shopify update', [
                'sku' => $normalizedSku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error updating Shopify: ' . $e->getMessage()], 500);
        }

        // Only save to DB if Shopify update succeeded
        try {
            Inventory::create([
                'sku' => $sku,
                'verified_stock' => $outgoingQty,
                'to_adjust' => -$outgoingQty,
                'reason' => $request->reason,
                'is_approved' => true,
                'approved_by' => Auth::user()->name ?? 'N/A',
                'approved_at' => Carbon::now('America/New_York'),
                'type' => 'outgoing',
                'warehouse_id' => $request->warehouse_id,
            ]);

            return response()->json(['success' => true, 'message' => 'Outgoing inventory deducted from Shopify successfully']);

        } catch (\Exception $e) {
            Log::error('Outgoing: Failed to save to database after Shopify update', [
                'sku' => $normalizedSku,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Shopify updated but failed to save to database: ' . $e->getMessage()], 500);
        }
    }

    // public function store(Request $request)
    // {
    //     try {
    //         // Validate input
    //         $validated = $request->validate([
    //             'sku' => 'required|string',
    //             'qty' => 'required|integer',
    //             'warehouse_id' => 'required|integer',
    //             'reason' => 'required|string',
    //             'date' => 'nullable|date',
    //         ]);

    //         $sku = trim($validated['sku']);
    //         $qty = (int) $validated['qty'];

    //         // Shopify credentials
    //         $shopifyDomain = config('services.shopify.store_url');
    //         $accessToken = config('services.shopify.access_token');

    //         /** -----------------------------------------------------------------
    //          * Find the Shopify Inventory Item ID (with pagination)
    //          * ----------------------------------------------------------------- */
    //         $inventoryItemId = null;
    //         $pageInfo = null;

    //         do {
    //             $url = "https://{$shopifyDomain}/admin/api/2025-01/products.json?limit=250";
    //             if ($pageInfo) {
    //                 $url .= "&page_info={$pageInfo}";
    //             }

    //             $response = Http::withHeaders([
    //                 'X-Shopify-Access-Token' => $accessToken,
    //             ])->get($url);

    //             if (!$response->successful()) {
    //                 return response()->json(['error' => 'Failed to fetch Shopify products.'], 500);
    //             }

    //             $products = $response->json('products') ?? [];

    //             foreach ($products as $product) {
    //                 foreach ($product['variants'] as $variant) {
    //                     if (trim(strtolower($variant['sku'])) === strtolower($sku)) {
    //                         $inventoryItemId = $variant['inventory_item_id'];
    //                         break 2;
    //                     }
    //                 }
    //             }

    //             // Handle pagination
    //             $linkHeader = $response->header('Link');
    //             if ($linkHeader && preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
    //                 $parsedUrl = parse_url($matches[1]);
    //                 parse_str($parsedUrl['query'] ?? '', $query);
    //                 $pageInfo = $query['page_info'] ?? null;
    //             } else {
    //                 $pageInfo = null;
    //             }

    //         } while (!$inventoryItemId && $pageInfo);

    //         if (!$inventoryItemId) {
    //             Log::warning("SKU not found in Shopify: {$sku}");
    //             return response()->json(['error' => "SKU '{$sku}' not found in Shopify"], 404);
    //         }

    //         /** -----------------------------------------------------------------
    //          * Find the Shopify Location ID for “Ohio”
    //          * ----------------------------------------------------------------- */
    //         $locationResponse = Http::withHeaders([
    //             'X-Shopify-Access-Token' => $accessToken,
    //         ])->get("https://{$shopifyDomain}/admin/api/2025-01/locations.json");

    //         if (!$locationResponse->successful()) {
    //             return response()->json(['error' => 'Failed to fetch Shopify locations.'], 500);
    //         }

    //         $locations = $locationResponse->json('locations');
    //         $ohioLocation = collect($locations)->first(function ($loc) {
    //             return stripos($loc['name'], 'ohio') !== false;
    //         });

    //         if (!$ohioLocation) {
    //             return response()->json(['error' => 'No Shopify location found with name containing "Ohio".'], 404);
    //         }

    //         $locationId = $ohioLocation['id'];

    //         /** -----------------------------------------------------------------
    //          * Ensure inventory item is connected to the Ohio location
    //          * ----------------------------------------------------------------- */
    //         $connectResponse = Http::withHeaders([
    //             'X-Shopify-Access-Token' => $accessToken,
    //             'Content-Type' => 'application/json',
    //         ])->post("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels/connect.json", [
    //             'location_id' => $locationId,
    //             'inventory_item_id' => $inventoryItemId,
    //         ]);

    //         // 422 just means already connected, ignore it
    //         if (!$connectResponse->successful() && $connectResponse->status() != 422) {
    //             Log::error("Failed to connect inventory item {$inventoryItemId} to Ohio location: " . $connectResponse->body());
    //         }

    //         /** -----------------------------------------------------------------
    //          * Adjust inventory quantity for the Ohio location
    //          * ----------------------------------------------------------------- */
    //         $adjustResponse = Http::withHeaders([
    //             'X-Shopify-Access-Token' => $accessToken,
    //             'Content-Type' => 'application/json',
    //         ])->post("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
    //             'location_id' => $locationId,
    //             'inventory_item_id' => $inventoryItemId,
    //             'available_adjustment' => -$qty,
    //         ]);

    //         Log::info("Shopify Adjust Response (SKU: {$sku}):", $adjustResponse->json());

    //         if (!$adjustResponse->successful()) {
    //             Log::error('Shopify adjust failed: ' . $adjustResponse->body());
    //             return response()->json(['error' => 'Failed to update Shopify inventory'], 500);
    //         }

    //         /** -----------------------------------------------------------------
    //          * Store locally
    //          * ----------------------------------------------------------------- */
    //         DB::table('inventories')->insert([
    //             'sku' => $sku,
    //             'verified_stock' => $qty,
    //             'to_adjust' => $qty,
    //             'reason' => $request->reason,
    //             'is_approved' => true,
    //             'approved_by' => Auth::user()->name ?? 'N/A',
    //             'approved_at' => Carbon::now('America/New_York'),
    //             'type' => 'incoming',
    //             'warehouse_id' => $request->warehouse_id,
    //         ]);

    //         return response()->json(['success' => true, 'message' => 'Shopify Ohio inventory updated successfully!']);

    //     } catch (\Exception $e) {
    //         Log::error('Incoming Store Error: ' . $e->getMessage());
    //         return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
    //     }
    // }



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
            ->where('type', 'outgoing') // Only outgoing records
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'sku' => $item->sku,
                    'verified_stock' => $item->verified_stock,
                    'reason' => $item->reason,
                    'warehouse_name' => $item->warehouse->name ?? '',
                    'approved_by' => $item->approved_by,
                    'approved_at' =>  $item->approved_at
                        ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('m-d-Y')
                        : '',
                ];
            });

        return response()->json(['data' => $data]);
    }


    public function getAvailableQtyBySku(Request $request)
    {
        $sku = $request->input('sku');

        // Your logic to get total available from Shopify by SKU
        $available = $this->fetchAvailableFromShopifyBySku($sku); // your own method

        return response()->json(['available_quantity' => $available]);
    }

}
