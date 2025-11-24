<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ShopifyApiInventoryController;
use App\Models\ShopifySku;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Inventory;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\ShopifyInventory;
use Illuminate\Support\Facades\DB;


class VerificationAdjustmentController extends Controller
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
        return view('inventory-management.verification-adjustment');
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
        //
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


    // public function getViewVerificationAdjustmentData(Request $request)
    // {
    //     $normalizeSku = fn($sku) => strtoupper(trim(preg_replace('/\s+/', ' ', $sku)));

    //     // Fetch all product_master data
    //     $productMasterData = ProductMaster::all();
    //     if ($productMasterData->isEmpty()) {
    //         return response()->json([
    //             'message' => 'Failed to fetch data from product_master table',
    //             'status' => 500
    //         ], 500);
    //     }

    //     // Get all SKUs
    //     $skus = $productMasterData->pluck('sku')
    //         ->filter()
    //         ->unique()
    //         ->map(fn($sku) => $normalizeSku($sku))
    //         ->toArray();

    //     // Fetch Shopify SKU data from local DB (shopify_skus table)
    //     $shopifyData = ShopifySku::whereIn('sku', $skus)
    //         ->get()
    //         ->keyBy(fn($item) => $normalizeSku($item->sku));

    //     // Fetch latest verified inventory
    //     $latestInventoryIds = Inventory::select(DB::raw('MAX(id) as latest_id'))
    //         ->whereIn('sku', $skus)
    //         ->groupBy('sku')
    //         ->pluck('latest_id');

    //     $latestInventoryData = Inventory::whereIn('id', $latestInventoryIds)->get();

    //     $verifiedStockData = $latestInventoryData
    //         ->filter(fn($inv) => $inv->is_hide == 0)
    //         ->keyBy(fn($inv) => $normalizeSku($inv->sku));

    //     $hiddenSkuSet = $latestInventoryData
    //         ->filter(fn($inv) => $inv->is_hide == 1)
    //         ->pluck('sku')
    //         ->map(fn($sku) => $normalizeSku($sku))
    //         ->toArray();

    //     // Filter out hidden SKUs
    //     $filteredData = $productMasterData->filter(fn($item) => !in_array($normalizeSku($item->sku ?? ''), $hiddenSkuSet));

    //     $mergedData = $filteredData->map(function ($item) use ($shopifyData, $verifiedStockData, $normalizeSku) {
    //         $childSku = $normalizeSku($item->sku ?? '');
    //         $isParent = stripos($childSku, 'PARENT') === 0;
    //         $item->IS_PARENT = $isParent;

    //         $values = $item->values;
    //         $lp = $values['lp'] ?? 0;

    //         if (!$isParent) {
    //             // Shopify SKU data
    //             if ($shopifyData->has($childSku)) {
    //                 $shopifyRow = $shopifyData[$childSku];
    //                 $item->INV = $shopifyRow->inv;
    //                 $item->L30 = $shopifyRow->quantity;
    //                 $item->ON_HAND = $shopifyRow->on_hand;
    //                 $item->COMMITTED = $shopifyRow->committed;
    //                 $item->AVAILABLE_TO_SELL = $shopifyRow->available_to_sell;
    //                 $item->IMAGE_URL = $shopifyRow->image_src ?? null;
    //             } else {
    //                 $item->INV = 0;
    //                 $item->L30 = 0;
    //                 $item->ON_HAND = 0;
    //                 $item->COMMITTED = 0;
    //                 $item->AVAILABLE_TO_SELL = 0;
    //                 $item->IMAGE_URL = null;
    //             }

    //             // Verified stock data
    //             if ($verifiedStockData->has($childSku)) {
    //                 $inv = $verifiedStockData[$childSku];
    //                 $item->VERIFIED_STOCK = $inv->verified_stock ?? null;
    //                 $item->TO_ADJUST = $inv->to_adjust ?? null;
    //                 $item->REASON = $inv->reason ?? null;
    //                 $item->REMARKS = $inv->REMARKS ?? null;
    //                 $item->APPROVED = (bool) $inv->approved;
    //                 $item->APPROVED_BY = $inv->approved_by ?? null;
    //                 $item->APPROVED_AT = $inv->approved_at ?? null;
    //             } else {
    //                 $item->VERIFIED_STOCK = null;
    //                 $item->TO_ADJUST = null;
    //                 $item->REASON = null;
    //                 $item->REMARKS = null;
    //                 $item->APPROVED = false;
    //                 $item->APPROVED_BY = null;
    //                 $item->APPROVED_AT = null;
    //             }

    //             // Calculate loss/gain
    //             $adjustedQty = isset($item->TO_ADJUST) && is_numeric($item->TO_ADJUST) ? floatval($item->TO_ADJUST) : 0;
    //             $item->LOSS_GAIN = round($adjustedQty * $lp, 2);

    //             // Update ShopifyInventory table
    //             ShopifyInventory::updateOrCreate(
    //                 ['sku' => $childSku],
    //                 [
    //                     'parent' => $item->parent ?? null,
    //                     'on_hand' => $item->ON_HAND,
    //                     'committed' => $item->COMMITTED,
    //                     'available_to_sell' => $item->AVAILABLE_TO_SELL,
    //                     'updated_at' => now(),
    //                 ]
    //             );
    //         }

    //         return $item;
    //     });

    //     return response()->json([
    //         'message' => 'Data fetched successfully',
    //         'data' => $mergedData->values(),
    //         'status' => 200
    //     ]);
    // }

    public function getViewVerificationAdjustmentData(Request $request)
    {
        // $normalizeSku = fn($sku) => strtoupper(trim(preg_replace('/\s+/', ' ', $sku)));
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse spaces
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace
            return $sku;
        };

        // Fetch product master
        $productMasterData = ProductMaster::all();

        // Get SKUs and remove hidden ones
        $skus = $productMasterData->pluck('sku')
            ->filter()
            ->unique()
            ->map(fn($sku) => $normalizeSku($sku))
            ->toArray();

        // Fetch Shopify data from local DB (shopify_skus)
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        // Fetch verified inventory from local table (already latest)
        $verifiedInventory = Inventory::whereIn('sku', $skus)
            ->where('is_hide', 0)
            ->get()
            ->keyBy(fn($inv) => $normalizeSku($inv->sku));

        // Merge everything
        $data = $productMasterData->map(function ($item) use ($shopifyData, $verifiedInventory, $normalizeSku) {
            $sku = $normalizeSku($item->sku ?? '');
            $values = $item->values;
            $lp = $values['lp'] ?? 0;

            $item->IS_PARENT = stripos($sku, 'PARENT') === 0;

            if (!$item->IS_PARENT) {
                $shopify = $shopifyData[$sku] ?? null;
                $inv = $verifiedInventory[$sku] ?? null;

                $item->INV = $shopify->inv ?? 0;
                $item->L30 = $shopify->quantity ?? 0;
                $item->ON_HAND = $shopify->on_hand ?? 0;
                $item->COMMITTED = $shopify->committed ?? 0;
                $item->AVAILABLE_TO_SELL = $shopify->available_to_sell ?? 0;
                $item->IMAGE_URL = $shopify->image_src ?? null;

                $item->VERIFIED_STOCK = $inv->verified_stock ?? null;
                $item->TO_ADJUST = $inv->to_adjust ?? null;
                $item->REASON = $inv->reason ?? null;
                $item->REMARKS = $inv->REMARKS ?? null;
                $item->APPROVED = (bool)($inv->approved ?? false);
                $item->APPROVED_BY = $inv->approved_by ?? null;
                $item->APPROVED_AT = $inv->approved_at ?? null;

                $adjustedQty = isset($item->TO_ADJUST) && is_numeric($item->TO_ADJUST) ? floatval($item->TO_ADJUST) : 0;
                $item->LOSS_GAIN = round($adjustedQty * $lp, 2);

                if ($sku === $normalizeSku('SS HD 2PK ORG WOB')) {
                    Log::info("=== DEBUG SKU SS HD 2PK ORG WOB ===", [
                        'Normalized_SKU' => $sku,
                        'Shopify Record Exists' => isset($shopify),
                        'Shopify Raw' => $shopify ? $shopify->toArray() : null,
                        'Shopify_INV' => $shopify->inv ?? null,
                        'Shopify_ON_HAND' => $shopify->on_hand ?? null,
                        'Shopify_COMMITTED' => $shopify->committed ?? null,
                        'Shopify_AVAILABLE_TO_SELL' => $shopify->available_to_sell ?? null,
                        'Shopify_QUANTITY' => $shopify->quantity ?? null,

                        'Inventory Record Exists' => isset($inv),
                        'Inventory Raw' => $inv ? $inv->toArray() : null,

                        'Final INV Sent' => $item->INV,
                        'Final ON_HAND Sent' => $item->ON_HAND,
                        'Final AVAILABLE_TO_SELL' => $item->AVAILABLE_TO_SELL,
                    ]);
                }

            }

            return $item;
        });

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $data->values(),
            'status' => 200
        ]);
    }


    // public function updateVerifiedStock(Request $request)   //current
    // {
    //     $validated = $request->validate([
    //         'sku' => 'nullable|string',
    //         'verified_stock' => 'required|numeric',
    //         'on_hand' => 'nullable|numeric',
    //         'reason' => 'required|string',
    //         'remarks' => 'nullable|string',
    //         'is_approved' => 'required|boolean',
    //     ]);

    //     $lp = 0;
    //     $sku = trim($validated['sku']);
    //     $product = ProductMaster::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->first();

    //     if ($product) {
    //         // $values = json_decode($product->Values, true);
    //         $values = $product->Values; 
    //         if (isset($values['lp']) && is_numeric($values['lp'])) {
    //             $lp = floatval($values['lp']);
    //         }
    //     } 
    //     // $response = $this->apiController->fetchDataFromProductMasterGoogleSheet(); 
    //     // if ($response->getStatusCode() === 200) { 
    //     //     $sheetData = $response->getData()->data; 
    //     //     foreach ($sheetData as $row) { 
    //     //         if (isset($row->SKU) && strtoupper(trim($row->SKU)) === strtoupper(trim($validated['sku']))) { 
    //     //             $lp = isset($row->LP) && is_numeric($row->LP) ? floatval($row->LP) : 0; 
    //     //             break; 
    //     //         }
    //     //     }
    //     // }

    //     $toAdjust = $validated['verified_stock'] - ($validated['on_hand'] ?? 0);
    //     $lossGain = round($toAdjust * $lp, 2);


    //     // Save record in DB
    //     $record = new Inventory();
    //     $record->sku = $validated['sku'];
    //     $record->on_hand = $validated['on_hand'];
    //     $record->verified_stock = $validated['verified_stock'];
    //     $record->reason = $validated['reason'];
    //     $record->remarks = $validated['remarks'];
    //     $record->is_approved = $validated['is_approved'];
    //     $record->approved_by = $validated['is_approved'] ? Auth::user()->name : null;
    //     $record->approved_at = $validated['is_approved'] ? Carbon::now('America/New_York') : null;
    //     $record->to_adjust = $toAdjust;
    //     $record->loss_gain = $lossGain;
    //     $record->is_hide = 0;
    //     $record->save();

    //     if ($validated['is_approved']) {
    //         $sku = $validated['sku'];
    //         // $verifiedToAdd = $validated['verified_stock']; // This is the value to add

    //         // 1. Fetch all products (with pagination to ensure all SKUs are fetched)
    //         $inventoryItemId = null;
    //         $pageInfo = null;

    //         do {
    //             $queryParams = ['limit' => 250];
    //             if ($pageInfo) {
    //                 $queryParams['page_info'] = $pageInfo;
    //             }

    //             $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //                 ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

    //             $products = $response->json('products');

    //             foreach ($products as $product) {
    //                 foreach ($product['variants'] as $variant) {
    //                     if ($variant['sku'] === $sku) {
    //                         $inventoryItemId = $variant['inventory_item_id'];
    //                         break 2;
    //                     }
    //                 }
    //             }

    //             // Handle pagination
    //             $linkHeader = $response->header('Link');
    //             $pageInfo = null;
    //             if ($linkHeader && preg_match('/<([^>]+page_info=([^&>]+)[^>]*)>; rel="next"/', $linkHeader, $matches)) {
    //                 $pageInfo = $matches[2];
    //             }

    //         } while (!$inventoryItemId && $pageInfo);

    //         if (!$inventoryItemId) {
    //             return response()->json(['success' => false, 'message' => 'Inventory item ID not found for SKU.']);
    //         }

    //         // 2. Get location ID and current available
    //         $invLevelResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //             ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
    //                 'inventory_item_ids' => $inventoryItemId
    //             ]);

    //         $levels = $invLevelResponse->json('inventory_levels');
    //         $locationId = $levels[0]['location_id'] ?? null;
    //         // $currentAvailable = $levels[0]['available'] ?? 0;

    //         if (!$locationId) {
    //             return response()->json(['success' => false, 'message' => 'Location ID not found for inventory item.']);
    //         }

    //         // 4. Send inventory adjustment to Shopify
    //         $adjustResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //             ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
    //                 'inventory_item_id' => $inventoryItemId,
    //                 'location_id' => $locationId,
    //                 'available_adjustment' => $toAdjust,
    //             ]);

    //         Log::info('Shopify Adjust Response:', $adjustResponse->json());

    //         if (!$adjustResponse->successful()) {
    //             return response()->json(['success' => false, 'message' => 'Failed to update Shopify inventory.']);
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'sku' => $record->sku,
    //             'verified_stock' => $record->verified_stock,
    //             'reason' => $record->reason,
    //             'remarks' => $record->remarks,
    //             'is_approved' => $record->is_approved,
    //             'approved_by' => $record->approved_by,
    //             'approved_at' => optional($record->approved_at)->format('Y-m-d\TH:i:s.u\Z'),
    //             'created_at' => optional($record->created_at)->format('Y-m-d\TH:i:s.u\Z'),
    //             'updated_at' => optional($record->updated_at)->format('Y-m-d\TH:i:s.u\Z'),
    //             'to_adjust' => $record->to_adjust,
    //             'loss_gain' => $lossGain, // Only used in response, not stored
    //         ]
    //     ]);
    // }


    public function updateVerifiedStock(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'nullable|string',
            'verified_stock' => 'required|numeric',
            'on_hand' => 'nullable|numeric',
            'reason' => 'required|string',
            'remarks' => 'nullable|string',
            'is_approved' => 'required|boolean',
        ]);

        $lp = 0;
        $sku = trim($validated['sku']);
        $product = ProductMaster::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->first();

        if ($product) {
            $values = $product->Values; 
            if (isset($values['lp']) && is_numeric($values['lp'])) {
                $lp = floatval($values['lp']);
            }
        }

        $toAdjust = $validated['verified_stock'] - ($validated['on_hand'] ?? 0);
        $lossGain = round($toAdjust * $lp, 2);

        $shopifyUpdateStatus = 'not_attempted';
        $inventoryItemId = null;
        $pageInfo = null;

        // If approving, FIRST update Shopify, then save to DB
        if ($validated['is_approved']) {
            // Normalize input SKU: replace all whitespace (normal + non-breaking) with single space
            $normalizedSku = strtoupper(preg_replace('/\s+/u', ' ', $sku));

            // Fast path: try local shopify_skus table for variant_id (one DB lookup)
            try {
                $shopifyRow = ShopifySku::whereRaw('LOWER(sku) = ?', [strtolower($normalizedSku)])->first();
            } catch (\Exception $e) {
                Log::error('Error fetching ShopifySku', ['sku' => $normalizedSku, 'error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Database error while fetching SKU information'
                ], 500);
            }

            if ($shopifyRow && !empty($shopifyRow->variant_id)) {
                // Single API call to fetch variant details (contains inventory_item_id)
                try {
                    $variantResp = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                        ->timeout(30)
                        ->retry(3, 2000)
                        ->get("https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$shopifyRow->variant_id}.json");

                    if ($variantResp->successful()) {
                        $inventoryItemId = $variantResp->json('variant.inventory_item_id') ?? null;
                        Log::info('Found inventory_item_id from variant', [
                            'sku' => $normalizedSku,
                            'variant_id' => $shopifyRow->variant_id,
                            'inventory_item_id' => $inventoryItemId
                        ]);
                    } else {
                        Log::warning('Variant lookup failed, falling back to product scan', [
                            'variant_id' => $shopifyRow->variant_id,
                            'status' => $variantResp->status(),
                            'response' => $variantResp->body()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Variant lookup exception, will fallback to product scan', ['err' => $e->getMessage()]);
                }
            } else {
                Log::info('No variant_id found in local DB for SKU, will search products', ['sku' => $normalizedSku]);
            }

            // Fallback: if we still don't have inventory_item_id, do the paginated product search
            if (!$inventoryItemId) {
                Log::info('Starting product search for inventory_item_id', ['sku' => $normalizedSku]);
                $productsChecked = 0;
                
                do {
                    $queryParams = ['limit' => 250, 'fields' => 'variants'];
                    if ($pageInfo) {
                        $queryParams['page_info'] = $pageInfo;
                    }

                    $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                        ->timeout(30)
                        ->retry(3, 2000)
                        ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

                    if (!$response->successful()) {
                        Log::error('Failed to fetch products from Shopify', [
                            'status' => $response->status(),
                            'response' => $response->body()
                        ]);
                        break;
                    }

                    $products = $response->json('products');
                    $productsChecked += count($products);

                    foreach ($products as $product) {
                        foreach ($product['variants'] as $variant) {
                            $variantSku = strtoupper(preg_replace('/\s+/u', ' ', trim($variant['sku'] ?? '')));
                            if ($variantSku === $normalizedSku) {
                                $inventoryItemId = $variant['inventory_item_id'];
                                Log::info('Found inventory_item_id via product search', [
                                    'sku' => $normalizedSku,
                                    'inventory_item_id' => $inventoryItemId,
                                    'products_checked' => $productsChecked
                                ]);
                                break 2;
                            }
                        }
                    }

                    // Handle pagination
                    $linkHeader = $response->header('Link');
                    $pageInfo = null;
                    if ($linkHeader && preg_match('/<([^>]+page_info=([^&>]+)[^>]*)>; rel="next"/', $linkHeader, $matches)) {
                        $pageInfo = $matches[2];
                    }

                } while (!$inventoryItemId && $pageInfo);
                
                if (!$inventoryItemId) {
                    Log::warning('Inventory item ID not found after checking all products', [
                        'sku' => $normalizedSku,
                        'products_checked' => $productsChecked
                    ]);
                }
            }

            if (!$inventoryItemId) {
                Log::error('Inventory item ID not found for SKU', ['sku' => $sku]);
                return response()->json([
                    'success' => false,
                    'message' => 'SKU not found in Shopify. Please sync inventory first.'
                ], 404);
            }

            // Get location ID with retry logic
            try {
                $invLevelResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                    ->timeout(30)
                    ->retry(3, 2000)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
                        'inventory_item_ids' => $inventoryItemId
                    ]);

                if (!$invLevelResponse->successful()) {
                    Log::error('Failed to fetch inventory levels', [
                        'inventory_item_id' => $inventoryItemId,
                        'status' => $invLevelResponse->status(),
                        'response' => $invLevelResponse->body()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to fetch Shopify inventory levels'
                    ], 500);
                }

                $levels = $invLevelResponse->json('inventory_levels');
                $locationId = $levels[0]['location_id'] ?? null;

                if (!$locationId) {
                    Log::error('Location ID not found for inventory item', [
                        'inventory_item_id' => $inventoryItemId,
                        'levels_response' => $levels
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Shopify location not found for this SKU'
                    ], 404);
                }

                Log::info('Attempting to adjust Shopify inventory', [
                    'sku' => $sku,
                    'inventory_item_id' => $inventoryItemId,
                    'location_id' => $locationId,
                    'adjustment' => $toAdjust
                ]);

                // Adjust inventory in Shopify with retry logic
                $adjustResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                    ->timeout(30)
                    ->retry(3, 2000)
                    ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                        'inventory_item_id' => $inventoryItemId,
                        'location_id' => $locationId,
                        'available_adjustment' => $toAdjust,
                    ]);

                if (!$adjustResponse->successful()) {
                    Log::error('Failed to update Shopify inventory', [
                        'sku' => $sku,
                        'status' => $adjustResponse->status(),
                        'response' => $adjustResponse->body()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to update Shopify inventory: ' . $adjustResponse->body()
                    ], 500);
                }

                Log::info('Successfully updated Shopify inventory', [
                    'sku' => $sku,
                    'adjustment' => $toAdjust,
                    'response' => $adjustResponse->json()
                ]);
                $shopifyUpdateStatus = 'success';

            } catch (\Exception $e) {
                Log::error('Exception during Shopify inventory update', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating Shopify: ' . $e->getMessage()
                ], 500);
            }
        }

        // Only save to database if not approved OR if Shopify update succeeded
        $record = new Inventory();
        $record->sku = $sku;
        $record->on_hand = $validated['on_hand'];
        $record->verified_stock = $validated['verified_stock'];
        $record->reason = $validated['reason'];
        $record->remarks = $validated['remarks'];
        $record->is_approved = $validated['is_approved'];
        $record->approved_by = $validated['is_approved'] ? Auth::user()->name : null;
        $record->approved_at = $validated['is_approved'] ? Carbon::now('America/New_York') : null;
        $record->to_adjust = $toAdjust;
        $record->loss_gain = $lossGain;
        $record->is_hide = 0;
        $record->save();

        return response()->json([
            'success' => true,
            'message' => $validated['is_approved'] 
                ? 'Approved and Shopify inventory updated successfully' 
                : 'Record saved successfully',
            'data' => [
                'sku' => $record->sku,
                'verified_stock' => $record->verified_stock,
                'reason' => $record->reason,
                'remarks' => $record->remarks,
                'is_approved' => $record->is_approved,
                'approved_by' => $record->approved_by,
                'approved_at' => optional($record->approved_at)->format('Y-m-d\TH:i:s.u\Z'),
                'created_at' => optional($record->created_at)->format('Y-m-d\TH:i:s.u\Z'),
                'updated_at' => optional($record->updated_at)->format('Y-m-d\TH:i:s.u\Z'),
                'to_adjust' => $record->to_adjust,
                'loss_gain' => $lossGain,
            ]
        ]);
    }


    public function getVerifiedStock()
    {
        $savedInventories = Inventory::all();


        // Format data to return in JSON with key 'data'
        $data = $savedInventories->map(function ($item) {

            return [
                'sku' => strtoupper(trim($item->sku)),
                'R&A' => (bool) $item->is_ra_checked,
                'verified_stock' => $item->verified_stock,
                'reason' => $item->reason,
                'is_approved' => (bool) $item->is_approved,
                'approved_by_ih' => (bool) $item->approved_by_ih,
                'approved_by' => $item->approved_by,
                'approved_at' =>  $item->approved_at,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function updateApprovedByIH(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'approved_by_ih' => 'required|boolean',
        ]);

        $inventory = Inventory::where('sku', $request->sku)->first();

        if (!$inventory) {
            return response()->json(['success' => false, 'message' => 'SKU not found.']);
        }

        $inventory->approved_by_ih = $request->approved_by_ih;
        $inventory->save();

        return response()->json(['success' => true]);
    }


    public function updateRAStatus(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'is_ra_checked' => 'required|boolean'
        ]);

        $inventory = Inventory::where('sku', $validated['sku'])->first();

        if ($inventory) {
            // SKU exists → Only update is_ra_checked
            $inventory->is_ra_checked = $validated['is_ra_checked'];
            $inventory->save();
        } else {
            //  SKU not found → Create new record
            $inventory = Inventory::create([
                'sku' => $validated['sku'],
                'is_ra_checked' => $validated['is_ra_checked'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function getVerifiedStockActivityLog()
    {
        $activityLogs = Inventory::where('type', null)->where('is_approved', true)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'sku' => $item->sku,
                    'verified_stock' => $item->verified_stock,
                    'to_adjust' => $item->to_adjust,
                    'loss_gain' => $item->loss_gain,
                    'reason' => $item->reason,
                    'remarks' => $item->remarks,
                    'approved_by' => $item->approved_by,
                    'approved_at' => Carbon::parse($item->created_at)->timezone('America/New_York')->format('d M Y, h:i A'),
                ];
            });
            
        return response()->json(['data' => $activityLogs]);
    }


    public function viewInventory()
    {
        return view('inventory-management.view-inventory');
    }

    public function getSkuWiseHistory(Request $request)
    {
        $sku = $request->input('sku');

        $query = Inventory::where('is_approved', true);

        if ($sku) {
            $query->where('sku', $sku);
        }

        $activityLogs = $query->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'sku' => $item->sku,
                    'verified_stock' => $item->verified_stock,
                    'to_adjust' => $item->to_adjust,
                    'on_hand' => $item->on_hand,
                    'reason' => $item->reason,
                    'approved_by' => $item->approved_by,
                    'approved_at' => Carbon::parse($item->created_at)->timezone('America/New_York')->format('d M Y, h:i A'),
                ];
            });

        return response()->json(['data' => $activityLogs]);
    }


    public function toggleHide(Request $request)
    {
        $latestRecord = Inventory::where('sku', $request->sku)->latest()->first();

        if ($latestRecord) {
            $latestRecord->update(['is_hide' => 1]);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Record not found.']);
    }


    public function getHiddenRows()
    {
        $latestHiddenIds = Inventory::select(DB::raw('MAX(id) as latest_id'))
            ->where('is_hide', 1)
            ->groupBy('sku')
            ->pluck('latest_id');

            Inventory::where('is_hide', 1)
            ->whereNotIn('id', $latestHiddenIds)
            ->update(['is_hide' => 0]);

        $hiddenRecords = Inventory::whereIn('id', $latestHiddenIds)->get();

        $data = $hiddenRecords->map(function ($item) {
            return [
                'sku' => $item->sku,
                'verified_stock' => $item->verified_stock,
                'to_adjust' => $item->to_adjust,
                'loss_gain' => $item->loss_gain, // already stored in DB
                'reason' => $item->reason,
                'approved_by' => $item->approved_by,
                'approved_at' => $item->approved_at 
                    ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('Y-m-d H:i:s') 
                    : null,
                'remarks' => $item->remarks ?? '-',
            ];
        });

        return response()->json(['data' => $data]);
    }


    public function unhideMultipleRows(Request $request)
    {
        $skus = $request->skus ?? [];

        foreach ($skus as $sku) {
            $latest = Inventory::where('sku', $sku)->where('is_hide', 1)->latest()->first();
            if ($latest) {
                $latest->update(['is_hide' => 0]);
            }
        }

        return response()->json(['success' => true]);
    }

    
}
