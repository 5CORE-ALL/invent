<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2OrderMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Order detail helpers for MM eBay 2 order show / Shopify push.
 */
class Ebay2OrderDetailService
{
    /**
     * @return array{order: array<string, mixed>, lines: list<array<string, mixed>>, raw: ?array}
     */
    public function detail(Ebay2OrderMetric $metric): array
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

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'eBay 2 token unavailable.'];
        }

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->timeout(45)
                ->get('https://api.ebay.com/sell/fulfillment/v1/order/'.rawurlencode($orderId));

            if (! $response->successful()) {
                return ['success' => false, 'message' => 'Order fetch failed: '.$response->body()];
            }

            $order = $response->json();
            if (! is_array($order)) {
                return ['success' => false, 'message' => 'Order not found on eBay 2'];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Order fetch failed: '.$e->getMessage()];
        }

        $existing = Ebay2OrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->value('raw_payload');
        $order = $this->mergePreservedShipTo(
            is_array($existing) ? $existing : [],
            $order
        );

        $status = trim((string) ($order['orderFulfillmentStatus'] ?? $order['orderPaymentStatus'] ?? ''));
        $orderDate = null;
        if (! empty($order['creationDate'])) {
            try {
                $orderDate = Carbon::parse($order['creationDate']);
            } catch (\Throwable $e) {
                $orderDate = null;
            }
        }

        $lineItems = is_array($order['lineItems'] ?? null) ? $order['lineItems'] : [];
        if ($lineItems === []) {
            Ebay2OrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => '__order__'],
                [
                    'order_number' => trim((string) ($order['legacyOrderId'] ?? $orderId)),
                    'order_date' => $orderDate,
                    'status' => $status,
                    'raw_payload' => $order,
                    'import_status' => 'ready',
                ]
            );
        } else {
            foreach ($lineItems as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $sku = trim((string) ($line['sku'] ?? $line['legacyItemId'] ?? '__unknown__'));
                Ebay2OrderMetric::updateOrCreate(
                    ['order_id' => $orderId, 'sku' => $sku],
                    [
                        'order_number' => trim((string) ($order['legacyOrderId'] ?? $orderId)),
                        'order_date' => $orderDate,
                        'status' => $status,
                        'product_id' => trim((string) ($line['legacyItemId'] ?? '')),
                        'display_title' => trim((string) ($line['title'] ?? '')),
                        'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                        'amount' => isset($line['lineItemCost']['value']) ? (float) $line['lineItemCost']['value'] : null,
                        'raw_payload' => $order,
                        'import_status' => 'ready',
                    ]
                );
            }
        }

        return ['success' => true, 'message' => 'Order details updated from eBay 2.'];
    }

    /**
     * Normalize eBay Fulfillment payload into the shared MM order-root shape.
     *
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(Ebay2OrderMetric $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
        if ($raw === []) {
            return [
                'order_id' => (string) $line->order_id,
                'order_number' => (string) ($line->order_number ?: $line->order_id),
                'status' => (string) ($line->status ?? ''),
                'order_date' => optional($line->order_date)?->toDateTimeString(),
                'line_items' => [],
                'shipping_address' => [],
                'buyer' => [],
                'raw' => [],
            ];
        }

        return $this->normalizeEbay2Order($raw, $line);
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
        if (isset($json['orderId'])) {
            return [$json];
        }
        if (isset($json['orders']) && is_array($json['orders'])) {
            return array_values(array_filter($json['orders'], 'is_array'));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    protected function normalizeEbay2Order(array $order, Ebay2OrderMetric $line): array
    {
        $ship = $this->extractShipTo($order);
        $buyer = is_array($order['buyer'] ?? null) ? $order['buyer'] : [];
        $buyerEmail = $buyer['buyerRegistrationAddress']['email'] ?? ($buyer['email'] ?? null);

        $items = [];
        foreach (is_array($order['lineItems'] ?? null) ? $order['lineItems'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? $item['legacyItemId'] ?? '__unknown__'));
            $items[] = [
                'sku' => $sku,
                'product_id' => (string) ($item['legacyItemId'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'price' => isset($item['lineItemCost']['value']) ? (float) $item['lineItemCost']['value'] : null,
                'raw' => $item,
            ];
        }

        if ($items === [] && $line->sku) {
            $items[] = [
                'sku' => (string) $line->sku,
                'product_id' => (string) ($line->product_id ?? ''),
                'title' => (string) ($line->display_title ?? ''),
                'quantity' => max(1, (int) ($line->quantity ?? 1)),
                'price' => $line->amount !== null ? (float) $line->amount : null,
                'raw' => [],
            ];
        }

        $nameParts = preg_split('/\s+/', trim((string) ($ship['fullName'] ?? '')), 2) ?: [];
        $firstName = $nameParts[0] ?? null;
        $lastName = $nameParts[1] ?? null;
        $total = isset($order['pricingSummary']['total']['value'])
            ? (float) $order['pricingSummary']['total']['value']
            : null;
        $currency = (string) ($order['pricingSummary']['total']['currency'] ?? 'USD');

        // Keys match Ebay2DetailFormatter::formatOrder (Shein-shaped aliases).
        $receiptAddress = [
            'contact_person' => $ship['fullName'] ?? null,
            'address' => $ship['addressLine1'] ?? null,
            'address1' => $ship['addressLine1'] ?? null,
            'address2' => $ship['addressLine2'] ?? null,
            'city' => $ship['city'] ?? null,
            'province' => $ship['stateOrProvince'] ?? null,
            'state' => $ship['stateOrProvince'] ?? null,
            'zip' => $ship['postalCode'] ?? null,
            'zip_code' => $ship['postalCode'] ?? null,
            'country' => $ship['countryCode'] ?? null,
            'phone' => $ship['phone'] ?? null,
            'email' => $buyerEmail,
        ];

        return [
            'order_id' => (string) ($order['orderId'] ?? $line->order_id),
            'order_number' => (string) ($order['legacyOrderId'] ?? $line->order_number ?? $line->order_id),
            'status' => (string) ($order['orderFulfillmentStatus'] ?? $line->status ?? ''),
            'order_status' => (string) ($order['orderFulfillmentStatus'] ?? $line->status ?? ''),
            'payment_status' => (string) ($order['orderPaymentStatus'] ?? ''),
            'order_date' => (string) ($order['creationDate'] ?? optional($line->order_date)?->toIso8601String() ?? ''),
            'gmt_create' => (string) ($order['creationDate'] ?? ''),
            'payment_type' => 'eBay 2',
            'currency' => $currency,
            'total' => $total,
            'order_amount' => $total,
            'pay_amount' => $total,
            'buyer_info' => [
                'email' => $buyerEmail,
                'login_id' => $buyer['username'] ?? null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $ship['phone'] ?? null,
                'country' => $ship['countryCode'] ?? null,
            ],
            'buyer' => [
                'email' => $buyerEmail,
                'username' => $buyer['username'] ?? null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $ship['phone'] ?? null,
            ],
            'receipt_address' => $receiptAddress,
            'shipping_address' => [
                'name' => $ship['fullName'] ?? null,
                'address1' => $ship['addressLine1'] ?? null,
                'address2' => $ship['addressLine2'] ?? null,
                'city' => $ship['city'] ?? null,
                'province' => $ship['stateOrProvince'] ?? null,
                'zip' => $ship['postalCode'] ?? null,
                'country' => $ship['countryCode'] ?? null,
                'phone' => $ship['phone'] ?? null,
            ],
            'shipment' => [
                'tracking' => null,
                'service' => null,
            ],
            'line_items' => $items,
            'raw' => $order,
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    protected function extractShipTo(array $order): array
    {
        $instr = $order['fulfillmentStartInstructions'][0] ?? [];
        $shipTo = is_array($instr['shippingStep']['shipTo'] ?? null)
            ? $instr['shippingStep']['shipTo']
            : [];
        $addr = is_array($shipTo['contactAddress'] ?? null) ? $shipTo['contactAddress'] : [];

        return [
            'fullName' => $shipTo['fullName'] ?? null,
            'addressLine1' => $addr['addressLine1'] ?? null,
            'addressLine2' => $addr['addressLine2'] ?? null,
            'city' => $addr['city'] ?? null,
            'stateOrProvince' => $addr['stateOrProvince'] ?? null,
            'postalCode' => $addr['postalCode'] ?? null,
            'countryCode' => $addr['countryCode'] ?? null,
            'phone' => $shipTo['primaryPhone']['phoneNumber'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    protected function mergePreservedShipTo(array $existing, array $incoming): array
    {
        $existingInstr = $existing['fulfillmentStartInstructions'] ?? null;
        $incomingInstr = $incoming['fulfillmentStartInstructions'] ?? null;
        if (($incomingInstr === null || $incomingInstr === []) && is_array($existingInstr) && $existingInstr !== []) {
            $incoming['fulfillmentStartInstructions'] = $existingInstr;
        }

        return $incoming;
    }

    protected function getAccessToken(): ?string
    {
        $clientId = config('services.ebay2.app_id');
        $clientSecret = config('services.ebay2.cert_id');
        $refreshToken = config('services.ebay2.refresh_token');
        if (! $clientId || ! $clientSecret || ! $refreshToken) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout(30)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            return $response->successful() ? $response->json('access_token') : null;
        } catch (\Throwable $e) {
            Log::warning('Ebay2OrderDetailService: token failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
