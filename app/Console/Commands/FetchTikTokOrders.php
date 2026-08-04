<?php

namespace App\Console\Commands;

use App\Models\Tiktok2Order;
use App\Models\TiktokOrder;
use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTikTokOrders extends Command
{
    protected $signature = 'tiktok:fetch-orders
        {--channel=tiktok : tiktok (→ tiktok_orders) or tiktok2 (→ tiktok2_orders)}
        {--days=60 : Rolling window of days to fetch (create_time)}
        {--from= : Start date Y-m-d (overrides --days start)}
        {--to= : End date Y-m-d (default: today)}
        {--status= : Optional order_status filter (e.g. COMPLETED, CANCELLED)}
        {--chunk-days=15 : Split API requests into N-day windows}
        {--prune : Delete rows older than the fetch window after import}';

    protected $description = 'Fetch TikTok / TikTok 2 Shop order raw sales data (line-item level)';

    public function handle(): int
    {
        $channel = strtolower(trim((string) $this->option('channel')));
        if (! in_array($channel, ['tiktok', 'tiktok2'], true)) {
            $this->error('Invalid --channel. Use tiktok or tiktok2.');

            return self::FAILURE;
        }

        $tiktok = $channel === 'tiktok2' ? app(TikTok2ShopService::class) : app(TikTokShopService::class);
        $orderModel = $channel === 'tiktok2' ? Tiktok2Order::class : TiktokOrder::class;
        $configKey = $channel === 'tiktok2' ? 'tiktok2' : 'tiktok';
        $label = $channel === 'tiktok2' ? 'TikTok 2' : 'TikTok';

        $tiktok->setOutputCallback(function (string $type, string $message) {
            match ($type) {
                'error' => $this->error($message),
                'warn' => $this->warn($message),
                default => $this->line($message),
            };
        });

        if (! $tiktok->isAuthenticated()) {
            $access = config("services.{$configKey}.access_token");
            $refresh = config("services.{$configKey}.refresh_token");
            if ($access) {
                $tiktok->setTokens($access, $refresh);
            }
        }

        if (! $tiktok->isAuthenticated()) {
            $prefix = strtoupper($configKey);
            $this->error("No {$label} access token. Run OAuth or set {$prefix}_ACCESS_TOKEN / {$prefix}_REFRESH_TOKEN.");

            return self::FAILURE;
        }

        $tz = 'America/Los_Angeles';
        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'), $tz)->endOfDay()
            : Carbon::now($tz);
        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'), $tz)->startOfDay()
            : $to->copy()->subDays(max(1, (int) $this->option('days')) - 1)->startOfDay();

        $status = trim((string) ($this->option('status') ?: ''));
        $status = $status !== '' ? strtoupper($status) : null;
        $chunkDays = max(1, (int) $this->option('chunk-days'));

        $this->info("Fetching by order create date: {$from->toDateTimeString()} → {$to->toDateTimeString()}");
        if ($status) {
            $this->info("Status filter: {$status}");
        }

        $shopInfo = $tiktok->getShopInfo();
        $shopRegion = $shopInfo['shops'][0]['region'] ?? ($shopInfo['shops'][0]['shop_region'] ?? null);

        $totalOrders = 0;
        $totalLines = 0;
        $upserted = 0;
        $cursor = $from->copy();

        while ($cursor->lt($to)) {
            $chunkEnd = $cursor->copy()->addDays($chunkDays);
            if ($chunkEnd->gt($to)) {
                $chunkEnd = $to->copy();
            }

            $ge = $cursor->timestamp;
            $lt = $chunkEnd->timestamp;
            if ($lt <= $ge) {
                $lt = $ge + 1;
            }

            $this->info('Chunk (order date): '.$cursor->toDateString().' → '.$chunkEnd->toDateString());

            $orders = $tiktok->getAllOrders($ge, $lt, $status, 50);
            $totalOrders += count($orders);

            foreach ($orders as $order) {
                $lines = $this->mapOrderToRows($order, $shopRegion);
                foreach ($lines as $row) {
                    $orderModel::updateOrCreate(
                        [
                            'order_id' => $row['order_id'],
                            'line_item_id' => $row['line_item_id'],
                        ],
                        $row
                    );
                    $upserted++;
                    $totalLines++;
                }
            }

            $cursor = $chunkEnd;
            usleep(200000);
        }

        if ($this->option('prune')) {
            $deleted = $orderModel::where('order_created_at', '<', $from)->delete();
            $this->info("Pruned {$deleted} rows older than window start.");
        }

        $this->info("Done ({$label}). Orders fetched={$totalOrders} line-rows upserted={$upserted} (processed lines={$totalLines})");
        Log::info('tiktok:fetch-orders completed', [
            'channel' => $channel,
            'orders' => $totalOrders,
            'upserted' => $upserted,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapOrderToRows(array $order, ?string $shopRegion): array
    {
        $orderId = (string) ($order['id'] ?? '');
        if ($orderId === '') {
            return [];
        }

        $lineItems = $order['line_items'] ?? [];
        if (! is_array($lineItems) || $lineItems === []) {
            $lineItems = [[
                'id' => $orderId.'_0',
                'seller_sku' => null,
                'sale_price' => null,
                'display_status' => $order['status'] ?? null,
            ]];
        }

        $orderAmount = $this->extractOrderAmount($order);
        $createdAt = $this->tsToCarbon($order['create_time'] ?? null);
        $updatedAt = $this->tsToCarbon($order['update_time'] ?? null);
        $fetchedAt = now();

        $rows = [];
        foreach ($lineItems as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $lineId = (string) ($item['id'] ?? ($orderId.'_'.$index));
            $qty = (int) ($item['quantity'] ?? $item['sku_quantity'] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }

            $rows[] = [
                'order_id' => $orderId,
                'line_item_id' => $lineId,
                'order_status' => $order['status'] ?? null,
                'line_status' => $item['display_status'] ?? ($item['package_status'] ?? null),
                'seller_sku' => $this->normalizeSku($item['seller_sku'] ?? null),
                'product_id' => isset($item['product_id']) ? (string) $item['product_id'] : null,
                'sku_id' => isset($item['sku_id']) ? (string) $item['sku_id'] : null,
                'product_name' => $item['product_name'] ?? null,
                'quantity' => $qty,
                'original_price' => $this->money($item['original_price'] ?? null),
                'sale_price' => $this->money($item['sale_price'] ?? null),
                'seller_discount' => $this->money($item['seller_discount'] ?? null),
                'platform_discount' => $this->money($item['platform_discount'] ?? null),
                'currency' => $item['currency'] ?? ($order['payment']['currency'] ?? null),
                'order_amount' => $orderAmount,
                'fulfillment_type' => $order['fulfillment_type'] ?? null,
                'delivery_type' => $order['delivery_type'] ?? null,
                'shipping_provider' => $item['shipping_provider_name'] ?? null,
                'buyer_nickname' => $order['buyer_nickname'] ?? null,
                'shop_region' => $shopRegion,
                'order_created_at' => $createdAt,
                'order_updated_at' => $updatedAt,
                'rts_time' => $this->tsToCarbon($item['rts_time'] ?? $order['rts_time'] ?? null),
                'delivery_time' => $this->tsToCarbon($item['delivery_time'] ?? $order['delivery_time'] ?? null),
                'collection_time' => $this->tsToCarbon($order['collection_time'] ?? null),
                'raw_json' => $order,
                'fetched_at' => $fetchedAt,
            ];
        }

        return $rows;
    }

    protected function extractOrderAmount(array $order): ?float
    {
        $payment = $order['payment'] ?? [];
        if (is_array($payment)) {
            foreach (['total_amount', 'original_total_product_price', 'sub_total'] as $key) {
                if (isset($payment[$key]) && is_numeric($payment[$key])) {
                    return (float) $payment[$key];
                }
            }
        }

        $sum = 0.0;
        $any = false;
        foreach ($order['line_items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (isset($item['sale_price']) && is_numeric($item['sale_price'])) {
                $qty = (int) ($item['quantity'] ?? 1);
                $sum += ((float) $item['sale_price']) * max(1, $qty);
                $any = true;
            }
        }

        return $any ? $sum : null;
    }

    protected function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * Store API unix timestamps as UTC wall-clock (queried via CA→UTC conversion).
     */
    protected function tsToCarbon(mixed $ts): ?Carbon
    {
        if ($ts === null || $ts === '' || ! is_numeric($ts)) {
            return null;
        }

        // Keep UTC representation so California day windows can convert cleanly.
        return Carbon::createFromTimestamp((int) $ts, 'UTC')->utc();
    }

    protected function normalizeSku(mixed $sku): ?string
    {
        if ($sku === null) {
            return null;
        }
        $sku = trim((string) $sku);

        return $sku === '' ? null : strtoupper($sku);
    }
}
