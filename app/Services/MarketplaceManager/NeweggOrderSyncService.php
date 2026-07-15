<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\NeweggOrderMetric;
use App\Services\NeweggApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Newegg orders into newegg_order_metrics (Shopify import queue).
 */
class NeweggOrderSyncService
{
    public function __construct(
        protected NeweggApiService $neweggApi
    ) {}

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        if (! $this->neweggApi->isConfigured()) {
            return ['success' => false, 'message' => 'Newegg API credentials missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! Schema::hasTable('newegg_order_metrics')) {
            return ['success' => false, 'message' => 'newegg_order_metrics table missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('newegg')) {
            return ['success' => true, 'message' => 'Order fetch disabled in settings.', 'upserted' => 0, 'pages' => 0];
        }

        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::now();
        $upserted = 0;
        $pages = 0;

        for ($page = 1; $page <= 50; $page++) {
            $res = $this->neweggApi->getOrders([
                'OrderDateFrom' => $from->format('Y-m-d H:i:s'),
                'OrderDateTo' => $to->format('Y-m-d H:i:s'),
            ], $page, 100);

            if (! empty($res['blocked_by_cloudflare'])) {
                return [
                    'success' => false,
                    'message' => 'Blocked by Cloudflare. Whitelist server IP in Newegg Seller Portal.',
                    'upserted' => $upserted,
                    'pages' => $pages,
                    'fetched' => $upserted,
                    'stored' => $upserted,
                ];
            }

            if (empty($res['ok']) && empty($res['json'])) {
                return [
                    'success' => $upserted > 0,
                    'message' => $res['error'] ?? ('Order fetch failed HTTP '.$res['status']),
                    'upserted' => $upserted,
                    'pages' => $pages,
                    'fetched' => $upserted,
                    'stored' => $upserted,
                ];
            }

            $pages++;
            $orders = $this->extractOrders($res['json'] ?? []);
            if ($orders === []) {
                break;
            }

            foreach ($orders as $order) {
                $upserted += $this->upsertOrder($order);
            }

            if (count($orders) < 100) {
                break;
            }
        }

        $autoImport = $import || (bool) (MarketplaceSyncSettings::getFor('newegg')['order']['auto_import_to_shopify'] ?? false);
        if ($autoImport) {
            $this->dispatchImportsForNewOrders();
        }

        return [
            'success' => true,
            'message' => "Synced {$upserted} Newegg order line(s) across {$pages} page(s).",
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
        $ids = NeweggOrderMetric::query()
            ->whereNull('shopify_order_id')
            ->where(function ($q) {
                $q->whereNull('import_status')->orWhereIn('import_status', ['ready', 'failed']);
            })
            ->limit(50)
            ->pluck('id');

        $n = 0;
        foreach ($ids as $id) {
            try {
                \App\Jobs\ImportNeweggOrderToShopify::dispatch((int) $id);
                $n++;
            } catch (\Throwable $e) {
                Log::warning('NeweggOrderSyncService: failed to queue import', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $n;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<array<string, mixed>>
     */
    protected function extractOrders(?array $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $list = data_get($json, 'ResponseBody.OrderInfoList')
            ?? data_get($json, 'OrderInfoList')
            ?? data_get($json, 'ResponseBody.OrderInfo')
            ?? [];

        if ($list === [] || $list === null) {
            return [];
        }

        // Single order object
        if (isset($list['OrderNumber']) || isset($list['SellerOrderNumber'])) {
            return [$list];
        }

        return array_values(array_filter($list, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function upsertOrder(array $order): int
    {
        $orderId = (string) ($order['OrderNumber'] ?? $order['SellerOrderNumber'] ?? '');
        if ($orderId === '') {
            return 0;
        }

        $orderDate = $order['OrderDate'] ?? $order['OrderDownloadedOn'] ?? null;
        $status = (string) ($order['OrderStatus'] ?? $order['OrderStatusDescription'] ?? '');
        $items = data_get($order, 'ItemInfoList') ?? data_get($order, 'ItemList') ?? [];
        if (isset($items['SellerPartNumber']) || isset($items['NeweggItemNumber'])) {
            $items = [$items];
        }
        if (! is_array($items) || $items === []) {
            NeweggOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => '__order__'],
                [
                    'order_number' => $orderId,
                    'order_date' => $orderDate ? Carbon::parse($orderDate) : null,
                    'status' => $status,
                    'quantity' => 1,
                    'raw_payload' => $order,
                    'import_status' => 'ready',
                ]
            );

            return 1;
        }

        $count = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['SellerPartNumber'] ?? $item['SellerPartNumber'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($item['NeweggItemNumber'] ?? '__unknown__'));
            }
            $qty = (int) ($item['OrderedQty'] ?? $item['Quantity'] ?? 1);
            $amount = isset($item['ExtendUnitPrice']) ? (float) $item['ExtendUnitPrice'] : null;

            NeweggOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => $sku],
                [
                    'order_number' => $orderId,
                    'order_date' => $orderDate ? Carbon::parse($orderDate) : null,
                    'status' => $status,
                    'product_id' => (string) ($item['NeweggItemNumber'] ?? ''),
                    'display_title' => (string) ($item['Description'] ?? $item['Title'] ?? ''),
                    'quantity' => max(1, $qty),
                    'amount' => $amount,
                    'raw_payload' => $order,
                    'import_status' => 'ready',
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function queueReadyImports(): void
    {
        $this->dispatchImportsForNewOrders();
    }
}
