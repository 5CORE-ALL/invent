<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\AliExpressApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AliexpressOrderSyncService
{
    /** AliExpress order list queries are safest in ~30-day windows. */
    private const DATE_CHUNK_DAYS = 30;

    /** Max history when fetching all orders (days = 0). */
    private const MAX_LOOKBACK_DAYS = 730;

    /**
     * Hard cutoff: never fetch/store/auto-import AE orders created before this date (America/Los_Angeles).
     * Older orders were already entered on Shopify manually.
     */
    public const MIN_ORDER_DATE = '2026-07-07';

    public function __construct(
        protected AliExpressApiService $aliExpressApi
    ) {}

    /**
     * Earliest allowed order create time (start of day, AE seller timezone).
     */
    public function minOrderDate(): Carbon
    {
        return Carbon::parse(self::MIN_ORDER_DATE, 'America/Los_Angeles')->startOfDay();
    }
    /**
     * Fetch orders from AliExpress and upsert into aliexpress_order_metrics.
     *
     * @param  int  $days  Number of days back, or 0 to fetch all available history (chunked).
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

        $pageSize = max(1, min(50, $pageSize));

        if ($days <= 0) {
            return $this->fetchAllOrders($pageSize);
        }

        $end = Carbon::now('America/Los_Angeles');
        $start = $end->copy()->subDays(max(1, $days));
        $min = $this->minOrderDate();
        if ($start->lt($min)) {
            $start = $min->copy();
        }
        if ($start->gt($end)) {
            return [
                'fetched' => 0,
                'stored' => 0,
                'message' => 'No orders to fetch after cutoff '.self::MIN_ORDER_DATE.'.',
            ];
        }

        $dateRange = [
            'create_date_start' => $start->format('Y-m-d H:i:s'),
            'create_date_end' => $end->format('Y-m-d H:i:s'),
        ];
        $result = $this->fetchOrdersInDateRange($dateRange, $pageSize);

        return [
            'fetched' => $result['fetched'],
            'stored' => $result['stored'],
            'message' => $result['error']
                ?? "Fetched {$result['fetched']} order(s), stored/updated {$result['stored']} line(s) from {$start->toDateString()} onward.",
        ];
    }

    /**
     * Fetch orders created on/after a specific date (inclusive) through now.
     *
     * @return array{fetched: int, stored: int, message: string}
     */
    public function fetchAndStoreFromDate(string $fromDate, int $pageSize = 50): array
    {
        if (empty($this->aliExpressApi->getAccessToken())) {
            return ['fetched' => 0, 'stored' => 0, 'message' => 'ALIEXPRESS_ACCESS_TOKEN missing.'];
        }

        if (! Schema::hasTable('aliexpress_order_metrics')) {
            return ['fetched' => 0, 'stored' => 0, 'message' => 'Run migrations for aliexpress_order_metrics.'];
        }

        try {
            $start = Carbon::parse($fromDate, 'America/Los_Angeles')->startOfDay();
        } catch (\Throwable $e) {
            return ['fetched' => 0, 'stored' => 0, 'message' => 'Invalid from_date. Use YYYY-MM-DD.'];
        }

        $min = $this->minOrderDate();
        if ($start->lt($min)) {
            $start = $min->copy();
        }

        $end = Carbon::now('America/Los_Angeles');
        if ($start->gt($end)) {
            return ['fetched' => 0, 'stored' => 0, 'message' => 'from_date cannot be in the future.'];
        }

        $pageSize = max(1, min(50, $pageSize));
        $fetched = 0;
        $stored = 0;
        $chunks = 0;
        $chunkEnd = $end->copy();

        while ($chunkEnd->gt($start)) {
            $chunkStart = $chunkEnd->copy()->subDays(self::DATE_CHUNK_DAYS);
            if ($chunkStart->lt($start)) {
                $chunkStart = $start->copy();
            }

            $dateRange = [
                'create_date_start' => $chunkStart->format('Y-m-d H:i:s'),
                'create_date_end' => $chunkEnd->format('Y-m-d H:i:s'),
            ];

            $result = $this->fetchOrdersInDateRange($dateRange, $pageSize);
            $fetched += $result['fetched'];
            $stored += $result['stored'];
            $chunks++;

            if ($result['error'] !== null) {
                return [
                    'fetched' => $fetched,
                    'stored' => $stored,
                    'message' => $result['error']." (partial: {$fetched} order(s) fetched from {$fromDate}).",
                ];
            }

            $chunkEnd = $chunkStart->copy()->subSecond();
            usleep(200000);
        }

        return [
            'fetched' => $fetched,
            'stored' => $stored,
            'message' => "Fetched {$fetched} order(s), stored/updated {$stored} line(s) from {$start->toDateString()} onward ({$chunks} chunk(s)).",
        ];
    }

    /**
     * Walk back in date chunks to fetch full order history.
     *
     * @return array{fetched: int, stored: int, message: string}
     */
    protected function fetchAllOrders(int $pageSize): array
    {
        $fetched = 0;
        $stored = 0;
        $chunks = 0;

        $chunkEnd = Carbon::now('America/Los_Angeles');
        $lookbackStart = $this->minOrderDate();

        while ($chunkEnd->gt($lookbackStart)) {
            $chunkStart = $chunkEnd->copy()->subDays(self::DATE_CHUNK_DAYS);
            if ($chunkStart->lt($lookbackStart)) {
                $chunkStart = $lookbackStart->copy();
            }

            $dateRange = [
                'create_date_start' => $chunkStart->format('Y-m-d H:i:s'),
                'create_date_end' => $chunkEnd->format('Y-m-d H:i:s'),
            ];

            $result = $this->fetchOrdersInDateRange($dateRange, $pageSize);
            $fetched += $result['fetched'];
            $stored += $result['stored'];
            $chunks++;

            if ($result['error'] !== null) {
                return [
                    'fetched' => $fetched,
                    'stored' => $stored,
                    'message' => $result['error']." (partial: {$fetched} order(s) fetched).",
                ];
            }

            $chunkEnd = $chunkStart->copy()->subSecond();
            usleep(200000);
        }

        return [
            'fetched' => $fetched,
            'stored' => $stored,
            'message' => "Fetched {$fetched} order(s), stored/updated {$stored} line(s) across {$chunks} date chunk(s) from ".self::MIN_ORDER_DATE.' onward.',
        ];
    }

    /**
     * @param  array{create_date_start: string, create_date_end: string}  $dateRange
     * @return array{fetched: int, stored: int, error: ?string}
     */
    protected function fetchOrdersInDateRange(array $dateRange, int $pageSize): array
    {
        $page = 1;
        $fetched = 0;
        $stored = 0;
        $totalPages = null;

        while (true) {
            $result = $this->aliExpressApi->getOrders($page, $pageSize, $dateRange);

            if (empty($result['success'])) {
                return [
                    'fetched' => $fetched,
                    'stored' => $stored,
                    'error' => $result['message'] ?? 'Order fetch failed on page '.$page,
                ];
            }

            $parsed = $result['data'] ?? [];
            $orders = $parsed['orders'] ?? [];

            if ($orders === []) {
                break;
            }

            if ($totalPages === null && isset($parsed['total_page'])) {
                $totalPages = max(1, (int) $parsed['total_page']);
            }

            foreach ($orders as $order) {
                if (! is_array($order)) {
                    continue;
                }
                $fetched++;
                $stored += $this->storeOrder($order);
            }

            if (count($orders) < $pageSize) {
                break;
            }

            if ($totalPages !== null && $page >= $totalPages) {
                break;
            }

            $page++;
            usleep(150000);
        }

        Log::info('[AliExpressOrderSync] Date range complete', [
            'start' => $dateRange['create_date_start'] ?? null,
            'end' => $dateRange['create_date_end'] ?? null,
            'fetched' => $fetched,
            'pages' => $page,
        ]);

        return [
            'fetched' => $fetched,
            'stored' => $stored,
            'error' => null,
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
        if ($orderDate) {
            try {
                $parsed = Carbon::parse($orderDate, 'America/Los_Angeles');
                if ($parsed->lt($this->minOrderDate())) {
                    return 0;
                }
            } catch (\Throwable $e) {
                // Continue and store if date cannot be parsed.
            }
        }

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
            ->where('order_date', '>=', self::MIN_ORDER_DATE.' 00:00:00')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereNotIn('import_status', [
                        'imported',
                        'pending_shopify',
                        'queued',
                        'skipped_pre_july7',
                    ]);
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
