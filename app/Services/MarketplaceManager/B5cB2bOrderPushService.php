<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bOrder;
use App\Models\MarketplaceSyncSettings;
use App\Services\Business5CoreB2bApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Create a Shopify order for a Business 5 Core (Laravel) B2B order.
 * Uses the store selected in b5cb2b settings (default: main), not the Shopify Business 5 Core shop.
 */
class B5cB2bOrderPushService
{
    use FindsExistingShopifyOrderByChannelRef;

    public const SHOPIFY_TAG = 'Business 5 Core (B2B)';

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
            [$number],
            ['b5cb2b-'],
            ['b5cb2b_order_number'],
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
        $pending = [];
        $missingSku = false;
        foreach ($order->displayLines() as $line) {
            $sku = trim((string) ($line['sku'] ?? ''));
            $qty = (int) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            if ($sku === '') {
                $missingSku = true;
                continue;
            }
            $title = trim((string) ($line['name'] ?? ''));
            $pending[] = [
                'sku' => $sku,
                'qty' => $qty,
                'title' => $title !== '' ? $title : $sku,
                'price' => (float) ($line['price'] ?? 0),
            ];
        }
        $variantIds = ShopifyVariantIdLookup::idsForSkus(
            (string) $config['store_url'],
            (string) $config['token'],
            array_column($pending, 'sku')
        );
        $resolved = [];
        foreach ($pending as $line) {
            $item = [
                'title' => mb_substr($line['title'], 0, 255),
                'quantity' => $line['qty'],
                'price' => number_format($line['price'], 2, '.', ''),
                'sku' => $line['sku'],
            ];
            if (! empty($variantIds[$line['sku']])) {
                $item['variant_id'] = $variantIds[$line['sku']];
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
        $extraTags = is_array($settings['order']['shopify_order_tags'] ?? null) ? $settings['order']['shopify_order_tags'] : [];
        $tags = self::shopifyTags($number, $extraTags);

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
     * Tags written on the Shopify order. The channel slug is never used as a visible tag.
     *
     * @param  array<int, mixed>  $extra
     * @return list<string>
     */
    public static function shopifyTags(string $orderNumber, array $extra = []): array
    {
        $tags = [];
        foreach (array_merge([self::SHOPIFY_TAG, $orderNumber], $extra) as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '' || strcasecmp($tag, 'b5cb2b') === 0) {
                continue;
            }
            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @return array{tags: string, changed: bool}
     */
    public static function rewriteTagList(string $csv): array
    {
        $tags = [];
        $changed = false;
        foreach (preg_split('/\s*,\s*/', $csv) ?: [] as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            if (strcasecmp($tag, 'b5cb2b') === 0) {
                $tag = self::SHOPIFY_TAG;
                $changed = true;
            }
            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }
        if (! in_array(self::SHOPIFY_TAG, $tags, true)) {
            $tags[] = self::SHOPIFY_TAG;
            $changed = true;
        }

        return ['tags' => implode(', ', $tags), 'changed' => $changed];
    }

    /**
     * Replace the b5cb2b tag on an order that is already in Shopify.
     *
     * @return array{success: bool, changed: bool, cached: bool, message: string}
     */
    public function renameShopifyTag(string $shopifyOrderId): array
    {
        $shopifyOrderId = trim($shopifyOrderId);
        if ($shopifyOrderId === '') {
            return ['success' => false, 'changed' => false, 'cached' => false, 'message' => 'No Shopify order.'];
        }

        $cacheKey = 'b5cb2b-display-tag:'.$shopifyOrderId;
        if (Cache::get($cacheKey)) {
            return ['success' => true, 'changed' => false, 'cached' => true, 'message' => 'Tag already updated.'];
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return ['success' => false, 'changed' => false, 'cached' => false, 'message' => 'Shopify store credentials are not configured.'];
        }

        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json?fields=id,tags';
        $response = $this->shopifySend('GET', $url, $config);
        if ($response === null || ! $response->successful()) {
            $status = $response ? $response->status() : 0;

            return ['success' => false, 'changed' => false, 'cached' => false, 'message' => 'Could not read Shopify tags'.($status ? ' (HTTP '.$status.')' : '').'.'];
        }

        $rewritten = self::rewriteTagList((string) $response->json('order.tags'));
        if (! $rewritten['changed']) {
            Cache::put($cacheKey, 1, now()->addDays(30));

            return ['success' => true, 'changed' => false, 'cached' => false, 'message' => 'Tag already updated.'];
        }

        sleep(1);
        $put = $this->shopifySend('PUT', 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json', $config, [
            'order' => [
                'id' => (int) $shopifyOrderId,
                'tags' => $rewritten['tags'],
            ],
        ]);
        if ($put === null || ! $put->successful()) {
            $status = $put ? $put->status() : 0;
            Log::warning('B5cB2bOrderPushService: Shopify tag update failed', [
                'shopify_order_id' => $shopifyOrderId,
                'status' => $status,
                'body' => $put ? mb_substr($put->body(), 0, 300) : null,
            ]);

            return ['success' => false, 'changed' => false, 'cached' => false, 'message' => 'Shopify tag update failed'.($status ? ' (HTTP '.$status.')' : '').'.'];
        }

        Cache::put($cacheKey, 1, now()->addDays(30));

        return ['success' => true, 'changed' => true, 'cached' => false, 'message' => 'Tag updated.'];
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $body
     */
    protected function shopifySend(string $method, string $url, array $config, array $body = []): ?Response
    {
        $pending = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['token'],
            'Content-Type' => 'application/json',
        ])->timeout(30);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $response = strtoupper($method) === 'PUT'
                    ? $pending->put($url, $body)
                    : $pending->get($url);
                if ($response->status() === 429 && $attempt < 5) {
                    $wait = (int) ($response->header('Retry-After') ?: (2 * $attempt));
                    sleep(max(2, min(20, $wait)));

                    continue;
                }

                return $response;
            } catch (\Throwable $e) {
                Log::warning('B5cB2bOrderPushService: Shopify tag request failed', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);
                if ($attempt >= 5) {
                    return null;
                }
                sleep(2 * $attempt);
            }
        }

        return null;
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

        sleep(1);
        $id = $this->postOrderOnce($config, $payload, $url);
        if ($id !== null || $this->lastApiStatus !== 422) {
            return $id;
        }

        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        unset($order['name'], $order['source_name']);
        $order['inventory_behaviour'] = 'bypass';
        $lines = [];
        foreach (is_array($order['line_items'] ?? null) ? $order['line_items'] : [] as $line) {
            if (! is_array($line)) {
                continue;
            }
            unset($line['variant_id']);
            $lines[] = $line;
        }
        if ($lines !== []) {
            $order['line_items'] = $lines;
        }

        return $this->postOrderOnce($config, ['order' => $order], $url);
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $payload
     */
    protected function postOrderOnce(array $config, array $payload, string $url): ?string
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
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

                if ($response->status() === 429 && $attempt < 5) {
                    $wait = (int) ($response->header('Retry-After') ?: (2 * $attempt));
                    sleep(max(2, min(20, $wait)));

                    continue;
                }

                $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
                Log::error('B5cB2bOrderPushService: Shopify order create failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'attempt' => $attempt,
                ]);

                return null;
            } catch (\Throwable $e) {
                $this->lastFailureReason = $e->getMessage();
                $this->lastApiStatus = null;
                Log::error('B5cB2bOrderPushService: exception', ['error' => $e->getMessage()]);
                if ($attempt >= 5) {
                    return null;
                }
                sleep(2 * $attempt);
            }
        }

        return null;
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
