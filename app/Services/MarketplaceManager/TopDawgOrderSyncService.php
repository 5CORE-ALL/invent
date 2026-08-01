<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportTopDawgOrderToShopify;
use App\Models\MarketplaceSyncSettings;
use App\Models\TopDawgOrderMetric;
use App\Services\TopDawgApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch TopDawg orders into topdawg_order_metrics for Marketplace Manager.
 */
class TopDawgOrderSyncService
{
    public function __construct(
        protected TopDawgApiService $topdawgApi
    ) {}

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        if (! $this->topdawgApi->isConfigured()) {
            return ['success' => false, 'message' => 'TopDawg API credentials missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! Schema::hasTable('topdawg_order_metrics')) {
            return ['success' => false, 'message' => 'topdawg_order_metrics table missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('topdawg')) {
            return ['success' => true, 'message' => 'Order fetch disabled in settings.', 'upserted' => 0, 'pages' => 0];
        }

        $from = Carbon::parse($fromDate)->startOfDay();

        try {
            $result = $this->topdawgApi->fetchOrders($from->toIso8601String());
            $orders = $result['data'] ?? [];
        } catch (\Throwable $e) {
            Log::error('TopDawgOrderSyncService: fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'TopDawg order fetch failed: '.$e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
            ];
        }

        $upserted = 0;
        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }
            $upserted += $this->upsertOrder($order, $from);
        }

        if ($import && MarketplaceSyncSettings::canAutoImportToShopify('topdawg')) {
            $dispatched = $this->dispatchImportsForNewOrders();
            $message = "Synced {$upserted} TopDawg order line(s). Dispatched {$dispatched} Shopify import job(s).";
        } else {
            $message = "Synced {$upserted} TopDawg order line(s).";
        }

        return [
            'success' => true,
            'message' => $message,
            'upserted' => $upserted,
            'pages' => 1,
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
        if (! MarketplaceSyncSettings::canAutoImportToShopify('topdawg')) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('topdawg');
        $query = TopDawgOrderMetric::query()
            ->whereNull('shopify_order_id')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereNotIn('import_status', ['queued', 'imported']);
            })
            ->orderBy('id');

        $dispatched = 0;
        $query->chunkById(50, function ($rows) use (&$dispatched, $paidOnly) {
            foreach ($rows as $row) {
                if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('topdawg', $row)) {
                    continue;
                }
                $row->update(['import_status' => 'queued']);
                ImportTopDawgOrderToShopify::dispatch((int) $row->id);
                $dispatched++;
            }
        });

        return $dispatched;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function upsertOrder(array $order, Carbon $from): int
    {
        $orderNumber = trim((string) (
            $order['order_number']
            ?? $order['orderNumber']
            ?? $order['id']
            ?? ''
        ));
        if ($orderNumber === '') {
            return 0;
        }

        $orderDateRaw = $order['order_date'] ?? $order['orderDate'] ?? $order['created_at'] ?? null;
        $orderDate = null;
        if ($orderDateRaw) {
            try {
                $orderDate = Carbon::parse($orderDateRaw);
            } catch (\Throwable) {
                $orderDate = null;
            }
        }
        if ($orderDate && $orderDate->lt($from)) {
            return 0;
        }

        $lines = $this->extractLines($order);
        $count = 0;
        foreach ($lines as $line) {
            $sku = trim((string) ($line['sku'] ?? ''));
            TopDawgOrderMetric::updateOrCreate(
                [
                    'order_number' => $orderNumber,
                    'sku' => $sku !== '' ? $sku : '__order__',
                ],
                [
                    'order_id' => $orderNumber,
                    'order_date' => $orderDate?->toDateString() ?? ($orderDateRaw ? (string) $orderDateRaw : null),
                    'order_paid_at' => $this->parsePaidAt($order),
                    'status' => $order['status'] ?? $order['order_status'] ?? null,
                    'amount' => $line['amount'] ?? $order['amount'] ?? $order['total'] ?? null,
                    'display_sku' => $line['display_sku'] ?? $sku,
                    'quantity' => (int) ($line['quantity'] ?? 1),
                    'product_id' => $line['product_id'] ?? null,
                    'display_title' => $line['title'] ?? null,
                    'raw_payload' => $order,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<array<string, mixed>>
     */
    protected function extractLines(array $order): array
    {
        $items = $order['items'] ?? $order['line_items'] ?? $order['products'] ?? null;
        if (is_array($items) && $items !== []) {
            $lines = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $sku = trim((string) ($item['product_code'] ?? $item['sku'] ?? $item['seller_sku'] ?? ''));
                $qty = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
                $amount = $item['amount'] ?? $item['price'] ?? $item['total'] ?? null;
                $lines[] = [
                    'sku' => $sku,
                    'display_sku' => $item['display_sku'] ?? $sku,
                    'quantity' => $qty >= 1 ? $qty : 1,
                    'amount' => $amount,
                    'product_id' => $item['product_id'] ?? $item['tdid'] ?? $item['id'] ?? null,
                    'title' => $item['product_name'] ?? $item['title'] ?? null,
                ];
            }
            if ($lines !== []) {
                return $lines;
            }
        }

        $sku = trim((string) ($order['product_code'] ?? $order['sku'] ?? $order['display_sku'] ?? ''));

        return [[
            'sku' => $sku,
            'display_sku' => $order['display_sku'] ?? $sku,
            'quantity' => (int) ($order['quantity'] ?? $order['qty'] ?? 1),
            'amount' => $order['amount'] ?? $order['total'] ?? null,
            'product_id' => $order['product_id'] ?? $order['tdid'] ?? null,
            'title' => $order['product_name'] ?? $order['title'] ?? null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function parsePaidAt(array $order): ?Carbon
    {
        $raw = $order['order_paid_at'] ?? $order['paid_at'] ?? $order['payment_date'] ?? null;
        if (! $raw) {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
