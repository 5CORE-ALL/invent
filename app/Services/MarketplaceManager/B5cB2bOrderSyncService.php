<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bOrder;
use App\Services\Business5CoreB2bApiService;
use App\Services\ShopifyB2BStoreOrderIngestService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class B5cB2bOrderSyncService
{
    public function __construct(
        protected Business5CoreB2bApiService $api,
        protected ShopifyB2BStoreOrderIngestService $dailyIngest
    ) {
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Business 5 Core B2B API is not configured.', 'upserted' => 0, 'pages' => 0];
        }
        if (! Schema::hasTable('b5c_b2b_orders')) {
            return ['success' => false, 'message' => 'b5c_b2b_orders table missing.', 'upserted' => 0, 'pages' => 0];
        }

        $from = Carbon::parse($fromDate)->startOfDay()->toDateString();

        try {
            $orders = $this->fetchOrdersSince($from);
        } catch (\Throwable $e) {
            Log::warning('B5C B2B order fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'upserted' => 0,
                'pages' => 0,
            ];
        }

        $upserted = 0;
        $lines = 0;
        foreach ($orders as $order) {
            $id = (int) ($order['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $detail = $order;
            try {
                $detail = $this->api->fetchOrder($id);
            } catch (\Throwable) {
            }
            $row = B5cB2bOrder::query()->updateOrCreate(
                ['store_order_id' => $id],
                [
                    'status' => $detail['status'] ?? $order['status'] ?? null,
                    'customer_email' => $detail['customer_email'] ?? $order['customer_email'] ?? null,
                    'customer_name' => $detail['customer_name'] ?? $order['customer_name'] ?? null,
                    'currency' => $detail['currency'] ?? $order['currency'] ?? 'USD',
                    'total' => $detail['total'] ?? $order['total'] ?? null,
                    'tracking_reference' => $detail['tracking_reference'] ?? $order['tracking_reference'] ?? null,
                    'ordered_at' => $detail['created_at'] ?? $order['created_at'] ?? null,
                    'payload' => $detail,
                ]
            );
            $lines += $this->dailyIngest->flattenOrder($row);
            $upserted++;
        }

        $this->dailyIngest->refreshPeriodLabels();

        return [
            'success' => true,
            'message' => "Synced {$upserted} Business 5 Core B2B order(s), {$lines} sales line(s).",
            'upserted' => $upserted,
            'pages' => 1,
            'fetched' => $upserted,
            'stored' => $upserted,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchOrdersSince(string $from): array
    {
        $orders = $this->api->fetchAllOrders(['since' => $from]);
        if ($orders !== []) {
            return $orders;
        }

        $all = $this->api->fetchAllOrders();
        if ($all === []) {
            return [];
        }

        $fromTs = Carbon::parse($from)->startOfDay();
        $filtered = [];
        foreach ($all as $order) {
            if (! is_array($order)) {
                continue;
            }
            $created = $order['created_at'] ?? $order['ordered_at'] ?? null;
            if ($created === null || $created === '') {
                $filtered[] = $order;
                continue;
            }
            try {
                if (Carbon::parse($created)->gte($fromTs)) {
                    $filtered[] = $order;
                }
            } catch (\Throwable) {
                $filtered[] = $order;
            }
        }

        // Store list endpoint may omit dates; keep the full catalog so sales is not empty.
        return $filtered !== [] ? $filtered : $all;
    }
}
