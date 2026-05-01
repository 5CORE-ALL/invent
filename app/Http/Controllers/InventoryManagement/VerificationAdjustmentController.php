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
use Illuminate\Support\Facades\Cache;
use App\Models\ShopifyInventoryLog;
use App\Jobs\UpdateShopifyInventoryJob;
use App\Models\LostGainAqHistory;
use Illuminate\Support\Str;


class VerificationAdjustmentController extends Controller
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
     * Shopify Admin API client. Custom apps require X-Shopify-Access-Token (same as inventory sync);
     * Basic auth only applies to legacy private apps.
     */
    protected function shopifyHttp(): \Illuminate\Http\Client\PendingRequest
    {
        $token = config('services.shopify.access_token') ?: $this->shopifyPassword;

        if ($token !== null && $token !== '') {
            return Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ]);
        }

        return Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
            ->withHeaders(['Content-Type' => 'application/json']);
    }

    /**
     * Ohio / dashboard location used by sync — not necessarily inventory_levels[0] when multiple locations exist.
     */
    protected function getPreferredShopifyLocationId(): ?string
    {
        $configured = config('services.shopify.inventory_location_id');
        if (! empty($configured)) {
            return (string) $configured;
        }

        return Cache::remember('shopify_verification_preferred_location_id', 3600, function () {
            try {
                $response = $this->shopifyHttp()
                    ->timeout(30)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/locations.json");

                if (! $response->successful()) {
                    return null;
                }

                foreach ($response->json('locations') ?? [] as $loc) {
                    if (stripos($loc['name'] ?? '', 'Ohio') !== false) {
                        return (string) $loc['id'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Could not resolve preferred Shopify location', ['error' => $e->getMessage()]);
            }

            return null;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $levels
     */
    protected function resolveLocationIdFromLevels(array $levels): ?string
    {
        if ($levels === []) {
            return null;
        }

        $preferredId = $this->getPreferredShopifyLocationId();
        if ($preferredId !== null && $preferredId !== '') {
            foreach ($levels as $level) {
                if (isset($level['location_id']) && (string) $level['location_id'] === (string) $preferredId) {
                    return (string) $level['location_id'];
                }
            }

            Log::warning('Preferred Shopify location not present in inventory levels; using first level', [
                'preferred_location_id' => $preferredId,
                'available_location_ids' => array_column($levels, 'location_id'),
            ]);
        }

        return isset($levels[0]['location_id']) ? (string) $levels[0]['location_id'] : null;
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

    /**
     * Returns merged verification-adjustment data: product master + Shopify + inventory.
     * Optimized: single queries per source, eager loading, cached product master.
     */
    public function getViewVerificationAdjustmentData(Request $request)
    {
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim((string) $sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return $sku;
        };

        // Cache product master 5 min to reduce DB load on repeated page loads
        $productMasterData = Cache::remember('verification_adjustment_product_master', 300, function () {
            return ProductMaster::all();
        });

        $originalSkus = $productMasterData->pluck('sku')->filter()->unique()->values()->toArray();
        if (empty($originalSkus)) {
            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => [],
                'status' => 200,
            ]);
        }

        // Shopify rows keyed by product_master.sku (NBSP / unicode space safe)
        $shopifyData = ShopifySku::mapByProductSkus($originalSkus);

        // Fetch Amazon prices from amazon_datsheets table (gracefully handle if table doesn't exist)
        $amazonPrices = collect();
        try {
            if (\Schema::hasTable('amazon_datsheets')) {
                $amazonPrices = DB::table('amazon_datsheets')
                    ->whereIn('sku', $originalSkus)
                    ->select('sku', 'price')
                    ->get()
                    ->keyBy(function($item) use ($normalizeSku) {
                        return $normalizeSku($item->sku);
                    });
            }
        } catch (\Exception $e) {
            \Log::warning('Could not fetch Amazon prices from amazon_datsheets table: ' . $e->getMessage());
        }

        // Latest inventory per SKU (one query for ids, one with eager load - avoids N+1)
        $latestInventoryIds = Inventory::whereIn('sku', $originalSkus)
            ->select(DB::raw('MAX(id) as latest_id'))
            ->groupBy('sku')
            ->pluck('latest_id');

        $verifiedInventory = Inventory::whereIn('id', $latestInventoryIds)
            ->with('verifiedByUser')
            ->get()
            ->keyBy(fn($inv) => $normalizeSku($inv->sku));

        // Latest approved date per SKU: single query, group in PHP (no N+1)
        $latestApprovedHistory = Inventory::whereIn('sku', $originalSkus)
            ->where('is_approved', true)
            ->whereNotNull('approved_at')
            ->select('sku', 'approved_at', 'approved_by')
            ->orderByDesc('approved_at')
            ->get()
            ->groupBy(fn($item) => $normalizeSku($item->sku))
            ->map(fn($group) => $group->first());

        // OVERRIDE: Merge everything - return ALL SKUs from product_master without any filtering
        $data = $productMasterData->map(function ($item) use ($shopifyData, $verifiedInventory, $latestApprovedHistory, $amazonPrices, $normalizeSku) {
            $sku = $normalizeSku($item->sku ?? '');
            $values = $item->values;
            $lp = $values['lp'] ?? 0;

            $item->IS_PARENT = stripos($sku, 'PARENT') === 0;

            if (!$item->IS_PARENT) {
                $shopify = $shopifyData->get($item->sku);
                $inv = $verifiedInventory[$sku] ?? null;

                // Inventory from shopify_skus: written by syncLiveInventoryToDb() using Shopify Admin GraphQL
                // for the Ohio location only (available, committed, on_hand, unavailable, incoming).
                // ON_HAND uses Shopify on_hand; INV (Main-INV column) matches AVAILABLE_TO_SELL.
                if ($shopify) {
                    $item->AVAILABLE_TO_SELL = (int) ($shopify->available_to_sell ?? 0);
                    $item->COMMITTED = (int) ($shopify->committed ?? 0);
                    $item->UNAVAILABLE = (int) ($shopify->getAttribute('unavailable') ?? 0);
                    $item->INCOMING = (int) ($shopify->getAttribute('incoming') ?? 0);
                    $onHand = max(0, (int) ($shopify->on_hand ?? 0));
                    $item->ON_HAND = $onHand;
                    $item->INV = $item->AVAILABLE_TO_SELL;
                    $item->L30 = $shopify->quantity ?? 0;
                    $item->IMAGE_URL = $shopify->image_src ?? null;
                } else {
                    $item->INV = 0;
                    $item->L30 = 0;
                    $item->ON_HAND = 0;
                    $item->COMMITTED = 0;
                    $item->AVAILABLE_TO_SELL = 0;
                    $item->UNAVAILABLE = 0;
                    $item->INCOMING = 0;
                    $item->IMAGE_URL = null;
                }

                // Add Amazon price from amazon_datsheet table
                $amazonPrice = $amazonPrices[$sku] ?? null;
                $item->AMAZON_PRICE = $amazonPrice ? (float) $amazonPrice->price : 0;

                $item->VERIFIED_STOCK = $inv?->verified_stock;
                $item->REASON = $inv?->reason;
                $item->REMARKS = $inv?->remarks;
                $item->APPROVED = (bool) ($inv?->is_approved ?? false);
                $item->APPROVED_BY = $inv?->approved_by;
                $item->APPROVED_AT = $inv?->approved_at;

                // HISTORY column - Latest approved date from Adjustment History
                $latestHistory = $latestApprovedHistory[$sku] ?? null;
                $item->HISTORY = $latestHistory ? $latestHistory->approved_at : null;

                $item->is_verified = (bool) ($inv?->is_verified ?? false);
                $item->is_doubtful = (bool) ($inv?->is_doubtful ?? false);
                // Also set uppercase versions for compatibility
                $item->IS_VERIFIED = (bool) ($inv?->is_verified ?? false);
                $item->IS_DOUBTFUL = (bool) ($inv?->is_doubtful ?? false);

                // Get verified by user's first name
                $verifiedByUser = $inv?->verifiedByUser;
                if ($verifiedByUser && $verifiedByUser->name) {
                    // Extract first name from full name (assumes "First Last" format)
                    $nameParts = explode(' ', trim($verifiedByUser->name));
                    $item->VERIFIED_BY_FIRST_NAME = $nameParts[0] ?? $verifiedByUser->name;
                } else {
                    $item->VERIFIED_BY_FIRST_NAME = null;
                }

                $tz = 'America/New_York';
                $ta = $inv?->to_adjust;

                $adjustedQty = isset($ta) && is_numeric($ta) ? floatval($ta) : 0;
                $item->LOSS_GAIN = round($adjustedQty * $lp, 2);

                $approvedAtYmd = $inv?->approved_at
                    ? Carbon::parse($inv->approved_at)->timezone($tz)->format('Y-m-d')
                    : null;

                // OVERRIDE: Explicitly set IS_HIDE to 0 for all items to override any filtering
                $item->IS_HIDE = 0;
                $item->is_hide = 0;

                // Eloquent serializes dynamic attributes with broken snake_case keys (e.g. TO_ADJUST -> t_o__a_d_j_u_s_t).
                // Return a plain array with stable snake_case keys for the UI.
                return array_merge($item->toArray(), [
                    'to_adjust' => $ta,
                    'approved_at_ymd' => $approvedAtYmd,
                    'shopify_adjustment_status' => $inv?->shopify_adjustment_status,
                    'shopify_adjustment_error' => $inv?->shopify_adjustment_error,
                    'shopify_adjustment_succeeded_at' => $inv?->shopify_adjustment_succeeded_at?->toIso8601String(),
                ]);
            }

            // Parent rows
            // For parent rows, set default values
            $item->IS_VERIFIED = false;
            $item->is_verified = false;
            $item->IS_DOUBTFUL = false;
            $item->is_doubtful = false;
            $item->VERIFIED_BY_FIRST_NAME = null;
            $item->HISTORY = null;
            $item->AMAZON_PRICE = 0;

            // OVERRIDE: Explicitly set IS_HIDE to 0 for all items to override any filtering
            $item->IS_HIDE = 0;
            $item->is_hide = 0;

            return $item->toArray();
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
        // Make reason required only when is_approved is true
        $rules = [
            'sku' => 'nullable|string',
            'verified_stock' => 'required|numeric',
            'on_hand' => 'nullable|numeric',
            'remarks' => 'nullable|string',
            'is_approved' => 'required|boolean',
        ];
        
        // Only require reason when approving
        if ($request->input('is_approved', false)) {
            $rules['reason'] = 'required|string';
        } else {
            $rules['reason'] = 'nullable|string';
        }
        
        $validated = $request->validate($rules);

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

        $shopifyAdjustmentStatus = null;
        $shopifyAdjustmentError = null;

        // If approving with a non-zero delta, update Shopify before saving (persist row even on failure for Status column + retries)
        if ($validated['is_approved'] && $toAdjust != 0) {
            $startTime = time();

            try {
                $shopifyResult = $this->updateShopifyInventoryWithRetry($sku, (int) $toAdjust, 10);

                if ($shopifyResult['success']) {
                    $shopifyAdjustmentStatus = 'success';
                    Log::info('Shopify updated successfully', ['sku' => $sku, 'duration' => time() - $startTime]);
                } else {
                    $shopifyAdjustmentStatus = 'failed';
                    $shopifyAdjustmentError = $shopifyResult['error'] ?? 'Unknown error';
                    Log::error('Shopify update failed; saving record with failed status for retry', [
                        'sku' => $sku,
                        'error' => $shopifyAdjustmentError,
                    ]);
                }
            } catch (\Exception $e) {
                $shopifyAdjustmentStatus = 'failed';
                $shopifyAdjustmentError = $e->getMessage();
                Log::error('Shopify update exception; saving record with failed status', [
                    'sku' => $sku,
                    'error' => $shopifyAdjustmentError,
                ]);
            }
        } elseif ($validated['is_approved'] && $toAdjust == 0) {
            $shopifyAdjustmentStatus = 'na';
        }

        // Save to database
        try {
            DB::beginTransaction();

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
            $record->is_verified = true;  // Auto-verify when any row value is updated
            $record->verified_by = Auth::id();
            $record->shopify_adjustment_status = $shopifyAdjustmentStatus;
            $record->shopify_adjustment_error = $shopifyAdjustmentError
                ? Str::limit((string) $shopifyAdjustmentError, 65000, '')
                : null;
            $record->shopify_retry_count = 0;
            $record->shopify_adjustment_succeeded_at = null;
            if ($shopifyAdjustmentStatus === 'success') {
                $record->shopify_adjustment_succeeded_at = Carbon::now('America/New_York');
            }
            $record->save();

            DB::commit();

            $successYmd = $record->shopify_adjustment_succeeded_at
                ? Carbon::parse($record->shopify_adjustment_succeeded_at)->timezone('America/New_York')->format('Y-m-d')
                : null;

            // Determine message
            $message = 'Record saved successfully';
            if ($validated['is_approved']) {
                if ($toAdjust != 0) {
                    if ($shopifyAdjustmentStatus === 'success') {
                        $message = 'Shopify inventory updated successfully and saved to database';
                    } elseif ($shopifyAdjustmentStatus === 'failed') {
                        $message = 'Saved, but Shopify was not updated. Check Status — you can Retry or wait for automatic retries (every 1 min, up to 5).';
                    } else {
                        $message = 'Record saved successfully';
                    }
                } else {
                    $message = 'Record saved. Shopify was not changed because verified quantity matches on hand (no adjustment).';
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'shopify_updated' => $shopifyAdjustmentStatus === 'success',
                'shopify_adjustment_status' => $shopifyAdjustmentStatus,
                'data' => [
                    'sku' => $record->sku,
                    'inventory_id' => $record->id,
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
                    'shopify_adjustment_status' => $record->shopify_adjustment_status,
                    'shopify_adjustment_error' => $record->shopify_adjustment_error,
                    'shopify_retry_count' => (int) $record->shopify_retry_count,
                    'shopify_success_ymd' => $successYmd,
                    'shopify_adjustment_succeeded_at' => $record->shopify_adjustment_succeeded_at?->toIso8601String(),
                    'approved_at_ymd' => $record->approved_at
                        ? Carbon::parse($record->approved_at)->timezone('America/New_York')->format('Y-m-d')
                        : null,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to save inventory record', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Shopify updated but failed to save to database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick Shopify update with timeout protection
     */
    protected function updateShopifyInventoryQuick(string $sku, int $adjustment, int $maxSeconds = 25): array
    {
        $startTime = time();
        $normalizedSku = strtoupper(preg_replace('/\s+/u', ' ', trim($sku)));
        
        // Step 1: Find inventory_item_id (max 2 attempts)
        if (time() - $startTime > $maxSeconds) {
            return ['success' => false, 'error' => 'Timeout exceeded'];
        }

        $inventoryItemId = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $inventoryItemId = $this->findInventoryItemIdFast($normalizedSku);
                if ($inventoryItemId) break;
            } catch (\Exception $e) {
                if ($attempt >= 2) return ['success' => false, 'error' => 'SKU lookup failed'];
                sleep(1);
            }
        }

        if (!$inventoryItemId) {
            return ['success' => false, 'error' => 'SKU not found'];
        }

        // Step 2: Get location_id
        if (time() - $startTime > $maxSeconds) {
            return ['success' => false, 'error' => 'Timeout during location lookup'];
        }

        $locationId = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $locationId = $this->getLocationIdFast($inventoryItemId);
                if ($locationId) break;
            } catch (\Exception $e) {
                if ($attempt >= 2) return ['success' => false, 'error' => 'Location lookup failed'];
                sleep(1);
            }
        }

        if (!$locationId) {
            return ['success' => false, 'error' => 'Location not found'];
        }

        // Step 3: Adjust inventory
        if (time() - $startTime > $maxSeconds) {
            return ['success' => false, 'error' => 'Timeout before adjustment'];
        }

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->adjustInventoryFast($inventoryItemId, $locationId, $adjustment);
                return ['success' => true, 'message' => 'Updated'];
            } catch (\Exception $e) {
                if ($attempt >= 2) return ['success' => false, 'error' => 'Adjustment failed'];
                sleep(1);
            }
        }

        return ['success' => false, 'error' => 'Unknown error'];
    }

    protected function findInventoryItemIdFast(string $normalizedSku): ?string
    {
        $shopifyRow = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])->first();
        
        if ($shopifyRow && $shopifyRow->variant_id) {
            try {
                $response = $this->shopifyHttp()->timeout(8)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$shopifyRow->variant_id}.json");

                if ($response->successful()) {
                    return (string) $response->json('variant.inventory_item_id');
                }
            } catch (\Exception $e) {}
        }

        return $this->searchProductsForSkuFast($normalizedSku, 3);
    }

    protected function searchProductsForSkuFast(string $normalizedSku, int $maxPages = 3): ?string
    {
        $pageInfo = null;
        $currentPage = 0;

        do {
            $currentPage++;
            if ($currentPage > $maxPages) break;

            $queryParams = ['limit' => 250, 'fields' => 'id,variants'];
            if ($pageInfo) $queryParams['page_info'] = $pageInfo;

            try {
                $response = $this->shopifyHttp()->timeout(8)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

                if ($response->status() === 429 || !$response->successful()) {
                    throw new \Exception("HTTP {$response->status()}");
                }

                foreach ($response->json('products') ?? [] as $product) {
                    foreach ($product['variants'] ?? [] as $variant) {
                        if (strtoupper(preg_replace('/\s+/u', ' ', trim($variant['sku'] ?? ''))) === $normalizedSku) {
                            return (string) ($variant['inventory_item_id'] ?? '');
                        }
                    }
                }

                $linkHeader = $response->header('Link');
                $pageInfo = null;
                if ($linkHeader && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>;\s*rel="next"/', $linkHeader, $matches)) {
                    $pageInfo = $matches[1];
                }

            } catch (\Exception $e) {
                throw $e;
            }

        } while ($pageInfo && $currentPage < $maxPages);

        return null;
    }

    protected function getLocationIdFast(string $inventoryItemId): ?string
    {
        $response = $this->shopifyHttp()->timeout(8)
            ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
                'inventory_item_ids' => $inventoryItemId
            ]);

        if (!$response->successful()) {
            throw new \Exception("HTTP {$response->status()}");
        }

        $levels = $response->json('inventory_levels') ?? [];

        return $this->resolveLocationIdFromLevels($levels);
    }

    protected function adjustInventoryFast(string $inventoryItemId, string $locationId, int $adjustment): void
    {
        $response = $this->shopifyHttp()->timeout(8)
            ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'available_adjustment' => $adjustment,
            ]);

        if (!$response->successful()) {
            throw new \Exception("HTTP {$response->status()}");
        }
    }

    /**
     * Update Shopify inventory with comprehensive retry logic (for queue jobs)
     */
    protected function updateShopifyInventoryWithRetry(string $sku, int $adjustment, int $maxAttempts = 10): array
    {
        $normalizedSku = strtoupper(preg_replace('/\s+/u', ' ', trim($sku)));
        $inventoryItemId = null;
        $locationId = null;

        // Step 1: Find inventory_item_id with retry - KEEP TRYING UNTIL SUCCESS
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $inventoryItemId = $this->findInventoryItemId($normalizedSku);
                if ($inventoryItemId) {
                    Log::info('Inventory item ID found', ['sku' => $sku, 'attempt' => $attempt]);
                    break;
                }
                
                // If not found, wait and retry
                Log::info('SKU not found in current products, retrying...', ['sku' => $sku, 'attempt' => $attempt]);
                sleep(min(2 * $attempt, 10)); // Progressive wait: 2s, 4s, 6s... max 10s
                
            } catch (\Exception $e) {
                Log::warning('Attempt to find inventory_item_id failed, retrying', [
                    'sku' => $sku,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
                
                // If rate limited, wait longer
                if (strpos($e->getMessage(), '429') !== false) {
                    sleep(5);
                } else {
                    sleep(min(pow(2, $attempt - 1), 10));
                }
            }
        }

        if (!$inventoryItemId) {
            return ['success' => false, 'error' => 'SKU not found in Shopify: ' . $normalizedSku . '. Please verify the SKU exists in Shopify.'];
        }

        // Step 2: Get location_id with retry - KEEP TRYING UNTIL SUCCESS
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $locationId = $this->getLocationId($inventoryItemId);
                if ($locationId) {
                    Log::info('Location ID found', ['sku' => $sku, 'attempt' => $attempt]);
                    break;
                }
                
                Log::info('Location not found, retrying...', ['sku' => $sku, 'attempt' => $attempt]);
                sleep(min(2 * $attempt, 10));
                
            } catch (\Exception $e) {
                Log::warning('Attempt to get location_id failed, retrying', [
                    'sku' => $sku,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
                
                if (strpos($e->getMessage(), '429') !== false) {
                    sleep(5);
                } else {
                    sleep(min(pow(2, $attempt - 1), 10));
                }
            }
        }

        if (!$locationId) {
            return ['success' => false, 'error' => 'Location not found in Shopify. Please contact support.'];
        }

        // Step 3: Adjust inventory with retry - KEEP TRYING UNTIL SUCCESS
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->adjustInventory($inventoryItemId, $locationId, $adjustment);
                
                Log::info('Shopify inventory adjusted successfully', [
                    'sku' => $sku,
                    'adjustment' => $adjustment,
                    'attempt' => $attempt,
                    'total_attempts' => $attempt
                ]);
                
                return ['success' => true, 'message' => 'Inventory updated successfully'];
                
            } catch (\Exception $e) {
                $isLastAttempt = ($attempt >= $maxAttempts);
                
                Log::warning('Attempt to adjust inventory failed' . ($isLastAttempt ? ' (final attempt)' : ', retrying'), [
                    'sku' => $sku,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage()
                ]);
                
                if ($isLastAttempt) {
                    return ['success' => false, 'error' => 'Unable to update Shopify after ' . $maxAttempts . ' attempts. Please try again.'];
                }
                
                // Determine wait time based on error type
                if (strpos($e->getMessage(), '429') !== false) {
                    // Rate limited - wait longer
                    $waitTime = 5 + ($attempt * 2); // 7s, 9s, 11s...
                    Log::info('Rate limited, waiting before retry', ['wait_seconds' => $waitTime]);
                    sleep($waitTime);
                } elseif (strpos($e->getMessage(), 'timeout') !== false || strpos($e->getMessage(), 'timed out') !== false) {
                    // Timeout - wait a bit
                    sleep(3);
                } else {
                    // Other errors - exponential backoff
                    sleep(min(pow(2, $attempt - 1), 10));
                }
            }
        }

        return ['success' => false, 'error' => 'Unable to complete Shopify update. Please try again.'];
    }

    /**
     * Find inventory_item_id for a SKU
     */
    protected function findInventoryItemId(string $normalizedSku): ?string
    {
        // Try local database first (fast path)
        $shopifyRow = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])->first();
        
        if ($shopifyRow && $shopifyRow->variant_id) {
            try {
                $response = $this->shopifyHttp()
                    ->timeout(60)
                    ->retry(2, 1000)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$shopifyRow->variant_id}.json");

                if ($response->successful()) {
                    $inventoryItemId = $response->json('variant.inventory_item_id');
                    if ($inventoryItemId) {
                        Log::info('Found inventory_item_id from local cache', ['sku' => $normalizedSku]);
                        return (string) $inventoryItemId;
                    }
                } elseif ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After') ?? 3;
                    Log::info('Rate limited looking up variant, waiting', ['retry_after' => $retryAfter]);
                    sleep((int)$retryAfter + 2);
                    throw new \Exception('Rate limited (429), retry needed');
                }
            } catch (\Exception $e) {
                Log::debug('Variant lookup failed, will search products', [
                    'variant_id' => $shopifyRow->variant_id,
                    'error' => $e->getMessage()
                ]);
                // Continue to product search
            }
        }

        // Fallback: Search through products
        Log::info('Searching products for SKU', ['sku' => $normalizedSku]);
        return $this->searchProductsForSku($normalizedSku);
    }

    /**
     * Search products for SKU
     */
    protected function searchProductsForSku(string $normalizedSku): ?string
    {
        $pageInfo = null;
        $maxPages = 100; // Increased to search more products
        $currentPage = 0;

        do {
            $currentPage++;
            
            if ($currentPage > $maxPages) {
                Log::warning('Max pages reached during product search', [
                    'sku' => $normalizedSku,
                    'max_pages' => $maxPages
                ]);
                break;
            }

            $queryParams = ['limit' => 250, 'fields' => 'id,variants'];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            try {
                $response = $this->shopifyHttp()
                    ->timeout(60)
                    ->retry(2, 1000)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After') ?? 3;
                    Log::info('Rate limited during product search, waiting', ['retry_after' => $retryAfter]);
                    sleep((int)$retryAfter + 2);
                    continue; // Retry this page
                }

                if (!$response->successful()) {
                    Log::warning('Failed to fetch products page', ['status' => $response->status(), 'page' => $currentPage]);
                    sleep(2);
                    continue; // Try next iteration
                }

                $products = $response->json('products') ?? [];

                foreach ($products as $product) {
                    foreach ($product['variants'] ?? [] as $variant) {
                        $variantSku = strtoupper(preg_replace('/\s+/u', ' ', trim($variant['sku'] ?? '')));
                        
                        if ($variantSku === $normalizedSku) {
                            Log::info('SKU found in products', ['sku' => $normalizedSku, 'page' => $currentPage]);
                            return (string) ($variant['inventory_item_id'] ?? '');
                        }
                    }
                }

                // Handle pagination
                $linkHeader = $response->header('Link');
                $pageInfo = null;
                if ($linkHeader && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>;\s*rel="next"/', $linkHeader, $matches)) {
                    $pageInfo = $matches[1];
                }
                
            } catch (\Exception $e) {
                Log::warning('Exception during product search', ['page' => $currentPage, 'error' => $e->getMessage()]);
                sleep(2);
                continue;
            }

        } while ($pageInfo);

        return null;
    }

    /**
     * Get location_id for inventory item
     */
    protected function getLocationId(string $inventoryItemId): ?string
    {
        $response = $this->shopifyHttp()
            ->timeout(60)
            ->retry(3, 1000)
            ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
                'inventory_item_ids' => $inventoryItemId
            ]);

        // Handle rate limiting
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After') ?? 3;
            Log::info('Rate limited getting location, waiting', ['retry_after' => $retryAfter]);
            sleep((int)$retryAfter + 2);
            throw new \Exception('Rate limited (429), retry needed');
        }

        if (!$response->successful()) {
            Log::warning('Failed to fetch inventory levels', ['status' => $response->status()]);
            throw new \Exception("HTTP {$response->status()}");
        }

        $levels = $response->json('inventory_levels') ?? [];

        return $this->resolveLocationIdFromLevels($levels);
    }

    /**
     * Adjust inventory level
     */
    protected function adjustInventory(string $inventoryItemId, string $locationId, int $adjustment): void
    {
        $response = $this->shopifyHttp()
            ->timeout(60) // Increased timeout to 60 seconds
            ->retry(3, 1000) // Auto-retry 3 times with 1s delay
            ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'available_adjustment' => $adjustment,
            ]);

        // Handle rate limiting
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After') ?? 3;
            Log::info('Rate limited (429), waiting before retry', ['retry_after' => $retryAfter]);
            sleep((int)$retryAfter + 2);
            throw new \Exception('Rate limited (429), retry needed');
        }

        if (!$response->successful()) {
            $errorBody = $response->body();
            $errorMessage = "HTTP {$response->status()}";
            
            // Parse Shopify error if available
            $responseData = $response->json();
            if (isset($responseData['errors'])) {
                $errorMessage .= " - " . json_encode($responseData['errors']);
            } elseif ($errorBody) {
                $errorMessage .= " - {$errorBody}";
            }
            
            Log::warning('Shopify adjustment API returned non-success status', [
                'status' => $response->status(),
                'body' => $errorBody,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'adjustment' => $adjustment
            ]);
            
            throw new \Exception($errorMessage);
        }

        // Verify the response has expected structure
        $responseData = $response->json();
        if (!isset($responseData['inventory_level'])) {
            Log::warning('Shopify response missing inventory_level, but status was successful', [
                'response' => $responseData,
                'inventory_item_id' => $inventoryItemId
            ]);
            // Don't throw error if status was successful, Shopify might have different response format
        }

        // Log successful adjustment
        Log::info('Shopify inventory adjustment confirmed', [
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'adjustment' => $adjustment,
            'new_available' => $responseData['inventory_level']['available'] ?? 'unknown',
            'response_status' => $response->status()
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

    public function updateVerifiedStatus(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'is_verified' => 'required'
        ]);

        // Normalize SKU to match data loading logic
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse spaces
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace
            return $sku;
        };
        $normalizedSku = $normalizeSku($validated['sku']);

        // Convert various boolean formats to actual boolean
        $isVerified = filter_var($request->input('is_verified'), FILTER_VALIDATE_BOOLEAN);
        
        // Also accept string/numeric values
        if (is_string($request->input('is_verified'))) {
            $isVerified = in_array(strtolower($request->input('is_verified')), ['true', '1', 'yes', 'on']);
        } elseif (is_numeric($request->input('is_verified'))) {
            $isVerified = (bool)$request->input('is_verified');
        }

        // Get the latest inventory record for this SKU (same logic as data loading)
        // Use raw query to match normalized SKU (case-insensitive, space-normalized)
        $latestInventory = Inventory::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])
            ->orderBy('id', 'desc')
            ->first();

        $verifiedByFirstName = null;
        
        if ($latestInventory) {
            // Update the latest record
            $latestInventory->is_verified = $isVerified;
            // Only set verified_by when marking as verified (true)
            if ($isVerified && Auth::check()) {
                $latestInventory->verified_by = Auth::id();
            } elseif (!$isVerified) {
                // Clear verified_by when unverifying
                $latestInventory->verified_by = null;
            }
            $latestInventory->save();
            
            // Load the verified_by user relationship
            $latestInventory->load('verifiedByUser');
            $inventory = $latestInventory;
        } else {
            // SKU not found → Create new record using DB facade to handle AUTO_INCREMENT properly
            try {
                $id = DB::table('inventories')->insertGetId([
                    'sku' => $normalizedSku,
                    'is_verified' => $isVerified,
                    'verified_by' => ($isVerified && Auth::check()) ? Auth::id() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inventory = Inventory::find($id);
            } catch (\Exception $e) {
                // Fallback: Try using Eloquent create (might work if table structure is fixed)
                $inventory = new Inventory();
                $inventory->sku = $normalizedSku;
                $inventory->is_verified = $isVerified;
                $inventory->verified_by = ($isVerified && Auth::check()) ? Auth::id() : null;
                $inventory->save();
            }
            
            // Load the verified_by user relationship
            $inventory->load('verifiedByUser');
        }
        
        // Get the first name of the user who verified
        if ($inventory->verifiedByUser && $inventory->verifiedByUser->name) {
            $nameParts = explode(' ', trim($inventory->verifiedByUser->name));
            $verifiedByFirstName = $nameParts[0] ?? $inventory->verifiedByUser->name;
        }

        return response()->json([
            'success' => true,
            'verified_by_first_name' => $verifiedByFirstName
        ]);
    }

    public function updateDoubtfulStatus(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'is_doubtful' => 'required'
        ]);

        // Normalize SKU to match data loading logic
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse spaces
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace
            return $sku;
        };
        $normalizedSku = $normalizeSku($validated['sku']);

        // Convert various boolean formats to actual boolean
        $isDoubtful = filter_var($request->input('is_doubtful'), FILTER_VALIDATE_BOOLEAN);
        
        // Also accept string/numeric values
        if (is_string($request->input('is_doubtful'))) {
            $isDoubtful = in_array(strtolower($request->input('is_doubtful')), ['true', '1', 'yes', 'on']);
        } elseif (is_numeric($request->input('is_doubtful'))) {
            $isDoubtful = (bool)$request->input('is_doubtful');
        }

        // Get the latest inventory record for this SKU (same logic as data loading)
        // Use raw query to match normalized SKU (case-insensitive, space-normalized)
        $latestInventory = Inventory::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])
            ->orderBy('id', 'desc')
            ->first();

        if ($latestInventory) {
            // Update the latest record
            $latestInventory->is_doubtful = $isDoubtful;
            $latestInventory->save();
        } else {
            // SKU not found → Create new record using DB facade to handle AUTO_INCREMENT properly
            try {
                $id = DB::table('inventories')->insertGetId([
                    'sku' => $normalizedSku,
                    'is_doubtful' => $isDoubtful,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $latestInventory = Inventory::find($id);
            } catch (\Exception $e) {
                // Fallback: Try using Eloquent create (might work if table structure is fixed)
                $latestInventory = new Inventory();
                $latestInventory->sku = $normalizedSku;
                $latestInventory->is_doubtful = $isDoubtful;
                $latestInventory->save();
            }
        }

        return response()->json(['success' => true]);
    }

    public function saveRemark(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'remark' => 'nullable|string',
        ]);

        // Normalize SKU to match data loading logic
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse spaces
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace
            return $sku;
        };
        $normalizedSku = $normalizeSku($validated['sku']);
        $remark = $validated['remark'] ?? '';

        try {
            DB::beginTransaction();

            // Update the record that appears in Activity Log (latest approved for this SKU)
            // so the remark shows in the same entry the user sees when they click "View"
            $record = Inventory::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])
                ->where('is_approved', true)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if (!$record) {
                // No approved record: use latest record by id (e.g. for grid display)
                $record = Inventory::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])
                    ->orderByDesc('id')
                    ->first();
            }

            if ($record) {
                $record->remarks = $remark;
                $record->updated_at = Carbon::now('America/New_York');
                $record->is_verified = true;
                $record->verified_by = Auth::id();
                $record->save();
            } else {
                // No existing record: create one so remark is stored
                $record = new Inventory();
                $record->sku = $normalizedSku;
                $record->remarks = $remark;
                $record->created_at = Carbon::now('America/New_York');
                $record->updated_at = Carbon::now('America/New_York');
                $record->is_verified = true;
                $record->verified_by = Auth::id();
                $record->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Remark saved successfully',
                'data' => [
                    'sku' => $record->sku,
                    'remarks' => $record->remarks,
                    'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to save remark', [
                'sku' => $normalizedSku,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save remark: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getVerifiedStockActivityLog(Request $request)
    {
        $query = Inventory::where('type', null)->where('is_approved', true);
        
        // Apply reason filter - handle comma-separated values
        if ($request->filled('reason')) {
            $reason = trim($request->reason);
            if ($reason !== '') {
                // Check if reason exists in the field (handles comma-separated values)
                // Match exact value or as part of comma-separated list
                $query->where(function($q) use ($reason) {
                    $q->where('reason', '=', $reason)
                      ->orWhere('reason', 'LIKE', "{$reason},%")
                      ->orWhere('reason', 'LIKE', "%,{$reason},%")
                      ->orWhere('reason', 'LIKE', "%,{$reason}");
                });
            }
        }
        
        // Apply approved_by filter
        if ($request->filled('approved_by')) {
            $approvedBy = trim($request->approved_by);
            if ($approvedBy !== '') {
                $query->where('approved_by', 'LIKE', "%{$approvedBy}%");
            }
        }
        
        // Apply date range filter (using approved_at)
        $hasDateFrom = $request->filled('date_from');
        $hasDateTo = $request->filled('date_to');
        
        if ($hasDateFrom || $hasDateTo) {
            $query->whereNotNull('approved_at');
            
            if ($hasDateFrom) {
                $dateFrom = trim($request->date_from);
                if ($dateFrom !== '') {
                    try {
                        // Parse date and set to start of day in America/New_York, then convert to UTC
                        $dateFromCarbon = Carbon::createFromFormat('Y-m-d', $dateFrom, 'America/New_York')
                            ->startOfDay()
                            ->setTimezone('UTC');
                        $query->where('approved_at', '>=', $dateFromCarbon);
                    } catch (\Exception $e) {
                        Log::warning('Invalid date_from format', ['date' => $dateFrom, 'error' => $e->getMessage()]);
                    }
                }
            }
            
            if ($hasDateTo) {
                $dateTo = trim($request->date_to);
                if ($dateTo !== '') {
                    try {
                        // Parse date and set to end of day in America/New_York, then convert to UTC
                        $dateToCarbon = Carbon::createFromFormat('Y-m-d', $dateTo, 'America/New_York')
                            ->endOfDay()
                            ->setTimezone('UTC');
                        $query->where('approved_at', '<=', $dateToCarbon);
                    } catch (\Exception $e) {
                        Log::warning('Invalid date_to format', ['date' => $dateTo, 'error' => $e->getMessage()]);
                    }
                }
            }
        }
        
        // Order: latest update/approval first (approved_at then updated_at)
        $activityLogs = $query->orderByDesc('approved_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'verified_stock' => $item->verified_stock,
                    'to_adjust' => $item->to_adjust,
                    'loss_gain' => $item->loss_gain,
                    'reason' => $item->reason,
                    'remarks' => $item->remarks,
                    'approved_by' => $item->approved_by,
                    'approved_at' => $item->approved_at
                        ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('d M Y, h:i A')
                        : '-',
                    'updated_at' => $item->updated_at
                        ? Carbon::parse($item->updated_at)->timezone('America/New_York')->format('d M Y, h:i A')
                        : '-',
                    'is_ia' => (bool) $item->is_ia,
                ];
            });

        return response()->json(['data' => $activityLogs]);
    }

    public function lostGain()
    {
        return view('inventory-management.lost-gain');
    }

    public function getLostGainProductData(Request $request)
    {
        $user = Auth::user();
        
        if (!$user || !in_array($user->email, ['inventory@5core.com', 'president@5core.com', 'software2@5core.com'])) {
            abort(404, 'Page not available');
        }
        
        $skus = $request->input('skus', []);
        
        if (empty($skus)) {
            return response()->json(['data' => []]);
        }
        
        $productMasters = ProductMaster::whereIn('sku', $skus)->get();
        
        $productData = $productMasters->map(function ($product) {
            $values = is_array($product->Values) ? $product->Values : (is_string($product->Values) ? json_decode($product->Values, true) : []);
            if (!is_array($values)) {
                $values = [];
            }
            $lp = $values['lp'] ?? $product->lp ?? 0;

            // Unit lives in Values JSON (e.g. "Pair"); fall back to product_master.unit column.
            $unit = $values['unit'] ?? $product->unit ?? null;
            if ($unit !== null && $unit !== '' && is_scalar($unit)) {
                $unit = (string) $unit;
            } else {
                $unit = null;
            }

            return [
                'sku' => $product->sku,
                'parent' => $product->parent ?? '(No Parent)',
                'lp' => floatval($lp),
                'unit' => $unit,
            ];
        });
        
        return response()->json(['data' => $productData]);
    }

    public function updateIAStatus(Request $request)
    {
        $user = Auth::user();
        
        if (!$user || !in_array($user->email, ['inventory@5core.com', 'president@5core.com', 'software2@5core.com'])) {
            abort(404, 'Page not available');
        }

        $validated = $request->validate([
            'skus' => 'required|array',
            'is_ia' => 'required',
        ]);

        // Convert is_ia to boolean (handles "true"/"false" strings, 1/0, true/false)
        $isIA = filter_var($request->input('is_ia'), FILTER_VALIDATE_BOOLEAN);

        $updated = 0;
        $notFound = [];
        
        foreach ($validated['skus'] as $sku) {
            // Normalize SKU (trim and handle case-insensitive matching)
            $normalizedSku = trim($sku);
            
            // Try exact match first - update ALL matching records (not just the first one)
            $inventories = Inventory::where('sku', $normalizedSku)
                ->where('type', null)
                ->where('is_approved', true)
                ->get();
            
            // If not found, try case-insensitive match
            if ($inventories->isEmpty()) {
                $inventories = Inventory::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($normalizedSku)])
                    ->where('type', null)
                    ->where('is_approved', true)
                    ->get();
            }
            
            if ($inventories->isNotEmpty()) {
                // Update ALL matching records for this SKU
                $inventoryIds = [];
                foreach ($inventories as $inventory) {
                    $inventory->is_ia = $isIA;
                    $inventory->save();
                    $inventoryIds[] = $inventory->id;
                    $updated++;
                }
                
                Log::info('I&A status updated for all matching records', [
                    'sku' => $normalizedSku,
                    'is_ia' => $isIA,
                    'inventory_ids' => $inventoryIds,
                    'count' => count($inventoryIds)
                ]);
            } else {
                $notFound[] = $normalizedSku;
                Log::warning('I&A update failed: SKU not found', [
                    'sku' => $normalizedSku,
                    'is_ia' => $isIA
                ]);
            }
        }

        $message = "Updated {$updated} record(s).";
        if (count($notFound) > 0) {
            $message .= " " . count($notFound) . " SKU(s) not found: " . implode(', ', array_slice($notFound, 0, 5));
            if (count($notFound) > 5) {
                $message .= " and " . (count($notFound) - 5) . " more.";
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'updated' => $updated,
            'not_found' => $notFound
        ]);
    }


    public function viewInventory()
    {
        return view('inventory-management.view-inventory');
    }

    /**
     * SKU-wise activity log: returns all approved inventory records for a SKU,
     * ordered by most recent activity (updated_at) for easy-to-understand history.
     */
    public function getSkuWiseHistory(Request $request)
    {
        $sku = $request->input('sku');

        $query = Inventory::where('is_approved', true);

        if ($sku) {
            $normalizedSku = strtoupper(trim((string) $sku));
            $query->whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku]);
        }

        $activityLogs = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'sku' => $item->sku,
                    'verified_stock' => $item->verified_stock,
                    'to_adjust' => $item->to_adjust,
                    'on_hand' => $item->on_hand,
                    'reason' => $item->reason,
                    'remarks' => $item->remarks ?? '-',
                    'approved_by' => $item->approved_by,
                    'approved_at' => $item->approved_at
                        ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('d M Y, h:i A')
                        : '-',
                    'updated_at' => $item->updated_at
                        ? Carbon::parse($item->updated_at)->timezone('America/New_York')->format('d M Y, h:i A')
                        : '-',
                ];
            });

        return response()->json(['data' => $activityLogs]);
    }

    /**
     * Returns Shopify Admin URL for this SKU's inventory adjustment history.
     * Shopify does not expose adjustment rows via API; inventoryHistoryUrl is the supported link.
     */
    public function getShopifyInventoryHistoryUrl(Request $request)
    {
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        $normalized = strtoupper(preg_replace('/\s+/u', ' ', $sku));
        $row = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])->first();
        if (!$row || empty($row->variant_id)) {
            return response()->json([
                'success' => false,
                'message' => 'SKU not found in Shopify sync or variant ID missing. Run Shopify SKU sync first.',
            ], 404);
        }

        $base = $this->shopifyDomain;
        if (!str_starts_with($base, 'http')) {
            $base = 'https://' . ltrim($base, '/');
        }
        $base = rtrim($base, '/');
        $graphqlUrl = $base . '/admin/api/2025-01/graphql.json';

        $variantGid = str_starts_with((string) $row->variant_id, 'gid://')
            ? $row->variant_id
            : 'gid://shopify/ProductVariant/' . $row->variant_id;

        $query = <<<'GQL'
query GetInventoryHistoryUrl($variantId: ID!) {
  productVariant(id: $variantId) {
    inventoryItem {
      inventoryHistoryUrl
    }
  }
}
GQL;

        $response = Http::timeout(20)->withHeaders([
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => config('services.shopify.access_token') ?: $this->shopifyPassword,
        ])->post($graphqlUrl, [
            'query' => $query,
            'variables' => ['variantId' => $variantGid],
        ]);

        $historyUrl = null;
        if ($response->successful()) {
            $json = $response->json();
            if (!empty($json['errors'])) {
                Log::warning('Shopify GraphQL inventoryHistoryUrl errors', ['errors' => $json['errors']]);
            } else {
                $historyUrl = data_get($json, 'data.productVariant.inventoryItem.inventoryHistoryUrl');
            }
        }

        if (empty($historyUrl)) {
            $vid = (string) $row->variant_id;
            if (preg_match('/(\d+)\s*$/', $vid, $m)) {
                $variantNumericId = $m[1];
            } else {
                $variantNumericId = preg_replace('/\D/', '', $vid);
            }
            $restVariant = $this->shopifyHttp()->timeout(15)
                ->get($base . '/admin/api/2025-01/variants/' . $variantNumericId . '.json');
            $invItemId = data_get($restVariant->json(), 'variant.inventory_item_id');
            if ($invItemId) {
                $itemGid = 'gid://shopify/InventoryItem/' . $invItemId;
                $q2 = <<<'GQL'
query GetInvHistory($id: ID!) {
  inventoryItem(id: $id) {
    inventoryHistoryUrl
  }
}
GQL;
                $r2 = Http::timeout(20)->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => config('services.shopify.access_token') ?: $this->shopifyPassword,
                ])->post($graphqlUrl, [
                    'query' => $q2,
                    'variables' => ['id' => $itemGid],
                ]);
                if ($r2->successful()) {
                    $j2 = $r2->json();
                    $historyUrl = data_get($j2, 'data.inventoryItem.inventoryHistoryUrl');
                }
            }
        }

        if (empty($historyUrl)) {
            return response()->json([
                'success' => false,
                'message' => 'Could not load Shopify adjustment history link. Check API token (read_inventory) and variant.',
            ], 404);
        }

        return response()->json(['success' => true, 'url' => $historyUrl]);
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

    /**
     * Retry Shopify inventory adjustment for a verification row (manual or scheduled auto-retry).
     */
    public function retryVerificationShopifyAdjustment(Request $request)
    {
        $request->validate([
            'inventory_id' => 'required|integer|exists:inventories,id',
        ]);

        $record = Inventory::find($request->inventory_id);
        if (! $record || $record->shopify_adjustment_status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'This row is not waiting for a Shopify retry.',
            ], 400);
        }

        if ((int) $record->shopify_retry_count >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum retry attempts (5) reached. Update inventory in Shopify manually if needed.',
            ], 400);
        }

        if ((float) $record->to_adjust == 0) {
            return response()->json([
                'success' => false,
                'message' => 'No adjustment quantity to push.',
            ], 400);
        }

        $sku = trim((string) $record->sku);
        $result = $this->updateShopifyInventoryWithRetry($sku, (int) $record->to_adjust, 10);

        if ($result['success']) {
            $record->shopify_adjustment_status = 'success';
            $record->shopify_adjustment_error = null;
            $record->shopify_adjustment_succeeded_at = Carbon::now('America/New_York');
            $record->save();

            $successYmd = Carbon::parse($record->shopify_adjustment_succeeded_at)->timezone('America/New_York')->format('Y-m-d');

            return response()->json([
                'success' => true,
                'message' => 'Shopify inventory updated successfully.',
                'shopify_adjustment_status' => 'success',
                'shopify_retry_count' => (int) $record->shopify_retry_count,
                'shopify_success_ymd' => $successYmd,
            ]);
        }

        $record->increment('shopify_retry_count');
        $record->shopify_adjustment_error = Str::limit((string) ($result['error'] ?? 'Unknown error'), 65000, '');
        $record->shopify_adjustment_status = 'failed';
        $record->save();

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Shopify update failed.',
            'shopify_adjustment_status' => 'failed',
            'shopify_retry_count' => (int) $record->shopify_retry_count,
        ], 422);
    }

    /**
     * Retry failed Shopify inventory update
     */
    public function retryShopifyUpdate(Request $request)
    {
        $request->validate([
            'log_id' => 'required|integer'
        ]);

        $log = ShopifyInventoryLog::find($request->log_id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log entry not found'
            ], 404);
        }

        if ($log->status === 'success') {
            return response()->json([
                'success' => true,
                'message' => 'Inventory already updated successfully'
            ]);
        }

        if (!$log->shouldRetry()) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum retry attempts reached'
            ], 400);
        }

        // Reset status and dispatch new job
        $log->update([
            'status' => 'pending',
            'error_message' => null
        ]);

        UpdateShopifyInventoryJob::dispatch($log->id, $log->sku, $log->quantity_adjustment);

        return response()->json([
            'success' => true,
            'message' => 'Retry queued successfully'
        ]);
    }

    /**
     * Get Shopify update status
     */
    public function getShopifyUpdateStatus(Request $request)
    {
        $request->validate([
            'log_id' => 'required|integer'
        ]);

        $log = ShopifyInventoryLog::find($request->log_id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log entry not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sku' => $log->sku,
                'status' => $log->status,
                'attempt' => $log->attempt,
                'max_attempts' => $log->max_attempts,
                'error_message' => $log->error_message,
                'can_retry' => $log->shouldRetry(),
                'succeeded_at' => $log->succeeded_at?->toISOString(),
                'last_attempt_at' => $log->last_attempt_at?->toISOString(),
            ]
        ]);
    }

    /**
     * Get pending/failed Shopify updates
     */
    public function getPendingShopifyUpdates()
    {
        $pending = ShopifyInventoryLog::whereIn('status', ['pending', 'processing', 'failed'])
            ->where('attempt', '<', DB::raw('max_attempts'))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'sku' => $log->sku,
                    'adjustment' => $log->quantity_adjustment,
                    'status' => $log->status,
                    'attempt' => $log->attempt,
                    'error' => $log->error_message,
                    'created_at' => $log->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $pending
        ]);
    }

    /**
     * Export verification data to Google Sheets (Simple Apps Script approach)
     */
    public function exportToGoogleSheets(Request $request)
    {
        try {
            $data = $request->input('data', []);
            
            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data to export'
                ], 400);
            }

            // Google Apps Script Web App URL
            // You'll get this URL after deploying the Apps Script
            $appsScriptUrl = config('services.google_apps_script.export_url');
            
            if (empty($appsScriptUrl)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Apps Script URL not configured. Please add GOOGLE_APPS_SCRIPT_EXPORT_URL to your .env file. See GOOGLE_SHEETS_SIMPLE_SETUP.md for instructions.'
                ], 500);
            }

            // Get the stored spreadsheet ID (if configured to use the same sheet)
            $spreadsheetId = config('services.google_apps_script.verification_adjustment_sheet_id');

            // Prepare payload
            $payload = [
                'data' => $data,
                'sheetTitle' => 'Verification Adjustment',
                'spreadsheetId' => $spreadsheetId // Empty string means create new
            ];

            // Send data to Google Apps Script
            Log::info('Sending data to Google Apps Script', [
                'url' => $appsScriptUrl,
                'rows' => count($data),
                'spreadsheetId' => $spreadsheetId ?: 'new'
            ]);

            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($appsScriptUrl, $payload);

            if (!$response->successful()) {
                Log::error('Google Apps Script request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to export to Google Sheets. Please check your Apps Script deployment.'
                ], 500);
            }

            $result = $response->json();

            if (!isset($result['success']) || !$result['success']) {
                Log::error('Google Apps Script returned error', ['result' => $result]);
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Export failed'
                ], 500);
            }

            Log::info('Data exported successfully to Google Sheets', [
                'spreadsheetId' => $result['spreadsheetId'],
                'url' => $result['spreadsheetUrl'],
                'rows' => $result['rowsWritten'] ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data exported to Google Sheets successfully',
                'spreadsheetId' => $result['spreadsheetId'],
                'spreadsheetUrl' => $result['spreadsheetUrl'],
                'rowsWritten' => $result['rowsWritten'] ?? count($data)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to export to Google Sheets', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export to Google Sheets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply Adjust Quantity (AQ) updates from the Lost/Gain screen: net positive to_adjust against negatives up to total positive.
     */
    public function adjustLostGainQuantities(Request $request)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->email, ['inventory@5core.com', 'president@5core.com', 'software2@5core.com'])) {
            abort(404, 'Page not available');
        }

        $validated = $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.inventory_id' => 'required|integer|exists:inventories,id',
            'updates.*.to_adjust' => 'required|numeric',
            'updates.*.loss_gain' => 'nullable|numeric',
            'kind' => 'nullable|in:aq,av',
        ]);

        $kind = $validated['kind'] ?? 'aq';

        $batchUuid = (string) Str::uuid();
        $userId = Auth::id();
        $updated = 0;

        try {
            DB::beginTransaction();

            foreach ($validated['updates'] as $u) {
                $inv = Inventory::where('id', $u['inventory_id'])
                    ->whereNull('type')
                    ->where('is_approved', true)
                    ->lockForUpdate()
                    ->first();

                if (!$inv) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Inventory not found or not eligible for adjustment (ID '.$u['inventory_id'].'). Reload and try again.',
                    ], 422);
                }

                $oldTo = (int) $inv->to_adjust;
                $oldLoss = $inv->loss_gain;
                $newTo = (int) round((float) $u['to_adjust']);
                $newLoss = array_key_exists('loss_gain', $u) && $u['loss_gain'] !== null
                    ? round((float) $u['loss_gain'], 2)
                    : $inv->loss_gain;

                if ($oldTo === $newTo && (string) $oldLoss === (string) $newLoss) {
                    continue;
                }

                LostGainAqHistory::create([
                    'batch_uuid' => $batchUuid,
                    'kind' => $kind,
                    'user_id' => $userId,
                    'inventory_id' => $inv->id,
                    'sku' => $inv->sku,
                    'old_to_adjust' => $oldTo,
                    'new_to_adjust' => $newTo,
                    'old_loss_gain' => $oldLoss,
                    'new_loss_gain' => $newLoss,
                ]);

                $inv->to_adjust = $newTo;
                $inv->loss_gain = $newLoss;
                $inv->save();
                $updated++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Updated {$updated} record(s).",
                'batch_uuid' => $batchUuid,
                'updated' => $updated,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('adjustLostGainQuantities failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getLostGainAqHistory(Request $request)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->email, ['inventory@5core.com', 'president@5core.com', 'software2@5core.com'])) {
            abort(404, 'Page not available');
        }

        // Allow large lists for the History panel (newest first). Hard-capped for safety.
        $limit = min(10000, max(1, (int) $request->input('limit', 500)));

        $rows = LostGainAqHistory::query()
            ->with(['user' => function ($q) {
                $q->select('id', 'email');
            }])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($h) {
                return [
                    'batch_uuid' => $h->batch_uuid,
                    'kind' => $h->kind ?? 'aq',
                    'sku' => $h->sku,
                    'inventory_id' => $h->inventory_id,
                    'old_to_adjust' => $h->old_to_adjust,
                    'new_to_adjust' => $h->new_to_adjust,
                    'old_loss_gain' => $h->old_loss_gain,
                    'new_loss_gain' => $h->new_loss_gain,
                    'user_email' => $h->user ? $h->user->email : null,
                    'created_at' => $h->created_at
                        ? Carbon::parse($h->created_at)->timezone('America/New_York')->format('d M Y, h:i A')
                        : '-',
                ];
            });

        return response()->json(['data' => $rows]);
    }

}
