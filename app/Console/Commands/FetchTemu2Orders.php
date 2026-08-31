<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\Temu2Order;
use App\Services\MarketplaceManager\TemuOrderAmountParser;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchTemu2Orders extends Command
{
    use ProcessesUpdatesInChunks;

    /**
     * Default: last 60 days + prune to that window (sales metrics cron).
     * Marketplace Manager passes --from / --days with --no-prune so older MM rows stay.
     *
     *   php artisan app:fetch-temu2-orders
     *   php artisan app:fetch-temu2-orders --from=2026-07-07 --no-prune
     *   php artisan app:fetch-temu2-orders --days=7 --no-prune
     */
    protected $signature = 'app:fetch-temu2-orders
                            {--from= : Fetch orders created on/after this date (YYYY-MM-DD)}
                            {--days= : Fetch orders from the last N days (ignored when --from is set)}
                            {--no-prune : Do not delete rows older than the fetch window}
                            {--chunk= : Override DB write chunk size (default from cron-monitor config)}';

    protected $description = 'Fetch Temu 2 order-wise raw data (bg.order.list.v2.get) into temu2_orders';

    /** Default rolling window when neither --from nor --days is set. */
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

        // Default: last 60 days (rolling window) up to now — used by sales metrics cron.
        // MM / temu2:sync-orders pass --from or --days (with --no-prune) for Shopify import prep.
        $to = Carbon::now();
        $fromOption = trim((string) $this->option('from'));
        $daysOption = $this->option('days');
        if ($fromOption !== '') {
            try {
                $from = Carbon::parse($fromOption, 'America/Los_Angeles')->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Invalid --from date. Use YYYY-MM-DD.');

                return self::FAILURE;
            }
            $window = 'from:'.$from->toDateString();
        } elseif ($daysOption !== null && $daysOption !== '') {
            $days = max(1, min(730, (int) $daysOption));
            $from = $to->copy()->subDays($days - 1)->startOfDay();
            $window = 'L'.$days;
        } else {
            $from = $to->copy()->subDays(self::WINDOW_DAYS - 1)->startOfDay();
            $window = 'L'.self::WINDOW_DAYS;
        }
        $status = null;
        $shouldPrune = ! $this->option('no-prune') && $fromOption === '' && ($daysOption === null || $daysOption === '');

        $this->info('Window: '.$from->toDateTimeString().' → '.$to->toDateTimeString().($shouldPrune ? ' (prune on)' : ' (no prune)'));

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

            // Default sales cron: keep only a rolling 60-day window.
            // MM fetches (--from/--days/--no-prune) must not wipe older rows still needed elsewhere.
            $pruned = 0;
            if ($shouldPrune) {
                $pruned = Temu2Order::where('parent_order_time', '<', $from)->delete();
            }

            // Pull the ACTUAL order amounts (bg.order.amount.query) so reported sales use
            // Temu 2's real figures instead of catalog price × qty — the same principle as the
            // Amazon pipeline, which stores real per-order price from its orderItems endpoint.
            $amountStats = $this->fetchAndStoreOrderAmounts($from);

            $this->info("✅ Done. Parent orders: {$totalParents}, sub-orders seen: {$totalSubOrders}, rows upserted: {$totalUpserted}, pruned: {$pruned}, amounts: {$amountStats['updated']} rows from {$amountStats['parents']} parent(s)");
            Log::info('Completed FetchTemu2Orders', [
                'parents' => $totalParents,
                'sub_orders' => $totalSubOrders,
                'upserted' => $totalUpserted,
                'pruned' => $pruned,
                'window' => $window,
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
    private function fetchAndStoreOrderAmounts(?Carbon $windowFrom = null): array
    {
        $recentCutoff = Carbon::now()->subDays(3);

        $backfilled = TemuOrderAmountParser::backfillStoredAmounts(Temu2Order::class);
        if ($backfilled > 0) {
            $this->info("Backfilled {$backfilled} Temu 2 amount(s) from stored amount JSON.");
        }

        // Only the fetch window (plus never-fetched rows in that window). Do not walk
        // every historical parent — that hit rate limits after the list pages and
        // aborted with 0 amounts (277 listed, 2700 amount-queued).
        $parentSns = Temu2Order::whereNotNull('parent_order_sn')
            ->when($windowFrom !== null, function ($q) use ($windowFrom) {
                $q->where('parent_order_time', '>=', $windowFrom);
            })
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
        sleep(2);

        $parentsDone = 0;
        $rowsUpdated = 0;
        $consecutiveFailures = 0;
        $loggedSample = false;
        $loggedFirstError = false;

        foreach ($parentSns as $parentOrderSn) {
            [$result, $error] = $this->queryOrderAmount($parentOrderSn);

            if ($result === null) {
                $consecutiveFailures++;
                if (! $loggedFirstError) {
                    $loggedFirstError = true;
                    $this->warn('   ⚠️ bg.order.amount.query failed: '.($error ?: 'unknown').' (parent '.$parentOrderSn.')');
                }
                if ($consecutiveFailures >= 10) {
                    $this->warn('   ⚠️ Too many consecutive amount-query failures — stopping amount sync for this run.');
                    break;
                }
                usleep(800000);
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
            $amountsByOrderSn = TemuOrderAmountParser::parseResult($result);

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
     * Call bg.order.amount.query for one parent order.
     *
     * @return array{0: ?array, 1: string}
     */
    private function queryOrderAmount(string $parentOrderSn): array
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
                $msg = 'HTTP '.$response->status();
                Log::warning('bg.order.amount.query HTTP failure', ['parent' => $parentOrderSn, 'status' => $response->status()]);

                return [null, $msg];
            }

            $data = $response->json();
            if (! ($data['success'] ?? false)) {
                $msg = (string) ($data['errorMsg'] ?? 'Unknown').' [code '.($data['errorCode'] ?? 'N/A').']';
                Log::warning('bg.order.amount.query API error', ['parent' => $parentOrderSn, 'error' => $data['errorMsg'] ?? null, 'code' => $data['errorCode'] ?? null]);

                return [null, $msg];
            }

            $result = $data['result'] ?? null;

            return [is_array($result) ? $result : null, is_array($result) ? '' : 'empty result'];
        } catch (\Throwable $e) {
            Log::warning('bg.order.amount.query exception', ['parent' => $parentOrderSn, 'error' => $e->getMessage()]);

            return [null, $e->getMessage()];
        }
    }

    /**
     * @return array<string, array{base: float|null, total: float|null}>
     */
    private function parseAmountResult(array $result): array
    {
        return TemuOrderAmountParser::parseResult($result);
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
