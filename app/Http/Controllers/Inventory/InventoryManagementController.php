<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\ShopifySku;
use App\Services\ShopifyInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryManagementController extends Controller
{
    /**
     * Display inventory management page
     */
    public function index(Request $request)
    {
        // Use shopify_skus table as primary source
        $query = ShopifySku::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('sku', 'LIKE', "%{$search}%");
        }

        $inventories = $query->orderBy('sku')->paginate(50)->withQueryString();

        // Get latest logs and inventory data for each SKU
        foreach ($inventories as $item) {
            $item->latest_log = InventoryLog::where('sku', $item->sku)
                ->with('creator')
                ->latest()
                ->first();
            
            // Get inventory data if exists
            $item->inventory_data = Inventory::where('sku', $item->sku)->first();
        }

        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('inventory.manage.index', compact('inventories', 'mode', 'demo'));
    }

    /**
     * Update inventory quantity
     */
    public function updateQuantity(Request $request)
    {
        $request->validate([
            'inventory_id' => 'required|exists:inventories,id',
            'new_qty' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $inventory = Inventory::findOrFail($request->inventory_id);
            $oldQty = $inventory->available_qty ?? 0;
            $newQty = $request->new_qty;

            // Update inventory
            $inventory->available_qty = $newQty;
            $inventory->save();

            // Create log
            $log = InventoryLog::create([
                'sku' => $inventory->sku,
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
                'qty_change' => $newQty - $oldQty,
                'change_source' => 'manual_adjustment',
                'notes' => $request->notes ?? 'Manual quantity update',
                'created_by' => Auth::id(),
            ]);

            // Push to Shopify if inventory_item_id exists
            if ($inventory->shopify_inventory_item_id) {
                $shopifyService = new ShopifyInventoryService();
                $result = $shopifyService->pushInventoryToShopify(
                    $inventory->shopify_inventory_item_id,
                    $newQty
                );

                if ($result['success']) {
                    $log->markPushedToShopify();
                } else {
                    $log->markShopifyError($result['message']);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inventory updated successfully',
                'data' => [
                    'old_qty' => $oldQty,
                    'new_qty' => $newQty,
                    'shopify_pushed' => isset($result) ? $result['success'] : false,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating inventory quantity', [
                'error' => $e->getMessage(),
                'inventory_id' => $request->inventory_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update inventory: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get inventory logs
     */
    public function getLogs($inventoryId)
    {
        $inventory = Inventory::findOrFail($inventoryId);
        
        $logs = InventoryLog::where('sku', $inventory->sku)
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    /**
     * Export inventory to CSV
     */
    public function export(Request $request)
    {
        $query = Inventory::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('sku', 'LIKE', "%{$search}%");
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        $inventories = $query->orderBy('sku')->get();

        // Generate CSV
        $filename = 'inventory_export_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($inventories) {
            $file = fopen('php://output', 'w');
            
            // CSV Header
            fputcsv($file, [
                'SKU',
                'Available Qty',
                'On Hand',
                'Shopify Variant ID',
                'Shopify Inventory Item ID',
                'Last Updated',
                'Updated By',
            ]);

            // Data rows
            foreach ($inventories as $inventory) {
                $lastLog = InventoryLog::where('sku', $inventory->sku)
                    ->with('creator')
                    ->latest()
                    ->first();

                fputcsv($file, [
                    $inventory->sku,
                    $inventory->available_qty ?? 0,
                    $inventory->on_hand ?? 0,
                    $inventory->shopify_variant_id ?? '',
                    $inventory->shopify_inventory_item_id ?? '',
                    $lastLog ? $lastLog->created_at->format('Y-m-d H:i:s') : '',
                    $lastLog && $lastLog->creator ? $lastLog->creator->name : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export with Shopify SKU data
     */
    public function exportWithShopify(Request $request)
    {
        // Get all SKUs from Shopify
        $shopifySkus = ShopifySku::all();
        
        // Get inventories
        $query = Inventory::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('sku', 'LIKE', "%{$search}%");
        }

        $inventories = $query->orderBy('sku')->get();

        // Generate CSV
        $filename = 'inventory_shopify_export_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($inventories, $shopifySkus) {
            $file = fopen('php://output', 'w');
            
            // CSV Header
            fputcsv($file, [
                'SKU',
                'Our Available Qty',
                'Our On Hand',
                'Shopify Product Title',
                'Shopify Variant Title',
                'Shopify Inventory',
                'Shopify Price',
                'Shopify Variant ID',
                'Shopify Inventory Item ID',
                'Last Updated',
                'Updated By',
                'Qty Difference',
            ]);

            // Data rows
            foreach ($inventories as $inventory) {
                // Find matching Shopify SKU
                $shopifySku = $shopifySkus->firstWhere('sku', $inventory->sku);
                
                $lastLog = InventoryLog::where('sku', $inventory->sku)
                    ->with('creator')
                    ->latest()
                    ->first();

                $ourQty = $inventory->available_qty ?? 0;
                $shopifyQty = $shopifySku ? ($shopifySku->inventory_shopify ?? 0) : 0;
                $difference = $ourQty - $shopifyQty;

                fputcsv($file, [
                    $inventory->sku,
                    $ourQty,
                    $inventory->on_hand ?? 0,
                    $shopifySku ? $shopifySku->product_title : '',
                    $shopifySku ? $shopifySku->variant_title : '',
                    $shopifyQty,
                    $shopifySku ? $shopifySku->price : '',
                    $inventory->shopify_variant_id ?? '',
                    $inventory->shopify_inventory_item_id ?? '',
                    $lastLog ? $lastLog->created_at->format('Y-m-d H:i:s') : '',
                    $lastLog && $lastLog->creator ? $lastLog->creator->name : '',
                    $difference,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Sync single SKU to Shopify
     */
    public function syncToShopify(Request $request)
    {
        $request->validate([
            'inventory_id' => 'required|exists:inventories,id',
        ]);

        $inventory = Inventory::findOrFail($request->inventory_id);

        if (!$inventory->shopify_inventory_item_id) {
            return response()->json([
                'success' => false,
                'message' => 'Shopify inventory item ID not found for this SKU',
            ], 400);
        }

        try {
            $shopifyService = new ShopifyInventoryService();
            $result = $shopifyService->pushInventoryToShopify(
                $inventory->shopify_inventory_item_id,
                $inventory->available_qty ?? 0
            );

            if ($result['success']) {
                // Log the sync
                InventoryLog::create([
                    'sku' => $inventory->sku,
                    'old_qty' => $inventory->available_qty,
                    'new_qty' => $inventory->available_qty,
                    'qty_change' => 0,
                    'change_source' => 'manual_shopify_sync',
                    'notes' => 'Manual Shopify sync triggered',
                    'pushed_to_shopify' => true,
                    'shopify_pushed_at' => now(),
                    'created_by' => Auth::id(),
                ]);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error syncing to Shopify', [
                'error' => $e->getMessage(),
                'inventory_id' => $request->inventory_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync: ' . $e->getMessage(),
            ], 500);
        }
    }
}
