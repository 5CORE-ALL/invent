<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportDobaOrderToShopify;
use App\Models\DobaDailyData;
use App\Models\MarketplaceSyncSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Doba orders into doba_daily_data for Marketplace Manager.
 */
class DobaOrderSyncService
{
    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        unset($fromDate);

        $result = $this->fetchAndStore(60);

        if ($import && MarketplaceSyncSettings::canAutoImportToShopify('doba')) {
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
        if (! Schema::hasTable('doba_daily_data')) {
            return [
                'success' => false,
                'message' => 'doba_daily_data table missing.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('doba')) {
            return [
                'success' => true,
                'message' => 'Order fetch disabled in settings.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $before = (int) DobaDailyData::query()->count();
        $daysArg = $days <= 0 ? 60 : max(1, min(730, $days));

        try {
            Artisan::call('doba:daily', ['--days' => $daysArg]);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            Log::error('DobaOrderSyncService: fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Doba order fetch failed: '.$e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $after = (int) DobaDailyData::query()->count();
        $stored = max(0, $after - $before);

        return [
            'success' => true,
            'message' => "Synced Doba orders ({$after} total, +{$stored} new).".($output !== '' ? ' '.$output : ''),
            'upserted' => $stored,
            'pages' => 1,
            'fetched' => $stored,
            'stored' => $stored,
        ];
    }

    public function dispatchImportsForNewOrders(): int
    {
        if (! MarketplaceSyncSettings::canAutoImportToShopify('doba')) {
            return 0;
        }

        if (! Schema::hasColumn('doba_daily_data', 'shopify_order_id')) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('doba');
        $queue = MarketplaceManagerRegistry::queueFor('doba');
        MarketplaceShopifyImportQueue::prepareForDispatch(DobaDailyData::class, $queue);
        $query = DobaDailyData::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', MarketplaceShopifyImportQueue::DISPATCHABLE_IMPORT_STATUSES);
            })
            ->when(Schema::hasColumn('doba_daily_data', 'order_time'), function ($q) {
                $q->where('order_time', '>=', MarketplaceShopifyImportQueue::defaultImportCutoff());
            })
            ->orderByDesc('id')
            ->limit(200);

        $dispatched = 0;
        foreach ($query->get() as $row) {
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('doba', $row)) {
                continue;
            }
            $row->update(['import_status' => 'queued']);
            MarketplaceShopifyImportQueue::push(new ImportDobaOrderToShopify((int) $row->id), $queue);
            $dispatched++;
        }

        return $dispatched;
    }
}
