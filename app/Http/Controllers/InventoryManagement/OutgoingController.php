<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\OutgoingEditHistory;
use App\Models\OutgoingReason;
use App\Models\ShopifySku;
use App\Models\AmazonDatasheet;
use App\Models\ChannelMaster;
use App\Models\OutgoingOrderMeta;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Http\Controllers\ShopifyApiInventoryController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

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
        $skus = ProductMaster::select('product_master.id', 'product_master.parent', 'product_master.sku', 'shopify_skus.inv as available_quantity')
        ->leftJoin('shopify_skus', 'product_master.sku', '=', 'shopify_skus.sku')
        ->get();

        $reasons = OutgoingReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray();
        $channels = ChannelMaster::where('status', 'Active')
            ->orderBy('channel')
            ->pluck('channel')
            ->values()
            ->all();

        return view('inventory-management.outgoing-view', compact('warehouses', 'skus', 'reasons', 'channels'));
    }

    public function getReasons()
    {
        $reasons = OutgoingReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray();
        return response()->json(['reasons' => $reasons]);
    }

    public function storeReason(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $name = trim($request->name);
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Reason name is required.'], 422);
        }
        $exists = OutgoingReason::where('name', $name)->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'This reason already exists.'], 422);
        }
        $maxOrder = OutgoingReason::max('sort_order') ?? 0;
        OutgoingReason::create([
            'name' => $name,
            'sort_order' => $maxOrder + 1,
        ]);
        return response()->json(['success' => true, 'reasons' => OutgoingReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray()]);
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
        $requireChannel = ChannelMaster::where('status', 'Active')->exists();
        $request->validate([
            'sku' => 'required|array',
            'sku.*' => 'required|string',
            'qty' => 'required|array',
            'qty.*' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'reason' => 'required|string',
            'channel' => ($requireChannel ? 'required' : 'nullable') . '|string|max:255',
            'comment' => 'nullable|string|max:80',
            'replacement_tracking' => 'nullable|string|max:22',
            'order_id' => 'nullable|string|max:128',
        ]);

        $skus = $request->sku;
        $count = count($skus);
        $warehouseId = $request->warehouse_id;
        $reason = $request->reason;
        $comment = $request->filled('comment') ? trim($request->comment) : null;
        $replacementTracking = $request->filled('replacement_tracking') ? trim($request->replacement_tracking) : null;
        $channel = $request->filled('channel') ? trim((string) $request->channel) : null;
        if ($channel === '') {
            $channel = null;
        }
        $orderIdForMeta = $request->filled('order_id') ? trim((string) $request->order_id) : null;
        if ($orderIdForMeta === '') {
            $orderIdForMeta = null;
        }

        for ($i = 0; $i < $count; $i++) {
            $sku = trim($skus[$i]);
            $outgoingQty = (int) $request->qty[$i];
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
                return response()->json(['error' => 'SKU not found in Shopify. Please sync inventory first. (Row ' . ($i + 1) . ': ' . $normalizedSku . ')'], 404);
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
                return response()->json(['error' => 'Failed to fetch inventory levels from Shopify (Row ' . ($i + 1) . ')'], 500);
            }

            $levels = $invLevelResponse->json('inventory_levels');
            $locationId = $levels[0]['location_id'] ?? null;

            if (!$locationId) {
                Log::error('Outgoing: Location ID not found', [
                    'inventory_item_id' => $inventoryItemId,
                    'levels_response' => $levels
                ]);
                return response()->json(['error' => 'Shopify location not found for this SKU (Row ' . ($i + 1) . ')'], 404);
            }

            Log::info('Outgoing: Attempting to adjust Shopify inventory', [
                'sku' => $normalizedSku,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'adjustment' => -$outgoingQty
            ]);

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
                return response()->json(['error' => 'Failed to update Shopify inventory (Row ' . ($i + 1) . '): ' . $adjustResponse->body()], 500);
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
            return response()->json(['error' => 'Error updating Shopify (Row ' . ($i + 1) . '): ' . $e->getMessage()], 500);
        }

        try {
                $inv = Inventory::create([
                    'sku' => $sku,
                    'verified_stock' => $outgoingQty,
                    'to_adjust' => -$outgoingQty,
                    'reason' => $reason,
                    'comment' => $comment,
                    'replacement_tracking' => $replacementTracking,
                    'channel' => $channel,
                    'is_approved' => true,
                    'approved_by' => Auth::user()->name ?? 'N/A',
                    'approved_at' => Carbon::now('America/New_York'),
                    'type' => 'outgoing',
                    'warehouse_id' => $warehouseId,
                ]);
                if ($orderIdForMeta !== null) {
                    OutgoingOrderMeta::updateOrCreate(
                        ['inventory_id' => $inv->id],
                        ['order_id' => $orderIdForMeta]
                    );
                }
            } catch (\Exception $e) {
                Log::error('Outgoing: Failed to save to database after Shopify update', [
                    'sku' => $normalizedSku,
                    'error' => $e->getMessage()
                ]);
                return response()->json(['error' => 'Shopify updated but failed to save to database (Row ' . ($i + 1) . '): ' . $e->getMessage()], 500);
            }
        }

        $msg = $count === 1
            ? 'Outgoing inventory deducted from Shopify successfully.'
            : $count . ' outgoing items deducted from Shopify successfully.';
        return response()->json(['success' => true, 'message' => $msg]);
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
     * Update reason and comment for an outgoing record and log history.
     */
    public function updateReasonAndComment(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:inventories,id',
            'reason' => 'required|string|max:255',
            'comment' => 'nullable|string|max:80',
            'channel' => 'nullable|string|max:255',
            'order_id' => 'nullable|string|max:128',
        ]);

        $inv = Inventory::where('id', $request->id)->where('type', 'outgoing')->first();
        if (!$inv) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $reason = trim($request->reason);
        $comment = $request->filled('comment') ? trim($request->comment) : null;
        $user = Auth::user()->name ?? 'N/A';
        $now = Carbon::now('America/New_York');
        $input = $request->all();

        if ($inv->reason !== $reason) {
            OutgoingEditHistory::create([
                'inventory_id' => $inv->id,
                'sku' => $inv->sku,
                'field' => 'reason',
                'old_value' => $inv->reason,
                'new_value' => $reason,
                'updated_by' => $user,
                'updated_at' => $now,
            ]);
            $inv->reason = $reason;
        }

        $currentComment = $inv->comment ?? $inv->remarks;
        if ($currentComment !== $comment) {
            OutgoingEditHistory::create([
                'inventory_id' => $inv->id,
                'sku' => $inv->sku,
                'field' => 'comment',
                'old_value' => $currentComment,
                'new_value' => $comment,
                'updated_by' => $user,
                'updated_at' => $now,
            ]);
            $inv->comment = $comment;
            if (Schema::hasColumn('inventories', 'remarks')) {
                $inv->remarks = $comment;
            }
        }

        if (array_key_exists('order_id', $input)) {
            $rawOrder = $input['order_id'];
            $newOrderId = ($rawOrder === null || $rawOrder === '') ? null : trim((string) $rawOrder);
            if ($newOrderId === '') {
                $newOrderId = null;
            }
            $oldMeta = OutgoingOrderMeta::where('inventory_id', $inv->id)->first();
            $oldOrderId = $oldMeta?->order_id;
            if ((string) ($oldOrderId ?? '') !== (string) ($newOrderId ?? '')) {
                OutgoingEditHistory::create([
                    'inventory_id' => $inv->id,
                    'sku' => $inv->sku,
                    'field' => 'order_id',
                    'old_value' => (string) ($oldOrderId ?? ''),
                    'new_value' => (string) ($newOrderId ?? ''),
                    'updated_by' => $user,
                    'updated_at' => $now,
                ]);
                if ($newOrderId === null) {
                    if ($oldMeta) {
                        $oldMeta->delete();
                    }
                } else {
                    OutgoingOrderMeta::updateOrCreate(
                        ['inventory_id' => $inv->id],
                        ['order_id' => $newOrderId]
                    );
                }
            }
        }

        if (array_key_exists('channel', $input)) {
            $ch = $input['channel'];
            $newChannel = ($ch === null || $ch === '') ? null : trim((string) $ch);
            $oldChannel = $inv->channel;
            if ((string) ($oldChannel ?? '') !== (string) ($newChannel ?? '')) {
                OutgoingEditHistory::create([
                    'inventory_id' => $inv->id,
                    'sku' => $inv->sku,
                    'field' => 'channel',
                    'old_value' => (string) ($oldChannel ?? ''),
                    'new_value' => (string) ($newChannel ?? ''),
                    'updated_by' => $user,
                    'updated_at' => $now,
                ]);
                $inv->channel = $newChannel;
            }
        }

        $inv->save();

        $orderIdOut = OutgoingOrderMeta::where('inventory_id', $inv->id)->value('order_id');

        return response()->json([
            'success' => true,
            'message' => 'Updated.',
            'record' => [
                'id' => $inv->id,
                'sku' => $inv->sku,
                'reason' => $inv->reason,
                'remarks' => $inv->comment ?? $inv->remarks,
                'channel' => $inv->channel,
                'order_id' => $orderIdOut,
            ],
        ]);
    }

    /**
     * Get edit history for an outgoing record (by inventory id).
     */
    public function getHistory(Request $request, $id)
    {
        $id = (int) $id;
        $inv = Inventory::where('id', $id)->where('type', 'outgoing')->first();
        if (!$inv) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $history = OutgoingEditHistory::where('inventory_id', $id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($h) {
                return [
                    'field' => $h->field,
                    'field_label' => $h->field === 'reason' ? 'Reason' : ($h->field === 'channel' ? 'Channel' : ($h->field === 'order_id' ? 'Order Id' : 'Comments/Remarks')),
                    'old_value' => $h->old_value,
                    'new_value' => $h->new_value,
                    'updated_by' => $h->updated_by,
                    'updated_at' => Carbon::parse($h->updated_at)->timezone('America/New_York')->format('m-d-Y H:i'),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'sku' => $inv->sku,
            'history' => $history,
        ]);
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

    public function list(Request $request)
    {
        $query = Inventory::with(['warehouse', 'outgoingOrderMeta'])
            ->where('type', 'outgoing');

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }
        if ($request->filled('person')) {
            $query->where('approved_by', $request->person);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('approved_at', '>=', Carbon::parse($request->start_date)->startOfDay());
        }
        if ($request->filled('end_date')) {
            $query->whereDate('approved_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        $items = $query->latest()->get();

        $skus = $items->pluck('sku')->map(fn ($s) => strtolower(trim((string) $s)))->unique()->values()->toArray();
        $pricesBySku = [];
        if (!empty($skus)) {
            $placeholders = implode(',', array_fill(0, count($skus), '?'));
            $amazonRows = AmazonDatasheet::whereNotNull('price')
                ->whereRaw("LOWER(TRIM(sku)) IN ({$placeholders})", $skus)
                ->orderByDesc('id')
                ->get();
            foreach ($amazonRows as $row) {
                $key = strtolower(trim((string) $row->sku));
                if (!isset($pricesBySku[$key])) {
                    $pricesBySku[$key] = (float) $row->price;
                }
            }
        }

        $data = $items->map(function ($item) use ($pricesBySku) {
            $price = $pricesBySku[strtolower(trim((string) $item->sku))] ?? 0;
            $qty = (int) $item->verified_stock;
            $value = round($qty * $price, 2);
            $archived = (bool) ($item->is_archived ?? false);
            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'verified_stock' => $item->verified_stock,
                'reason' => $item->reason,
                'channel' => $item->channel,
                'order_id' => $item->outgoingOrderMeta?->order_id,
                'remarks' => $item->comment ?? $item->remarks,
                'replacement_tracking' => $item->replacement_tracking,
                'warehouse_name' => $item->warehouse->name ?? '',
                'approved_by' => $item->approved_by,
                'approved_at' => $item->approved_at
                    ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('m-d-Y')
                    : '',
                'price' => $price,
                'value' => $value,
                'is_archived' => $archived,
            ];
        })->values()->all();

        $reasons = OutgoingReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray();
        $persons = Inventory::where('type', 'outgoing')->distinct()->pluck('approved_by')->filter()->values()->all();
        $channelRows = ChannelMaster::where('status', 'Active')->orderBy('channel')->pluck('channel');
        $channelsFromData = Inventory::where('type', 'outgoing')->whereNotNull('channel')->where('channel', '!=', '')->distinct()->pluck('channel');
        $channels = $channelRows->merge($channelsFromData)->unique()->sort()->values()->all();

        return response()->json([
            'data' => $data,
            'reasons' => $reasons,
            'persons' => $persons,
            'channels' => $channels,
        ]);
    }

    public function archive(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:inventories,id',
        ]);

        $updated = Inventory::where('type', 'outgoing')
            ->whereIn('id', $request->ids)
            ->update(['is_archived' => true]);

        return response()->json([
            'success' => true,
            'message' => $updated . ' row(s) archived.',
        ]);
    }

    public function getAvailableQtyBySku(Request $request)
    {
        $sku = $request->input('sku');

        // Your logic to get total available from Shopify by SKU
        $available = $this->fetchAvailableFromShopifyBySku($sku); // your own method

        return response()->json(['available_quantity' => $available]);
    }

}
