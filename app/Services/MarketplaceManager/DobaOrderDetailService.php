<?php

namespace App\Services\MarketplaceManager;

use App\Models\DobaDailyData;
use Illuminate\Support\Facades\Schema;

class DobaOrderDetailService
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('doba_daily_data')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        $result = app(DobaOrderSyncService::class)->fetchAndStore(60);
        if (empty($result['success'])) {
            return ['success' => false, 'message' => $result['message'] ?? 'Fetch failed.'];
        }

        $exists = DobaDailyData::query()
            ->where('order_no', $orderId)
            ->exists();

        if (! $exists) {
            return ['success' => false, 'message' => 'Order not found in doba_daily_data after refresh.'];
        }

        return ['success' => true, 'message' => 'Order refreshed from Doba daily fetch.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(DobaDailyData $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : (
            is_string($line->raw_payload) ? (json_decode($line->raw_payload, true) ?: []) : []
        );
        if ($raw !== []) {
            return $raw;
        }

        return [
            'order_no' => $line->order_no,
            'platform_order_no' => $line->platform_order_no,
            'order_status' => $line->order_status,
            'order_time' => $line->order_time,
            'pay_time' => $line->pay_time,
            'sku' => $line->sku,
            'product_name' => $line->product_name,
            'quantity' => $line->quantity,
            'total_price' => $line->total_price,
            'item_price' => $line->item_price,
            'receiver_name' => $line->receiver_name,
            'shipping_city' => $line->shipping_city,
            'shipping_state' => $line->shipping_state,
            'shipping_country' => $line->shipping_country,
            'carrier_name' => $line->carrier_name,
            'tracking_number' => $line->tracking_number,
        ];
    }
}
