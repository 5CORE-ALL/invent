<?php

namespace App\Services\MarketplaceManager;

use App\Models\TemuOrder;
use App\Services\TemuApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TemuOrderDetailService
{
    public function __construct(
        protected TemuApiService $api
    ) {}

    /**
     * Refresh ship-to (and keep cached line payload) for one parent order.
     * Does not re-run the full 60-day order sync.
     *
     * @return array{success: bool, message?: string, address_loaded?: bool}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('temu_orders')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        $lines = TemuOrder::query()
            ->where('parent_order_sn', $orderId)
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            return ['success' => false, 'message' => 'Order not found in temu_orders.'];
        }

        $addressLoaded = false;
        $addressMessage = null;

        try {
            $addrRes = $this->api->getOrderShippingAddress($orderId);
            $addressMessage = $addrRes['message'] ?? null;
            if (! empty($addrRes['success']) && ! empty($addrRes['address'])) {
                $address = $addrRes['address'];
                foreach ($lines as $line) {
                    $raw = is_array($line->raw_json) ? $line->raw_json : (
                        is_string($line->raw_json) ? (json_decode($line->raw_json, true) ?: []) : []
                    );
                    $raw['receipt_address'] = $address;
                    if (! empty($address['email'])) {
                        $raw['buyer_email'] = $address['email'];
                        $raw['buyer_info'] = array_merge(
                            is_array($raw['buyer_info'] ?? null) ? $raw['buyer_info'] : [],
                            ['email' => $address['email']]
                        );
                    }
                    $line->raw_json = $raw;
                    $line->save();
                }
                $addressLoaded = true;
            }
        } catch (\Throwable $e) {
            $addressMessage = $e->getMessage();
            Log::warning('TemuOrderDetailService: address refresh failed', [
                'parent_order_sn' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'address_loaded' => $addressLoaded,
            'message' => $addressLoaded
                ? 'Order refreshed with Temu shipping address.'
                : ('Order found locally.'.($addressMessage ? ' Address: '.$addressMessage : ' Address unavailable — push may omit shipping_address.')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(TemuOrder $line): array
    {
        $raw = is_array($line->raw_json) ? $line->raw_json : (
            is_string($line->raw_json) ? (json_decode($line->raw_json, true) ?: []) : []
        );

        return $this->flattenOrderRoot($raw, $line);
    }

    /**
     * Flatten Temu list payload ({parentOrderMap, order}) into formatter-friendly keys.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function flattenOrderRoot(array $raw, ?TemuOrder $line = null): array
    {
        $parentMap = is_array($raw['parentOrderMap'] ?? null) ? $raw['parentOrderMap'] : [];
        $sub = is_array($raw['order'] ?? null) ? $raw['order'] : [];

        $flat = $raw;
        $flat['parent_order_sn'] = $raw['parent_order_sn']
            ?? $parentMap['parentOrderSn']
            ?? $line?->parent_order_sn;
        $flat['order_sn'] = $raw['order_sn'] ?? $sub['orderSn'] ?? $line?->order_sn;
        $flat['parent_order_status_text'] = $raw['parent_order_status_text']
            ?? $line?->parent_order_status_text;
        $flat['order_status_text'] = $raw['order_status_text']
            ?? $sub['orderStatus']
            ?? $line?->order_status_text;
        $flat['parent_order_time'] = $raw['parent_order_time']
            ?? $parentMap['parentOrderTime']
            ?? $line?->parent_order_time;
        $flat['payment_type'] = $raw['payment_type']
            ?? $sub['orderPaymentType']
            ?? $line?->order_payment_type;
        $flat['order_base_amount'] = $raw['order_base_amount'] ?? $line?->order_base_amount;
        $flat['order_total_amount'] = $raw['order_total_amount'] ?? $line?->order_total_amount;
        $flat['ext_code'] = $raw['ext_code'] ?? $line?->ext_code;
        $flat['goods_name'] = $raw['goods_name'] ?? $sub['goodsName'] ?? $line?->goods_name;
        $flat['goods_id'] = $raw['goods_id'] ?? (isset($sub['goodsId']) ? (string) $sub['goodsId'] : null) ?? $line?->goods_id;
        $flat['quantity'] = $raw['quantity'] ?? $sub['quantity'] ?? $line?->quantity;

        if (! isset($flat['receipt_address']) || ! is_array($flat['receipt_address'])) {
            $flat['receipt_address'] = [];
        }

        return $flat;
    }
}
