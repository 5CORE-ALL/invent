<?php

namespace App\Console\Commands;

use App\Models\BestbuyUsaProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\MacyProduct;
use App\Models\TiendamiaProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FetchMacyProducts extends Command
{
    /**  
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-macy-products';

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
        
        $token = $this->getAccessToken();
        if (!$token) return;

        $skuSales = $this->getSalesTotals($token); // ['channel' => ['sku' => ['l30'=>x,'l60'=>y]]]

        // Fetch and store Macy's products with channel-specific pricing
        $this->fetchChannelProducts($token, 'macys', "Macy's, Inc.", $skuSales);
        
        // Fetch and store Tiendamia products with channel-specific pricing
        $this->fetchChannelProducts($token, 'tiendamia', "Tiendamia", $skuSales);
        
        // Fetch and store BestBuy products with channel-specific pricing
        $this->fetchChannelProducts($token, 'bestbuyusa', "Best Buy USA", $skuSales);

        $this->info("All Macy, Tiendamia, BestbuyUSA products stored successfully.");
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

                    $originalSku = $sku;
                    $sku = strtolower($sku);
                    $l30 = $skuSales[$channelName][$sku]['l30'] ?? 0;

                    $updates[] = [
                        'sku' => DB::connection()->getPdo()->quote($originalSku),
                        'price' => $price,
                        'm_l30' => $l30,
                        'updated_at' => "'" . now()->toDateTimeString() . "'"
                    ];
                }

                // Execute batch update using INSERT ON DUPLICATE KEY UPDATE
                if (!empty($updates)) {
                    try {
                        $values = [];
                        foreach ($updates as $update) {
                            $values[] = "({$update['sku']}, {$update['price']}, {$update['m_l30']}, {$update['updated_at']}, {$update['updated_at']})";
                        }
                        
                        $sql = "INSERT INTO {$tableName} (sku, price, m_l30, created_at, updated_at) VALUES " 
                             . implode(', ', $values)
                             . " ON DUPLICATE KEY UPDATE price = VALUES(price), m_l30 = VALUES(m_l30), updated_at = VALUES(updated_at)";
                        
                        DB::connection()->getPdo()->exec($sql);
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
        // If we get unauthorized, clear cache and retry
        if (!$response->successful() && str_contains($response->body(), 'Unauthorized')) {
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

        $now = now('America/New_York');
        $startDate = $now->copy()->subDays(29)->startOfDay()->toIso8601String();

        $startL30 = $now->copy()->subDays(29)->startOfDay();
        $endL30   = $now->copy()->endOfDay();

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

            foreach ($orders as $order) {
                $channel = $order['origin']['channel_name'] ?? 'UNKNOWN';
                $created = Carbon::parse($order['created_at'], 'America/New_York');

                foreach ($order['order_lines'] ?? [] as $line) {
                    $sku = $line['product']['id'] ?? null;
                    $qty = $line['quantity'] ?? 0;
                    if (!$sku) continue;

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
        } while ($pageToken);


        $this->info("Total Macy's SKUs: " . count($sales));
        // foreach ($sales as $channel => $skuMap) {
        //     $this->info("Channel {$channel} has " . count($skuMap) . " SKUs with orders.");
        // }

        // Debug logging removed to save memory

        return $sales;
    }



}
