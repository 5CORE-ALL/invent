<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\AliExpressApiService;
use App\Services\ShopifyStoreSelector;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AliexpressOrderSyncService
{
    public function __construct(
        protected AliExpressApiService $aliExpressApi
    ) {}

    /**
     * @return array{fetched: int, stored: int, message: string}
     */
    public function fetchAndStore(int $days = 7, int $pageSize = 50): array
    {
        if (empty($this->aliExpressApi->getAccessToken())) {
            return ['fetched' => 0, 'stored' => 0, 'message' => 'ALIEXPRESS_ACCESS_TOKEN missing.'];
        }

        if (! Schema::hasTable('aliexpress_order_metrics')) {
            return ['fetched' => 0, 'stored' => 0, 'message' => 'Run migrations for aliexpress_order_metrics.'];
        }

        $dateRange = $this->aliExpressApi->buildOrderDateRange($days);
        $page = 1;
        $fetched = 0;
        $stored = 0;

        while (true) {
            $result = $this->aliExpressApi->getOrders($page, $pageSize, $dateRange);
            if (empty($result['success'])) {
                return [
                    'fetched' => $fetched,
                    'stored' => $stored,
                    'message' => $result['message'] ?? 'Order fetch failed on page '.$page,
                ];
            }

            $orders = $result['data']['orders'] ?? [];
            if ($orders === []) {
                break;
            }

            foreach ($orders as $order) {
                if (! is_array($order)) {
                    continue;
                }
                $fetched++;
                $stored += $this->storeOrder($order);
            }

            $page++;
            usleep(150000);
        }

        return [
            'fetched' => $fetched,
            'stored' => $stored,
            'message' => "Fetched {$fetched} order(s), stored/updated {$stored} line(s).",
        ];
    }

    /**
     * @return int Number of lines stored
     */
    protected function storeOrder(array $order): int
    {
        $orderId = (string) ($order['order_id'] ?? $order['id'] ?? '');
        if ($orderId === '') {
            return 0;
        }

        $orderDate = $order['gmt_create'] ?? $order['create_time'] ?? null;
        $status = (string) ($order['order_status'] ?? $order['status'] ?? '');
        $lines = $this->aliExpressApi->extractOrderProductLines($order);
        $count = 0;

        if ($lines === []) {
            AliexpressOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => '__order__'],
                [
                    'order_number' => $orderId,
                    'order_date' => $orderDate ? Carbon::parse($orderDate) : null,
                    'status' => $status,
                    'quantity' => 1,
                    'amount' => $this->extractOrderAmount($order),
                    'raw_payload' => $order,
                ]
            );

            return 1;
        }

        foreach ($lines as $line) {
            $sku = trim((string) ($line['sku_code'] ?? $line['sku'] ?? ''));
            if ($sku === '') {
                $sku = (string) ($line['product_id'] ?? '__unknown__');
            }

            AliexpressOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => $sku],
                [
                    'order_number' => $orderId,
                    'order_date' => $orderDate ? Carbon::parse($orderDate) : null,
                    'status' => $status,
                    'product_id' => (string) ($line['product_id'] ?? ''),
                    'display_title' => (string) ($line['product_name'] ?? $line['title'] ?? ''),
                    'quantity' => max(1, (int) ($line['quantity'] ?? $line['product_count'] ?? 1)),
                    'amount' => $this->extractLineAmount($line, $order),
                    'raw_payload' => ['order' => $order, 'line' => $line],
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function extractOrderAmount(array $order): ?float
    {
        $amount = $order['order_amount']['amount'] ?? $order['total_amount'] ?? $order['pay_amount'] ?? null;

        return is_numeric($amount) ? (float) $amount : null;
    }

    protected function extractLineAmount(array $line, array $order): ?float
    {
        $amount = $line['product_unit_price']['amount']
            ?? $line['product_unit_price']
            ?? $line['amount']
            ?? null;

        if (is_numeric($amount)) {
            return (float) $amount;
        }

        return $this->extractOrderAmount($order);
    }

    public function dispatchImportsForNewOrders(): int
    {
        $settings = MarketplaceSyncSettings::getFor('aliexpress');
        if (! ($settings['order']['auto_import_to_shopify'] ?? false)) {
            return 0;
        }

        $orders = AliexpressOrderMetric::query()
            ->whereNull('shopify_order_id')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereNotIn('import_status', ['imported', 'pending_shopify', 'import_failed']);
            })
            ->orderBy('id')
            ->limit(50)
            ->get();

        $dispatched = 0;
        foreach ($orders as $order) {
            \App\Jobs\ImportAliexpressOrderToShopify::dispatch($order->id);
            $order->update(['import_status' => 'queued']);
            $dispatched++;
        }

        return $dispatched;
    }
}
