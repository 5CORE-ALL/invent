<?php

namespace App\Services\MarketplaceManager;

use App\Models\PurchasingPowerSale;
use Illuminate\Support\Facades\Schema;

class PurchasingPowerOrderDetailService
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('purchasing_power_sales')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        $result = app(PurchasingPowerOrderSyncService::class)->fetchAndStore(60);
        if (empty($result['success'])) {
            return ['success' => false, 'message' => $result['message'] ?? 'Fetch failed.'];
        }

        $exists = PurchasingPowerSale::query()
            ->where('order_id', $orderId)
            ->orWhere('order_number', $orderId)
            ->exists();

        if (! $exists) {
            return ['success' => false, 'message' => 'Order not found in purchasing_power_sales after refresh.'];
        }

        return ['success' => true, 'message' => 'Order refreshed from Purchasing Power fetch.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(PurchasingPowerSale $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : (
            is_string($line->raw_payload) ? (json_decode($line->raw_payload, true) ?: []) : []
        );
        if ($raw !== []) {
            return $raw;
        }

        return [
            'order_id' => $line->order_id,
            'order_number' => $line->order_number,
            'status' => $line->status,
            'date_created' => $line->date_created,
            'amount' => $line->amount,
            'offer_sku' => $line->offer_sku ?? $line->product_sku,
            'product_name' => $line->product_name,
            'quantity' => $line->quantity,
            'customer_first_name' => $line->customer_first_name,
            'customer_last_name' => $line->customer_last_name,
            'customer_city' => $line->customer_city,
            'customer_state' => $line->customer_state,
            'customer_country' => $line->customer_country,
            'tracking_number' => $line->tracking_number,
            'shipping_company' => $line->shipping_company,
        ];
    }
}
