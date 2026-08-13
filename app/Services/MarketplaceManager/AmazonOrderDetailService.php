<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmazonOrderDetailService
{
    public function __construct(
        protected AmazonSpOrdersClient $ordersClient,
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

        $order = AmazonOrder::query()->where('amazon_order_id', $amazonOrderId)->first();
        if (! $order) {
            return ['success' => false, 'message' => 'Order not found locally. Fetch Amazon orders first.'];
        }

        $result = $this->hydrateRestrictedPii($order, true);

        return [
            'success' => ! empty($result['success']),
            'message' => $result['message'] ?? ($result['success'] ? 'Order details updated from Amazon.' : 'Failed to pull Amazon order details.'),
        ];
    }

    /**
     * Fetch buyer + shipping PII via Restricted Data Token and merge into raw_data.
     *
     * @return array{success: bool, order: array<string, mixed>, hydrated: bool, message: string}
     */
    public function hydrateRestrictedPii(AmazonOrder $order, bool $force = false): array
    {
        $existing = $this->resolveOrderRoot($order);
        if (! $force && $this->hasUsableShippingAddress($existing)) {
            return [
                'success' => true,
                'order' => $existing,
                'hydrated' => false,
                'message' => 'Shipping address already present on stored Amazon payload.',
            ];
        }

        $amazonOrderId = trim((string) $order->amazon_order_id);
        $result = $this->ordersClient->getOrderWithPii($amazonOrderId);
        if (empty($result['success'])) {
            return [
                'success' => false,
                'order' => $existing,
                'hydrated' => false,
                'message' => $result['message'] ?? 'Amazon PII fetch failed.',
            ];
        }

        $fresh = is_array($result['order'] ?? null) ? $result['order'] : [];
        $merged = array_merge($existing, $fresh);
        foreach (['ShippingAddress', 'shippingAddress', 'BuyerInfo', 'buyerInfo'] as $key) {
            if (! empty($fresh[$key]) && is_array($fresh[$key])) {
                $merged[$key] = $fresh[$key];
            }
        }

        $order->raw_data = $merged;
        if (! empty($fresh['OrderStatus'])) {
            $order->status = (string) $fresh['OrderStatus'];
        }
        $channel = strtoupper(trim((string) ($fresh['FulfillmentChannel'] ?? $fresh['fulfillmentChannel'] ?? '')));
        if ($channel !== '' && Schema::hasColumn('amazon_orders', 'fulfillment_channel')) {
            $order->fulfillment_channel = $channel;
        }
        $order->save();

        Log::info('AmazonOrderDetailService: hydrated restricted order PII', [
            'amazon_order_id' => $amazonOrderId,
            'has_address' => $this->hasUsableShippingAddress($merged),
        ]);

        return [
            'success' => true,
            'order' => $merged,
            'hydrated' => true,
            'message' => $this->hasUsableShippingAddress($merged)
                ? 'Pulled Amazon buyer/shipping address.'
                : 'Amazon PII fetched but shipping street is still empty.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(AmazonOrder $order): array
    {
        $raw = AmazonOrder::decodeRawPayload($order->raw_data);
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

    /**
     * @param  array<string, mixed>  $orderRoot
     */
    public function hasUsableShippingAddress(array $orderRoot): bool
    {
        $addr = is_array($orderRoot['ShippingAddress'] ?? null)
            ? $orderRoot['ShippingAddress']
            : (is_array($orderRoot['shippingAddress'] ?? null) ? $orderRoot['shippingAddress'] : []);

        $line1 = trim((string) ($addr['AddressLine1'] ?? $addr['addressLine1'] ?? ''));

        return $line1 !== '';
    }
}
