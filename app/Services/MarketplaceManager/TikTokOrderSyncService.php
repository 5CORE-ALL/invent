<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\TiktokOrder;
use App\Services\TikTokShopService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TikTokOrderSyncService
{
    public function __construct(
        protected TikTokShopService $tiktokApi
    ) {}

    /**
     * @return array{success: bool, message: string, upserted: int, fetched: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        if (! $this->tiktokApi->isAuthenticated()) {
            return ['success' => false, 'message' => 'TikTok Shop API not authenticated.', 'upserted' => 0, 'fetched' => 0];
        }

        if (! Schema::hasTable('tiktok_orders')) {
            return ['success' => false, 'message' => 'tiktok_orders table missing.', 'upserted' => 0, 'fetched' => 0];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('tiktok')) {
            return ['success' => true, 'message' => 'Order fetch disabled in settings.', 'upserted' => 0, 'fetched' => 0];
        }

        $from = Carbon::parse($fromDate)->startOfDay();
        $createTimeGe = $from->timestamp;
        $createTimeLt = now()->timestamp;

        $orders = $this->tiktokApi->getAllOrders($createTimeGe, $createTimeLt);

        if ($orders === []) {
            return [
                'success' => true,
                'message' => 'No TikTok Shop orders found in date range.',
                'upserted' => 0,
                'fetched' => 0,
            ];
        }

        $upserted = 0;
        foreach ($orders as $order) {
            $upserted += $this->upsertOrder($order);
        }

        if ($import) {
            $this->dispatchImportsForNewOrders();
        }

        return [
            'success' => true,
            'message' => "Synced {$upserted} TikTok Shop order line(s) from ".count($orders)." order(s).",
            'upserted' => $upserted,
            'fetched' => count($orders),
        ];
    }

    /**
     * @return array{success: bool, message: string, upserted: int, fetched: int}
     */
    public function fetchAndStore(int $days = 60): array
    {
        $from = Carbon::now()->subDays(max(1, $days))->toDateString();

        return $this->sync($from, false);
    }

    public function dispatchImportsForNewOrders(): int
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok');
        if (! ($settings['order']['auto_import_to_shopify'] ?? false)) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('tiktok', $settings);

        // Only import open/new orders (like Reverb), not delivered/completed history.
        $importableStatuses = [
            'AWAITING_SHIPMENT',
            'PARTIALLY_SHIPPING',
            'AWAITING_COLLECTION',
            'ON_HOLD',
        ];

        $orders = TiktokOrder::query()
            ->whereNull('shopify_order_id')
            ->whereIn('order_status', $importableStatuses)
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', ['ready', 'import_failed', 'failed']);
            })
            ->orderBy('id')
            ->limit(50)
            ->get();

        $seenOrderIds = [];
        $dispatched = 0;

        foreach ($orders as $order) {
            $orderId = (string) $order->order_id;
            if (isset($seenOrderIds[$orderId])) {
                continue;
            }
            $seenOrderIds[$orderId] = true;

            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('tiktok', $order)) {
                continue;
            }

            try {
                \App\Jobs\ImportTikTokOrderToShopify::dispatch((int) $order->id);
                TiktokOrder::query()
                    ->where('order_id', $orderId)
                    ->whereNull('shopify_order_id')
                    ->update(['import_status' => 'queued']);
                $dispatched++;
            } catch (\Throwable $e) {
                Log::warning('TikTokOrderSyncService: failed to queue import', [
                    'id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    protected function upsertOrder(array $order): int
    {
        $orderId = trim((string) ($order['id'] ?? $order['order_id'] ?? ''));
        if ($orderId === '') {
            return 0;
        }

        $orderStatus = (string) ($order['status'] ?? $order['order_status'] ?? '');
        $orderAmount = null;
        $payment = $order['payment'] ?? [];
        if (is_array($payment)) {
            $orderAmount = (float) ($payment['total_amount'] ?? $payment['original_total_product_price'] ?? 0);
            if ($orderAmount == 0) {
                $orderAmount = (float) ($order['total_amount'] ?? 0);
            }
        }
        $currency = (string) ($payment['currency'] ?? $order['currency'] ?? 'USD');
        $buyerNickname = (string) ($order['buyer_message'] ?? $order['buyer_uid'] ?? '');
        $fulfillmentType = (string) ($order['fulfillment_type'] ?? '');
        $shippingProvider = (string) ($order['shipping_provider'] ?? '');
        $orderCreatedAt = isset($order['create_time'])
            ? Carbon::createFromTimestamp((int) $order['create_time'])
            : null;
        $orderUpdatedAt = isset($order['update_time'])
            ? Carbon::createFromTimestamp((int) $order['update_time'])
            : null;

        $lineItems = $order['line_items'] ?? $order['order_line_list'] ?? $order['items'] ?? [];
        if (! is_array($lineItems) || $lineItems === []) {
            TiktokOrder::updateOrCreate(
                ['order_id' => $orderId, 'line_item_id' => '__order__'],
                [
                    'order_status' => $orderStatus,
                    'order_amount' => $orderAmount,
                    'currency' => $currency,
                    'buyer_nickname' => $buyerNickname,
                    'fulfillment_type' => $fulfillmentType,
                    'shipping_provider' => $shippingProvider,
                    'order_created_at' => $orderCreatedAt,
                    'order_updated_at' => $orderUpdatedAt,
                    'raw_json' => json_encode($order),
                    'fetched_at' => now(),
                    'import_status' => 'ready',
                ]
            );

            return 1;
        }

        $count = 0;
        foreach ($lineItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $lineItemId = trim((string) ($item['id'] ?? $item['order_line_id'] ?? ''));
            $sellerSku = trim((string) ($item['seller_sku'] ?? $item['sku'] ?? ''));
            $productId = trim((string) ($item['product_id'] ?? ''));
            $skuId = trim((string) ($item['sku_id'] ?? ''));
            $productName = trim((string) ($item['product_name'] ?? $item['name'] ?? ''));
            $qty = max(1, (int) ($item['quantity'] ?? 1));

            $originalPrice = (float) ($item['original_price'] ?? $item['sku_original_price'] ?? 0);
            $salePrice = (float) ($item['sale_price'] ?? $item['sku_sale_price'] ?? 0);
            $sellerDiscount = (float) ($item['seller_discount'] ?? 0);
            $platformDiscount = (float) ($item['platform_discount'] ?? 0);
            $lineStatus = (string) ($item['display_status'] ?? $item['item_status'] ?? '');

            TiktokOrder::updateOrCreate(
                ['order_id' => $orderId, 'line_item_id' => $lineItemId ?: $sellerSku],
                [
                    'order_status' => $orderStatus,
                    'line_status' => $lineStatus,
                    'seller_sku' => $sellerSku,
                    'product_id' => $productId,
                    'sku_id' => $skuId,
                    'product_name' => $productName,
                    'quantity' => $qty,
                    'original_price' => $originalPrice > 0 ? $originalPrice : null,
                    'sale_price' => $salePrice > 0 ? $salePrice : null,
                    'seller_discount' => $sellerDiscount > 0 ? $sellerDiscount : null,
                    'platform_discount' => $platformDiscount > 0 ? $platformDiscount : null,
                    'currency' => $currency,
                    'order_amount' => $orderAmount,
                    'fulfillment_type' => $fulfillmentType,
                    'shipping_provider' => $shippingProvider,
                    'buyer_nickname' => $buyerNickname,
                    'order_created_at' => $orderCreatedAt,
                    'order_updated_at' => $orderUpdatedAt,
                    'raw_json' => json_encode($order),
                    'fetched_at' => now(),
                    'import_status' => 'ready',
                ]
            );
            $count++;
        }

        return $count;
    }
}
