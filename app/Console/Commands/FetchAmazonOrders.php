<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\AmazonOrderCursor;
use App\Models\AmazonDailySync;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchAmazonOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-amazon-orders 
        {--fetch-missing-items : Only fetch items for orders that have no items}
        {--fix-zero-prices : Fix items with $0 price by looking up from product_master or re-fetching}
        {--daily : Fetch orders for today only}
        {--yesterday : Fetch orders for yesterday only}
        {--last-days=30 : Fetch orders for last N days (default: 30)}
        {--from= : Fetch orders from this date (Y-m-d format)}
        {--to= : Fetch orders to this date (Y-m-d format)}
        {--with-items : Also fetch order items (line items) for each order}
        {--auto-sync : Automatically sync pending/failed days}
        {--resync-date= : Re-sync a specific date (Y-m-d format)}
        {--resync-last-days= : Re-sync last N days}
        {--initialize-days= : Initialize sync tracking for last N days (default: 90)}
        {--status : Show sync status for all days}
        {--delay=3 : Delay in seconds between API requests (default: 3)}
        {--max-retries=3 : Maximum retries for failed API calls (default: 3)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Amazon orders day-by-day with auto-resume on failure (uses California/Pacific Time)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Option to only fetch missing items for existing orders
        if ($this->option('fetch-missing-items')) {
            $this->fetchMissingItems();
            return;
        }

        // Option to fix items with $0 price
        if ($this->option('fix-zero-prices')) {
            $this->fixZeroPriceItems();
            return;
        }

        // Show sync status
        if ($this->option('status')) {
            $this->showSyncStatus();
            return;
        }

        // Initialize days tracking
        if ($this->option('initialize-days')) {
            $days = (int) $this->option('initialize-days');
            $this->initializeDailyTracking($days);
            return;
        }

        // Re-sync specific date
        if ($this->option('resync-date')) {
            $date = Carbon::parse($this->option('resync-date'), 'America/Los_Angeles');
            $this->resyncSpecificDate($date);
            return;
        }

        // Re-sync last N days
        if ($this->option('resync-last-days')) {
            $days = (int) $this->option('resync-last-days');
            $this->resyncLastDays($days);
            return;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get access token');
            return;
        }

        $this->info('✅ Access Token obtained successfully');

        // Auto-sync mode: Process pending/failed days
        if ($this->option('auto-sync')) {
            $this->autoSyncPendingDays($accessToken);
            return;
        }

        // Determine dates to sync
        $datesToSync = $this->determineDatesToSync();

        if (empty($datesToSync)) {
            $this->warn('No dates to sync. All days are already completed.');
            $this->info('Use --resync-date or --resync-last-days to re-fetch data.');
            return;
        }

        $this->info("📅 Syncing " . count($datesToSync) . " day(s)...\n");

        // Process each day sequentially
        foreach ($datesToSync as $date) {
            $this->syncSingleDay($accessToken, $date);
            
            // Small delay between days
            if (count($datesToSync) > 1) {
                sleep(2);
            }
        }

        $this->info("\n🎉 All days processed!");
        $this->showSyncStatus();
    }

    /**
     * Determine dates to sync based on command options
     */
    private function determineDatesToSync()
    {
        $dates = [];

        // Daily-based fetching options (using California/Pacific Time)
        if ($this->option('daily')) {
            $date = Carbon::today('America/Los_Angeles');
            $dates[] = $date;
            $this->ensureDailySyncRecord($date);
            return $dates;
        }

        if ($this->option('yesterday')) {
            $date = Carbon::yesterday('America/Los_Angeles');
            $dates[] = $date;
            $this->ensureDailySyncRecord($date);
            return $dates;
        }

        // Date range
        if ($this->option('from') && $this->option('to')) {
            $startDate = Carbon::parse($this->option('from'), 'America/Los_Angeles')->startOfDay();
            $endDate = Carbon::parse($this->option('to'), 'America/Los_Angeles')->startOfDay();
            
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dates[] = $currentDate->copy();
                $this->ensureDailySyncRecord($currentDate);
                $currentDate->addDay();
            }
            return $dates;
        }

        // Default: last N days
        $lastDays = (int) ($this->option('last-days') ?: 30);
        $this->info("Processing last {$lastDays} days (California Time)...");
        
        for ($i = $lastDays - 1; $i >= 0; $i--) {
            $date = Carbon::today('America/Los_Angeles')->subDays($i);
            $dates[] = $date;
            $this->ensureDailySyncRecord($date);
        }

        return $dates;
    }

    /**
     * Get end date respecting Amazon's 2-minute delay rule
     */
    private function getEndDateWithAmazonDelay($date)
    {
        $now = Carbon::now('America/Los_Angeles');
        $twoMinutesAgo = $now->copy()->subMinutes(2);
        
        // For today: use current time minus 2 minutes as end time
        if ($date->isToday('America/Los_Angeles')) {
            return $twoMinutesAgo;
        }
        
        // For past dates: use end of day, but respect 2-minute rule if recent
        $endDate = $date->copy()->endOfDay();
        if ($endDate->greaterThan($twoMinutesAgo)) {
            return $twoMinutesAgo;
        }
        
        return $endDate;
    }

    /**
     * Ensure a daily sync record exists for a given date
     */
    private function ensureDailySyncRecord($date)
    {
        $dateString = $date->format('Y-m-d');
        
        AmazonDailySync::firstOrCreate(
            ['sync_date' => $dateString],
            [
                'status' => AmazonDailySync::STATUS_PENDING,
                'orders_fetched' => 0,
                'pages_fetched' => 0,
                'items_fetched' => 0,
                'retry_count' => 0,
            ]
        );
    }

    /**
     * Sync a single day's orders with resume capability
     */
    private function syncSingleDay($accessToken, $date)
    {
        $dateString = $date->format('Y-m-d');
        
        // Get or create sync record
        $sync = AmazonDailySync::firstOrCreate(
            ['sync_date' => $dateString],
            [
                'status' => AmazonDailySync::STATUS_PENDING,
                'orders_fetched' => 0,
                'pages_fetched' => 0,
                'items_fetched' => 0,
                'retry_count' => 0,
            ]
        );

        // Check if already completed
        if ($sync->status === AmazonDailySync::STATUS_COMPLETED) {
            $this->info("✅ {$dateString}: Already completed ({$sync->orders_fetched} orders)");
            return;
        }

        // Update to in-progress
        if ($sync->status !== AmazonDailySync::STATUS_IN_PROGRESS) {
            $sync->update([
                'status' => AmazonDailySync::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'error_message' => null,
            ]);
        }

        $this->info("\n📅 Syncing: {$dateString}");
        if ($sync->next_token) {
            $this->info("   🔄 Resuming from page " . ($sync->pages_fetched + 1));
        }

        // Execute the fetch for this day
        $this->executeDailySync($accessToken, $sync, $date);
    }

    /**
     * Execute daily sync with immediate inserts and safe failure handling
     */
    private function executeDailySync($accessToken, $sync, $date)
    {
        $marketplaceId = config('services.amazon_sp.marketplace_id');
        $delay = (int) $this->option('delay') ?: 3;
        $maxRetries = (int) $this->option('max-retries') ?: 3;
        
        // Date range for this specific day
        $startDate = $date->copy()->startOfDay();
        $endDate = $this->getEndDateWithAmazonDelay($date);
        
        $createdAfter = $startDate->toIso8601ZuluString();
        $createdBefore = $endDate->toIso8601ZuluString();
        
        // Resume from saved NextToken if exists
        $nextToken = $sync->next_token;
        
        $totalOrdersFetched = $sync->orders_fetched;
        $totalPagesFetched = $sync->pages_fetched;
        $totalItemsFetched = $sync->items_fetched ?? 0;
        
        do {
            // Build API request params
            $params = [
                'MarketplaceIds' => $marketplaceId,
                'MaxResultsPerPage' => 100,
            ];
            
            // Use NextToken if resuming, otherwise use date range
            if ($nextToken) {
                $params['NextToken'] = $nextToken;
            } else {
                $params['CreatedAfter'] = $createdAfter;
                $params['CreatedBefore'] = $createdBefore;
            }

            // Fetch page with retry logic
            $attempt = 0;
            $response = null;
            $shouldStop = false;
            
            while ($attempt < $maxRetries) {
                $response = Http::timeout(30)->withHeaders([
                    'x-amz-access-token' => $accessToken,
                ])->get('https://sellingpartnerapi-na.amazon.com/orders/v0/orders', $params);

                if ($response->successful()) {
                    break; // Success - exit retry loop
                }

                $statusCode = $response->status();
                $body = $response->json();
                $errorCode = $body['errors'][0]['code'] ?? '';
                $errorMessage = $body['errors'][0]['message'] ?? $response->body();

                // Handle quota exceeded or rate limited - STOP SAFELY
                if ($statusCode === 429 || $errorCode === 'QuotaExceeded') {
                    $this->error("   ⚠️ Amazon API rate limit/quota exceeded.");
                    $this->error("   Error: {$errorMessage}");
                    $this->info("   💾 Saving progress for resume...");
                    
                    // Save sync state as FAILED
                    $sync->update([
                        'status' => AmazonDailySync::STATUS_FAILED,
                        'error_message' => "Rate limit/quota exceeded: {$errorMessage}",
                        'orders_fetched' => $totalOrdersFetched,
                        'pages_fetched' => $totalPagesFetched,
                        'items_fetched' => $totalItemsFetched,
                        'last_page_at' => now(),
                        'retry_count' => $sync->retry_count + 1,
                    ]);
                    
                    Log::error("Amazon Orders API quota exceeded", [
                        'date' => $sync->sync_date,
                        'next_token' => $nextToken ? substr($nextToken, 0, 50) . '...' : null,
                        'error' => $errorMessage,
                    ]);
                    
                    $this->info("   ✅ Progress saved. Run command again to resume.");
                    
                    $shouldStop = true;
                    break; // Exit retry loop
                }

                // Other errors - retry with backoff
                $attempt++;
                if ($attempt < $maxRetries) {
                    $waitTime = pow(2, $attempt) * 5; // 10s, 20s, 40s
                    $this->warn("   ⚠️ API error (attempt {$attempt}/{$maxRetries}). Waiting {$waitTime}s...");
                    Log::warning("Amazon API error, retrying", ['attempt' => $attempt, 'error' => $errorMessage]);
                    sleep($waitTime);
                } else {
                    // Max retries reached - save and stop
                    $this->error("   ❌ Max retries reached. Saving progress...");
                    $sync->update([
                        'status' => AmazonDailySync::STATUS_FAILED,
                        'error_message' => "Max retries reached: {$errorMessage}",
                        'orders_fetched' => $totalOrdersFetched,
                        'pages_fetched' => $totalPagesFetched,
                        'items_fetched' => $totalItemsFetched,
                        'last_page_at' => now(),
                        'retry_count' => $sync->retry_count + 1,
                    ]);
                    $shouldStop = true;
                }
            }

            // Stop if we encountered a fatal error
            if ($shouldStop || !$response || $response->failed()) {
                break;
            }

            // Parse response
            $data = $response->json();
            $orders = $data['payload']['Orders'] ?? [];
            $nextToken = $data['payload']['NextToken'] ?? null;
            
            $pageOrderCount = count($orders);
            $totalPagesFetched++;
            
            $this->info("   📄 Page {$totalPagesFetched}: {$pageOrderCount} orders");
            
            // INSERT orders immediately (no batching in memory)
            $insertedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $totalItemsFetchedThisPage = 0;
            
            foreach ($orders as $order) {
                $orderId = $order['AmazonOrderId'] ?? null;
                if (!$orderId) {
                    $skippedCount++;
                    continue;
                }

                // Use updateOrCreate to ensure we have the latest data
                $orderRecord = AmazonOrder::updateOrCreate(
                    ['amazon_order_id' => $orderId],
                    [
                        'order_date' => Carbon::parse($order['PurchaseDate']),
                        'status' => $order['OrderStatus'] ?? null,
                        'total_amount' => $order['OrderTotal']['Amount'] ?? 0,
                        'currency' => $order['OrderTotal']['CurrencyCode'] ?? 'USD',
                        'raw_data' => json_encode($order),
                    ]
                );

                // Check if it was just created
                if ($orderRecord->wasRecentlyCreated) {
                    $insertedCount++;
                } else {
                    $updatedCount++;
                }

                // Fetch order items if --with-items flag is set
                if ($this->option('with-items')) {
                    $items = $this->fetchOrderItemsWithRetry($accessToken, $orderId);
                    
                    foreach ($items as $item) {
                        // Calculate total price including all components (matches Amazon Seller Central "Ordered product sales")
                        $itemPrice = 0;
                        $itemCurrency = 'USD';
                        
                        // ItemPrice (base product price × quantity)
                        if (isset($item['ItemPrice']) && is_array($item['ItemPrice'])) {
                            $itemPrice += floatval($item['ItemPrice']['Amount'] ?? 0);
                            $itemCurrency = $item['ItemPrice']['CurrencyCode'] ?? 'USD';
                        }
                        
                        // ShippingPrice (shipping charged to customer)
                        if (isset($item['ShippingPrice']) && is_array($item['ShippingPrice'])) {
                            $itemPrice += floatval($item['ShippingPrice']['Amount'] ?? 0);
                        }
                        
                        // GiftWrapPrice (gift wrap fees)
                        if (isset($item['GiftWrapPrice']) && is_array($item['GiftWrapPrice'])) {
                            $itemPrice += floatval($item['GiftWrapPrice']['Amount'] ?? 0);
                        }
                        
                        // Subtract PromotionDiscount (discounts reduce the total)
                        if (isset($item['PromotionDiscount']) && is_array($item['PromotionDiscount'])) {
                            $itemPrice -= floatval($item['PromotionDiscount']['Amount'] ?? 0);
                        }
                        
                        AmazonOrderItem::updateOrCreate(
                            [
                                'amazon_order_id' => $orderRecord->id,
                                'asin' => $item['ASIN'] ?? null,
                                'sku' => $item['SellerSKU'] ?? null,
                            ],
                            [
                                'quantity' => $item['QuantityOrdered'] ?? 1,
                                'price' => $itemPrice,
                                'currency' => $itemCurrency,
                                'title' => $item['Title'] ?? null,
                                'raw_data' => json_encode($item),
                            ]
                        );
                        $totalItemsFetchedThisPage++;
                    }

                    // Small delay between item requests to respect rate limits
                    if (count($items) > 0) {
                        usleep(500000); // 500ms
                    }
                }
            }
            
            $totalOrdersFetched += $insertedCount;
            $totalItemsFetched += $totalItemsFetchedThisPage;
            
            if ($this->option('with-items')) {
                $this->info("      ✅ New: {$insertedCount}, Updated: {$updatedCount}, Items: {$totalItemsFetchedThisPage}, Total Orders: {$totalOrdersFetched}, Total Items: {$totalItemsFetched}");
            } else {
                $this->info("      ✅ New: {$insertedCount}, Updated: {$updatedCount}, Total: {$totalOrdersFetched}");
            }
            
            // SAVE sync state after EVERY successful page
            $sync->update([
                'next_token' => $nextToken,
                'orders_fetched' => $totalOrdersFetched,
                'pages_fetched' => $totalPagesFetched,
                'items_fetched' => $totalItemsFetched,
                'last_page_at' => now(),
            ]);
            
            // If no more pages, mark as completed
            if (!$nextToken) {
                if ($this->option('with-items')) {
                    $this->info("   ✅ Day completed: {$totalOrdersFetched} orders, {$totalItemsFetched} items in {$totalPagesFetched} pages");
                } else {
                    $this->info("   ✅ Day completed: {$totalOrdersFetched} orders in {$totalPagesFetched} pages");
                }
                $sync->update([
                    'status' => AmazonDailySync::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'next_token' => null,
                ]);
                break;
            }
            
            // Rate limiting between pages
            sleep($delay);
            
        } while ($nextToken);

        // If sync is still in progress (didn't complete), it failed
        if ($sync->status === AmazonDailySync::STATUS_IN_PROGRESS) {
            $sync->update(['status' => AmazonDailySync::STATUS_FAILED]);
        }
    }

    /**
     * Show sync status for all tracked days
     */
    private function showSyncStatus()
    {
        $syncs = AmazonDailySync::orderBy('sync_date', 'desc')->take(90)->get();
        
        if ($syncs->isEmpty()) {
            $this->warn('No sync records found. Use --initialize-days to create tracking records.');
            return;
        }

        $this->info("\n📊 Sync Status (Last 90 days):\n");
        
        $statusCounts = [
            'completed' => 0,
            'failed' => 0,
            'in_progress' => 0,
            'pending' => 0,
        ];

        $this->table(
            ['Date', 'Status', 'Orders', 'Items', 'Pages', 'Last Update', 'Error'],
            $syncs->map(function ($sync) use (&$statusCounts) {
                $statusCounts[$sync->status]++;
                
                $statusEmoji = [
                    'completed' => '✅',
                    'failed' => '❌',
                    'in_progress' => '⏳',
                    'pending' => '⏸️',
                    'skipped' => '⏭️',
                ];
                
                return [
                    $sync->sync_date,
                    ($statusEmoji[$sync->status] ?? '') . ' ' . $sync->status,
                    $sync->orders_fetched,
                    $sync->items_fetched ?? 0,
                    $sync->pages_fetched,
                    $sync->last_page_at ? $sync->last_page_at->diffForHumans() : '-',
                    $sync->error_message ? substr($sync->error_message, 0, 50) . '...' : '-',
                ];
            })
        );

        $this->info("\nSummary:");
        $this->info("  ✅ Completed: {$statusCounts['completed']}");
        $this->info("  ❌ Failed: {$statusCounts['failed']}");
        $this->info("  ⏳ In Progress: {$statusCounts['in_progress']}");
        $this->info("  ⏸️ Pending: {$statusCounts['pending']}");
    }

    /**
     * Initialize daily tracking for last N days
     */
    private function initializeDailyTracking($days)
    {
        $this->info("Initializing tracking for last {$days} days...");
        
        $created = 0;
        $existing = 0;
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today('America/Los_Angeles')->subDays($i);
            $dateString = $date->format('Y-m-d');
            
            $sync = AmazonDailySync::firstOrCreate(
                ['sync_date' => $dateString],
                [
                    'status' => AmazonDailySync::STATUS_PENDING,
                    'orders_fetched' => 0,
                    'pages_fetched' => 0,
                    'items_fetched' => 0,
                    'retry_count' => 0,
                ]
            );

            if ($sync->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        $this->info("✅ Initialized: {$created} new days, {$existing} existing days");
        $this->showSyncStatus();
    }

    /**
     * Auto-sync all pending or failed days
     */
    private function autoSyncPendingDays($accessToken)
    {
        $pendingSyncs = AmazonDailySync::needsSync()
            ->orderBy('sync_date', 'asc')
            ->get();

        if ($pendingSyncs->isEmpty()) {
            $this->info('✅ No pending or failed days to sync. All up to date!');
            return;
        }

        $this->info("Found " . $pendingSyncs->count() . " day(s) needing sync.\n");

        foreach ($pendingSyncs as $sync) {
            $date = Carbon::parse($sync->sync_date, 'America/Los_Angeles');
            $this->syncSingleDay($accessToken, $date);
            
            // Small delay between days
            sleep(2);

            // Refresh the sync record
            $sync->refresh();
            
            // If this day failed, stop auto-sync to prevent cascading failures
            if ($sync->status === AmazonDailySync::STATUS_FAILED) {
                $this->warn("\n⚠️ Day {$sync->sync_date} failed. Stopping auto-sync.");
                $this->info("Fix the issue and run again to continue from this point.");
                break;
            }
        }

        $this->info("\n🎉 Auto-sync completed!");
        $this->showSyncStatus();
    }

    /**
     * Re-sync a specific date (marks it as pending first)
     */
    private function resyncSpecificDate($date)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get access token');
            return;
        }

        $dateString = $date->format('Y-m-d');
        
        $sync = AmazonDailySync::where('sync_date', $dateString)->first();
        
        if (!$sync) {
            $this->info("Creating new sync record for {$dateString}");
            $sync = AmazonDailySync::create([
                'sync_date' => $dateString,
                'status' => AmazonDailySync::STATUS_PENDING,
                'orders_fetched' => 0,
                'pages_fetched' => 0,
                'items_fetched' => 0,
                'retry_count' => 0,
            ]);
        } else {
            $this->info("Resetting sync record for {$dateString}");
            $sync->update([
                'status' => AmazonDailySync::STATUS_PENDING,
                'next_token' => null,
                'orders_fetched' => 0,
                'pages_fetched' => 0,
                'items_fetched' => 0,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
                'last_page_at' => null,
            ]);
        }

        $this->syncSingleDay($accessToken, $date);
    }

    /**
     * Re-sync last N days
     */
    private function resyncLastDays($days)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get access token');
            return;
        }

        $this->info("Re-syncing last {$days} days...\n");
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today('America/Los_Angeles')->subDays($i);
            $dateString = $date->format('Y-m-d');
            
            $sync = AmazonDailySync::where('sync_date', $dateString)->first();
            
            if (!$sync) {
                $sync = AmazonDailySync::create([
                    'sync_date' => $dateString,
                    'status' => AmazonDailySync::STATUS_PENDING,
                    'orders_fetched' => 0,
                    'pages_fetched' => 0,
                    'items_fetched' => 0,
                    'retry_count' => 0,
                ]);
            } else {
                $sync->update([
                    'status' => AmazonDailySync::STATUS_PENDING,
                    'next_token' => null,
                    'orders_fetched' => 0,
                    'pages_fetched' => 0,
                    'items_fetched' => 0,
                    'error_message' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    'last_page_at' => null,
                ]);
            }

            $this->syncSingleDay($accessToken, $date);
            
            // Small delay between days
            if ($i > 0) {
                sleep(2);
            }
        }

        $this->info("\n🎉 Re-sync completed!");
        $this->showSyncStatus();
    }

    /**
     * Fetch items for orders that don't have any items
     */
    private function fetchMissingItems()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get access token');
            return;
        }

        $ordersWithoutItems = AmazonOrder::whereDoesntHave('items')->get();
        $total = $ordersWithoutItems->count();
        
        if ($total === 0) {
            $this->info('No orders with missing items found.');
            return;
        }

        $this->info("Found {$total} orders without items. Fetching items...");
        
        $success = 0;
        $failed = 0;

        foreach ($ordersWithoutItems as $index => $order) {
            $items = $this->fetchOrderItemsWithRetry($accessToken, $order->amazon_order_id);
            
            if (count($items) > 0) {
                foreach ($items as $item) {
                    // Safely extract ItemPrice - it may not exist for some items
                    $itemPrice = 0;
                    $itemCurrency = 'USD';
                    if (isset($item['ItemPrice']) && is_array($item['ItemPrice'])) {
                        $itemPrice = floatval($item['ItemPrice']['Amount'] ?? 0);
                        $itemCurrency = $item['ItemPrice']['CurrencyCode'] ?? 'USD';
                    }
                    
                    AmazonOrderItem::updateOrCreate(
                        [
                            'amazon_order_id' => $order->id, 
                            'asin' => $item['ASIN'] ?? null,
                            'sku' => $item['SellerSKU'] ?? null,
                        ],
                        [
                            'quantity' => $item['QuantityOrdered'] ?? 1,
                            'price' => $itemPrice,
                            'currency' => $itemCurrency,
                            'title' => $item['Title'] ?? null,
                            'raw_data' => json_encode($item),
                        ]
                    );
                }
                $success++;
            } else {
                $failed++;
            }

            // Progress update every 50 orders
            if (($index + 1) % 50 === 0) {
                $this->info("  Progress: " . ($index + 1) . "/{$total} - Success: {$success}, Failed: {$failed}");
            }

            // Rate limiting - 500ms between requests
            usleep(500000);
        }

        $this->info("✅ Missing items fetch complete. Success: {$success}, Failed: {$failed}");
    }

    /**
     * Fix items with $0 price by:
     * 1. Re-fetching from Amazon API
     * 2. Looking up price from product_master (using current Amazon price)
     * 3. Using order total_amount if single item order
     */
    private function fixZeroPriceItems()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get access token');
            return;
        }

        // Get all items with $0 price
        $zeroPriceItems = AmazonOrderItem::where('price', 0)
            ->orWhereNull('price')
            ->with('order')
            ->get();
        
        $total = $zeroPriceItems->count();
        
        if ($total === 0) {
            $this->info('No items with $0 price found.');
            return;
        }

        $this->info("Found {$total} items with \$0 price. Fixing...");
        
        // Load product_master prices into memory for fast lookup
        $skus = $zeroPriceItems->pluck('sku')->filter()->unique()->values()->toArray();
        $productPrices = [];
        
        if (!empty($skus)) {
            $products = ProductMaster::whereIn('sku', $skus)->get();
            foreach ($products as $pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                // Try to get Amazon price from product_master
                $amzPrice = 0;
                foreach ($values as $k => $v) {
                    $keyLower = strtolower($k);
                    if (in_array($keyLower, ['amz', 'amazon', 'amz_price', 'amazon_price', 'price'])) {
                        $amzPrice = floatval($v);
                        break;
                    }
                }
                if ($amzPrice > 0) {
                    $productPrices[$pm->sku] = $amzPrice;
                }
            }
        }
        
        $this->info("  Loaded prices for " . count($productPrices) . " SKUs from product_master");
        
        $fixedFromApi = 0;
        $fixedFromProductMaster = 0;
        $fixedFromOrderTotal = 0;
        $stillZero = 0;

        foreach ($zeroPriceItems as $index => $item) {
            $newPrice = 0;
            $source = '';
            
            // Method 1: Try to re-fetch from API with all price components
            $order = $item->order;
            if ($order) {
                $apiItems = $this->fetchOrderItemsWithRetry($accessToken, $order->amazon_order_id);
                foreach ($apiItems as $apiItem) {
                    if (($apiItem['ASIN'] ?? '') === $item->asin && ($apiItem['SellerSKU'] ?? '') === $item->sku) {
                        // Calculate total price including all components
                        $totalPrice = 0;
                        
                        // ItemPrice
                        if (isset($apiItem['ItemPrice']['Amount'])) {
                            $totalPrice += floatval($apiItem['ItemPrice']['Amount']);
                        }
                        
                        // ShippingPrice
                        if (isset($apiItem['ShippingPrice']['Amount'])) {
                            $totalPrice += floatval($apiItem['ShippingPrice']['Amount']);
                        }
                        
                        // GiftWrapPrice
                        if (isset($apiItem['GiftWrapPrice']['Amount'])) {
                            $totalPrice += floatval($apiItem['GiftWrapPrice']['Amount']);
                        }
                        
                        // Subtract PromotionDiscount
                        if (isset($apiItem['PromotionDiscount']['Amount'])) {
                            $totalPrice -= floatval($apiItem['PromotionDiscount']['Amount']);
                        }
                        
                        if ($totalPrice > 0) {
                            $newPrice = $totalPrice;
                            $source = 'API';
                            break;
                        }
                    }
                }
            }
            
            // Method 2: Look up from product_master (if API didn't work)
            if ($newPrice == 0 && $item->sku && isset($productPrices[$item->sku])) {
                $quantity = $item->quantity ?: 1;
                $newPrice = $productPrices[$item->sku] * $quantity;
                $source = 'ProductMaster';
            }
            
            // Method 3: Use order total if single item order
            if ($newPrice == 0 && $order) {
                $itemsInOrder = AmazonOrderItem::where('amazon_order_id', $order->id)->count();
                if ($itemsInOrder == 1 && $order->total_amount > 0) {
                    $newPrice = floatval($order->total_amount);
                    $source = 'OrderTotal';
                }
            }
            
            // Update if we found a price
            if ($newPrice > 0) {
                $item->price = round($newPrice, 2);
                $item->save();
                
                switch ($source) {
                    case 'API': $fixedFromApi++; break;
                    case 'ProductMaster': $fixedFromProductMaster++; break;
                    case 'OrderTotal': $fixedFromOrderTotal++; break;
                }
            } else {
                $stillZero++;
            }

            // Progress update every 50 items
            if (($index + 1) % 50 === 0) {
                $this->info("  Progress: " . ($index + 1) . "/{$total}");
            }

            // Rate limiting for API calls
            usleep(300000); // 300ms
        }

        $this->info("✅ Zero price fix complete:");
        $this->info("   Fixed from API: {$fixedFromApi}");
        $this->info("   Fixed from ProductMaster: {$fixedFromProductMaster}");
        $this->info("   Fixed from OrderTotal: {$fixedFromOrderTotal}");
        $this->info("   Still $0: {$stillZero}");
    }

    private function getAccessToken()
    {
        $res = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => config('services.amazon_sp.refresh_token'),
            'client_id' => config('services.amazon_sp.client_id'),
            'client_secret' => config('services.amazon_sp.client_secret'),
        ]);

        return $res['access_token'] ?? null;
    }

    private function fetchOrderItemsWithRetry($accessToken, $orderId, $maxRetries = 5)
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $response = Http::timeout(30)->withHeaders([
                'x-amz-access-token' => $accessToken,
            ])->get("https://sellingpartnerapi-na.amazon.com/orders/v0/orders/{$orderId}/orderItems");

            if ($response->successful()) {
                return $response->json()['payload']['OrderItems'] ?? [];
            }

            $statusCode = $response->status();
            $body = $response->json();
            $errorCode = $body['errors'][0]['code'] ?? '';
            
            // Rate limited or quota exceeded - wait and retry
            if ($statusCode === 429 || $errorCode === 'QuotaExceeded') {
                $attempt++;
                $waitTime = pow(2, $attempt) * 15; // Exponential backoff: 30, 60, 120, 240, 480 seconds
                Log::warning("Rate limited/quota exceeded for order {$orderId}, waiting {$waitTime}s (attempt {$attempt}/{$maxRetries})");
                sleep($waitTime);
                continue;
            }

            // Other error - log and return empty
            Log::warning("Failed to fetch items for order {$orderId}: " . $response->body());
            break;
        }

        return [];
    }
}
