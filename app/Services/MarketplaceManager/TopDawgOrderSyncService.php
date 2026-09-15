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
    /** Calendar days back from today (PST) that may be created in Shopify. */
    public const SHOPIFY_IMPORT_LOOKBACK_DAYS = 2;

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
            $imported = $this->importUnpushedInline(25);
            $message = "Synced {$upserted} TopDawg order line(s). Dispatched {$dispatched} Shopify import job(s). Imported {$imported} inline.";
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
    public function fetchAndStore(int $days = self::SHOPIFY_IMPORT_LOOKBACK_DAYS): array
    {
        $from = Carbon::now('America/Los_Angeles')->subDays(max(0, $days))->toDateString();

        return $this->sync($from, false);
    }

    public function dispatchImportsForNewOrders(): int
    {
        $since = self::shopifyImportCutoffDate();
        $this->reopenRecentSkippedImports($since);

        return MarketplaceShopifyImportQueue::dispatchLatestUnpushed(
            'topdawg',
            TopDawgOrderMetric::class,
            static fn (int $id) => new ImportTopDawgOrderToShopify($id),
            'order_id',
            function ($q) use ($since) {
                $q->where('order_date', '>=', $since->toDateString());
            }
        );
    }

    /**
     * Create Shopify copies now (do not wait for mm-topdawg). Volume is small.
     */
    public function importUnpushedInline(int $limit = 25): int
    {
        if (! MarketplaceSyncSettings::canAutoImportToShopify('topdawg')) {
            return 0;
        }

        $limit = max(1, min(40, $limit));
        $since = self::shopifyImportCutoffDate();
        $this->reopenRecentSkippedImports($since);

        $orders = TopDawgOrderMetric::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->where('order_date', '>=', $since->toDateString())
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', array_merge(
                        MarketplaceShopifyImportQueue::DISPATCHABLE_IMPORT_STATUSES,
                        ['queued', 'skipped_old']
                    ));
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit($limit * 4)
            ->get();

        $push = app(TopDawgOrderPushService::class);
        $imported = 0;
        $seen = [];
        foreach ($orders as $order) {
            $orderId = trim((string) ($order->order_id ?? ''));
            if ($orderId === '' || isset($seen[$orderId])) {
                continue;
            }
            $seen[$orderId] = true;
            if (! $push->isWithinShopifyImportWindow($order)) {
                $order->update(['import_status' => 'skipped_old']);

                continue;
            }
            try {
                $id = $push->importToShopify($order);
                if ($id) {
                    $imported++;
                }
            } catch (\Throwable $e) {
                Log::warning('TopDawgOrderSyncService: inline import failed', [
                    'id' => $order->id,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
            if ($imported >= $limit) {
                break;
            }
        }

        return $imported;
    }

    public function shopifyImportCutoff(): Carbon
    {
        return self::shopifyImportCutoffDate();
    }

    public static function shopifyImportCutoffDate(): Carbon
    {
        return Carbon::now('America/Los_Angeles')
            ->subDays(self::SHOPIFY_IMPORT_LOOKBACK_DAYS)
            ->startOfDay();
    }

    public static function orderDateIsWithinShopifyWindow(mixed $raw): bool
    {
        $date = self::normalizeOrderDate($raw);
        if ($date === null) {
            return false;
        }

        return $date->toDateString() >= self::shopifyImportCutoffDate()->toDateString();
    }

    public static function parseOrderDate(array $order): ?Carbon
    {
        foreach ([
            'order_date', 'orderDate', 'created_at', 'createdAt',
            'gmt_create', 'create_time', 'created_time', 'order_created_at',
            'date_created', 'placed_at', 'purchased_at', 'timestamp',
        ] as $key) {
            if (! array_key_exists($key, $order) || $order[$key] === null || $order[$key] === '') {
                continue;
            }
            $parsed = self::normalizeOrderDate($order[$key]);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    public static function normalizeOrderDate(mixed $raw): ?Carbon
    {
        if ($raw instanceof Carbon) {
            return $raw->copy()->timezone('America/Los_Angeles')->startOfDay();
        }
        if ($raw instanceof \DateTimeInterface) {
            return Carbon::instance($raw)->timezone('America/Los_Angeles')->startOfDay();
        }
        if (is_numeric($raw) && (int) $raw > 1_000_000_000) {
            try {
                return Carbon::createFromTimestamp((int) $raw, 'America/Los_Angeles')->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
                return Carbon::parse($raw, 'America/Los_Angeles')->startOfDay();
            }

            return Carbon::parse($raw)->timezone('America/Los_Angeles')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function reopenRecentSkippedImports(Carbon $since): void
    {
        TopDawgOrderMetric::query()
            ->where('import_status', 'skipped_old')
            ->where('order_date', '>=', $since->toDateString())
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->update(['import_status' => 'ready']);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public function upsertSingleOrder(array $order): int
    {
        return $this->upsertOrder($order, $this->shopifyImportCutoff());
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function upsertOrder(array $order, Carbon $from): int
    {
        $orderNumber = trim((string) (
            $order['order_number']
            ?? $order['orderNumber']
            ?? $order['order_id']
            ?? $order['id']
            ?? ''
        ));
        if ($orderNumber === '') {
            return 0;
        }

        $orderDate = self::parseOrderDate($order);
        if ($orderDate && $orderDate->lt($from->copy()->startOfDay())) {
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
                    'order_date' => $orderDate?->toDateString(),
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
        $items = $order['items'] ?? $order['line_items'] ?? $order['products'] ?? $order['transactions'] ?? null;
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
