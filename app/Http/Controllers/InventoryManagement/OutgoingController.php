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
use App\Services\ShopifyAdminCallGate;
use App\Services\ShopifyOhioLocationResolver;
use App\Services\ShopifyStockTransferGraphql;

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
        $warehouses = Warehouse::inventoryOptions();
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
        Warehouse::forceOnRequest($request);
        $request->validate([
            'sku' => 'required|array',
            'sku.*' => 'required|string',
            'qty' => 'required|array',
            'qty.*' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'reason' => 'required|string',
            'channel' => ($requireChannel ? 'required' : 'nullable') . '|string|max:255',
            'comment' => 'nullable|string|max:80',
            'replacement_tracking' => 'nullable|string|max:40',
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

            $adjusted = $this->adjustShopifyAvailable($sku, -$outgoingQty);
            if (! $adjusted['ok']) {
                Log::error('Outgoing: Shopify adjust failed', [
                    'sku' => $normalizedSku,
                    'error' => $adjusted['error'] ?? null,
                ]);
                $status = (int) ($adjusted['status'] ?? 500);

                return response()->json([
                    'error' => ($adjusted['error'] ?? 'Failed to update Shopify inventory')
                        .' (Row '.($i + 1).': '.$normalizedSku.')',
                ], $status >= 400 && $status < 600 ? $status : 500);
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

    /**
     * Programmatic entry point for the All Issues "Outgoing needed?" flow.
     *
     * Mirrors the per-row work done inside store(): resolves the SKU's Shopify
     * inventory_item_id + location_id, calls `inventory_levels/adjust.json`
     * with `available_adjustment = -$qty`, then writes a row into `inventories`
     * (type=outgoing, is_approved=true) so it shows on /outgoing-view.
     *
     * @return array{success: bool, inventory_id: int|null, error: string|null}
     */
    public function processOutgoingFromIssue(string $sku, int $qty, array $opts = []): array
    {
        $sku = trim($sku);
        if ($sku === '' || $qty <= 0) {
            return ['success' => false, 'inventory_id' => null, 'error' => 'SKU and positive qty are required.'];
        }

        $warehouseId = (int) ($opts['warehouse_id'] ?? 0);
       
        if ($warehouseId <= 0 || ! DB::table('warehouses')->where('id', $warehouseId)->exists()) {
            return ['success' => false, 'inventory_id' => null, 'error' => 'A valid warehouse is required (id ' . $warehouseId . ' not found).'];
        }

        $reason = trim((string) ($opts['reason'] ?? 'Replacement (All Issues)'));
        if ($reason === '') {
            $reason = 'Replacement (All Issues)';
        }
        // Auto-create the reason once so /outgoing-view filters keep working.
        if (! OutgoingReason::where('name', $reason)->exists()) {
            $maxOrder = OutgoingReason::max('sort_order') ?? 0;
            OutgoingReason::create(['name' => $reason, 'sort_order' => $maxOrder + 1]);
        }

        $comment             = isset($opts['comment']) ? trim((string) $opts['comment']) : null;
        if ($comment === '') {
            $comment = null;
        }
        $replacementTracking = isset($opts['replacement_tracking']) ? trim((string) $opts['replacement_tracking']) : null;
        if ($replacementTracking === '') {
            $replacementTracking = null;
        }
        $channel             = isset($opts['channel']) ? trim((string) $opts['channel']) : null;
        if ($channel === '') {
            $channel = null;
        }
        $orderId             = isset($opts['order_id']) ? trim((string) $opts['order_id']) : null;
        if ($orderId === '') {
            $orderId = null;
        }

        $normalizedSku = strtoupper(preg_replace('/\s+/u', ' ', $sku));

        $adjusted = $this->adjustShopifyAvailable($sku, -$qty);
        if (! $adjusted['ok']) {
            Log::error('Issue Outgoing: Shopify adjust failed', [
                'sku' => $normalizedSku,
                'error' => $adjusted['error'] ?? null,
            ]);

            return ['success' => false, 'inventory_id' => null, 'error' => $adjusted['error'] ?? 'Shopify rejected the inventory adjustment.'];
        }

        // ── Step 3: create the inventories row so /outgoing-view sees it ────
        try {
            $inv = Inventory::create([
                'sku'                  => $sku,
                'verified_stock'       => $qty,
                'to_adjust'            => -$qty,
                'reason'               => $reason,
                'comment'              => $comment,
                'replacement_tracking' => $replacementTracking,
                'channel'              => $channel,
                'is_approved'          => true,
                'approved_by'          => Auth::user()->name ?? 'System',
                'approved_at'          => Carbon::now('America/New_York'),
                'type'                 => 'outgoing',
                'warehouse_id'         => $warehouseId,
            ]);
            if ($orderId !== null) {
                OutgoingOrderMeta::updateOrCreate(
                    ['inventory_id' => $inv->id],
                    ['order_id' => $orderId]
                );
            }
            return ['success' => true, 'inventory_id' => (int) $inv->id, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Issue Outgoing: failed to write inventories row after Shopify adjust', [
                'sku' => $normalizedSku,
                'error' => $e->getMessage(),
            ]);
            // Shopify already adjusted; surface a warning but don't try to roll back.
            return ['success' => false, 'inventory_id' => null, 'error' => 'Shopify adjusted but local outgoing record could not be saved: ' . $e->getMessage()];
        }
    }

    /**
     * Push an available-qty delta to Shopify and mirror it onto shopify_skus.
     *
     * Custom apps reject Basic auth. The previous outgoing path sent the API
     * key and password, so Shopify never applied the deduction. This uses the
     * Admin access token and the configured Ohio location, the same way
     * verification and incoming already do.
     *
     * @return array{ok: bool, error: ?string, status: int, available: ?int}
     */
    private function adjustShopifyAvailable(string $sku, int $adjustment): array
    {
        if (! is_string($this->shopifyDomain) || $this->shopifyDomain === '') {
            return ['ok' => false, 'error' => 'Shopify store URL is not configured.', 'status' => 500, 'available' => null];
        }

        try {
            $inventoryItemId = $this->resolveOutgoingInventoryItemId($sku);
        } catch (\Throwable $e) {
            Log::error('Outgoing: Shopify SKU lookup failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Error searching SKU on Shopify: '.$e->getMessage(), 'status' => 500, 'available' => null];
        }

        if (! $inventoryItemId) {
            return ['ok' => false, 'error' => 'SKU not found in Shopify: '.$sku, 'status' => 404, 'available' => null];
        }

        $locationId = ShopifyOhioLocationResolver::preferredLocationId();
        if (! is_string($locationId) || $locationId === '') {
            return ['ok' => false, 'error' => 'Shopify Ohio location is not configured for SKU: '.$sku, 'status' => 404, 'available' => null];
        }

        try {
            $pushed = $this->pushShopifyAvailableAdjustment($inventoryItemId, $locationId, $adjustment);
        } catch (\Throwable $e) {
            Log::error('Outgoing: Shopify adjust exception', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Error contacting Shopify: '.$e->getMessage(), 'status' => 500, 'available' => null];
        }

        if (! $pushed['ok']) {
            return $pushed;
        }

        $this->mirrorShopifySkuAvailable($sku, $adjustment, $pushed['available'] ?? null);

        Log::info('Outgoing: Shopify inventory adjusted', [
            'sku' => $sku,
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'adjustment' => $adjustment,
            'available' => $pushed['available'] ?? null,
        ]);

        return $pushed;
    }

    /**
     * @return array{ok: bool, error: ?string, status: int, available: ?int}
     */
    private function pushShopifyAvailableAdjustment(string $inventoryItemId, string $locationId, int $adjustment): array
    {
        $result = $this->graphqlAdjustAvailable($inventoryItemId, $locationId, $adjustment);
        if (! $result['ok'] && $this->inventoryNotStockedMessage((string) ($result['error'] ?? ''))) {
            $connected = $this->connectInventoryToLocation($inventoryItemId, $locationId);
            if (! $connected['ok']) {
                return $connected;
            }
            $result = $this->graphqlAdjustAvailable($inventoryItemId, $locationId, $adjustment);
        }

        if ($result['ok']) {
            return $result;
        }

        return $this->restAdjustAvailable($inventoryItemId, $locationId, $adjustment);
    }

    /**
     * @return array{ok: bool, error: ?string, status: int, available: ?int}
     */
    private function graphqlAdjustAvailable(string $inventoryItemId, string $locationId, int $adjustment): array
    {
        $response = $this->shopifyHttp()->timeout(30)->post(
            "https://{$this->shopifyDomain}/admin/api/2025-01/graphql.json",
            [
                'query' => ShopifyStockTransferGraphql::ADJUST_MUTATION,
                'variables' => [
                    'input' => [
                        'reason' => 'correction',
                        'name' => 'available',
                        'changes' => [[
                            'delta' => $adjustment,
                            'inventoryItemId' => 'gid://shopify/InventoryItem/'.$inventoryItemId,
                            'locationId' => 'gid://shopify/Location/'.$locationId,
                        ]],
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            return [
                'ok' => false,
                'error' => 'Shopify rejected the inventory adjustment (HTTP '.$response->status().').',
                'status' => 500,
                'available' => null,
            ];
        }

        $parsed = ShopifyStockTransferGraphql::parseAdjust($response->json());
        if ($parsed['success'] ?? false) {
            return [
                'ok' => true,
                'error' => null,
                'status' => 200,
                'available' => $parsed['available'] ?? null,
            ];
        }

        return [
            'ok' => false,
            'error' => (string) ($parsed['error'] ?? 'Shopify rejected the inventory adjustment.'),
            'status' => 500,
            'available' => null,
        ];
    }

    /**
     * @return array{ok: bool, error: ?string, status: int, available: ?int}
     */
    private function restAdjustAvailable(string $inventoryItemId, string $locationId, int $adjustment): array
    {
        ShopifyAdminCallGate::acquire();
        $response = $this->shopifyHttp()->timeout(30)->post(
            "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json",
            [
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'available_adjustment' => $adjustment,
            ]
        );
        ShopifyAdminCallGate::record($response);

        if (! $response->successful()) {
            $body = $response->json('errors') ?? $response->body();
            $message = is_string($body) ? $body : json_encode($body);

            return [
                'ok' => false,
                'error' => 'Shopify rejected the inventory adjustment: '.$message,
                'status' => 500,
                'available' => null,
            ];
        }

        $available = $response->json('inventory_level.available');

        return [
            'ok' => true,
            'error' => null,
            'status' => 200,
            'available' => is_numeric($available) ? (int) $available : null,
        ];
    }

    /**
     * @return array{ok: bool, error: ?string, status: int, available: ?int}
     */
    private function connectInventoryToLocation(string $inventoryItemId, string $locationId): array
    {
        ShopifyAdminCallGate::acquire();
        $response = $this->shopifyHttp()->timeout(30)->post(
            "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/connect.json",
            [
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
            ]
        );
        ShopifyAdminCallGate::record($response);

        if ($response->successful() || $response->status() === 422) {
            return ['ok' => true, 'error' => null, 'status' => 200, 'available' => null];
        }

        return [
            'ok' => false,
            'error' => 'Could not stock this SKU at the Shopify warehouse.',
            'status' => 500,
            'available' => null,
        ];
    }

    private function resolveOutgoingInventoryItemId(string $sku): ?string
    {
        $row = ShopifySku::firstForProductSku($sku);
        $variantId = $row ? trim((string) ($row->variant_id ?? '')) : '';
        if ($variantId === '') {
            $catalogId = ShopifySku::mainCatalogVariantId($sku);
            $variantId = $catalogId !== null ? trim($catalogId) : '';
        }

        if ($variantId !== '') {
            $fromVariant = $this->inventoryItemIdFromVariant($variantId);
            if ($fromVariant) {
                return $fromVariant;
            }
        }

        $candidates = [];
        if ($row && trim((string) $row->sku) !== '') {
            $candidates[] = (string) $row->sku;
        }
        $candidates[] = trim($sku);
        $normalized = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($normalized !== '') {
            $candidates[] = $normalized;
        }

        foreach (array_values(array_unique(array_filter($candidates))) as $candidate) {
            $found = $this->inventoryItemIdFromSkuSearch($candidate, $sku);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function inventoryItemIdFromVariant(string $variantId): ?string
    {
        $numeric = preg_replace('/\D/', '', $variantId);
        if ($numeric === null || $numeric === '') {
            return null;
        }

        $query = <<<'GQL'
query ($id: ID!) {
  productVariant(id: $id) {
    inventoryItem { id }
  }
}
GQL;

        $response = $this->shopifyHttp()->timeout(30)->post(
            "https://{$this->shopifyDomain}/admin/api/2025-01/graphql.json",
            [
                'query' => $query,
                'variables' => ['id' => 'gid://shopify/ProductVariant/'.$numeric],
            ]
        );
        $this->throwIfShopifyAuthFailed($response);

        if (! $response->successful()) {
            return null;
        }

        return $this->numericShopifyId($response->json('data.productVariant.inventoryItem.id'));
    }

    private function inventoryItemIdFromSkuSearch(string $querySku, string $wantedSku): ?string
    {
        $query = <<<'GQL'
query ($q: String!) {
  productVariants(first: 10, query: $q) {
    nodes {
      sku
      inventoryItem { id }
    }
  }
}
GQL;

        $quoted = str_replace(['\\', '"'], ['\\\\', '\\"'], $querySku);
        $response = $this->shopifyHttp()->timeout(30)->post(
            "https://{$this->shopifyDomain}/admin/api/2025-01/graphql.json",
            [
                'query' => $query,
                'variables' => ['q' => 'sku:"'.$quoted.'"'],
            ]
        );
        $this->throwIfShopifyAuthFailed($response);

        if (! $response->successful()) {
            return null;
        }

        foreach ($response->json('data.productVariants.nodes') ?? [] as $node) {
            if (! is_array($node) || ! ShopifySku::skusMatch($wantedSku, (string) ($node['sku'] ?? ''))) {
                continue;
            }
            $id = $this->numericShopifyId($node['inventoryItem']['id'] ?? null);
            if ($id) {
                return $id;
            }
        }

        return null;
    }

    private function throwIfShopifyAuthFailed(\Illuminate\Http\Client\Response $response): void
    {
        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException('Shopify rejected the access token (HTTP '.$response->status().').');
        }
    }

    private function numericShopifyId(mixed $gid): ?string
    {
        if (! is_string($gid) || ! preg_match('/(\d+)$/', $gid, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function inventoryNotStockedMessage(string $message): bool
    {
        $text = strtolower($message);

        return str_contains($text, 'not stocked') || str_contains($text, 'not connected');
    }

    private function mirrorShopifySkuAvailable(string $sku, int $adjustment, ?int $newAvailable): void
    {
        try {
            $row = ShopifySku::firstForProductSku($sku);
            if (! $row) {
                return;
            }

            $old = (int) ($row->available_to_sell ?? $row->inv ?? 0);
            $available = $newAvailable !== null ? $newAvailable : $old + $adjustment;
            $row->inv = $available;
            if (Schema::hasColumn('shopify_skus', 'available_to_sell')) {
                $row->available_to_sell = $available;
            }
            if (Schema::hasColumn('shopify_skus', 'on_hand')) {
                $onHand = (int) ($row->on_hand ?? $old);
                $row->on_hand = $onHand + ($available - $old);
            }
            $row->save();
        } catch (\Throwable $e) {
            Log::warning('Outgoing: Shopify adjusted but local shopify_skus was not updated', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shopifyHttp(): \Illuminate\Http\Client\PendingRequest
    {
        $token = config('services.shopify.access_token') ?: $this->shopifyPassword;
        if (is_string($token) && $token !== '') {
            return Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ]);
        }

        return Http::withBasicAuth((string) $this->shopifyApiKey, (string) $this->shopifyPassword)
            ->withHeaders(['Content-Type' => 'application/json']);
    }
}
