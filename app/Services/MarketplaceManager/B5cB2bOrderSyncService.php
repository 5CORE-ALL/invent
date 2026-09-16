<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bOrder;
use App\Services\Business5CoreB2bApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class B5cB2bOrderSyncService
{
    public function __construct(protected Business5CoreB2bApiService $api)
    {
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
            $orders = $this->api->fetchAllOrders(['since' => $from]);
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
            B5cB2bOrder::query()->updateOrCreate(
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
            $upserted++;
        }

        return [
            'success' => true,
            'message' => "Synced {$upserted} Business 5 Core B2B order(s).",
            'upserted' => $upserted,
            'pages' => 1,
            'fetched' => $upserted,
            'stored' => $upserted,
        ];
    }
}
