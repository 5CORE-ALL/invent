<?php

namespace App\Console\Commands;

use App\Models\DobaDailyData;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchDobaDailyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doba:daily {--days=60 : Number of days to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store raw order data from Doba';

    protected $baseUrl = 'https://openapi.doba.com/api';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $startTime = microtime(true);

        $this->info("Fetching Doba Daily Orders Data (Last {$days} days)...");

        // Validate credentials
        $appKey = config('services.doba.app_key');
        $privateKey = config('services.doba.private_key');

        if (!$appKey || !$privateKey) {
            $this->error('Doba credentials missing in .env (DOBA_APP_KEY, DOBA_PRIVATE_KEY)');
            return 1;
        }

        // Calculate date boundaries
        $now = Carbon::now();
        $cutoffDate = $now->copy()->subDays($days);
        $l30Start = $now->copy()->subDays(30);

        // Fetch and store orders
        $this->fetchAndStoreOrders($cutoffDate, $l30Start, $now);

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Doba daily data fetched and stored successfully in {$elapsed} seconds.");

        return 0;
    }

    /**
     * Get current timestamp in milliseconds
     */
    protected function getMillisecond(): int
    {
        list($s1, $s2) = explode(' ', microtime());
        return intval((float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000));
    }

    /**
     * Generate content string for signing
     */
    protected function getContent(int $timestamp): string
    {
        $appKey = config('services.doba.app_key');
        return "appKey={$appKey}&signType=rsa2&timestamp={$timestamp}";
    }

    /**
     * Generate RSA signature
     */
    protected function generateSignature(string $content): string
    {
        $privateKeyFormatted = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap(config('services.doba.private_key'), 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        $private_key = openssl_pkey_get_private($privateKeyFormatted);
        if (!$private_key) {
            throw new Exception("Invalid private key.");
        }
        openssl_sign($content, $signature, $private_key, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * Fetch all orders and store raw data
     */
    protected function fetchAndStoreOrders(Carbon $cutoffDate, Carbon $l30Start, Carbon $now): void
    {
        $totalOrders = 0;
        $totalItems = 0;
        $bulkData = [];

        // Date range for API
        $beginTime = $cutoffDate->format('Y-m-d\TH:i:sP');
        $endTime = $now->format('Y-m-d\TH:i:sP');

        $this->info("  Date range: {$beginTime} to {$endTime}");

        $pageNo = 1;
        $pageSize = 100;

        do {
            $this->info("  Fetching page {$pageNo}...");

            // Generate fresh signature for each request
            $timestamp = $this->getMillisecond();
            $getContent = $this->getContent($timestamp);
            $sign = $this->generateSignature($getContent);

            $response = Http::withHeaders([
                'appKey' => config('services.doba.app_key'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/seller/queryOrderDetail', [
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
                'beginTime' => $beginTime,
                'endTime' => $endTime,
            ]);

            if (!$response->ok()) {
                $this->error('HTTP Error: ' . $response->status() . ' - ' . $response->body());
                Log::error('Doba HTTP Error', ['response' => $response->body()]);
                break;
            }

            $json = $response->json();

            // Debug: Log raw response
            $this->info("  API Response Code: " . ($json['responseCode'] ?? 'N/A'));

            if (($json['responseCode'] ?? '') !== '000000') {
                $this->error('API Error: ' . ($json['responseMessage'] ?? 'Unknown error'));
                Log::error('Doba API Error', ['response' => $json]);
                break;
            }

            $orders = $json['businessData'][0]['data'] ?? [];

            if (empty($orders)) {
                $this->info("  No more orders found on page {$pageNo}.");
                break;
            }

            $totalOrders += count($orders);

            foreach ($orders as $order) {
                $orderData = $this->parseOrderData($order, $l30Start);
                if (!empty($orderData)) {
                    $bulkData = array_merge($bulkData, $orderData);
                    $totalItems += count($orderData);
                }
            }

            // Bulk insert in chunks of 100
            if (count($bulkData) >= 100) {
                $this->bulkUpsertOrders($bulkData);
                $bulkData = [];
            }

            $this->info("  Processed {$totalOrders} orders ({$totalItems} items)...");

            $pageNo++;

        } while (count($orders) >= $pageSize);

        // Insert remaining orders
        if (!empty($bulkData)) {
            $this->bulkUpsertOrders($bulkData);
        }

        $this->info("Fetched {$totalOrders} total orders, stored {$totalItems} items.");
    }

    /**
     * Parse order data into flat array for each order item
     */
    protected function parseOrderData(array $order, Carbon $l30Start): array
    {
        $items = [];

        // Order identifiers - Doba uses ordBusiId
        $orderNo = $order['ordBusiId'] ?? $order['orderNo'] ?? null;
        $platformOrderNo = $order['platformOrderNo'] ?? null;
        $orderTime = isset($order['ordTime']) ? Carbon::parse($order['ordTime']) : 
                     (isset($order['orderTime']) ? Carbon::parse($order['orderTime']) : null);
        $payTime = isset($order['reviewPassTime']) ? Carbon::parse($order['reviewPassTime']) : 
                   (isset($order['payTime']) ? Carbon::parse($order['payTime']) : null);

        // Determine period based on order time
        $period = 'l60';
        if ($orderTime && $orderTime->gte($l30Start)) {
            $period = 'l30';
        }

        // Order level info
        $orderStatus = $order['ordStatus'] ?? $order['orderStatus'] ?? null;
        $orderType = $order['deliveryMethod'] ?? $order['orderType'] ?? null;
        $currency = $order['currency'] ?? 'USD';

        // Shipping address from shippingAddress object
        $shippingAddr = $order['shippingAddress'] ?? [];
        $receiverName = $shippingAddr['name'] ?? $order['buyerName'] ?? null;
        $receiverPhone = $shippingAddr['telephone'] ?? null;
        $receiverEmail = $order['receiverEmail'] ?? null;
        $shippingAddress1 = $shippingAddr['address1'] ?? null;
        $shippingAddress2 = $shippingAddr['address2'] ?? null;
        $shippingCity = $shippingAddr['cityName'] ?? null;
        $shippingState = $shippingAddr['provinceCode'] ?? $shippingAddr['provinceName'] ?? null;
        $shippingPostalCode = $shippingAddr['zip'] ?? null;
        $shippingCountry = $shippingAddr['countryCode'] ?? $shippingAddr['countryName'] ?? null;

        // Fulfillment
        $shippingMethod = $order['shippingMethod'] ?? null;
        $carrierName = $order['logisticsType'] ?? null;
        $trackingNumber = $order['trackingNumber'] ?? null;
        $shipTime = isset($order['shipTime']) ? Carbon::parse($order['shipTime']) : null;
        $deliveryTime = isset($order['deliveryTime']) ? Carbon::parse($order['deliveryTime']) : null;

        // Warehouse - from pickupWarehouse object
        $pickupWarehouse = $order['pickupWarehouse'] ?? [];
        $warehouseCode = $pickupWarehouse['pickupWarehouseCode'] ?? $order['warehouseCode'] ?? null;
        $warehouseName = $pickupWarehouse['pickupWarehouseName'] ?? $order['warehouseName'] ?? null;

        // Store/Shop - use orderSourcePlatform
        $storeName = $order['storeName'] ?? $order['shopName'] ?? null;
        $platformName = $order['orderSourcePlatform'] ?? $order['platformName'] ?? null;
        $inventoryLocation = $order['inventoryLocation'] ?? null;

        // Seller
        $sellerId = $order['bBusiId'] ?? $order['sellerId'] ?? null;
        $sellerName = $order['buyerName'] ?? $order['sellerName'] ?? null;

        // Order totals - Doba uses orderTotal, itemsSubtotal, etc.
        $totalPrice = $order['orderTotal'] ?? $order['totalPrice'] ?? null;
        $itemsSubtotal = $order['itemsSubtotal'] ?? null;
        $shippingFee = $order['shippingSubtotal'] ?? $order['shippingFee'] ?? null;
        $taxSubtotal = $order['taxSubtotal'] ?? null;
        $discountAmount = $order['discountAmount'] ?? null;
        $platformFee = $order['platformFee'] ?? null;

        // Parse order items - Doba uses orderItemList
        $orderItems = $order['orderItemList'] ?? $order['orderItems'] ?? $order['items'] ?? [];
        
        // If no items array, treat order itself as single item
        if (empty($orderItems)) {
            $orderItems = [$order];
        }

        foreach ($orderItems as $item) {
            // Doba specific fields
            $itemNo = $item['itemNo'] ?? $item['goodsNo'] ?? $orderNo;
            $sku = $item['goodsSkuCode'] ?? $item['sku'] ?? $item['skuCode'] ?? null;
            $productName = $item['goodsName'] ?? $item['productName'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 1);
            $itemPrice = $item['unitPrice'] ?? $item['price'] ?? null;
            $anticipatedIncome = $item['anticipatedIncome'] ?? null;

            if (!$orderNo) {
                continue;
            }

            // Use orderNo as itemNo if not available
            $uniqueItemNo = $itemNo ?? $orderNo . '_' . ($sku ?? uniqid());

            $items[] = [
                'order_no' => $orderNo,
                'platform_order_no' => $platformOrderNo,
                'order_time' => $orderTime?->toDateTimeString(),
                'pay_time' => $payTime?->toDateTimeString(),
                'order_status' => $orderStatus,
                'order_type' => $orderType,
                'period' => $period,
                'item_no' => substr($uniqueItemNo, 0, 100),
                'sku' => $sku ? substr($sku, 0, 100) : null,
                'product_name' => $productName ? substr($productName, 0, 500) : null,
                'quantity' => $quantity,
                'item_price' => $itemPrice,
                'total_price' => $totalPrice,
                'shipping_fee' => $shippingFee,
                'discount_amount' => $discountAmount,
                'platform_fee' => $platformFee,
                'anticipated_income' => $anticipatedIncome,
                'currency' => $currency,
                'receiver_name' => $receiverName ? substr($receiverName, 0, 200) : null,
                'receiver_phone' => $receiverPhone ? substr($receiverPhone, 0, 50) : null,
                'receiver_email' => $receiverEmail ? substr($receiverEmail, 0, 255) : null,
                'shipping_address1' => $shippingAddress1 ? substr($shippingAddress1, 0, 255) : null,
                'shipping_address2' => $shippingAddress2 ? substr($shippingAddress2, 0, 255) : null,
                'shipping_city' => $shippingCity ? substr($shippingCity, 0, 100) : null,
                'shipping_state' => $shippingState ? substr($shippingState, 0, 50) : null,
                'shipping_postal_code' => $shippingPostalCode ? substr($shippingPostalCode, 0, 20) : null,
                'shipping_country' => $shippingCountry ? substr($shippingCountry, 0, 50) : null,
                'shipping_method' => $shippingMethod ? substr($shippingMethod, 0, 100) : null,
                'carrier_name' => $carrierName ? substr($carrierName, 0, 50) : null,
                'tracking_number' => $trackingNumber ? substr($trackingNumber, 0, 100) : null,
                'ship_time' => $shipTime?->toDateTimeString(),
                'delivery_time' => $deliveryTime?->toDateTimeString(),
                'warehouse_code' => $warehouseCode ? substr($warehouseCode, 0, 50) : null,
                'warehouse_name' => $warehouseName ? substr($warehouseName, 0, 100) : null,
                'store_name' => $storeName ? substr($storeName, 0, 100) : null,
                'platform_name' => $platformName ? substr($platformName, 0, 50) : null,
                'seller_id' => $sellerId ? substr($sellerId, 0, 50) : null,
                'seller_name' => $sellerName ? substr($sellerName, 0, 100) : null,
                'order_item_json' => json_encode($item),
                'order_json' => json_encode($order),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        return $items;
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
            DobaDailyData::upsert(
                $orders,
                ['order_no', 'item_no'],
                [
                    'platform_order_no', 'order_time', 'pay_time', 'order_status',
                    'order_type', 'period', 'sku', 'product_name', 'quantity',
                    'item_price', 'total_price', 'shipping_fee', 'discount_amount',
                    'platform_fee', 'anticipated_income', 'currency', 'receiver_name',
                    'receiver_phone', 'receiver_email', 'shipping_address1',
                    'shipping_address2', 'shipping_city', 'shipping_state',
                    'shipping_postal_code', 'shipping_country', 'shipping_method',
                    'carrier_name', 'tracking_number', 'ship_time', 'delivery_time',
                    'warehouse_code', 'warehouse_name', 'store_name', 'platform_name',
                    'seller_id', 'seller_name', 'order_item_json', 'order_json',
                    'updated_at'
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to upsert Doba orders: ' . $e->getMessage());
            $this->error('Upsert failed: ' . $e->getMessage());
        }
    }
}
