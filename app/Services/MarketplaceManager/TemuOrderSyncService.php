<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportTemuOrderToShopify;
use App\Models\MarketplaceSyncSettings;
use App\Models\TemuOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Temu orders into temu_orders for Marketplace Manager.
 *
 * Same duplicate-avoidance pattern as Reverb:
 * - Hard cutoff (MIN_ORDER_DATE) — older orders were already entered on Shopify manually
 * - Manual / scheduled fetch does NOT auto-push unless import is explicitly requested
 *   and Settings → auto_import_to_shopify is ON
 */
class TemuOrderSyncService
{
    /**
     * Hard cutoff: never fetch/store Temu orders created before this date (America/Los_Angeles).
     * Older orders were already entered on Shopify manually.
     */
    public const MIN_ORDER_DATE = '2026-07-07';

    /**
     * Auto-import to Shopify only for recent open orders (not the full July+ backlog).
     */
    public const AUTO_IMPORT_MAX_AGE_DAYS = 3;

    /**
     * Only these open statuses may auto-import. Prefer allowlist over skip-list so unknown
     * / null / shipped-like statuses never flood Shopify.
     *
     * @var list<string>
     */
    public const AUTO_IMPORT_ALLOWED_STATUSES = [
        'UN_SHIPPING',
        'PENDING',
    ];

    /** @var list<string> */
    public const SKIP_AUTO_IMPORT_STATUSES = [
        'DELIVERED',
        'PARTIALLY_DELIVERED',
        'CANCELLED',
        'CANCELED',
        'CLOSED',
        'SHIPPED',
        'PARTIALLY_SHIPPED',
    ];

    /**
     * Earliest allowed order create time (start of day, Temu reporting timezone).
     */
    public function minOrderDate(): Carbon
    {
        return Carbon::parse(self::MIN_ORDER_DATE, 'America/Los_Angeles')->startOfDay();
    }

    public function autoImportFromDate(): Carbon
    {
        $importFrom = Carbon::now('America/Los_Angeles')
            ->subDays(max(1, self::AUTO_IMPORT_MAX_AGE_DAYS))
            ->startOfDay();
        $min = $this->minOrderDate();

        return $importFrom->lt($min) ? $min->copy() : $importFrom;
    }

    public static function resolveOrderStatus(?object $row): string
    {
        return strtoupper(trim((string) (
            ($row->parent_order_status_text ?? null)
            ?: ($row->order_status_text ?? null)
            ?: ''
        )));
    }

    public static function isAllowedAutoImportStatus(string $status): bool
    {
        return $status !== '' && in_array($status, self::AUTO_IMPORT_ALLOWED_STATUSES, true);
    }

    /**
     * Final safety check used by the queue job (and dispatch) so already-queued jobs
     * cannot create Shopify orders for delivered / old backlog.
     */
    public function isEligibleForAutoImport(TemuOrder $order): bool
    {
        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return false;
        }

        $status = self::resolveOrderStatus($order);
        if (! self::isAllowedAutoImportStatus($status)) {
            return false;
        }

        $parentTime = trim((string) ($order->parent_order_time ?? ''));
        if ($parentTime === '') {
            return false;
        }

        try {
            $created = Carbon::parse($parentTime, 'America/Los_Angeles');
        } catch (\Throwable $e) {
            return false;
        }

        return $created->gte($this->autoImportFromDate());
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        $result = $this->fetchAndStoreFromDate($fromDate);

        if ($import && MarketplaceSyncSettings::canAutoImportToShopify('temu')) {
            $dispatched = $this->dispatchImportsForNewOrders();
            $result['message'] .= " Dispatched {$dispatched} import job(s).";
        }

        return $result;
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function fetchAndStoreFromDate(string $fromDate): array
    {
        try {
            $start = Carbon::parse($fromDate, 'America/Los_Angeles')->startOfDay();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Invalid from_date. Use YYYY-MM-DD.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $min = $this->minOrderDate();
        if ($start->lt($min)) {
            $start = $min->copy();
        }

        if ($start->gt(Carbon::now('America/Los_Angeles'))) {
            return [
                'success' => false,
                'message' => 'from_date cannot be in the future.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        return $this->runFetch(['--from' => $start->toDateString(), '--no-prune' => true]);
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function fetchAndStore(int $days = 60): array
    {
        if ($days <= 0) {
            return $this->fetchAndStoreFromDate(self::MIN_ORDER_DATE);
        }

        $end = Carbon::now('America/Los_Angeles');
        $start = $end->copy()->subDays(max(1, $days) - 1)->startOfDay();
        $min = $this->minOrderDate();
        if ($start->lt($min)) {
            $start = $min->copy();
        }
        if ($start->gt($end)) {
            return [
                'success' => true,
                'message' => 'No orders to fetch after cutoff '.self::MIN_ORDER_DATE.'.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        return $this->runFetch(['--from' => $start->toDateString(), '--no-prune' => true]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    protected function runFetch(array $params): array
    {
        if (! Schema::hasTable('temu_orders')) {
            return [
                'success' => false,
                'message' => 'temu_orders table missing.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('temu')) {
            return [
                'success' => true,
                'message' => 'Order fetch disabled in settings.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $before = (int) TemuOrder::query()->count();

        try {
            Artisan::call('app:fetch-temu-orders', $params);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            Log::error('TemuOrderSyncService: fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Temu order fetch failed: '.$e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $after = (int) TemuOrder::query()->count();
        $stored = max(0, $after - $before);
        $fromLabel = (string) ($params['--from'] ?? self::MIN_ORDER_DATE);

        return [
            'success' => true,
            'message' => "Synced Temu orders from {$fromLabel} onward ({$after} total, +{$stored} new).".($output !== '' ? ' '.$output : ''),
            'upserted' => $stored,
            'pages' => 1,
            'fetched' => $stored,
            'stored' => $stored,
        ];
    }

    public function dispatchImportsForNewOrders(): int
    {
        if (! MarketplaceSyncSettings::canAutoImportToShopify('temu')) {
            return 0;
        }

        if (! Schema::hasColumn('temu_orders', 'shopify_order_id')) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('temu');
        $this->markClosedOrdersSkippedForImport();

        // Only NEW open orders (last N days + allowlisted statuses).
        $importFrom = $this->autoImportFromDate();
        $allowedStatuses = self::AUTO_IMPORT_ALLOWED_STATUSES;
        $placeholders = implode(', ', array_fill(0, count($allowedStatuses), '?'));
        $queue = MarketplaceManagerRegistry::queueFor('temu');
        MarketplaceShopifyImportQueue::releaseStuckQueued(TemuOrder::class, $queue, function ($q) use ($importFrom) {
            $q->whereNotNull('parent_order_time')
                ->where('parent_order_time', '>=', $importFrom->format('Y-m-d H:i:s'));
        });

        $query = TemuOrder::query()
            ->whereNull('shopify_order_id')
            ->whereNotNull('parent_order_time')
            ->where('parent_order_time', '>=', $importFrom->format('Y-m-d H:i:s'))
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', ['ready', 'import_failed', 'failed']);
            })
            ->whereRaw(
                "UPPER(TRIM(COALESCE(NULLIF(parent_order_status_text, ''), order_status_text, ''))) IN ({$placeholders})",
                $allowedStatuses
            )
            ->orderByDesc('id')
            ->limit(300);

        $dispatched = 0;
        $seenParents = [];
        $maxDispatch = 40;
        foreach ($query->get() as $row) {
            if ($dispatched >= $maxDispatch) {
                break;
            }
            $parent = trim((string) ($row->parent_order_sn ?? ''));
            if ($parent === '' || isset($seenParents[$parent])) {
                continue;
            }
            if (! $this->isEligibleForAutoImport($row)) {
                TemuOrder::query()
                    ->where('parent_order_sn', $parent)
                    ->whereNull('shopify_order_id')
                    ->update(['import_status' => 'skipped_closed']);
                continue;
            }
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('temu', $row)) {
                continue;
            }
            $seenParents[$parent] = true;
            TemuOrder::query()
                ->where('parent_order_sn', $parent)
                ->whereNull('shopify_order_id')
                ->update(['import_status' => 'queued']);
            MarketplaceShopifyImportQueue::push(new ImportTemuOrderToShopify((int) $row->id), $queue);
            $dispatched++;
        }

        return $dispatched;
    }

    /**
     * Mark closed / stale Temu parents so they never enter the Shopify import queue again.
     */
    protected function markClosedOrdersSkippedForImport(): void
    {
        if (! Schema::hasColumn('temu_orders', 'import_status')) {
            return;
        }

        $allowedStatuses = self::AUTO_IMPORT_ALLOWED_STATUSES;
        $allowedPlaceholders = implode(', ', array_fill(0, count($allowedStatuses), '?'));
        $importFrom = $this->autoImportFromDate()->format('Y-m-d H:i:s');

        try {
            $base = TemuOrder::query()
                ->whereNull('shopify_order_id')
                ->where(function ($q) {
                    $q->whereNull('import_status')
                        ->orWhereNotIn('import_status', ['imported', 'skipped_pre_july7', 'skipped_closed']);
                });

            // Closed / shipped / delivered / unknown statuses.
            (clone $base)
                ->whereRaw(
                    "UPPER(TRIM(COALESCE(NULLIF(parent_order_status_text, ''), order_status_text, ''))) NOT IN ({$allowedPlaceholders})",
                    $allowedStatuses
                )
                ->update(['import_status' => 'skipped_closed']);

            // Older than auto-import window (even if still UN_SHIPPING).
            (clone $base)
                ->where(function ($q) use ($importFrom) {
                    $q->whereNull('parent_order_time')
                        ->orWhere('parent_order_time', '<', $importFrom);
                })
                ->update(['import_status' => 'skipped_closed']);
        } catch (\Throwable $e) {
            Log::warning('TemuOrderSyncService: markClosedOrdersSkippedForImport failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
