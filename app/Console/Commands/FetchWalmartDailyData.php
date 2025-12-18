<?php

namespace App\Console\Commands;

use App\Models\WalmartDailyData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchWalmartDailyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walmart:daily {--days=60 : Number of days to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store raw order data from Walmart Marketplace';

    protected $baseUrl = 'https://marketplace.walmartapis.com';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $startTime = microtime(true);

        $this->info("Fetching Walmart Daily Orders Data (Last {$days} days)...");

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
        $this->info("Walmart daily data fetched and stored successfully in {$elapsed} seconds.");

        return 0;
    }

    /**
     * Get access token from Walmart
     */
    protected function getAccessToken(): ?string
    {
        $clientId = env('WALMART_CLIENT_ID');
        $clientSecret = env('WALMART_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            $this->error('Walmart credentials missing');
            return null;
        }

        $authorization = base64_encode("{$clientId}:{$clientSecret}");

        $response = Http::withoutVerifying()->asForm()->withHeaders([
            'Authorization' => "Basic {$authorization}",
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/v3/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'] ?? null;
        }

        Log::error('Failed to get Walmart access token: ' . $response->body());
        return null;
    }

    /**
     * Fetch all orders and store raw data
     */
    protected function fetchAndStoreOrders(string $token, Carbon $cutoffDate, Carbon $l30Start): void
    {
        $totalOrders = 0;
        $totalLines = 0;
        $bulkData = [];
        $nextCursor = null;

        $startDate = $cutoffDate->toIso8601String();
        $endDate = Carbon::now()->toIso8601String();

        do {
            if ($nextCursor) {
                $url = $this->baseUrl . "/v3/orders" . $nextCursor;
                $response = Http::withoutVerifying()->withHeaders([
                    'WM_QOS.CORRELATION_ID' => uniqid(),
                    'WM_SEC.ACCESS_TOKEN' => $token,
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
                    'WM_SEC.ACCESS_TOKEN' => $token,
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

        } while (!empty($orders) && $nextCursor);

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

        $purchaseOrderId = $order['purchaseOrderId'] ?? null;
        $customerOrderId = $order['customerOrderId'] ?? null;
        $orderDate = isset($order['orderDate']) ? Carbon::createFromTimestampMs($order['orderDate']) : null;

        // Determine period based on order date
        $period = 'l60';
        if ($orderDate && $orderDate->gte($l30Start)) {
            $period = 'l30';
        }

        // Order level info
        $orderType = $order['orderType'] ?? null;
        $martId = $order['martId'] ?? null;
        $isReplacement = $order['isReplacement'] ?? false;
        $isPremiumOrder = $order['isPremiumOrder'] ?? false;
        $originalCustomerOrderId = $order['originalCustomerOrderID'] ?? null;
        $replacementOrderId = $order['replacementOrderId'] ?? null;
        $sellerOrderId = $order['sellerOrderId'] ?? null;
        $partnerId = $order['partnerId'] ?? null;

        // Shipping info
        $shippingInfo = $order['shippingInfo'] ?? [];
        $postalAddress = $shippingInfo['postalAddress'] ?? [];
        $phone = $postalAddress['phone'] ?? null;
        $email = $postalAddress['email'] ?? null;

        // Ship node
        $shipNode = $order['shipNode'] ?? [];

        // Parse order lines
        $orderLines = $order['orderLines']['orderLine'] ?? [];

        foreach ($orderLines as $line) {
            $lineNumber = $line['lineNumber'] ?? null;
            $item = $line['item'] ?? [];
            $sku = $item['sku'] ?? null;
            $productName = $item['productName'] ?? null;
            $condition = $item['condition'] ?? null;
            $upc = $item['upc'] ?? null;
            $gtin = $item['gtin'] ?? null;
            $itemId = $item['itemId'] ?? null;

            // Quantity
            $quantity = (int) ($line['orderLineQuantity']['amount'] ?? 1);

            // Charges - capture all charge types
            $unitPrice = null;
            $taxAmount = 0;
            $shippingCharge = 0;
            $discountAmount = 0;
            $feeAmount = 0;
            $currency = 'USD';
            $charges = $line['charges']['charge'] ?? [];
            foreach ($charges as $charge) {
                $chargeType = $charge['chargeType'] ?? '';
                $amount = $charge['chargeAmount']['amount'] ?? 0;
                $currency = $charge['chargeAmount']['currency'] ?? 'USD';
                
                if ($chargeType === 'PRODUCT') {
                    $unitPrice = $amount;
                    $taxAmount = $charge['tax']['taxAmount']['amount'] ?? 0;
                } elseif ($chargeType === 'SHIPPING') {
                    $shippingCharge = $amount;
                } elseif ($chargeType === 'DISCOUNT') {
                    $discountAmount = abs($amount); // Discounts are usually negative
                } elseif ($chargeType === 'FEE') {
                    $feeAmount = $amount;
                }
            }

            // Refund info
            $refundAmount = null;
            $refundReason = null;
            $refunds = $line['refund'] ?? [];
            if (!empty($refunds)) {
                $refund = is_array($refunds) && isset($refunds[0]) ? $refunds[0] : $refunds;
                $refundAmount = $refund['refundAmount']['amount'] ?? null;
                $refundReason = $refund['refundReason'] ?? null;
            }

            // Cancellation
            $cancellationReason = $line['cancellationReason'] ?? null;

            // Status - capture all statuses
            $statusDate = isset($line['statusDate']) ? Carbon::createFromTimestampMs($line['statusDate']) : null;
            $status = null;
            $trackingNumber = null;
            $carrierName = null;
            $shipDateTime = null;
            $allStatusesJson = null;

            $orderLineStatuses = $line['orderLineStatuses']['orderLineStatus'] ?? [];
            if (!empty($orderLineStatuses)) {
                // Store all statuses as JSON
                $allStatusesJson = json_encode($orderLineStatuses);
                
                $firstStatus = $orderLineStatuses[0];
                $status = $firstStatus['status'] ?? null;
                $trackingInfo = $firstStatus['trackingInfo'] ?? [];
                $trackingNumber = $trackingInfo['trackingNumber'] ?? null;
                $carrierName = $trackingInfo['carrierName']['carrier'] ?? $trackingInfo['carrierName']['otherCarrier'] ?? null;
                $shipDateTime = isset($trackingInfo['shipDateTime']) ? Carbon::createFromTimestampMs($trackingInfo['shipDateTime']) : null;
            }

            // Fulfillment
            $fulfillment = $line['fulfillment'] ?? [];
            $fulfillmentOption = $fulfillment['fulfillmentOption'] ?? null;

            // Estimated dates
            $estimatedDeliveryDate = isset($shippingInfo['estimatedDeliveryDate']) ? Carbon::createFromTimestampMs($shippingInfo['estimatedDeliveryDate']) : null;
            $estimatedShipDate = isset($shippingInfo['estimatedShipDate']) ? Carbon::createFromTimestampMs($shippingInfo['estimatedShipDate']) : null;

            if (!$purchaseOrderId || !$lineNumber) {
                continue;
            }

            $lines[] = [
                'purchase_order_id' => $purchaseOrderId,
                'customer_order_id' => $customerOrderId,
                'order_date' => $orderDate?->toDateTimeString(),
                'order_type' => $orderType,
                'mart_id' => $martId,
                'is_replacement' => $isReplacement ? 1 : 0,
                'is_premium_order' => $isPremiumOrder ? 1 : 0,
                'original_customer_order_id' => $originalCustomerOrderId,
                'replacement_order_id' => $replacementOrderId,
                'seller_order_id' => $sellerOrderId,
                'order_line_number' => $lineNumber,
                'period' => $period,
                'sku' => $sku,
                'upc' => $upc,
                'gtin' => $gtin,
                'item_id' => $itemId,
                'product_name' => $productName ? substr($productName, 0, 500) : null,
                'quantity' => $quantity,
                'condition' => $condition,
                'unit_price' => $unitPrice,
                'currency' => $currency,
                'tax_amount' => $taxAmount,
                'shipping_charge' => $shippingCharge,
                'discount_amount' => $discountAmount,
                'fee_amount' => $feeAmount,
                'status' => $status,
                'all_statuses_json' => $allStatusesJson,
                'order_line_json' => json_encode($line),
                'status_date' => $statusDate?->toDateTimeString(),
                'cancellation_reason' => $cancellationReason ? substr($cancellationReason, 0, 255) : null,
                'refund_amount' => $refundAmount,
                'refund_reason' => $refundReason ? substr($refundReason, 0, 255) : null,
                'customer_name' => isset($postalAddress['name']) ? substr($postalAddress['name'], 0, 200) : null,
                'customer_phone' => $phone ? substr($phone, 0, 50) : null,
                'customer_email' => $email ? substr($email, 0, 255) : null,
                'shipping_address1' => isset($postalAddress['address1']) ? substr($postalAddress['address1'], 0, 255) : null,
                'shipping_address2' => isset($postalAddress['address2']) ? substr($postalAddress['address2'], 0, 255) : null,
                'shipping_city' => isset($postalAddress['city']) ? substr($postalAddress['city'], 0, 100) : null,
                'shipping_state' => isset($postalAddress['state']) ? substr($postalAddress['state'], 0, 50) : null,
                'shipping_postal_code' => $postalAddress['postalCode'] ?? null,
                'shipping_country' => isset($postalAddress['country']) ? substr($postalAddress['country'], 0, 10) : null,
                'shipping_method' => $shippingInfo['methodCode'] ?? null,
                'ship_method_code' => $shippingInfo['shipMethodCode'] ?? null,
                'carrier_name' => $carrierName,
                'tracking_number' => $trackingNumber,
                'estimated_delivery_date' => $estimatedDeliveryDate?->toDateTimeString(),
                'estimated_ship_date' => $estimatedShipDate?->toDateTimeString(),
                'ship_date_time' => $shipDateTime?->toDateTimeString(),
                'fulfillment_option' => $fulfillmentOption,
                'ship_node_type' => $shipNode['type'] ?? null,
                'ship_node_name' => isset($shipNode['name']) ? substr($shipNode['name'], 0, 100) : null,
                'pickup_location' => isset($fulfillment['pickUpLocationId']) ? substr($fulfillment['pickUpLocationId'], 0, 255) : null,
                'partner_id' => $partnerId,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        return $lines;
    }

    /**
     * Bulk upsert orders
     */
    protected function bulkUpsertOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        try {
            WalmartDailyData::upsert(
                $orders,
                ['purchase_order_id', 'order_line_number'],
                [
                    'customer_order_id', 'order_date', 'order_type', 'mart_id',
                    'is_replacement', 'is_premium_order', 'original_customer_order_id',
                    'replacement_order_id', 'seller_order_id', 'period', 'sku', 'upc',
                    'gtin', 'item_id', 'product_name', 'quantity', 'condition',
                    'unit_price', 'currency', 'tax_amount', 'shipping_charge',
                    'discount_amount', 'fee_amount', 'status', 'all_statuses_json',
                    'order_line_json', 'status_date', 'cancellation_reason',
                    'refund_amount', 'refund_reason', 'customer_name', 'customer_phone',
                    'customer_email', 'shipping_address1', 'shipping_address2',
                    'shipping_city', 'shipping_state', 'shipping_postal_code',
                    'shipping_country', 'shipping_method', 'ship_method_code',
                    'carrier_name', 'tracking_number', 'estimated_delivery_date',
                    'estimated_ship_date', 'ship_date_time', 'fulfillment_option',
                    'ship_node_type', 'ship_node_name', 'pickup_location',
                    'partner_id', 'updated_at'
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to upsert Walmart orders: ' . $e->getMessage());
            $this->error('Upsert failed: ' . $e->getMessage());
        }
    }
}
