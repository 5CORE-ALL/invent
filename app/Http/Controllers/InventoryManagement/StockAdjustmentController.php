<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Inventory;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Http\Controllers\ShopifyApiInventoryController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

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

        try {
            // 1. Get inventory_item_id
            $inventoryItemId = null;    
            $pageInfo = null;

            do {
                $queryParams = ['limit' => 250];
                if ($pageInfo) $queryParams['page_info'] = $pageInfo;

                $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

                $products = $response->json('products');

                foreach ($products as $product) {
                    foreach ($product['variants'] as $variant) {
                        if (trim($variant['sku']) === $sku) {
                            $inventoryItemId = $variant['inventory_item_id'];
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

            if (!$inventoryItemId) {
                Log::error("Inventory Item ID not found for SKU: $sku");
                return response()->json(['error' => 'SKU not found in Shopify'], 404);
            }

            // 2. Get location ID
            $invLevelResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
                    'inventory_item_ids' => $inventoryItemId,
                ]);

            $levels = $invLevelResponse->json('inventory_levels');
            $locationId = $levels[0]['location_id'] ?? null;

            if (!$locationId) {
                Log::error("Location ID not found for inventory item: $inventoryItemId");
                return response()->json(['error' => 'Location ID not found'], 404);
            }

            // 3. Adjust Shopify quantity
            $adjustValue = $adjustment === 'Add' ? $qty : -$qty;

            $adjustResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                    'inventory_item_id' => $inventoryItemId,
                    'location_id' => $locationId,
                    'available_adjustment' => $adjustValue,
                ]);

            if (!$adjustResponse->successful()) {
                Log::error("Failed to update Shopify for SKU $sku", $adjustResponse->json());
                return response()->json(['error' => 'Failed to update Shopify inventory'], 500);
            }

            // 4. Store in DB
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

            return response()->json(['message' => 'Stock adjusted successfully in Shopify']);

        } catch (\Exception $e) {
            Log::error("Stock adjustment failed for SKU $sku: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Something went wrong.'], 500);
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
