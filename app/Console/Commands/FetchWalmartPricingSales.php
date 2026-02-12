<?php

namespace App\Console\Commands;

use App\Models\WalmartPricingSales;
use App\Models\WalmartDailyData;
use App\Services\WalmartRateLimiter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FetchWalmartPricingSales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walmart:pricing-sales';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Walmart pricing insights and order sales data from API';

    protected $baseUrl = 'https://marketplace.walmartapis.com';
    protected $token;
    protected $rateLimiter;
    protected ?string $pricingApiError = null;
    protected ?string $listingApiError = null;

    /**
     * Traffic level to numeric mapping
     */
    protected $trafficMap = [
        'VERY_LOW' => 1,
        'LOW' => 2,
        'MEDIUM' => 3,
        'HIGH' => 4,
        'VERY_HIGH' => 5,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = 30; // Always fetch 30 days data
        $startTime = microtime(true);

        // Initialize rate limiter
        $this->rateLimiter = new WalmartRateLimiter();

        $this->info("Fetching Walmart Pricing & Sales Data (Incremental Save Mode)...");

        // Get access token
        $this->token = $this->getAccessToken();
        if (!$this->token) {
            $this->error('Failed to get access token');
            return 1;
        }

        $this->info('Access token received.');

        // Step 1: Calculate order counts first
        $this->info('Step 1/2: Calculating order counts...');
        $orderCounts = $this->calculateOrderCounts($days);
        $this->info("  Calculated order counts for " . count($orderCounts) . " SKUs");

        // Step 2: Insert/update only daily sales metrics (no insight APIs).
        $this->info('Step 2/2: Saving daily sales metrics to walmart_pricing...');
        $this->mergeAndStoreData([], $orderCounts, []);
        $this->info("  ✓ Saved daily sales data for " . count($orderCounts) . " SKUs");

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("✓ Walmart daily sales data fetched and stored successfully in {$elapsed} seconds.");

        return 0;
    }

    /**
     * Get access token from Walmart (with caching to reduce API calls)
     */
    protected function getAccessToken(): ?string
    {
        // Check cache first (token valid for ~15 minutes)
        $cachedToken = Cache::get('walmart_api_token');
        if ($cachedToken) {
            $this->comment('Using cached token (saves 1 API call)');
            return $cachedToken;
        }
        
        $clientId = env('WALMART_CLIENT_ID');
        $clientSecret = env('WALMART_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            $this->error('Walmart credentials missing');
            return null;
        }

        $authorization = base64_encode("{$clientId}:{$clientSecret}");

        $this->comment('Requesting new token from Walmart...');
        $response = Http::withoutVerifying()->asForm()->withHeaders([
            'Authorization' => "Basic {$authorization}",
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/v3/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            $token = $response->json()['access_token'] ?? null;
            
            if ($token) {
                // Cache token for 14 minutes (tokens expire after ~15 minutes)
                Cache::put('walmart_api_token', $token, 840); // 840 seconds = 14 minutes
                $this->info('New token cached for 14 minutes');
            }
            
            return $token;
        }

        Log::error('Failed to get Walmart access token: ' . $response->body());
        return null;
    }

    /**
     * Fetch pricing insights from Walmart API
     */
    protected function fetchPricingInsights(): array
    {
        $allPricingData = [];
        $pageNumber = 0;
        $maxPages = 100; // Safety limit

        do {
            try {
                // Use rate limiter with retry logic
                $response = $this->rateLimiter->executeWithRetry(function() use ($pageNumber) {
                    $response = Http::withoutVerifying()->withHeaders([
                        'WM_QOS.CORRELATION_ID' => uniqid(),
                        'WM_SEC.ACCESS_TOKEN' => $this->token,
                        'WM_SVC.NAME' => 'Walmart Marketplace',
                        'accept' => 'application/json',
                        'content-type' => 'application/json',
                    ])->post($this->baseUrl . '/v3/price/getPricingInsights', [
                        'pageNumber' => $pageNumber,
                        'sort' => [
                            'sortField' => 'TRAFFIC',
                            'sortOrder' => 'DESC'
                        ]
                    ]);

                    // Check for token expiration and refresh
                    if ($response->status() == 401 || strpos($response->body(), 'UNAUTHORIZED') !== false) {
                        $this->warn("  Token expired, refreshing...");
                        $this->token = $this->getAccessToken();
                        if (!$this->token) {
                            throw new \Exception('Failed to refresh access token');
                        }
                        
                        // Retry with new token
                        $response = Http::withoutVerifying()->withHeaders([
                            'WM_QOS.CORRELATION_ID' => uniqid(),
                            'WM_SEC.ACCESS_TOKEN' => $this->token,
                            'WM_SVC.NAME' => 'Walmart Marketplace',
                            'accept' => 'application/json',
                            'content-type' => 'application/json',
                        ])->post($this->baseUrl . '/v3/price/getPricingInsights', [
                            'pageNumber' => $pageNumber,
                            'sort' => [
                                'sortField' => 'TRAFFIC',
                                'sortOrder' => 'DESC'
                            ]
                        ]);
                    }

                    // Throw exception on failure so retry logic can handle it
                    if (!$response->successful()) {
                        throw new \Exception($response->body());
                    }

                    return $response;
                }, 'pricing', 3);

                $data = $response->json();
                $items = $data['data']['pricingInsightsResponseList'] ?? [];

                if (empty($items)) {
                    break;
                }

                foreach ($items as $item) {
                    $sku = $item['sku'] ?? null;
                    if ($sku) {
                        $allPricingData[$sku] = $item;
                    }
                }

                $remaining = $this->rateLimiter->getRemainingRequests('pricing');
                $this->info("  Page {$pageNumber}: " . count($items) . " items (Total: " . count($allPricingData) . ", Remaining: {$remaining})");

                $pageNumber++;

            } catch (\Exception $e) {
                $this->pricingApiError = $e->getMessage();
                $this->error("Failed to fetch pricing page {$pageNumber}: " . $e->getMessage());
                break;
            }

        } while ($pageNumber < $maxPages && !empty($items));

        return $allPricingData;
    }

    /**
     * Fetch listing quality items for views/pageViews
     */
    protected function fetchListingQuality(): array
    {
        $allQualityData = [];
        $page = 1;
        $limit = 200; // Increased from 50 to reduce total requests
        $maxPages = 20; // Reduced - most SKUs should be in first few pages
        $duplicatePageCount = 0; // Track consecutive pages with no new SKUs

        do {
            try {
                $countBefore = count($allQualityData);
                
                // Use rate limiter with retry logic
                $response = $this->rateLimiter->executeWithRetry(function() use ($page, $limit) {
                    $response = Http::withoutVerifying()->withHeaders([
                        'WM_QOS.CORRELATION_ID' => uniqid(),
                        'WM_SEC.ACCESS_TOKEN' => $this->token,
                        'WM_SVC.NAME' => 'Walmart Marketplace',
                        'accept' => 'application/json',
                        'content-type' => 'application/json',
                    ])->post($this->baseUrl . '/v3/insights/items/listingQuality/items', [
                        'limit' => $limit,
                        'page' => $page,
                    ]);

                    // If unauthorized, refresh token and retry once
                    if ($response->status() == 401 || strpos($response->body(), 'UNAUTHORIZED') !== false) {
                        $this->warn("  Token expired, refreshing...");
                        $this->token = $this->getAccessToken();
                        if (!$this->token) {
                            throw new \Exception('Failed to refresh access token');
                        }

                        $response = Http::withoutVerifying()->withHeaders([
                            'WM_QOS.CORRELATION_ID' => uniqid(),
                            'WM_SEC.ACCESS_TOKEN' => $this->token,
                            'WM_SVC.NAME' => 'Walmart Marketplace',
                            'accept' => 'application/json',
                            'content-type' => 'application/json',
                        ])->post($this->baseUrl . '/v3/insights/items/listingQuality/items', [
                            'limit' => $limit,
                            'page' => $page,
                        ]);
                    }

                    if (!$response->successful()) {
                        throw new \Exception($response->body());
                    }

                    return $response;
                }, 'listing', 3);

                $data = $response->json();
                $items = $data['payload'] ?? [];

                if (empty($items)) {
                    break;
                }

                $newSkuCount = 0;
                foreach ($items as $item) {
                    $sku = $item['sku'] ?? null;
                    if ($sku && !isset($allQualityData[$sku])) {
                        $newSkuCount++;
                        // Get last30DaysPageViews from stats, fallback to pageViews
                        $pageViews = $item['stats']['last30DaysPageViews'] 
                            ?? $item['stats']['pageViews'] 
                            ?? $item['last30DaysPageViews']
                            ?? null;
                        
                        // Debug logging for specific SKU
                        if (strpos($sku, 'G PICK EXH') !== false) {
                            Log::info("Found SKU: {$sku}", [
                                'page_views' => $pageViews,
                                'stats' => $item['stats'] ?? 'No stats',
                                'full_item' => $item
                            ]);
                        }
                        
                        $allQualityData[$sku] = [
                            'quality_score' => $item['qualityScore'] ?? null,
                            'offer_score' => $item['offerScore'] ?? null,
                            'content_score' => $item['contentScore'] ?? null,
                            'page_views' => $pageViews ? (int) $pageViews : null,
                            'issues' => $item['issues'] ?? [],
                        ];
                    }
                }

                $remaining = $this->rateLimiter->getRemainingRequests('listing');
                $countAfter = count($allQualityData);
                $this->info("  Page {$page}: {$newSkuCount} new SKUs (Total: {$countAfter}, Remaining: {$remaining})");

                // Check if we're getting duplicate pages
                if ($countAfter == $countBefore) {
                    $duplicatePageCount++;
                    if ($duplicatePageCount >= 2) {
                        $this->comment("  No new SKUs in last 2 pages, stopping pagination.");
                        break;
                    }
                } else {
                    $duplicatePageCount = 0; // Reset counter if we got new SKUs
                }

                $page++;

            } catch (\Exception $e) {
                $this->listingApiError = $e->getMessage();
                $this->error("Failed to fetch listing quality page {$page}: " . $e->getMessage());
                break;
            }

        } while ($page <= $maxPages && count($items) >= $limit);

        return $allQualityData;
    }

    /**
     * Fetch pricing insights and save incrementally (NEW - saves during fetch)
     */
    protected function fetchAndSavePricingInsights(array $orderCounts): int
    {
        $allPricingData = [];
        $pageNumber = 0;
        $maxPages = 100;
        $totalSaved = 0;
        $batchSize = 50; // Save every 50 SKUs

        do {
            try {
                // Use rate limiter with retry logic
                $response = $this->rateLimiter->executeWithRetry(function() use ($pageNumber) {
                    $response = Http::withoutVerifying()->withHeaders([
                        'WM_QOS.CORRELATION_ID' => uniqid(),
                        'WM_SEC.ACCESS_TOKEN' => $this->token,
                        'WM_SVC.NAME' => 'Walmart Marketplace',
                        'accept' => 'application/json',
                        'content-type' => 'application/json',
                    ])->post($this->baseUrl . '/v3/price/getPricingInsights', [
                        'pageNumber' => $pageNumber,
                        'sort' => [
                            'sortField' => 'TRAFFIC',
                            'sortOrder' => 'DESC'
                        ]
                    ]);

                    // Check for token expiration and refresh
                    if ($response->status() == 401 || strpos($response->body(), 'UNAUTHORIZED') !== false) {
                        $this->warn("  Token expired, refreshing...");
                        $this->token = $this->getAccessToken();
                        if (!$this->token) {
                            throw new \Exception('Failed to refresh access token');
                        }
                        
                        // Retry with new token
                        $response = Http::withoutVerifying()->withHeaders([
                            'WM_QOS.CORRELATION_ID' => uniqid(),
                            'WM_SEC.ACCESS_TOKEN' => $this->token,
                            'WM_SVC.NAME' => 'Walmart Marketplace',
                            'accept' => 'application/json',
                            'content-type' => 'application/json',
                        ])->post($this->baseUrl . '/v3/price/getPricingInsights', [
                            'pageNumber' => $pageNumber,
                            'sort' => [
                                'sortField' => 'TRAFFIC',
                                'sortOrder' => 'DESC'
                            ]
                        ]);
                    }

                    if (!$response->successful()) {
                        throw new \Exception($response->body());
                    }

                    return $response;
                }, 'pricing', 3);

                $data = $response->json();
                $items = $data['data']['pricingInsightsResponseList'] ?? [];

                if (empty($items)) {
                    break;
                }

                foreach ($items as $item) {
                    $sku = $item['sku'] ?? null;
                    if ($sku) {
                        $allPricingData[$sku] = $item;
                    }
                }

                $remaining = $this->rateLimiter->getRemainingRequests('pricing');
                $this->info("  Page {$pageNumber}: " . count($items) . " items (Total: " . count($allPricingData) . ", Remaining: {$remaining})");

                // Save in batches during fetch
                if (count($allPricingData) >= $batchSize) {
                    $saved = $this->saveIncrementalBatch($allPricingData, $orderCounts, []);
                    $totalSaved += $saved;
                    $this->comment("  → Saved batch: {$saved} SKUs (Total saved: {$totalSaved})");
                    $allPricingData = []; // Clear batch
                }

                $pageNumber++;

            } catch (\Exception $e) {
                $this->pricingApiError = $e->getMessage();
                $this->error("Failed to fetch pricing page {$pageNumber}: " . $e->getMessage());
                break;
            }

        } while ($pageNumber < $maxPages && !empty($items));

        // Save remaining data
        if (!empty($allPricingData)) {
            $saved = $this->saveIncrementalBatch($allPricingData, $orderCounts, []);
            $totalSaved += $saved;
            $this->comment("  → Saved final batch: {$saved} SKUs (Total saved: {$totalSaved})");
        }

        return $totalSaved;
    }

    /**
     * Fetch listing quality and save incrementally (NEW - saves during fetch)
     */
    protected function fetchAndSaveListingQuality(): int
    {
        $allQualityData = [];
        $page = 1;
        $limit = 200;
        $maxPages = 20;
        $duplicatePageCount = 0;
        $totalSaved = 0;
        $batchSize = 100; // Save every 100 SKUs

        do {
            try {
                $countBefore = count($allQualityData);
                
                $response = $this->rateLimiter->executeWithRetry(function() use ($page, $limit) {
                    $response = Http::withoutVerifying()->withHeaders([
                        'WM_QOS.CORRELATION_ID' => uniqid(),
                        'WM_SEC.ACCESS_TOKEN' => $this->token,
                        'WM_SVC.NAME' => 'Walmart Marketplace',
                        'accept' => 'application/json',
                        'content-type' => 'application/json',
                    ])->post($this->baseUrl . '/v3/insights/items/listingQuality/items', [
                        'limit' => $limit,
                        'page' => $page,
                    ]);

                    if ($response->status() == 401 || strpos($response->body(), 'UNAUTHORIZED') !== false) {
                        $this->warn("  Token expired, refreshing...");
                        $this->token = $this->getAccessToken();
                        if (!$this->token) {
                            throw new \Exception('Failed to refresh access token');
                        }

                        $response = Http::withoutVerifying()->withHeaders([
                            'WM_QOS.CORRELATION_ID' => uniqid(),
                            'WM_SEC.ACCESS_TOKEN' => $this->token,
                            'WM_SVC.NAME' => 'Walmart Marketplace',
                            'accept' => 'application/json',
                            'content-type' => 'application/json',
                        ])->post($this->baseUrl . '/v3/insights/items/listingQuality/items', [
                            'limit' => $limit,
                            'page' => $page,
                        ]);
                    }

                    if (!$response->successful()) {
                        throw new \Exception($response->body());
                    }

                    return $response;
                }, 'listing', 3);

                $data = $response->json();
                $items = $data['payload'] ?? [];

                if (empty($items)) {
                    break;
                }

                $newSkuCount = 0;
                foreach ($items as $item) {
                    $sku = $item['sku'] ?? null;
                    if ($sku && !isset($allQualityData[$sku])) {
                        $newSkuCount++;
                        $pageViews = $item['stats']['last30DaysPageViews'] 
                            ?? $item['stats']['pageViews'] 
                            ?? $item['last30DaysPageViews']
                            ?? null;
                        
                        $allQualityData[$sku] = [
                            'quality_score' => $item['qualityScore'] ?? null,
                            'offer_score' => $item['offerScore'] ?? null,
                            'content_score' => $item['contentScore'] ?? null,
                            'page_views' => $pageViews ? (int) $pageViews : null,
                            'issues' => $item['issues'] ?? [],
                        ];
                    }
                }

                $remaining = $this->rateLimiter->getRemainingRequests('listing');
                $countAfter = count($allQualityData);
                $this->info("  Page {$page}: {$newSkuCount} new SKUs (Total: {$countAfter}, Remaining: {$remaining})");

                // Save in batches during fetch
                if (count($allQualityData) >= $batchSize) {
                    $saved = $this->updateListingQualityBatch($allQualityData);
                    $totalSaved += $saved;
                    $this->comment("  → Updated batch: {$saved} SKUs (Total updated: {$totalSaved})");
                    $allQualityData = []; // Clear batch
                }

                // Check for duplicates
                if ($countAfter == $countBefore) {
                    $duplicatePageCount++;
                    if ($duplicatePageCount >= 2) {
                        $this->comment("  No new SKUs in last 2 pages, stopping pagination.");
                        break;
                    }
                } else {
                    $duplicatePageCount = 0;
                }

                $page++;

            } catch (\Exception $e) {
                $this->listingApiError = $e->getMessage();
                $this->error("Failed to fetch listing quality page {$page}: " . $e->getMessage());
                break;
            }

        } while ($page <= $maxPages && count($items) >= $limit);

        // Save remaining data
        if (!empty($allQualityData)) {
            // Debug: Check if target SKU is in final batch
            if (isset($allQualityData['G PICK EXH PR 12PK'])) {
                Log::info("G PICK EXH PR 12PK in final batch", [
                    'data' => $allQualityData['G PICK EXH PR 12PK']
                ]);
            }
            
            $saved = $this->updateListingQualityBatch($allQualityData);
            $totalSaved += $saved;
            $this->comment("  → Updated final batch: {$saved} SKUs (Total updated: {$totalSaved})");
        }

        return $totalSaved;
    }

    /**
     * Save incremental batch (pricing data with orders)
     */
    protected function saveIncrementalBatch(array $pricingData, array $orderCounts, array $listingQualityData): int
    {
        $bulkData = [];
        
        foreach ($pricingData as $sku => $pricing) {
            $orders = $orderCounts[$sku] ?? [
                'l30_orders' => 0,
                'l30_qty' => 0,
                'l30_revenue' => 0,
                'l60_orders' => 0,
                'l60_qty' => 0,
                'l60_revenue' => 0,
            ];
            $quality = $listingQualityData[$sku] ?? [];

            $views = $this->trafficMap[$pricing['traffic'] ?? ''] ?? null;
            $pageViews = $quality['page_views'] ?? null;

            $bulkData[] = [
                'sku' => $sku,
                'item_id' => $pricing['itemId'] ?? null,
                'item_name' => isset($pricing['itemName']) ? substr($pricing['itemName'], 0, 500) : null,
                'current_price' => $pricing['currentPrice'] ?? null,
                'buy_box_base_price' => $pricing['buyBoxBasePrice'] ?? null,
                'buy_box_total_price' => $pricing['buyBoxTotalPrice'] ?? null,
                'buy_box_win_rate' => $pricing['buyBoxWinRate'] ?? null,
                'competitor_price' => $pricing['competitorPrice'] ?? null,
                'comparison_price' => $pricing['comparisonPrice'] ?? null,
                'price_differential' => $pricing['priceDifferential'] ?? null,
                'price_competitive_score' => $pricing['priceCompetitiveScore'] ?? null,
                'price_competitive' => ($pricing['priceCompetitive'] ?? false) ? 1 : 0,
                'repricer_strategy_type' => $pricing['repricerStrategyType'] ?? null,
                'repricer_strategy_name' => $pricing['repricerStrategyName'] ?? null,
                'repricer_status' => $pricing['repricerStatus'] ?? null,
                'repricer_min_price' => $pricing['repricerMinPrice'] ?? null,
                'repricer_max_price' => $pricing['repricerMaxPrice'] ?? null,
                'gmv30' => $pricing['gmv30'] ?? null,
                'inventory_count' => $pricing['inventoryCount'] ?? null,
                'fulfillment' => $pricing['fulfillment'] ?? null,
                'sales_rank' => $pricing['salesRank'] ?? null,
                'l30_orders' => $orders['l30_orders'],
                'l30_qty' => $orders['l30_qty'],
                'l30_revenue' => $orders['l30_revenue'],
                'l60_orders' => $orders['l60_orders'],
                'l60_qty' => $orders['l60_qty'],
                'l60_revenue' => $orders['l60_revenue'],
                'traffic' => $pricing['traffic'] ?? null,
                'views' => $views,
                'page_views' => $pageViews,
                'in_demand' => ($pricing['inDemand'] ?? false) ? 1 : 0,
                'promo_status' => $pricing['promoStatus'] ?? null,
                'promo_details' => isset($pricing['promoDetails']) ? json_encode($pricing['promoDetails']) : null,
                'reduced_referral_status' => $pricing['reducedReferralStatus'] ?? null,
                'walmart_funded_status' => $pricing['walmartFundedStatus'] ?? null,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        if (!empty($bulkData)) {
            $this->bulkUpsert($bulkData);
            // Note: Not syncing to apicentral - using walmart_pricing table only
        }

        return count($bulkData);
    }

    /**
     * Update or create listing quality data for SKUs
     */
    protected function updateListingQualityBatch(array $listingQualityData): int
    {
        $updated = 0;
        $bulkData = [];
        
        foreach ($listingQualityData as $sku => $quality) {
            $pageViewsValue = $quality['page_views'] ?? null;
            
            // Debug logging for specific SKU
            if (strpos($sku, 'G PICK EXH') !== false) {
                Log::info("Saving page_views for SKU: {$sku}", [
                    'page_views' => $pageViewsValue,
                    'quality_data' => $quality
                ]);
            }
            
            $bulkData[] = [
                'sku' => $sku,
                'page_views' => $pageViewsValue,
                'updated_at' => now()->toDateTimeString(),
            ];
            
            // Batch upsert every 50 records
            if (count($bulkData) >= 50) {
                try {
                    WalmartPricingSales::upsert(
                        $bulkData,
                        ['sku'],  // Unique key
                        ['page_views', 'updated_at']  // Fields to update
                    );
                    $updated += count($bulkData);
                    
                    // Debug log successful upsert
                    $skusInBatch = array_column($bulkData, 'sku');
                    if (in_array('G PICK EXH PR 12PK', $skusInBatch)) {
                        Log::info("Upserted batch containing G PICK EXH PR 12PK", [
                            'batch_size' => count($bulkData),
                            'total_updated' => $updated
                        ]);
                    }
                    
                    $bulkData = [];
                } catch (\Exception $e) {
                    Log::error('Failed to upsert listing quality batch: ' . $e->getMessage());
                }
            }
        }
        
        // Insert remaining data
        if (!empty($bulkData)) {
            try {
                WalmartPricingSales::upsert(
                    $bulkData,
                    ['sku'],
                    ['page_views', 'updated_at']
                );
                $updated += count($bulkData);
            } catch (\Exception $e) {
                Log::error('Failed to upsert final listing quality batch: ' . $e->getMessage());
            }
        }

        return $updated;
    }

    /**
     * Calculate order counts from WalmartDailyData table or fetch from API
     */
    protected function calculateOrderCounts(int $days): array
    {
        $now = Carbon::now();
        $l30Start = $now->copy()->subDays(30);
        $l60Start = $now->copy()->subDays($days);

        // Check if we have data in WalmartDailyData
        $hasData = WalmartDailyData::where('order_date', '>=', $l60Start)->exists();

        if ($hasData) {
            $this->info("  Using existing order data from walmart_daily_data table...");
            return $this->calculateFromDailyData($l30Start, $l60Start);
        }

        // Otherwise fetch from API
        $this->info("  Fetching orders from Walmart API...");
        return $this->fetchAndCalculateOrders($days);
    }

    /**
     * Calculate order counts from WalmartDailyData table
     */
    protected function calculateFromDailyData(Carbon $l30Start, Carbon $l60Start): array
    {
        $orderCounts = [];

        // L30 counts
        $l30Data = WalmartDailyData::select('sku')
            ->selectRaw('COUNT(DISTINCT purchase_order_id) as order_count')
            ->selectRaw('SUM(quantity) as total_qty')
            ->selectRaw('SUM(unit_price * quantity) as total_revenue')
            ->where('order_date', '>=', $l30Start)
            ->whereNotNull('sku')
            ->groupBy('sku')
            ->get();

        foreach ($l30Data as $row) {
            $orderCounts[$row->sku] = [
                'l30_orders' => (int) $row->order_count,
                'l30_qty' => (int) $row->total_qty,
                'l30_revenue' => (float) $row->total_revenue,
                'l60_orders' => 0,
                'l60_qty' => 0,
                'l60_revenue' => 0,
            ];
        }

        // L60 counts (full 60 days)
        $l60Data = WalmartDailyData::select('sku')
            ->selectRaw('COUNT(DISTINCT purchase_order_id) as order_count')
            ->selectRaw('SUM(quantity) as total_qty')
            ->selectRaw('SUM(unit_price * quantity) as total_revenue')
            ->where('order_date', '>=', $l60Start)
            ->whereNotNull('sku')
            ->groupBy('sku')
            ->get();

        foreach ($l60Data as $row) {
            if (!isset($orderCounts[$row->sku])) {
                $orderCounts[$row->sku] = [
                    'l30_orders' => 0,
                    'l30_qty' => 0,
                    'l30_revenue' => 0,
                    'l60_orders' => 0,
                    'l60_qty' => 0,
                    'l60_revenue' => 0,
                ];
            }
            $orderCounts[$row->sku]['l60_orders'] = (int) $row->order_count;
            $orderCounts[$row->sku]['l60_qty'] = (int) $row->total_qty;
            $orderCounts[$row->sku]['l60_revenue'] = (float) $row->total_revenue;
        }

        return $orderCounts;
    }

    /**
     * Fetch orders from API and calculate counts
     */
    protected function fetchAndCalculateOrders(int $days): array
    {
        $orderCounts = [];
        $now = Carbon::now();
        $l30Start = $now->copy()->subDays(30);
        $startDate = $now->copy()->subDays($days)->toIso8601String();
        $endDate = $now->toIso8601String();

        $nextCursor = null;
        $dailyRowsBatch = [];
        $dailyBatchSize = 200;

        do {
            if ($nextCursor) {
                $url = $this->baseUrl . "/v3/orders" . $nextCursor;
                $response = Http::withoutVerifying()->withHeaders([
                    'WM_QOS.CORRELATION_ID' => uniqid(),
                    'WM_SEC.ACCESS_TOKEN' => $this->token,
                    'WM_SVC.NAME' => 'Walmart Marketplace',
                    'accept' => 'application/json',
                ])->get($url);
            } else {
                $query = [
                    'createdStartDate' => $startDate,
                    'createdEndDate' => $endDate,
                    'limit' => 100,
                    'productInfo' => 'true',
                    'replacementInfo' => 'false',
                ];

                $response = Http::withoutVerifying()->withHeaders([
                    'WM_QOS.CORRELATION_ID' => uniqid(),
                    'WM_SEC.ACCESS_TOKEN' => $this->token,
                    'WM_SVC.NAME' => 'Walmart Marketplace',
                    'accept' => 'application/json',
                ])->get($this->baseUrl . "/v3/orders", $query);
            }

            if (!$response->successful()) {
                $this->error('Order fetch failed: ' . $response->body());
                break;
            }

            $data = $response->json();
            $orders = $data['list']['elements']['order'] ?? [];
            $nextCursor = $data['list']['meta']['nextCursor'] ?? null;

            foreach ($orders as $order) {
                $orderDate = isset($order['orderDate']) ? Carbon::createFromTimestampMs($order['orderDate']) : null;
                $isL30 = $orderDate && $orderDate->gte($l30Start);

                $orderLines = $order['orderLines']['orderLine'] ?? [];
                foreach ($orderLines as $line) {
                    $sku = $line['item']['sku'] ?? null;
                    if (!$sku) continue;

                    $quantity = (int) ($line['orderLineQuantity']['amount'] ?? 1);
                    $unitPrice = 0;

                    // Get price from charges
                    $charges = $line['charges']['charge'] ?? [];
                    foreach ($charges as $charge) {
                        if (($charge['chargeType'] ?? '') === 'PRODUCT') {
                            $unitPrice = (float) ($charge['chargeAmount']['amount'] ?? 0);
                            break;
                        }
                    }

                    if (!isset($orderCounts[$sku])) {
                        $orderCounts[$sku] = [
                            'l30_orders' => 0,
                            'l30_qty' => 0,
                            'l30_revenue' => 0,
                            'l60_orders' => 0,
                            'l60_qty' => 0,
                            'l60_revenue' => 0,
                        ];
                    }

                    // L60 always includes L30
                    $orderCounts[$sku]['l60_orders']++;
                    $orderCounts[$sku]['l60_qty'] += $quantity;
                    $orderCounts[$sku]['l60_revenue'] += $unitPrice * $quantity;

                    if ($isL30) {
                        $orderCounts[$sku]['l30_orders']++;
                        $orderCounts[$sku]['l30_qty'] += $quantity;
                        $orderCounts[$sku]['l30_revenue'] += $unitPrice * $quantity;
                    }

                    // Also persist raw order line in walmart_daily_data.
                    $dailyRow = $this->buildDailyRowFromOrderLine($order, $line, $orderDate, $isL30);
                    if ($dailyRow) {
                        $dailyRowsBatch[] = $dailyRow;
                    }

                    if (count($dailyRowsBatch) >= $dailyBatchSize) {
                        $this->upsertDailyRowsBatch($dailyRowsBatch);
                        $dailyRowsBatch = [];
                    }
                }
            }

            $this->info("  Processed " . count($orders) . " orders (Total SKUs: " . count($orderCounts) . ")");

        } while (!empty($orders) && $nextCursor);

        if (!empty($dailyRowsBatch)) {
            $this->upsertDailyRowsBatch($dailyRowsBatch);
        }

        return $orderCounts;
    }

    /**
     * Build walmart_daily_data row from Walmart order line payload.
     */
    protected function buildDailyRowFromOrderLine(array $order, array $line, ?Carbon $orderDate, bool $isL30): ?array
    {
        $purchaseOrderId = $order['purchaseOrderId'] ?? null;
        $lineNumber = $line['lineNumber'] ?? ($line['orderLineNumber'] ?? null);
        $sku = $line['item']['sku'] ?? null;

        if (!$purchaseOrderId || $lineNumber === null || !$sku) {
            return null;
        }

        $quantity = (int) ($line['orderLineQuantity']['amount'] ?? 1);
        $unitPrice = 0.0;
        $currency = null;
        $charges = $line['charges']['charge'] ?? [];
        foreach ($charges as $charge) {
            if (($charge['chargeType'] ?? '') === 'PRODUCT') {
                $unitPrice = (float) ($charge['chargeAmount']['amount'] ?? 0);
                $currency = $charge['chargeAmount']['currency'] ?? null;
                break;
            }
        }

        return [
            'purchase_order_id' => (string) $purchaseOrderId,
            'order_line_number' => (int) $lineNumber,
            'customer_order_id' => $order['customerOrderId'] ?? null,
            'order_date' => $orderDate?->toDateTimeString(),
            'period' => $isL30 ? 'l30' : 'l60',
            'sku' => $sku,
            'item_id' => $line['item']['productId'] ?? null,
            'product_name' => isset($line['item']['productName']) ? substr($line['item']['productName'], 0, 500) : null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'order_line_json' => json_encode($line),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Upsert walmart_daily_data in chunks.
     */
    protected function upsertDailyRowsBatch(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        try {
            WalmartDailyData::upsert(
                $rows,
                ['purchase_order_id', 'order_line_number'],
                [
                    'customer_order_id',
                    'order_date',
                    'period',
                    'sku',
                    'item_id',
                    'product_name',
                    'quantity',
                    'unit_price',
                    'currency',
                    'order_line_json',
                    'updated_at',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to upsert walmart_daily_data from pricing-sales command: ' . $e->getMessage());
            $this->error('walmart_daily_data upsert failed: ' . $e->getMessage());
        }
    }

    /**
     * Merge pricing, order, and listing quality data, then store
     */
    protected function mergeAndStoreData(array $pricingData, array $orderCounts, array $listingQualityData = []): void
    {
        $bulkData = [];
        $allSkus = array_unique(array_merge(
            array_keys($pricingData),
            array_keys($orderCounts),
            array_keys($listingQualityData)
        ));

        foreach ($allSkus as $sku) {
            $pricing = $pricingData[$sku] ?? [];
            $orders = $orderCounts[$sku] ?? [
                'l30_orders' => 0,
                'l30_qty' => 0,
                'l30_revenue' => 0,
                'l60_orders' => 0,
                'l60_qty' => 0,
                'l60_revenue' => 0,
            ];
            $quality = $listingQualityData[$sku] ?? [];

            // views = traffic level mapping, page_views = actual page views from listing quality API
            $views = $this->trafficMap[$pricing['traffic'] ?? ''] ?? null;
            $pageViews = $quality['page_views'] ?? null; 

            $bulkData[] = [
                'sku' => $sku,
                'item_id' => $pricing['itemId'] ?? null,
                'item_name' => isset($pricing['itemName']) ? substr($pricing['itemName'], 0, 500) : null,
                'current_price' => $pricing['currentPrice'] ?? null,
                'buy_box_base_price' => $pricing['buyBoxBasePrice'] ?? null,
                'buy_box_total_price' => $pricing['buyBoxTotalPrice'] ?? null,
                'buy_box_win_rate' => $pricing['buyBoxWinRate'] ?? null,
                'competitor_price' => $pricing['competitorPrice'] ?? null,
                'comparison_price' => $pricing['comparisonPrice'] ?? null,
                'price_differential' => $pricing['priceDifferential'] ?? null,
                'price_competitive_score' => $pricing['priceCompetitiveScore'] ?? null,
                'price_competitive' => ($pricing['priceCompetitive'] ?? false) ? 1 : 0,
                'repricer_strategy_type' => $pricing['repricerStrategyType'] ?? null,
                'repricer_strategy_name' => $pricing['repricerStrategyName'] ?? null,
                'repricer_status' => $pricing['repricerStatus'] ?? null,
                'repricer_min_price' => $pricing['repricerMinPrice'] ?? null,
                'repricer_max_price' => $pricing['repricerMaxPrice'] ?? null,
                'gmv30' => $pricing['gmv30'] ?? null,
                'inventory_count' => $pricing['inventoryCount'] ?? null,
                'fulfillment' => $pricing['fulfillment'] ?? null,
                'sales_rank' => $pricing['salesRank'] ?? null,
                'l30_orders' => $orders['l30_orders'],
                'l30_qty' => $orders['l30_qty'],
                'l30_revenue' => $orders['l30_revenue'],
                'l60_orders' => $orders['l60_orders'],
                'l60_qty' => $orders['l60_qty'],
                'l60_revenue' => $orders['l60_revenue'],
                'traffic' => $pricing['traffic'] ?? null,
                'views' => $views,
                'page_views' => $pageViews,
                'in_demand' => ($pricing['inDemand'] ?? false) ? 1 : 0,
                'promo_status' => $pricing['promoStatus'] ?? null,
                'promo_details' => isset($pricing['promoDetails']) ? json_encode($pricing['promoDetails']) : null,
                'reduced_referral_status' => $pricing['reducedReferralStatus'] ?? null,
                'walmart_funded_status' => $pricing['walmartFundedStatus'] ?? null,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            // Bulk insert in chunks
            if (count($bulkData) >= 100) {
                $this->bulkUpsert($bulkData);
                $bulkData = [];
            }
        }

        // Insert remaining data
        if (!empty($bulkData)) {
            $this->bulkUpsert($bulkData);
        }

        $this->info("Stored data for " . count($allSkus) . " SKUs");
        
        // Note: Not syncing to apicentral - using walmart_pricing table only
    }
    
    /**
     * Update walmart_metrics table in apicentral with latest data
     * DISABLED: Using walmart_pricing table only (no apicentral sync)
     */
    protected function updateWalmartMetrics(array $data): void
    {
        // Disabled - not syncing to apicentral
        // All data stays in walmart_pricing table
        return;
        
        /* Original apicentral sync code (disabled)
        foreach ($data as $item) {
            DB::connection('apicentral')->table('walmart_metrics')->updateOrInsert(
                ['sku' => $item['sku']],
                [
                    'l30' => $item['l30_qty'] ?? 0,
                    'l30_amt' => $item['l30_revenue'] ?? 0,
                    'l60' => $item['l60_qty'] ?? 0,
                    'l60_amt' => $item['l60_revenue'] ?? 0,
                    'price' => $item['current_price'] ?? 0,
                    'stock' => $item['inventory_count'] ?? 0,
                    'updated_at' => now(),
                ]
            );
        }
        */
    }

    /**
     * Bulk upsert data
     */
    protected function bulkUpsert(array $data): void
    {
        if (empty($data)) {
            return;
        }

        try {
            WalmartPricingSales::upsert(
                $data,
                ['sku'],
                [
                    'item_id', 'item_name', 'current_price', 'buy_box_base_price',
                    'buy_box_total_price', 'buy_box_win_rate', 'competitor_price',
                    'comparison_price', 'price_differential', 'price_competitive_score',
                    'price_competitive', 'repricer_strategy_type', 'repricer_strategy_name',
                    'repricer_status', 'repricer_min_price', 'repricer_max_price',
                    'gmv30', 'inventory_count', 'fulfillment', 'sales_rank',
                    'l30_orders', 'l30_qty', 'l30_revenue', 'l60_orders', 'l60_qty',
                    'l60_revenue', 'traffic', 'views', 'page_views', 'in_demand', 'promo_status',
                    'promo_details', 'reduced_referral_status', 'walmart_funded_status',
                    'updated_at'
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to upsert Walmart pricing sales: ' . $e->getMessage());
            $this->error('Upsert failed: ' . $e->getMessage());
        }
    }

    /**
     * Submit inventory feed to Walmart and get feed ID
     * 
     * NOTE: This should be run separately to avoid rate limit conflicts
     * Use the dedicated command: php artisan walmart:submit-inventory-feed
     */
    protected function submitInventoryFeed(array $pricingData): ?string
    {
        if (empty($pricingData)) {
            $this->warn('No pricing data available for inventory feed');
            return null;
        }

        // Create inventory feed XML
        $xml = $this->createInventoryFeedXml($pricingData);
        
        try {
            // Use rate limiter for feeds
            $response = $this->rateLimiter->executeWithRetry(function() use ($xml) {
                return Http::withoutVerifying()
                    ->withHeaders([
                        'WM_QOS.CORRELATION_ID' => uniqid(),
                        'WM_SEC.ACCESS_TOKEN' => $this->token,
                        'WM_SVC.NAME' => 'Walmart Marketplace',
                        'Content-Type' => 'multipart/form-data',
                    ])
                    ->attach('file', $xml, 'inventory_feed.xml', ['Content-Type' => 'application/xml'])
                    ->post($this->baseUrl . '/v3/feeds?feedType=inventory');
            }, 'feeds', 2);

            if ($response->successful()) {
                $responseBody = $response->body();
                $feedId = null;
                
                $this->info("  Feed response received (length: " . strlen($responseBody) . " chars)");
                
                // Try parsing as JSON first
                $jsonData = json_decode($responseBody, true);
                if ($jsonData && isset($jsonData['feedId'])) {
                    $feedId = $jsonData['feedId'];
                    $this->info("  Parsed as JSON");
                } else {
                    // Parse XML response
                    try {
                        $xml = simplexml_load_string($responseBody);
                        if ($xml && isset($xml->feedId)) {
                            $feedId = (string) $xml->feedId;
                            $this->info("  Parsed as XML");
                        }
                    } catch (\Exception $e) {
                        $this->warn('Failed to parse feed response: ' . $e->getMessage());
                    }
                }
                
                if ($feedId) {
                    $this->info("  Inventory feed submitted. Feed ID: {$feedId}");
                    
                    // Store feed ID for tracking
                    Log::info("Walmart Inventory Feed Submitted", [
                        'feed_id' => $feedId,
                        'item_count' => count($pricingData),
                        'timestamp' => now()
                    ]);
                    
                    return $feedId;
                } else {
                    $this->warn('Feed submitted but no feed ID found in response: ' . substr($responseBody, 0, 200) . '...');
                }
            } else {
                $this->error('Feed submission failed: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error('Feed submission error: ' . $e->getMessage());
            Log::error('Walmart feed submission failed: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Create inventory feed XML
     */
    protected function createInventoryFeedXml(array $pricingData): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<InventoryFeed xmlns="http://walmart.com/">' . "\n";
        $xml .= '  <InventoryHeader>' . "\n";
        $xml .= '    <version>1.4</version>' . "\n";
        $xml .= '  </InventoryHeader>' . "\n";
        
        // Take first 100 items to avoid huge feeds
        $items = array_slice($pricingData, 0, 100, true);
        
        foreach ($items as $sku => $item) {
            $inventoryCount = $item['inventoryCount'] ?? 0;
            
            $xml .= '  <inventory>' . "\n";
            $xml .= '    <sku>' . htmlspecialchars($sku) . '</sku>' . "\n";
            $xml .= '    <quantity>' . "\n";
            $xml .= '      <unit>EACH</unit>' . "\n";
            $xml .= '      <amount>' . max(0, (int) $inventoryCount) . '</amount>' . "\n";
            $xml .= '    </quantity>' . "\n";
            $xml .= '  </inventory>' . "\n";
        }
        
        $xml .= '</InventoryFeed>';
        
        return $xml;
    }

    /**
     * Get feed status by feed ID
     */
    protected function getFeedStatus(string $feedId): ?array
    {
        try {
            $response = Http::withoutVerifying()->withHeaders([
                'WM_QOS.CORRELATION_ID' => uniqid(),
                'WM_SEC.ACCESS_TOKEN' => $this->token,
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'Accept' => 'application/json',
            ])->get($this->baseUrl . "/v3/feeds/{$feedId}");

            if ($response->successful()) {
                return $response->json();
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to get feed status for {$feedId}: " . $e->getMessage());
        }
        
        return null;
    }
}

