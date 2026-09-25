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

    public bool $lastCreated = false;

    public bool $lastLinkWasCached = false;

    public function importToShopify(B5cB2bOrder $order): ?string
    {
        $this->lastFailureReason = null;
        $this->lastApiStatus = null;
        $this->lastDuplicateLinkMessage = null;
        $this->lastCreated = false;
        $this->lastLinkWasCached = false;

        $order = $this->refreshFromStore($order);

        $existingId = trim((string) ($order->shopify_order_id ?? ''));
        if ($existingId !== '') {
            $cacheKey = 'b5cb2b-shopify-link:'.(int) $order->store_order_id.':'.$existingId;
            if (Cache::get($cacheKey)) {
                $this->lastLinkWasCached = true;
                if ($this->syncAddressToShopify($order, $existingId)) {
                    $this->lastLinkWasCached = false;
                }

                return $existingId;
            }

            $belongs = $this->shopifyOrderBelongsTo($order, $existingId);
            if ($belongs === true) {
                Cache::put($cacheKey, 1, now()->addDays(30));
                $this->syncLineSkus($order, $existingId);
                $this->syncAddressToShopify($order, $existingId);

                return $existingId;
            }
            if ($belongs === null) {
                $this->lastFailureReason = 'Could not verify the saved Shopify order, so it was left unchanged.';

                return $existingId;
            }

            Log::warning('B5cB2bOrderPushService: saved Shopify id is not this Business 5 Core order', [
                'store_order_id' => $order->store_order_id,
                'channel_order' => $order->channelOrderNumber(),
                'shopify_order_id' => $existingId,
            ]);
            $order->update([
                'shopify_order_id' => null,
                'shopify_imported_at' => null,
            ]);
            $order->shopify_order_id = null;
            Cache::forget('b5cb2b-display-tag:'.$existingId);
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
        $this->lastCreated = true;
        Cache::put('b5cb2b-shopify-link:'.(int) $order->store_order_id.':'.$shopifyOrderId, 1, now()->addDays(30));
        $createdAddress = is_array($plan['payload']['shipping_address'] ?? null) ? $plan['payload']['shipping_address'] : [];
        if ($createdAddress !== []) {
            Cache::put($this->addressCacheKey($order, $createdAddress), 1, now()->addDays(30));
        }

        $this->syncLineSkus($order->fresh() ?? $order, $shopifyOrderId);

        return $shopifyOrderId;
    }

    /**
     * True when this Shopify order is the Business 5 Core order. False when it is missing or a different order.
     * Null when Shopify could not be checked.
     *
     * @param  array<string, mixed>  $shopifyOrder
     */
    public static function shopifyOrderRecordMatchesChannel(array $shopifyOrder, string $channelNumber, int $storeOrderId): bool
    {
        $channelNumber = strtoupper(trim($channelNumber));
        $name = strtoupper(ltrim(trim((string) ($shopifyOrder['name'] ?? '')), '#'));
        if ($channelNumber !== '' && $name === $channelNumber) {
            return true;
        }

        $tags = $shopifyOrder['tags'] ?? '';
        if (is_array($tags)) {
            $tags = implode(',', $tags);
        }
        foreach (preg_split('/\s*,\s*/', (string) $tags) ?: [] as $tag) {
            if (strtoupper(trim((string) $tag)) === $channelNumber) {
                return true;
            }
        }

        $notes = $shopifyOrder['note_attributes'] ?? [];
        if (! is_array($notes)) {
            return false;
        }
        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }
            $key = (string) ($note['name'] ?? '');
            $value = trim((string) ($note['value'] ?? ''));
            if ($key === 'b5cb2b_order_number' && $channelNumber !== '' && strtoupper($value) === $channelNumber) {
                return true;
            }
            if ($key === 'b5cb2b_order_id' && $storeOrderId > 0 && (int) $value === $storeOrderId) {
                return true;
            }
        }

        return false;
    }

    protected function shopifyOrderBelongsTo(B5cB2bOrder $order, string $shopifyOrderId): ?bool
    {
        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return null;
        }

        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json?fields=id,name,tags,note,note_attributes';
        $response = $this->shopifySend('GET', $url, $config);
        if ($response === null) {
            return null;
        }
        if ($response->status() === 404) {
            return false;
        }
        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json('order');
        if (! is_array($payload)) {
            return false;
        }

        return self::shopifyOrderRecordMatchesChannel(
            $payload,
            $order->channelOrderNumber(),
            (int) $order->store_order_id
        );
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
        $shipping = self::shopifyAddressFromPayload($payload, $first, $last);
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
     * @return array{success: bool, changed: bool, cached: bool, clear_link: bool, message: string}
     */
    public function renameShopifyTag(string $shopifyOrderId, string $channelNumber = '', int $storeOrderId = 0): array
    {
        $shopifyOrderId = trim($shopifyOrderId);
        if ($shopifyOrderId === '') {
            return ['success' => false, 'changed' => false, 'cached' => false, 'clear_link' => false, 'message' => 'No Shopify order.'];
        }

        $cacheKey = 'b5cb2b-display-tag:'.$shopifyOrderId;
        if (Cache::get($cacheKey)) {
            return ['success' => true, 'changed' => false, 'cached' => true, 'clear_link' => false, 'message' => 'Tag already updated.'];
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return ['success' => false, 'changed' => false, 'cached' => false, 'clear_link' => false, 'message' => 'Shopify store credentials are not configured.'];
        }

        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json?fields=id,name,tags,note,note_attributes';
        $response = $this->shopifySend('GET', $url, $config);
        if ($response === null || ! $response->successful()) {
            $status = $response ? $response->status() : 0;
            $missing = $status === 404;

            return ['success' => false, 'changed' => false, 'cached' => false, 'clear_link' => $missing, 'message' => 'Could not read Shopify tags'.($status ? ' (HTTP '.$status.')' : '').'.'];
        }

        $shopifyOrder = $response->json('order');
        if ($channelNumber !== '' && is_array($shopifyOrder) && ! self::shopifyOrderRecordMatchesChannel($shopifyOrder, $channelNumber, $storeOrderId)) {
            return ['success' => false, 'changed' => false, 'cached' => false, 'clear_link' => true, 'message' => 'Saved Shopify id is not '.$channelNumber.'.'];
        }

        $rewritten = self::rewriteTagList((string) (is_array($shopifyOrder) ? ($shopifyOrder['tags'] ?? '') : ''));
        if (! $rewritten['changed']) {
            Cache::put($cacheKey, 1, now()->addDays(30));

            return ['success' => true, 'changed' => false, 'cached' => false, 'clear_link' => false, 'message' => 'Tag already updated.'];
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

            return ['success' => false, 'changed' => false, 'cached' => false, 'clear_link' => false, 'message' => 'Shopify tag update failed'.($status ? ' (HTTP '.$status.')' : '').'.'];
        }

        Cache::put($cacheKey, 1, now()->addDays(30));

        return ['success' => true, 'changed' => true, 'cached' => false, 'clear_link' => false, 'message' => 'Tag updated.'];
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
     * Business 5 Core sends the street as a string or under several address keys.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function shopifyAddressFromPayload(array $payload, string $first, string $last): array
    {
        $bags = [];
        foreach (['shipping_address', 'shipping', 'ship_to', 'delivery_address', 'delivery', 'address', 'billing_address'] as $key) {
            if (is_array($payload[$key] ?? null)) {
                $bags[] = $payload[$key];
            }
        }
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        foreach (['shipping_address', 'address', 'shipping'] as $key) {
            if (is_array($customer[$key] ?? null)) {
                $bags[] = $customer[$key];
            }
        }
        $bags[] = $payload;

        $address1 = self::firstAddressValue($bags, [
            'address1', 'address_1', 'address_line_1', 'address_line1', 'line1', 'line_1',
            'street', 'street1', 'street_address', 'address',
        ]);
        if ($address1 === '' && is_string($payload['shipping_address'] ?? null)) {
            $address1 = trim($payload['shipping_address']);
        }
        if ($address1 === '' && is_string($customer['address'] ?? null)) {
            $address1 = trim($customer['address']);
        }
        if ($address1 === '') {
            return [];
        }

        $address2 = self::firstAddressValue($bags, ['address2', 'address_2', 'address_line_2', 'address_line2', 'line2', 'line_2', 'street2']);
        $city = self::firstAddressValue($bags, ['city', 'shipping_city', 'town']);
        $province = self::firstAddressValue($bags, ['province_code', 'province', 'state', 'shipping_state', 'shipping_province', 'region']);
        $zip = self::firstAddressValue($bags, ['zip', 'zip_code', 'postal_code', 'postcode', 'shipping_zip', 'shipping_postal_code', 'shipping_postcode']);
        $phone = self::firstAddressValue($bags, ['phone', 'shipping_phone', 'customer_phone', 'telephone', 'mobile']);
        $country = self::firstAddressValue($bags, ['country_code', 'country', 'shipping_country', 'shipping_country_code']);
        $country = self::countryCode($country);

        if ($city === '' && $zip === '' && str_contains($address1, ',')) {
            if (preg_match('/^(.+?),\s*([^,]+),\s*([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)$/', $address1, $match) === 1) {
                $address1 = trim($match[1]);
                $city = trim($match[2]);
                $province = strtoupper($match[3]);
                $zip = $match[4];
            }
        }

        $address = array_filter([
            'first_name' => $first,
            'last_name' => $last,
            'address1' => $address1,
            'address2' => $address2,
            'city' => $city,
            'province' => $province,
            'zip' => $zip,
            'country_code' => $country,
            'phone' => $phone,
        ], static fn ($value) => $value !== '');
        if (strlen($province) === 2) {
            $address['province_code'] = strtoupper($province);
            $address['province'] = strtoupper($province);
        }

        return $address;
    }

    /**
     * Write the Business 5 Core ship-to onto an order that is already in Shopify.
     * Returns true when a Shopify request was made.
     *
     * @return bool
     */
    public function syncAddressToShopify(B5cB2bOrder $order, string $shopifyOrderId): bool
    {
        $shopifyOrderId = trim($shopifyOrderId);
        if ($shopifyOrderId === '') {
            return false;
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        [$first, $last] = $this->splitName((string) ($order->customer_name ?? ($payload['customer_name'] ?? '')));
        $address = self::shopifyAddressFromPayload($payload, $first, $last);
        if ($address === [] || trim((string) ($address['address1'] ?? '')) === '') {
            return false;
        }

        $cacheKey = $this->addressCacheKey($order, $address);
        if (Cache::get($cacheKey)) {
            return false;
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return false;
        }

        sleep(1);
        $put = $this->shopifySend('PUT', 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json', $config, [
            'order' => [
                'id' => (int) $shopifyOrderId,
                'shipping_address' => $address,
                'billing_address' => $address,
            ],
        ]);
        if ($put === null || ! $put->successful()) {
            Log::warning('B5cB2bOrderPushService: Shopify address update failed', [
                'shopify_order_id' => $shopifyOrderId,
                'store_order_id' => $order->store_order_id,
                'status' => $put ? $put->status() : 0,
            ]);

            return true;
        }

        Cache::put($cacheKey, 1, now()->addDays(30));

        return true;
    }

    /**
     * @param  array<string, string>  $address
     */
    protected function addressCacheKey(B5cB2bOrder $order, array $address): string
    {
        return 'b5cb2b-shopify-address:'.(int) $order->store_order_id.':'.md5(json_encode($address) ?: '');
    }

    /**
     * @param  list<array<string, mixed>>  $bags
     * @param  list<string>  $keys
     */
    protected static function firstAddressValue(array $bags, array $keys): string
    {
        foreach ($bags as $bag) {
            foreach ($keys as $key) {
                $value = self::scalarAddressValue($bag[$key] ?? null);
                if ($value !== '') {
                    return $value;
                }
            }
            foreach ($keys as $key) {
                $value = self::scalarAddressValue($bag['shipping_'.$key] ?? null);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    protected static function scalarAddressValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    protected static function countryCode(string $country): string
    {
        $country = strtoupper(trim($country));
        $map = [
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'USA' => 'US',
            'CANADA' => 'CA',
        ];
        if (isset($map[$country])) {
            return $map[$country];
        }
        if ($country === '' || strlen($country) > 2) {
            return 'US';
        }

        return $country;
    }
}
