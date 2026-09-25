<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportB5cB2bOrderToShopify;
use App\Models\B5cB2bOrder;
use App\Models\MarketplaceSyncSettings;
use App\Services\Business5CoreB2bApiService;
use App\Services\ShopifyB2BStoreOrderIngestService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class B5cB2bOrderSyncService
{
    public function __construct(
        protected Business5CoreB2bApiService $api,
        protected ShopifyB2BStoreOrderIngestService $dailyIngest
    ) {
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Business 5 Core B2B API is not configured.', 'upserted' => 0, 'pages' => 0];
        }
        if (! Schema::hasTable('b5c_b2b_orders')) {
            return ['success' => false, 'message' => 'b5c_b2b_orders table missing.', 'upserted' => 0, 'pages' => 0];
        }

        $from = Carbon::parse($fromDate)->startOfDay()->toDateString();

        try {
            $orders = $this->fetchOrdersSince($from);
        } catch (\Throwable $e) {
            Log::warning('B5C B2B order fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
            ];
        }

        $upserted = 0;
        $lines = 0;
        foreach ($orders as $order) {
            $id = (int) ($order['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $detail = $order;
            try {
                $detail = $this->api->fetchOrder($id);
            } catch (\Throwable) {
            }
            $row = B5cB2bOrder::query()->updateOrCreate(
                ['store_order_id' => $id],
                [
                    'status' => $detail['status'] ?? $order['status'] ?? null,
                    'customer_email' => $detail['customer_email'] ?? $order['customer_email'] ?? null,
                    'customer_name' => $detail['customer_name'] ?? $order['customer_name'] ?? null,
                    'currency' => $detail['currency'] ?? $order['currency'] ?? 'USD',
                    'total' => $detail['total'] ?? $order['total'] ?? null,
                    'tracking_reference' => $detail['tracking_reference'] ?? $order['tracking_reference'] ?? null,
                    'ordered_at' => $detail['created_at'] ?? $order['created_at'] ?? null,
                    'payload' => $detail,
                ]
            );
            $lines += $this->dailyIngest->flattenOrder($row);
            $upserted++;
        }

        $this->dailyIngest->refreshPeriodLabels();

        $queued = 0;
        $imported = 0;
        if ($import || MarketplaceShopifyImportQueue::shouldDispatchImports('b5cb2b')) {
            $inline = $this->importUnlinkedInline(25);
            $imported = (int) ($inline['imported'] ?? 0);
            $queued = $this->dispatchImportsForNewOrders();
        }

        $message = "Synced {$upserted} Business 5 Core B2B order(s), {$lines} sales line(s).";
        if ($imported > 0) {
            $message .= " Imported {$imported} to Shopify.";
        }
        if ($queued > 0) {
            $message .= " Queued {$queued} Shopify import(s).";
        }

        return [
            'success' => true,
            'message' => $message,
            'upserted' => $upserted,
            'pages' => 1,
            'fetched' => $upserted,
            'stored' => $upserted,
            'queued' => $queued,
        ];
    }

    /**
     * Queue Shopify creates for orders that still have no shopify_order_id.
     */
    public function dispatchImportsForNewOrders(bool $force = false): int
    {
        if (! $force && ! MarketplaceSyncSettings::canAutoImportToShopify('b5cb2b')) {
            return 0;
        }
        if (! Schema::hasTable('b5c_b2b_orders')) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('b5cb2b');
        $orders = B5cB2bOrder::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->orderByDesc('store_order_id')
            ->limit(200)
            ->get();

        $dispatched = 0;
        foreach ($orders as $order) {
            $status = strtolower(trim((string) ($order->status ?? '')));
            if (in_array($status, ['canceled', 'cancelled'], true)) {
                continue;
            }
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('b5cb2b', $order)) {
                continue;
            }

            $key = ImportB5cB2bOrderToShopify::dispatchKeyFor((int) $order->id);
            if (! Cache::add($key, 1, now()->addMinutes(30))) {
                continue;
            }

            try {
                ImportB5cB2bOrderToShopify::dispatch((int) $order->id);
                $dispatched++;
            } catch (\Throwable $e) {
                Cache::forget($key);
                Log::warning('B5cB2bOrderSyncService: could not queue Shopify import', [
                    'id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * Create Shopify orders in this request so the SKU is written even when the queue worker is behind.
     *
     * @return array{imported: int, failed: int, skipped: int, message: string}
     */
    public function importUnlinkedInline(int $limit = 8): array
    {
        if (! Schema::hasTable('b5c_b2b_orders')) {
            return ['imported' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'b5c_b2b_orders table missing.'];
        }

        $orders = B5cB2bOrder::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->orderByDesc('store_order_id')
            ->limit(max(1, $limit))
            ->get();

        $push = app(B5cB2bOrderPushService::class);
        $imported = 0;
        $failed = 0;
        $reasons = [];
        foreach ($orders as $order) {
            Cache::forget(ImportB5cB2bOrderToShopify::dispatchKeyFor((int) $order->id));
            $shopifyId = $push->importToShopify($order);
            if ($shopifyId) {
                $imported++;
                continue;
            }
            $failed++;
            $reasons[] = $order->channelOrderNumber().': '.($push->lastFailureReason ?: 'Shopify import failed');
        }

        $message = "Imported {$imported} Business 5 Core order(s) to Shopify.";
        if ($failed > 0) {
            $message .= ' Failed '.$failed.'. '.implode(' ', array_slice($reasons, 0, 3));
        }
        if ($imported === 0 && $failed === 0) {
            $message = 'No unlinked Business 5 Core orders to import.';
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'skipped' => 0,
            'message' => $message,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchOrdersSince(string $from): array
    {
        $orders = $this->api->fetchAllOrders(['since' => $from]);
        if ($orders !== []) {
            return $orders;
        }

        $all = $this->api->fetchAllOrders();
        if ($all === []) {
            return [];
        }

        $fromTs = Carbon::parse($from)->startOfDay();
        $filtered = [];
        foreach ($all as $order) {
            if (! is_array($order)) {
                continue;
            }
            $created = $order['created_at'] ?? $order['ordered_at'] ?? null;
            if ($created === null || $created === '') {
                $filtered[] = $order;
                continue;
            }
            try {
                if (Carbon::parse($created)->gte($fromTs)) {
                    $filtered[] = $order;
                }
            } catch (\Throwable) {
                $filtered[] = $order;
            }
        }

        // Store list endpoint may omit dates; keep the full catalog so sales is not empty.
        return $filtered !== [] ? $filtered : $all;
    }
}
