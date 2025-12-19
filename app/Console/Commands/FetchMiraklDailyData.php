<?php

namespace App\Console\Commands;

use App\Models\MiraklDailyData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchMiraklDailyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mirakl:daily {--days=60 : Number of days to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store raw order data from Mirakl Connect (Macy\'s, Tiendamia, Best Buy USA)';

    protected $baseUrl = 'https://miraklconnect.com/api/v2/orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $startTime = microtime(true);

        $this->info("Fetching Mirakl Daily Orders Data (Last {$days} days)...");

        // Get access token
        $token = $this->getAccessToken();
        if (!$token) {
            $this->error('Failed to get access token');
            return 1;
        }

        $this->info('Access token received. Fetching all orders...');

        // Calculate date boundaries
        $now = Carbon::now();
        $cutoffDate = $now->copy()->subDays($days);
        $l30Start = $now->copy()->subDays(30);

        // Fetch and store orders
        $this->fetchAndStoreOrders($token, $cutoffDate, $l30Start);

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Mirakl daily data fetched and stored successfully in {$elapsed} seconds.");

        return 0;
    }

    /**
     * Get access token from Mirakl
     */
    protected function getAccessToken(): ?string
    {
        // Try to get cached token
        $token = Cache::get('macy_access_token');

        if (!$token) {
            $response = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.macy.client_id'),
                'client_secret' => config('services.macy.client_secret'),
            ]);

            if ($response->successful()) {
                $token = $response->json()['access_token'];
                Cache::put('macy_access_token', $token, 3000);
                Log::info('New Mirakl access token obtained and cached');
            } else {
                Log::error('Failed to get Mirakl access token: ' . $response->body());
                return null;
            }
        }

        return $token;
    }

    /**
     * Fetch all orders and store raw data
     */
    protected function fetchAndStoreOrders(string $token, Carbon $cutoffDate, Carbon $l30Start): void
    {
        $pageToken = null;
        $totalOrders = 0;
        $totalLines = 0;
        $bulkData = [];
        $startDate = $cutoffDate->toIso8601String();

        do {
            $url = $this->baseUrl . '?fulfillment_type=FULFILLED_BY_SELLER&limit=100&created_from=' . urlencode($startDate);

            if ($pageToken) {
                $url .= '&page_token=' . urlencode($pageToken);
            }

            $response = Http::withoutVerifying()->withToken($token)->get($url);

            // Refresh token if needed
            if (!$response->successful() && str_contains($response->body(), 'Unauthorized')) {
                Cache::forget('macy_access_token');
                $token = $this->getAccessToken();
                if (!$token) {
                    $this->error('Failed to refresh token');
                    break;
                }
                $response = Http::withoutVerifying()->withToken($token)->get($url);
            }

            if (!$response->successful()) {
                $this->error('Order fetch failed: ' . $response->body());
                break;
            }

            $json = $response->json();
            $orders = $json['data'] ?? [];
            $pageToken = $json['next_page_token'] ?? null;

            $totalOrders += count($orders);

            foreach ($orders as $order) {
                $orderData = $this->parseOrderData($order, $l30Start);
                if (!empty($orderData)) {
                    $bulkData = array_merge($bulkData, $orderData);
                    $totalLines += count($orderData);
                }
            }

            // Bulk insert in chunks of 100
            if (count($bulkData) >= 100) {
                $this->bulkUpsertOrders($bulkData);
                $bulkData = [];
            }

            $this->info("  Processed {$totalOrders} orders ({$totalLines} order lines)...");

        } while (!empty($orders) && $pageToken);

        // Insert remaining orders
        if (!empty($bulkData)) {
            $this->bulkUpsertOrders($bulkData);
        }

        $this->info("Fetched {$totalOrders} total orders, stored {$totalLines} order lines.");
    }

    /**
     * Parse order data into flat array for each order line
     */
    protected function parseOrderData(array $order, Carbon $l30Start): array
    {
        $lines = [];
        $channelName = $order['origin']['channel_name'] ?? 'UNKNOWN';
        $channelId = $order['origin']['channel_id'] ?? null;
        $orderId = $order['id'] ?? null;
        $channelOrderId = $order['channel_order_id'] ?? null;
        $orderCreatedAt = isset($order['created_at']) ? Carbon::parse($order['created_at']) : null;
        $orderUpdatedAt = isset($order['updated_at']) ? Carbon::parse($order['updated_at']) : null;

        // Determine period based on order date
        $period = 'l60';
        if ($orderCreatedAt && $orderCreatedAt->gte($l30Start)) {
            $period = 'l30';
        }

        // Billing info
        $billing = $order['billing_info']['address'] ?? [];

        // Shipping info
        $shippingInfo = $order['shipping_info'] ?? [];
        $shippingAddress = $shippingInfo['address'] ?? [];

        foreach ($order['order_lines'] ?? [] as $line) {
            $lineId = $line['id'] ?? null;
            $sku = $line['product']['id'] ?? null;
            $productTitle = $line['product']['title'] ?? null;
            $quantity = $line['quantity'] ?? 1;
            $unitPrice = $line['price']['amount'] ?? null;
            $currency = $line['price']['currency'] ?? 'USD';
            $lineStatus = $line['status'] ?? null;

            // Calculate total tax
            $taxAmount = 0;
            foreach ($line['taxes'] ?? [] as $tax) {
                $taxAmount += $tax['amount']['amount'] ?? 0;
            }

            // Shipping price and tax
            $shippingPrice = $line['total_shipping_price']['amount'] ?? 0;
            $shippingTax = 0;
            foreach ($line['shipping_taxes'] ?? [] as $shippingTaxItem) {
                $shippingTax += $shippingTaxItem['amount']['amount'] ?? 0;
            }

            if (!$orderId || !$lineId) {
                continue;
            }

            $lines[] = [
                'channel_name' => $channelName,
                'channel_id' => $channelId,
                'order_id' => $orderId,
                'channel_order_id' => $channelOrderId,
                'order_line_id' => $lineId,
                'status' => $lineStatus,
                'order_created_at' => $orderCreatedAt?->toDateTimeString(),
                'order_updated_at' => $orderUpdatedAt?->toDateTimeString(),
                'period' => $period,
                'sku' => $sku,
                'product_title' => $productTitle ? substr($productTitle, 0, 500) : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'currency' => $currency,
                'tax_amount' => $taxAmount,
                'shipping_price' => $shippingPrice,
                'shipping_tax' => $shippingTax,
                'billing_first_name' => isset($billing['first_name']) ? substr($billing['first_name'], 0, 100) : null,
                'billing_last_name' => isset($billing['last_name']) ? substr($billing['last_name'], 0, 100) : null,
                'billing_street' => isset($billing['street']) ? substr($billing['street'], 0, 255) : null,
                'billing_city' => isset($billing['city']) ? substr($billing['city'], 0, 100) : null,
                'billing_state' => isset($billing['state']) ? substr($billing['state'], 0, 50) : null,
                'billing_zip' => $billing['zip_code'] ?? null,
                'billing_country' => isset($billing['country']) ? substr($billing['country'], 0, 10) : null,
                'shipping_first_name' => isset($shippingAddress['first_name']) ? substr($shippingAddress['first_name'], 0, 100) : null,
                'shipping_last_name' => isset($shippingAddress['last_name']) ? substr($shippingAddress['last_name'], 0, 100) : null,
                'shipping_street' => isset($shippingAddress['street']) ? substr($shippingAddress['street'], 0, 255) : null,
                'shipping_city' => isset($shippingAddress['city']) ? substr($shippingAddress['city'], 0, 100) : null,
                'shipping_state' => isset($shippingAddress['state']) ? substr($shippingAddress['state'], 0, 50) : null,
                'shipping_zip' => $shippingAddress['zip_code'] ?? null,
                'shipping_country' => isset($shippingAddress['country']) ? substr($shippingAddress['country'], 0, 10) : null,
                'shipping_carrier' => isset($shippingInfo['carrier']) ? substr($shippingInfo['carrier'], 0, 50) : null,
                'shipping_method' => isset($shippingInfo['method']) ? substr($shippingInfo['method'], 0, 100) : null,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        return $lines;
    }

    /**
     * Bulk upsert orders using INSERT ON DUPLICATE KEY UPDATE
     */
    protected function bulkUpsertOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        try {
            MiraklDailyData::upsert(
                $orders,
                ['order_id', 'order_line_id'],
                [
                    'channel_name', 'channel_id', 'channel_order_id', 'status',
                    'order_created_at', 'order_updated_at', 'period', 'sku',
                    'product_title', 'quantity', 'unit_price', 'currency',
                    'tax_amount', 'shipping_price', 'shipping_tax',
                    'billing_first_name', 'billing_last_name', 'billing_street',
                    'billing_city', 'billing_state', 'billing_zip', 'billing_country',
                    'shipping_first_name', 'shipping_last_name', 'shipping_street',
                    'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country',
                    'shipping_carrier', 'shipping_method', 'updated_at'
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to upsert Mirakl orders: ' . $e->getMessage());
            $this->error('Upsert failed: ' . $e->getMessage());
        }
    }
}
