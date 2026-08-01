<?php

namespace App\Services\MarketplaceManager;

use App\Models\MacyOrderMetric;
use Illuminate\Support\Facades\Schema;

class MacyOrderDetailService
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('mirakl_daily_data')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        $result = app(MacyOrderSyncService::class)->fetchAndStore(60);
        if (empty($result['success'])) {
            return ['success' => false, 'message' => $result['message'] ?? 'Fetch failed.'];
        }

        $exists = MacyOrderMetric::query()
            ->where('order_id', $orderId)
            ->orWhere('channel_order_id', $orderId)
            ->exists();

        if (! $exists) {
            return ['success' => false, 'message' => 'Order not found in Macy\'s, Inc. rows after refresh.'];
        }

        return ['success' => true, 'message' => 'Order refreshed from Mirakl daily fetch.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(MacyOrderMetric $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : (
            is_string($line->raw_payload) ? (json_decode($line->raw_payload, true) ?: []) : []
        );
        if ($raw !== []) {
            return $raw;
        }

        return [
            'order_id' => $line->order_id,
            'channel_order_id' => $line->channel_order_id,
            'status' => $line->status,
            'order_created_at' => $line->order_created_at,
            'sku' => $line->sku,
            'product_title' => $line->product_title,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'shipping_first_name' => $line->shipping_first_name,
            'shipping_last_name' => $line->shipping_last_name,
            'shipping_city' => $line->shipping_city,
            'shipping_state' => $line->shipping_state,
            'shipping_country' => $line->shipping_country,
            'shipping_carrier' => $line->shipping_carrier,
        ];
    }
}
