<?php

namespace App\Console\Commands;

use App\Models\TemuOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchTemuOrders extends Command
{
    /**
     * Examples:
     *   php artisan app:fetch-temu-orders                 (last 30 days)
     *   php artisan app:fetch-temu-orders --days=60
     *   php artisan app:fetch-temu-orders --from=2026-05-01 --to=2026-05-31
     *   php artisan app:fetch-temu-orders --status=2      (only a given parentOrderStatus)
     */
    protected $signature = 'app:fetch-temu-orders
        {--days=30 : Number of days back to fetch (ignored if --from/--to given)}
        {--from= : Start date (Y-m-d), inclusive}
        {--to= : End date (Y-m-d), inclusive}
        {--status= : Optional parentOrderStatus filter}';

    protected $description = 'Fetch order-wise raw data from Temu (bg.order.list.v2.get) and store it in the temu_orders table';

    private const ORDER_STATUS_MAP = [
        1 => 'PENDING',
        2 => 'UN_SHIPPING',
        3 => 'CANCELED',
        4 => 'SHIPPED',
        41 => 'PARTIALLY_SHIPPED',
        5 => 'DELIVERED',
        51 => 'PARTIALLY_DELIVERED',
    ];

    public function handle(): int
    {
        Log::info('Starting FetchTemuOrders command');
        $this->info('Starting FetchTemuOrders command');

        $appKey = config('services.temu.app_key');
        $appSecret = config('services.temu.secret_key');
        $accessToken = config('services.temu.access_token');

        if (empty($appKey) || empty($appSecret) || empty($accessToken)) {
            $this->error('Missing Temu API credentials in .env (TEMU_APP_KEY, TEMU_SECRET_KEY, TEMU_ACCESS_TOKEN)');

            return self::FAILURE;
        }

        // Resolve date window
        try {
            if ($this->option('from') || $this->option('to')) {
                $from = Carbon::parse($this->option('from') ?: $this->option('to'))->startOfDay();
                $to = Carbon::parse($this->option('to') ?: $this->option('from'))->endOfDay();
            } else {
                $days = max(1, (int) $this->option('days'));
                $to = Carbon::today()->subDay()->endOfDay();
                $from = $to->copy()->subDays($days - 1)->startOfDay();
            }
        } catch (\Throwable $e) {
            $this->error('Invalid date option: '.$e->getMessage());

            return self::FAILURE;
        }

        $status = $this->option('status');
        $window = ($this->option('from') || $this->option('to'))
            ? 'manual'
            : 'L'.max(1, (int) $this->option('days'));

        $this->info('Window: '.$from->toDateTimeString().' → '.$to->toDateTimeString());

        $pageNumber = 1;
        $hasMorePages = true;
        $totalParents = 0;
        $totalSubOrders = 0;
        $totalUpserted = 0;

        try {
            do {
                $requestBody = [
                    'type' => 'bg.order.list.v2.get',
                    'pageSize' => 100,
                    'pageNumber' => $pageNumber,
                    'createAfter' => $from->timestamp,
                    'createBefore' => $to->timestamp,
                ];
                if ($status !== null && $status !== '') {
                    $requestBody['parentOrderStatus'] = (int) $status;
                }

                $signedRequest = $this->generateSignValue($requestBody);

                $response = Http::timeout(60)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

                if ($response->failed()) {
                    $this->error("Request failed (page {$pageNumber}): ".$response->body());
                    Log::error('FetchTemuOrders request failed', ['page' => $pageNumber, 'response' => $response->body()]);
                    break;
                }

                $data = $response->json();

                if (! ($data['success'] ?? false)) {
                    $this->error('Temu API error: '.($data['errorMsg'] ?? 'Unknown').' [code '.($data['errorCode'] ?? 'N/A').']');
                    Log::error('FetchTemuOrders API error', ['response' => $data]);
                    break;
                }

                $pageItems = $data['result']['pageItems'] ?? [];
                $totalCount = (int) ($data['result']['totalItemNum'] ?? $data['result']['totalCount'] ?? 0);

                $this->info("Page {$pageNumber}: ".count($pageItems)." parent orders (Total: {$totalCount})");

                if (empty($pageItems)) {
                    break;
                }

                foreach ($pageItems as $parent) {
                    $totalParents++;
                    $parentMap = $parent['parentOrderMap'] ?? [];
                    $subOrders = $parent['orderList'] ?? [];

                    foreach ($subOrders as $sub) {
                        $totalSubOrders++;
                        $orderSn = $sub['orderSn'] ?? null;
                        if (empty($orderSn)) {
                            continue;
                        }

                        $product = $sub['productList'][0] ?? [];
                        $orderStatus = $sub['orderStatus'] ?? null;
                        $parentStatus = $parentMap['parentOrderStatus'] ?? null;

                        $record = [
                            'parent_order_sn' => $parentMap['parentOrderSn'] ?? null,
                            'parent_order_status' => $parentStatus,
                            'parent_order_status_text' => $this->statusText($parentStatus),
                            'parent_order_time' => $this->tsToDateTime($parentMap['parentOrderTime'] ?? null),
                            'expect_ship_latest_time' => $this->tsToDateTime($parentMap['expectShipLatestTime'] ?? null),
                            'parent_shipping_time' => $this->tsToDateTime($parentMap['parentShippingTime'] ?? null),
                            'latest_delivery_time' => $this->tsToDateTime($parentMap['latestDeliveryTime'] ?? null),
                            'order_update_time' => $this->tsToDateTime($parentMap['updateTime'] ?? null),
                            'region_id' => $parentMap['regionId'] ?? null,
                            'site_id' => $parentMap['siteId'] ?? null,

                            'order_sn' => $orderSn,
                            'sku_id' => isset($sub['skuId']) ? (string) $sub['skuId'] : null,
                            'goods_id' => isset($sub['goodsId']) ? (string) $sub['goodsId'] : null,
                            'ext_code' => $product['extCode'] ?? null,
                            'product_sku_id' => isset($product['productSkuId']) ? (string) $product['productSkuId'] : null,
                            'goods_name' => $sub['goodsName'] ?? null,
                            'spec' => $sub['spec'] ?? null,
                            'quantity' => isset($sub['quantity']) ? (int) $sub['quantity'] : null,
                            'original_order_quantity' => isset($sub['originalOrderQuantity']) ? (int) $sub['originalOrderQuantity'] : null,
                            'canceled_quantity_before_shipment' => isset($sub['canceledQuantityBeforeShipment']) ? (int) $sub['canceledQuantityBeforeShipment'] : null,
                            'order_status' => $orderStatus,
                            'order_status_text' => $this->statusText($orderStatus),
                            'fulfillment_type' => $sub['fulfillmentType'] ?? null,
                            'order_payment_type' => $sub['orderPaymentType'] ?? null,
                            'thumb_url' => $sub['thumbUrl'] ?? null,
                            'order_shipping_time' => $this->tsToDateTime($sub['orderShippingTime'] ?? null),

                            'raw_json' => json_encode([
                                'parentOrderMap' => $parentMap,
                                'order' => $sub,
                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'fetch_window' => $window,
                            'fetched_at' => now(),
                        ];

                        TemuOrder::updateOrCreate(['order_sn' => $orderSn], $record);
                        $totalUpserted++;
                    }
                }

                $processedSoFar = $pageNumber * 100;
                $hasMorePages = $processedSoFar < $totalCount && count($pageItems) >= 100;
                $pageNumber++;

                usleep(300000); // 0.3s to avoid rate limits
            } while ($hasMorePages);

            $this->info("✅ Done. Parent orders: {$totalParents}, sub-orders seen: {$totalSubOrders}, rows upserted: {$totalUpserted}");
            Log::info('Completed FetchTemuOrders', [
                'parents' => $totalParents,
                'sub_orders' => $totalSubOrders,
                'upserted' => $totalUpserted,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Error in FetchTemuOrders: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in FetchTemuOrders: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function statusText($status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        return self::ORDER_STATUS_MAP[(int) $status] ?? (string) $status;
    }

    /** Convert a Temu timestamp (seconds or milliseconds) to a Carbon datetime. */
    private function tsToDateTime($ts): ?Carbon
    {
        if (empty($ts) || ! is_numeric($ts)) {
            return null;
        }
        $ts = (int) $ts;
        if ($ts <= 0) {
            return null;
        }
        if ($ts > 9999999999) { // milliseconds
            $ts = (int) ($ts / 1000);
        }

        try {
            return Carbon::createFromTimestamp($ts);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function generateSignValue($requestBody)
    {
        $appKey = config('services.temu.app_key');
        $appSecret = config('services.temu.secret_key');
        $accessToken = config('services.temu.access_token');
        $timestamp = time();

        $params = [
            'access_token' => $accessToken,
            'app_key' => $appKey,
            'timestamp' => $timestamp,
            'data_type' => 'JSON',
        ];

        $signParams = array_merge($params, $requestBody);
        ksort($signParams);

        $temp = '';
        foreach ($signParams as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $temp .= $key.$value;
        }

        $signStr = $appSecret.$temp.$appSecret;
        $params['sign'] = strtoupper(md5($signStr));

        return array_merge($params, $requestBody);
    }
}
