<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\AutoStockBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoStockBalanceController extends Controller
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

        $skus = ProductMaster::select('product_master.id', 'product_master.parent', 'product_master.sku', 'shopify_skus.inv as available_quantity', 'shopify_skus.quantity as l30')
            ->leftJoin('shopify_skus', 'product_master.sku', '=', 'shopify_skus.sku')
            ->get()
            ->map(function ($item) {
            $inv = $item->available_quantity ?? 0;
            $l30 = $item->l30 ?? 0;
            $item->dil = $inv != 0 ? round(($l30 / $inv) * 100) : 0;
            return $item;
        });

        return view('inventory-management.auto-stock-balance-view', compact('warehouses', 'skus'));
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
            'from_parent_name' => 'required|string',
            'from_sku' => 'required|string',
            'from_dil_percent' => 'nullable|numeric',
            'from_available_qty' => 'nullable|integer',
            'from_adjust_qty' => 'required|integer|min:1',

            'to_parent_name' => 'required|string',
            'to_sku' => 'required|string',
            'to_dil_percent' => 'nullable|numeric',
            'to_available_qty' => 'nullable|integer',
            'to_adjust_qty' => 'required|integer|min:1',

            'added_qty' => 'nullable|integer|min:1',
        ]);

        try {
            
            $fromSku = trim($request->from_sku);
            $toSku = trim($request->to_sku);
            $fromQty = (int) $request->from_adjust_qty;
            $toQty = (int) $request->to_adjust_qty;

            // Helper: get inventory_item_id and location_id
            $getInventoryInfo = function ($sku) {

                $normalizeSku = fn($sku) => strtoupper(preg_replace('/\s+/u', ' ', trim($sku)));

                $inventoryItemId = null;
                $pageInfo = null;

                do {
                    $params = ['limit' => 250];
                    if ($pageInfo) $params['page_info'] = $pageInfo;

                    $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                        ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $params);

                    $products = $response->json('products') ?? [];

                    foreach ($products as $product) {
                        foreach ($product['variants'] as $variant) {
                            $variantSku = $normalizeSku($variant['sku'] ?? '');
                            if ($variantSku === $sku) {
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
                    throw new \Exception("Inventory item ID not found for SKU: $sku");
                }

                $invLevelResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
                        'inventory_item_ids' => $inventoryItemId,
                    ]);

                $levels = $invLevelResponse->json('inventory_levels');
                $locationId = $levels[0]['location_id'] ?? null;

                if (!$locationId) {
                    throw new \Exception("Location ID not found for SKU: $sku");
                }

                return [
                    'inventory_item_id' => $inventoryItemId,
                    'location_id' => $locationId,
                ];
            };

            // Add to 'from_sku' in Shopify
            $fromInfo = $getInventoryInfo($fromSku);
            $increaseFrom = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                    'inventory_item_id' => $fromInfo['inventory_item_id'],
                    'location_id' => $fromInfo['location_id'],
                    'available_adjustment' => $fromQty, // ADD positive qty
                ]);

            if (!$increaseFrom->successful()) {
                Log::error("Failed to increase inventory for SKU $fromSku", $increaseFrom->json());
                return response()->json(['error' => 'Failed to increase inventory in Shopify for From SKU.'], 500);
            }

            // Add to 'to_sku' in Shopify
            $toInfo = $getInventoryInfo($toSku);
            $increaseTo = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                    'inventory_item_id' => $toInfo['inventory_item_id'],
                    'location_id' => $toInfo['location_id'],
                    'available_adjustment' => $toQty, // ADD positive qty
                ]);

            if (!$increaseTo->successful()) {
                Log::error("Failed to increase inventory for SKU $toSku", $increaseTo->json());
                return response()->json(['error' => 'Failed to increase inventory in Shopify for To SKU.'], 500);
            }

            $userId = Auth::check() ? Auth::id() : null;
           
            // Store in auto_stock_balance table
            AutoStockBalance::create([
                'from_parent_name'   => $request->from_parent_name,
                'from_sku'           => $fromSku,
                'from_available_qty' => $request->from_available_qty,
                'from_dil_percent'   => $request->from_dil_percent,
                'from_adjust_qty'    => $fromQty,
                'from_adj_dil'       => $request->from_adj_dil,

                'to_parent_name'     => $request->to_parent_name,
                'to_sku'             => $toSku,
                'to_available_qty'   => $request->to_available_qty,
                'to_dil_percent'     => $request->to_dil_percent,
                'to_adjust_qty'      => $toQty,
                'to_adj_dil'         => $request->to_adj_dil,

                'added_qty'          => $request->added_qty,
                'user_id'            => $userId,
            ]);

            return response()->json(['message' => 'Adjusted quantities added to Shopify and saved successfully.']);

        } catch (\Exception $e) {
            Log::error("Auto stock adjustment failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Something went wrong during stock adjustment.'], 500);
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
        $data = AutoStockBalance::latest()->get()->map(function ($item) {
            return [
                'from_parent_name'    => $item->from_parent_name,
                'from_sku'            => $item->from_sku,
                'from_dil_percent'    => $item->from_dil_percent,
                'from_available_qty'  => $item->from_available_qty,
                'from_adjust_qty'     => $item->from_adjust_qty,

                'to_parent_name'      => $item->to_parent_name,
                'to_sku'              => $item->to_sku,
                'to_dil_percent'      => $item->to_dil_percent,
                'to_available_qty'    => $item->to_available_qty,
                'to_adjust_qty'       => $item->to_adjust_qty,

                'user_name'           => $item->user ? $item->user->name : '-',
                'added_at'            => $item->created_at
                    ? Carbon::parse($item->created_at)->timezone('America/New_York')->format('m-d-Y')
                    : '',
            ];
        });

        return response()->json(['data' => $data]);
    }
}

