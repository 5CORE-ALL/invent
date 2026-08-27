<?php

namespace App\Services\MarketplaceManager;

use App\Models\WayfairDailyData;
use Illuminate\Support\Facades\Schema;

class WayfairOrderDetailService
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function fetchAndPersistOrderDetail(string $orderId, bool $refreshFromApi = false): array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('wayfair_daily_data')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        if ($refreshFromApi) {
            $result = app(WayfairOrderSyncService::class)->fetchAndStore(60);
            if (empty($result['success'])) {
                $exists = WayfairDailyData::query()->where('po_number', $orderId)->exists();
                if ($exists) {
                    return ['success' => true, 'message' => 'Using cached Wayfair order (live refresh failed).'];
                }

                return ['success' => false, 'message' => $result['message'] ?? 'Fetch failed.'];
            }
        }

        $exists = WayfairDailyData::query()
            ->where('po_number', $orderId)
            ->exists();

        if (! $exists) {
            return ['success' => false, 'message' => 'Order not found in wayfair_daily_data.'];
        }

        return ['success' => true, 'message' => $refreshFromApi
            ? 'Order refreshed from Wayfair fetch.'
            : 'Using stored Wayfair order.'];
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
            'poNumber' => $line->po_number,
            'po_number' => $line->po_number,
            'status' => $line->status,
            'poDate' => $line->po_date,
            'po_date' => $line->po_date,
            'total_price' => $line->total_price,
            'unit_price' => $line->unit_price,
            'sku' => $line->sku,
            'quantity' => $line->quantity,
            'customerName' => $line->customer_name,
            'customerAddress1' => $line->customer_address1,
            'customerAddress2' => $line->customer_address2,
            'customerCity' => $line->customer_city,
            'customerState' => $line->customer_state,
            'customerPostalCode' => $line->customer_postal_code,
            'shipTo' => [
                'name' => $line->customer_name,
                'address1' => $line->customer_address1,
                'address2' => $line->customer_address2,
                'city' => $line->customer_city,
                'state' => $line->customer_state,
                'postalCode' => $line->customer_postal_code,
                'country' => $line->customer_country,
                'phoneNumber' => $line->customer_phone,
            ],
            'shippingInfo' => [
                'shipSpeed' => $line->ship_speed,
                'carrierCode' => $line->carrier_code,
            ],
            'customer_name' => $line->customer_name,
            'customer_city' => $line->customer_city,
            'customer_state' => $line->customer_state,
            'customer_country' => $line->customer_country,
            'carrier_code' => $line->carrier_code,
        ];
    }
}
