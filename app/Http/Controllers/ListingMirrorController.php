<?php

namespace App\Http\Controllers;

use App\Models\AmazonDatasheet;
use App\Models\ProductStockMapping;
use App\Models\FbaTable;
use App\Models\ListingMirrorSync;
use App\Models\ShopifySku;
use App\Models\EbayMetric;
use App\Services\ListingMirror\ShopifySyncService;
use App\Services\ListingMirror\EbaySyncService;
use App\Services\AmazonSpApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListingMirrorController extends Controller
{
    protected $shopifySyncService;
    protected $ebaySyncService;
    protected $amazonService;

    public function __construct()
    {
        $this->shopifySyncService = new ShopifySyncService();
        $this->ebaySyncService = new EbaySyncService();
        $this->amazonService = new AmazonSpApiService();
    }

    /**
     * Display all Amazon listings in one view
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', '');
        
        // Get all Amazon listings with related data
        $query = AmazonDatasheet::query()
            ->select(
                'amazon_datsheets.id',
                'amazon_datsheets.asin',
                'amazon_datsheets.sku',
                'amazon_datsheets.price',
                'amazon_datsheets.listing_status',
                'product_stock_mappings.inventory_amazon',
                DB::raw('MAX(fba_table.quantity_available) as fba_quantity'),
                'product_stock_mappings.image',
                'product_stock_mappings.title'
            )
            ->leftJoin('product_stock_mappings', 'amazon_datsheets.sku', '=', 'product_stock_mappings.sku')
            ->leftJoin('fba_table', function($join) {
                $join->on('amazon_datsheets.sku', '=', 'fba_table.seller_sku')
                     ->orOn('amazon_datsheets.sku', '=', 'fba_table.fulfillment_channel_sku');
            })
            ->whereNotNull('amazon_datsheets.sku')
            ->where('amazon_datsheets.sku', '!=', '')
            ->groupBy(
                'amazon_datsheets.id',
                'amazon_datsheets.asin',
                'amazon_datsheets.sku',
                'amazon_datsheets.price',
                'amazon_datsheets.listing_status',
                'product_stock_mappings.inventory_amazon',
                'product_stock_mappings.image',
                'product_stock_mappings.title'
            );

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('amazon_datsheets.sku', 'LIKE', "%{$search}%")
                  ->orWhere('amazon_datsheets.asin', 'LIKE', "%{$search}%")
                  ->orWhere('product_stock_mappings.title', 'LIKE', "%{$search}%");
            });
        }

        // Apply status filter
        if ($status) {
            $query->where('amazon_datsheets.listing_status', $status);
        }

        $listings = $query->orderBy('amazon_datsheets.sku')
            ->paginate(50);

        // Get channel availability for each listing
        foreach ($listings as $listing) {
            $listing->shopify_available = ShopifySku::where('sku', $listing->sku)->exists();
            $listing->ebay_available = EbayMetric::where('sku', $listing->sku)->exists();
            
            // Get latest sync status
            $listing->last_sync = ListingMirrorSync::where('sku', $listing->sku)
                ->orderBy('synced_at', 'desc')
                ->first();
        }

        return view('listing-mirror.index', compact('listings', 'search', 'status'));
    }

    /**
     * Sync inventory to a specific channel
     */
    public function syncInventory(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'channel' => 'required|string|in:shopify,ebay',
            'quantity' => 'nullable|integer|min:0',
        ]);

        $sku = $request->sku;
        $channel = $request->channel;
        $quantity = $request->quantity;

        try {
            // Get Amazon inventory if quantity not provided
            if ($quantity === null) {
                $stockMapping = ProductStockMapping::where('sku', $sku)->first();
                $quantity = (int) ($stockMapping->inventory_amazon ?? 0);
                
                // Try FBA table if not found
                if ($quantity === 0) {
                    $fbaItem = FbaTable::where('seller_sku', $sku)
                        ->orWhere('fulfillment_channel_sku', $sku)
                        ->first();
                    $quantity = $fbaItem ? (int) $fbaItem->quantity_available : 0;
                }
            }

            // Create sync record
            $sync = ListingMirrorSync::create([
                'sku' => $sku,
                'channel' => $channel,
                'sync_type' => 'inventory',
                'status' => 'processing',
                'source_data' => ['quantity' => $quantity],
            ]);

            // Perform sync based on channel
            if ($channel === 'shopify') {
                $result = $this->shopifySyncService->syncInventory($sku, $quantity);
            } else if ($channel === 'ebay') {
                $account = $request->get('account', 'ebay1');
                $result = $this->ebaySyncService->syncInventory($sku, $quantity, $account);
            } else {
                throw new \Exception("Unsupported channel: {$channel}");
            }

            // Update sync record
            $sync->update([
                'status' => $result['success'] ? 'completed' : 'failed',
                'error_message' => $result['success'] ? null : $result['message'],
                'target_data' => ['quantity' => $quantity],
                'response_data' => $result,
                'synced_at' => now(),
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Inventory synced to {$channel} successfully",
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Listing mirror sync inventory error', [
                'sku' => $sku,
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync price to a specific channel
     */
    public function syncPrice(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'channel' => 'required|string|in:shopify,ebay',
            'price' => 'nullable|numeric|min:0',
        ]);

        $sku = $request->sku;
        $channel = $request->channel;
        $price = $request->price;

        try {
            // Get Amazon price if not provided
            if ($price === null) {
                $amazonListing = AmazonDatasheet::where('sku', $sku)->first();
                $price = $amazonListing ? (float) $amazonListing->price : 0;
            }

            if ($price <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid price: {$price}"
                ], 400);
            }

            // Create sync record
            $sync = ListingMirrorSync::create([
                'sku' => $sku,
                'channel' => $channel,
                'sync_type' => 'price',
                'status' => 'processing',
                'source_data' => ['price' => $price],
            ]);

            // Perform sync based on channel
            if ($channel === 'shopify') {
                $result = $this->shopifySyncService->syncPrice($sku, $price);
            } else if ($channel === 'ebay') {
                $account = $request->get('account', 'ebay1');
                $result = $this->ebaySyncService->syncPrice($sku, $price, $account);
            } else {
                throw new \Exception("Unsupported channel: {$channel}");
            }

            // Update sync record
            $sync->update([
                'status' => $result['success'] ? 'completed' : 'failed',
                'error_message' => $result['success'] ? null : $result['message'],
                'target_data' => ['price' => $price],
                'response_data' => $result,
                'synced_at' => now(),
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Price synced to {$channel} successfully",
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Listing mirror sync price error', [
                'sku' => $sku,
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk sync - sync multiple items at once
     */
    public function bulkSync(Request $request)
    {
        $request->validate([
            'skus' => 'required|array',
            'skus.*' => 'required|string',
            'channel' => 'required|string|in:shopify,ebay',
            'sync_type' => 'required|string|in:inventory,price,both',
        ]);

        $skus = $request->skus;
        $channel = $request->channel;
        $syncType = $request->sync_type;
        $results = [];

        foreach ($skus as $sku) {
            try {
                if ($syncType === 'inventory' || $syncType === 'both') {
                    $this->syncInventory(new Request([
                        'sku' => $sku,
                        'channel' => $channel,
                        'account' => $request->get('account', 'ebay1'),
                    ]));
                }

                if ($syncType === 'price' || $syncType === 'both') {
                    $this->syncPrice(new Request([
                        'sku' => $sku,
                        'channel' => $channel,
                        'account' => $request->get('account', 'ebay1'),
                    ]));
                }

                $results[$sku] = ['success' => true];
            } catch (\Exception $e) {
                $results[$sku] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk sync completed",
            'results' => $results
        ]);
    }

    /**
     * Get sync history for a SKU
     */
    public function getSyncHistory(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
        ]);

        $history = ListingMirrorSync::where('sku', $request->sku)
            ->orderBy('synced_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($history);
    }
}
