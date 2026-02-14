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
use App\Models\ShopifySku;
use App\Models\SkuRelationship;
use Illuminate\Support\Facades\DB;


class StockBalanceController extends Controller
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
     * Make Shopify API call with automatic retry on rate limit
     */
    private function shopifyApiCall($method, $url, $data = [], $maxRetries = 3)
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            
            $request = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->timeout(30);
            
            if ($method === 'GET') {
                $response = $request->get($url, $data);
            } else {
                $response = $request->post($url, $data);
            }
            
            // If rate limited, wait and retry
            if ($response->status() === 429 && $attempt < $maxRetries) {
                $waitTime = $attempt * 2; // 2, 4, 6 seconds
                Log::info("Rate limited, waiting {$waitTime}s before retry", [
                    'attempt' => $attempt,
                    'url' => $url
                ]);
                sleep($waitTime);
                continue;
            }
            
            return $response;
        }
        
        return $response;
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
     * Display the tabulator view
     */
    public function tabulatorView()
    {
        return view('inventory-management.stock-balance-tabulator');
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
        // Increase max execution time to prevent timeout
        set_time_limit(120);
        
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

            Log::info("Stock balance transfer request received", [
                'from_sku' => $fromSku,
                'to_sku' => $toSku,
                'from_qty' => $fromQty,
                'to_qty' => $toQty,
                'from_available_qty' => $request->from_available_qty,
                'to_available_qty' => $request->to_available_qty,
                'user' => Auth::user()->name ?? 'Unknown'
            ]);
            
            // Validate that available quantities are not negative
            if ($request->to_available_qty < 0) {
                Log::error("Negative inventory detected for TO SKU", [
                    'to_sku' => $toSku,
                    'to_available_qty' => $request->to_available_qty
                ]);
                
                return response()->json([
                    'error' => 'Invalid inventory data: TO SKU has negative inventory (' . $request->to_available_qty . '). Please check Shopify inventory for ' . $toSku,
                    'details' => 'Cannot transfer to SKU with negative inventory'
                ], 422);
            }
            
            if ($request->from_available_qty < 0) {
                Log::error("Negative inventory detected for FROM SKU", [
                    'from_sku' => $fromSku,
                    'from_available_qty' => $request->from_available_qty
                ]);
                
                return response()->json([
                    'error' => 'Invalid inventory data: FROM SKU has negative inventory (' . $request->from_available_qty . '). Please check Shopify inventory for ' . $fromSku,
                    'details' => 'Cannot transfer from SKU with negative inventory'
                ], 422);
            }

            // Helper function to get inventory_item_id and location_id using ShopifySku table
            $getInventoryInfo = function ($sku) {
                // Step 1: Get variant_id from local shopify_skus table
                $shopifySku = ShopifySku::where('sku', $sku)->first();
                
                if (!$shopifySku || !$shopifySku->variant_id) {
                    Log::error("SKU not found in shopify_skus table", [
                        'sku' => $sku,
                        'found_in_db' => $shopifySku ? 'yes' : 'no'
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'SKU not found in Shopify inventory',
                        'details' => "The SKU '{$sku}' was not found in your local Shopify inventory table. Please sync your Shopify data first."
                    ];
                }

                $variantId = $shopifySku->variant_id;

                // Step 2: Get inventory_item_id from variant
                usleep(500000); // Rate limit protection - 0.5s delay (allows 2 calls/second)
                $variantResponse = $this->shopifyApiCall(
                    'GET',
                    "https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$variantId}.json"
                );

                if (!$variantResponse->successful()) {
                    Log::error("Failed to fetch variant for SKU", [
                        'sku' => $sku,
                        'variant_id' => $variantId,
                        'status' => $variantResponse->status(),
                        'body' => $variantResponse->body()
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Failed to fetch product from Shopify',
                        'details' => "Error " . $variantResponse->status() . " - Could not retrieve product details for SKU: {$sku}"
                    ];
                }

                $variant = $variantResponse->json('variant');
                $inventoryItemId = $variant['inventory_item_id'] ?? null;
                
                if (!$inventoryItemId) {
                    return [
                        'success' => false,
                        'error' => 'Invalid product data',
                        'details' => "Could not find inventory item ID for SKU: {$sku}"
                    ];
                }

                // Step 3: Get location_id from inventory levels
                usleep(500000); // Rate limit protection - 0.5s delay (allows 2 calls/second)
                $levelsResponse = $this->shopifyApiCall(
                    'GET',
                    "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json",
                    ['inventory_item_ids' => $inventoryItemId]
                );

                if (!$levelsResponse->successful()) {
                    Log::error("Failed to fetch inventory levels for SKU", [
                        'sku' => $sku,
                        'inventory_item_id' => $inventoryItemId,
                        'status' => $levelsResponse->status()
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Failed to get current inventory level',
                        'details' => "Error " . $levelsResponse->status() . " - Could not fetch inventory levels for SKU: {$sku}"
                    ];
                }

                $levels = $levelsResponse->json('inventory_levels');
                $locationId = $levels[0]['location_id'] ?? null;
                $availableQty = $levels[0]['available'] ?? 0;

                if (!$locationId) {
                    return [
                        'success' => false,
                        'error' => 'Shopify location not found',
                        'details' => "Could not determine location for SKU: {$sku}"
                    ];
                }

                Log::info("Got inventory info for SKU", [
                    'sku' => $sku,
                    'variant_id' => $variantId,
                    'inventory_item_id' => $inventoryItemId,
                    'location_id' => $locationId,
                    'available_qty' => $availableQty
                ]);

                return [
                    'success' => true,
                    'inventory_item_id' => $inventoryItemId,
                    'location_id' => $locationId,
                    'available' => $availableQty,
                ];
            };

            // Step 1: Get inventory info and decrease from 'from_sku'
            $fromInfo = $getInventoryInfo($fromSku);
            
            if (!$fromInfo['success']) {
                return response()->json([
                    'error' => $fromInfo['error'],
                    'details' => $fromInfo['details']
                ], 404);
            }

            // Validate that there's enough inventory available
            $currentAvailable = $fromInfo['available'] ?? 0;
            if ($currentAvailable < $fromQty) {
                Log::warning("Insufficient inventory for transfer", [
                    'from_sku' => $fromSku,
                    'requested_qty' => $fromQty,
                    'available_qty' => $currentAvailable
                ]);
                
                return response()->json([
                    'error' => 'Insufficient Inventory',
                    'details' => "Cannot transfer {$fromQty} units from SKU: {$fromSku}<br><br>" .
                                "<strong>Current Available:</strong> {$currentAvailable} units<br>" .
                                "<strong>Requested Transfer:</strong> {$fromQty} units<br><br>" .
                                "You need <strong>" . ($fromQty - $currentAvailable) . " more units</strong> to complete this transfer."
                ], 400);
            }

            usleep(500000); // Rate limit protection - 0.5s delay (allows 2 calls/second)
            $decrease = $this->shopifyApiCall(
                'POST',
                "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json",
                [
                    'inventory_item_id' => $fromInfo['inventory_item_id'],
                    'location_id' => $fromInfo['location_id'],
                    'available_adjustment' => -$fromQty,
                ]
            );

            if (!$decrease->successful()) {
                $responseBody = $decrease->json();
                $shopifyError = $responseBody['errors'] ?? $decrease->body();
                
                Log::error("Failed to deduct inventory for SKU", [
                    'sku' => $fromSku,
                    'status' => $decrease->status(),
                    'response' => $decrease->body(),
                    'requested_qty' => $fromQty,
                    'available_before_attempt' => $currentAvailable
                ]);
                
                return response()->json([
                    'error' => 'Failed to deduct inventory from Shopify',
                    'details' => "Could not decrease stock for SKU: {$fromSku}<br><br>" .
                                "<strong>Available Quantity:</strong> {$currentAvailable} units<br>" .
                                "<strong>Attempted Deduction:</strong> {$fromQty} units<br><br>" .
                                "<strong>Shopify Error:</strong> " . (is_string($shopifyError) ? $shopifyError : json_encode($shopifyError))
                ], 500);
            }

            Log::info("Successfully decreased inventory", [
                'sku' => $fromSku,
                'adjustment' => -$fromQty,
                'response' => $decrease->json()
            ]);

            // Step 2: Get inventory info and increase to 'to_sku'
            usleep(500000); // Rate limit protection - 0.5s delay before processing second SKU
            $toInfo = $getInventoryInfo($toSku);
            
            if (!$toInfo['success']) {
                return response()->json([
                    'error' => $toInfo['error'],
                    'details' => $toInfo['details']
                ], 404);
            }

            usleep(500000); // Rate limit protection - 0.5s delay (allows 2 calls/second)
            $increase = $this->shopifyApiCall(
                'POST',
                "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json",
                [
                    'inventory_item_id' => $toInfo['inventory_item_id'],
                    'location_id' => $toInfo['location_id'],
                    'available_adjustment' => $toQty,
                ]
            );

            if (!$increase->successful()) {
                Log::error("Failed to increase inventory for SKU", [
                    'sku' => $toSku,
                    'status' => $increase->status(),
                    'response' => $increase->body()
                ]);
                
                // Try to rollback the first adjustment
                Log::warning("Attempting to rollback first adjustment", [
                    'from_sku' => $fromSku,
                    'rollback_qty' => $fromQty
                ]);
                
                usleep(500000); // 0.5s delay for rollback
                $rollback = $this->shopifyApiCall(
                    'POST',
                    "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json",
                    [
                        'inventory_item_id' => $fromInfo['inventory_item_id'],
                        'location_id' => $fromInfo['location_id'],
                        'available_adjustment' => $fromQty, // Add back
                    ]
                );
                
                if ($rollback->successful()) {
                    Log::info("Successfully rolled back first adjustment", ['sku' => $fromSku]);
                    return response()->json([
                        'error' => 'Failed to increase inventory in Shopify',
                        'details' => "Could not increase stock for SKU: $toSku. Previous deduction has been rolled back."
                    ], 500);
                } else {
                    Log::error("Failed to rollback first adjustment", [
                        'sku' => $fromSku,
                        'status' => $rollback->status()
                    ]);
                    return response()->json([
                        'error' => 'Failed to increase inventory in Shopify',
                        'details' => "Could not increase stock for SKU: $toSku. WARNING: $fromSku was decreased by $fromQty but rollback failed!"
                    ], 500);
                }
            }

            Log::info("Successfully increased inventory", [
                'sku' => $toSku,
                'adjustment' => $toQty,
                'response' => $increase->json()
            ]);

            // Step 3: Only save to database after both Shopify updates succeed
            try {
                DB::beginTransaction();
                
                // Cap DIL percent values to prevent database overflow
                // Database column is decimal(5,2), range: -999.99 to 999.99
                // We'll clamp between -999.99 and 100 (since DIL % shouldn't exceed 100)
                $fromDilPercent = $request->from_dil_percent;
                if ($fromDilPercent > 100) {
                    Log::warning("from_dil_percent exceeds 100%, capping it", [
                        'original' => $fromDilPercent,
                        'capped' => 100
                    ]);
                    $fromDilPercent = 100;
                } elseif ($fromDilPercent < -999.99) {
                    Log::warning("from_dil_percent below minimum, capping it", [
                        'original' => $fromDilPercent,
                        'capped' => -999.99
                    ]);
                    $fromDilPercent = -999.99;
                }
                
                $toDilPercent = $request->to_dil_percent;
                if ($toDilPercent > 100) {
                    Log::warning("to_dil_percent exceeds 100%, capping it", [
                        'original' => $toDilPercent,
                        'capped' => 100
                    ]);
                    $toDilPercent = 100;
                } elseif ($toDilPercent < -999.99) {
                    Log::warning("to_dil_percent below minimum, capping it", [
                        'original' => $toDilPercent,
                        'capped' => -999.99
                    ]);
                    $toDilPercent = -999.99;
                }
                
                $dataToInsert = [
                    'from_parent_name'     => $request->from_parent_name,
                    'from_sku'             => $fromSku,
                    'from_dil_percent'     => $fromDilPercent,
                    'from_available_qty'   => $request->from_available_qty,
                    'from_adjust_qty'      => $fromQty,

                    'to_parent_name'       => $request->to_parent_name,
                    'to_sku'               => $toSku,
                    'to_dil_percent'       => $toDilPercent,
                    'to_available_qty'     => $request->to_available_qty,
                    'to_adjust_qty'        => $toQty,

                    'transferred_by'       => Auth::user()->name ?? 'N/A',
                    'transferred_at'       => Carbon::now('America/New_York'),
                ];
                
                Log::info("Attempting to save stock balance to database", [
                    'data' => $dataToInsert
                ]);
                
                StockBalance::create($dataToInsert);
                
                DB::commit();
                
                Log::info("Stock balance transfer saved to database", [
                    'from_sku' => $fromSku,
                    'to_sku' => $toSku
                ]);
                
            } catch (\Exception $dbException) {
                DB::rollBack();
                
                Log::error("Failed to save stock balance to database after successful Shopify updates", [
                    'from_sku' => $fromSku,
                    'to_sku' => $toSku,
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                    'line' => $dbException->getLine(),
                    'file' => $dbException->getFile()
                ]);
                
                return response()->json([
                    'error' => 'Shopify updated successfully but failed to save to database',
                    'details' => "Inventory was transferred in Shopify successfully.<br><br>" .
                                "<strong>Database Error:</strong> " . $dbException->getMessage() . "<br><br>" .
                                "<strong>Transfer Details:</strong><br>" .
                                "From: $fromSku (-$fromQty)<br>" .
                                "To: $toSku (+$toQty)<br><br>" .
                                "<em>Note: The inventory has been updated in Shopify but the record was not saved to your local database.</em>",
                    'shopify_updated' => true,
                    'db_error' => $dbException->getMessage()
                ], 500);
            }

            return response()->json([
                'message' => '✓ SHOPIFY - Stock transferred successfully and saved to database'
            ]);

        } catch (\Exception $e) {
            Log::error("Stock transfer failed: " . $e->getMessage(), [
                'from_sku' => $request->from_sku ?? 'N/A',
                'to_sku' => $request->to_sku ?? 'N/A',
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'error' => 'Error storing stock balance',
                'details' => $e->getMessage()
            ], 500);
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

    /**
     * Get inventory data for the inventory table
     */
    public function getInventoryData()
    {
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return $sku;
        };

        // Fetch product master
        $productMasterData = ProductMaster::all();

        // Get SKUs
        $skus = $productMasterData->pluck('sku')
            ->filter()
            ->unique()
            ->map(fn($sku) => $normalizeSku($sku))
            ->toArray();

        // Fetch Shopify data from local DB (shopify_skus)
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        // Fetch inventory action data
        // Get all inventory records and normalize their SKUs for matching
        // Since there might be multiple records per SKU, get the latest one for each normalized SKU
        $allInventoryActions = Inventory::all();
        $inventoryActions = collect();
        
        foreach ($allInventoryActions as $inv) {
            $normalizedInvSku = $normalizeSku($inv->sku);
            // Only include if this normalized SKU is in our list
            if (in_array($normalizedInvSku, $skus)) {
                // If we already have this normalized SKU, keep the one with the latest update
                if (!$inventoryActions->has($normalizedInvSku)) {
                    $inventoryActions[$normalizedInvSku] = $inv;
                } else {
                    $existing = $inventoryActions[$normalizedInvSku];
                    if ($inv->updated_at > $existing->updated_at) {
                        $inventoryActions[$normalizedInvSku] = $inv;
                    }
                }
            }
        }
        
        // Fetch last stock balance history for each SKU
        $lastStockBalances = collect();
        foreach ($skus as $sku) {
            // Get the latest stock balance where this SKU is either the FROM or TO SKU
            $lastBalance = StockBalance::where(function($query) use ($sku) {
                $query->where('from_sku', $sku)
                      ->orWhere('to_sku', $sku);
            })
            ->orderBy('transferred_at', 'desc')
            ->first();
            
            if ($lastBalance) {
                $lastStockBalances[$sku] = $lastBalance;
            }
        }

        // Merge everything
        $data = $productMasterData->map(function ($item) use ($shopifyData, $inventoryActions, $lastStockBalances, $normalizeSku) {
            $sku = $normalizeSku($item->sku ?? '');
            $shopify = $shopifyData[$sku] ?? null;
            $inventory = $inventoryActions[$sku] ?? null;
            $lastBalance = $lastStockBalances[$sku] ?? null;

            $inv = $shopify->inv ?? 0;
            $l30 = $shopify->quantity ?? 0;
            // Calculate DIL following verification-adjustment pattern:
            // If INV > 0 and L30 === 0, then DIL = 0
            // If INV is negative or zero, set DIL to 0 to prevent extreme values
            // Otherwise, if INV > 0, then DIL = L30 / INV
            if ($inv > 0 && $l30 === 0) {
                $dil = 0;
            } else if ($inv > 0) {
                $dil = $l30 / $inv;
                // Cap DIL to prevent extreme values (max 100x or -100x ratio)
                if ($dil > 100) {
                    $dil = 100;
                } else if ($dil < -100) {
                    $dil = -100;
                }
            } else {
                // If inventory is 0 or negative, set DIL to 0
                $dil = 0;
            }
            
            // Format last update history
            $lastUpdate = null;
            if ($lastBalance) {
                $direction = '';
                $otherSku = '';
                $qty = 0;
                
                if ($lastBalance->from_sku === $item->sku) {
                    $direction = 'OUT';
                    $otherSku = $lastBalance->to_sku;
                    $qty = $lastBalance->from_adjust_qty;
                } else {
                    $direction = 'IN';
                    $otherSku = $lastBalance->from_sku;
                    $qty = $lastBalance->to_adjust_qty;
                }
                
                $transferredAt = $lastBalance->transferred_at 
                    ? Carbon::parse($lastBalance->transferred_at)->timezone('America/New_York')->format('m/d/y H:i')
                    : '';
                
                $lastUpdate = [
                    'direction' => $direction,
                    'other_sku' => $otherSku,
                    'qty' => $qty,
                    'date' => $transferredAt,
                    'by' => $lastBalance->transferred_by
                ];
            }

            return [
                'IMAGE_URL' => $shopify->image_src ?? null,
                'Parent' => $item->parent ?? '(No Parent)',
                'SKU' => $item->sku ?? '',
                'INV' => $inv,
                'SOLD' => $l30,
                'DIL' => $dil,
                'ACTION' => $inventory->action ?? null,
                'LAST_UPDATE' => $lastUpdate,
            ];
        })->filter(function ($item) {
            // Filter out items with no SKU
            return !empty($item['SKU']);
        });

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $data->values(),
            'status' => 200
        ]);
    }

    /**
     * Update action for a SKU
     */
    public function updateAction(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'action' => 'nullable|string|in:NRB,RB',
        ]);

        try {
            // Normalize SKU to match the same normalization used in getInventoryData
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $sku = $normalizeSku($request->sku);
            $action = $request->action;

            // Find or create inventory record - use the normalized SKU
            // Since SKU is not unique anymore, we need to update all records with this SKU or get the latest one
            $inventory = Inventory::where('sku', $sku)->first();
            
            if (!$inventory) {
                // Create new record if it doesn't exist
                $inventory = new Inventory();
                $inventory->sku = $sku;
            }
            
            $inventory->action = $action;
            $inventory->save();

            Log::info("Action updated successfully", [
                'sku' => $sku,
                'action' => $action
            ]);

            return response()->json([
                'message' => 'Action updated successfully',
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update action: " . $e->getMessage(), [
                'sku' => $request->sku ?? 'N/A',
                'action' => $request->action ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error updating action',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get relationships for a SKU
     */
    public function getRelationships(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
        ]);

        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $sku = $normalizeSku($request->sku);

            $relationships = SkuRelationship::where('source_sku', $sku)
                ->pluck('related_sku')
                ->toArray();

            return response()->json([
                'message' => 'Relationships fetched successfully',
                'data' => $relationships,
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get relationships: " . $e->getMessage(), [
                'sku' => $request->sku ?? 'N/A',
            ]);
            
            return response()->json([
                'error' => 'Error fetching relationships',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add relationships for a SKU
     */
    public function addRelationships(Request $request)
    {
        $request->validate([
            'source_sku' => 'required|string',
            'related_skus' => 'required|array',
            'related_skus.*' => 'required|string',
        ]);

        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $sourceSku = $normalizeSku($request->source_sku);
            $relatedSkus = array_map($normalizeSku, $request->related_skus);

            // Remove duplicates and the source SKU itself
            $relatedSkus = array_unique($relatedSkus);
            $relatedSkus = array_filter($relatedSkus, function($sku) use ($sourceSku) {
                return $sku !== $sourceSku;
            });

            $added = 0;
            foreach ($relatedSkus as $relatedSku) {
                try {
                    SkuRelationship::firstOrCreate([
                        'source_sku' => $sourceSku,
                        'related_sku' => $relatedSku,
                    ]);
                    $added++;
                } catch (\Exception $e) {
                    // Skip if duplicate or other error
                    Log::warning("Failed to add relationship", [
                        'source_sku' => $sourceSku,
                        'related_sku' => $relatedSku,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'message' => "Successfully added {$added} relationship(s)",
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to add relationships: " . $e->getMessage(), [
                'source_sku' => $request->source_sku ?? 'N/A',
            ]);
            
            return response()->json([
                'error' => 'Error adding relationships',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a relationship
     */
    public function deleteRelationship(Request $request)
    {
        $request->validate([
            'source_sku' => 'required|string',
            'related_sku' => 'required|string',
        ]);

        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $sourceSku = $normalizeSku($request->source_sku);
            $relatedSku = $normalizeSku($request->related_sku);

            $deleted = SkuRelationship::where('source_sku', $sourceSku)
                ->where('related_sku', $relatedSku)
                ->delete();

            return response()->json([
                'message' => 'Relationship deleted successfully',
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete relationship: " . $e->getMessage(), [
                'source_sku' => $request->source_sku ?? 'N/A',
                'related_sku' => $request->related_sku ?? 'N/A',
            ]);
            
            return response()->json([
                'error' => 'Error deleting relationship',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all SKUs for autocomplete
     */
    public function getSkusForAutocomplete(Request $request)
    {
        // Accept both 'search' and 'term' parameters (Select2 uses 'term')
        $search = $request->get('search', $request->get('term', ''));
        
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return $sku;
        };

        $query = ProductMaster::select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if ($search) {
            $normalizedSearch = $normalizeSku($search);
            $query->whereRaw("UPPER(TRIM(REPLACE(REPLACE(sku, '\n', ' '), '\r', ' '))) LIKE ?", ['%' . $normalizedSearch . '%']);
        }

        $skus = $query->distinct()
            ->orderBy('sku', 'asc')
            ->limit(100)
            ->pluck('sku')
            ->map(function($sku) use ($normalizeSku) {
                return [
                    'id' => $sku,
                    'text' => $sku,
                    'normalized' => $normalizeSku($sku)
                ];
            })
            ->values();

        return response()->json([
            'results' => $skus,
            'status' => 200
        ]);
    }

    /**
     * Get the most recent history for a from_sku to auto-fill the form
     */
    public function getRecentHistory(Request $request)
    {
        $request->validate([
            'from_sku' => 'required|string',
        ]);

        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $fromSku = $normalizeSku($request->from_sku);

            // Get the most recent history for this from_sku
            $history = StockBalance::where('from_sku', $fromSku)
                ->orderBy('transferred_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$history) {
                return response()->json([
                    'message' => 'No history found for this SKU',
                    'data' => null,
                    'status' => 200
                ]);
            }

            return response()->json([
                'message' => 'History fetched successfully',
                'data' => [
                    'from_parent_name' => $history->from_parent_name,
                    'from_sku' => $history->from_sku,
                    'from_dil_percent' => $history->from_dil_percent,
                    'from_available_qty' => $history->from_available_qty,
                    'from_adjust_qty' => $history->from_adjust_qty,
                    'to_parent_name' => $history->to_parent_name,
                    'to_sku' => $history->to_sku,
                    'to_dil_percent' => $history->to_dil_percent,
                    'to_available_qty' => $history->to_available_qty,
                    'to_adjust_qty' => $history->to_adjust_qty,
                ],
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get recent history: " . $e->getMessage(), [
                'from_sku' => $request->from_sku ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error fetching history',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
