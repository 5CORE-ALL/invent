<?php

namespace App\Http\Controllers;

use App\Models\InventoryWarehouse;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Log as FacadesLog;

class InventoryWarehouseController extends Controller
{

    public function index()
    {
        $warehouses = InventoryWarehouse::with('user')->latest()->get();

        return view('purchase-master.transit_container.inventory_warehouse', compact('warehouses'));
    }

    public function debugSku($sku)
    {
        $normalized = $this->normalizeSku($sku);
        $skuNoSpaces = str_replace([' ', '-', '_'], '', $normalized);
        
        $result = [
            'original' => $sku,
            'normalized' => $normalized,
            'no_spaces' => $skuNoSpaces,
            'table_total' => ShopifySku::count(),
            'valid_variants' => ShopifySku::whereNotNull('variant_id')->where('variant_id', '!=', '')->where('variant_id', '!=', '0')->count(),
        ];
        
        // Exact match
        $exact = ShopifySku::where('sku', $normalized)->first();
        if ($exact) {
            $result['exact_match'] = [
                'found' => true,
                'db_sku' => $exact->sku,
                'variant_id' => $exact->variant_id,
                'is_valid' => !empty($exact->variant_id) && $exact->variant_id != '0'
            ];
        } else {
            $result['exact_match'] = ['found' => false];
            
            // Fuzzy match
            $fuzzy = ShopifySku::whereNotNull('variant_id')
                ->where('variant_id', '!=', '')
                ->where('variant_id', '!=', '0')
                ->get()
                ->first(function($item) use ($skuNoSpaces) {
                    return str_replace([' ', '-', '_'], '', strtoupper(trim($item->sku))) === $skuNoSpaces;
                });
            
            if ($fuzzy) {
                $result['fuzzy_match'] = [
                    'found' => true,
                    'db_sku' => $fuzzy->sku,
                    'variant_id' => $fuzzy->variant_id
                ];
            } else {
                $result['fuzzy_match'] = ['found' => false];
                
                // Similar SKUs
                $similar = ShopifySku::where('sku', 'LIKE', '%' . substr($normalized, 0, 5) . '%')->limit(5)->get(['sku', 'variant_id']);
                $result['similar_skus'] = $similar->toArray();
            }
        }
        
        return response()->json($result, 200, [], JSON_PRETTY_PRINT);
    }

    // public function pushInventory(Request $request)
    // {
    //     $tabName = $request->input('tab_name');
    //     $rows = $request->input('data', []);

    //     foreach ($rows as $row) {
    //         InventoryWarehouse::create([
    //             'tab_name'          => $row['tab_name'] ?? $tabName,
    //             'supplier_name'     => $row['supplier_name'] ?? null,
    //             'company_name'      => $row['company_name'] ?? null,
    //             'our_sku'           => $row['our_sku'] ?? null,
    //             'parent'            => $row['parent'] ?? null,
    //             'no_of_units'       => !empty($row['no_of_units']) ? (int) $row['no_of_units'] : null,
    //             'total_ctn'         => !empty($row['total_ctn']) ? (int) $row['total_ctn'] : null,
    //             'rate'              => !empty($row['rate']) ? (float) $row['rate'] : null,
    //             'unit'              => $row['unit'] ?? null,
    //             'status'            => $row['status'] ?? null,
    //             'changes'           => $row['changes'] ?? null,
    //             'values'            => $row['values'] ?? null,
    //             'package_size'      => $row['package_size'] ?? null,
    //             'product_size_link' => $row['product_size_link'] ?? null,
    //             'comparison_link'   => $row['comparison_link'] ?? null,
    //             'order_link'        => $row['order_link'] ?? null,
    //             'image_src'         => $row['image_src'] ?? null,
    //             'photos'            => $row['photos'] ?? null,
    //             'specification'     => $row['specification'] ?? null,
    //             'supplier_names'    => $row['supplier_names'] ?? [],
    //         ]);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Inventory pushed successfully',
    //         'count'   => count($rows),
    //     ]);
    // }


    // public function pushInventory(Request $request)
    // {
    //     $tabName = $request->input('tab_name');
    //     $rows = $request->input('data', []);

    //     $alreadyPushedSkus = [];

    //     foreach ($rows as $row) {
    //         $sku = trim($row['our_sku'] ?? '');
    //         $units = !empty($row['no_of_units']) ? (int) $row['no_of_units'] : 0;
    //         $ctns  = !empty($row['total_ctn']) ? (int) $row['total_ctn'] : 0;

    //         $qty = $units * $ctns;

    //         if (!$sku || $qty <= 0) continue;

    //         try {
    //             ini_set('max_execution_time', 60);

    //             $alreadyPushed = InventoryWarehouse::where('tab_name', $tabName)
    //                 ->where('our_sku', $sku)
    //                 ->where('pushed', 1)
    //                 ->exists();

    //             if ($alreadyPushed) {
    //                 $alreadyPushedSkus[] = $sku; // collect for popup
    //                 continue; // skip pushing
    //             }

    //             // --- STEP 1: Find inventory_item_id from products.json ---
    //             $inventoryItemId = null;
    //             $pageInfo = null;
    //             $normalizedSku = strtoupper(preg_replace('/\s+/u', ' ', $sku));

    //             do {
    //                 $queryParams = ['limit' => 250];
    //                 if ($pageInfo) $queryParams['page_info'] = $pageInfo;

    //                 $response = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //                     ->get("https://" . config('services.shopify.store_url') . "/admin/api/2025-01/products.json", $queryParams);

    //                 $products = $response->json('products') ?? [];

    //                 foreach ($products as $product) {
    //                     foreach ($product['variants'] as $variant) {
    //                         $variantSku = strtoupper(preg_replace('/\s+/u', ' ', trim($variant['sku'] ?? '')));
    //                         if ($variantSku === $normalizedSku) {
    //                             $inventoryItemId = $variant['inventory_item_id'];
    //                             break 2;
    //                         }
    //                     }
    //                 }

    //                 // Pagination
    //                 $linkHeader = $response->header('Link');
    //                 $pageInfo = null;
    //                 if ($linkHeader && preg_match('/<([^>]+page_info=([^&>]+)[^>]*)>; rel="next"/', $linkHeader, $matches)) {
    //                     $pageInfo = $matches[2];
    //                 }
    //             } while (!$inventoryItemId && $pageInfo);

    //             if (!$inventoryItemId) {
    //                 Log::warning("Shopify SKU not found: {$sku}");
    //                 continue;
    //             }

    //             // --- STEP 2: Get location_id from inventory_levels ---
    //             $invLevelResponse = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //                 ->get("https://" . config('services.shopify.store_url') . "/admin/api/2025-01/inventory_levels.json", [
    //                     'inventory_item_ids' => $inventoryItemId,
    //                 ]);

    //             $levels = $invLevelResponse->json('inventory_levels') ?? [];
    //             $locationId = $levels[0]['location_id'] ?? null;

    //             if (!$locationId) {
    //                 Log::warning("Shopify location not found for SKU: {$sku}");
    //                 continue;
    //             }

    //             // --- STEP 3: Adjust Shopify available qty ---
    //             $adjustResponse = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //                 ->post("https://" . config('services.shopify.store_url') . "/admin/api/2025-01/inventory_levels/adjust.json", [
    //                     'inventory_item_id' => $inventoryItemId,
    //                     'location_id' => $locationId,
    //                     'available_adjustment' => $qty,
    //                 ]);

    //             if (!$adjustResponse->successful()) {
    //                 Log::error("Failed to adjust Shopify inventory for SKU: {$sku}", $adjustResponse->json());
    //                 continue;
    //             }

    //             // --- STEP 4: Store in local DB ---
    //             InventoryWarehouse::create([
    //                 'tab_name'          => $row['tab_name'] ?? $tabName,
    //                 'supplier_name'     => $row['supplier_name'] ?? null,
    //                 'company_name'      => $row['company_name'] ?? null,
    //                 'our_sku'           => $sku,
    //                 'pushed'            => 1,
    //                 'parent'            => $row['parent'] ?? null,
    //                 'no_of_units'       => $units,
    //                 'total_ctn'         => $ctns,
    //                 'rate'              => !empty($row['rate']) ? (float) $row['rate'] : null,
    //                 'unit'              => $row['unit'] ?? null,
    //                 'status'            => $row['status'] ?? null,
    //                 'changes'           => $row['changes'] ?? null,
    //                 'values'            => $row['values'] ?? null,
    //                 'package_size'      => $row['package_size'] ?? null,
    //                 'product_size_link' => $row['product_size_link'] ?? null,
    //                 'comparison_link'   => $row['comparison_link'] ?? null,
    //                 'order_link'        => $row['order_link'] ?? null,
    //                 'image_src'         => $row['image_src'] ?? null,
    //                 'photos'            => $row['photos'] ?? null,
    //                 'specification'     => $row['specification'] ?? null,
    //                 'supplier_names'    => $row['supplier_names'] ?? [],
    //             ]);

    //         } catch (\Exception $e) {
    //             Log::error("PushInventory failed for SKU {$sku}: " . $e->getMessage());
    //         }
    //     }

    //     $message = 'Inventory pushed successfully to Shopify and stored.';
    //     if (!empty($alreadyPushedSkus)) {
    //         $message .= "\n\nThese SKUs were already pushed previously and were skipped:\n" . implode(', ', $alreadyPushedSkus);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => $message,
    //         'count'   => count($rows),
    //         'skipped' => $alreadyPushedSkus
    //     ]);
    // }


   // Add this helper at the top of your controller
    private function normalizeSku($sku) {
        // Trim, convert to uppercase, and normalize whitespace (including multiple spaces, tabs, etc.)
        $normalized = strtoupper(preg_replace('/\s+/u', ' ', trim($sku)));
        // Remove any non-printable characters
        $normalized = preg_replace('/[\x00-\x1F\x7F]/u', '', $normalized);
        return $normalized;
    }

    /**
     * Make Shopify API request with automatic retry on rate limit errors
     * 
     * @param callable $requestCallback Function that returns the HTTP response
     * @param string $sku SKU being processed (for logging)
     * @param string $operation Operation name (for logging)
     * @param int $maxRetries Maximum number of retries (default 5)
     * @return \Illuminate\Http\Client\Response|null
     */
    private function makeShopifyRequestWithRetry(callable $requestCallback, string $sku, string $operation, int $maxRetries = 5)
    {
        $retryCount = 0;
        
        while ($retryCount <= $maxRetries) {
            // Make the request
            $response = $requestCallback();
            
            // If successful, return immediately
            if ($response && $response->successful()) {
                return $response;
            }
            
            // Check if it's a rate limit error (429)
            if ($response && $response->status() === 429) {
                $retryCount++;
                
                if ($retryCount > $maxRetries) {
                    Log::error("Max retries exceeded for rate limit", [
                        'sku' => $sku,
                        'operation' => $operation,
                        'retry_count' => $retryCount
                    ]);
                    return $response;
                }
                
                // Get retry-after header if available, otherwise use 3 second sleep
                $retryAfter = $response->header('Retry-After');
                if ($retryAfter) {
                    $waitSeconds = (int)$retryAfter;
                    Log::warning("Rate limit hit - waiting {$waitSeconds} seconds", [
                        'sku' => $sku,
                        'operation' => $operation,
                        'retry_count' => $retryCount,
                        'retry_after' => $waitSeconds
                    ]);
                    sleep($waitSeconds + 1); // Add 1 second buffer
                } else {
                    // Fixed 3 second sleep as requested
                    Log::warning("Rate limit hit - waiting 3 seconds (retry {$retryCount}/{$maxRetries})", [
                        'sku' => $sku,
                        'operation' => $operation,
                        'retry_count' => $retryCount
                    ]);
                    sleep(3);
                }
                
                // Continue to retry
                continue;
            }
            
            // For other errors, return the response (let caller handle it)
            return $response;
        }
        
        // Max retries exceeded
        Log::error("Max retries exceeded for Shopify request", [
            'sku' => $sku,
            'operation' => $operation,
            'retry_count' => $retryCount
        ]);
        
        return $response;
    }

    public function pushInventory(Request $request)
    {
        $tabName = $request->input('tab_name');
        $rows = $request->input('data', []);
        $userId = auth()->id();

        $alreadyPushed = [];
        $notFound = [];
        $pushedRows = [];
        
        Log::info('=== PUSH INVENTORY REQUEST STARTED ===', [
            'tab_name' => $tabName,
            'row_count' => count($rows),
            'user_id' => $userId
        ]);
        
        // Validate Shopify credentials exist
        if (!config('services.shopify.api_key') || !config('services.shopify.password') || !config('services.shopify.store_url')) {
            Log::error("Shopify credentials not configured");
            return response()->json([
                'success' => false,
                'message' => 'Shopify API credentials are not configured. Please check your .env file.'
            ]);
        }

        Log::info("Shopify config validated", [
            'store_url' => config('services.shopify.store_url'),
            'has_api_key' => !empty(config('services.shopify.api_key')),
            'has_password' => !empty(config('services.shopify.password'))
        ]);
        
        // Get all already pushed SKUs for this tab
        $existingPushed = InventoryWarehouse::where('tab_name', $tabName)
            ->where('pushed', 1)
            ->pluck('transit_container_id')
            ->map(fn($v) => (int)$v)
            ->toArray();

        // ✅ Build SKU lookup map from local database
        $skuLookup = ShopifySku::whereNotNull('variant_id')
            ->where('variant_id', '!=', '')
            ->where('variant_id', '!=', '0')
            ->where('variant_id', '!=', 0)
            ->get()
            ->mapWithKeys(function($item) {
                $normalizedSku = $this->normalizeSku($item->sku);
                return [$normalizedSku => $item->variant_id];
            })
            ->toArray();

        $dbSkuCount = ShopifySku::count();
        Log::info("SKU lookup map built", [
            'tab' => $tabName,
            'skus_to_push' => count($rows),
            'lookup_size' => count($skuLookup),
            'total_db_skus' => $dbSkuCount
        ]);

        // Cache for API-fetched variants to avoid repeated API calls
        $apiCache = [];

        foreach ($rows as $row) {
            $rowId = (int)($row['id'] ?? 0);
            $sku = $this->normalizeSku($row['our_sku'] ?? '');
            $units = !empty($row['no_of_units']) ? (int) $row['no_of_units'] : 0;
            $ctns  = !empty($row['total_ctn']) ? (int) $row['total_ctn'] : 0;
            $qty = $units * $ctns;

            if (!$rowId || !$sku || $qty <= 0) {
                Log::warning("Invalid row data - skipping", ['row_id' => $rowId, 'sku' => $sku, 'qty' => $qty]);
                continue;
            }

            // Skip already pushed
            if (in_array($rowId, $existingPushed, true)) {
                $alreadyPushed[] = $sku;
                Log::info("Already pushed - skipping", ['sku' => $sku, 'row_id' => $rowId]);
                continue;
            }

            try {
                Log::info("Processing SKU", [
                    'sku' => $sku,
                    'row_id' => $rowId,
                    'qty' => $qty
                ]);
                
                // ✅ Get variant_id from local database lookup
                $variantId = $skuLookup[$sku] ?? null;

                if (!$variantId) {
                        Log::info("Tier 1 exact match failed - trying fuzzy match", ['sku' => $sku]);
                    
                    // Try multiple matching strategies in database
                    $skuNoSpaces = str_replace([' ', '-', '_'], '', $sku);
                    
                    // Strategy 1: Fuzzy collection match
                    $flexibleMatch = ShopifySku::whereNotNull('variant_id')
                        ->where('variant_id', '!=', '')
                        ->where('variant_id', '!=', '0')
                        ->where('variant_id', '!=', 0)
                        ->get()
                        ->first(function($item) use ($skuNoSpaces) {
                            $itemNoSpaces = str_replace([' ', '-', '_'], '', strtoupper(trim($item->sku)));
                            return $itemNoSpaces === $skuNoSpaces;
                        });
                    
                    if ($flexibleMatch) {
                        $variantId = $flexibleMatch->variant_id;
                        Log::info("Tier 2 fuzzy match SUCCESS", [
                            'sku' => $sku,
                            'db_sku' => $flexibleMatch->sku,
                            'variant_id' => $variantId
                        ]);
                    } else {
                        // Strategy 2: Search Shopify API directly as fallback
                        Log::info("Tier 2 fuzzy match failed - trying Tier 3 API search", ['sku' => $sku]);
                        
                        // Check if we already fetched this page in API cache
                        if (empty($apiCache)) {
                            Log::info("API cache empty - building cache from Shopify products endpoint");
                            $pageInfo = null;
                            
                            do {
                                $queryParams = ['limit' => 250];
                                if ($pageInfo) $queryParams['page_info'] = $pageInfo;
                                
                                $response = $this->makeShopifyRequestWithRetry(function() use ($queryParams) {
                                    return Http::withBasicAuth(
                                        config('services.shopify.api_key'),
                                        config('services.shopify.password')
                                    )->timeout(30)->get(
                                        "https://" . config('services.shopify.store_url') . "/admin/api/2025-01/products.json",
                                        $queryParams
                                    );
                                }, $sku, 'products_cache');
                                
                                // Rate limiting for pagination
                                usleep(550000);
                                
                                if (!$response || !$response->successful()) {
                                    Log::error("API fetch failed for products page", [
                                        'status' => $response ? $response->status() : 'null',
                                        'body' => $response ? $response->body() : 'null'
                                    ]);
                                    break;
                                }
                                
                                $products = $response->json('products') ?? [];
                                
                                foreach ($products as $product) {
                                    foreach ($product['variants'] as $variant) {
                                        $variantSku = strtoupper(preg_replace('/\s+/u', ' ', trim($variant['sku'] ?? '')));
                                        $variantSkuNoSpaces = str_replace([' ', '-', '_'], '', $variantSku);
                                        $apiCache[$variantSku] = $variant['id'];
                                        $apiCache[$variantSkuNoSpaces] = $variant['id'];
                                    }
                                }
                                
                                // Check for next page
                                $linkHeader = $response->header('Link');
                                $pageInfo = null;
                                if ($linkHeader && preg_match('/<([^>]+page_info=([^&>]+)[^>]*)>; rel="next"/', $linkHeader, $matches)) {
                                    $pageInfo = $matches[2];
                                }
                                
                            } while ($pageInfo);
                            
                            Log::info("API cache built successfully", ['cached_variants' => count($apiCache)]);
                        }
                        
                        // Check in API cache
                        $variantId = $apiCache[$sku] ?? $apiCache[$skuNoSpaces] ?? null;
                        
                        if ($variantId) {
                            Log::info("Tier 3 API cache match SUCCESS", ['sku' => $sku, 'variant_id' => $variantId]);
                        } else {
                            Log::error("All 3 tiers FAILED - SKU not found", [
                                'sku' => $sku,
                                'tried_db_exact' => true,
                                'tried_db_fuzzy' => true,
                                'tried_api' => true,
                                'api_cache_size' => count($apiCache)
                            ]);
                            $notFound[] = $sku;
                            continue;
                        }
                    }
                }

                if (!$variantId) {
                    Log::error("No variant_id after all lookup attempts", ['sku' => $sku]);
                    $notFound[] = $sku;
                    continue;
                }

                Log::info("Variant ID confirmed - proceeding to update", [
                    'sku' => $sku,
                    'variant_id' => $variantId,
                    'qty_adjustment' => $qty
                ]);

                // ✅ Rate limiting: Shopify allows 2 calls/second, so wait 0.55 seconds between calls
                usleep(550000); // 550ms delay to stay under rate limit (slightly more than 500ms for safety)

                // ✅ Get inventory_item_id from variant with retry logic
                $variantResponse = $this->makeShopifyRequestWithRetry(function() use ($variantId) {
                    return Http::withBasicAuth(
                        config('services.shopify.api_key'), 
                        config('services.shopify.password')
                    )->timeout(30)->get(
                        "https://" . config('services.shopify.store_url') . "/admin/api/2025-01/variants/{$variantId}.json"
                    );
                }, $sku, 'variant');

                if (!$variantResponse || !$variantResponse->successful()) {
                    Log::error("Shopify variant API failed after retries", [
                        'sku' => $sku, 
                        'variant_id' => $variantId,
                        'status' => $variantResponse ? $variantResponse->status() : 'null'
                    ]);
                    $notFound[] = $sku;
                    continue;
                }

                $inventoryItemId = $variantResponse->json('variant.inventory_item_id');

                if (!$inventoryItemId) {
                    Log::error("No inventory_item_id in response", ['sku' => $sku, 'response' => $variantResponse->json()]);
                    $notFound[] = $sku;
                    continue;
                }

                // Rate limiting
                usleep(550000);

                // --- Get location_id with retry logic ---
                $invLevelResponse = $this->makeShopifyRequestWithRetry(function() use ($inventoryItemId) {
                    return Http::withBasicAuth(
                        config('services.shopify.api_key'), 
                        config('services.shopify.password')
                    )->timeout(30)->get(
                        "https://" . config('services.shopify.store_url') . "/admin/api/2025-01/inventory_levels.json",
                        ['inventory_item_ids' => $inventoryItemId]
                    );
                }, $sku, 'inventory_levels');

                if (!$invLevelResponse || !$invLevelResponse->successful()) {
                    Log::error("Shopify inventory levels API failed after retries", [
                        'sku' => $sku,
                        'inventory_item_id' => $inventoryItemId
                    ]);
                    $notFound[] = $sku;
                    continue;
                }

                $levels = $invLevelResponse->json('inventory_levels') ?? [];
                $locationId = $levels[0]['location_id'] ?? null;

                if (!$locationId) {
                    Log::error("No location found", ['sku' => $sku, 'inventory_item_id' => $inventoryItemId]);
                    $notFound[] = $sku;
                    continue;
                }

                // Rate limiting
                usleep(550000);

                // --- Adjust Shopify qty with retry logic ---
                $adjustResponse = $this->makeShopifyRequestWithRetry(function() use ($inventoryItemId, $locationId, $qty) {
                    return Http::withBasicAuth(
                        config('services.shopify.api_key'), 
                        config('services.shopify.password')
                    )->timeout(30)->post(
                        "https://" . config('services.shopify.store_url') . "/admin/api/2025-01/inventory_levels/adjust.json",
                        [
                            'inventory_item_id' => $inventoryItemId,
                            'location_id' => $locationId,
                            'available_adjustment' => $qty,
                        ]
                    );
                }, $sku, 'adjust');

                if (!$adjustResponse || !$adjustResponse->successful()) {
                    Log::error("Shopify inventory adjustment FAILED after retries", [
                        'sku' => $sku,
                        'status' => $adjustResponse ? $adjustResponse->status() : 'null',
                        'error' => $adjustResponse ? $adjustResponse->body() : 'null',
                        'qty' => $qty,
                        'inventory_item_id' => $inventoryItemId,
                        'location_id' => $locationId
                    ]);
                    $notFound[] = $sku;
                    continue;
                }

                Log::info("Shopify inventory updated successfully", [
                    'sku' => $sku, 
                    'qty_added' => $qty,
                    'new_available' => $adjustResponse->json('inventory_level.available')
                ]);

                // --- Store in DB only after Shopify success ---
                InventoryWarehouse::updateOrCreate(
                    ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                    [
                        'our_sku' => $sku,
                        'pushed' => 1,
                        'created_by' => $userId,
                        'supplier_name' => $row['supplier_name'] ?? null,
                        'company_name' => $row['company_name'] ?? null,
                        'no_of_units' => $units,
                        'total_ctn' => $ctns,
                        'parent' => $row['parent'] ?? null,
                        'rate' => !empty($row['rate']) ? (float)$row['rate'] : null,
                        'unit' => $row['unit'] ?? null,
                        'status' => $row['status'] ?? null,
                        'changes' => $row['changes'] ?? null,
                        'values' => $row['values'] ?? null,
                        'package_size' => $row['package_size'] ?? null,
                        'product_size_link' => $row['product_size_link'] ?? null,
                        'comparison_link' => $row['comparison_link'] ?? null,
                        'order_link' => $row['order_link'] ?? null,
                        'image_src' => $row['image_src'] ?? null,
                        'photos' => $row['photos'] ?? null,
                        'specification' => $row['specification'] ?? null,
                        'supplier_names' => $row['supplier_names'] ?? [],
                    ]
                );

                $pushedRows[] = ['row_id' => $rowId, 'sku' => $sku];
                $existingPushed[] = $rowId;

                Log::info("SKU fully processed and saved to database", [
                    'sku' => $sku,
                    'row_id' => $rowId,
                    'warehouse_record_created' => true
                ]);

            } catch (\Exception $e) {
                Log::error("EXCEPTION while processing SKU", [
                    'sku' => $sku,
                    'row_id' => $rowId,
                    'error_message' => $e->getMessage(),
                    'error_line' => $e->getLine(),
                    'error_file' => basename($e->getFile()),
                    'trace' => $e->getTraceAsString()
                ]);
                // Add to notFound so user knows it failed
                if (!in_array($sku, $notFound)) {
                    $notFound[] = $sku;
                }
            }
        }

        Log::info('=== PUSH INVENTORY REQUEST COMPLETED ===', [
            'pushed_count' => count($pushedRows),
            'skipped_count' => count($alreadyPushed),
            'not_found_count' => count($notFound),
            'pushed_skus' => array_column($pushedRows, 'sku'),
            'skipped_skus' => $alreadyPushed,
            'not_found_skus' => $notFound
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inventory push completed.',
            'skipped' => $alreadyPushed,
            'not_found' => $notFound,
            'pushed' => $pushedRows,
        ]);
    }

    /**
     * Push a single inventory item to Shopify
     * Returns status immediately for real-time updates
     */
    public function pushSingleItem(Request $request)
    {
        $row = $request->input('data');
        $tabName = $request->input('tab_name');
        $userId = auth()->id();

        $rowId = (int)($row['id'] ?? 0);
        $sku = $this->normalizeSku($row['our_sku'] ?? '');
        $units = !empty($row['no_of_units']) ? (int) $row['no_of_units'] : 0;
        $ctns  = !empty($row['total_ctn']) ? (int) $row['total_ctn'] : 0;
        $qty = $units * $ctns;

        // Validate input
        if (!$rowId || !$sku || $qty <= 0) {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'row_id' => $rowId,
                'sku' => $sku,
                'message' => 'Invalid row data'
            ]);
        }

        // Check if already pushed successfully
        $existing = InventoryWarehouse::where('tab_name', $tabName)
            ->where('transit_container_id', $rowId)
            ->where('push_status', 'success')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'status' => 'skipped',
                'row_id' => $rowId,
                'sku' => $sku,
                'message' => 'Already pushed successfully'
            ]);
        }

        // Validate Shopify credentials
        if (!config('services.shopify.api_key') || !config('services.shopify.password') || !config('services.shopify.store_url')) {
            InventoryWarehouse::updateOrCreate(
                ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                [
                    'our_sku' => $sku,
                    'push_status' => 'failed',
                    'pushed' => 0,
                    'created_by' => $userId,
                ]
            );
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'row_id' => $rowId,
                'sku' => $sku,
                'message' => 'Shopify credentials not configured'
            ]);
        }

        try {
            // Update status to processing
            InventoryWarehouse::updateOrCreate(
                ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                [
                    'our_sku' => $sku,
                    'push_status' => 'pending',
                    'pushed' => 0,
                    'created_by' => $userId,
                ]
            );

            // Build SKU lookup map from local database
            $skuLookup = ShopifySku::whereNotNull('variant_id')
                ->where('variant_id', '!=', '')
                ->where('variant_id', '!=', '0')
                ->where('variant_id', '!=', 0)
                ->get()
                ->mapWithKeys(function($item) {
                    $normalizedSku = $this->normalizeSku($item->sku);
                    return [$normalizedSku => $item->variant_id];
                })
                ->toArray();

            // Get variant_id from local database lookup
            $variantId = $skuLookup[$sku] ?? null;

            if (!$variantId) {
                // Try fuzzy match
                $skuNoSpaces = str_replace([' ', '-', '_'], '', $sku);
                $flexibleMatch = ShopifySku::whereNotNull('variant_id')
                    ->where('variant_id', '!=', '')
                    ->where('variant_id', '!=', '0')
                    ->where('variant_id', '!=', 0)
                    ->get()
                    ->first(function($item) use ($skuNoSpaces) {
                        $itemNoSpaces = str_replace([' ', '-', '_'], '', strtoupper(trim($item->sku)));
                        return $itemNoSpaces === $skuNoSpaces;
                    });
                
                if ($flexibleMatch) {
                    $variantId = $flexibleMatch->variant_id;
                }
            }

            if (!$variantId) {
                InventoryWarehouse::updateOrCreate(
                    ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                    [
                        'our_sku' => $sku,
                        'push_status' => 'failed',
                        'pushed' => 0,
                        'created_by' => $userId,
                    ]
                );
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'row_id' => $rowId,
                    'sku' => $sku,
                    'message' => 'SKU not found in Shopify'
                ]);
            }

            // Rate limiting
            usleep(550000);

            // Get inventory_item_id from variant with retry
            $variantResponse = $this->makeShopifyRequestWithRetry(function() use ($variantId) {
                return Http::withBasicAuth(
                    config('services.shopify.api_key'), 
                    config('services.shopify.password')
                )->timeout(30)->get(
                    "https://" . config('services.shopify.store_url') . "/admin/api/2025-01/variants/{$variantId}.json"
                );
            }, $sku, 'variant');

            if (!$variantResponse || !$variantResponse->successful()) {
                InventoryWarehouse::updateOrCreate(
                    ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                    [
                        'our_sku' => $sku,
                        'push_status' => 'failed',
                        'pushed' => 0,
                        'created_by' => $userId,
                    ]
                );
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'row_id' => $rowId,
                    'sku' => $sku,
                    'message' => 'Failed to fetch variant from Shopify'
                ]);
            }

            $inventoryItemId = $variantResponse->json('variant.inventory_item_id');

            if (!$inventoryItemId) {
                InventoryWarehouse::updateOrCreate(
                    ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                    [
                        'our_sku' => $sku,
                        'push_status' => 'failed',
                        'pushed' => 0,
                        'created_by' => $userId,
                    ]
                );
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'row_id' => $rowId,
                    'sku' => $sku,
                    'message' => 'No inventory_item_id found'
                ]);
            }

            // Rate limiting
            usleep(550000);

            // Get location_id with retry
            $invLevelResponse = $this->makeShopifyRequestWithRetry(function() use ($inventoryItemId) {
                return Http::withBasicAuth(
                    config('services.shopify.api_key'), 
                    config('services.shopify.password')
                )->timeout(30)->get(
                    "https://" . config('services.shopify.store_url') . "/admin/api/2025-01/inventory_levels.json",
                    ['inventory_item_ids' => $inventoryItemId]
                );
            }, $sku, 'inventory_levels');

            if (!$invLevelResponse || !$invLevelResponse->successful()) {
                InventoryWarehouse::updateOrCreate(
                    ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                    [
                        'our_sku' => $sku,
                        'push_status' => 'failed',
                        'pushed' => 0,
                        'created_by' => $userId,
                    ]
                );
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'row_id' => $rowId,
                    'sku' => $sku,
                    'message' => 'Failed to fetch inventory levels'
                ]);
            }

            $levels = $invLevelResponse->json('inventory_levels') ?? [];
            $locationId = $levels[0]['location_id'] ?? null;

            if (!$locationId) {
                InventoryWarehouse::updateOrCreate(
                    ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                    [
                        'our_sku' => $sku,
                        'push_status' => 'failed',
                        'pushed' => 0,
                        'created_by' => $userId,
                    ]
                );
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'row_id' => $rowId,
                    'sku' => $sku,
                    'message' => 'No location found'
                ]);
            }

            // Rate limiting
            usleep(550000);

            // Adjust Shopify qty with retry
            $adjustResponse = $this->makeShopifyRequestWithRetry(function() use ($inventoryItemId, $locationId, $qty) {
                return Http::withBasicAuth(
                    config('services.shopify.api_key'), 
                    config('services.shopify.password')
                )->timeout(30)->post(
                    "https://" . config('services.shopify.store_url') . "/admin/api/2025-01/inventory_levels/adjust.json",
                    [
                        'inventory_item_id' => $inventoryItemId,
                        'location_id' => $locationId,
                        'available_adjustment' => $qty,
                    ]
                );
            }, $sku, 'adjust');

            if (!$adjustResponse || !$adjustResponse->successful()) {
                InventoryWarehouse::updateOrCreate(
                    ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                    [
                        'our_sku' => $sku,
                        'push_status' => 'failed',
                        'pushed' => 0,
                        'created_by' => $userId,
                    ]
                );
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'row_id' => $rowId,
                    'sku' => $sku,
                    'message' => 'Failed to adjust inventory in Shopify'
                ]);
            }

            // Success - Store in DB
            InventoryWarehouse::updateOrCreate(
                ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                [
                    'our_sku' => $sku,
                    'push_status' => 'success',
                    'pushed' => 1,
                    'created_by' => $userId,
                    'supplier_name' => $row['supplier_name'] ?? null,
                    'company_name' => $row['company_name'] ?? null,
                    'no_of_units' => $units,
                    'total_ctn' => $ctns,
                    'parent' => $row['parent'] ?? null,
                    'rate' => !empty($row['rate']) ? (float)$row['rate'] : null,
                    'unit' => $row['unit'] ?? null,
                    'status' => $row['status'] ?? null,
                    'changes' => $row['changes'] ?? null,
                    'values' => $row['values'] ?? null,
                    'package_size' => $row['package_size'] ?? null,
                    'product_size_link' => $row['product_size_link'] ?? null,
                    'comparison_link' => $row['comparison_link'] ?? null,
                    'order_link' => $row['order_link'] ?? null,
                    'image_src' => $row['image_src'] ?? null,
                    'photos' => $row['photos'] ?? null,
                    'specification' => $row['specification'] ?? null,
                    'supplier_names' => $row['supplier_names'] ?? [],
                ]
            );

            return response()->json([
                'success' => true,
                'status' => 'success',
                'row_id' => $rowId,
                'sku' => $sku,
                'message' => 'Successfully pushed to Shopify'
            ]);

        } catch (\Exception $e) {
            Log::error("EXCEPTION while processing single SKU", [
                'sku' => $sku,
                'row_id' => $rowId,
                'error_message' => $e->getMessage(),
            ]);

            InventoryWarehouse::updateOrCreate(
                ['tab_name' => $tabName, 'transit_container_id' => $rowId],
                [
                    'our_sku' => $sku,
                    'push_status' => 'failed',
                    'pushed' => 0,
                    'created_by' => $userId,
                ]
            );

            return response()->json([
                'success' => false,
                'status' => 'failed',
                'row_id' => $rowId,
                'sku' => $sku,
                'message' => 'Exception: ' . $e->getMessage()
            ]);
        }
    }


    // public function pushInventory(Request $request)
    // {
    //     $tabName = $request->input('tab_name');
    //     $rows = $request->input('data', []);

    //     $alreadyPushed = [];
    //     $notFound = [];
    //     $pushedRows = [];

    //     // ✅ UPDATED: Get all pushed row IDs for this tab
    //     $existingPushed = InventoryWarehouse::where('tab_name', $tabName)
    //         ->where('pushed', 1)
    //         ->pluck('transit_container_id')
    //         ->toArray();

    //     // ✅ UPDATED: Fetch all Shopify products ONCE (avoid per-SKU requests)
    //     $skuInventoryMap = [];
    //     $pageInfo = null;

    //     do {
    //         $queryParams = ['limit' => 250];
    //         if ($pageInfo) $queryParams['page_info'] = $pageInfo;

    //         $response = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //             ->get("https://" . config('services.shopify.store_url') . "/admin/api/2025-01/products.json", $queryParams);

    //         $products = $response->json('products') ?? [];

    //         foreach ($products as $product) {
    //             foreach ($product['variants'] as $variant) {
    //                 $skuKey = $this->normalizeSku($variant['sku'] ?? '');
    //                 if ($skuKey) {
    //                     $skuInventoryMap[$skuKey] = $variant['inventory_item_id'];
    //                 }
    //             }
    //         }

    //         // handle pagination
    //         $linkHeader = $response->header('Link');
    //         $pageInfo = null;
    //         if ($linkHeader && preg_match('/<([^>]+page_info=([^&>]+)[^>]*)>; rel="next"/', $linkHeader, $matches)) {
    //             $pageInfo = $matches[2];
    //         }
    //     } while ($pageInfo);

    //     // ✅ Loop each selected SKU
    //     foreach ($rows as $row) {
    //         $rowId = $row['id'] ?? null;
    //         $sku = $this->normalizeSku($row['our_sku'] ?? '');
    //         $units = !empty($row['no_of_units']) ? (int)$row['no_of_units'] : 0;
    //         $ctns  = !empty($row['total_ctn']) ? (int)$row['total_ctn'] : 0;
    //         $qty = $units * $ctns;

    //         if (!$rowId || !$sku || $qty <= 0) continue;

    //         // ✅ Skip if already pushed
    //         if (in_array($rowId, $existingPushed, true)) {
    //             $alreadyPushed[] = $sku;
    //             continue;
    //         }

    //         try {
    //             // ✅ Use pre-fetched inventory map
    //             $inventoryItemId = $skuInventoryMap[$sku] ?? null;

    //             if (!$inventoryItemId) {
    //                 $notFound[] = $sku;
    //                 continue;
    //             }

    //             // ✅ Get location_id
    //             $invLevelResponse = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //                 ->get("https://" . config('services.shopify.store_url') . "/admin/api/2025-01/inventory_levels.json", [
    //                     'inventory_item_ids' => $inventoryItemId,
    //                 ]);

    //             $levels = $invLevelResponse->json('inventory_levels') ?? [];
    //             $locationId = $levels[0]['location_id'] ?? null;

    //             if (!$locationId) {
    //                 $notFound[] = $sku;
    //                 continue;
    //             }

    //             // ✅ Adjust Shopify qty
    //             $adjustResponse = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //                 ->post("https://" . config('services.shopify.store_url') . "/admin/api/2025-01/inventory_levels/adjust.json", [
    //                     'inventory_item_id' => $inventoryItemId,
    //                     'location_id' => $locationId,
    //                     'available_adjustment' => $qty,
    //                 ]);

    //             if (!$adjustResponse->successful()) {
    //                 Log::error("❌ Failed to adjust inventory for SKU {$sku}: ", $adjustResponse->json());
    //                 continue;
    //             }

    //             // ✅ Store in DB
    //             InventoryWarehouse::updateOrCreate(
    //                 ['tab_name' => $tabName, 'transit_container_id' => $rowId],
    //                 [
    //                     'our_sku' => $sku,
    //                     'pushed' => 1,
    //                     'supplier_name' => $row['supplier_name'] ?? null,
    //                     'company_name' => $row['company_name'] ?? null,
    //                     'no_of_units' => $units,
    //                     'total_ctn' => $ctns,
    //                     'parent' => $row['parent'] ?? null,
    //                     'rate' => !empty($row['rate']) ? (float)$row['rate'] : null,
    //                     'unit' => $row['unit'] ?? null,
    //                     'status' => $row['status'] ?? null,
    //                     'changes' => $row['changes'] ?? null,
    //                     'values' => $row['values'] ?? null,
    //                     'package_size' => $row['package_size'] ?? null,
    //                     'product_size_link' => $row['product_size_link'] ?? null,
    //                     'comparison_link' => $row['comparison_link'] ?? null,
    //                     'order_link' => $row['order_link'] ?? null,
    //                     'image_src' => $row['image_src'] ?? null,
    //                     'photos' => $row['photos'] ?? null,
    //                     'specification' => $row['specification'] ?? null,
    //                     'supplier_names' => $row['supplier_names'] ?? [],
    //                 ]
    //             );

    //             $pushedRows[] = ['row_id' => $rowId, 'sku' => $sku];
    //             $existingPushed[] = $rowId;

    //         } catch (\Exception $e) {
    //             Log::error("PushInventory exception for SKU {$sku}: " . $e->getMessage());
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Inventory push completed.',
    //         'skipped' => $alreadyPushed,
    //         'not_found' => $notFound,
    //         'pushed' => $pushedRows,
    //     ]);
    // }




}
