<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressOrderMetric;
use App\Services\AliExpressApiService;
use Illuminate\Support\Facades\Log;

class AliexpressOrderDetailService
{
    public function __construct(
        protected AliExpressApiService $aliExpressApi
    ) {}

    /**
     * @return array{success: bool, message?: string, order?: array<string, mixed>}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Order ID is required.'];
        }

        $info = $this->aliExpressApi->getOrderInfo($orderId);
        if (empty($info['success'])) {
            return [
                'success' => false,
                'message' => $info['message'] ?? 'Could not load AliExpress order details.',
            ];
        }

        $order = is_array($info['data'] ?? null) ? $info['data'] : [];
        $receipt = $this->aliExpressApi->getOrderReceiptInfo($orderId);
        if (! empty($receipt['success']) && is_array($receipt['data'] ?? null)) {
            $order = $this->mergeReceiptAddress($order, $receipt['data']);
        }

        $lines = AliexpressOrderMetric::query()
            ->where('order_id', $orderId)
            ->get();

        if ($lines->isEmpty()) {
            return ['success' => false, 'message' => 'Order not found in local database.'];
        }

        foreach ($lines as $line) {
            $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
            $raw['order'] = $order;
            $raw['order_detail_fetched_at'] = now()->toIso8601String();
            if (is_array($raw['line'] ?? null)) {
                // keep per-line snapshot
            }
            $line->update(['raw_payload' => $raw]);
        }

        Log::info('AliexpressOrderDetailService: persisted order detail', [
            'order_id' => $orderId,
            'lines' => $lines->count(),
        ]);

        return ['success' => true, 'order' => $order];
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>
     */
    protected function mergeReceiptAddress(array $order, array $receipt): array
    {
        $existing = is_array($order['receipt_address'] ?? null) ? $order['receipt_address'] : [];
        $incoming = is_array($receipt['receipt_address'] ?? null)
            ? $receipt['receipt_address']
            : $receipt;

        foreach ($incoming as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $existing[$key] = $value;
        }

        $order['receipt_address'] = $existing;

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(AliexpressOrderMetric $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];

        return is_array($raw['order'] ?? null) ? $raw['order'] : $raw;
    }
}
