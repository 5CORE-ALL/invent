<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\FaireOrderMetric;
use App\Services\FaireApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Faire orders into faire_order_metrics (Shopify import queue).
 */
class FaireOrderSyncService
{
    use PreservesMarketplaceImportStatus;

    public function __construct(
        protected FaireApiService $faireApi
    ) {}

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        if (! $this->faireApi->isConfigured()) {
            return ['success' => false, 'message' => 'Faire API credentials missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! Schema::hasTable('faire_order_metrics')) {
            return ['success' => false, 'message' => 'faire_order_metrics table missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('faire')) {
            return ['success' => true, 'message' => 'Order fetch disabled in settings.', 'upserted' => 0, 'pages' => 0];
        }

        $from = Carbon::parse($fromDate)->startOfDay();
        $upserted = 0;
        $pages = 0;
        $detailService = app(FaireOrderDetailService::class);
        $cursor = null;
        $pageNum = 1;

        for ($i = 1; $i <= 50; $i++) {
            $params = ['limit' => 50];
            if ($cursor !== null && $cursor !== '') {
                // Faire: cursor pages cannot also send date filters.
                $params['cursor'] = $cursor;
            } else {
                $params['updated_at_min'] = $from->toIso8601String();
                $params['page'] = $pageNum;
            }

            $res = $this->faireApi->getOrders($params);

            if (! empty($res['blocked_by_cloudflare'])) {
                return [
                    'success' => false,
                    'message' => 'Blocked by Cloudflare while fetching Faire orders.',
                    'upserted' => $upserted,
                    'pages' => $pages,
                    'fetched' => $upserted,
                    'stored' => $upserted,
                ];
            }

            if (empty($res['ok']) && empty($res['json'])) {
                return [
                    'success' => $upserted > 0,
                    'message' => $res['error'] ?? ('Order fetch failed HTTP '.($res['status'] ?? 0)),
                    'upserted' => $upserted,
                    'pages' => $pages,
                    'fetched' => $upserted,
                    'stored' => $upserted,
                ];
            }

            $pages++;
            $json = is_array($res['json'] ?? null) ? $res['json'] : [];
            $orders = $detailService->extractOrders($json);
            if ($orders === []) {
                break;
            }

            foreach ($orders as $order) {
                $upserted += $this->upsertOrder($order);
            }

            $nextCursor = $this->nextFaireCursor($json);
            if ($nextCursor !== null && $nextCursor !== '' && $nextCursor !== $cursor) {
                $cursor = $nextCursor;
                continue;
            }
            if (count($orders) >= 50 && ($cursor === null || $cursor === '')) {
                $pageNum++;
                continue;
            }
            break;
        }

        if ($import || MarketplaceShopifyImportQueue::shouldDispatchImports('faire')) {
            $this->dispatchImportsForNewOrders();
        }

        if (FaireOrderPushService::canAutoSyncAddress()) {
            try {
                \App\Jobs\SyncFaireAddressJob::dispatch(false, 25);
            } catch (\Throwable $e) {
                Log::warning('FaireOrderSyncService: could not queue address sync', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'message' => "Synced {$upserted} Faire order line(s) across {$pages} page(s).",
            'upserted' => $upserted,
            'pages' => $pages,
            'fetched' => $upserted,
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
        return MarketplaceShopifyImportQueue::dispatchLatestUnpushed(
            'faire',
            FaireOrderMetric::class,
            static fn (int $id) => new \App\Jobs\ImportFaireOrderToShopify($id)
        );
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function upsertOrder(array $order): int
    {
        $orderId = trim((string) ($order['id'] ?? ''));
        if ($orderId === '') {
            return 0;
        }

        $existingPayload = FaireOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->value('raw_payload');
        if (is_array($existingPayload)) {
            $order = app(FaireOrderDetailService::class)->mergePreservedAddress($existingPayload, $order);
        }

        $displayId = (string) ($order['display_id'] ?? $orderId);
        $orderDate = $order['created_at'] ?? $order['updated_at'] ?? null;
        $status = (string) ($order['state'] ?? $order['status'] ?? '');
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];

        if ($items === []) {
            $existing = FaireOrderMetric::query()
                ->where('order_id', $orderId)
                ->where('sku', '__order__')
                ->first();
            FaireOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => '__order__'],
                array_merge([
                    'order_number' => $displayId,
                    'order_date' => $orderDate ? Carbon::parse($orderDate) : null,
                    'status' => $status,
                    'quantity' => 1,
                    'raw_payload' => $order,
                ], $this->importStatusForUpsert($existing))
            );

            return 1;
        }

        $count = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = $this->skuFromFaireItem($item);
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $amountMinor = data_get($item, 'price.amount_minor')
                ?? data_get($item, 'total_price.amount_minor')
                ?? data_get($item, 'price_cents');
            $amount = is_numeric($amountMinor) ? round(((float) $amountMinor) / 100, 2) : null;

            FaireOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => $sku],
                array_merge([
                    'order_number' => $displayId,
                    'order_date' => $orderDate ? Carbon::parse($orderDate) : null,
                    'status' => $status,
                    'product_id' => (string) (
                        $item['product_id']
                        ?? data_get($item, 'product.id')
                        ?? data_get($item, 'product_variant.id')
                        ?? ''
                    ),
                    'display_title' => (string) (
                        $item['product_name']
                        ?? data_get($item, 'product.name')
                        ?? $item['name']
                        ?? $sku
                    ),
                    'quantity' => $qty,
                    'amount' => $amount,
                    'raw_payload' => $order,
                ], $this->importStatusForUpsert(
                    FaireOrderMetric::query()->where('order_id', $orderId)->where('sku', $sku)->first()
                ))
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function skuFromFaireItem(array $item): string
    {
        $candidates = [
            $item['sku'] ?? '',
            data_get($item, 'product_variant.sku'),
            data_get($item, 'variant.sku'),
            data_get($item, 'product.sku'),
            $item['seller_sku'] ?? '',
            $item['seller_part_number'] ?? '',
            data_get($item, 'product_variant.shop_sku'),
        ];
        foreach ($candidates as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                return $sku;
            }
        }

        return trim((string) ($item['id'] ?? '__unknown__')) ?: '__unknown__';
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function nextFaireCursor(array $json): ?string
    {
        foreach (['cursor', 'next_cursor', 'next_page'] as $key) {
            $value = trim((string) ($json[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        $page = $json['page'] ?? null;
        if (is_string($page) && $page !== '' && ! ctype_digit($page)) {
            return $page;
        }
        if (is_array($page)) {
            $value = trim((string) ($page['cursor'] ?? $page['next'] ?? $page['next_cursor'] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
