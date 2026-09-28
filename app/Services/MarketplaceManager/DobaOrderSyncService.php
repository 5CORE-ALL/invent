<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportDobaOrderToShopify;
use App\Models\DobaDailyData;
use App\Models\MarketplaceSyncSettings;
use App\Services\DobaApiService;
use App\Support\DobaTrackingNumber;
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

    /**
     * Pull the newest Doba orders into doba_daily_data.
     */
    public function fetchRecentOrders(int $days = 3, int $maxPages = 2): int
    {
        if (! Schema::hasTable('doba_daily_data')) {
            return 0;
        }
        $api = app(DobaApiService::class);
        if (! $api->isConfigured()) {
            return 0;
        }

        try {
            return app(\App\Console\Commands\FetchDobaDailyData::class)->fetchRecentOrders($days, $maxPages);
        } catch (\Throwable $e) {
            Log::warning('DobaOrderSyncService: recent order fetch failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Ask Doba for one order id and store the waybill on doba_daily_data.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOrderById(string $orderId, bool $quick = false): ?array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('doba_daily_data')) {
            return null;
        }

        $api = app(DobaApiService::class);
        if (! $api->isConfigured()) {
            return null;
        }

        $windows = [
            [
                'beginTime' => now()->subDays(45)->format('Y-m-d\TH:i:sP'),
                'endTime' => now()->format('Y-m-d\TH:i:sP'),
            ],
        ];
        if (! $quick) {
            $windows[] = [
                'beginTime' => now()->subDays(45)->format('Y-m-d H:i:s'),
                'endTime' => now()->format('Y-m-d H:i:s'),
            ];
        }
        $order = null;
        $timeout = $quick ? 4 : 12;
        $shortId = preg_match('/^\d{4,9}$/', $orderId) === 1;
        foreach ($windows as $window) {
            $query = $window + ['pageNo' => 1, 'pageSize' => 10];
            $fields = $quick
                ? ($shortId ? ['platformOrderNo'] : ['ordBusiId'])
                : ($shortId ? ['platformOrderNo', 'ordBusiId'] : ['ordBusiId', 'platformOrderNo']);
            foreach ($fields as $field) {
                $rows = $api->querySellerOrderDetail($query + [$field => $orderId], $timeout);
                $order = $this->matchDobaOrder($rows, $orderId);
                if (is_array($order)) {
                    break 2;
                }
            }
        }
        if (! is_array($order)) {
            return null;
        }

        $this->storeFetchedDobaOrder($order, $orderId);

        return $order;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    protected function matchDobaOrder(array $rows, string $orderId): ?array
    {
        $want = strtoupper(ltrim(trim($orderId), '#'));
        if ($want === '') {
            return null;
        }
        foreach ($rows as $row) {
            foreach (['ordBusiId', 'orderNo', 'platformOrderNo', 'storeOrderNo', 'outOrderId', 'saleOrderNo'] as $key) {
                $value = strtoupper(ltrim(trim((string) ($row[$key] ?? '')), '#'));
                if ($value !== '' && ($value === $want || str_ends_with($value, $want))) {
                    return $row;
                }
            }
            $blob = strtoupper((string) json_encode($row));
            if (preg_match('/(?<!\d)'.preg_quote($want, '/').'(?!\d)/', $blob) === 1) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function storeFetchedDobaOrder(array $order, string $requestedId): void
    {
        $orderNo = trim((string) ($order['ordBusiId'] ?? $order['orderNo'] ?? ''));
        $platformOrderNo = trim((string) ($order['platformOrderNo'] ?? ''));
        $hit = DobaTrackingNumber::fromOrderPayload($order);
        $status = trim((string) ($order['ordStatus'] ?? $order['orderStatus'] ?? ''));
        $updates = ['order_json' => json_encode($order), 'updated_at' => now()];
        if ($status !== '') {
            $updates['order_status'] = substr($status, 0, 50);
        }
        if ($hit['tracking'] !== '') {
            $updates['tracking_number'] = substr($hit['tracking'], 0, 100);
            if ($hit['carrier'] !== '') {
                $updates['carrier_name'] = substr($hit['carrier'], 0, 50);
            }
        }

        $keys = array_values(array_filter(array_unique([$orderNo, $platformOrderNo, trim($requestedId)])));
        if ($keys === []) {
            return;
        }

        $updated = DobaDailyData::query()
            ->where(function ($query) use ($keys): void {
                $query->whereIn('order_no', $keys)->orWhereIn('platform_order_no', $keys);
            })
            ->update($updates);
        if ($updated === 0) {
            app(\App\Console\Commands\FetchDobaDailyData::class)->storeOrderFromApi($order);
        }
    }

    public function dispatchImportsForNewOrders(): int
    {
        return MarketplaceShopifyImportQueue::dispatchLatestUnpushed(
            'doba',
            DobaDailyData::class,
            static fn (int $id) => new ImportDobaOrderToShopify($id),
            'order_no',
            function ($query): void {
                $query->where('order_time', '>=', now()->subDays(21))
                    ->whereRaw("UPPER(COALESCE(order_status, '')) NOT LIKE '%CANCEL%'")
                    ->whereRaw("UPPER(COALESCE(order_status, '')) NOT LIKE '%REFUND%'")
                    ->whereRaw("UPPER(COALESCE(order_status, '')) NOT LIKE '%VOID%'")
                    ->whereRaw("UPPER(COALESCE(order_status, '')) NOT LIKE '%DELIVERED%'")
                    ->whereRaw("UPPER(COALESCE(order_status, '')) NOT LIKE '%COMPLETED%'");
            }
        );
    }
}
