<?php

namespace App\Console\Commands;

use App\Services\ShopifyPlsTokenService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchPlsSalesData extends Command
{
    protected $signature = 'app:fetch-pls-sales-data {--days=90 : Number of days of sales data to fetch}';

    protected $description = 'Fetch detailed sales data from PLS Shopify store and store in pls_sales table';

    public function handle()
    {
        $shopUrl = config('services.prolightsounds.domain');
        $token = app(ShopifyPlsTokenService::class)->getAccessToken();
        $version = "2025-01";
        $days     = (int) $this->option('days');

        // Validate credentials
        if (empty($shopUrl) || empty($token)) {
            $this->error("ProLightSounds Shopify credentials not configured");
            Log::error("ProLightSounds Shopify credentials missing");
            return 1;
        }

        $this->info("Fetching PLS sales data for the last {$days} days...");

        $now = Carbon::now('America/New_York');
        $createdAtMin = $now->copy()->subDays($days)->toIso8601String();

        $requestBase = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ]);

        if (config('filesystems.default') === 'local' || env('FILESYSTEM_DRIVER') === 'local') {
            $requestBase = $requestBase->withoutVerifying();
        }

        $domain = preg_replace('#^https?://#', '', $shopUrl);
        $domain = rtrim($domain, '/');
        
        $url = "https://{$domain}/admin/api/{$version}/orders.json";
        $pageInfo = null;
        $totalOrders = 0;
        $totalLineItems = 0;

        do {
            $queryParams = [
                'status' => 'any',
                'limit' => 250,
                'created_at_min' => $createdAtMin,
            ];

            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $response = $requestBase->timeout(120)->retry(2, 500)->get($url, $queryParams);

            if (!$response->successful()) {
                $this->error("Shopify API Error: " . $response->body());
                Log::error("ProLightSounds Shopify API Error", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                break;
            }

            $orders = $response->json()['orders'] ?? [];
            $totalOrders += count($orders);

            foreach ($orders as $order) {
                $orderId = $order['id'] ?? null;
                $orderDate = isset($order['created_at']) ? Carbon::parse($order['created_at']) : null;
                $processedAt = isset($order['processed_at']) ? Carbon::parse($order['processed_at']) : null;
                $cancelledAt = isset($order['cancelled_at']) ? Carbon::parse($order['cancelled_at']) : null;

                foreach ($order['line_items'] as $lineItem) {
                    $lineItemId = $lineItem['id'] ?? null;
                    $sku = $lineItem['sku'] ?? null;

                    if (!$orderId || !$lineItemId) {
                        continue;
                    }

                    $quantity = $lineItem['quantity'] ?? 0;
                    $price = isset($lineItem['price']) ? (float) $lineItem['price'] : 0;
                    $totalAmount = $quantity * $price;
                    $discountAmount = 0;
                    
                    // Calculate discount if available
                    if (isset($lineItem['total_discount'])) {
                        $discountAmount = (float) $lineItem['total_discount'];
                    }

                    // Get tax amount
                    $taxAmount = 0;
                    if (isset($lineItem['tax_lines']) && is_array($lineItem['tax_lines'])) {
                        foreach ($lineItem['tax_lines'] as $taxLine) {
                            $taxAmount += (float) ($taxLine['price'] ?? 0);
                        }
                    }

                    // Get fulfillment status
                    $fulfillmentStatus = $lineItem['fulfillment_status'] ?? 'unfulfilled';
                    $fulfilledAt = null;
                    if ($fulfillmentStatus === 'fulfilled' && isset($order['fulfillments'][0]['created_at'])) {
                        $fulfilledAt = Carbon::parse($order['fulfillments'][0]['created_at']);
                    }

                    DB::table('pls_sales')->updateOrInsert(
                        [
                            'shopify_order_id' => $orderId,
                            'shopify_line_item_id' => $lineItemId,
                        ],
                        [
                            'order_number' => $order['order_number'] ?? null,
                            'order_name' => $order['name'] ?? null,
                            'sku' => $sku,
                            'product_title' => $lineItem['title'] ?? null,
                            'variant_title' => $lineItem['variant_title'] ?? null,
                            'quantity' => $quantity,
                            'price' => $price,
                            'total_amount' => $totalAmount,
                            'discount_amount' => $discountAmount,
                            'tax_amount' => $taxAmount,
                            'financial_status' => $order['financial_status'] ?? null,
                            'fulfillment_status' => $fulfillmentStatus,
                            'order_date' => $orderDate,
                            'processed_at' => $processedAt,
                            'fulfilled_at' => $fulfilledAt,
                            'cancelled_at' => $cancelledAt,
                            'customer_email' => $order['customer']['email'] ?? null,
                            'customer_name' => isset($order['customer']) 
                                ? trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''))
                                : null,
                            'currency' => $order['currency'] ?? 'USD',
                            'tags' => $order['tags'] ?? null,
                            'note' => $order['note'] ?? null,
                            'updated_at' => now(),
                            'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        ]
                    );

                    $totalLineItems++;
                }
            }

            // Pagination
            $pageInfo = $this->getNextPageInfo($response);
            
            if ($pageInfo) {
                $this->info("Processed {$totalOrders} orders, {$totalLineItems} line items so far...");
                usleep(500000); // Rate limiting
            }

        } while ($pageInfo);

        $this->info("✅ Successfully fetched PLS sales data!");
        $this->info("📦 Total orders: {$totalOrders}");
        $this->info("📝 Total line items: {$totalLineItems}");
        
        Log::info('PLS sales data fetched successfully', [
            'orders' => $totalOrders,
            'line_items' => $totalLineItems,
        ]);

        return 0;
    }

    private function getNextPageInfo($response): ?string
    {
        if ($response->hasHeader('Link') && str_contains($response->header('Link'), 'rel="next"')) {
            $links = explode(',', $response->header('Link'));
            foreach ($links as $link) {
                if (str_contains($link, 'rel="next"')) {
                    preg_match('/<(.*)>; rel="next"/', $link, $matches);
                    if (!empty($matches[1])) {
                        parse_str((string) parse_url($matches[1], PHP_URL_QUERY), $query);
                        return $query['page_info'] ?? null;
                    }
                }
            }
        }
        return null;
    }
}
