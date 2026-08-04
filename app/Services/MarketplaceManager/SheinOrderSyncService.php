<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\SheinOrderMetric;
use App\Services\SheinApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Shein orders into shein_order_metrics (Shopify import queue).
 * Uses SheinApiService::fetchOrdersRaw (order-list + order-detail).
 */
class SheinOrderSyncService
{
    public function __construct(
        protected SheinApiService $sheinApi
    ) {}

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        if (! $this->sheinApi->isConfigured()) {
            return ['success' => false, 'message' => 'Shein API credentials missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! Schema::hasTable('shein_order_metrics')) {
            return ['success' => false, 'message' => 'shein_order_metrics table missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('shein')) {
            return ['success' => true, 'message' => 'Order fetch disabled in settings.', 'upserted' => 0, 'pages' => 0];
        }

        $from = Carbon::parse($fromDate)->startOfDay();
        $days = max(1, min(30, (int) $from->diffInDays(Carbon::now()) + 1));

        try {
            // queryType 1 = new, 2 = updated — merge both windows.
            $rawNew = $this->sheinApi->fetchOrdersRaw($days, 1, true, false);
            $rawUpd = $this->sheinApi->fetchOrdersRaw($days, 2, true, false);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Shein order fetch failed: '.$e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
            ];
        }

        $detailsByNo = [];
        foreach (array_merge($rawNew['order_details'] ?? [], $rawUpd['order_details'] ?? []) as $od) {
            if (! is_array($od)) {
                continue;
            }
            $no = trim((string) ($od['orderNo'] ?? ''));
            if ($no !== '') {
                $detailsByNo[$no] = $od;
            }
        }

        // Fallback to list rows when detail missing.
        foreach (array_merge($rawNew['order_list'] ?? [], $rawUpd['order_list'] ?? []) as $ol) {
            if (! is_array($ol)) {
                continue;
            }
            $no = trim((string) ($ol['orderNo'] ?? ''));
            if ($no !== '' && ! isset($detailsByNo[$no])) {
                $detailsByNo[$no] = $ol;
            }
        }

        $upserted = 0;
        foreach ($detailsByNo as $order) {
            $upserted += $this->upsertOrder($order);
        }

        // Accept Pending orders on Shein before Shopify import / address sync.
        if (MarketplaceSyncSettings::canAutoAcceptOnShein()) {
            try {
                $accept = app(SheinOrderDetailService::class)->acceptPendingOrders(40);
                Log::info('SheinOrderSyncService: auto-accept', $accept);
            } catch (\Throwable $e) {
                Log::warning('SheinOrderSyncService: auto-accept failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($import) {
            $this->dispatchImportsForNewOrders();
        }

        // Address sync is queued by SyncMarketplaceOrdersJob (same as AliExpress / Reverb).

        $pages = (int) (($rawNew['windows'] ?? 0) + ($rawUpd['windows'] ?? 0));

        return [
            'success' => true,
            'message' => "Synced {$upserted} Shein order line(s) from {$days}d ({$pages} window(s)).",
            'upserted' => $upserted,
            'pages' => $pages,
            'fetched' => count($detailsByNo),
            'stored' => $upserted,
        ];
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function fetchAndStoreFromDate(string $fromDate): array
    {
        return $this->sync($fromDate, false);
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function fetchAndStore(int $days = 7): array
    {
        $from = Carbon::now()->subDays(max(0, $days))->toDateString();

        return $this->sync($from, false);
    }

    public function dispatchImportsForNewOrders(): int
    {
        $settings = MarketplaceSyncSettings::getFor('shein');
        if (! ($settings['order']['auto_import_to_shopify'] ?? false)) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('shein', $settings);

        $orders = SheinOrderMetric::query()
            ->whereNull('shopify_order_id')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', ['ready', 'import_failed', 'failed']);
            })
            ->orderBy('id')
            ->limit(50)
            ->get();

        $dispatched = 0;
        foreach ($orders as $order) {
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('shein', $order)) {
                continue;
            }

            try {
                \App\Jobs\ImportSheinOrderToShopify::dispatch((int) $order->id);
                $order->update(['import_status' => 'queued']);
                $dispatched++;
            } catch (\Throwable $e) {
                Log::warning('SheinOrderSyncService: failed to queue import', [
                    'id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function upsertOrder(array $order): int
    {
        $orderId = trim((string) ($order['orderNo'] ?? ''));
        if ($orderId === '') {
            return 0;
        }

        $existingPayload = SheinOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->value('raw_payload');
        if (is_array($existingPayload)) {
            if (is_array($existingPayload['order'] ?? null)) {
                $existingPayload = $existingPayload['order'];
            }
            $order = $this->mergeAddressFields($existingPayload, $order);
        }

        $statusCode = $order['orderStatus'] ?? null;
        $statusMap = [
            1 => 'Pending',
            2 => 'To Be Shipped',
            3 => 'To Be Shipped by SHEIN',
            4 => 'Shipped',
            5 => 'Received',
            6 => 'Refund',
            7 => 'To Be Collected by SHEIN',
        ];
        $status = $statusMap[(int) $statusCode] ?? (string) ($statusCode ?? '');

        $orderTime = $order['orderTime'] ?? $order['paymentTime'] ?? null;
        $orderDate = null;
        if (is_string($orderTime) && $orderTime !== '') {
            try {
                $orderDate = Carbon::parse($orderTime);
            } catch (\Throwable $e) {
                $orderDate = null;
            }
        }

        $goodsList = is_array($order['orderGoodsInfoList'] ?? null) ? $order['orderGoodsInfoList'] : [];
        if ($goodsList === []) {
            SheinOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => '__order__'],
                [
                    'order_number' => $orderId,
                    'order_date' => $orderDate,
                    'status' => $status,
                    'quantity' => 1,
                    // Match AliExpress / Reverb wrapper shape.
                    'raw_payload' => ['order' => $order],
                    'import_status' => 'ready',
                ]
            );

            return 1;
        }

        $count = 0;
        foreach ($goodsList as $goods) {
            if (! is_array($goods)) {
                continue;
            }
            $sku = trim((string) ($goods['sellerSku'] ?? $goods['goodsSn'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($goods['skuCode'] ?? '__unknown__'));
            }
            $qty = max(1, (int) ($goods['quantity'] ?? 1));
            $amount = isset($goods['sellerCurrencyPrice'])
                ? (float) $goods['sellerCurrencyPrice']
                : (isset($goods['orderCurrencyPrice']) ? (float) $goods['orderCurrencyPrice'] : null);

            // Prefer numeric goodsId for tracking ship API; keep skuCode as fallback.
            $goodsId = trim((string) ($goods['goodsId'] ?? $goods['goods_id'] ?? ''));
            $skuCode = trim((string) ($goods['skuCode'] ?? ''));
            $productId = $goodsId !== '' ? $goodsId : $skuCode;

            SheinOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => $sku],
                [
                    'order_number' => $orderId,
                    'order_date' => $orderDate,
                    'status' => $status,
                    'product_id' => $productId,
                    'display_title' => trim((string) ($goods['goodsTitle'] ?? '')),
                    'quantity' => $qty,
                    'amount' => $amount,
                    // Match AliExpress / Reverb: raw['order'] + raw['line'].
                    'raw_payload' => ['order' => $order, 'line' => $goods],
                    'import_status' => 'ready',
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    protected function mergeAddressFields(array $existing, array $incoming): array
    {
        // Nested address objects: field-level merge (never wipe a full receiveMsg with a partial one).
        foreach (['receiveMsg', 'shippingAddress', 'billAddress'] as $key) {
            $oldVal = is_array($existing[$key] ?? null) ? $existing[$key] : null;
            $newVal = is_array($incoming[$key] ?? null) ? $incoming[$key] : null;
            if ($oldVal === null || $oldVal === []) {
                continue;
            }
            if ($newVal === null || $newVal === []) {
                $incoming[$key] = $oldVal;

                continue;
            }
            foreach ($oldVal as $field => $oldFieldVal) {
                $newFieldVal = $newVal[$field] ?? null;
                if (($newFieldVal === null || $newFieldVal === '') && $oldFieldVal !== null && $oldFieldVal !== '') {
                    $newVal[$field] = $oldFieldVal;
                }
            }
            $incoming[$key] = $newVal;
        }

        $keys = [
            'buyerName', 'buyerEmail', 'phone',
            'address', 'city', 'province', 'postCode', 'country',
            'firstName', 'lastName',
        ];

        foreach ($keys as $key) {
            $newVal = $incoming[$key] ?? null;
            $oldVal = $existing[$key] ?? null;
            $newEmpty = $newVal === null || $newVal === '' || (is_array($newVal) && $newVal === []);
            $oldFilled = $oldVal !== null && $oldVal !== '' && (! is_array($oldVal) || $oldVal !== []);
            if ($newEmpty && $oldFilled) {
                $incoming[$key] = $existing[$key];
            }
        }

        return $incoming;
    }
}
