<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportTemu2OrderToShopify;
use App\Models\MarketplaceSyncSettings;
use App\Models\Temu2Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Temu 2 orders into temu2_orders for Marketplace Manager.
 */
class Temu2OrderSyncService
{
    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        $result = $this->fetchAndStoreFromDate($fromDate);

        if ($import && MarketplaceSyncSettings::canAutoImportToShopify('temu2')) {
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
        unset($days);

        if (! Schema::hasTable('temu2_orders')) {
            return [
                'success' => false,
                'message' => 'temu2_orders table missing.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('temu2')) {
            return [
                'success' => true,
                'message' => 'Order fetch disabled in settings.',
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $before = (int) Temu2Order::query()->count();

        try {
            Artisan::call('app:fetch-temu2-orders');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            Log::error('Temu2OrderSyncService: fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Temu order fetch failed: '.$e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $after = (int) Temu2Order::query()->count();
        $stored = max(0, $after - $before);

        return [
            'success' => true,
            'message' => "Synced Temu 2 orders ({$after} total, +{$stored} new).".($output !== '' ? ' '.$output : ''),
            'upserted' => $stored,
            'pages' => 1,
            'fetched' => $stored,
            'stored' => $stored,
        ];
    }

    public function dispatchImportsForNewOrders(): int
    {
        if (! MarketplaceSyncSettings::canAutoImportToShopify('temu2')) {
            return 0;
        }

        if (! Schema::hasColumn('temu2_orders', 'shopify_order_id')) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('temu2');
        $query = Temu2Order::query()
            ->whereNull('shopify_order_id')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereNotIn('import_status', ['queued', 'imported']);
            })
            ->orderBy('id');

        $dispatched = 0;
        $query->chunkById(50, function ($rows) use (&$dispatched, $paidOnly) {
            foreach ($rows as $row) {
                if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('temu2', $row)) {
                    continue;
                }
                $row->update(['import_status' => 'queued']);
                ImportTemu2OrderToShopify::dispatch((int) $row->id);
                $dispatched++;
            }
        });

        return $dispatched;
    }
}
