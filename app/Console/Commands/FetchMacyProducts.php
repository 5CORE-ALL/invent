<?php

namespace App\Console\Commands;

use App\Models\BestbuyUsaProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\MacyProduct;
use App\Models\TiendamiaProduct;
use App\Models\PurchasingPowerProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FetchMacyProducts extends Command
{
    /**  
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-macy-products {--pp-mcm-only : Only sync Purchasing Power prices from MCM OF21}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store Macy products data';

    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //     $token = $this->getAccessToken();
    //     if (!$token) return;

    //     // Step 1: Mass-fetch all orders once
    //     $skuSales = $this->getSalesTotals($token); // ['sku' => ['m_l30' => 12, 'm_l60' => 4]]

    //     // Step 2: Paginate through products
    //     $pageToken = null;
    //     $page = 1;

    //     do {
    //         $this->info("Fetching product page $page...");
    //         $url = 'https://miraklconnect.com/api/products?limit=1000';
    //         if ($pageToken) {
    //             $url .= '&page_token=' . urlencode($pageToken);
    //         }

    //         $response = Http::withToken($token)->get($url);
    //         if (!$response->successful()) {
    //             $this->error('Product fetch failed: ' . $response->body());
    //             return;
    //         }

    //         $json = $response->json();
    //         $products = $json['data'] ?? [];
    //         $pageToken = $json['next_page_token'] ?? null;

    //         foreach ($products as $product) {
    //             $sku = $product['id'] ?? null;
    //             $price = $product['discount_prices'][0]['price']['amount'] ?? null;

    //             if (!$sku || $price === null) continue;

    //             $m_l30 = $skuSales[$sku]['m_l30'] ?? 0;
    //             $m_l60 = $skuSales[$sku]['m_l60'] ?? 0;

    //             MacyProduct::updateOrCreate(
    //                 ['sku' => $sku],
    //                 [
    //                     'price' => $price,
    //                     'm_l30' => $m_l30,
    //                     'm_l60' => $m_l60,
    //                 ]
    //             );
    //         }

    //         $page++;
    //     } while ($pageToken);

    //     $this->info("All Macy products stored successfully");
    // }

    // private function getAccessToken()
    // {
    //     return Cache::remember('macy_access_token', 3500, function () {
    //         $response = Http::asForm()->post('https://auth.mirakl.net/oauth/token', [
    //             'grant_type' => 'client_credentials',
    //             'client_id' => config('services.macy.client_id'),
    //             'client_secret' => config('services.macy.client_secret'),
    //         ]);

    //         return $response->successful()
    //             ? $response->json()['access_token']
    //             : null;
    //     });
    // }

    // private function getSalesTotals(string $token): array
    // {
    //     $this->info("Fetching all orders in last 60 days...");

    //     $orders = [];
    //     $pageToken = null;
    //     $startDate = now()->subDays(60)->toIso8601String(); // ISO format for query param

    //     do {
    //         $url = 'https://miraklconnect.com/api/v2/orders?fulfillment_type=FULFILLED_BY_SELLER&limit=100';
    //         $url .= '&updated_from=' . urlencode($startDate);
    //         if ($pageToken) {
    //             $url .= '&page_token=' . urlencode($pageToken);
    //         }

    //         $response = Http::withToken($token)->get($url);
    //         if (!$response->successful()) {
    //             $this->error("Order fetch failed: " . $response->body());
    //             break;
    //         }

    //         $json = $response->json();
    //         $orders = array_merge($orders, $json['data'] ?? []);
    //         $pageToken = $json['next_page_token'] ?? null;
    //     } while ($pageToken);

    //     $this->info("Orders fetched: " . count($orders));

    //     // Define date ranges
    //     $now = now();
    //     $startL30 = $now->copy()->subDays(30);
    //     $endL30 = $now->copy()->subDay();

    //     $startL60 = $now->copy()->subDays(60);
    //     $endL60 = $now->copy()->subDays(31);

    //     // Initialize sku map
    //     $sales = [];

    //     foreach ($orders as $order) {
    //         $created = Carbon::parse($order['created_at']);

    //         foreach ($order['order_lines'] ?? [] as $line) {
    //             $sku = $line['product']['id'] ?? null;
    //             $qty = $line['quantity'] ?? 0;

    //             if (!$sku) continue;

    //             if (!isset($sales[$sku])) {
    //                 $sales[$sku] = ['m_l30' => 0, 'm_l60' => 0];
    //             }

    //             if ($created->between($startL60, $endL60)) {
    //                 $sales[$sku]['m_l60'] += $qty;
    //             } elseif ($created->between($startL30, $endL30)) {
    //                 $sales[$sku]['m_l30'] += $qty;
    //             }
    //         }
    //     }

    //     return $sales;
    // }


    // private function getSalesTotals(string $token): array
    // {
    //     $this->info("Fetching all Macy orders in last 60 days...");

    //     $sales = [];
    //     $pageToken = null;
    //     $startDate = now()->subDays(60)->startOfDay()->toIso8601String();

    //     // Define L30 and L60 ranges once
    //     $now = now();
    //     $startL30 = $now->copy()->subDays(30)->startOfDay();
    //     $endL30 = $now->copy()->endOfDay();

    //     $startL60 = $now->copy()->subDays(60)->startOfDay();
    //     $endL60 = $now->copy()->subDays(31)->endOfDay();

    //     do {
    //         $url = 'https://miraklconnect.com/api/v2/orders?fulfillment_type=FULFILLED_BY_SELLER&limit=100&created_from=' . urlencode($startDate);
    //         if ($pageToken) {
    //             $url .= '&page_token=' . urlencode($pageToken);
    //         }

    //         $response = Http::withToken($token)->get($url);
    //         if (!$response->successful()) {
    //             $this->error("Order fetch failed: " . $response->body());
    //             break;
    //         }

    //         $json = $response->json();
    //         $orders = $json['data'] ?? [];
    //         $pageToken = $json['next_page_token'] ?? null;

    //         // $channelNames = array_map(function($orders) {
    //         //     return $orders['origin']['channel_name'];
    //         // }, $orders);
    //         // if($channelNames == "Macy's, Inc."){
    //         //     Log::info('No orders found in this page.');
    //         // } else {
    //         //     Log::info('Channel names in this page: ' . implode(', ', $channelNames));
    //         // }

    //         foreach ($orders as $order) {
    //             $created = Carbon::parse($order['created_at']);

    //             foreach ($order['order_lines'] ?? [] as $line) {
    //                 $sku = $line['product']['id'] ?? null;
    //                 $qty = $line['quantity'] ?? 0;

    //                 if (!$sku) continue;

    //                 if (!isset($sales[$sku])) {
    //                     $sales[$sku] = ['m_l30' => 0, 'm_l60' => 0];
    //                 }

    //                 if ($created->between($startL30, $endL30, true)) {
    //                     $sales[$sku]['m_l30'] += $qty;
    //                 } elseif ($created->between($startL60, $endL60, true)) {
    //                     $sales[$sku]['m_l60'] += $qty;
    //                 }
    //             }
    //         }

    //         $this->info("Processed " . count($orders) . " orders in this page...");

    //     } while ($pageToken);

    //     return $sales;
    // }


    // private function getSalesTotals(string $token): array
    // {
    //     $this->info("Fetching Macy orders in last 60 days...");

    //     $pageToken = null;
    //     $sales = [];

    //     $now = now('America/New_York');
    //     $startDate = $now->copy()->subDays(60)->startOfDay()->toIso8601String();

    //     $startL30 = $now->copy()->subDays(29)->startOfDay();
    //     $endL30   = $now->copy()->endOfDay();
    //     $startL60 = $now->copy()->subDays(59)->startOfDay();
    //     $endL60   = $now->copy()->subDays(30)->endOfDay();

    //     do {
    //         $url = 'https://miraklconnect.com/api/v2/orders'
    //             . '?fulfillment_type=FULFILLED_BY_SELLER'
    //             . '&limit=100'
    //             . '&created_from=' . urlencode($startDate);

    //         if ($pageToken) {
    //             $url .= '&page_token=' . urlencode($pageToken);
    //         }

    //         $response = Http::withToken($token)->get($url);

    //         if (!$response->successful()) {
    //             $this->error("Order fetch failed: " . $response->body());
    //             break;
    //         }

    //         $json = $response->json();
    //         $pageOrders = $json['data'] ?? [];
    //         $pageToken = $json['next_page_token'] ?? null;

    //         // Filter only Macy's orders
    //         $macysOrders = array_filter($pageOrders, function($order) {
    //             return isset($order['origin']['channel_name']) && $order['origin']['channel_name'] === "Macy's, Inc.";
    //         });
    //         dd($macysOrders);

    //         foreach ($macysOrders as $order) {
    //             $created = Carbon::parse($order['created_at'], 'America/New_York');

    //             foreach ($order['order_lines'] ?? [] as $line) {
    //                 $sku = $line['product']['id'] ?? null;
    //                 $qty = $line['quantity'] ?? 0;

    //                 if (!$sku) continue;

    //                 if (!isset($sales[$sku])) {
    //                     $sales[$sku] = ['m_l30' => 0, 'm_l60' => 0];
    //                 }

    //                 if ($created->between($startL30, $endL30)) {
    //                     $sales[$sku]['m_l30'] += $qty;
    //                 } elseif ($created->between($startL60, $endL60)) {
    //                     $sales[$sku]['m_l60'] += $qty;
    //                 }
    //             }
    //         }

    //         Log::info("Processed page with " . count($macysOrders) . " Macy's orders.");
    //     } while ($pageToken);

    //     $this->info("Total Macy's SKUs: " . count($sales));

    //     return $sales;
    // }


    public function handle()
    {
        // Increase memory limit for this command to handle large product datasets
        ini_set('memory_limit', '256M');

        if ($this->option('pp-mcm-only')) {
            $this->syncPurchasingPowerPricesFromMcm();
            DB::connection()->disconnect();
            return self::SUCCESS;
        }
        
        $token = $this->getAccessToken();
        if (!$token) return;

        $skuSales = $this->getSalesTotals($token); // ['channel' => ['sku' => ['l30'=>x,'l60'=>y]]]

        // Fetch and store Macy's products with channel-specific pricing
        $this->fetchChannelProducts($token, 'macys', "Macy's, Inc.", $skuSales);
        
        // Close DB connection between channels to prevent buildup
        DB::connection()->disconnect();
        sleep(1);
        
        // Fetch and store Tiendamia products with channel-specific pricing
        $this->fetchChannelProducts($token, 'tiendamia', "Tiendamia", $skuSales);
        
        // Close DB connection between channels
        DB::connection()->disconnect();
        sleep(1);
        
        // Fetch and store BestBuy products with channel-specific pricing
        $this->fetchChannelProducts($token, 'bestbuyusa', "Best Buy USA", $skuSales);

        // Close DB connection between channels
        DB::connection()->disconnect();
        sleep(1);

        // Fetch and store Purchasing Power products with channel-specific pricing
        $this->fetchChannelProducts($token, 'purchasingpower', "Purchasing Power", $skuSales);

        // Overlay live MCM offer prices (seller portal) — Connect discount_prices often
        // diverge from the Purchasing Power marketplace listed price shown in the UI.
        DB::connection()->disconnect();
        sleep(1);
        $this->syncPurchasingPowerPricesFromMcm();

        // Final cleanup
        DB::connection()->disconnect();
        
        $this->info("All Macy, Tiendamia, BestbuyUSA, Purchasing Power products stored successfully.");
    }

    /**
     * OF21 — pull Purchasing Power MCM offer prices into purchasing_power_products.
     * Seller portal listed price lives here; Mirakl Connect catalog prices can differ.
     */
    private function syncPurchasingPowerPricesFromMcm(): void
    {
        $apiKey = trim((string) config('services.purchasingpower.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.purchasingpower.mcm_base_url', ''), '/');

        if ($apiKey === '' || $baseUrl === '') {
            $this->warn('Purchasing Power MCM API key not set (PURCHASING_POWER_MCM_API_KEY); skipping MCM price sync.');
            return;
        }

        $this->info('Syncing Purchasing Power prices from MCM offers (OF21)...');

        $shopId = config('services.purchasingpower.shop_id');
        $offset = 0;
        $max = 100;
        $totalUpdated = 0;
        $page = 1;

        try {
            do {
                $params = [
                    'max' => $max,
                    'offset' => $offset,
                ];
                if ($shopId !== null && $shopId !== '') {
                    $params['shop_id'] = (int) $shopId;
                }

                $response = null;
                for ($attempt = 1; $attempt <= 5; $attempt++) {
                    $response = Http::withoutVerifying()
                        ->withHeaders([
                            'Authorization' => $apiKey,
                            'Accept' => 'application/json',
                        ])
                        ->timeout(60)
                        ->get($baseUrl.'/api/offers', $params);

                    if ($response->status() !== 429) {
                        break;
                    }

                    $sleepSec = min(30, 3 * $attempt);
                    $this->warn("MCM rate limited (429); sleeping {$sleepSec}s then retry {$attempt}/5...");
                    sleep($sleepSec);
                }

                if (! $response || ! $response->successful()) {
                    $status = $response ? $response->status() : 0;
                    $body = $response ? substr($response->body(), 0, 300) : 'no response';
                    $this->error('Purchasing Power MCM OF21 failed: HTTP '.$status.' '.$body);
                    Log::error('Purchasing Power MCM OF21 failed', [
                        'status' => $status,
                        'body' => $response ? substr($response->body(), 0, 1000) : null,
                    ]);
                    return;
                }

                $offers = $response->json('offers') ?? [];
                $totalCount = (int) ($response->json('total_count') ?? 0);
                $updates = [];

                foreach ($offers as $offer) {
                    if (! is_array($offer)) {
                        continue;
                    }

                    $sku = trim((string) ($offer['shop_sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }

                    $price = $this->extractMcmOfferPrice($offer);
                    if ($price === null) {
                        continue;
                    }

                    $updates[] = [
                        'sku' => $sku,
                        'price' => $price,
                        'stock' => isset($offer['quantity']) && is_numeric($offer['quantity'])
                            ? (int) $offer['quantity']
                            : 0,
                    ];
                }

                if (! empty($updates)) {
                    $now = now()->toDateTimeString();
                    foreach (array_chunk($updates, 50) as $chunk) {
                        $values = [];
                        $bindings = [];
                        foreach ($chunk as $update) {
                            $values[] = '(?, ?, ?, 0, ?, ?)';
                            $bindings[] = $update['sku'];
                            $bindings[] = $update['price'];
                            $bindings[] = $update['stock'];
                            $bindings[] = $now;
                            $bindings[] = $now;
                        }

                        $sql = 'INSERT INTO purchasing_power_products (sku, price, stock, m_l30, created_at, updated_at) VALUES '
                            .implode(', ', $values)
                            .' ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock), updated_at = VALUES(updated_at)';

                        DB::statement($sql, $bindings);
                        $totalUpdated += count($chunk);
                    }
                }

                $fetched = count($offers);
                $this->info("MCM offers page {$page}: processed {$fetched} (updated {$totalUpdated}, total_count={$totalCount})");
                $offset += $max;
                $page++;
                unset($offers, $updates);
                sleep(1); // avoid Mirakl MCM 429

                // Stop when page is short, or we've covered total_count (when provided).
                $hasMore = $fetched >= $max && ($totalCount === 0 || $offset < $totalCount);
            } while ($hasMore);

            $this->info("Purchasing Power MCM price sync complete. Updated: {$totalUpdated}");
        } catch (\Throwable $e) {
            $this->error('Purchasing Power MCM price sync error: '.$e->getMessage());
            Log::error('Purchasing Power MCM price sync error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function extractMcmOfferPrice(array $offer): ?float
    {
        $candidates = [
            data_get($offer, 'applicable_pricing.price'),
            data_get($offer, 'price'),
            data_get($offer, 'all_prices.0.price'),
            data_get($offer, 'discount.discount_price'),
            data_get($offer, 'discount.origin_price'),
        ];

        foreach ($candidates as $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return round((float) $value, 2);
            }
        }

        return null;
    }

    private function fetchChannelProducts($token, $channelCode, $channelName, $skuSales)
    {
        $pageToken = null;
        $page = 1;
        $totalProcessed = 0;

        do {
            $this->info("Fetching {$channelName} products - page $page...");

            $url = "https://miraklconnect.com/api/products?limit=1000&channel_code={$channelCode}";
            if ($pageToken) {
                $url .= '&page_token=' . urlencode($pageToken);
            }

            $response = Http::withoutVerifying()->withToken($token)->get($url);
            
            // Check if token expired and refresh if needed
            if (!$response->successful()) {
                $newToken = $this->refreshTokenIfNeeded($response);
                if ($newToken) {
                    $token = $newToken;
                    $response = Http::withoutVerifying()->withToken($token)->get($url);
                }
            }
            
            if (!$response->successful()) {
                $this->error("{$channelName} product fetch failed: " . $response->body());
                return;
            }

            $json = $response->json();
            $products = $json['data'] ?? [];
            $pageToken = $json['next_page_token'] ?? null;

            // Determine table name based on channel
            $tableName = match($channelName) {
                "Macy's, Inc." => 'macy_products',
                "Tiendamia" => 'tiendamia_products',
                "Best Buy USA" => 'bestbuy_usa_products',
                "Purchasing Power" => 'purchasing_power_products',
                default => null,
            };

            if (!$tableName) {
                $this->error("Unknown channel: {$channelName}");
                return;
            }

            // Process in smaller batches
            $batchSize = 25;
            $productBatches = array_chunk($products, $batchSize);
            
            foreach ($productBatches as $batch) {
                $updates = [];
                
                foreach ($batch as $product) {
                    $sku = $product['id'] ?? null;
                    if (!$sku) continue;
                    
                    $price = $product['discount_prices'][0]['price']['amount'] ?? 
                             $product['standard_prices'][0]['price']['amount'] ?? 
                             $product['price']['amount'] ?? 
                             $product['prices'][0]['amount'] ?? 
                             $product['offer_price']['amount'] ?? null;
                    
                    if ($price === null) continue;

                    // Calculate total stock from all warehouses
                    $stock = 0;
                    if (isset($product['quantities']) && is_array($product['quantities'])) {
                        foreach ($product['quantities'] as $quantity) {
                            $stock += $quantity['available_quantity'] ?? 0;
                        }
                    }

                    $originalSku = $sku;
                    $sku = strtolower($sku);
                    $l30 = $skuSales[$channelName][$sku]['l30'] ?? 0;

                    $updates[] = [
                        'sku' => $originalSku,
                        'price' => $price,
                        'stock' => $stock,
                        'm_l30' => $l30,
                    ];
                }

                // Execute batch update using INSERT ON DUPLICATE KEY UPDATE
                if (!empty($updates)) {
                    try {
                        $now = now()->toDateTimeString();
                        $values = [];
                        $bindings = [];
                        
                        foreach ($updates as $update) {
                            $values[] = "(?, ?, ?, ?, ?, ?)";
                            $bindings[] = $update['sku'];
                            $bindings[] = $update['price'];
                            $bindings[] = $update['stock'];
                            $bindings[] = $update['m_l30'];
                            $bindings[] = $now;
                            $bindings[] = $now;
                        }
                        
                        $sql = "INSERT INTO {$tableName} (sku, price, stock, m_l30, created_at, updated_at) VALUES " 
                             . implode(', ', $values)
                             . " ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock), m_l30 = VALUES(m_l30), updated_at = VALUES(updated_at)";
                        
                        DB::statement($sql, $bindings);
                        $totalProcessed += count($updates);
                        
                    } catch (\Exception $e) {
                        Log::error("Failed to update {$channelName} batch: " . $e->getMessage());
                    }
                }
                
                unset($batch, $updates);
                usleep(50000); // 50ms delay between batches to reduce server load
            }
            
            unset($products, $productBatches, $json);
            gc_collect_cycles();

            $this->info("Page {$page}: Processed {$totalProcessed} {$channelName} products");
            $page++;
            
        } while ($pageToken);

        $this->info("{$channelName} products stored successfully. Total: {$totalProcessed}");
    }

    private function getAccessToken()
    {
        // Try to get cached token
        $token = Cache::get('macy_access_token');
        
        // If no token or token might be expired, get a fresh one
        if (!$token) {
            $response = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.macy.client_id'),
                'client_secret' => config('services.macy.client_secret'),
            ]);

            if ($response->successful()) {
                $token = $response->json()['access_token'];
                // Cache for 50 minutes (3000 seconds) to be safe
                Cache::put('macy_access_token', $token, 3000);
                Log::info("New Macy access token obtained and cached");
            } else {
                Log::error("Failed to get Macy access token: " . $response->body());
                return null;
            }
        }
        
        return $token;
    }
    
    private function refreshTokenIfNeeded($response)
    {
        // Handle both "Unauthorized" (capital) and "unauthorized" (lowercase) from Mirakl
        if (!$response->successful() && stripos($response->body(), 'unauthorized') !== false) {
            Log::warning("Macy token unauthorized, clearing cache and getting new token");
            Cache::forget('macy_access_token');
            return $this->getAccessToken();
        }
        return null;
    }

    private function getSalesTotals(string $token): array
    {
        $this->info("Fetching Macy, Tiendamia, BestbuyUSA orders in last 30 days...");

        $pageToken = null;
        $sales = [];
        $page = 1;

        $now = now('America/Los_Angeles');
        $startDate = $now->copy()->subDays(29)->startOfDay()->toIso8601String();

        $startL30 = $now->copy()->subDays(29)->startOfDay();
        $endL30   = $now->copy()->endOfDay();

        $companyId = config('services.macy.company_id');

        do {
            $url = 'https://miraklconnect.com/api/v2/orders'
                . '?fulfillment_type=FULFILLED_BY_SELLER'
                . '&limit=100'
                . '&created_from=' . urlencode($startDate);

            if ($pageToken) {
                $url .= '&page_token=' . urlencode($pageToken);
            }
            $response = Http::withoutVerifying()->withToken($token)->get($url);
            
            // Check if token expired and refresh if needed
            if (!$response->successful()) {
                $newToken = $this->refreshTokenIfNeeded($response);
                if ($newToken) {
                    $token = $newToken;
                    // Retry the request with new token
                    $response = Http::withoutVerifying()->withToken($token)->get($url);
                }
            }
            
            if (!$response->successful()) {
                $this->error("Order fetch failed: " . $response->body());
                break;
            }

            $json = $response->json();
            $orders = $json['data'] ?? [];
            $pageToken = $json['next_page_token'] ?? null;

            // Log all unique channel names on the first page so we can verify exact strings
            if ($page === 1) {
                $channelNames = array_unique(array_map(fn($o) => $o['origin']['channel_name'] ?? 'UNKNOWN', $orders));
                Log::info("Mirakl order channel names found on page 1: " . implode(' | ', $channelNames));
                $this->info("Channels in orders: " . implode(' | ', $channelNames));
            }

            foreach ($orders as $order) {
                $channel = $order['origin']['channel_name'] ?? 'UNKNOWN';
                $created = Carbon::parse($order['created_at'], 'America/Los_Angeles');

                foreach ($order['order_lines'] ?? [] as $line) {
                    $sku = $line['product']['id'] ?? null;
                    $qty = $line['quantity'] ?? 0;
                    $lineStatus = $line['status'] ?? null;
                    
                    if (!$sku) continue;

                    // Skip CLOSED orders (canceled/refunded) - matching UpdateMarketplaceDailyMetrics logic
                    if ($lineStatus === 'CLOSED') continue;

                    $sku = strtolower($sku);

                    if (str_contains($sku, 'cdkc13') && $channel === "Best Buy USA") {
                        Log::info("Found SKU containing cdkc13 in Best Buy order: {$sku}, qty {$qty}, created_at {$order['created_at']}");
                    }

                    if (!isset($sales[$channel][$sku])) {
                        $sales[$channel][$sku] = ['l30' => 0];
                    }

                    if ($created->between($startL30, $endL30)) {
                        $sales[$channel][$sku]['l30'] += $qty;
                    }
                }
            }
            $page++;
        } while ($pageToken);


        // Log all channel names found so we can verify exact strings from Mirakl
        foreach ($sales as $channel => $skuMap) {
            $this->info("Channel [{$channel}] has " . count($skuMap) . " SKUs with orders.");
            Log::info("Mirakl channel order count: [{$channel}] = " . count($skuMap) . " SKUs");
        }

        return $sales;
    }



}
