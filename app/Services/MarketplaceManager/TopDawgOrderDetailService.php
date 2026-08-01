<?php

namespace App\Services\MarketplaceManager;

use App\Models\TopDawgOrderMetric;
use App\Services\TopDawgApiService;
use Illuminate\Support\Facades\Schema;

class TopDawgOrderDetailService
{
    public function __construct(
        protected TopDawgApiService $topdawgApi
    ) {}

    /**
     * @return array{success: bool, message?: string}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('topdawg_order_metrics')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        // TopDawg list API has no single-order endpoint — refresh from recent list.
        try {
            $result = $this->topdawgApi->fetchOrders(now()->subDays(60)->toIso8601String());
            $orders = $result['data'] ?? [];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $match = null;
        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }
            $num = trim((string) ($order['order_number'] ?? $order['orderNumber'] ?? $order['id'] ?? ''));
            if ($num === $orderId) {
                $match = $order;
                break;
            }
        }

        if ($match === null) {
            return ['success' => false, 'message' => 'Order not found in recent TopDawg API results.'];
        }

        app(TopDawgOrderSyncService::class)->fetchAndStoreFromDate(
            now()->subDays(60)->toDateString()
        );

        return ['success' => true, 'message' => 'Order refreshed from TopDawg API.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(TopDawgOrderMetric $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
        if ($raw !== []) {
            return $raw;
        }

        return [
            'order_number' => $line->order_number ?? $line->order_id,
            'order_id' => $line->order_id ?? $line->order_number,
            'status' => $line->status,
            'order_date' => $line->order_date,
            'amount' => $line->amount,
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    public function extractOrders(array $json): array
    {
        $orders = $json['orders'] ?? $json['results'] ?? $json['data'] ?? [];

        return is_array($orders) ? array_values(array_filter($orders, 'is_array')) : [];
    }
}
