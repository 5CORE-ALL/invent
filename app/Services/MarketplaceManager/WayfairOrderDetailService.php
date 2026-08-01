<?php

namespace App\Services\MarketplaceManager;

use App\Models\WayfairDailyData;
use Illuminate\Support\Facades\Schema;

class WayfairOrderDetailService
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('wayfair_daily_data')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        $result = app(WayfairOrderSyncService::class)->fetchAndStore(60);
        if (empty($result['success'])) {
            return ['success' => false, 'message' => $result['message'] ?? 'Fetch failed.'];
        }

        $exists = WayfairDailyData::query()
            ->where('po_number', $orderId)
            ->exists();

        if (! $exists) {
            return ['success' => false, 'message' => 'Order not found in wayfair_daily_data after refresh.'];
        }

        return ['success' => true, 'message' => 'Order refreshed from Wayfair fetch.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(WayfairDailyData $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : (
            is_string($line->raw_payload) ? (json_decode($line->raw_payload, true) ?: []) : []
        );
        if ($raw !== []) {
            return $raw;
        }

        return [
            'po_number' => $line->po_number,
            'status' => $line->status,
            'po_date' => $line->po_date,
            'total_price' => $line->total_price,
            'unit_price' => $line->unit_price,
            'sku' => $line->sku,
            'quantity' => $line->quantity,
            'customer_name' => $line->customer_name,
            'customer_city' => $line->customer_city,
            'customer_state' => $line->customer_state,
            'customer_country' => $line->customer_country,
            'carrier_code' => $line->carrier_code,
        ];
    }
}
