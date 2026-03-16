<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Http\Controllers\ShopifyApiInventoryController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ApiController;
use App\Models\IncomingData;
use App\Models\IncomingOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;



class IncomingController extends Controller
{

    protected $shopifyDomain;
    protected $shopifyApiKey;
    protected $shopifyPassword;

    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
        $this->shopifyDomain = config('services.shopify.store_url');
        $this->shopifyApiKey =  '9d5c067dd4bcaf83a72137dddab72a4d';  //config('services.shopify.api_key');
        $this->shopifyPassword =  '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'; //config('services.shopify.password');
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::select('id', 'name')->get();
        $skus = ProductMaster::select('id','parent','sku')->get();

        return view('inventory-management.incoming-view', compact('warehouses', 'skus'));
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
    // public function store(Request $request)
    // {   

    //     $request->validate([
    //         'sku' => 'required|string',
    //         'parent' => 'required|string',
    //         'qty' => 'required|integer|min:1',
    //         'warehouse_id' => 'required|exists:warehouses,id',
    //         'reason' => 'required|string',
    //         'date' => 'nullable',
    //     ]);

    //     $sku = trim($request->sku);
    //     $incomingQty = (int) $request->qty;

    //     try {

    //         $normalizedSku = strtoupper(preg_replace('/\s+/u', ' ', $sku));

    //         // Use same logic as updateVerifiedStock to get inventory_item_id
    //         $inventoryItemId = null;
    //         $pageInfo = null;

    //         do {
    //             $queryParams = ['limit' => 250];
    //             if ($pageInfo) $queryParams['page_info'] = $pageInfo;
                

    //             // $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //             //     ->get("https://5-core.myshopify.com/admin/api/2025-01/products.json", $queryParams);

    //             try {

    //             // $response = Http::withBasicAuth(config('services.shopify.api_key'),config('services.shopify.password') 
    //             // )->get("https://{$shopifyDomain}/admin/api/2025-01/products.json",$query_p);

    //             $response = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //                 ->get("https://5-core.myshopify.com/admin/api/2025-01/products.json", $queryParams);


    //                 // $response = Http::withHeaders([
    //                 //     'X-Shopify-Access-Token' => $accessToken,
    //                 // ])->get("https://{$shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

    //                 // dd($response);

    //             } catch (\Exception $e) {
    //                 Log::error("Incoming store failed for SKU $sku: " . $e->getMessage(), [
    //                     'trace' => $e->getTraceAsString()
    //                 ]);
    //                 return response()->json(['error' => 'Something went wrong.'], 500);
    //             }

    //             // if (!$response->successful()) {
    //             //     Log::error('Failed to fetch products from Shopify', $response->json());
    //             //     return response()->json(['error' => 'Failed to fetch products from Shopify'], 500);
    //             // }

    //             $products = $response->json('products');

    //             foreach ($products as $product) {
    //                 foreach ($product['variants'] as $variant) {
    //                     $variantSku = strtoupper(preg_replace('/\s+/u', ' ', trim($variant['sku'] ?? '')));

    //                     if ($variantSku === $normalizedSku) {
    //                         $inventoryItemId = $variant['inventory_item_id'];
    //                         break 2;
    //                     }
    //                 }
    //             }



    //             // Pagination support
    //             $linkHeader = $response->header('Link');
    //             $pageInfo = null;
    //             if ($linkHeader && preg_match('/<([^>]+page_info=([^&>]+)[^>]*)>; rel="next"/', $linkHeader, $matches)) {
    //                 $pageInfo = $matches[2];
    //             }
    //         } while (!$inventoryItemId && $pageInfo);

    //         if (!$inventoryItemId) {
    //             Log::error("Inventory Item ID not found for SKU: $sku");
    //             return response()->json(['error' => 'SKU not found in Shopify'], 404);
    //         }

    //         try {

    //         //  Get location ID from inventory_levels
    //         $invLevelResponse = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //             ->get("https://5-core.myshopify.com/admin/api/2025-01/inventory_levels.json", [
    //                 'inventory_item_ids' => $inventoryItemId,
    //             ]);

    //         } catch (\Exception $e) {
    //             Log::error("Incoming store failed for SKU $sku: " . $e->getMessage(), [
    //                 'trace' => $e->getTraceAsString()
    //             ]);
    //             return response()->json(['error' => 'inventory issuse.'], 500);
    //         }

    //         // $invLevelResponse = Http::withHeaders([
    //         //     'X-Shopify-Access-Token' => $accessToken,
    //         // ])->get("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
    //         //     'inventory_item_ids' => $inventoryItemId,
    //         // ]);

    //         if (!$invLevelResponse->successful()) {
    //             Log::error('Failed to fetch inventory levels', $invLevelResponse->json());
    //             return response()->json(['error' => 'Failed to fetch inventory levels'], 500);
    //         }

    //         $levels = $invLevelResponse->json('inventory_levels');
    //         $locationId = $levels[0]['location_id'] ?? null;

    //         if (!$locationId) {
    //             Log::error("Location ID not found for inventory item: $inventoryItemId");
    //             return response()->json(['error' => 'Location ID not found'], 404);
    //         }

    //         try {

    //             // Send adjustment to Shopify (increase available by qty)
    //             $adjustResponse = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //                 ->post("https://5-core.myshopify.com/admin/api/2025-01/inventory_levels/adjust.json", [
    //                     'inventory_item_id' => $inventoryItemId,
    //                     'location_id' => $locationId,   
    //                     'available_adjustment' => $incomingQty,
    //                 ]);

    //         } catch (\Exception $e) {
    //         Log::error("Incoming store failed for SKU $sku: " . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return response()->json(['error' => 'inventory issuse.'], 500);
    //         }

    //         // $adjustResponse = Http::withHeaders([
    //         //     'X-Shopify-Access-Token' => $accessToken,
    //         // ])->post("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
    //         //     'inventory_item_id' => $inventoryItemId,
    //         //     'location_id' => $locationId,
    //         //     'available_adjustment' => $incomingQty,
    //         // ]);

    //         if (!$adjustResponse->successful()) {
    //             Log::error("Failed to update Shopify for SKU $sku", $adjustResponse->json());
    //             return response()->json(['error' => 'Failed to update Shopify inventory'], 500);
    //         }

    //         //  Store in database
    //         Inventory::create([
    //             'sku' => $sku,
    //             'verified_stock' => $incomingQty,
    //             'to_adjust' => $incomingQty,
    //             'reason' => $request->reason,
    //             'is_approved' => true,
    //             'approved_by' => Auth::user()->name ?? 'N/A',
    //             'approved_at' => Carbon::now('America/New_York'),
    //             'type' => 'incoming',
    //             'warehouse_id' => $request->warehouse_id,
    //         ]);

    //         return response()->json(['message' => 'Incoming inventory stored and updated in Shopify successfully']);

    //     } catch (\Exception $e) {
    //         Log::error("Incoming store failed for SKU $sku: " . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return response()->json(['error' => 'Something went wrong.'], 500);
    //     }
    // }


    public function store(Request $request)
    {
        // Set execution time limit to 90 seconds to handle slow API responses
        set_time_limit(90);
        ini_set('max_execution_time', 90);
        
        try {
            // Validate input
            $validated = $request->validate([
                'sku' => 'required|string',
                'qty' => 'required|integer|min:1',
                'warehouse_id' => 'required|integer',
                'reason' => 'required|string',
                'date' => 'nullable|date',
            ]);

            $sku = trim($validated['sku']);
            $qty = (int) $validated['qty'];

            Log::info("Incoming request received", [
                'sku' => $sku,
                'qty' => $qty,
                'warehouse_id' => $validated['warehouse_id'],
                'user' => Auth::user()->name ?? 'Unknown'
            ]);

            // Shopify credentials
            $shopifyDomain = config('services.shopify.store_url');
            $accessToken = config('services.shopify.access_token');

            if (!$accessToken || !$shopifyDomain) {
                Log::error("Missing Shopify credentials");
                return response()->json(['error' => 'Configuration error', 'details' => 'Shopify credentials not configured'], 500);
            }

            /** -----------------------------------------------------------------
             * Find the Shopify Inventory Item ID
             * Strategy:
             * 1) Fast-path: check local `shopify_skus` table for known variant_id
             * 2) Quick variants endpoint lookup (sku query)
             * 3) Fallback: paginated products.json search
             * ----------------------------------------------------------------- */
            $inventoryItemId = null;
            $pageInfo = null;
            $maxPages = 20;
            $pageCount = 0;

            // Fast-path: check local `shopify_skus` table for a known variant_id
            try {
                $shopifyRow = ShopifySku::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->first();
                if ($shopifyRow && !empty($shopifyRow->variant_id)) {
                    Log::info('Incoming: trying fast-path variant lookup', ['sku' => $sku, 'variant_id' => $shopifyRow->variant_id]);
                    try {
                        $variantResp = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                            ->timeout(30)
                            ->retry(5, 2000, function ($exception, $request) {
                                return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                                       ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                                       $exception->getCode() === 429;
                            })
                            ->get("https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$shopifyRow->variant_id}.json");

                        if ($variantResp->successful()) {
                            $inventoryItemId = $variantResp->json('variant.inventory_item_id') ?? null;
                            Log::info('Incoming: Found inventory_item_id from variant fast-path', ['sku' => $sku, 'inventory_item_id' => $inventoryItemId]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Incoming: fast-path variant fetch failed, will fall back to normal lookup', ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Incoming: error checking shopify_skus fast-path', ['error' => $e->getMessage()]);
            }

            // Quick attempt: query /variants.json?sku=... which is faster than full pagination
            // This is the PRIMARY method - should work if SKU exists
            if (!$inventoryItemId) {
                $variantRetries = 0;
                $maxVariantRetries = 5;
                
                while ($variantRetries < $maxVariantRetries && !$inventoryItemId) {
                    $variantRetries++;
                    try {
                        Log::info("Incoming: Attempting variants endpoint lookup (attempt {$variantRetries}/{$maxVariantRetries})", ['sku' => $sku]);
                        
                        $vResp = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                            ->timeout(30)
                            ->retry(3, 2000, function ($exception, $request) {
                                return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                                       ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                                       $exception->getCode() === 429;
                            })
                            ->get("https://{$this->shopifyDomain}/admin/api/2025-01/variants.json", [
                                'sku' => $sku,
                            ]);

                        if ($vResp->successful()) {
                            $variants = $vResp->json('variants') ?? [];
                            if (!empty($variants) && !empty($variants[0]['inventory_item_id'])) {
                                $inventoryItemId = $variants[0]['inventory_item_id'];
                                Log::info('Incoming: Found inventory_item_id via variants endpoint', ['sku' => $sku, 'inventory_item_id' => $inventoryItemId, 'attempt' => $variantRetries]);
                                break;
                            } else {
                                Log::warning('Incoming: Variants endpoint returned empty results', ['sku' => $sku, 'attempt' => $variantRetries]);
                            }
                        } else {
                            Log::warning('Incoming: Variants endpoint request failed', ['sku' => $sku, 'status' => $vResp->status(), 'attempt' => $variantRetries]);
                            if ($vResp->status() === 429) {
                                // Rate limited - wait longer
                                sleep(5);
                            } elseif ($vResp->status() >= 500) {
                                // Server error - retry
                                sleep(2);
                            } else {
                                // Client error - don't retry
                                break;
                            }
                        }
                    } catch (\Illuminate\Http\Client\ConnectionException $e) {
                        Log::warning('Incoming: Connection timeout on variants endpoint', ['sku' => $sku, 'attempt' => $variantRetries, 'error' => $e->getMessage()]);
                        if ($variantRetries < $maxVariantRetries) {
                            sleep(3);
                            continue;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Incoming: variants endpoint quick lookup failed', ['error' => $e->getMessage(), 'attempt' => $variantRetries]);
                        if ($variantRetries < $maxVariantRetries) {
                            sleep(2);
                            continue;
                        }
                    }
                }
            }

            // Fallback: paginated products.json search (only if variants endpoint didn't work)
            if (!$inventoryItemId) {
                Log::info("Incoming: Falling back to paginated products search", ['sku' => $sku]);
                
                do {
                    $pageCount++;
                    if ($pageCount > $maxPages) {
                        Log::warning("Incoming: Max pages reached in pagination", ['sku' => $sku, 'max_pages' => $maxPages]);
                        break;
                    }

                    $url = "https://{$shopifyDomain}/admin/api/2025-01/products.json?limit=250";
                    if ($pageInfo) {
                        $url .= "&page_info={$pageInfo}";
                    }

                    $pageRetries = 0;
                    $maxPageRetries = 3;
                    
                    while ($pageRetries < $maxPageRetries) {
                        $pageRetries++;
                        try {
                            $response = Http::withHeaders([
                                'X-Shopify-Access-Token' => $accessToken,
                            ])
                            ->timeout(30)
                            ->retry(2, 2000, function ($exception, $request) {
                                return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                                       ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                                       $exception->getCode() === 429;
                            })
                            ->get($url);

                            if ($response->successful()) {
                                $products = $response->json('products') ?? [];

                                foreach ($products as $product) {
                                    foreach ($product['variants'] as $variant) {
                                        if (trim(strtolower($variant['sku'] ?? '')) === strtolower($sku)) {
                                            $inventoryItemId = $variant['inventory_item_id'];
                                            Log::info("Found inventory item for SKU via pagination", ['sku' => $sku, 'inventory_item_id' => $inventoryItemId, 'page' => $pageCount]);
                                            break 3; // Break out of all loops
                                        }
                                    }
                                }

                                // Handle pagination
                                $linkHeader = $response->header('Link');
                                $pageInfo = null;
                                if ($linkHeader && preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
                                    $parsedUrl = parse_url($matches[1]);
                                    parse_str($parsedUrl['query'] ?? '', $query);
                                    $pageInfo = $query['page_info'] ?? null;
                                }
                                break; // Success, exit retry loop
                            } else {
                                if ($response->status() === 429) {
                                    Log::warning("Rate limited on products page", ['status' => $response->status(), 'page_count' => $pageCount, 'retry' => $pageRetries]);
                                    if ($pageRetries < $maxPageRetries) {
                                        sleep(5);
                                        continue;
                                    }
                                } elseif ($response->status() >= 500) {
                                    Log::warning("Server error on products page", ['status' => $response->status(), 'page_count' => $pageCount, 'retry' => $pageRetries]);
                                    if ($pageRetries < $maxPageRetries) {
                                        sleep(2);
                                        continue;
                                    }
                                }
                                $pageInfo = null;
                                break;
                            }
                        } catch (\Illuminate\Http\Client\ConnectionException $e) {
                            Log::warning("Connection timeout fetching Shopify products page", ['page_count' => $pageCount, 'retry' => $pageRetries, 'error' => $e->getMessage()]);
                            if ($pageRetries < $maxPageRetries) {
                                sleep(3);
                                continue;
                            }
                            $pageInfo = null;
                            break;
                        } catch (\Exception $e) {
                            Log::warning("Exception fetching Shopify products: " . $e->getMessage(), ['page_count' => $pageCount, 'retry' => $pageRetries]);
                            if ($pageRetries < $maxPageRetries) {
                                sleep(2);
                                continue;
                            }
                            $pageInfo = null;
                            break;
                        }
                    }

                } while (!$inventoryItemId && $pageInfo);
            }

            if (!$inventoryItemId) {
                Log::warning("SKU not found in Shopify: {$sku}");
                return response()->json([
                    'error' => 'SKU not found',
                    'details' => "The SKU '{$sku}' was not found in Shopify. Please check the SKU spelling."
                ], 404);
            }

            /** -----------------------------------------------------------------
             * Find the Shopify Location ID for "Ohio" with retries
             * ----------------------------------------------------------------- */
            $locationId = null;
            $maxRetries = 3;
            $attempt = 0;

            while ($attempt < $maxRetries && !$locationId) {
                $attempt++;
                try {
                    $locationResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $accessToken,
                    ])
                    ->timeout(30)
                    ->retry(3, 2000, function ($exception, $request) {
                        return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                               ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                               $exception->getCode() === 429;
                    })
                    ->get("https://{$shopifyDomain}/admin/api/2025-01/locations.json");

                    if ($locationResponse->successful()) {

                        $locations = $locationResponse->json('locations') ?? [];
                        
                        $ohioLocation = collect($locations)->first(function ($loc) {
                            return stripos($loc['name'] ?? '', 'ohio') !== false;
                        });

                        if ($ohioLocation) {
                            $locationId = $ohioLocation['id'];
                            Log::info("Found Ohio location", ['location_id' => $locationId]);
                        } else {
                            Log::warning("No Ohio location found in Shopify");
                            return response()->json([
                                'error' => 'Location not found',
                                'details' => 'No Shopify location found with name containing "Ohio"'
                            ], 404);
                        }
                    } else {
                        Log::warning("Failed to fetch locations", ['attempt' => $attempt, 'status' => $locationResponse->status()]);
                        if ($attempt < $maxRetries) {
                            if ($locationResponse->status() === 429) {
                                sleep(5);
                            } else {
                                sleep($attempt);
                            }
                            continue;
                        }
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::warning("Connection timeout fetching locations: " . $e->getMessage(), ['attempt' => $attempt]);
                    if ($attempt < $maxRetries) {
                        sleep(3);
                        continue;
                    }
                    throw $e;
                } catch (\Exception $e) {
                    Log::warning("Exception fetching locations: " . $e->getMessage(), ['attempt' => $attempt]);
                    if ($attempt < $maxRetries) {
                        sleep($attempt);
                        continue;
                    }
                    throw $e;
                }
            }

            if (!$locationId) {
                return response()->json([
                    'error' => 'Failed to fetch location',
                    'details' => 'Could not connect to Shopify to get location data'
                ], 503);
            }

            /** -----------------------------------------------------------------
             * Ensure inventory item is connected to the Ohio location (with retries)
             * ----------------------------------------------------------------- */
            $connectAttempt = 0;
            while ($connectAttempt < $maxRetries) {
                $connectAttempt++;
                try {
                    $connectResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ])->timeout(10)->post("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels/connect.json", [
                        'location_id' => $locationId,
                        'inventory_item_id' => $inventoryItemId,
                    ]);

                    // 422 means already connected - that's fine
                    if ($connectResponse->successful() || $connectResponse->status() == 422) {
                        Log::info("Inventory item connected to location", ['status' => $connectResponse->status()]);
                        break;
                    } elseif ($connectResponse->status() >= 500 || $connectResponse->status() == 429) {
                        if ($connectAttempt < $maxRetries) {
                            sleep($connectAttempt);
                            continue;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Exception connecting inventory: " . $e->getMessage(), ['attempt' => $connectAttempt]);
                    if ($connectAttempt < $maxRetries) {
                        sleep($connectAttempt);
                        continue;
                    }
                }
            }

            /** -----------------------------------------------------------------
             * Adjust inventory quantity for the Ohio location (with retries)
             * ----------------------------------------------------------------- */
            $adjustAttempt = 0;
            $adjustResponse = null;

            while ($adjustAttempt < $maxRetries) {
                $adjustAttempt++;
                try {
                    $adjustResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(30)
                    ->retry(5, 2000, function ($exception, $request) {
                        return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                               ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                               $exception->getCode() === 429;
                    })
                    ->post("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                        'location_id' => $locationId,
                        'inventory_item_id' => $inventoryItemId,
                        'available_adjustment' => $qty,
                    ]);

                    if ($adjustResponse->successful()) {
                        Log::info("Successfully adjusted Shopify inventory", ['sku' => $sku, 'qty' => $qty, 'attempt' => $adjustAttempt]);
                        break;
                    } elseif ($adjustResponse->status() >= 500 || $adjustResponse->status() == 429) {
                        Log::warning("Adjust failed, retrying...", ['status' => $adjustResponse->status(), 'attempt' => $adjustAttempt]);
                        if ($adjustAttempt < $maxRetries) {
                            if ($adjustResponse->status() === 429) {
                                sleep(5);
                            } else {
                                sleep($adjustAttempt);
                            }
                            continue;
                        }
                    } else {
                        Log::error("Adjust failed with non-retryable error", ['sku' => $sku, 'status' => $adjustResponse->status(), 'body' => $adjustResponse->body()]);
                        break;
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::warning("Connection timeout adjusting inventory: " . $e->getMessage(), ['attempt' => $adjustAttempt]);
                    if ($adjustAttempt < $maxRetries) {
                        sleep(3);
                        continue;
                    }
                    throw $e;
                } catch (\Exception $e) {
                    Log::warning("Exception adjusting inventory: " . $e->getMessage(), ['attempt' => $adjustAttempt]);
                    if ($adjustAttempt < $maxRetries) {
                        sleep($adjustAttempt);
                        continue;
                    }
                    throw $e;
                }
            }

            if (!$adjustResponse || !$adjustResponse->successful()) {
                $status = $adjustResponse ? $adjustResponse->status() : 'No response';
                Log::error("Failed to adjust Shopify inventory after retries", ['sku' => $sku, 'status' => $status]);
                return response()->json([
                    'error' => 'Failed to update Shopify inventory',
                    'details' => 'Could not complete the adjustment. Please try again.'
                ], 503);
            }

            /** -----------------------------------------------------------------
             * Store locally in database
             * ----------------------------------------------------------------- */
            try {
                DB::beginTransaction();
                
                DB::table('inventories')->insert([
                    'sku' => $sku,
                    'verified_stock' => $qty,
                    'to_adjust' => $qty,
                    'reason' => $validated['reason'],
                    'is_approved' => true,
                    'approved_by' => Auth::user()->name ?? 'N/A',
                    'approved_at' => Carbon::now('America/New_York'),
                    'type' => 'incoming',
                    'warehouse_id' => $validated['warehouse_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::commit();

                Log::info('Incoming inventory stored successfully', ['sku' => $sku, 'qty' => $qty]);

                return response()->json([
                    'success' => true,
                    'message' => "✓ Incoming stock for {$sku} added successfully! Quantity: {$qty} units.",
                    'new_stock_level' => $qty
                ], 200);

            } catch (\Exception $dbException) {
                DB::rollBack();
                Log::error("Failed to save to database after successful Shopify update", ['sku' => $sku, 'error' => $dbException->getMessage()]);
                
                return response()->json([
                    'error' => 'Database Error',
                    'details' => 'Shopify was updated but database record could not be created. Please contact support.',
                    'shopify_updated' => true
                ], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation Error',
                'details' => $e->errors()
            ], 422);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Connection timeout: {$e->getMessage()}", [
                'sku' => $sku ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Connection Timeout',
                'details' => 'The request to Shopify took too long. The SKU may exist but the API is slow. Please try again in a moment.'
            ], 504);
        } catch (\Exception $e) {
            Log::error("Incoming store failed: " . $e->getMessage(), [
                'sku' => $sku ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'An unexpected error occurred',
                'details' => 'Please try again or contact support if the problem persists. Error: ' . $e->getMessage()
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
        $data = Inventory::with('warehouse')
            ->where('type', 'incoming') // Only incoming records
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'sku' => $item->sku,
                    'verified_stock' => $item->verified_stock,
                    'reason' => $item->reason,
                    'warehouse_name' => $item->warehouse->name ?? '',
                    'approved_by' => $item->approved_by,
                    'approved_at' =>  $item->approved_at
                        ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('m-d-Y')
                        : '',
                ];
            });

        return response()->json(['data' => $data]);
    }



    public function incomingOrderIndex()
    {
        $warehouses = Warehouse::select('id', 'name')->get();
        $skus = ProductMaster::select('id','parent','sku')->get();

        return view('inventory-management.incoming-orders-view', compact('warehouses', 'skus'));
    }

   

    public function incomingOrderStore(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'qty' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'reason' => 'required|string',
        ]);

        $sku = trim($request->sku);

        try {
            // Store in incoming_data table
            $incomingOrder = IncomingData::updateOrCreate(
                ['sku' => $sku], // since sku is unique
                [
                    'warehouse_id' => $request->warehouse_id,
                    'quantity'     => (int) $request->qty,
                    'reason'       => $request->reason,
                    'approved_by'  => Auth::user()->name ?? 'N/A',
                    'approved_at'  => Carbon::now('America/New_York'),

                ]
            );

            return response()->json([
                'message' => 'Incoming order stored successfully',
                'data'    => $incomingOrder
            ]);

        } catch (\Exception $e) {
            Log::error("Incoming order store failed for SKU $sku: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }


    public function incomingOrderList()
    {
        $data = IncomingData::with('warehouse')
            ->get()
            ->map(function ($item) {
                return [
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'reason' => $item->reason,
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
