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
     * Ask Doba for one order id and store the waybill on doba_daily_data.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOrderById(string $orderId): ?array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('doba_daily_data')) {
            return null;
        }

        $api = app(DobaApiService::class);
        if (! $api->isConfigured()) {
            return null;
        }

        $window = [
            'pageNo' => 1,
            'pageSize' => 10,
            'beginTime' => now()->subDays(45)->format('Y-m-d\TH:i:sP'),
            'endTime' => now()->format('Y-m-d\TH:i:sP'),
        ];
        $order = null;
        foreach (['ordBusiId', 'platformOrderNo'] as $field) {
            $rows = $api->querySellerOrderDetail($window + [$field => $orderId]);
            $order = $this->matchDobaOrder($rows, $orderId);
            if (is_array($order)) {
                break;
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
        $want = strtoupper(trim($orderId));
        foreach ($rows as $row) {
            foreach (['ordBusiId', 'orderNo', 'platformOrderNo'] as $key) {
                $value = strtoupper(trim((string) ($row[$key] ?? '')));
                if ($value !== '' && $value === $want) {
                    return $row;
                }
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

        DobaDailyData::query()
            ->where(function ($query) use ($keys): void {
                $query->whereIn('order_no', $keys)->orWhereIn('platform_order_no', $keys);
            })
            ->update($updates);
    }

    public function dispatchImportsForNewOrders(): int
    {
        Log::info('DobaOrderSyncService: Shopify import dispatcher skipped until DobaOrderPushService is implemented.');

        return 0;
    }
}
