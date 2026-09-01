<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportPurchasingPowerOrderToShopify;
use App\Models\MarketplaceSyncSettings;
use App\Models\PurchasingPowerSale;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Purchasing Power orders into purchasing_power_sales for Marketplace Manager.
 */
class PurchasingPowerOrderSyncService
{
    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        unset($fromDate);

        $result = $this->fetchAndStore(60);

        if ($import && MarketplaceSyncSettings::canAutoImportToShopify('purchasingpower')) {
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
        if (! Schema::hasTable('purchasing_power_sales')) {
            return [
                'success' => false,
                'message' => 'purchasing_power_sales table missing.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('purchasingpower')) {
            return [
                'success' => true,
                'message' => 'Order fetch disabled in settings.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $before = (int) PurchasingPowerSale::query()->count();

        try {
            Artisan::call('purchasing-power:sync', [
                '--orders' => true,
                '--days' => max(1, $days),
            ]);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            Log::error('PurchasingPowerOrderSyncService: fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Purchasing Power order fetch failed: '.$e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $after = (int) PurchasingPowerSale::query()->count();
        $stored = max(0, $after - $before);

        return [
            'success' => true,
            'message' => "Synced Purchasing Power orders ({$after} total, +{$stored} new).".($output !== '' ? ' '.$output : ''),
            'upserted' => $stored,
            'pages' => 1,
            'fetched' => $stored,
            'stored' => $stored,
        ];
    }

    public function dispatchImportsForNewOrders(): int
    {
        Log::info('PurchasingPowerOrderSyncService: Shopify import dispatcher skipped until PurchasingPowerOrderPushService is implemented.');

        return 0;
    }
}
