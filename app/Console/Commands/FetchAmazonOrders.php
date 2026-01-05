<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
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
        {--last-days=7 : Fetch orders for last N days (default: 7)}
        {--new-only : Only fetch orders newer than the latest order in database}
        {--from= : Fetch orders from this date (Y-m-d format)}
        {--to= : Fetch orders to this date (Y-m-d format)}
        {--limit= : Maximum number of orders to fetch (no limit by default)}
        {--delay=3 : Delay in seconds between API requests (default: 3)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Amazon orders daily and insert into database with date-based storage';

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

        // Check if specific date range is provided
        if ($this->option('from') && $this->option('to')) {
            $this->fetchDateRange($accessToken);
            return;
        }

        // Check if we should only fetch new orders
        if ($this->option('new-only')) {
            $this->fetchNewOrdersOnly($accessToken);
            return;
        }

        // Daily-based fetching options
        if ($this->option('daily')) {
            $this->fetchDailyOrders($accessToken, Carbon::today());
            return;
        }

        if ($this->option('yesterday')) {
            $this->fetchDailyOrders($accessToken, Carbon::yesterday());
            return;
        }

        // Default: fetch orders for last N days
        $lastDays = (int) ($this->option('last-days') ?: 7);
        $this->fetchLastDaysOrders($accessToken, $lastDays);

        // After inserting new orders, fetch items for any orders missing items
        $this->info('Checking for orders with missing items...');
        $this->fetchMissingItems();

        $this->info('✅ Amazon Orders inserted successfully!');
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

    /**
     * Fetch orders for a specific single day
     */
    private function fetchDailyOrders($accessToken, $date)
    {
        $startDate = $date->copy()->startOfDay();
        
        // Amazon requires 2-minute delay - can't fetch orders newer than 2 minutes ago
        $now = Carbon::now();
        $twoMinutesAgo = $now->copy()->subMinutes(2);
        
        // For today: use current time minus 2 minutes as end time
        // For past dates: use end of day
        if ($date->isToday()) {
            $endDate = $twoMinutesAgo;
            $this->info("Fetching orders for {$date->toDateString()} (up to " . $endDate->format('H:i:s') . ")...");
        } else {
            $endDate = $date->copy()->endOfDay();
            // But still respect the 2-minute rule if date is recent
            if ($endDate->greaterThan($twoMinutesAgo)) {
                $endDate = $twoMinutesAgo;
            }
            $this->info("Fetching orders for {$date->toDateString()}...");
        }
        
        $orders = $this->fetchOrders($accessToken, $startDate, $endDate);
        $this->info("Fetched " . count($orders) . " orders for {$date->toDateString()}");
        
        if (count($orders) > 0) {
            $this->insertOrders($orders, null, $accessToken);
        } else {
            $this->info('No orders found for this date.');
        }
    }

    /**
     * Fetch orders for the last N days
     */
    private function fetchLastDaysOrders($accessToken, $days)
    {
        $this->info("Fetching orders for last {$days} days (respecting Amazon's 2-minute delay)...");
        
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::today()->subDays($i);
            
            // Skip if this would be too recent (less than 2 minutes ago)
            $twoMinutesAgo = Carbon::now()->subMinutes(2);
            if ($i == 0 && $date->startOfDay()->greaterThan($twoMinutesAgo)) {
                $this->info("Skipping {$date->toDateString()} - too recent (Amazon 2-minute rule)");
                continue;
            }
            
            $this->fetchDailyOrders($accessToken, $date);
            
            // Small delay between days to respect rate limits
            if ($i < $days - 1) {
                sleep(1);
            }
        }
    }

    /**
     * Fetch orders for a specific date range
     */
    private function fetchDateRange($accessToken)
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $startDate = Carbon::parse($this->option('from'));
        $endDate = Carbon::parse($this->option('to'));

        $this->info("Fetching orders from {$startDate->toDateString()} to {$endDate->toDateString()}...");
        $this->info("Limit: " . ($limit ? "{$limit} orders" : "No limit (fetching all)"));

        $orders = $this->fetchOrders($accessToken, $startDate, $endDate, $limit);
        $this->info("Fetched " . count($orders) . " orders");

        if (count($orders) > 0) {
            $this->insertOrders($orders, null, $accessToken);
        }

        $this->info('✅ Date range orders fetched successfully!');
    }

    /**
     * Fetch only new orders (from last order date to yesterday)
     */
    private function fetchNewOrdersOnly($accessToken)
    {
        $lastOrderDate = AmazonOrder::max('order_date');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        
        if (!$lastOrderDate) {
            $this->info('No existing orders found. Fetching last 30 days...');
            $startDate = Carbon::today()->subDays(30);
            $endDate = Carbon::yesterday();
            $orders = $this->fetchOrders($accessToken, $startDate, $endDate, $limit);
            $this->info("Fetched " . count($orders) . " orders for last 30 days");
            $this->insertOrders($orders, null, $accessToken);
            return;
        }

        $startDate = Carbon::parse($lastOrderDate)->addDay();
        $endDate = Carbon::yesterday();

        if ($startDate->greaterThan($endDate)) {
            $this->info('Database is already up to date! Last order: ' . $lastOrderDate);
            return;
        }

        $this->info("Fetching NEW orders from {$startDate->toDateString()} to {$endDate->toDateString()}...");
        $this->info("(Skipping orders before {$startDate->toDateString()} - already in database)");
        $this->info("Limit: " . ($limit ? "{$limit} orders" : "No limit (fetching all)"));

        $orders = $this->fetchOrders($accessToken, $startDate, $endDate, $limit);
        $this->info("Fetched " . count($orders) . " NEW orders");

        if (count($orders) > 0) {
            $this->insertOrders($orders, null, $accessToken);
        }

        $this->info('✅ New orders fetched successfully!');
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

    private function fetchOrders($accessToken, $startDate, $endDate, $limit = null)
    {
        $marketplaceId = env('SPAPI_MARKETPLACE_ID');
        $orders = [];
        $nextToken = null;
        $delay = (int) $this->option('delay') ?: 3;
        $maxRetries = 5;

        $createdAfter = $startDate->toIso8601ZuluString();
        $createdBefore = $endDate->endOfDay()->toIso8601ZuluString();

        do {
            // Check limit before fetching more
            if ($limit && count($orders) >= $limit) {
                $this->info("  Reached limit of {$limit} orders. Stopping fetch.");
                break;
            }

            $params = [
                'MarketplaceIds' => $marketplaceId,
                'CreatedAfter' => $createdAfter,
                'CreatedBefore' => $createdBefore,
                'MaxResultsPerPage' => 100,
            ];

            if ($nextToken) {
                $params['NextToken'] = $nextToken;
            }

            // Retry loop for quota/rate limit errors
            $attempt = 0;
            $response = null;
            while ($attempt < $maxRetries) {
                $response = Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                ])->get('https://sellingpartnerapi-na.amazon.com/orders/v0/orders', $params);

                if ($response->successful()) {
                    break;
                }

                $statusCode = $response->status();
                $body = $response->json();
                $errorCode = $body['errors'][0]['code'] ?? '';

                // Handle quota exceeded or rate limited
                if ($statusCode === 429 || $errorCode === 'QuotaExceeded') {
                    $attempt++;
                    $waitTime = pow(2, $attempt) * 30; // Exponential backoff: 60s, 120s, 240s, 480s, 960s
                    $this->warn("  ⚠️ Quota exceeded. Waiting {$waitTime}s before retry (attempt {$attempt}/{$maxRetries})...");
                    Log::warning("Amazon API quota exceeded, waiting {$waitTime}s", ['attempt' => $attempt]);
                    sleep($waitTime);
                    continue;
                }

                // Other error - break out
                $this->error('Failed to fetch orders: ' . $response->body());
                Log::error('Amazon Orders API Error', ['response' => $response->body()]);
                break 2; // Break out of both loops
            }

            if (!$response || $response->failed()) {
                $this->error('  Max retries reached or fatal error. Stopping fetch.');
                break;
            }

            $data = $response->json();
            $fetchedOrders = $data['payload']['Orders'] ?? [];
            $orders = array_merge($orders, $fetchedOrders);
            
            $nextToken = $data['payload']['NextToken'] ?? null;
            
            $this->info("  Fetched " . count($fetchedOrders) . " orders, total: " . count($orders));
            
            // Rate limiting - Amazon SP-API has rate limits
            if ($nextToken) {
                sleep($delay);
            }
        } while ($nextToken);

        // Trim to limit if exceeded
        if ($limit && count($orders) > $limit) {
            $orders = array_slice($orders, 0, $limit);
        }

        return $orders;
    }

    private function insertOrders($orders, $period, $accessToken)
    {
        $inserted = 0;
        $updated = 0;
        $itemsInserted = 0;
        $total = count($orders);
        $delay = (int) $this->option('delay') ?: 3;
        $delayMs = $delay * 1000000; // Convert to microseconds

        $this->info("  Inserting {$total} orders with {$delay}s delay between item fetches...");

        foreach ($orders as $index => $order) {
            $orderId = $order['AmazonOrderId'] ?? null;
            if (!$orderId) continue;

            // Check if order already exists
            $existingOrder = AmazonOrder::where('amazon_order_id', $orderId)->first();
            
            // Insert/Update order (without period field)
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

            if ($existingOrder) {
                $updated++;
            } else {
                $inserted++;
            }

            // Fetch order items
            $items = $this->fetchOrderItems($accessToken, $orderId);
            
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
                $itemsInserted++;
            }

            // Progress update every 50 orders
            if (($index + 1) % 50 === 0) {
                $this->info("    Progress: " . ($index + 1) . "/{$total} orders processed...");
            }

            // Rate limiting for order items API
            usleep($delayMs);
        }

        $this->info("  ✅ Processed orders: {$inserted} new, {$updated} updated, {$itemsInserted} items inserted");
    }

    private function fetchOrderItems($accessToken, $orderId)
    {
        return $this->fetchOrderItemsWithRetry($accessToken, $orderId);
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
