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
     * Hard cutoff: never fetch/store/auto-import Temu orders created before this date (America/Los_Angeles).
     * Older orders were already entered on Shopify manually.
     */
    public const MIN_ORDER_DATE = '2026-07-07';

    /**
     * Earliest allowed order create time (start of day, Temu reporting timezone).
     */
    public function minOrderDate(): Carbon
    {
        return Carbon::parse(self::MIN_ORDER_DATE, 'America/Los_Angeles')->startOfDay();
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
        $query = TemuOrder::query()
            ->whereNull('shopify_order_id')
            ->where('parent_order_time', '>=', self::MIN_ORDER_DATE.' 00:00:00')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereNotIn('import_status', ['queued', 'imported', 'skipped_pre_july7']);
            })
            ->orderBy('id');

        $dispatched = 0;
        $seenParents = [];
        $query->chunkById(50, function ($rows) use (&$dispatched, $paidOnly, &$seenParents) {
            foreach ($rows as $row) {
                $parent = trim((string) ($row->parent_order_sn ?? ''));
                if ($parent === '' || isset($seenParents[$parent])) {
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
                ImportTemuOrderToShopify::dispatch((int) $row->id);
                $dispatched++;
            }
        });

        return $dispatched;
    }
}
