<?php

namespace App\Console\Commands;

use App\Models\BestbuyUsaProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\MacyProduct;
use App\Models\MacysPriceData;
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
    protected $signature = 'app:fetch-macy-products
                            {--pp-mcm-only : Only sync Purchasing Power prices from MCM OF21}
                            {--macy-mcm-only : Only sync Macy listed prices from MCM OF21}
                            {--bestbuy-mcm-only : Only sync Best Buy listed prices from MCM OF21}
                            {--bestbuy-mcm-offset=0 : Resume Best Buy OF21 at this offer offset}';

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

        if ($this->option('macy-mcm-only')) {
            $this->syncMacyPricesFromMcm();
            \App\Support\MacysRuleSpriceApply::dispatch();
            DB::connection()->disconnect();
            return self::SUCCESS;
        }

        if ($this->option('bestbuy-mcm-only')) {
            $this->syncBestBuyPricesFromMcm();
            DB::connection()->disconnect();
            return self::SUCCESS;
        }
        
        $token = $this->getAccessToken();
        if (!$token) return;

        $skuSales = $this->getSalesTotals($token); // ['channel' => ['sku' => ['l30'=>x,'l60'=>y]]]

        // Fetch and store Macy's products with channel-specific pricing
        $this->fetchChannelProducts($token, 'macys', "Macy's, Inc.", $skuSales);
        // Overlay live MCM offer prices — Connect discount_prices diverge (e.g. GSS BLU 2PCS $20.98 vs live $23.41).
        $this->syncMacyPricesFromMcm();
        \App\Support\MacysRuleSpriceApply::dispatch();
        
        // Close DB connection between channels to prevent buildup
        DB::connection()->disconnect();
        sleep(1);
        
        // Fetch and store BestBuy products with channel-specific pricing
        $this->fetchChannelProducts($token, 'bestbuyusa', "Best Buy USA", $skuSales);
        $this->syncBestBuyPricesFromMcm();

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
        
        $this->info("All Macy, BestbuyUSA, Purchasing Power products stored successfully.");
    }

    /**
     * OF21 — pull Macy MCM offer prices into macy_products (listed price for /macys-pricing).
     * Also refreshes macys_price_data as an offer cache. Connect catalog prices are not used.
     */
    private function syncMacyPricesFromMcm(): void
    {
        $apiKey = trim((string) config('services.macy.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');

        if ($apiKey === '' || $baseUrl === '') {
            $this->warn('Macy MCM API key not set (MACY_MCM_API_KEY); skipping MCM price sync.');
            return;
        }

        $this->info('Syncing Macy prices from MCM offers (OF21)...');

        $shopId = config('services.macy.shop_id');
        $offset = 0;
        $max = 100;
        $totalUpdated = 0;
        $page = 1;
        $seenNormSkus = [];

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
                    $this->warn("Macy MCM rate limited (429); sleeping {$sleepSec}s then retry {$attempt}/5...");
                    sleep($sleepSec);
                }

                if (! $response || ! $response->successful()) {
                    $status = $response ? $response->status() : 0;
                    $body = $response ? substr($response->body(), 0, 300) : 'no response';
                    $this->error('Macy MCM OF21 failed: HTTP '.$status.' '.$body);
                    Log::error('Macy MCM OF21 failed', [
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

                    $normSku = $this->normalizeMacyOfferSku($sku);
                    if ($normSku !== '') {
                        $seenNormSkus[$normSku] = true;
                    }

                    $price = $this->extractMcmOfferPrice($offer);
                    if ($price === null) {
                        continue;
                    }

                    $inactivity = $offer['inactivity_reasons'] ?? '';
                    if (is_array($inactivity)) {
                        $inactivity = implode(',', array_filter(array_map('strval', $inactivity)));
                    }

                    $upc = null;
                    foreach ($offer['product_references'] ?? [] as $ref) {
                        if (! is_array($ref)) {
                            continue;
                        }
                        if (strcasecmp((string) ($ref['reference_type'] ?? ''), 'UPC') === 0) {
                            $upc = trim((string) ($ref['reference'] ?? ''));
                            break;
                        }
                    }

                    $updates[] = [
                        'sku' => $sku,
                        'offer_sku' => $sku,
                        'product_sku' => trim((string) ($offer['product_sku'] ?? '')) ?: null,
                        'category_code' => trim((string) ($offer['category_code'] ?? '')) ?: null,
                        'category_label' => trim((string) ($offer['category_label'] ?? '')) ?: null,
                        'brand' => trim((string) ($offer['product_brand'] ?? '')) ?: null,
                        'product_name' => trim((string) ($offer['product_title'] ?? '')) ?: null,
                        'price' => $price,
                        'original_price' => is_numeric(data_get($offer, 'applicable_pricing.unit_origin_price'))
                            ? round((float) data_get($offer, 'applicable_pricing.unit_origin_price'), 2)
                            : $price,
                        'discount_price' => is_numeric(data_get($offer, 'discount.discount_price'))
                            ? round((float) data_get($offer, 'discount.discount_price'), 2)
                            : null,
                        'quantity' => isset($offer['quantity']) && is_numeric($offer['quantity'])
                            ? (int) $offer['quantity']
                            : 0,
                        'activated' => (bool) ($offer['active'] ?? false),
                        'upc' => $upc !== '' ? $upc : null,
                        'inactivity_reason' => is_string($inactivity) ? $inactivity : null,
                    ];
                }

                if (! empty($updates)) {
                    $now = now()->toDateTimeString();
                    foreach (array_chunk($updates, 50) as $chunk) {
                        $values = [];
                        $bindings = [];
                        $hasListingStatus = Schema::hasColumn('macy_products', 'listing_status');
                        foreach ($chunk as $update) {
                            $listedPrice = ! empty($update['activated']) ? $update['price'] : 0;
                            $listingStatus = ! empty($update['activated']) ? 'active' : 'inactive';
                            if ($hasListingStatus) {
                                $values[] = '(?, ?, ?, 0, ?, ?, ?)';
                                $bindings[] = $update['sku'];
                                $bindings[] = $listedPrice;
                                $bindings[] = $update['quantity'];
                                $bindings[] = $listingStatus;
                                $bindings[] = $now;
                                $bindings[] = $now;
                            } else {
                                $values[] = '(?, ?, ?, 0, ?, ?)';
                                $bindings[] = $update['sku'];
                                $bindings[] = $listedPrice;
                                $bindings[] = $update['quantity'];
                                $bindings[] = $now;
                                $bindings[] = $now;
                            }
                        }

                        $sql = $hasListingStatus
                            ? 'INSERT INTO macy_products (sku, price, stock, m_l30, listing_status, created_at, updated_at) VALUES '
                                .implode(', ', $values)
                                .' ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock), listing_status = VALUES(listing_status), updated_at = VALUES(updated_at)'
                            : 'INSERT INTO macy_products (sku, price, stock, m_l30, created_at, updated_at) VALUES '
                                .implode(', ', $values)
                                .' ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock), updated_at = VALUES(updated_at)';

                        DB::statement($sql, $bindings);

                        foreach ($chunk as $update) {
                            $existing = MacysPriceData::query()
                                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($update['sku']))])
                                ->first();

                            $payload = [
                                'offer_sku' => $update['offer_sku'],
                                'product_sku' => $update['product_sku'],
                                'category_code' => $update['category_code'],
                                'category_label' => $update['category_label'],
                                'brand' => $update['brand'],
                                'product_name' => $update['product_name'],
                                'price' => $update['price'],
                                'original_price' => $update['original_price'],
                                'discount_price' => $update['discount_price'],
                                'quantity' => $update['quantity'],
                                'activated' => $update['activated'],
                                'upc' => $update['upc'],
                                'inactivity_reason' => $update['inactivity_reason'],
                            ];

                            if ($existing) {
                                $existing->fill($payload);
                                $existing->save();
                            } else {
                                MacysPriceData::create($payload + ['sku' => $update['sku']]);
                            }
                        }

                        $totalUpdated += count($chunk);
                    }
                }

                $fetched = count($offers);
                $this->info("Macy MCM offers page {$page}: processed {$fetched} (updated {$totalUpdated}, total_count={$totalCount})");
                $offset += $max;
                $page++;
                unset($offers, $updates);
                sleep(1);

                $hasMore = $fetched >= $max && ($totalCount === 0 || $offset < $totalCount);
            } while ($hasMore);

            $this->clearMacyProductsPriceNotInMcm(array_keys($seenNormSkus));
            $this->info("Macy MCM price sync complete. Updated: {$totalUpdated}");
        } catch (\Throwable $e) {
            $this->error('Macy MCM price sync error: '.$e->getMessage());
            Log::error('Macy MCM price sync error', ['error' => $e->getMessage()]);
        }
    }

    private function normalizeMacyOfferSku(string $sku): string
    {
        $sku = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $sku);

        return strtoupper(trim(preg_replace('/\s+/u', ' ', $sku) ?? ''));
    }

    /**
     * After a full MCM pull, drop leftover Connect catalog prices so they are not treated as listed.
     *
     * @param  list<string>  $mcmNormSkus
     */
    private function clearMacyProductsPriceNotInMcm(array $mcmNormSkus): void
    {
        $keep = array_fill_keys(array_filter($mcmNormSkus), true);
        if ($keep === []) {
            return;
        }

        $cleared = 0;
        MacyProduct::query()->select('id', 'sku', 'price')->orderBy('id')->chunkById(200, function ($rows) use ($keep, &$cleared) {
            $ids = [];
            foreach ($rows as $row) {
                $norm = $this->normalizeMacyOfferSku((string) $row->sku);
                if ($norm === '' || isset($keep[$norm])) {
                    continue;
                }
                if ((float) $row->price > 0) {
                    $ids[] = $row->id;
                }
            }
            if ($ids !== []) {
                MacyProduct::query()->whereIn('id', $ids)->update(['price' => 0]);
                $cleared += count($ids);
            }
        });

        $this->info("Cleared Connect-only macy_products.price on {$cleared} SKUs not in MCM.");
    }

    /**
     * @param  list<string>  $mcmNormSkus
     * @param  list<string>  $mcmExactSkus
     */
    private function clearPurchasingPowerProductsPriceNotInMcm(array $mcmNormSkus, array $mcmExactSkus = []): void
    {
        $keepNorm = array_fill_keys(array_filter($mcmNormSkus), true);
        $keepExact = array_fill_keys(array_filter($mcmExactSkus), true);
        if ($keepNorm === [] && $keepExact === []) {
            return;
        }

        $cleared = 0;
        $cols = ['id', 'sku', 'price'];
        if (Schema::hasColumn('purchasing_power_products', 'listing_status')) {
            $cols[] = 'listing_status';
        }
        PurchasingPowerProduct::query()->select($cols)->orderBy('id')->chunkById(200, function ($rows) use ($keepNorm, $keepExact, &$cleared) {
            $ids = [];
            foreach ($rows as $row) {
                $exact = trim((string) $row->sku);
                $norm = $this->normalizeMacyOfferSku($exact);
                if ($norm === '') {
                    continue;
                }
                $kept = $keepExact !== []
                    ? isset($keepExact[$exact])
                    : isset($keepNorm[$norm]);
                if ($kept) {
                    continue;
                }
                if ((float) $row->price > 0 || (string) ($row->listing_status ?? '') === 'active') {
                    $ids[] = $row->id;
                }
            }
            if ($ids !== []) {
                $payload = ['price' => 0];
                if (Schema::hasColumn('purchasing_power_products', 'listing_status')) {
                    $payload['listing_status'] = 'inactive';
                }
                PurchasingPowerProduct::query()->whereIn('id', $ids)->update($payload);
                $cleared += count($ids);
            }
        });

        $this->info("Cleared leftover purchasing_power_products.price on {$cleared} SKUs not in MCM.");
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
        $seenNormSkus = [];
        $seenExactSkus = [];

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

                    $activated = array_key_exists('active', $offer)
                        ? (bool) $offer['active']
                        : false;
                    $seenNormSkus[$this->normalizeMacyOfferSku($sku)] = true;
                    $seenExactSkus[$sku] = true;

                    $updates[] = [
                        'sku' => $sku,
                        'price' => $activated ? $price : 0,
                        'stock' => isset($offer['quantity']) && is_numeric($offer['quantity'])
                            ? (int) $offer['quantity']
                            : 0,
                        'listing_status' => $activated ? 'active' : 'inactive',
                    ];
                }

                if (! empty($updates)) {
                    $now = now()->toDateTimeString();
                    $hasListingStatus = Schema::hasColumn('purchasing_power_products', 'listing_status');
                    foreach (array_chunk($updates, 50) as $chunk) {
                        $values = [];
                        $bindings = [];
                        foreach ($chunk as $update) {
                            if ($hasListingStatus) {
                                $values[] = '(?, ?, ?, 0, ?, ?, ?)';
                                $bindings[] = $update['sku'];
                                $bindings[] = $update['price'];
                                $bindings[] = $update['stock'];
                                $bindings[] = $update['listing_status'];
                                $bindings[] = $now;
                                $bindings[] = $now;
                            } else {
                                $values[] = '(?, ?, ?, 0, ?, ?)';
                                $bindings[] = $update['sku'];
                                $bindings[] = $update['price'];
                                $bindings[] = $update['stock'];
                                $bindings[] = $now;
                                $bindings[] = $now;
                            }
                        }

                        $sql = $hasListingStatus
                            ? 'INSERT INTO purchasing_power_products (sku, price, stock, m_l30, listing_status, created_at, updated_at) VALUES '
                                .implode(', ', $values)
                                .' ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock), listing_status = VALUES(listing_status), updated_at = VALUES(updated_at)'
                            : 'INSERT INTO purchasing_power_products (sku, price, stock, m_l30, created_at, updated_at) VALUES '
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

            $this->clearPurchasingPowerProductsPriceNotInMcm(array_keys($seenNormSkus), array_keys($seenExactSkus));
            $this->info("Purchasing Power MCM price sync complete. Updated: {$totalUpdated}");
            \App\Support\PurchasingPowerRuleSpriceApply::dispatch();
        } catch (\Throwable $e) {
            $this->error('Purchasing Power MCM price sync error: '.$e->getMessage());
            Log::error('Purchasing Power MCM price sync error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  list<string>  $mcmNormSkus
     * @param  list<string>  $mcmExactSkus
     */
    private function clearBestBuyProductsPriceNotInMcm(array $mcmNormSkus, array $mcmExactSkus = []): void
    {
        $keepNorm = array_fill_keys(array_filter($mcmNormSkus), true);
        $keepExact = array_fill_keys(array_filter($mcmExactSkus), true);
        if ($keepNorm === [] && $keepExact === []) {
            return;
        }

        $cleared = 0;
        $cols = ['id', 'sku', 'price'];
        if (Schema::hasColumn('bestbuy_usa_products', 'listing_status')) {
            $cols[] = 'listing_status';
        }
        BestbuyUsaProduct::query()->select($cols)->orderBy('id')->chunkById(200, function ($rows) use ($keepNorm, $keepExact, &$cleared) {
            $ids = [];
            foreach ($rows as $row) {
                $exact = trim((string) $row->sku);
                $norm = $this->normalizeMacyOfferSku($exact);
                if ($norm === '') {
                    continue;
                }
                $kept = $keepExact !== []
                    ? isset($keepExact[$exact])
                    : isset($keepNorm[$norm]);
                if ($kept) {
                    continue;
                }
                if ((float) $row->price > 0 || (string) ($row->listing_status ?? '') === 'active') {
                    $ids[] = $row->id;
                }
            }
            if ($ids !== []) {
                $payload = ['price' => 0];
                if (Schema::hasColumn('bestbuy_usa_products', 'listing_status')) {
                    $payload['listing_status'] = 'inactive';
                }
                BestbuyUsaProduct::query()->whereIn('id', $ids)->update($payload);
                $cleared += count($ids);
            }
        });

        $this->info("Cleared leftover bestbuy_usa_products.price on {$cleared} SKUs not in MCM.");
    }

    /**
     * @param  array<string, int|string>  $params
     * @return \Illuminate\Http\Client\Response|null  null = exhausted 429 retries
     */
    private function fetchBestBuyMcmOffers(string $baseUrl, string $apiKey, array $params)
    {
        $response = null;
        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(60)
                ->get($baseUrl.'/api/offers', $params);

            if ($response->status() !== 429) {
                return $response;
            }

            $sleepSec = min(90, 10 * $attempt);
            $this->warn("Best Buy MCM rate limited (429); sleeping {$sleepSec}s then retry {$attempt}/8...");
            sleep($sleepSec);
        }

        return null;
    }

    /**
     * OF21 — pull Best Buy MCM offer prices into bestbuy_usa_products.
     * Seller portal listed price lives here; Connect catalog / uploaded sheet are not listed.
     */
    private function syncBestBuyPricesFromMcm(): void
    {
        $apiKey = trim((string) config('services.bestbuy.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.bestbuy.mcm_base_url', ''), '/');

        if ($apiKey === '' || $baseUrl === '') {
            $this->warn('Best Buy MCM API key not set (BESTBUY_MCM_API_KEY); skipping MCM price sync.');
            return;
        }

        $this->info('Syncing Best Buy prices from MCM offers (OF21)...');

        $shopId = config('services.bestbuy.shop_id');
        $startOffset = max(0, (int) $this->option('bestbuy-mcm-offset'));
        $offset = $startOffset;
        $max = 50;
        $totalUpdated = 0;
        $page = (int) floor($offset / $max) + 1;
        $seenNormSkus = [];
        $seenExactSkus = [];
        $rateLimitRounds = 0;

        if ($startOffset > 0) {
            $this->warn("Resuming Best Buy OF21 at offset {$startOffset}. Leftover clear is skipped until a full 0-offset run finishes.");
        }

        try {
            do {
                $params = [
                    'max' => $max,
                    'offset' => $offset,
                ];
                if ($shopId !== null && $shopId !== '') {
                    $params['shop_id'] = (int) $shopId;
                }

                $response = $this->fetchBestBuyMcmOffers($baseUrl, $apiKey, $params);
                if ($response === null) {
                    $rateLimitRounds++;
                    if ($rateLimitRounds >= 4) {
                        $this->error("Best Buy MCM still 429 at offset {$offset}. Resume with: php artisan app:fetch-macy-products --bestbuy-mcm-only --bestbuy-mcm-offset={$offset}");
                        return;
                    }
                    $this->warn("Best Buy MCM 429 at offset {$offset}; waiting 90s then retrying this page ({$rateLimitRounds}/4)...");
                    sleep(90);
                    continue;
                }
                $rateLimitRounds = 0;

                if (! $response->successful()) {
                    $status = $response->status();
                    $body = substr($response->body(), 0, 300);
                    $this->error('Best Buy MCM OF21 failed: HTTP '.$status.' '.$body);
                    Log::error('Best Buy MCM OF21 failed', [
                        'status' => $status,
                        'body' => substr($response->body(), 0, 1000),
                        'offset' => $offset,
                    ]);
                    $this->warn("Resume with: php artisan app:fetch-macy-products --bestbuy-mcm-only --bestbuy-mcm-offset={$offset}");
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

                    $activated = array_key_exists('active', $offer)
                        ? (bool) $offer['active']
                        : false;
                    $seenNormSkus[$this->normalizeMacyOfferSku($sku)] = true;
                    $seenExactSkus[$sku] = true;

                    $updates[] = [
                        'sku' => $sku,
                        'price' => $activated ? $price : 0,
                        'stock' => isset($offer['quantity']) && is_numeric($offer['quantity'])
                            ? (int) $offer['quantity']
                            : 0,
                        'listing_status' => $activated ? 'active' : 'inactive',
                    ];
                }

                if (! empty($updates)) {
                    $now = now()->toDateTimeString();
                    $hasListingStatus = Schema::hasColumn('bestbuy_usa_products', 'listing_status');
                    foreach (array_chunk($updates, 50) as $chunk) {
                        $values = [];
                        $bindings = [];
                        foreach ($chunk as $update) {
                            if ($hasListingStatus) {
                                $values[] = '(?, ?, ?, 0, ?, ?, ?)';
                                $bindings[] = $update['sku'];
                                $bindings[] = $update['price'];
                                $bindings[] = $update['stock'];
                                $bindings[] = $update['listing_status'];
                                $bindings[] = $now;
                                $bindings[] = $now;
                            } else {
                                $values[] = '(?, ?, ?, 0, ?, ?)';
                                $bindings[] = $update['sku'];
                                $bindings[] = $update['price'];
                                $bindings[] = $update['stock'];
                                $bindings[] = $now;
                                $bindings[] = $now;
                            }
                        }

                        $sql = $hasListingStatus
                            ? 'INSERT INTO bestbuy_usa_products (sku, price, stock, m_l30, listing_status, created_at, updated_at) VALUES '
                                .implode(', ', $values)
                                .' ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock), listing_status = VALUES(listing_status), updated_at = VALUES(updated_at)'
                            : 'INSERT INTO bestbuy_usa_products (sku, price, stock, m_l30, created_at, updated_at) VALUES '
                                .implode(', ', $values)
                                .' ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock), updated_at = VALUES(updated_at)';

                        DB::statement($sql, $bindings);
                        $totalUpdated += count($chunk);
                    }
                }

                $fetched = count($offers);
                $this->info("Best Buy MCM offers page {$page}: processed {$fetched} (updated {$totalUpdated}, total_count={$totalCount})");
                $offset += $max;
                $page++;
                unset($offers, $updates);
                sleep(4);

                $hasMore = $fetched >= $max && ($totalCount === 0 || $offset < $totalCount);
            } while ($hasMore);

            if ($startOffset === 0) {
                $this->clearBestBuyProductsPriceNotInMcm(array_keys($seenNormSkus), array_keys($seenExactSkus));
            } else {
                $this->warn('Skipped leftover clear because this was a resumed OF21 run.');
            }
            $this->info("Best Buy MCM price sync complete. Updated: {$totalUpdated}");
        } catch (\Throwable $e) {
            $this->error('Best Buy MCM price sync error: '.$e->getMessage());
            Log::error('Best Buy MCM price sync error', ['error' => $e->getMessage()]);
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
                "Best Buy USA" => 'bestbuy_usa_products',
                "Purchasing Power" => 'purchasing_power_products',
                default => null,
            };

            if (!$tableName) {
                $this->error("Unknown channel: {$channelName}");
                return;
            }

            $hasListingStatus = Schema::hasColumn($tableName, 'listing_status');

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
                    $listingStatus = $hasListingStatus ? $this->listingStatusFromConnectProduct($product) : null;
                    
                    if ($price === null && $listingStatus === null) continue;

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
                        'listing_status' => $listingStatus,
                    ];
                }

                // Execute batch update using INSERT ON DUPLICATE KEY UPDATE
                if (!empty($updates)) {
                    try {
                        $now = now()->toDateTimeString();
                        $values = [];
                        $bindings = [];
                        
                        foreach ($updates as $update) {
                            if ($hasListingStatus) {
                                $values[] = "(?, ?, ?, ?, ?, ?, ?)";
                                $bindings[] = $update['sku'];
                                $bindings[] = $update['price'];
                                $bindings[] = $update['stock'];
                                $bindings[] = $update['m_l30'];
                                $bindings[] = $update['listing_status'];
                                $bindings[] = $now;
                                $bindings[] = $now;
                            } else {
                                $values[] = "(?, ?, ?, ?, ?, ?)";
                                $bindings[] = $update['sku'];
                                $bindings[] = $update['price'];
                                $bindings[] = $update['stock'];
                                $bindings[] = $update['m_l30'];
                                $bindings[] = $now;
                                $bindings[] = $now;
                            }
                        }
                        
                        if ($hasListingStatus) {
                            // Macy listed price comes from MCM OF21, not Connect catalog.
                            $priceUpdate = in_array($tableName, ['macy_products', 'bestbuy_usa_products'], true)
                                ? 'price = price'
                                : 'price = COALESCE(VALUES(price), price)';
                            $sql = "INSERT INTO {$tableName} (sku, price, stock, m_l30, listing_status, created_at, updated_at) VALUES "
                                 . implode(', ', $values)
                                 . " ON DUPLICATE KEY UPDATE {$priceUpdate}, stock = VALUES(stock), m_l30 = VALUES(m_l30), listing_status = COALESCE(VALUES(listing_status), listing_status), updated_at = VALUES(updated_at)";
                        } else {
                            $sql = "INSERT INTO {$tableName} (sku, price, stock, m_l30, created_at, updated_at) VALUES "
                                 . implode(', ', $values)
                                 . " ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock), m_l30 = VALUES(m_l30), updated_at = VALUES(updated_at)";
                        }
                        
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

    /**
     * @param  array<string, mixed>  $product
     */
    private function listingStatusFromConnectProduct(array $product): ?string
    {
        if (array_key_exists('active', $product) && ! is_array($product['active'])) {
            $raw = $product['active'];
            if (is_bool($raw)) {
                return $raw ? 'active' : 'inactive';
            }
            if (is_numeric($raw)) {
                return ((int) $raw) === 1 ? 'active' : 'inactive';
            }
        }
        foreach (['status', 'offer_status', 'product_status', 'state'] as $key) {
            if (! array_key_exists($key, $product) || is_array($product[$key])) {
                continue;
            }
            $raw = strtolower(trim((string) $product[$key]));
            if ($raw === '') {
                continue;
            }
            if (in_array($raw, ['active', 'live', 'published', 'enabled', '1', 'true'], true)) {
                return 'active';
            }
            if (in_array($raw, ['inactive', 'offline', 'disabled', 'unpublished', 'draft', '0', 'false'], true)) {
                return 'inactive';
            }
        }
        $offers = $product['offers'] ?? null;
        if (! is_array($offers)) {
            return null;
        }
        $anyActive = false;
        $anyInactive = false;
        foreach ($offers as $offer) {
            if (! is_array($offer) || ! array_key_exists('active', $offer) || is_array($offer['active'])) {
                continue;
            }
            if (filter_var($offer['active'], FILTER_VALIDATE_BOOLEAN)) {
                $anyActive = true;
            } else {
                $anyInactive = true;
            }
        }
        if ($anyInactive && ! $anyActive) {
            return 'inactive';
        }
        if ($anyActive) {
            return 'active';
        }

        return null;
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
        $this->info("Fetching Macy, BestbuyUSA, Purchasing Power orders in last 30 days...");

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
