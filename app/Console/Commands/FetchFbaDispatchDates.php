<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FbaTable;
use App\Models\FbaOrder;

class FetchFbaDispatchDates extends Command
{
    protected $signature = 'app:fetch-fba-dispatch-dates
        {--year= : Year to fetch (default current year)}
        {--days= : Number of days back to fetch (overrides year)}';

    protected $description = 'Fetch FBA orders from Amazon Orders API and store order details with dispatch dates';

    public function handle()
    {
        $this->info('📦 Starting FBA orders fetch...');

        $year = intval($this->option('year') ?: date('Y'));
        $days = $this->option('days');

        if ($days) {
            $createdAfter = date('Y-m-d\TH:i:s\Z', strtotime("-{$days} days"));
        } else {
            $createdAfter = "{$year}-01-01T00:00:00-07:00";
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('❌ Could not obtain access token.');
            return 1;
        }

        $endpoint = env('SPAPI_ENDPOINT', 'https://sellingpartnerapi-na.amazon.com');
        $marketplace = env('SPAPI_MARKETPLACE_ID', 'ATVPDKIKX0DER');

        // Get all FBA SKUs
        $fbaSkus = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->pluck('seller_sku')
            ->map(function ($sku) {
                $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                return strtoupper(trim($base));
            })
            ->unique()
            ->toArray();

        if (empty($fbaSkus)) {
            $this->warn('⚠️ No FBA SKUs found.');
            return 0;
        }

        $this->info("ℹ️ FBA SKUs to check: " . count($fbaSkus));

        $url = "{$endpoint}/orders/v0/orders?MarketplaceIds={$marketplace}&CreatedAfter={$createdAfter}&FulfillmentChannel=AFN";

        $nextToken = null;
        $totalOrders = 0;
        $updatedSkus = 0;

        do {
            $currentUrl = $nextToken ? "{$url}&NextToken=" . urlencode($nextToken) : $url;

            $res = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get($currentUrl);

            if ($res->failed()) {
                $this->warn("⚠️ API failed: " . $res->status());
                Log::error('Orders API failed', $res->json());
                break;
            }

            $payload = $res->json()['payload'] ?? null;
            if (!$payload || !isset($payload['Orders'])) {
                $this->warn("ℹ️ No orders found.");
                break;
            }

            $orders = $payload['Orders'];
            $totalOrders += count($orders);

            foreach ($orders as $order) {
                $orderId = $order['AmazonOrderId'];
                $latestShipDate = $order['LatestShipDate'] ?? null;
                $purchaseDate = $order['PurchaseDate'] ?? null;
                $orderStatus = $order['OrderStatus'] ?? null;

                if (!$purchaseDate || $orderStatus === 'Cancelled') continue;

                // Get order items
                $itemsUrl = "{$endpoint}/orders/v0/orders/{$orderId}/orderItems";
                $itemsRes = Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->get($itemsUrl);

                if ($itemsRes->failed()) {
                    Log::error("Failed to get items for order {$orderId}");
                    continue;
                }

                $itemsPayload = $itemsRes->json()['payload'] ?? null;
                if (!$itemsPayload || !isset($itemsPayload['OrderItems'])) continue;

                foreach ($itemsPayload['OrderItems'] as $item) {
                    $sellerSku = $item['SellerSKU'] ?? '';
                    $quantity = $item['QuantityOrdered'] ?? 1;
                    $baseSku = strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', $sellerSku)));

                    if (in_array($baseSku, $fbaSkus)) {
                        // Insert order
                        FbaOrder::create([
                            'amazon_order_id' => $orderId,
                            'sku' => $baseSku,
                            'seller_sku' => $sellerSku,
                            'order_date' => date('Y-m-d', strtotime($purchaseDate)),
                            'dispatch_date' => $latestShipDate ? date('Y-m-d', strtotime($latestShipDate)) : null,
                            'quantity' => $quantity,
                            'status' => $orderStatus,
                        ]);
                        $updatedSkus++;
                    }
                }

                sleep(1); // Rate limit
            }

            $nextToken = $payload['NextToken'] ?? null;

        } while ($nextToken);

        $this->info("✅ Processed {$totalOrders} orders, inserted {$updatedSkus} order items.");
        return 0;
    }

    private function getAccessToken()
    {
        try {
            $res = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => env('SPAPI_REFRESH_TOKEN'),
                'client_id' => env('SPAPI_CLIENT_ID'),
                'client_secret' => env('SPAPI_CLIENT_SECRET'),
            ]);

            if ($res->failed()) {
                Log::error('Access token request failed', $res->json());
                return null;
            }

            return $res->json()['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Access token error: ' . $e->getMessage());
            return null;
        }
    }
}
