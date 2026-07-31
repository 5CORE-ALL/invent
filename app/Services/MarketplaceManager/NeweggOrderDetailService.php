<?php

namespace App\Services\MarketplaceManager;

use App\Models\NeweggOrderMetric;
use App\Services\NeweggApiService;
use Illuminate\Support\Facades\Log;

/**
 * Order detail helpers for MM Newegg order show / Shopify push.
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
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Order ID is required.'];
        }

        // Match FetchNeweggOrders / Newegg docs: OrderNumberList.OrderNumber[]
        $res = $this->neweggApi->getOrders([
            'OrderNumberList' => [
                'OrderNumber' => [$orderId],
            ],
        ], 1, 10);

        if (! empty($res['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare'];
        }

        if (empty($res['ok']) && empty($res['json'])) {
            return ['success' => false, 'message' => $res['error'] ?? 'Order fetch failed'];
        }

        $order = $this->findOrderInResponse($res['json'] ?? null, $orderId);
        if ($order === null) {
            return ['success' => false, 'message' => 'Order not found on Newegg'];
        }

        // Never wipe a good cached ShipTo address with a blank/privacy-stripped refresh.
        $existing = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->value('raw_payload');
        $order = $this->mergePreservedShipTo(
            is_array($existing) ? $existing : [],
            $order
        );

        $status = (string) ($order['OrderStatus'] ?? $order['OrderStatusDescription'] ?? '');

        // Same persistence shape as AliExpress / Reverb: wrap under raw['order'].
        $lines = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->get();

        if ($lines->isEmpty()) {
            return ['success' => false, 'message' => 'Order not found in local database.'];
        }

        foreach ($lines as $line) {
            $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
            $raw['order'] = $order;
            $raw['order_detail_fetched_at'] = now()->toIso8601String();
            $line->update([
                'raw_payload' => $raw,
                'status' => $status,
            ]);
        }

        Log::info('NeweggOrderDetailService: persisted order detail', [
            'order_id' => $orderId,
            'lines' => $lines->count(),
        ]);

        return ['success' => true, 'order' => $order, 'message' => 'Order details updated from Newegg.'];
    }

    /**
     * Normalize Newegg API payload into the shared MM order-root shape
     * used by NeweggDetailFormatter / Shopify push.
     *
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(NeweggOrderMetric $line): array
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

        // Newegg OrderInfo shape.
        if (
            isset($raw['OrderNumber'])
            || isset($raw['SellerOrderNumber'])
            || isset($raw['ShipToAddress1'])
            || isset($raw['ItemInfoList'])
            || isset($raw['ItemList'])
            || isset($raw['CustomerName'])
            || isset($raw['CustomerEmailAddress'])
        ) {
            return $this->normalizeNeweggOrder($raw, $line);
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
     * Unwrap Newegg OrderInfoList / OrderInfo envelopes into a flat list of orders.
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
        return isset($row['OrderNumber'])
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
        // Cached raw_payload may nest under "order" (same shape as AE/Reverb).
        if (is_array($existing['order'] ?? null)) {
            $existing = $existing['order'];
        }
        $existing = $this->unwrapOrderInfoNode($existing);
        if (! $this->looksLikeOrderInfo($existing)) {
            return $incoming;
        }

        $preserveKeys = [
            'ShipToAddress1',
            'ShipToAddress2',
            'ShipToCityName',
            'ShipToStateCode',
            'ShipToZipCode',
            'ShipToCountryCode',
            'ShipToFirstName',
            'ShipToLastName',
            'ShipToCompany',
            'ShipToPhoneNumber',
            'CustomerName',
            'CustomerEmailAddress',
            'CustomerPhoneNumber',
        ];

        foreach ($preserveKeys as $key) {
            $newVal = trim((string) ($incoming[$key] ?? ''));
            $oldVal = trim((string) ($existing[$key] ?? ''));
            if ($newVal === '' && $oldVal !== '') {
                $incoming[$key] = $existing[$key];
            }
        }

        return $incoming;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    protected function normalizeNeweggOrder(array $order, NeweggOrderMetric $line): array
    {
        $orderId = (string) ($order['OrderNumber'] ?? $order['SellerOrderNumber'] ?? $line->order_id);
        $first = trim((string) ($order['ShipToFirstName'] ?? ''));
        $last = trim((string) ($order['ShipToLastName'] ?? ''));
        $customerName = trim((string) ($order['CustomerName'] ?? ''));
        if ($first === '' && $customerName !== '') {
            $parts = preg_split('/\s+/', $customerName, 2) ?: [];
            $first = (string) ($parts[0] ?? '');
            $last = (string) ($parts[1] ?? '');
        }
        $contact = trim($first.' '.$last);
        if ($contact === '') {
            $contact = $customerName;
        }

        $phone = trim((string) ($order['CustomerPhoneNumber'] ?? $order['ShipToPhoneNumber'] ?? ''));
        $email = trim((string) ($order['CustomerEmailAddress'] ?? ''));
        $currency = strtoupper(trim((string) ($order['CurrencyCode'] ?? 'USD'))) ?: 'USD';
        $status = (string) ($order['OrderStatusDescription'] ?? $order['OrderStatus'] ?? $line->status ?? '');

        $items = $order['ItemInfoList'] ?? $order['ItemList'] ?? [];
        if (isset($items['SellerPartNumber']) || isset($items['NeweggItemNumber'])) {
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
            $sku = trim((string) ($item['SellerPartNumber'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($item['NeweggItemNumber'] ?? '__unknown__'));
            }
            $qty = max(1, (int) ($item['OrderedQty'] ?? $item['Quantity'] ?? 1));
            $unit = isset($item['UnitPrice']) && is_numeric($item['UnitPrice'])
                ? (float) $item['UnitPrice']
                : null;
            if ($unit === null && isset($item['ExtendUnitPrice']) && is_numeric($item['ExtendUnitPrice'])) {
                $unit = (float) $item['ExtendUnitPrice'] / $qty;
            }
            $lineTotal = isset($item['ExtendUnitPrice']) && is_numeric($item['ExtendUnitPrice'])
                ? (float) $item['ExtendUnitPrice']
                : ($unit !== null ? $unit * $qty : null);

            $products[] = [
                'sku_code' => $sku,
                'sku' => $sku,
                'product_id' => (string) ($item['NeweggItemNumber'] ?? ''),
                'product_name' => (string) ($item['Description'] ?? $item['Title'] ?? $sku),
                'product_count' => $qty,
                'quantity' => $qty,
                'product_unit_price' => $unit,
                'product_price' => $unit,
                'total_product_amount' => $lineTotal,
            ];
        }

        $packages = $order['PackageInfoList'] ?? [];
        if (isset($packages['TrackingNumber']) || isset($packages['ShipCarrier'])) {
            $packages = [$packages];
        }
        if (! is_array($packages)) {
            $packages = [];
        }

        $logistics = [];
        foreach ($packages as $pkg) {
            if (! is_array($pkg)) {
                continue;
            }
            $tracking = trim((string) ($pkg['TrackingNumber'] ?? $pkg['tracking_number'] ?? ''));
            if ($tracking === '') {
                continue;
            }
            $logistics[] = [
                'logistics_service_name' => (string) (
                    $pkg['ShipCarrier']
                    ?? $pkg['ShipService']
                    ?? $order['ShipService']
                    ?? 'Newegg'
                ),
                'logistics_no' => $tracking,
                'tracking_number' => $tracking,
            ];
        }

        $orderTotal = $order['OrderTotalAmount'] ?? null;
        $itemAmount = $order['OrderItemAmount'] ?? null;
        $shippingAmount = $order['ShippingAmount'] ?? null;
        $discount = $order['DiscountAmount'] ?? null;
        $tax = $order['SalesTax'] ?? $order['TaxAmount'] ?? $order['VATTotal'] ?? null;
        $created = $order['OrderDate'] ?? $order['OrderDownloadedOn'] ?? optional($line->order_date)?->toDateTimeString();

        $address1 = trim((string) ($order['ShipToAddress1'] ?? ''));
        $address2 = trim((string) ($order['ShipToAddress2'] ?? ''));
        $city = trim((string) ($order['ShipToCityName'] ?? ''));
        $province = trim((string) ($order['ShipToStateCode'] ?? ''));
        $zip = trim((string) ($order['ShipToZipCode'] ?? ''));
        $country = trim((string) ($order['ShipToCountryCode'] ?? ''));
        $company = trim((string) ($order['ShipToCompany'] ?? ''));

        return [
            'order_id' => $orderId,
            'order_number' => $orderId,
            'order_status' => $status,
            'status' => $status,
            'gmt_create' => $created,
            'gmt_pay_time' => $created,
            'payment_type' => (string) ($order['PaymentMethod'] ?? $order['PaymentType'] ?? 'Newegg'),
            'order_amount' => ['amount' => $orderTotal, 'currency_code' => $currency],
            'pay_amount' => ['amount' => $orderTotal ?? $itemAmount, 'currency_code' => $currency],
            'logistics_amount' => ['amount' => $shippingAmount, 'currency_code' => $currency],
            'shipping_cost' => $shippingAmount,
            'discount_amount' => ['amount' => $discount, 'currency_code' => $currency],
            'tax_amount' => ['amount' => $tax, 'currency_code' => $currency],
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
            'logistic_info_list' => $logistics,
            'logistics_type' => (string) ($order['ShipService'] ?? ''),
            'logistics_no' => $logistics[0]['logistics_no'] ?? null,
        ];
    }
}
