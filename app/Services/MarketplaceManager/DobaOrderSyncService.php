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
        Log::info('DobaOrderSyncService: Shopify import dispatcher skipped until DobaOrderPushService is implemented.');

        return 0;
    }
}
