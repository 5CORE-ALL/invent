<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bOrder;
use App\Models\MarketplaceSyncSettings;
use App\Services\Business5CoreB2bApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Create a Shopify order for a Business 5 Core (Laravel) B2B order.
 * Uses the store selected in b5cb2b settings (default: main), not the Shopify Business 5 Core shop.
 */
class B5cB2bOrderPushService
{
    use FindsExistingShopifyOrderByChannelRef;

    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    public ?string $lastDuplicateLinkMessage = null;

    public function importToShopify(B5cB2bOrder $order): ?string
    {
        $this->lastFailureReason = null;
        $this->lastApiStatus = null;
        $this->lastDuplicateLinkMessage = null;

        $order = $this->refreshFromStore($order);

        $existingId = trim((string) ($order->shopify_order_id ?? ''));
        if ($existingId !== '') {
            $this->syncLineSkus($order, $existingId);

            return $existingId;
        }

        $status = strtolower(trim((string) ($order->status ?? '')));
        if (in_array($status, ['canceled', 'cancelled'], true)) {
            $this->lastFailureReason = 'Cancelled Business 5 Core orders are not imported.';

            return null;
        }

        if (MarketplaceOrderPaidFilter::blocksUnpaidPush('b5cb2b', $order)) {
            $this->lastFailureReason = MarketplaceOrderPaidFilter::unpaidPushBlockedMessage();

            return null;
        }

        $plan = $this->buildImportPlan($order);
        if (empty($plan['success'])) {
            $this->lastFailureReason = (string) ($plan['message'] ?? 'Could not build Shopify import plan.');

            return null;
        }

        $config = $this->shopifyConfig();
        $number = $order->channelOrderNumber();
        $shopifyOrderId = $this->postOrderGuarded(
            $config,
            ['order' => $plan['payload']],
            [$number, (string) $order->store_order_id],
            ['b5cb2b-', 'b5-'],
            ['b5cb2b_order_number', 'b5cb2b_order_id'],
            'B5cB2bOrderPushService',
            $order->shopify_order_id
        );
        if (! $shopifyOrderId) {
            return null;
        }

        $order->update([
            'shopify_order_id' => $shopifyOrderId,
            'shopify_imported_at' => $order->shopify_imported_at ?? now(),
        ]);

        $this->syncLineSkus($order->fresh() ?? $order, $shopifyOrderId);

        return $shopifyOrderId;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildImportPlan(B5cB2bOrder $order): array
    {
        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return ['success' => false, 'message' => 'Shopify store credentials are not configured for the Business 5 Core import store.'];
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $resolved = [];
        $missingSku = false;
        foreach ($order->displayLines() as $line) {
            $sku = trim((string) ($line['sku'] ?? ''));
            $qty = (int) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $title = trim((string) ($line['name'] ?? ''));
            if ($title === '') {
                $title = $sku;
            }
            if ($sku === '') {
                $missingSku = true;
                continue;
            }
            $item = [
                'title' => mb_substr($title !== '' ? $title : $sku, 0, 255),
                'quantity' => $qty,
                'price' => number_format((float) ($line['price'] ?? 0), 2, '.', ''),
                'sku' => $sku,
            ];
            $variantId = ShopifyVariantIdLookup::idForSku(
                (string) $config['store_url'],
                (string) $config['token'],
                $sku
            );
            if ($variantId) {
                $item['variant_id'] = $variantId;
            }
            $resolved[] = $item;
        }

        if ($resolved === []) {
            $message = 'Business 5 Core order '.$order->channelOrderNumber().' has no line items to import.';
            if ($missingSku) {
                $message = 'Business 5 Core order '.$order->channelOrderNumber().' has no SKU on its lines.';
            }

            return ['success' => false, 'message' => $message];
        }

        $settings = MarketplaceSyncSettings::getFor('b5cb2b');
        $number = $order->channelOrderNumber();
        $email = trim((string) ($order->customer_email ?? ($payload['customer_email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = 'b5-'.$order->store_order_id.'@orders.business5core.invalid';
        }

        [$first, $last] = $this->splitName((string) ($order->customer_name ?? ($payload['customer_name'] ?? '')));
        $shipping = $this->shippingAddress($payload, $first, $last);
        $tags = array_values(array_filter(array_unique(array_merge(
            ['b5cb2b', $number],
            is_array($settings['order']['shopify_order_tags'] ?? null) ? $settings['order']['shopify_order_tags'] : []
        ))));

        $orderPayload = [
            'email' => $email,
            'financial_status' => $this->financialStatus($order->status, $payload),
            'send_receipt' => false,
            'send_fulfillment_receipt' => false,
            'inventory_behaviour' => 'decrement_obeying_policy',
            'tags' => implode(', ', $tags),
            'note' => 'Business 5 Core order '.$number,
            'source_name' => (string) ($settings['order']['shopify_source_name'] ?? 'b5cb2b'),
            'source_identifier' => $number,
            'line_items' => $resolved,
            'customer' => [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
            ],
            'note_attributes' => [
                ['name' => 'b5cb2b_order_id', 'value' => (string) $order->store_order_id],
                ['name' => 'b5cb2b_order_number', 'value' => $number],
            ],
        ];

        if (($settings['order']['keep_order_number_from_channel'] ?? true) === true) {
            $orderPayload['name'] = $number;
        }
        if ($shipping !== []) {
            $orderPayload['shipping_address'] = $shipping;
            $orderPayload['billing_address'] = $shipping;
        }

        $shippingAmount = (float) ($payload['shipping_total'] ?? $payload['shipping_cost'] ?? $payload['shipping_amount'] ?? 0);
        if ($shippingAmount > 0) {
            $orderPayload['shipping_lines'] = [[
                'title' => (string) ($payload['shipping_method'] ?? 'Shipping'),
                'price' => number_format($shippingAmount, 2, '.', ''),
                'code' => 'b5cb2b',
            ]];
        }

        return [
            'success' => true,
            'payload' => $orderPayload,
            'order_number' => $number,
        ];
    }

    protected function refreshFromStore(B5cB2bOrder $order): B5cB2bOrder
    {
        $id = (int) $order->store_order_id;
        if ($id <= 0) {
            return $order;
        }

        try {
            $detail = app(Business5CoreB2bApiService::class)->fetchOrder($id);
        } catch (\Throwable $e) {
            Log::warning('B5cB2bOrderPushService: order detail refresh failed', [
                'store_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $order;
        }

        if ($detail === [] || B5cB2bOrder::rawLines($detail) === []) {
            return $order;
        }

        $order->update([
            'status' => $detail['status'] ?? $order->status,
            'customer_email' => $detail['customer_email'] ?? $order->customer_email,
            'customer_name' => $detail['customer_name'] ?? $order->customer_name,
            'currency' => $detail['currency'] ?? $order->currency,
            'total' => $detail['total'] ?? $order->total,
            'tracking_reference' => $detail['tracking_reference'] ?? $order->tracking_reference,
            'payload' => $detail,
        ]);

        return $order->fresh() ?? $order;
    }

    /**
     * Write each Business 5 Core line SKU onto the Shopify order line.
     * Shopify keeps the variant SKU when variant_id is set, so this corrects a blank or different SKU.
     */
    protected function syncLineSkus(B5cB2bOrder $order, string $shopifyOrderId): void
    {
        $wanted = [];
        foreach ($order->displayLines() as $line) {
            $sku = trim((string) ($line['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $wanted[] = $sku;
        }
        if ($wanted === []) {
            return;
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return;
        }

        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json';
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
            ])->timeout(30)->get($url);
        } catch (\Throwable $e) {
            Log::warning('B5cB2bOrderPushService: could not read Shopify order lines', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            return;
        }

        $existing = $response->json('order.line_items');
        if (! is_array($existing) || $existing === []) {
            return;
        }

        $updates = [];
        foreach (array_values($existing) as $index => $line) {
            if (! is_array($line) || empty($line['id'])) {
                continue;
            }
            $sku = $wanted[$index] ?? '';
            if ($sku === '') {
                continue;
            }
            if (trim((string) ($line['sku'] ?? '')) === $sku) {
                continue;
            }
            $updates[] = [
                'id' => (int) $line['id'],
                'sku' => $sku,
            ];
        }
        if ($updates === []) {
            return;
        }

        try {
            $put = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
                'Content-Type' => 'application/json',
            ])->timeout(30)->put($url, [
                'order' => [
                    'id' => (int) $shopifyOrderId,
                    'line_items' => $updates,
                ],
            ]);
            if (! $put->successful()) {
                Log::warning('B5cB2bOrderPushService: Shopify line SKU update failed', [
                    'shopify_order_id' => $shopifyOrderId,
                    'status' => $put->status(),
                    'body' => mb_substr($put->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('B5cB2bOrderPushService: Shopify line SKU update exception', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{store_url: string, token: string, store_key?: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('b5cb2b');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $payload
     */
    protected function postOrder(array $config, array $payload): ?string
    {
        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders.json';

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($url, $payload);

            $this->lastApiStatus = $response->status();
            if ($response->successful()) {
                $id = (string) ($response->json('order.id') ?? '');
                if ($id === '') {
                    $this->lastFailureReason = 'Shopify returned no order id';

                    return null;
                }

                return $id;
            }

            $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
            Log::error('B5cB2bOrderPushService: Shopify order create failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();
            Log::error('B5cB2bOrderPushService: exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    protected function lineItems(array $payload): array
    {
        foreach (['products', 'line_items', 'items', 'lines'] as $key) {
            if (is_array($payload[$key] ?? null)) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }
        if (is_array($payload['data']['products'] ?? null)) {
            return array_values(array_filter($payload['data']['products'], 'is_array'));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function financialStatus(?string $status, array $payload): string
    {
        $status = strtolower(trim((string) $status));
        $payment = strtolower(trim((string) ($payload['payment_status'] ?? '')));
        if (in_array($status, ['unpaid', 'pending_payment'], true) || in_array($payment, ['unpaid', 'pending', 'pending_payment'], true)) {
            return 'pending';
        }

        return 'paid';
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['Business', 'Customer'];
        }
        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [$parts[0] ?? 'Business', $parts[1] ?? 'Customer'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function shippingAddress(array $payload, string $first, string $last): array
    {
        $raw = is_array($payload['shipping_address'] ?? null)
            ? $payload['shipping_address']
            : (is_array($payload['shipping'] ?? null) ? $payload['shipping'] : []);
        $address1 = trim((string) ($raw['address1'] ?? $raw['address'] ?? $raw['street'] ?? $raw['line1'] ?? $payload['shipping_address1'] ?? ''));
        if ($address1 === '') {
            return [];
        }

        $country = strtoupper(trim((string) ($raw['country_code'] ?? $raw['country'] ?? $payload['shipping_country'] ?? 'US')));
        if (strlen($country) > 2) {
            $country = 'US';
        }

        return array_filter([
            'first_name' => $first,
            'last_name' => $last,
            'address1' => $address1,
            'address2' => trim((string) ($raw['address2'] ?? $raw['line2'] ?? '')),
            'city' => trim((string) ($raw['city'] ?? $payload['shipping_city'] ?? '')),
            'province' => trim((string) ($raw['province'] ?? $raw['state'] ?? $raw['province_code'] ?? '')),
            'zip' => trim((string) ($raw['zip'] ?? $raw['postal_code'] ?? $raw['postcode'] ?? '')),
            'country_code' => $country,
            'phone' => trim((string) ($raw['phone'] ?? $payload['phone'] ?? '')),
        ], static fn ($value) => $value !== '');
    }
}
