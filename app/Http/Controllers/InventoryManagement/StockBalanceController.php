<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Inventory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\StockBalance;
use App\Http\Controllers\ApiController;


class StockBalanceController extends Controller
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
        // $skus = ProductMaster::select('id','parent','sku')->get();

        $skus = ProductMaster::select('product_master.id', 'product_master.parent', 'product_master.sku', 'shopify_skus.inv as available_quantity', 'shopify_skus.quantity as l30')
            ->leftJoin('shopify_skus', 'product_master.sku', '=', 'shopify_skus.sku')
            ->get()
            ->map(function ($item) {
            $inv = $item->available_quantity ?? 0;
            $l30 = $item->l30 ?? 0;
            $item->dil = $inv != 0 ? round(($l30 / $inv) * 100) : 0;
            return $item;
        });

        return view('inventory-management.stock-balance-view', compact('warehouses', 'skus'));
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

            'transferred_by' => 'nullable|string',
            'transferred_at' => 'nullable|date',
        ]);

        try {
            $fromSku = trim($request->from_sku);
            $toSku = trim($request->to_sku);
            $fromQty = (int) $request->from_adjust_qty;
            $toQty = (int) $request->to_adjust_qty;

            // Helper function to get inventory_item_id and location_id
            $getInventoryInfo = function ($sku) {
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

            // 1. Decrease inventory from 'from_sku'
            $fromInfo = $getInventoryInfo($fromSku);

            $decrease = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                    'inventory_item_id' => $fromInfo['inventory_item_id'],
                    'location_id' => $fromInfo['location_id'],
                    'available_adjustment' => -$fromQty,
                ]);

            if (!$decrease->successful()) {
                Log::error("Failed to deduct inventory for SKU $fromSku", $decrease->json());
                return response()->json(['error' => 'Failed to deduct inventory from Shopify.'], 500);
            }

            // 2. Increase inventory to 'to_sku'
            $toInfo = $getInventoryInfo($toSku);

            $increase = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                    'inventory_item_id' => $toInfo['inventory_item_id'],
                    'location_id' => $toInfo['location_id'],
                    'available_adjustment' => $toQty,
                ]);

            if (!$increase->successful()) {
                Log::error("Failed to increase inventory for SKU $toSku", $increase->json());
                return response()->json(['error' => 'Failed to increase inventory in Shopify.'], 500);
            }

            StockBalance::create([
                'from_parent_name'     => $request->from_parent_name,
                'from_sku'             => $fromSku,
                'from_dil_percent'     => $request->from_dil_percent,
                // 'from_warehouse_id'    => $fromWarehouseId, // assuming you set this earlier
                'from_available_qty'   => $request->from_available_qty,
                'from_adjust_qty'      => $fromQty,

                'to_parent_name'       => $request->to_parent_name,
                'to_sku'               => $toSku,
                'to_dil_percent'       => $request->to_dil_percent,
                // 'to_warehouse_id'      => $toWarehouseId, // assuming you set this earlier
                'to_available_qty'     => $request->to_available_qty,
                'to_adjust_qty'        => $toQty,

                'transferred_by'       => Auth::user()->name ?? 'N/A',
                'transferred_at'       => Carbon::now('America/New_York'),
            ]);

            return response()->json(['message' => 'Inventory transferred successfully.']);

        } catch (\Exception $e) {
            Log::error("Stock transfer failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Something went wrong during transfer.'], 500);
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
        $data = StockBalance::latest()->get()->map(function ($item) {
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

                'transferred_by'      => $item->transferred_by,
                'transferred_at'      => $item->transferred_at
                    ? Carbon::parse($item->transferred_at)->timezone('America/New_York')->format('m-d-Y')
                    : '',
            ];
        });

        return response()->json(['data' => $data]);
    }
}
