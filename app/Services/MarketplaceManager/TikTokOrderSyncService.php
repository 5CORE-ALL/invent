<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportTikTokOrderToShopify;
use App\Models\MarketplaceSyncSettings;
use App\Models\TiktokOrder;
use App\Services\TikTokShopService;
use Carbon\Carbon;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class TikTokOrderSyncService
{
    use PreservesMarketplaceImportStatus;
    use ResolvesTikTokOrderRawJson;

    /** Auto-import from this date onward. Older rows were entered on Shopify manually. */
    public const MIN_ORDER_DATE = '2026-07-07';

    /** @var list<string> */
    public const AUTO_IMPORT_STATUSES = [
        'UNPAID',
        'AWAITING_SHIPMENT',
        'PARTIALLY_SHIPPING',
        'AWAITING_COLLECTION',
        'ON_HOLD',
        'IN_TRANSIT',
        'SHIPPED',
        'DELIVERED',
        'COMPLETED',
    ];

    public function __construct(
        protected TikTokShopService $tiktokApi
    ) {}

    public function autoImportFromDate(): Carbon
    {
        return Carbon::parse(self::MIN_ORDER_DATE, 'America/Los_Angeles')->startOfDay();
    }

    public static function normalizeOrderStatus(?string $status): string
    {
        return str_replace([' ', '-'], '_', strtoupper(trim((string) $status)));
    }

    public function isEligibleForAutoImport(TiktokOrder $order): bool
    {
        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return false;
        }

        $status = self::normalizeOrderStatus((string) ($order->order_status ?? ''));
        if ($status === '' || ! in_array($status, self::AUTO_IMPORT_STATUSES, true)) {
            return false;
        }

        $created = $order->order_created_at;
        if (! $created) {
            return false;
        }

        return $created->gte($this->autoImportFromDate());
    }

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

        $from = Carbon::parse($fromDate);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fromDate))) {
            $from = $from->startOfDay();
        }
        $createTimeGe = $from->timestamp;
        $createTimeLt = now()->timestamp;

        $orders = $this->tiktokApi->getAllOrders($createTimeGe, $createTimeLt);
        $orders = $this->enrichOrdersWithDetails($orders);

        $upserted = 0;
        foreach ($orders as $order) {
            $upserted += $this->upsertOrder($order);
        }

        $dispatched = $this->dispatchImportsForNewOrders();
        $message = "Synced {$upserted} TikTok Shop order line(s) from ".count($orders)." order(s).";
        if ($dispatched > 0) {
            $message .= " Pushed {$dispatched} order(s) to Shopify.";
        }

        return [
            'success' => true,
            'message' => $message,
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

        $this->releaseStuckQueuedImports();

        $cutoff = $this->autoImportFromDate();

        $orders = TiktokOrder::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->where('order_created_at', '>=', $cutoff)
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', MarketplaceShopifyImportQueue::DISPATCHABLE_IMPORT_STATUSES);
            })
            ->orderByDesc('order_created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $seenOrderIds = [];
        $dispatched = 0;

        foreach ($orders as $order) {
            $orderId = (string) $order->order_id;
            if ($orderId === '' || isset($seenOrderIds[$orderId])) {
                continue;
            }
            $seenOrderIds[$orderId] = true;

            $alreadyImported = TiktokOrder::query()
                ->where('order_id', $orderId)
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->value('shopify_order_id');
            if ($alreadyImported) {
                TiktokOrder::query()
                    ->where('order_id', $orderId)
                    ->update([
                        'shopify_order_id' => (string) $alreadyImported,
                        'import_status' => 'imported',
                    ]);
                continue;
            }

            if (! $this->isEligibleForAutoImport($order)) {
                continue;
            }

            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('tiktok', $order)) {
                continue;
            }

            try {
                $job = new ImportTikTokOrderToShopify((int) $order->id);
                (new UniqueLock(app('cache.store')))->release($job);
                Queue::connection('database')->pushOn(
                    MarketplaceManagerRegistry::queueFor('tiktok'),
                    $job
                );
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

    protected function releaseStuckQueuedImports(): void
    {
        MarketplaceShopifyImportQueue::prepareForDispatch(
            TiktokOrder::class,
            MarketplaceManagerRegistry::queueFor('tiktok'),
            function ($q) {
                $q->where('order_created_at', '>=', $this->autoImportFromDate());
            }
        );
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
            $existing = TiktokOrder::query()
                ->where('order_id', $orderId)
                ->where('line_item_id', '__order__')
                ->first();
            $existingRaw = $this->normalizeTikTokRawJson($existing->raw_json ?? null);
            TiktokOrder::updateOrCreate(
                ['order_id' => $orderId, 'line_item_id' => '__order__'],
                array_merge([
                    'order_status' => $orderStatus,
                    'order_amount' => $orderAmount,
                    'currency' => $currency,
                    'buyer_nickname' => $buyerNickname,
                    'fulfillment_type' => $fulfillmentType,
                    'shipping_provider' => $shippingProvider,
                    'order_created_at' => $orderCreatedAt,
                    'order_updated_at' => $orderUpdatedAt,
                    'raw_json' => $this->mergePreservedTikTokRecipientAddress($order, $existingRaw),
                    'fetched_at' => now(),
                ], $this->importStatusForUpsert($existing))
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

            $lineItemKey = $lineItemId ?: $sellerSku;
            $existing = TiktokOrder::query()
                ->where('order_id', $orderId)
                ->where('line_item_id', $lineItemKey)
                ->first();
            $existingRaw = $this->normalizeTikTokRawJson($existing->raw_json ?? null);
            TiktokOrder::updateOrCreate(
                ['order_id' => $orderId, 'line_item_id' => $lineItemKey],
                array_merge([
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
                    'raw_json' => $this->mergePreservedTikTokRecipientAddress($order, $existingRaw),
                    'fetched_at' => now(),
                ], $this->importStatusForUpsert($existing))
            );
            $count++;
        }

        return $count;
    }

    /**
     * Get Order List often masks recipient_address. Batch Get Order Detail so
     * Shopify imports have a real ship-to / customer address.
     *
     * @param  list<array<string, mixed>>  $orders
     * @return list<array<string, mixed>>
     */
    protected function enrichOrdersWithDetails(array $orders): array
    {
        $needIds = [];
        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }
            $id = trim((string) ($order['id'] ?? $order['order_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $mapped = $this->mapTikTokAddressToShopify($this->tikTokAddressFromRaw($order));
            if (! $this->tikTokShopifyAddressIsComplete($mapped)) {
                $needIds[] = $id;
            }
        }
        $needIds = array_values(array_unique($needIds));
        if ($needIds === []) {
            return $orders;
        }

        $byId = [];
        foreach (array_chunk($needIds, 50) as $chunk) {
            $response = $this->tiktokApi->getOrderDetails($chunk);
            $details = $response['orders'] ?? $response['data']['orders'] ?? [];
            if (! is_array($details)) {
                $details = [];
            }
            foreach ($details as $detail) {
                if (! is_array($detail)) {
                    continue;
                }
                $id = trim((string) ($detail['id'] ?? $detail['order_id'] ?? ''));
                if ($id !== '') {
                    $byId[$id] = $detail;
                }
            }
            usleep(150000);
        }

        foreach ($orders as $i => $order) {
            if (! is_array($order)) {
                continue;
            }
            $id = trim((string) ($order['id'] ?? $order['order_id'] ?? ''));
            if ($id === '' || ! isset($byId[$id])) {
                continue;
            }
            $detail = $byId[$id];
            $merged = array_replace_recursive($order, $detail);
            if (is_array($detail['recipient_address'] ?? null) && $detail['recipient_address'] !== []) {
                $merged['recipient_address'] = $detail['recipient_address'];
            }
            $orders[$i] = $merged;
        }

        return $orders;
    }
}
