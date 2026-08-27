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
        try {
            $from = \Carbon\Carbon::parse($fromDate)->startOfDay();
            $days = max(1, min(730, (int) $from->diffInDays(now()) + 1));
        } catch (\Throwable $e) {
            $days = 60;
        }

        return $this->fetchAndStore($days);
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
        MarketplaceShopifyImportQueue::prepareForDispatch(WayfairDailyData::class, $queue);
        $orders = WayfairDailyData::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', MarketplaceShopifyImportQueue::DISPATCHABLE_IMPORT_STATUSES);
            })
            ->when(Schema::hasColumn('wayfair_daily_data', 'po_date'), function ($q) {
                $q->where('po_date', '>=', MarketplaceShopifyImportQueue::DEFAULT_IMPORT_CUTOFF_DATE);
            })
            ->orderByDesc('po_date')
            ->orderByDesc('id')
            ->limit(400)
            ->get();

        $seen = [];
        $dispatched = 0;
        foreach ($orders as $row) {
            $poNumber = trim((string) $row->po_number);
            if ($poNumber === '' || isset($seen[$poNumber])) {
                continue;
            }
            $seen[$poNumber] = true;

            $status = strtoupper(trim((string) ($row->status ?? '')));
            if (str_contains($status, 'CANCEL')) {
                WayfairDailyData::query()
                    ->where('po_number', $poNumber)
                    ->where(function ($q) {
                        $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                    })
                    ->update(['import_status' => 'skipped_closed']);
                continue;
            }

            $alreadyImported = WayfairDailyData::query()
                ->where('po_number', $poNumber)
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->value('shopify_order_id');
            if ($alreadyImported) {
                WayfairDailyData::query()
                    ->where('po_number', $poNumber)
                    ->where(function ($q) {
                        $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                    })
                    ->update([
                        'shopify_order_id' => (string) $alreadyImported,
                        'import_status' => 'imported',
                        'pushed_to_shopify_at' => now(),
                    ]);
                continue;
            }

            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('wayfair', $row)) {
                WayfairDailyData::query()
                    ->where('po_number', $poNumber)
                    ->where(function ($q) {
                        $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                    })
                    ->update(['import_status' => 'skipped_unpaid']);
                continue;
            }

            MarketplaceShopifyImportQueue::push(new ImportWayfairOrderToShopify((int) $row->id), $queue);
            WayfairDailyData::query()
                ->where('po_number', $poNumber)
                ->where(function ($q) {
                    $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                })
                ->update(['import_status' => 'queued']);
            $dispatched++;
            if ($dispatched >= 200) {
                break;
            }
        }

        return $dispatched;
    }
}
