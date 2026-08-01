<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonOrder;
use Illuminate\Support\Facades\Schema;

class AmazonOrderDetailService
{
    public function __construct(
        protected AmazonOrderSyncService $orderSync
    ) {}

    /**
     * @return array{success: bool, message?: string}
     */
    public function fetchAndPersistOrderDetail(string $amazonOrderId): array
    {
        $amazonOrderId = trim($amazonOrderId);
        if ($amazonOrderId === '' || ! Schema::hasTable('amazon_orders')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        try {
            $result = $this->orderSync->fetchAndStore(14);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $found = AmazonOrder::query()->where('amazon_order_id', $amazonOrderId)->exists();

        return [
            'success' => ! empty($result['success']),
            'message' => $found
                ? 'Order refreshed from Amazon SP-API fetch.'
                : ($result['message'] ?? 'Order fetch completed; order may be outside the fetch window.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(AmazonOrder $order): array
    {
        $raw = is_array($order->raw_data) ? $order->raw_data : [];
        if ($raw !== []) {
            return $raw;
        }

        return [
            'AmazonOrderId' => $order->amazon_order_id,
            'OrderStatus' => $order->status,
            'PurchaseDate' => $order->order_date,
            'OrderTotal' => [
                'Amount' => $order->total_amount,
                'CurrencyCode' => $order->currency,
            ],
        ];
    }
}
