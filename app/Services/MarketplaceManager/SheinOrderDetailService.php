<?php

namespace App\Services\MarketplaceManager;

use App\Models\SheinOrderMetric;
use App\Services\SheinApiService;

/**
 * Order detail helpers for MM Shein order show / Shopify push.
 */
class SheinOrderDetailService
{
    public function __construct(
        protected SheinApiService $sheinApi
    ) {}

    /**
     * @return array{order: array<string, mixed>, lines: list<array<string, mixed>>, raw: ?array}
     */
    public function detail(SheinOrderMetric $metric): array
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
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Order ID is required.'];
        }

        try {
            $details = $this->sheinApi->getOrderDetails([$orderId]);
            $orders = $details['orders'] ?? [];
            $order = null;
            foreach ($orders as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }
                if (trim((string) ($candidate['orderNo'] ?? '')) === $orderId) {
                    $order = $candidate;
                    break;
                }
            }
            if ($order === null && count($orders) === 1 && is_array($orders[0])) {
                $order = $orders[0];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Order fetch failed: '.$e->getMessage()];
        }

        if ($order === null) {
            return ['success' => false, 'message' => 'Order not found on Shein'];
        }

        $existing = SheinOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->value('raw_payload');
        $order = $this->mergePreservedShipTo(
            is_array($existing) ? $existing : [],
            $order
        );

        $statusCode = $order['orderStatus'] ?? null;
        $statusMap = [
            1 => 'Pending',
            2 => 'To Be Shipped',
            3 => 'To Be Shipped by SHEIN',
            4 => 'Shipped',
            5 => 'Received',
            6 => 'Refund',
            7 => 'To Be Collected by SHEIN',
        ];
        $status = $statusMap[(int) $statusCode] ?? (string) ($statusCode ?? '');

        SheinOrderMetric::query()
            ->where('order_id', $orderId)
            ->update([
                'raw_payload' => $order,
                'status' => $status,
            ]);

        return ['success' => true, 'message' => 'Order details updated from Shein.'];
    }

    /**
     * Normalize Shein API payload into the shared MM order-root shape
     * used by SheinDetailFormatter / Shopify push.
     *
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(SheinOrderMetric $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
        if (is_array($raw['order'] ?? null)) {
            $raw = $raw['order'];
        }

        $raw = $this->unwrapOrderInfoNode($raw);

        if ($raw === []) {
            return [];
        }

        // Already in shared/formatter shape.
        if (isset($raw['receipt_address']) || isset($raw['product_list'])) {
            return $raw;
        }

        // Native Shein order-detail shape (orderNo / orderGoodsInfoList).
        if (
            isset($raw['orderNo'])
            || isset($raw['orderGoodsInfoList'])
            || isset($raw['receiveMsg'])
            || isset($raw['OrderNumber'])
            || isset($raw['SellerOrderNumber'])
            || isset($raw['ShipToAddress1'])
            || isset($raw['ItemInfoList'])
            || isset($raw['ItemList'])
            || isset($raw['CustomerName'])
            || isset($raw['CustomerEmailAddress'])
        ) {
            return $this->normalizeSheinOrder($raw, $line);
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>|null
     */
    protected function findOrderInResponse(?array $json, string $orderId): ?array
    {
        foreach ($this->extractOrders($json) as $candidate) {
            $candidateId = trim((string) ($candidate['OrderNumber'] ?? $candidate['SellerOrderNumber'] ?? ''));
            if ($candidateId !== '' && $candidateId === $orderId) {
                return $candidate;
            }
        }

        // If API returned exactly one order, accept it even when id field naming differs.
        $orders = $this->extractOrders($json);
        if (count($orders) === 1) {
            return $orders[0];
        }

        return null;
    }

    /**
     * Unwrap Shein OrderInfoList / OrderInfo envelopes into a flat list of orders.
     *
     * @param  array<string, mixed>|null  $json
     * @return list<array<string, mixed>>
     */
    public function extractOrders(?array $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $list = data_get($json, 'ResponseBody.OrderInfoList')
            ?? data_get($json, 'OrderInfoList')
            ?? data_get($json, 'ResponseBody.OrderInfo')
            ?? data_get($json, 'OrderInfo')
            ?? [];

        if ($list === [] || $list === null) {
            return [];
        }

        if (! is_array($list)) {
            return [];
        }

        // Single flat order.
        if ($this->looksLikeOrderInfo($list)) {
            return [$list];
        }

        // { "OrderInfo": { ... } } or { "OrderInfo": [ {...}, {...} ] }
        if (isset($list['OrderInfo'])) {
            $list = $list['OrderInfo'];
            if (! is_array($list)) {
                return [];
            }
            if ($this->looksLikeOrderInfo($list)) {
                return [$list];
            }
        }

        $out = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $row = $this->unwrapOrderInfoNode($row);
            if ($this->looksLikeOrderInfo($row)) {
                $out[] = $row;
            }
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function unwrapOrderInfoNode(array $node): array
    {
        if ($this->looksLikeOrderInfo($node)) {
            return $node;
        }

        if (isset($node['OrderInfo']) && is_array($node['OrderInfo'])) {
            $inner = $node['OrderInfo'];
            if (isset($inner[0]) && is_array($inner[0])) {
                $inner = $inner[0];
            }
            if (is_array($inner) && $this->looksLikeOrderInfo($inner)) {
                return $inner;
            }
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function looksLikeOrderInfo(array $row): bool
    {
        return isset($row['orderNo'])
            || isset($row['orderGoodsInfoList'])
            || isset($row['OrderNumber'])
            || isset($row['SellerOrderNumber'])
            || isset($row['ShipToAddress1'])
            || isset($row['ItemInfoList'])
            || isset($row['ItemList'])
            || isset($row['CustomerEmailAddress'])
            || isset($row['CustomerName']);
    }

    /**
     * Keep previously stored ship-to / customer PII when a refresh returns blanks.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    protected function mergePreservedShipTo(array $existing, array $incoming): array
    {
        $existing = $this->unwrapOrderInfoNode($existing);
        if (! $this->looksLikeOrderInfo($existing)) {
            return $incoming;
        }

        $preserveKeys = [
            'receiveMsg', 'shippingAddress', 'billAddress',
            'ShipToAddress1', 'ShipToAddress2', 'ShipToCityName', 'ShipToStateCode',
            'ShipToZipCode', 'ShipToCountryCode', 'ShipToFirstName', 'ShipToLastName',
            'ShipToCompany', 'ShipToPhoneNumber', 'CustomerName', 'CustomerEmailAddress',
            'CustomerPhoneNumber', 'buyerName', 'buyerEmail',
        ];

        foreach ($preserveKeys as $key) {
            $newVal = $incoming[$key] ?? null;
            $oldVal = $existing[$key] ?? null;
            $newEmpty = $newVal === null || $newVal === '' || (is_array($newVal) && $newVal === []);
            $oldFilled = $oldVal !== null && $oldVal !== '' && (! is_array($oldVal) || $oldVal !== []);
            if ($newEmpty && $oldFilled) {
                $incoming[$key] = $existing[$key];
            }
        }

        return $incoming;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    protected function normalizeSheinOrder(array $order, SheinOrderMetric $line): array
    {
        // Prefer native Shein order-detail fields; fall back to Newegg-shaped clones.
        $orderId = (string) ($order['orderNo'] ?? $order['OrderNumber'] ?? $order['SellerOrderNumber'] ?? $line->order_id);

        $receive = is_array($order['receiveMsg'] ?? null) ? $order['receiveMsg'] : [];
        if ($receive === [] && is_array($order['shippingAddress'] ?? null)) {
            $receive = $order['shippingAddress'];
        }

        $first = trim((string) ($receive['firstName'] ?? $order['ShipToFirstName'] ?? ''));
        $last = trim((string) ($receive['lastName'] ?? $order['ShipToLastName'] ?? ''));
        $customerName = trim((string) (
            $receive['name']
            ?? $order['buyerName']
            ?? $order['CustomerName']
            ?? trim($first.' '.$last)
        ));
        if ($first === '' && $customerName !== '') {
            $parts = preg_split('/\s+/', $customerName, 2) ?: [];
            $first = (string) ($parts[0] ?? '');
            $last = (string) ($parts[1] ?? '');
        }
        $contact = trim($first.' '.$last);
        if ($contact === '') {
            $contact = $customerName;
        }

        $phone = trim((string) ($receive['phone'] ?? $receive['mobile'] ?? $order['CustomerPhoneNumber'] ?? $order['ShipToPhoneNumber'] ?? ''));
        $email = trim((string) ($receive['email'] ?? $order['buyerEmail'] ?? $order['CustomerEmailAddress'] ?? ''));
        $currency = strtoupper(trim((string) ($order['saleCurrency'] ?? $order['orderCurrency'] ?? $order['CurrencyCode'] ?? 'USD'))) ?: 'USD';

        $statusCode = $order['orderStatus'] ?? null;
        $statusMap = [
            1 => 'Pending', 2 => 'To Be Shipped', 3 => 'To Be Shipped by SHEIN',
            4 => 'Shipped', 5 => 'Received', 6 => 'Refund', 7 => 'To Be Collected by SHEIN',
        ];
        $status = $statusMap[(int) $statusCode]
            ?? (string) ($order['OrderStatusDescription'] ?? $order['OrderStatus'] ?? $line->status ?? '');

        $items = $order['orderGoodsInfoList'] ?? $order['ItemInfoList'] ?? $order['ItemList'] ?? [];
        if (isset($items['sellerSku']) || isset($items['skuCode']) || isset($items['SellerPartNumber'])) {
            $items = [$items];
        }
        if (! is_array($items)) {
            $items = [];
        }

        $products = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sellerSku'] ?? $item['goodsSn'] ?? $item['SellerPartNumber'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($item['skuCode'] ?? $item['SheinItemNumber'] ?? '__unknown__'));
            }
            $qty = max(1, (int) ($item['quantity'] ?? $item['OrderedQty'] ?? $item['Quantity'] ?? 1));
            $unit = isset($item['sellerCurrencyPrice']) && is_numeric($item['sellerCurrencyPrice'])
                ? (float) $item['sellerCurrencyPrice']
                : (isset($item['orderCurrencyPrice']) && is_numeric($item['orderCurrencyPrice'])
                    ? (float) $item['orderCurrencyPrice']
                    : (isset($item['UnitPrice']) && is_numeric($item['UnitPrice']) ? (float) $item['UnitPrice'] : null));
            $lineTotal = $unit !== null ? $unit * $qty : null;

            $products[] = [
                'sku_code' => $sku,
                'sku' => $sku,
                'product_id' => (string) ($item['skuCode'] ?? $item['goodsId'] ?? $item['SheinItemNumber'] ?? ''),
                'product_name' => (string) ($item['goodsTitle'] ?? $item['Description'] ?? $item['Title'] ?? $sku),
                'product_count' => $qty,
                'quantity' => $qty,
                'product_unit_price' => $unit,
                'product_price' => $unit,
                'total_product_amount' => $lineTotal,
            ];
        }

        $created = $order['orderTime'] ?? $order['paymentTime'] ?? $order['OrderDate'] ?? optional($line->order_date)?->toDateTimeString();

        $address1 = trim((string) ($receive['address'] ?? $receive['address1'] ?? $order['ShipToAddress1'] ?? ''));
        $address2 = trim((string) ($receive['address2'] ?? $order['ShipToAddress2'] ?? ''));
        $city = trim((string) ($receive['city'] ?? $order['ShipToCityName'] ?? ''));
        $province = trim((string) ($receive['province'] ?? $receive['state'] ?? $order['ShipToStateCode'] ?? ''));
        $zip = trim((string) ($receive['postCode'] ?? $receive['zipCode'] ?? $order['ShipToZipCode'] ?? ''));
        $country = trim((string) ($receive['country'] ?? $receive['countryCode'] ?? $order['ShipToCountryCode'] ?? ''));
        $company = trim((string) ($receive['company'] ?? $order['ShipToCompany'] ?? ''));

        $orderTotal = $order['productTotalPrice'] ?? $order['orderTotalInfo']['totalPrice'] ?? $order['OrderTotalAmount'] ?? null;
        $shippingAmount = $order['freight'] ?? $order['ShippingAmount'] ?? null;

        return [
            'order_id' => $orderId,
            'order_number' => $orderId,
            'order_status' => $status,
            'status' => $status,
            'gmt_create' => $created,
            'gmt_pay_time' => $order['paymentTime'] ?? $created,
            'payment_type' => 'Shein',
            'order_amount' => ['amount' => $orderTotal, 'currency_code' => $currency],
            'pay_amount' => ['amount' => $orderTotal, 'currency_code' => $currency],
            'logistics_amount' => ['amount' => $shippingAmount, 'currency_code' => $currency],
            'shipping_cost' => $shippingAmount,
            'currency_code' => $currency,
            'buyer_signer_fullname' => $contact !== '' ? $contact : null,
            'buyer_email' => $email !== '' ? $email : null,
            'buyer_info' => [
                'first_name' => $first !== '' ? $first : null,
                'last_name' => $last !== '' ? $last : null,
                'login_id' => $email !== '' ? $email : null,
                'email' => $email !== '' ? $email : null,
                'country' => $country !== '' ? $country : null,
            ],
            'receipt_address' => [
                'contact_person' => $contact !== '' ? $contact : null,
                'company' => $company !== '' ? $company : null,
                'address' => $address1,
                'address2' => $address2,
                'detail_address' => $address1 !== '' ? $address1 : null,
                'city' => $city,
                'province' => $province,
                'state' => $province,
                'zip' => $zip,
                'zip_code' => $zip,
                'country' => $country,
                'country_name' => $country,
                'mobile_no' => $phone !== '' ? $phone : null,
                'phone_number' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
            ],
            'product_list' => [
                'order_product_dto' => $products,
            ],
            'logistic_info_list' => [],
            'logistics_type' => '',
            'logistics_no' => null,
        ];
    }
}
