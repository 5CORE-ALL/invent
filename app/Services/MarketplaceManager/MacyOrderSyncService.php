<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportMacyOrderToShopify;
use App\Models\MarketplaceSyncSettings;
use App\Models\MacyOrderMetric;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Macy\'s orders into mirakl_daily_data for Marketplace Manager.
 */
class MacyOrderSyncService
{
    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        unset($fromDate);

        $result = $this->fetchAndStore(60);

        if ($import && MarketplaceSyncSettings::canAutoImportToShopify('macy')) {
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
        if (! Schema::hasTable('mirakl_daily_data')) {
            return [
                'success' => false,
                'message' => 'mirakl_daily_data table missing.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('macy')) {
            return [
                'success' => true,
                'message' => 'Order fetch disabled in settings.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $before = (int) MacyOrderMetric::query()->count();

        try {
            Artisan::call('mirakl:daily', [
                '--days' => max(1, $days > 0 ? $days : 60),
            ]);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            Log::error('MacyOrderSyncService: fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Macy\'s order fetch failed: '.$e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $after = (int) MacyOrderMetric::query()->count();
        $stored = max(0, $after - $before);

        return [
            'success' => true,
            'message' => "Synced Macy\'s orders ({$after} total, +{$stored} new).".($output !== '' ? ' '.$output : ''),
            'upserted' => $stored,
            'pages' => 1,
            'fetched' => $stored,
            'stored' => $stored,
        ];
    }

    public function dispatchImportsForNewOrders(): int
    {
        if (! MarketplaceSyncSettings::canAutoImportToShopify('macy')) {
            return 0;
        }

        if (! Schema::hasColumn('mirakl_daily_data', 'shopify_order_id')) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('macy');
        $queue = MarketplaceManagerRegistry::queueFor('macy');
        MarketplaceShopifyImportQueue::releaseStuckQueued(MacyOrderMetric::class, $queue);
        $query = MacyOrderMetric::query()
            ->whereNull('shopify_order_id')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', ['ready', 'import_failed', 'failed']);
            })
            ->when(Schema::hasColumn('mirakl_daily_data', 'order_created_at'), function ($q) {
                $q->where('order_created_at', '>=', now()->subDays(14));
            })
            ->orderByDesc('id')
            ->limit(50);

        $dispatched = 0;
        foreach ($query->get() as $row) {
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('macy', $row)) {
                continue;
            }
            $row->update(['import_status' => 'queued']);
            MarketplaceShopifyImportQueue::push(new ImportMacyOrderToShopify((int) $row->id), $queue);
            $dispatched++;
        }

        return $dispatched;
    }
}
