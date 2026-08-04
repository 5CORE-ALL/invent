<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\Temu2Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchTemu2Orders extends Command
{
    use ProcessesUpdatesInChunks;

    /**
     * Always fetches the last 60 days of Temu 2 orders and keeps the table pruned
     * to a rolling 60-day window.
     *
     *   php artisan app:fetch-temu2-orders
     */
    protected $signature = 'app:fetch-temu2-orders {--chunk= : Override DB write chunk size (default from cron-monitor config)}';

    protected $description = 'Fetch the last 60 days of order-wise raw data from Temu 2 (bg.order.list.v2.get) into temu2_orders (rolling 60-day window)';

    /** Rolling window size — always 60 days. */
    private const WINDOW_DAYS = 60;

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
        Log::info('Starting FetchTemu2Orders command');
        $this->info('Starting FetchTemu2Orders command');

        $appKey = config('services.temu2.app_key');
        $appSecret = config('services.temu2.secret_key');
        $accessToken = config('services.temu2.access_token');

        if (empty($appKey) || empty($appSecret) || empty($accessToken)) {
            $this->error('Missing Temu 2 API credentials in .env (TEMU2_APP_KEY, TEMU2_SECRET_KEY, TEMU2_ACCESS_TOKEN)');

            return self::FAILURE;
        }

        // Always last 60 days (rolling window), fetching right up to the present moment.
        // Previously the upper bound was IST "yesterday end" (Carbon::today()->subDay()),
        // which is only ~11:29 AM Pacific of yesterday — so a full Pacific "yesterday" was
        // never in temu2_orders and Y Sales / daily figures came up short. createBefore is an
        // epoch instant, so using now() simply means "everything up to now".
        $to = Carbon::now();
        $from = $to->copy()->subDays(self::WINDOW_DAYS - 1)->startOfDay();
        $status = null;
        $window = 'L'.self::WINDOW_DAYS;

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
                    Log::error('FetchTemu2Orders request failed', ['page' => $pageNumber, 'response' => $response->body()]);
                    break;
                }

                $data = $response->json();

                if (! ($data['success'] ?? false)) {
                    $this->error('Temu 2 API error: '.($data['errorMsg'] ?? 'Unknown').' [code '.($data['errorCode'] ?? 'N/A').']');
                    Log::error('FetchTemu2Orders API error', ['response' => $data]);
                    break;
                }

                $pageItems = $data['result']['pageItems'] ?? [];
                $totalCount = (int) ($data['result']['totalItemNum'] ?? $data['result']['totalCount'] ?? 0);

                $this->info("Page {$pageNumber}: ".count($pageItems)." parent orders (Total: {$totalCount})");

                if (empty($pageItems)) {
                    break;
                }

                $pendingRecords = [];
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

                        $pendingRecords[] = [
                            'order_sn' => $orderSn,
                            'record' => [
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
                            ],
                        ];
                    }
                }

                // API pageSize stays 100; persist in config-sized write chunks.
                $this->writeItemsInChunks($pendingRecords, function (array $chunk) use (&$totalUpserted) {
                    foreach ($chunk as $entry) {
                        Temu2Order::updateOrCreate(['order_sn' => $entry['order_sn']], $entry['record']);
                        $totalUpserted++;
                    }

                    return ['updated' => count($chunk), 'failed' => 0];
                });

                $processedSoFar = $pageNumber * 100;
                $hasMorePages = $processedSoFar < $totalCount && count($pageItems) >= 100;
                $pageNumber++;

                usleep(300000); // 0.3s to avoid rate limits
            } while ($hasMorePages);

            // Keep only a rolling 60-day window: prune orders older than the start of the window.
            $pruned = Temu2Order::where('parent_order_time', '<', $from)->delete();

            // Pull the ACTUAL order amounts (bg.order.amount.query) so reported sales use
            // Temu 2's real figures instead of catalog price × qty — the same principle as the
            // Amazon pipeline, which stores real per-order price from its orderItems endpoint.
            $amountStats = $this->fetchAndStoreOrderAmounts();

            $this->info("✅ Done. Parent orders: {$totalParents}, sub-orders seen: {$totalSubOrders}, rows upserted: {$totalUpserted}, pruned (>60d): {$pruned}, amounts: {$amountStats['updated']} rows from {$amountStats['parents']} parent(s)");
            Log::info('Completed FetchTemu2Orders', [
                'parents' => $totalParents,
                'sub_orders' => $totalSubOrders,
                'upserted' => $totalUpserted,
                'pruned' => $pruned,
                'amount_parents' => $amountStats['parents'],
                'amount_rows' => $amountStats['updated'],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Error in FetchTemu2Orders: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in FetchTemu2Orders: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Fetch actual order amounts via bg.order.amount.query for parents that need them
     * and store per-sub-order base/total amounts on temu2_orders.
     *
     * Refreshes: never-fetched rows, plus a trailing recent window (amounts can change
     * with cancellations/refunds — same reasoning as the Amazon trailing re-sync).
     *
     * @return array{parents:int, updated:int}
     */
    private function fetchAndStoreOrderAmounts(): array
    {
        $recentCutoff = Carbon::now()->subDays(3);

        // Group by parent (not distinct + orderBy) so ordering by the latest order time is
        // compatible with MySQL ONLY_FULL_GROUP_BY. Recent parents first so a cut-short run
        // still refreshes the newest orders.
        $parentSns = Temu2Order::whereNotNull('parent_order_sn')
            ->where(function ($q) use ($recentCutoff) {
                $q->whereNull('amount_fetched_at')
                    ->orWhere('parent_order_time', '>=', $recentCutoff);
            })
            ->groupBy('parent_order_sn')
            ->orderByRaw('MAX(parent_order_time) DESC')
            ->pluck('parent_order_sn')
            ->filter()
            ->values();

        if ($parentSns->isEmpty()) {
            return ['parents' => 0, 'updated' => 0];
        }

        $this->info('Fetching order amounts for '.$parentSns->count().' parent order(s)...');

        $parentsDone = 0;
        $rowsUpdated = 0;
        $consecutiveFailures = 0;
        $loggedSample = false;

        foreach ($parentSns as $parentOrderSn) {
            $result = $this->queryOrderAmount($parentOrderSn);

            if ($result === null) {
                $consecutiveFailures++;
                // Stop early if the API is clearly unhappy (quota / auth) rather than
                // hammering it for every parent.
                if ($consecutiveFailures >= 10) {
                    $this->warn('   ⚠️ Too many consecutive amount-query failures — stopping amount sync for this run.');
                    break;
                }
                usleep(300000);
                continue;
            }
            $consecutiveFailures = 0;

            // Emit the first raw response so the exact amount schema is visible in this
            // command's own output/logs (no separate probe command needed).
            if (! $loggedSample) {
                $loggedSample = true;
                $sample = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $this->line('   ℹ️ Sample bg.order.amount.query result: '.$sample);
                Log::info('Temu order amount sample', ['parentOrderSn' => $parentOrderSn, 'result' => $result]);
            }

            $rawJson = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $amountsByOrderSn = $this->parseAmountResult($result);

            $subOrders = Temu2Order::where('parent_order_sn', $parentOrderSn)->get();
            $chunkSize = $this->monitoredChunkSize();
            foreach ($subOrders->chunk($chunkSize) as $subChunk) {
                DB::transaction(function () use ($subChunk, $amountsByOrderSn, $rawJson, &$rowsUpdated) {
                    foreach ($subChunk as $row) {
                        $amt = $amountsByOrderSn[$row->order_sn] ?? null;

                        $row->amount_raw_json = $rawJson;
                        $row->amount_fetched_at = now();
                        if ($amt !== null) {
                            if ($amt['base'] !== null) {
                                $row->order_base_amount = $amt['base'];
                            }
                            if ($amt['total'] !== null) {
                                $row->order_total_amount = $amt['total'];
                            }
                        }
                        $row->save();
                        $rowsUpdated++;
                    }
                });
            }

            $parentsDone++;
            usleep(350000); 
        }

        return ['parents' => $parentsDone, 'updated' => $rowsUpdated];
    }

    /**
     * Call bg.order.amount.query for one parent order. Returns the decoded `result`
     * array on success, or null on any failure (logged, non-fatal).
     */
    private function queryOrderAmount(string $parentOrderSn): ?array
    {
        try {
            $body = [
                'type' => 'bg.order.amount.query',
                'parentOrderSn' => $parentOrderSn,
            ];
            $signed = $this->generateSignValue($body);

            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://openapi-b-us.temu.com/openapi/router', $signed);

            if ($response->failed()) {
                Log::warning('bg.order.amount.query HTTP failure', ['parent' => $parentOrderSn, 'status' => $response->status()]);

                return null;
            }

            $data = $response->json();
            if (! ($data['success'] ?? false)) {
                Log::warning('bg.order.amount.query API error', ['parent' => $parentOrderSn, 'error' => $data['errorMsg'] ?? null, 'code' => $data['errorCode'] ?? null]);
                return null;
            }

            $result = $data['result'] ?? null;

            return is_array($result) ? $result : null;
        } catch (\Throwable $e) {
            Log::warning('bg.order.amount.query exception', ['parent' => $parentOrderSn, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Defensively extract per-sub-order amounts from a bg.order.amount.query result.
     * The response wraps amounts in `result`; per-line entries live in one of a few
     * possible list keys and are keyed by orderSn. Base = goods/product amount (matches
     * Temu 2 "Base price sales"); total = order/pay amount. Unknown shapes yield an empty
     * map, in which case the caller keeps the raw JSON and sales fall back to catalog price.
     *
     * @return array<string, array{base: float|null, total: float|null}>
     */
    private function parseAmountResult(array $result): array
    {
        $baseKeys = ['goodsAmount', 'baseAmount', 'productAmount', 'skuAmount', 'goodsAmountTotal', 'salesAmount'];
        $totalKeys = ['orderAmount', 'totalAmount', 'payAmount', 'settleAmount', 'actualAmount'];

        $pick = function (array $entry, array $keys): ?float {
            foreach ($keys as $k) {
                if (array_key_exists($k, $entry) && is_scalar($entry[$k]) && $entry[$k] !== '') {
                    return (float) $entry[$k];
                }
            }

            return null;
        };

        // Find the array of per-order entries (each has an orderSn).
        $entries = [];
        foreach (['skuList', 'orderList', 'subOrderList', 'orderAmountList', 'amountList', 'items'] as $listKey) {
            if (isset($result[$listKey]) && is_array($result[$listKey])) {
                foreach ($result[$listKey] as $e) {
                    if (is_array($e) && isset($e['orderSn'])) {
                        $entries[] = $e;
                    }
                }
                if (! empty($entries)) {
                    break;
                }
            }
        }

        // Fallback: the result itself represents a single order line.
        if (empty($entries) && isset($result['orderSn'])) {
            $entries[] = $result;
        }

        $out = [];
        foreach ($entries as $e) {
            $orderSn = (string) $e['orderSn'];
            if ($orderSn === '') {
                continue;
            }
            $out[$orderSn] = [
                'base' => $pick($e, $baseKeys),
                'total' => $pick($e, $totalKeys),
            ];
        }

        return $out;
    }

    private function statusText($status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        return self::ORDER_STATUS_MAP[(int) $status] ?? (string) $status;
    }

    /** Convert a Temu 2 timestamp (seconds or milliseconds) to a Carbon datetime. */
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
            // Store in Pacific (Temu's reporting timezone) so day-windows line up with
            // Seller Central regardless of the server/app timezone.
            return Carbon::createFromTimestamp($ts, 'America/Los_Angeles');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function generateSignValue($requestBody)
    {
        $appKey = config('services.temu2.app_key');
        $appSecret = config('services.temu2.secret_key');
        $accessToken = config('services.temu2.access_token');
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
