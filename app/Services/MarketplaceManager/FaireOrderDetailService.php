<?php

namespace App\Services\MarketplaceManager;

use App\Models\FaireOrderMetric;
use App\Services\FaireApiService;

/**
 * Order detail helpers for MM Faire order show / Shopify push.
 */
class FaireOrderDetailService
{
    public function __construct(
        protected FaireApiService $faireApi
    ) {}

    /**
     * @return array{order: array<string, mixed>, lines: list<array<string, mixed>>, raw: ?array}
     */
    public function detail(FaireOrderMetric $metric): array
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

        $res = $this->faireApi->getOrder($orderId);
        if (! empty($res['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare'];
        }

        if (empty($res['ok']) && empty($res['json'])) {
            // Fallback: list filter by id when single-get is unavailable.
            $list = $this->faireApi->getOrders(['limit' => 10, 'page' => 1]);
            $order = $this->findOrderInResponse($list['json'] ?? null, $orderId);
            if ($order === null) {
                return ['success' => false, 'message' => $res['error'] ?? 'Order fetch failed'];
            }
        } else {
            $json = is_array($res['json']) ? $res['json'] : [];
            $order = is_array($json['order'] ?? null) ? $json['order'] : $json;
            if (! $this->looksLikeFaireOrder($order)) {
                $order = $this->findOrderInResponse($json, $orderId) ?? $order;
            }
        }

        if (! is_array($order) || ! $this->looksLikeFaireOrder($order)) {
            return ['success' => false, 'message' => 'Order not found on Faire'];
        }

        $existing = FaireOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->value('raw_payload');
        $order = $this->mergePreservedAddress(
            is_array($existing) ? $existing : [],
            $order
        );

        FaireOrderMetric::query()
            ->where('order_id', $orderId)
            ->update([
                'raw_payload' => $order,
                'status' => (string) ($order['state'] ?? $order['status'] ?? ''),
                'order_number' => (string) ($order['display_id'] ?? $order['id'] ?? $orderId),
            ]);

        return ['success' => true, 'message' => 'Order details updated from Faire.'];
    }

    /**
     * Normalize Faire API payload into the shared MM order-root shape
     * used by FaireDetailFormatter / Shopify push.
     *
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(FaireOrderMetric $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
        if (is_array($raw['order'] ?? null)) {
            $raw = $raw['order'];
        }

        if ($raw === []) {
            return [];
        }

        if (isset($raw['receipt_address']) || isset($raw['product_list'])) {
            return $raw;
        }

        if ($this->looksLikeFaireOrder($raw)) {
            return $this->normalizeFaireOrder($raw, $line);
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<array<string, mixed>>
     */
    public function extractOrders(?array $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $list = $json['orders'] ?? $json['data'] ?? $json['items'] ?? null;
        if ($list === null && $this->looksLikeFaireOrder($json)) {
            return [$json];
        }
        if (! is_array($list)) {
            return [];
        }

        if ($this->looksLikeFaireOrder($list)) {
            return [$list];
        }

        $out = [];
        foreach ($list as $row) {
            if (is_array($row) && $this->looksLikeFaireOrder($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>|null
     */
    protected function findOrderInResponse(?array $json, string $orderId): ?array
    {
        foreach ($this->extractOrders($json) as $candidate) {
            $candidateId = trim((string) ($candidate['id'] ?? $candidate['display_id'] ?? ''));
            if ($candidateId !== '' && ($candidateId === $orderId || (string) ($candidate['display_id'] ?? '') === $orderId)) {
                return $candidate;
            }
        }

        $orders = $this->extractOrders($json);
        if (count($orders) === 1) {
            return $orders[0];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function looksLikeFaireOrder(array $row): bool
    {
        return isset($row['id'])
            || isset($row['display_id'])
            || isset($row['items'])
            || isset($row['address'])
            || isset($row['shipments'])
            || isset($row['state']);
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergePreservedAddress(array $existing, array $incoming): array
    {
        if (is_array($existing['order'] ?? null)) {
            $existing = $existing['order'];
        }

        $oldAddr = is_array($existing['address'] ?? null) ? $existing['address'] : [];
        $newAddr = is_array($incoming['address'] ?? null) ? $incoming['address'] : [];
        if ($oldAddr !== [] && $newAddr !== []) {
            foreach ($oldAddr as $key => $value) {
                $newVal = is_string($newAddr[$key] ?? null) ? trim((string) $newAddr[$key]) : ($newAddr[$key] ?? null);
                $oldVal = is_string($value) ? trim($value) : $value;
                if (($newVal === null || $newVal === '') && $oldVal !== null && $oldVal !== '') {
                    $newAddr[$key] = $value;
                }
            }
            $incoming['address'] = $newAddr;
        } elseif ($oldAddr !== [] && $newAddr === []) {
            $incoming['address'] = $oldAddr;
        }

        $oldCustomer = is_array($existing['customer'] ?? null) ? $existing['customer'] : [];
        $newCustomer = is_array($incoming['customer'] ?? null) ? $incoming['customer'] : [];
        if ($oldCustomer !== [] && $newCustomer === []) {
            $incoming['customer'] = $oldCustomer;
        }

        return $incoming;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public function normalizeFaireOrder(array $order, FaireOrderMetric $line): array
    {
        $orderId = (string) ($order['id'] ?? $line->order_id);
        $displayId = (string) ($order['display_id'] ?? $orderId);
        $address = is_array($order['address'] ?? null) ? $order['address'] : [];
        $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];

        $first = trim((string) ($address['first_name'] ?? $customer['first_name'] ?? ''));
        $last = trim((string) ($address['last_name'] ?? $customer['last_name'] ?? ''));
        $name = trim((string) ($address['name'] ?? $address['contact_name'] ?? ''));
        if ($first === '' && $name !== '') {
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $first = (string) ($parts[0] ?? '');
            $last = (string) ($parts[1] ?? $last);
        }
        $contact = trim($first.' '.$last);
        if ($contact === '') {
            $contact = $name !== '' ? $name : 'Faire Customer';
        }

        $email = trim((string) (
            $customer['email']
            ?? $address['email']
            ?? $order['customer_email']
            ?? ''
        ));
        $phone = trim((string) (
            $address['phone_number']
            ?? $address['phone']
            ?? $customer['phone_number']
            ?? $customer['phone']
            ?? ''
        ));

        $items = is_array($order['items'] ?? null) ? $order['items'] : [];
        $products = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? $item['product_variant']['sku'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($item['id'] ?? '__unknown__'));
            }
            $qty = max(1, (int) ($item['quantity'] ?? $item['includes_tester_quantity'] ?? 1));
            $amountMinor = data_get($item, 'price.amount_minor')
                ?? data_get($item, 'total_price.amount_minor')
                ?? data_get($item, 'price_cents')
                ?? data_get($item, 'total_price_cents');
            $unit = is_numeric($amountMinor) ? round(((float) $amountMinor) / 100, 2) : null;
            // Some payloads give unit price; others give line total.
            if ($unit !== null && ! empty($item['price']['amount_minor']) && empty($item['includes_tester_quantity'])) {
                // keep as unit
            }

            $products[] = [
                'sku_code' => $sku,
                'sku' => $sku,
                'product_id' => (string) (
                    $item['product_id']
                    ?? data_get($item, 'product.id')
                    ?? data_get($item, 'product_variant.id')
                    ?? ''
                ),
                'order_item_id' => (string) ($item['id'] ?? ''),
                'product_name' => (string) (
                    $item['product_name']
                    ?? data_get($item, 'product.name')
                    ?? $item['name']
                    ?? $sku
                ),
                'product_count' => $qty,
                'quantity' => $qty,
                'product_unit_price' => $unit,
                'product_price' => $unit,
                'total_product_amount' => $unit !== null ? $unit * $qty : null,
            ];
        }

        $shipments = is_array($order['shipments'] ?? null) ? $order['shipments'] : [];
        $logistics = [];
        foreach ($shipments as $shipment) {
            if (! is_array($shipment)) {
                continue;
            }
            $tracking = trim((string) (
                $shipment['tracking_code']
                ?? $shipment['tracking_number']
                ?? ''
            ));
            if ($tracking === '') {
                continue;
            }
            $logistics[] = [
                'logistics_service_name' => (string) ($shipment['carrier'] ?? 'Faire'),
                'logistics_no' => $tracking,
                'tracking_number' => $tracking,
            ];
        }

        $currency = strtoupper(trim((string) (
            data_get($order, 'payout_costs.total_payout.currency')
            ?? data_get($order, 'price.currency')
            ?? 'USD'
        ))) ?: 'USD';

        return [
            'order_id' => $orderId,
            'order_number' => $displayId,
            'id' => $orderId,
            'order_status' => (string) ($order['state'] ?? $order['status'] ?? $line->status ?? ''),
            'status' => (string) ($order['state'] ?? $order['status'] ?? $line->status ?? ''),
            'gmt_create' => $order['created_at'] ?? $order['updated_at'] ?? optional($line->order_date)?->toDateTimeString(),
            'create_time' => $order['created_at'] ?? null,
            'buyer_info' => [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'country' => $address['country'] ?? $address['country_code'] ?? null,
            ],
            'receipt_address' => [
                'contact_person' => $contact,
                'company' => $address['company_name'] ?? $address['company'] ?? null,
                'address' => $address['address1'] ?? $address['address_1'] ?? $address['street_1'] ?? null,
                'address1' => $address['address1'] ?? $address['address_1'] ?? $address['street_1'] ?? null,
                'address2' => $address['address2'] ?? $address['address_2'] ?? $address['street_2'] ?? null,
                'city' => $address['city'] ?? null,
                'province' => $address['state'] ?? $address['state_or_province'] ?? $address['province'] ?? null,
                'state' => $address['state'] ?? $address['state_or_province'] ?? $address['province'] ?? null,
                'zip' => $address['postal_code'] ?? $address['zip_code'] ?? $address['zip'] ?? null,
                'zip_code' => $address['postal_code'] ?? $address['zip_code'] ?? $address['zip'] ?? null,
                'country' => $address['country_code'] ?? $address['country'] ?? null,
                'country_name' => $address['country'] ?? null,
                'phone' => $phone,
                'mobile_no' => $phone,
                'email' => $email,
            ],
            'product_list' => $products,
            'logistics_info_list' => $logistics,
            'currency' => $currency,
            'payment_type' => $order['payment_initiated_at'] ?? null,
            '_faire_raw' => $order,
        ];
    }
}
