<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ShopifySku;

class ShopifyApiInventoryController extends Controller
{
    protected $shopifyApiKey;
    protected $shopifyPassword;
    protected $shopifyStoreUrl;


    protected $shopifyStoreUrlName;
    protected $shopifyAccessToken;

    public function __construct()
    {
        $this->shopifyApiKey = config('services.shopify.api_key');
        $this->shopifyPassword = config('services.shopify.password');
        $this->shopifyStoreUrl = str_replace(
            ['https://', 'http://'],
            '',
            config('services.shopify.store_url')
        );
        $this->shopifyStoreUrlName = env('SHOPIFY_STORE_URL');
        $this->shopifyAccessToken = env('SHOPIFY_PASSWORD');
    }

    /**
     * Helper for Shopify GET requests with retry/backoff on 429 and 5xx.
     */
    private function shopifyGet(string $url, array $params = [])
    {
        $maxAttempts = 12;
        $attempt = 0;
        $delayMs = 2000; // starting backoff

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                    'Content-Type' => 'application/json'
                ])->timeout(120)->get($url, $params);

                if ($response->successful()) {
                    if ($attempt > 1) {
                        Log::info('shopifyGet: success after retries', ['url' => $url, 'attempt' => $attempt]);
                    }
                    // Log rate headers on success for visibility
                    $limitHeader = $response->header('X-Shopify-Shop-Api-Call-Limit');
                    if ($limitHeader) {
                        Log::info('shopifyGet: rate header', ['url' => $url, 'limit' => $limitHeader]);
                    }
                    return $response;
                }

                $status = $response->status();

                // If rate-limited or server error, back off and retry
                if ($status === 429 || $status >= 500) {
                    $retryAfter = $response->header('Retry-After');
                    $limitHeader = $response->header('X-Shopify-Shop-Api-Call-Limit');
                    Log::warning('shopifyGet: received rate/server status, will retry', ['url' => $url, 'status' => $status, 'attempt' => $attempt, 'limit' => $limitHeader, 'retry_after' => $retryAfter]);

                    // If server provided Retry-After, respect it (seconds)
                    if ($retryAfter !== null && is_numeric($retryAfter)) {
                        $sleepSec = (float) $retryAfter + (rand(100, 500) / 1000); // add 100-500ms jitter
                        usleep((int)($sleepSec * 1000000));
                    } else {
                        // Sleep with exponential backoff + jitter (convert ms to microseconds)
                        $jitter = rand(100, 500); // ms
                        usleep(($delayMs + $jitter) * 1000);
                        $delayMs *= 2;
                    }

                    continue;
                }

                // For other 4xx errors, don't retry
                Log::error('shopifyGet: non-retriable response', ['url' => $url, 'status' => $status, 'body' => $response->body()]);
                return $response;

            } catch (\Exception $e) {
                // Network/timeout exceptions: log and backoff with jitter
                Log::warning('shopifyGet exception, will retry', ['url' => $url, 'err' => $e->getMessage(), 'attempt' => $attempt]);
                $jitter = rand(100, 500);
                usleep(($delayMs + $jitter) * 1000);
                $delayMs *= 2;
                continue;
            }
        }

        Log::error('shopifyGet: exhausted retries', ['url' => $url]);
        // Final attempt without swallowing exception — return last response or throw
        try {
            return Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json'
            ])->timeout(120)->get($url, $params);
        } catch (\Exception $e) {
            Log::error('shopifyGet final attempt failed', ['url' => $url, 'err' => $e->getMessage()]);
            throw $e;
        }
    }

    public function saveDailyInventory()
    {
        try {
            $startTime = microtime(true);
            Log::info('Starting Shopify inventory sync');

            $endDate = Carbon::now()->endOfDay();
            $startDate = Carbon::now()->subDays(30)->startOfDay();

            // Get ALL SKUs (including paginated products)
            $inventoryData = $this->getAllInventoryData();
            $inventoryCount = count($inventoryData);
            Log::info('Fetched ' . $inventoryCount . ' SKUs from products');
            
            // Safety check: ensure we got a reasonable number of SKUs
            if ($inventoryCount < 10) {
                Log::critical('saveDailyInventory: Too few SKUs fetched, aborting to prevent data loss', ['count' => $inventoryCount]);
                return false;
            }

            // Fetch orders for the period
            $ordersData = $this->fetchAllPages($startDate, $endDate);
            Log::info('Fetched ' . count($ordersData['orders']) . ' order items');

            // Process and save data
            $simplifiedData = $this->processSimplifiedData($ordersData['orders'], $inventoryData);
            
            // Verify we didn't lose SKUs during processing
            if (count($simplifiedData) < $inventoryCount * 0.9) {
                Log::critical('saveDailyInventory: Lost too many SKUs during processing', [
                    'original' => $inventoryCount,
                    'processed' => count($simplifiedData)
                ]);
                return false;
            }
            
            $this->saveSkus($simplifiedData);

            // Update on_hand directly from products API data (inventoryData)
            if (!empty($inventoryData)) {
                $updateCount = 0;
                DB::transaction(function () use ($inventoryData, &$updateCount) {
                    foreach ($inventoryData as $rawSku => $data) {
                            // Store SKU exactly as it comes from Shopify
                            $sku = $rawSku;
                            
                            if (empty($sku)) {
                                continue;
                            }

                            // Debug specific SKU
                            if (stripos($sku, 'SS HD 2PK ORG WOB') !== false || stripos($sku, 'SS ECO 2PK BLK') !== false) {
                                Log::info('=== saveDailyInventory: Updating SKU from products API ===', [
                                    'sku' => $sku,
                                    'on_hand_from_products' => $data['on_hand'] ?? 0,
                                    'available_to_sell' => $data['available_to_sell'] ?? 0,
                                    'image_src' => $data['image_src'] ?? null,
                                ]);
                            }

                            $affected = ShopifySku::where('sku', $sku)->update([
                                'on_hand'          => $data['on_hand'] ?? 0,
                                'available_to_sell' => $data['available_to_sell'] ?? 0,
                                'image_src'        => $data['image_src'] ?? null,
                                'updated_at'       => now(),
                            ]);
                            
                            if ($affected > 0) {
                                $updateCount++;
                            }

                            if (stripos($sku, 'SS HD 2PK ORG WOB') !== false || stripos($sku, 'SS ECO 2PK BLK') !== false) {
                                Log::info('=== saveDailyInventory: DB rows updated ===', ['affected_rows' => $affected]);
                            }
                        }
                });
                
                Log::info('saveDailyInventory: on_hand updates completed', [
                    'total_skus_from_api' => count($inventoryData),
                    'db_rows_updated' => $updateCount
                ]);
                
                Cache::forget('shopify_skus_list');
            } else {
                Log::warning('No inventory data from products API to update.');
            }

            $duration = round(microtime(true) - $startTime, 2);
            return true;
        } catch (\Exception $e) {
            Log::error('Shopify Inventory Error: ' . $e->getMessage());
            return false;
        }
    }

    protected function getAllInventoryData(): array
    {
        $inventoryData = [];
        $pageInfo = null;
        $hasMore = true;
        $pageCount = 0;
        $totalProducts = 0;
        $totalVariants = 0;
        $consecutiveFailures = 0;
        $maxConsecutiveFailures = 3;

        while ($hasMore) {
            $pageCount++;
            // Include price and compare_at_price in fields to get both B2B and B2C prices
            $queryParams = ['limit' => 250, 'fields' => 'id,title,handle,variants,image,images'];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $url = "https://{$this->shopifyStoreUrl}/admin/api/2025-01/products.json";
            $response = $this->shopifyGet($url, $queryParams);

            if (!$response->successful()) {
                $consecutiveFailures++;
                Log::error("Failed to fetch products (Page {$pageCount}, Attempt {$consecutiveFailures}): " . $response->body());
                
                if ($consecutiveFailures >= $maxConsecutiveFailures) {
                    Log::critical("Aborting getAllInventoryData after {$consecutiveFailures} consecutive failures at page {$pageCount}");
                    break;
                }
                
                // Wait and retry current page
                sleep(5);
                continue;
            }
            
            // Reset failure counter on success
            $consecutiveFailures = 0;

            $products = $response->json()['products'] ?? [];
            $productCount = count($products);
            $totalProducts += $productCount;

            Log::info("Page {$pageCount} fetched successfully. Products: {$productCount}");

            foreach ($products as $product) {
                foreach ($product['variants'] as $variant) {
                    $totalVariants++;
                    $imageUrl = $this->sanitizeImageUrl(
                        $product['image']['src']
                            ?? (!empty($product['images']) ? $product['images'][0]['src'] : null)
                    );

                    if (!empty($variant['sku'])) {
                        // B2C price is the standard price (public price)
                        $b2cPrice = $variant['price'] ?? null;
                        
                        // B2B price can come from compare_at_price (wholesale) or metafields
                        // For now, we'll use compare_at_price if available, otherwise null
                        // You can extend this to fetch from metafields if needed
                        $b2bPrice = !empty($variant['compare_at_price']) ? $variant['compare_at_price'] : null;
                        
                        // Create product link using handle
                        $productHandle = $product['handle'] ?? null;
                        if ($productHandle && $this->shopifyStoreUrlName) {
                            // Remove protocol if present in store URL
                            $storeUrl = str_replace(['https://', 'http://'], '', $this->shopifyStoreUrlName);
                            $productLink = "https://{$storeUrl}/products/{$productHandle}";
                            
                            // Debug first product
                            if ($totalVariants === 1) {
                                Log::info('=== Product Link Creation DEBUG ===', [
                                    'shopifyStoreUrlName' => $this->shopifyStoreUrlName,
                                    'storeUrl' => $storeUrl,
                                    'productHandle' => $productHandle,
                                    'productLink' => $productLink,
                                ]);
                            }
                        } else {
                            $productLink = null;
                            // Debug why link is null
                            if ($totalVariants === 1) {
                                Log::warning('=== Product Link is NULL ===', [
                                    'productHandle' => $productHandle,
                                    'shopifyStoreUrlName' => $this->shopifyStoreUrlName,
                                ]);
                            }
                        }
                        
                        $inventoryData[$variant['sku']] = [
                            'variant_id'        => $variant['id'],
                            'inventory'         => $variant['inventory_quantity'] ?? 0,
                            'product_title'     => $product['title'] ?? '',
                            'sku'               => $variant['sku'] ?? '',
                            'variant_title'     => $variant['title'] ?? '',
                            'product_link'      => $productLink,
                            'inventory_item_id' => $variant['inventory_item_id'],
                            'on_hand'           => $variant['old_inventory_quantity'] ?? 0,   // OnHand
                            'available_to_sell' => $variant['inventory_quantity'] ?? 0,       // AvailableToSell
                            'price'             => $variant['price'],
                            'b2b_price'         => $b2bPrice,
                            'b2c_price'         => $b2cPrice,
                            'image_src'         => $imageUrl,
                        ];

                        // Log first 3 SKUs + images per page (to avoid huge logs)
                        if ($totalVariants <= 3 || $totalVariants % 500 === 0) {
                            Log::info("Variant preview", [
                                'product_title' => $product['title'] ?? '',
                                'sku'           => $variant['sku'],
                                'image'         => $imageUrl,
                                'product_link'  => $productLink,
                                'handle'        => $productHandle,
                            ]);
                        }
                    } else {
                        Log::warning('Variant without SKU', [
                            'product_id' => $product['id'],
                            'variant_id' => $variant['id'],
                            'on_hand'    => $variant['old_inventory_quantity'] ?? 0,
                            'available_to_sell' => $variant['inventory_quantity'] ?? 0,
                            'image'      => $imageUrl,
                        ]);
                    }
                }
            }

            // Pagination handling
            $pageInfo = $this->getNextPageInfo($response);
            $hasMore = (bool) $pageInfo;

            // Avoid rate limiting - increased delay to 4 seconds
            if ($hasMore) {
                Log::info("Waiting 4s before next page...");
                usleep(6000000); // 4s delay
            }
        }


        return $inventoryData;
    }

    /**
     * Clean Shopify image URL
     */
    protected function sanitizeImageUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Remove line breaks and spaces
        $cleanUrl = trim(preg_replace('/\s+/', '', $url));

        // Remove ?v= query string (Shopify versioning param)
        $cleanUrl = strtok($cleanUrl, '?');

        return $cleanUrl;
    }


    public function fetchInventoryWithCommitment(): array
    {
        set_time_limit(500);
        $shopUrl = 'https://' . env('SHOPIFY_STORE_URL');
        $token = env('SHOPIFY_PASSWORD'); 

        // Step 1: Get Ohio Location ID
        $locationId = null;
        $locationResponse = $this->shopifyGet("$shopUrl/admin/api/2025-01/locations.json");

        if ($locationResponse->successful()) {
            foreach ($locationResponse->json('locations') as $loc) {
                if (stripos($loc['name'], 'Ohio') !== false) {
                    $locationId = $loc['id'];
                    Log::info('Matched Ohio location ID', ['id' => $locationId]);
                    break;
                }
            }
        }

        // Rate limiting delay
        usleep(6000000); // 4s delay

        if (!$locationId) {
            Log::error('Ohio location not found.');
            return [];
        }

        // Step 2: Fetch ALL Products (with pagination)
        $skuMap = [];
        $imageMap = [];
        $nextPageUrl = "$shopUrl/admin/api/2025-01/products.json?limit=250&fields=variants,image,title,handle,id";
        $pageCount = 0;
        $maxPages = 500; // Safety limit

        do {
            $pageCount++;
            
            if ($pageCount > $maxPages) {
                Log::error('fetchInventoryWithCommitment: Exceeded max pages', ['pages' => $pageCount]);
                break;
            }
            
            $response = $this->shopifyGet($nextPageUrl);

            if (!$response->successful()) {
                Log::error('Failed to fetch products', ['url' => $nextPageUrl, 'page' => $pageCount]);
                break;
            }

            $products = $response->json('products');
            
            if (empty($products)) {
                Log::info('No more products to fetch', ['page' => $pageCount]);
                break;
            }
            
            foreach ($products as $product) {
                $mainImage = $product['image']['src'] ?? null;

                foreach ($product['variants'] as $variant) {
                    // Store SKU exactly as it comes from Shopify - no normalization
                    $sku = $variant['sku'] ?? '';
                    $iid = $variant['inventory_item_id'];

                    if (!empty($sku)) {
                        $skuMap[$sku] = $iid;
                        $imageMap[$sku] = $mainImage;

                        // Log specific SKU for debugging
                        if (stripos($sku, 'SS HD 2PK ORG WOB') !== false || stripos($sku, 'SS ECO 2PK BLK') !== false) {
                            Log::info('=== fetchInventoryWithCommitment: Found SKU ===', [
                                'sku' => $sku,
                                'inventory_item_id' => $iid,
                                'product_id' => $product['id'],
                                'product_title' => $product['title'] ?? null,
                                'image_url' => $mainImage,
                            ]);
                        }
                    }
                }
            }

            $linkHeader = $response->header('Link');
            $nextPageUrl = null;
            if ($linkHeader && preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
                $nextPageUrl = $matches[1];
            }

            // Rate limiting delay between product pages
            if ($nextPageUrl) {
                usleep(6000000); // 4s delay
            }
        } while ($nextPageUrl);

        // Step 3: Fetch Inventory Levels (only from Ohio)
        $availableByIid = [];
        $chunks = array_chunk(array_values($skuMap), 50);

        foreach ($chunks as $chunk) {
            $invResponse = $this->shopifyGet("$shopUrl/admin/api/2024-01/inventory_levels.json", [
                'inventory_item_ids' => implode(',', $chunk),
                'location_ids' => $locationId,
            ]);

            if (!$invResponse->successful()) {
                Log::error('Failed to fetch inventory levels', ['body' => $invResponse->body()]);
                continue;
            }

            foreach ($invResponse->json('inventory_levels') ?? [] as $level) {
                $iid = $level['inventory_item_id'];
                $availableByIid[$iid] = ($availableByIid[$iid] ?? 0) + $level['available'];
            }

            // Rate limiting delay between inventory chunks
            usleep(6000000); // 4s delay
        }

        // Step 4: Fetch Committed Quantities from Orders
        $committedBySku = [];
        $orderResponse = $this->shopifyGet("$shopUrl/admin/api/2024-01/orders.json", [
            'status' => 'open',
            'fulfillment_status' => 'unfulfilled',
            'limit' => 250,
        ]);

        if ($orderResponse->successful()) {
            foreach ($orderResponse->json('orders') ?? [] as $order) {
                foreach ($order['line_items'] as $item) {
                    // Store SKU exactly as it comes from Shopify - no normalization
                    $sku = $item['sku'] ?? '';
                    $qty = (int) $item['quantity'];
                    if (!empty($sku)) {
                        $committedBySku[$sku] = ($committedBySku[$sku] ?? 0) + $qty;
                    }
                }
            }
        } else {
            Log::error('Failed to fetch orders');
        }

        // Rate limiting delay before final processing
        usleep(6000000); // 6s delay

        // Step 5: Merge Final Inventory
        $final = [];
        foreach ($skuMap as $sku => $iid) {
            $available = $availableByIid[$iid] ?? 0;
            $committed = $committedBySku[$sku] ?? 0;
            $onHand = $available + $committed;

            $final[$sku] = [
                'available_to_sell' => $available,
                'committed' => $committed,
                'on_hand' => $onHand,
                'image_url' => $imageMap[$sku] ?? null,
            ];

            if (stripos($sku, 'SS HD 2PK ORG WOB') !== false || stripos($sku, 'SS ECO 2PK BLK') !== false) {
                Log::info('=== fetchInventoryWithCommitment: Final inventory for SKU ===', [
                    'sku' => $sku,
                    'inventory_item_id' => $iid,
                    'available_from_levels' => $available,
                    'committed_from_orders' => $committed,
                    'calculated_on_hand' => $onHand,
                    'image_url' => $imageMap[$sku] ?? null,
                ]);
            }
        }


        return $final;
    }




    public function getInventoryArray(): array
    {
        return $this->getAccurateInventoryCountsFromShopify();
    }


    protected function fetchAllPages(Carbon $startDate, Carbon $endDate, ?string $sku = null): array
    {
        $allOrders = [];
        $pageInfo = null;
        $hasMore = true;
        $attempts = 0;
        $pageCount = 0;

        while ($hasMore && $attempts < 3) {
            $pageCount++;
            
            try {
                $response = $this->makeApiRequest($startDate, $endDate, $sku, $pageInfo);

                if ($response->successful()) {
                    $orders = $response->json()['orders'] ?? [];
                    $filteredOrders = $this->filterOrders($orders, $sku);
                    $allOrders = array_merge($allOrders, $filteredOrders);

                    $pageInfo = $this->getNextPageInfo($response);
                    $hasMore = (bool) $pageInfo;
                    $attempts = 0;

                    if ($hasMore) {
                        usleep(6000000); // 4s delay
                    }
                } else {
                    $attempts++;
                    Log::warning("Order fetch attempt {$attempts} failed: " . $response->body());
                    sleep(2);
                }
            } catch (\Exception $e) {
                $attempts++;
                Log::error("Order fetch exception on attempt {$attempts}: " . $e->getMessage());
                if ($attempts >= 3) {
                    break;
                }
                sleep(3);
            }
        }

        Log::info("Fetched orders from {$pageCount} pages");
        return [
            'orders' => $allOrders,
            'totalResults' => count($allOrders)
        ];
    }

    protected function makeApiRequest(Carbon $startDate, Carbon $endDate, ?string $sku = null, ?string $pageInfo = null)
    {
        $queryParams = [
            'limit' => 250,
            'fields' => 'id,line_items,created_at'
        ];

        if ($pageInfo) {
            $queryParams['page_info'] = $pageInfo;
        } else {
            $queryParams = array_merge($queryParams, [
                'created_at_min' => $startDate->format('Y-m-d\TH:i:sP'),
                'created_at_max' => $endDate->format('Y-m-d\TH:i:sP'),
                'status' => 'any'
            ]);

            if ($sku) {
                $queryParams['line_items_sku'] = $sku;
            }
        }

        $url = "https://{$this->shopifyStoreUrl}/admin/api/2025-01/orders.json";
        return $this->shopifyGet($url, $queryParams);
    }

    protected function filterOrders(array $orders, ?string $sku): array
    {
        $filtered = [];

        foreach ($orders as $order) {
            foreach ($order['line_items'] ?? [] as $lineItem) {
                if (!empty($lineItem['sku']) && (!$sku || $lineItem['sku'] === $sku)) {
                    $filtered[] = [
                        'quantity' => $lineItem['quantity'],
                        'sku' => $lineItem['sku'],
                        'order_id' => $order['id'],
                        'created_at' => $order['created_at']
                    ];
                }
            }
        }

        return $filtered;
    }

    protected function getNextPageInfo($response): ?string
    {
        if ($response->hasHeader('Link') && str_contains($response->header('Link'), 'rel="next"')) {
            $links = explode(',', $response->header('Link'));
            foreach ($links as $link) {
                if (str_contains($link, 'rel="next"')) {
                    preg_match('/<(.*)>; rel="next"/', $link, $matches);
                    parse_str(parse_url($matches[1], PHP_URL_QUERY), $query);
                    return $query['page_info'] ?? null;
                }
            }
        }
        return null;
    }

    protected function processSimplifiedData(array $orders, array $inventoryData): array
    {
        $groupedData = [];

        // Initialize all SKUs with inventory data
        foreach ($inventoryData as $sku => $data) {
            $groupedData[$sku] = [
                'variant_id' => $data['variant_id'],
                'sku' => $sku,
                'quantity' => 0,
                'inventory' => $data['inventory'],
                'price' => $data['price'],
                'b2b_price' => $data['b2b_price'] ?? null,
                'b2c_price' => $data['b2c_price'] ?? null,
                'image_src' => $data['image_src'],
                'product_title' => $data['product_title'] ?? null,
                'variant_title' => $data['variant_title'] ?? null,
                'product_link' => $data['product_link'] ?? null
            ];
        }

        // Update quantities for SKUs that had sales
        foreach ($orders as $order) {
            $sku = $order['sku'];
            if (isset($groupedData[$sku])) {
                $groupedData[$sku]['quantity'] += $order['quantity'];
            } else {
                Log::warning('Order SKU not found in products', ['sku' => $sku]);
            }
        }

        ksort($groupedData);
        return array_values($groupedData);
    }

    protected function saveSkus(array $simplifiedData)
    {
        DB::transaction(function () use ($simplifiedData) {
            $skusToUpdate = [];
            
            // Collect all SKUs we're about to update
            foreach ($simplifiedData as $item) {
                $sku = $item['sku'] ?? '';
                if (!empty($sku)) {
                    $skusToUpdate[] = $sku;
                }
            }
            
            // Only reset SKUs that we're actively updating (not ALL SKUs in table)
            if (!empty($skusToUpdate)) {
                ShopifySku::whereIn('sku', $skusToUpdate)->update([
                    'inv' => 0,
                    'quantity' => 0,
                    'price' => null,
                    'b2b_price' => null,
                    'b2c_price' => null,
                ]);
            }

            // Batch processing for better performance
            $updateCount = 0;
            foreach (array_chunk($simplifiedData, 1000) as $chunk) {
                foreach ($chunk as $item) {
                    // Store SKU exactly as it comes from Shopify - no normalization
                    $sku = $item['sku'] ?? '';
                    
                    if (empty($sku)) {
                        continue;
                    }
                    
                    // Debug first 3 SKUs to see if product_link is present
                    if ($updateCount < 3) {
                        Log::info('=== saveSkus DEBUG ===', [
                            'sku' => $sku,
                            'product_title' => $item['product_title'] ?? null,
                            'product_link' => $item['product_link'] ?? null,
                        ]);
                    }

                    if (stripos($sku, 'SS HD 2PK ORG WOB') !== false || stripos($sku, 'SS ECO 2PK BLK') !== false) {
                        Log::info('=== saveSkus: Saving SKU ===', [
                            'sku' => $sku,
                            'variant_id' => $item['variant_id'],
                            'quantity_L30' => $item['quantity'],
                            'inv' => $item['inventory'],
                            'price' => $item['price'],
                            'b2b_price' => $item['b2b_price'] ?? null,
                            'b2c_price' => $item['b2c_price'] ?? null,
                            'image_src' => $item['image_src'],
                        ]);
                    }

                    ShopifySku::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'sku' => $sku,
                            'quantity' => $item['quantity'],
                            'variant_id' => $item['variant_id'],
                            'inv' => $item['inventory'],
                            'price' => $item['price'],
                            'b2b_price' => $item['b2b_price'] ?? null,
                            'b2c_price' => $item['b2c_price'] ?? null,
                            'image_src' => $item['image_src'],
                            'product_title' => $item['product_title'] ?? null,
                            'variant_title' => $item['variant_title'] ?? null,
                            'product_link' => $item['product_link'] ?? null,
                            'updated_at' => now()
                        ]
                    );
                    $updateCount++;
                }
            }
            
            Log::info('saveSkus completed', ['updated_count' => $updateCount]);
        });

        Cache::forget('shopify_skus_list');
    }

    /**
     * Fetch live inventory (available_to_sell, committed, on_hand) from Shopify
     * and persist those values into `shopify_skus` table.
     *
     * This uses the existing fetchInventoryWithCommitment() which returns
     * an array keyed by normalized SKU.
     */
    public function syncLiveInventoryToDb(): bool
    {
        try {
            $live = $this->fetchInventoryWithCommitment();

            if (empty($live)) {
                Log::warning('No live inventory returned from Shopify (sync skipped).');
                return false;
            }
            
            // Safety check: ensure we got a reasonable number of SKUs
            $liveCount = count($live);
            if ($liveCount < 10) {
                Log::critical('syncLiveInventoryToDb: Too few SKUs returned, aborting', ['count' => $liveCount]);
                return false;
            }
            
            Log::info('syncLiveInventoryToDb: Starting sync', ['sku_count' => $liveCount]);

            $updatedCount = 0;
            DB::transaction(function () use ($live, &$updatedCount) {
                // Process in chunks for memory safety
                $chunks = array_chunk($live, 1000, true);
                foreach ($chunks as $chunk) {
                    foreach ($chunk as $sku => $data) {
                        if (empty($sku)) {
                            continue;
                        }
                        
                        // Store SKU exactly as it comes from Shopify - no normalization
                        ShopifySku::updateOrCreate(
                            ['sku' => $sku],
                            [
                                'available_to_sell' => $data['available_to_sell'] ?? 0,
                                'committed' => $data['committed'] ?? 0,
                                'on_hand' => $data['on_hand'] ?? 0,
                                'image_src' => $data['image_url'] ?? null,
                                'updated_at' => now(),
                            ]
                        );
                        $updatedCount++;
                    }
                }
            });

            Cache::forget('shopify_skus_list');
            Log::info('Synced live inventory to shopify_skus table', [
                'total_from_api' => count($live),
                'db_updated' => $updatedCount
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to sync live inventory to DB: ' . $e->getMessage());
            return false;
        }
    }

    protected function getInventoryLevels(array $inventoryItemIds): array
    {
        $inventoryLevels = [];
        $chunks = array_chunk($inventoryItemIds, 50);

        foreach ($chunks as $chunk) {
            $query = http_build_query(['inventory_item_ids' => implode(',', $chunk)]);

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json'
            ])
                ->get("https://{$this->shopifyStoreUrl}/admin/api/2025-01/inventory_levels.json?$query");

            if ($response->successful()) {
                foreach ($response->json()['inventory_levels'] as $level) {
                    $inventoryLevels[$level['inventory_item_id']] = [
                        'available' => $level['available'],
                        'location_id' => $level['location_id'],
                    ];
                }
            }

            // Rate limiting delay between chunks
            usleep(1000000); // 1s delay
        }

        return $inventoryLevels;
    }

    protected function getCommittedQuantities(): array
    {
        $committed = [];
        $pageInfo = null;
        $hasMore = true;

        while ($hasMore) {
            $queryParams = ['status' => 'open', 'limit' => 250, 'fields' => 'line_items'];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json'
            ])
                ->get("https://{$this->shopifyStoreUrl}/admin/api/2025-01/orders.json", $queryParams);

            if (!$response->successful()) {
                break;
            }

            foreach ($response->json()['orders'] as $order) {
                foreach ($order['line_items'] as $item) {
                    $variantId = $item['variant_id'];
                    $committed[$variantId] = ($committed[$variantId] ?? 0) + $item['quantity'];
                }
            }

            $pageInfo = $this->getNextPageInfo($response);
            $hasMore = (bool) $pageInfo;

            // Rate limiting delay
            if ($hasMore) {
                usleep(1000000); // 1s delay
            }
        }

        return $committed;
    }
}
