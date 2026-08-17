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

        for ($page = 1; $page <= 50; $page++) {
            $res = $this->faireApi->getOrders([
                'limit' => 50, // Faire requires 10–50
                'page' => $page,
                'created_at_min' => $from->toIso8601String(),
            ]);

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
            $orders = $detailService->extractOrders($res['json'] ?? []);
            if ($orders === []) {
                break;
            }

            foreach ($orders as $order) {
                $upserted += $this->upsertOrder($order);
            }

            if (count($orders) < 50) {
                break;
            }
        }

        if ($import) {
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
        $settings = MarketplaceSyncSettings::getFor('faire');
        if (! ($settings['order']['auto_import_to_shopify'] ?? false)) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('faire', $settings);

        $orders = FaireOrderMetric::query()
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
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('faire', $order)) {
                continue;
            }

            try {
                \App\Jobs\ImportFaireOrderToShopify::dispatch((int) $order->id);
                $order->update(['import_status' => 'queued']);
                $dispatched++;
            } catch (\Throwable $e) {
                Log::warning('FaireOrderSyncService: failed to queue import', [
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
            $sku = trim((string) ($item['sku'] ?? data_get($item, 'product_variant.sku') ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($item['id'] ?? '__unknown__'));
            }
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
}
