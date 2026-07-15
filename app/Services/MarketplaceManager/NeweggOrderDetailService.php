<?php

namespace App\Services\MarketplaceManager;

use App\Models\NeweggOrderMetric;
use App\Services\NeweggApiService;

/**
 * Minimal order detail for MM order show page.
 */
class NeweggOrderDetailService
{
    public function __construct(
        protected NeweggApiService $neweggApi
    ) {}

    /**
     * @return array{order: array<string, mixed>, lines: list<array<string, mixed>>, raw: ?array}
     */
    public function detail(NeweggOrderMetric $metric): array
    {
        $raw = is_array($metric->raw_payload) ? $metric->raw_payload : null;

        return [
            'order' => [
                'order_id' => $metric->order_id,
                'order_number' => $metric->order_number,
                'order_date' => optional($metric->order_date)?->toDateTimeString(),
                'status' => $metric->status,
                'shopify_order_id' => $metric->shopify_order_id,
                'import_status' => $metric->import_status,
            ],
            'lines' => [[
                'sku' => $metric->sku,
                'product_id' => $metric->product_id,
                'title' => $metric->display_title,
                'quantity' => $metric->quantity,
                'amount' => $metric->amount,
            ]],
            'raw' => $raw,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $res = $this->neweggApi->getOrders([
            'OrderNumber' => $orderId,
        ], 1, 10);

        if (! empty($res['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare'];
        }

        if (empty($res['ok']) && empty($res['json'])) {
            return ['success' => false, 'message' => $res['error'] ?? 'Order fetch failed'];
        }

        $list = data_get($res['json'], 'ResponseBody.OrderInfoList')
            ?? data_get($res['json'], 'OrderInfoList')
            ?? [];
        $order = is_array($list) ? (isset($list[0]) ? $list[0] : $list) : null;
        if (! is_array($order) || $order === []) {
            return ['success' => false, 'message' => 'Order not found on Newegg'];
        }

        NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->update([
                'raw_payload' => $order,
                'status' => (string) ($order['OrderStatus'] ?? $order['OrderStatusDescription'] ?? ''),
            ]);

        return ['success' => true, 'message' => 'Order details updated from Newegg.'];
    }
}
