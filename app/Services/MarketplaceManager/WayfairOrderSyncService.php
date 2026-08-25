<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportWayfairOrderToShopify;
use App\Models\MarketplaceSyncSettings;
use App\Models\WayfairDailyData;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Wayfair POs into wayfair_daily_data for Marketplace Manager.
 */
class WayfairOrderSyncService
{
    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        unset($fromDate);

        $result = $this->fetchAndStore(60);

        if ($import && MarketplaceSyncSettings::canAutoImportToShopify('wayfair')) {
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
        unset($fromDate);

        return $this->fetchAndStore(60);
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function fetchAndStore(int $days = 60): array
    {
        if (! Schema::hasTable('wayfair_daily_data')) {
            return [
                'success' => false,
                'message' => 'wayfair_daily_data table missing.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('wayfair')) {
            return [
                'success' => true,
                'message' => 'Order fetch disabled in settings.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $before = (int) WayfairDailyData::query()->count();
        $daysArg = $days <= 0 ? 60 : max(1, min(730, $days));

        try {
            Artisan::call('wayfair:daily', ['--days' => $daysArg]);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            Log::error('WayfairOrderSyncService: fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Wayfair order fetch failed: '.$e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $after = (int) WayfairDailyData::query()->count();
        $stored = max(0, $after - $before);

        return [
            'success' => true,
            'message' => "Synced Wayfair orders ({$after} total, +{$stored} new).".($output !== '' ? ' '.$output : ''),
            'upserted' => $stored,
            'pages'  => 1,
            'fetched' => $stored,
            'stored' => $stored,
        ];
    }

    public function dispatchImportsForNewOrders(): int
    {
        if (! MarketplaceSyncSettings::canAutoImportToShopify('wayfair')) {
            return 0;
        }

        if (! Schema::hasColumn('wayfair_daily_data', 'shopify_order_id')) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('wayfair');
        $queue = MarketplaceManagerRegistry::queueFor('wayfair');
        MarketplaceShopifyImportQueue::releaseStuckQueued(WayfairDailyData::class, $queue);
        $query = WayfairDailyData::query()
            ->whereNull('shopify_order_id')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', ['ready', 'import_failed', 'failed']);
            })
            ->when(Schema::hasColumn('wayfair_daily_data', 'po_date'), function ($q) {
                $q->where('po_date', '>=', now()->subDays(14)->toDateString());
            })
            ->orderByDesc('id')
            ->limit(50);

        $dispatched = 0;
        foreach ($query->get() as $row) {
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('wayfair', $row)) {
                continue;
            }
            $row->update(['import_status' => 'queued']);
            MarketplaceShopifyImportQueue::push(new ImportWayfairOrderToShopify((int) $row->id), $queue);
            $dispatched++;
        }

        return $dispatched;
    }
}
