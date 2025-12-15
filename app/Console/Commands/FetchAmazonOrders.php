<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchAmazonOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-amazon-orders {--fetch-missing-items : Only fetch items for orders that have no items}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Amazon orders and insert into database for L30/L60 periods';

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

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get access token');
            return;
        }

        $this->info('Access Token obtained successfully');

        $dateRanges = $this->dateRanges();

        foreach ($dateRanges as $period => $range) {
            $this->info("Fetching orders for {$period}...");
            $orders = $this->fetchOrders($accessToken, $range['start'], $range['end']);
            $this->info("Fetched " . count($orders) . " orders for {$period}");
            
            $this->insertOrders($orders, $period, $accessToken);
        }

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
                    AmazonOrderItem::updateOrCreate(
                        [
                            'amazon_order_id' => $order->id, 
                            'asin' => $item['ASIN'] ?? null,
                            'sku' => $item['SellerSKU'] ?? null,
                        ],
                        [
                            'quantity' => $item['QuantityOrdered'] ?? 1,
                            'price' => $item['ItemPrice']['Amount'] ?? 0,
                            'currency' => $item['ItemPrice']['CurrencyCode'] ?? 'USD',
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

    private function dateRanges()
    {
        $today = Carbon::today();

        return [
            'l30' => [
                'start' => $today->copy()->subDays(30),  // 30 days ago
                'end' => $today->copy()->subDay(),       // yesterday
            ],
            'l60' => [
                'start' => $today->copy()->subDays(60),  // 60 days ago
                'end' => $today->copy()->subDays(31),    // 31 days ago
            ],
        ];
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

    private function fetchOrders($accessToken, $startDate, $endDate)
    {
        $marketplaceId = env('SPAPI_MARKETPLACE_ID');
        $orders = [];
        $nextToken = null;

        $createdAfter = $startDate->toIso8601ZuluString();
        $createdBefore = $endDate->endOfDay()->toIso8601ZuluString();

        do {
            $params = [
                'MarketplaceIds' => $marketplaceId,
                'CreatedAfter' => $createdAfter,
                'CreatedBefore' => $createdBefore,
                'MaxResultsPerPage' => 100,
            ];

            if ($nextToken) {
                $params['NextToken'] = $nextToken;
            }

            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
            ])->get('https://sellingpartnerapi-na.amazon.com/orders/v0/orders', $params);

            if ($response->failed()) {
                $this->error('Failed to fetch orders: ' . $response->body());
                Log::error('Amazon Orders API Error', ['response' => $response->body()]);
                break;
            }

            $data = $response->json();
            $fetchedOrders = $data['payload']['Orders'] ?? [];
            $orders = array_merge($orders, $fetchedOrders);
            
            $nextToken = $data['payload']['NextToken'] ?? null;
            
            $this->info("  Fetched " . count($fetchedOrders) . " orders, total: " . count($orders));
            
            // Rate limiting - Amazon SP-API has rate limits
            if ($nextToken) {
                sleep(2); // Increased from 1 to 2 seconds
            }
        } while ($nextToken);

        return $orders;
    }

    private function insertOrders($orders, $period, $accessToken)
    {
        $inserted = 0;
        $itemsInserted = 0;

        foreach ($orders as $order) {
            $orderId = $order['AmazonOrderId'] ?? null;
            if (!$orderId) continue;

            // Insert/Update order
            $orderRecord = AmazonOrder::updateOrCreate(
                ['amazon_order_id' => $orderId],
                [
                    'order_date' => Carbon::parse($order['PurchaseDate']),
                    'status' => $order['OrderStatus'] ?? null,
                    'total_amount' => $order['OrderTotal']['Amount'] ?? 0,
                    'currency' => $order['OrderTotal']['CurrencyCode'] ?? 'USD',
                    'period' => $period,
                    'raw_data' => json_encode($order),
                ]
            );

            $inserted++;

            // Fetch order items
            $items = $this->fetchOrderItems($accessToken, $orderId);
            
            foreach ($items as $item) {
                AmazonOrderItem::updateOrCreate(
                    [
                        'amazon_order_id' => $orderRecord->id, 
                        'asin' => $item['ASIN'] ?? null,
                        'sku' => $item['SellerSKU'] ?? null,
                    ],
                    [
                        'quantity' => $item['QuantityOrdered'] ?? 1,
                        'price' => $item['ItemPrice']['Amount'] ?? 0,
                        'currency' => $item['ItemPrice']['CurrencyCode'] ?? 'USD',
                        'title' => $item['Title'] ?? null,
                        'raw_data' => json_encode($item),
                    ]
                );
                $itemsInserted++;
            }

            // Rate limiting for order items API - increased delay
            usleep(500000); // 500ms delay (increased from 200ms)
        }

        $this->info("  Inserted {$inserted} orders and {$itemsInserted} order items for {$period}");
    }

    private function fetchOrderItems($accessToken, $orderId)
    {
        return $this->fetchOrderItemsWithRetry($accessToken, $orderId);
    }

    private function fetchOrderItemsWithRetry($accessToken, $orderId, $maxRetries = 3)
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
            
            // Rate limited - wait and retry
            if ($statusCode === 429) {
                $attempt++;
                $waitTime = pow(2, $attempt); // Exponential backoff: 2, 4, 8 seconds
                Log::warning("Rate limited for order {$orderId}, waiting {$waitTime}s (attempt {$attempt}/{$maxRetries})");
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
