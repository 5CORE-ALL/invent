<?php

namespace App\Services\MarketplaceManager;

use App\Models\TemuOrder;
use Illuminate\Support\Facades\Schema;

class TemuOrderDetailService
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('temu_orders')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        $result = app(TemuOrderSyncService::class)->fetchAndStore(60);
        if (empty($result['success'])) {
            return ['success' => false, 'message' => $result['message'] ?? 'Fetch failed.'];
        }

        $exists = TemuOrder::query()
            ->where('parent_order_sn', $orderId)
            ->exists();

        if (! $exists) {
            return ['success' => false, 'message' => 'Order not found in temu_orders after refresh.'];
        }

        return ['success' => true, 'message' => 'Order refreshed from Temu fetch.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(TemuOrder $line): array
    {
        $raw = is_array($line->raw_json) ? $line->raw_json : (
            is_string($line->raw_json) ? (json_decode($line->raw_json, true) ?: []) : []
        );
        if ($raw !== []) {
            return $raw;
        }

        return [
            'parent_order_sn' => $line->parent_order_sn,
            'order_sn' => $line->order_sn,
            'parent_order_status_text' => $line->parent_order_status_text,
            'order_status_text' => $line->order_status_text,
            'parent_order_time' => $line->parent_order_time,
            'order_total_amount' => $line->order_total_amount,
            'order_base_amount' => $line->order_base_amount,
            'ext_code' => $line->ext_code,
            'goods_name' => $line->goods_name,
            'goods_id' => $line->goods_id,
            'quantity' => $line->quantity,
        ];
    }
}
