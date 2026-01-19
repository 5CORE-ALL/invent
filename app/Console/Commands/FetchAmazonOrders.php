<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\AmazonOrderCursor;
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
        {--new-only : Only fetch orders newer than the latest order in database}
        {--from= : Fetch orders from this date (Y-m-d format)}
        {--to= : Fetch orders to this date (Y-m-d format)}
        {--limit= : Maximum number of orders to fetch (no limit by default)}
        {--delay=3 : Delay in seconds between API requests (default: 3)}
        {--reset-cursor : Reset cursor and start fresh (ignores existing cursor)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Amazon orders daily and insert into database (uses California/Pacific Time) - CURSOR-BASED';

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

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get access token');
            return;
        }

        $this->info('Access Token obtained successfully');

        // Determine date range for cursor
        $dateRange = $this->determineDateRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        $this->info("Date Range: {$startDate->toDateTimeString()} to {$endDate->toDateTimeString()}");

        // Execute cursor-based fetch
        $this->fetchOrdersWithCursor($accessToken, $startDate, $endDate);
    }

    /**
     * Determine date range based on command options
     */
    private function determineDateRange()
    {
        // Check if specific date range is provided
        if ($this->option('from') && $this->option('to')) {
            return [
                'start' => Carbon::parse($this->option('from'))->startOfDay(),
                'end' => Carbon::parse($this->option('to'))->endOfDay(),
            ];
        }

        // Check if we should only fetch new orders
        if ($this->option('new-only')) {
            $lastOrderDate = AmazonOrder::max('order_date');
            
            if (!$lastOrderDate) {
                $this->info('No existing orders found. Fetching last 30 days (California Time)...');
                return [
                    'start' => Carbon::today('America/Los_Angeles')->subDays(29)->startOfDay(),
                    'end' => Carbon::today('America/Los_Angeles')->endOfDay(),
                ];
            }

            return [
                'start' => Carbon::parse($lastOrderDate)->addDay()->startOfDay(),
                'end' => Carbon::today('America/Los_Angeles')->endOfDay(),
            ];
        }

        // Daily-based fetching options (using California/Pacific Time)
        if ($this->option('daily')) {
            $date = Carbon::today('America/Los_Angeles');
            return [
                'start' => $date->copy()->startOfDay(),
                'end' => $this->getEndDateWithAmazonDelay($date),
            ];
        }

        if ($this->option('yesterday')) {
            $date = Carbon::yesterday('America/Los_Angeles');
            return [
                'start' => $date->copy()->startOfDay(),
                'end' => $this->getEndDateWithAmazonDelay($date),
            ];
        }

        // Default: fetch orders for last N days
        $lastDays = (int) ($this->option('last-days') ?: 30);
        $this->info("Fetching orders for last {$lastDays} days (California Time)...");
        
        return [
            'start' => Carbon::today('America/Los_Angeles')->subDays($lastDays - 1)->startOfDay(),
            'end' => Carbon::now('America/Los_Angeles')->subMinutes(2), // Amazon 2-minute rule
        ];
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
     * Cursor-based fetch: Insert orders immediately after each page, resume on failure
     */
    private function fetchOrdersWithCursor($accessToken, $startDate, $endDate)
    {
        // Generate unique cursor key based on date range
        $cursorKey = 'orders_' . $startDate->format('Ymd_His') . '_to_' . $endDate->format('Ymd_His');
        
        // Check for existing cursor
        $cursor = AmazonOrderCursor::where('cursor_key', $cursorKey)->first();
        
        // Reset cursor if requested
        if ($this->option('reset-cursor') && $cursor) {
            $this->warn("Resetting cursor: {$cursorKey}");
            $cursor->delete();
            $cursor = null;
        }
        
        // If cursor is completed, exit
        if ($cursor && $cursor->status === 'completed') {
            $this->info("✅ Cursor already completed. Fetched {$cursor->orders_fetched} orders in {$cursor->pages_fetched} pages.");
            $this->info("Completed at: {$cursor->completed_at}");
            $this->info("Use --reset-cursor to fetch again.");
            return;
        }
        
        // Create or resume cursor
        if (!$cursor) {
            $cursor = AmazonOrderCursor::create([
                'cursor_key' => $cursorKey,
                'status' => 'running',
                'started_at' => now(),
                'orders_fetched' => 0,
                'pages_fetched' => 0,
            ]);
            $this->info("🆕 Created new cursor: {$cursorKey}");
        } else {
            $this->info("🔄 Resuming cursor: {$cursorKey}");
            $this->info("   Status: {$cursor->status}");
            $this->info("   Progress: {$cursor->orders_fetched} orders, {$cursor->pages_fetched} pages");
            $this->info("   Last page at: {$cursor->last_page_at}");
            
            // Reset status to running if it was failed
            if ($cursor->status === 'failed') {
                $cursor->update(['status' => 'running', 'error_message' => null]);
            }
        }
        
        // Execute cursor-based pagination
        $this->executeCursorFetch($accessToken, $cursor, $startDate, $endDate);
    }

    /**
     * Execute cursor-based fetch with immediate inserts and safe failure handling
     */
    private function executeCursorFetch($accessToken, $cursor, $startDate, $endDate)
    {
        $marketplaceId = env('SPAPI_MARKETPLACE_ID');
        $delay = (int) $this->option('delay') ?: 3;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $maxRetries = 3; // Lower retries - we'll resume on next run
        
        $createdAfter = $startDate->toIso8601ZuluString();
        $createdBefore = $endDate->toIso8601ZuluString();
        
        // Resume from saved NextToken if exists
        $nextToken = $cursor->next_token;
        
        $totalOrdersFetched = $cursor->orders_fetched;
        $totalPagesFetched = $cursor->pages_fetched;
        
        do {
            // Check limit before fetching more
            if ($limit && $totalOrdersFetched >= $limit) {
                $this->info("✅ Reached limit of {$limit} orders. Marking cursor as completed.");
                $cursor->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'orders_fetched' => $totalOrdersFetched,
                    'pages_fetched' => $totalPagesFetched,
                    'next_token' => null,
                ]);
                break;
            }

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
                $response = Http::withHeaders([
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
                    $this->error("⚠️ Amazon API rate limit/quota exceeded.");
                    $this->error("   Error: {$errorMessage}");
                    $this->info("💾 Saving cursor state for resume...");
                    
                    // Save cursor state as FAILED
                    $cursor->update([
                        'status' => 'failed',
                        'error_message' => "Rate limit/quota exceeded: {$errorMessage}",
                        'orders_fetched' => $totalOrdersFetched,
                        'pages_fetched' => $totalPagesFetched,
                        'last_page_at' => now(),
                    ]);
                    
                    Log::error("Amazon Orders API quota exceeded, cursor saved", [
                        'cursor_key' => $cursor->cursor_key,
                        'next_token' => $nextToken ? substr($nextToken, 0, 50) . '...' : null,
                        'error' => $errorMessage,
                    ]);
                    
                    $this->info("✅ Cursor saved. Run command again to resume from this point.");
                    $this->info("   Orders fetched so far: {$totalOrdersFetched}");
                    $this->info("   Pages fetched so far: {$totalPagesFetched}");
                    
                    $shouldStop = true;
                    break; // Exit retry loop
                }

                // Other errors - retry with backoff
                $attempt++;
                if ($attempt < $maxRetries) {
                    $waitTime = pow(2, $attempt) * 5; // 10s, 20s, 40s
                    $this->warn("  ⚠️ API error (attempt {$attempt}/{$maxRetries}). Waiting {$waitTime}s...");
                    Log::warning("Amazon API error, retrying", ['attempt' => $attempt, 'error' => $errorMessage]);
                    sleep($waitTime);
                } else {
                    // Max retries reached - save and stop
                    $this->error("❌ Max retries reached. Saving cursor state...");
                    $cursor->update([
                        'status' => 'failed',
                        'error_message' => "Max retries reached: {$errorMessage}",
                        'orders_fetched' => $totalOrdersFetched,
                        'pages_fetched' => $totalPagesFetched,
                        'last_page_at' => now(),
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
            
            $this->info("📄 Page {$totalPagesFetched}: Fetched {$pageOrderCount} orders");
            
            // INSERT orders immediately (no batching in memory)
            $insertedCount = 0;
            $skippedCount = 0;
            
            foreach ($orders as $order) {
                $orderId = $order['AmazonOrderId'] ?? null;
                if (!$orderId) {
                    $skippedCount++;
                    continue;
                }

                // Use firstOrCreate to avoid duplicates (does NOT update existing)
                $orderRecord = AmazonOrder::firstOrCreate(
                    ['amazon_order_id' => $orderId],
                    [
                        'order_date' => Carbon::parse($order['PurchaseDate']),
                        'status' => $order['OrderStatus'] ?? null,
                        'total_amount' => $order['OrderTotal']['Amount'] ?? 0,
                        'currency' => $order['OrderTotal']['CurrencyCode'] ?? 'USD',
                        'raw_data' => json_encode($order),
                    ]
                );

                // Check if it was just created (wasRecentlyCreated)
                if ($orderRecord->wasRecentlyCreated) {
                    $insertedCount++;
                }
            }
            
            $totalOrdersFetched += $insertedCount;
            
            $this->info("   ✅ Inserted: {$insertedCount}, Skipped (duplicates): " . ($pageOrderCount - $insertedCount - $skippedCount));
            $this->info("   📊 Total orders fetched: {$totalOrdersFetched}");
            
            // SAVE cursor state after EVERY successful page
            $cursor->update([
                'next_token' => $nextToken,
                'orders_fetched' => $totalOrdersFetched,
                'pages_fetched' => $totalPagesFetched,
                'last_page_at' => now(),
            ]);
            
            // If no more pages, mark as completed
            if (!$nextToken) {
                $this->info("✅ All pages fetched. Marking cursor as completed.");
                $cursor->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'next_token' => null,
                ]);
                break;
            }
            
            // Rate limiting between pages
            $this->info("   ⏳ Waiting {$delay}s before next page...");
            sleep($delay);
            
        } while ($nextToken);

        // Final summary
        if ($cursor->status === 'completed') {
            $this->info("\n🎉 ✅ Cursor fetch COMPLETED!");
            $this->info("   Total Orders Fetched: {$totalOrdersFetched}");
            $this->info("   Total Pages Fetched: {$totalPagesFetched}");
            $this->info("   Started at: {$cursor->started_at}");
            $this->info("   Completed at: {$cursor->completed_at}");
        } else {
            $this->warn("\n⚠️ Cursor fetch PAUSED (will resume on next run).");
            $this->info("   Orders fetched so far: {$totalOrdersFetched}");
            $this->info("   Pages fetched so far: {$totalPagesFetched}");
        }
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
            
            // Method 1: Try to re-fetch from API
            $order = $item->order;
            if ($order) {
                $apiItems = $this->fetchOrderItemsWithRetry($accessToken, $order->amazon_order_id);
                foreach ($apiItems as $apiItem) {
                    if (($apiItem['ASIN'] ?? '') === $item->asin && ($apiItem['SellerSKU'] ?? '') === $item->sku) {
                        if (isset($apiItem['ItemPrice']['Amount']) && floatval($apiItem['ItemPrice']['Amount']) > 0) {
                            $newPrice = floatval($apiItem['ItemPrice']['Amount']);
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
            'refresh_token' => env('SPAPI_REFRESH_TOKEN'),
            'client_id' => env('SPAPI_CLIENT_ID'),
            'client_secret' => env('SPAPI_CLIENT_SECRET'),
        ]);

        return $res['access_token'] ?? null;
    }

    private function fetchOrderItemsWithRetry($accessToken, $orderId, $maxRetries = 5)
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $response = Http::withHeaders([
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
