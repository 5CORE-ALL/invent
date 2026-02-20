<?php

namespace App\Services;

use App\Models\PendingShopifyOrder;
use App\Models\ReverbOrderMetric;
use App\Models\ReverbSyncSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReverbOrderPushService
{
    /** Backoff in seconds for 429 / timeout retries */
    public const API_BACKOFF = [30, 60, 120, 240, 480];

    /** Max retries for timeout/connection errors */
    public const MAX_RETRIES = 5;
    /**
     * Push a single Reverb order to Shopify. Returns Shopify order ID or null on failure.
     */
    public function pushOrder(ReverbOrderMetric $order): ?string
    {
        if ($order->shopify_order_id) {
            return $order->shopify_order_id;
        }

        $settings = ReverbSyncSettings::getForReverb();
        $orderTags = $settings['order']['shopify_order_tags'] ?? ['reverb'];
        $tags = array_values(array_unique(array_merge(
            ['reverb', 'reverb-' . ($order->order_number ?? $order->id)],
            $orderTags
        )));

        try {
            $shopifyOrderId = $this->createShopifyOrderFromReverb($order, $tags);
            $order->update([
                'shopify_order_id' => (string) $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
            ]);
            if (class_exists(\App\Services\ReverbSyncLogService::class)) {
                app(ReverbSyncLogService::class)->logOrderPushedToShopify(
                    (string) ($order->order_number ?? $order->id),
                    (int) $shopifyOrderId,
                    $order->sku,
                    'Reverb order #' . ($order->order_number ?? $order->id)
                );
            }
            return $shopifyOrderId;
        } catch (\Throwable $e) {
            Log::error('ReverbOrderPushService: push failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a Shopify order from a Reverb order. NEVER throws – returns Shopify order ID or null.
     * Tries variant first; falls back to custom line item; on total API failure stores in pending_shopify_orders.
     */
    public function createOrderFromMarketplace(ReverbOrderMetric $order): ?string
    {
        $variantId = $order->sku ? $this->findShopifyVariantIdBySku($order->sku) : null;
        $quantity = (int) ($order->quantity ?: 1);

        if (!$variantId) {
            Log::info('ReverbOrderPushService: fallback=SKU_NOT_FOUND', [
                'order_number' => $order->order_number,
                'sku' => $order->sku,
            ]);
            $result = $this->createOrderWithCustomItem($order, 'SKU [' . ($order->sku ?? 'N/A') . '] not found in Shopify - created as custom item', ['SKU Missing']);
            if ($result !== null) {
                return $result;
            }
            $reason = 'Custom item failed (SKU not found). ' . ($this->lastFailureReason ?? $this->lastApiErrorCode ?? 'Unknown');
            $this->storeInPending($order, $reason);
            return null;
        }

        $inventoryQty = $this->getInventoryLevel($variantId);
        $lowInventoryNote = null;
        $tags = ['Reverb Order'];
        if ($inventoryQty !== null && ($inventoryQty < 1 || $inventoryQty < $quantity)) {
            $tags[] = 'Low Inventory Warning';
            $lowInventoryNote = 'Inventory was low (available: ' . $inventoryQty . ', ordered: ' . $quantity . ')';
            Log::warning('ReverbOrderPushService: low inventory, creating with variant', [
                'order_number' => $order->order_number,
                'available' => $inventoryQty,
                'requested' => $quantity,
            ]);
        }

        $variantResult = $this->createOrderWithVariant($order, (int) $variantId, $tags, $lowInventoryNote);
        if ($variantResult !== null) {
            return $variantResult;
        }

        $errorCode = $this->lastApiErrorCode ?? 'Unknown';
        Log::info('ReverbOrderPushService: fallback=VARIANT_ERROR', [
            'order_number' => $order->order_number,
            'error_code' => $errorCode,
            'last_response_preview' => substr((string) $this->lastApiResponseBody, 0, 200),
        ]);
        $result = $this->createOrderWithCustomItem($order, 'Fallback - ' . $errorCode, ['SKU Missing', 'Fallback - ' . $errorCode]);
        if ($result !== null) {
            return $result;
        }

        $reason = 'Variant and custom both failed. Variant: ' . $errorCode . ' | Custom: ' . ($this->lastFailureReason ?? $this->lastApiErrorCode ?? 'Unknown');
        $this->storeInPending($order, $reason);
        return null;
    }

    /** For logging when order creation fails. */
    public ?string $lastApiErrorCode = null;

    public ?string $lastApiResponseBody = null;

    public ?int $lastApiStatus = null;

    /** Human-readable reason for last failure (for logging/storage). */
    public ?string $lastFailureReason = null;

    /**
     * Create order using variant_id. Handles 429/timeout with retries. Returns Shopify order ID or null.
     */
    protected function createOrderWithVariant(ReverbOrderMetric $order, int $variantId, array $extraTags = [], ?string $extraNote = null): ?string
    {
        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');

        $price = number_format((float) ($order->amount ?? 0), 2, '.', '');
        $quantity = (int) ($order->quantity ?: 1);
        $orderNumber = (string) ($order->order_number ?? $order->id);

        $payload = [
            'order' => [
                'line_items' => [['variant_id' => $variantId, 'quantity' => $quantity, 'price' => $price]],
                'financial_status' => 'paid',
                'inventory_behaviour' => 'decrement_obeying_policy',
                'tags' => implode(', ', array_values(array_unique(array_merge(['Reverb Order'], $extraTags)))),
                'note' => 'Imported from Reverb Order #' . $orderNumber . ($extraNote ? "\n" . $extraNote : ''),
                'source_name' => 'reverb',
                'note_attributes' => [['name' => 'reverb_order_number', 'value' => $orderNumber]],
            ],
        ];

        $reverbDetails = $this->enrichOrderPayloadFromReverb($payload['order'], $order);
        $response = $this->postOrderWithRetry($storeUrl, $token, $payload);
        if ($response === null) {
            return null;
        }

        $data = $response->json();
        $shopifyOrderId = (string) ($data['order']['id'] ?? '');
        if ($shopifyOrderId && $reverbDetails && !empty($reverbDetails['shipping_code']) && in_array($reverbDetails['status'] ?? '', ['shipped', 'delivered'], true)) {
            $trackingUrl = $reverbDetails['_links']['web_tracking']['href'] ?? null;
            $this->addShopifyFulfillmentWithTracking($storeUrl, $token, (int) $shopifyOrderId, (string) $reverbDetails['shipping_code'], (string) ($reverbDetails['shipping_provider'] ?? ''), $reverbDetails['shipped_at'] ?? null, is_string($trackingUrl) ? $trackingUrl : null);
        }
        return $shopifyOrderId;
    }

    /**
     * Create order with custom line item only. Never throws – returns Shopify order ID or null.
     * Validates line item data and logs each step for debugging.
     */
    public function createOrderWithCustomItem(ReverbOrderMetric $order, string $reasonNote, array $extraTags = []): ?string
    {
        $this->lastFailureReason = null;
        $orderNumber = (string) ($order->order_number ?? $order->id);

        Log::info('ReverbOrderPushService: createOrderWithCustomItem start', [
            'order_number' => $orderNumber,
            'sku' => $order->sku,
            'reason' => substr($reasonNote, 0, 80),
        ]);

        $lineItem = $this->validateAndBuildCustomLineItem($order);
        if ($lineItem === null) {
            $this->lastFailureReason = 'Validation failed for custom line item';
            Log::error('ReverbOrderPushService: custom line item validation failed', [
                'order_number' => $orderNumber,
                'sku' => $order->sku,
                'amount' => $order->amount,
                'quantity' => $order->quantity,
                'display_sku' => $order->display_sku,
            ]);
            return null;
        }

        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');
        if (!$storeUrl || !$token) {
            $this->lastFailureReason = 'Shopify store URL or token not configured';
            Log::error('ReverbOrderPushService: Shopify config missing');
            return null;
        }

        $baseTags = ['Reverb Order', 'SKU Missing'];
        $tags = implode(', ', array_values(array_unique(array_merge($baseTags, $extraTags))));

        $payload = [
            'order' => [
                'line_items' => [$lineItem],
                'financial_status' => 'paid',
                'inventory_behaviour' => 'bypass',
                'tags' => $tags,
                'note' => 'Imported from Reverb Order #' . $orderNumber . "\n" . $reasonNote,
                'source_name' => 'reverb',
                'note_attributes' => [['name' => 'reverb_order_number', 'value' => $orderNumber]],
            ],
        ];

        Log::debug('ReverbOrderPushService: custom payload built', ['order_number' => $orderNumber, 'line_item' => $lineItem]);

        $reverbDetails = $this->enrichOrderPayloadFromReverb($payload['order'], $order);

        $response = $this->postOrderWithRetry($storeUrl, $token, $payload);
        if ($response === null) {
            $reason = $this->lastApiStatus . ' ' . ($this->lastApiErrorCode ?? 'Unknown') . ' | ' . substr((string) $this->lastApiResponseBody, 0, 200);
            $this->lastFailureReason = $reason;
            Log::error('ReverbOrderPushService: createOrderWithCustomItem API failed', [
                'order_number' => $orderNumber,
                'status' => $this->lastApiStatus,
                'error' => $this->lastApiErrorCode,
                'response_preview' => substr((string) $this->lastApiResponseBody, 0, 300),
            ]);
            return null;
        }

        $data = $response->json();
        $shopifyOrderId = (string) ($data['order']['id'] ?? '');
        if (!$shopifyOrderId) {
            $this->lastFailureReason = 'Shopify API returned success but no order ID';
            Log::error('ReverbOrderPushService: no order ID in response', ['order_number' => $orderNumber, 'response_keys' => array_keys($data['order'] ?? [])]);
            return null;
        }

        Log::info('ReverbOrderPushService: createOrderWithCustomItem success', [
            'order_number' => $orderNumber,
            'shopify_order_id' => $shopifyOrderId,
        ]);

        if ($reverbDetails && !empty($reverbDetails['shipping_code']) && in_array($reverbDetails['status'] ?? '', ['shipped', 'delivered'], true)) {
            $trackingUrl = $reverbDetails['_links']['web_tracking']['href'] ?? null;
            $this->addShopifyFulfillmentWithTracking($storeUrl, $token, (int) $shopifyOrderId, (string) $reverbDetails['shipping_code'], (string) ($reverbDetails['shipping_provider'] ?? ''), $reverbDetails['shipped_at'] ?? null, is_string($trackingUrl) ? $trackingUrl : null);
        }
        return $shopifyOrderId;
    }

    /**
     * Validate and build custom line item. Returns array or null on validation failure.
     */
    protected function validateAndBuildCustomLineItem(ReverbOrderMetric $order): ?array
    {
        $title = trim((string) ($order->display_sku ?? $order->sku ?? ''));
        if ($title === '') {
            $title = 'Reverb order item';
        }
        $title = mb_substr($title, 0, 255);

        $priceVal = (float) ($order->amount ?? 0);
        if ($priceVal < 0 || !is_finite($priceVal)) {
            $priceVal = 0.00;
        }
        $price = number_format($priceVal, 2, '.', '');

        $quantity = (int) ($order->quantity ?? 1);
        if ($quantity < 1) {
            $quantity = 1;
        }

        $lineItem = [
            'title' => $title,
            'price' => $price,
            'quantity' => $quantity,
        ];
        if ($order->sku && trim((string) $order->sku) !== '') {
            $lineItem['properties'] = [['name' => 'SKU', 'value' => mb_substr(trim((string) $order->sku), 0, 255)]];
        }
        return $lineItem;
    }

    /**
     * POST order to Shopify with 429 and timeout retries. Returns response or null on failure.
     */
    protected function postOrderWithRetry(string $storeUrl, string $token, array $payload): ?\Illuminate\Http\Client\Response
    {
        $url = "https://{$storeUrl}/admin/api/2024-01/orders.json";
        $attempt = 0;
        $backoffIndex = 0;

        while (true) {
            $attempt++;
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($url, $payload);

            if ($response->successful()) {
                $this->lastApiErrorCode = null;
                $this->lastApiResponseBody = null;
                $this->lastApiStatus = null;
                return $response;
            }

            $status = $response->status();
            $body = $response->body();
            $this->lastApiStatus = $status;
            $this->lastApiResponseBody = $body;
            $this->lastApiErrorCode = $this->parseShopifyErrorCode($status, $body);

            $handling = $this->handleApiError($status, $body, $response->toException());
            if ($handling['retry'] && $attempt < self::MAX_RETRIES) {
                $wait = self::API_BACKOFF[$backoffIndex % count(self::API_BACKOFF)];
                Log::warning('ReverbOrderPushService: API error, retrying', [
                    'attempt' => $attempt,
                    'wait' => $wait,
                    'status' => $status,
                    'error_code' => $handling['error_code'],
                ]);
                sleep($wait);
                $backoffIndex++;
                continue;
            }

            return null;
        }
    }

    protected function parseShopifyErrorCode(int $status, string $body): string
    {
        $preview = substr(preg_replace('/\s+/', ' ', trim($body)), 0, 150);
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['errors'])) {
            $err = $decoded['errors'];
            if (is_string($err)) {
                return (string) $status . ' ' . substr($err, 0, 80);
            }
            if (is_array($err)) {
                $first = reset($err);
                $msg = is_array($first) ? json_encode($first) : (string) $first;
                return (string) $status . ' ' . substr($msg, 0, 80);
            }
        }
        return (string) $status . ' ' . $preview;
    }

    /**
     * Categorize API error and decide action.
     * @return array{retry: bool, fallback_to_custom: bool, error_code: string, is_permanent: bool}
     */
    public function handleApiError(int $status, string $body, ?\Throwable $exception = null): array
    {
        $preview = substr(preg_replace('/\s+/', ' ', trim($body)), 0, 100);
        $errorCode = (string) $status . ' ' . $preview;
        $message = $body . ($exception ? ' ' . $exception->getMessage() : '');

        if ($status === 429) {
            return ['retry' => true, 'fallback_to_custom' => true, 'error_code' => '429 Rate Limit', 'is_permanent' => false];
        }
        if ($status >= 500 || $status === 0) {
            return ['retry' => true, 'fallback_to_custom' => true, 'error_code' => $errorCode, 'is_permanent' => false];
        }
        if (stripos($message, 'timeout') !== false || stripos($message, 'connection') !== false || stripos($message, 'Connection') !== false || stripos($message, 'Connection refused') !== false) {
            return ['retry' => true, 'fallback_to_custom' => true, 'error_code' => 'Timeout/Connection', 'is_permanent' => false];
        }
        if ($status >= 400 && $status < 500) {
            return ['retry' => false, 'fallback_to_custom' => true, 'error_code' => $errorCode, 'is_permanent' => true];
        }
        return ['retry' => false, 'fallback_to_custom' => true, 'error_code' => $errorCode, 'is_permanent' => false];
    }

    public function shouldRetry(int $status, string $body): bool
    {
        return $this->handleApiError($status, $body)['retry'];
    }

    /**
     * Get inventory level for variant. Alias for getVariantInventoryQuantity.
     */
    public function getInventoryLevel(string $variantId): ?int
    {
        return $this->getVariantInventoryQuantity($variantId);
    }

    /**
     * Store order in pending_shopify_orders for later retry. Sends admin alert.
     */
    public function storeInPending(ReverbOrderMetric $order, string $reason): void
    {
        PendingShopifyOrder::create([
            'reverb_order_metric_id' => $order->id,
            'order_data' => [
                'reverb_order_metric_id' => $order->id,
                'order_number' => $order->order_number,
                'sku' => $order->sku,
                'amount' => $order->amount,
                'quantity' => $order->quantity,
                'display_sku' => $order->display_sku,
            ],
            'attempts' => 0,
            'last_error' => $reason,
        ]);

        Log::critical('ReverbOrderPushService: Shopify order stored in pending_shopify_orders', [
            'reverb_order_metric_id' => $order->id,
            'order_number' => $order->order_number,
            'reason' => $reason,
            'last_api_status' => $this->lastApiStatus,
            'last_api_error' => $this->lastApiErrorCode,
        ]);
    }

    /**
     * Legacy: Create Shopify order with custom line item (can throw). Prefer createOrderWithCustomItem for never-fail flow.
     */
    public function createShopifyOrderWithCustomItem(ReverbOrderMetric $order, string $reasonNote, array $extraTags = []): ?string
    {
        return $this->createOrderWithCustomItem($order, $reasonNote, $extraTags);
    }

    /**
     * Add customer and shipping from Reverb order details. Returns reverb details array or null.
     */
    protected function enrichOrderPayloadFromReverb(array &$orderPayload, ReverbOrderMetric $order): ?array
    {
        $reverbDetails = $this->fetchReverbOrderDetails((string) $order->order_number);
        if (!$reverbDetails) {
            return null;
        }
        $buyerName = $reverbDetails['buyer_name'] ?? null;
        $buyerFirst = $reverbDetails['buyer_first_name'] ?? null;
        $buyerLast = $reverbDetails['buyer_last_name'] ?? null;
        $buyerEmail = $reverbDetails['buyer_email'] ?? $reverbDetails['email'] ?? null;
        if (!$buyerFirst && $buyerName) {
            $parts = explode(' ', trim($buyerName), 2);
            $buyerFirst = $parts[0] ?? '';
            $buyerLast = $parts[1] ?? '';
        }
        $orderPayload['customer'] = [
            'first_name' => (string) ($buyerFirst ?? ''),
            'last_name' => (string) ($buyerLast ?? ''),
        ];
        if ($buyerEmail) {
            $orderPayload['customer']['email'] = (string) $buyerEmail;
        }
        $addr = $reverbDetails['shipping_address'] ?? null;
        if (is_array($addr) && !empty($addr['street_address'])) {
            $name = $addr['name'] ?? $buyerName ?? trim(($buyerFirst ?? '') . ' ' . ($buyerLast ?? ''));
            $shippingAddr = [
                'first_name' => $buyerFirst ?? $name,
                'last_name' => $buyerLast ?? '',
                'address1' => (string) ($addr['street_address'] ?? ''),
                'address2' => (string) ($addr['extended_address'] ?? ''),
                'city' => (string) ($addr['locality'] ?? ''),
                'province_code' => (string) ($addr['region'] ?? ''),
                'country_code' => (string) ($addr['country_code'] ?? 'US'),
                'zip' => (string) ($addr['postal_code'] ?? ''),
                'phone' => (string) ($addr['unformatted_phone'] ?? $addr['phone'] ?? ''),
            ];
            $orderPayload['shipping_address'] = $shippingAddr;
            $orderPayload['billing_address'] = array_merge($shippingAddr, ['name' => $name ?: trim(($buyerFirst ?? '') . ' ' . ($buyerLast ?? ''))]);
        }
        return $reverbDetails;
    }

    /**
     * Get available inventory quantity for a Shopify variant. Returns null if unable to determine.
     */
    protected function getVariantInventoryQuantity(string $variantId): ?int
    {
        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->timeout(15)->get("https://{$storeUrl}/admin/api/2024-01/variants/{$variantId}.json", [
            'fields' => 'id,inventory_quantity,inventory_item_id',
        ]);

        if (!$response->successful()) {
            return null;
        }
        $variant = $response->json('variant');
        if (!is_array($variant)) {
            return null;
        }
        return isset($variant['inventory_quantity']) ? (int) $variant['inventory_quantity'] : null;
    }

    /**
     * Fetch full order details from Reverb API (buyer, shipping, payment, tracking).
     * Returns array or null if not found / API error.
     */
    public function fetchReverbOrderDetails(string $orderNumber): ?array
    {
        $url = 'https://api.reverb.com/api/my/orders/selling/' . $orderNumber;
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.reverb.token'),
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
        ])->timeout(15)->get($url);

        if (! $response->successful()) {
            return null;
        }
        $data = $response->json();
        return is_array($data) ? $data : null;
    }

    public function createShopifyOrderFromReverb(ReverbOrderMetric $order, array $tags): string
    {
        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');

        $variantId = $order->sku ? $this->findShopifyVariantIdBySku($order->sku) : null;
        $useCustomLineItem = ! $variantId;

        if ($useCustomLineItem) {
            Log::warning('ReverbOrderPushService: SKU not found in Shopify, creating order with custom line item', [
                'order_number' => $order->order_number,
                'sku' => $order->sku,
            ]);
        }

        $reverbDetails = $this->fetchReverbOrderDetails((string) $order->order_number);

        $lineItem = $variantId
            ? [
                'variant_id' => (int) $variantId,
                'quantity' => (int) ($order->quantity ?: 1),
            ]
            : [
                'title' => (string) ($order->display_sku ?? $order->sku ?? 'Reverb order item'),
                'price' => (string) (number_format((float) ($order->amount ?? 0), 2, '.', '')),
                'quantity' => (int) ($order->quantity ?: 1),
                'sku' => (string) ($order->sku ?? ''),
            ];

        $baseNote = 'Imported from Reverb. Order #' . ($order->order_number ?? $order->id);
        if ($useCustomLineItem) {
            $baseNote .= "\nProduct not found in Shopify - SKU: " . ($order->sku ?? 'N/A');
        }

        $noteAttrs = [
            ['name' => 'reverb_order_number', 'value' => (string) ($order->order_number ?? $order->id)],
        ];

        $orderPayload = [
            'line_items' => [$lineItem],
            'tags' => implode(', ', $tags),
            'note' => $baseNote,
            'source_name' => 'reverb',
            'note_attributes' => $noteAttrs,
            'financial_status' => 'paid',
            'inventory_behaviour' => 'decrement_obeying_policy',
        ];

        if ($reverbDetails) {
            $buyerName = $reverbDetails['buyer_name'] ?? null;
            $buyerFirst = $reverbDetails['buyer_first_name'] ?? null;
            $buyerLast = $reverbDetails['buyer_last_name'] ?? null;
            $buyerEmail = $reverbDetails['buyer_email'] ?? $reverbDetails['email'] ?? null;
            if (!$buyerFirst && $buyerName) {
                $parts = explode(' ', trim($buyerName), 2);
                $buyerFirst = $parts[0] ?? '';
                $buyerLast = $parts[1] ?? '';
            }
            $buyerId = $reverbDetails['buyer_id'] ?? null;
            $orderPayload['note_attributes'][] = ['name' => 'reverb_buyer_id', 'value' => (string) ($buyerId ?? '')];
            if ($reverbDetails['payment_method'] ?? null) {
                $orderPayload['note_attributes'][] = ['name' => 'reverb_payment_method', 'value' => (string) $reverbDetails['payment_method']];
            }
            if ($buyerName) {
                $orderPayload['note_attributes'][] = ['name' => 'reverb_buyer_username', 'value' => (string) $buyerName];
            }

            $orderPayload['customer'] = [
                'first_name' => (string) ($buyerFirst ?? ''),
                'last_name' => (string) ($buyerLast ?? ''),
            ];
            if ($buyerEmail) {
                $orderPayload['customer']['email'] = (string) $buyerEmail;
            }

            $addr = $reverbDetails['shipping_address'] ?? null;
            if (is_array($addr) && !empty($addr['street_address'])) {
                $name = $addr['name'] ?? $buyerName ?? trim(($buyerFirst ?? '') . ' ' . ($buyerLast ?? ''));
                $shippingAddr = [
                    'first_name' => $buyerFirst ?? $name,
                    'last_name' => $buyerLast ?? '',
                    'address1' => (string) ($addr['street_address'] ?? ''),
                    'address2' => (string) ($addr['extended_address'] ?? ''),
                    'city' => (string) ($addr['locality'] ?? ''),
                    'province_code' => (string) ($addr['region'] ?? ''),
                    'country_code' => (string) ($addr['country_code'] ?? 'US'),
                    'zip' => (string) ($addr['postal_code'] ?? ''),
                    'phone' => (string) ($addr['unformatted_phone'] ?? $addr['phone'] ?? ''),
                ];
                if (!empty($addr['company'])) {
                    $shippingAddr['company'] = (string) $addr['company'];
                }
                if ($name && empty($shippingAddr['first_name'])) {
                    $shippingAddr['first_name'] = $name;
                }
                $orderPayload['shipping_address'] = $shippingAddr;
                $orderPayload['billing_address'] = array_merge($shippingAddr, ['name' => $name ?: trim($buyerFirst . ' ' . $buyerLast)]);
            }

            $taxCents = $reverbDetails['amount_tax']['amount_cents'] ?? 0;
            if ($taxCents > 0) {
                $taxAmount = (float) ($taxCents / 100);
                $orderPayload['total_tax'] = (string) number_format($taxAmount, 2, '.', '');
            }
        }

        $payload = ['order' => $orderPayload];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post("https://{$storeUrl}/admin/api/2024-01/orders.json", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Shopify API error: ' . $response->body());
        }

        $data = $response->json();
        $shopifyOrderId = (string) ($data['order']['id'] ?? '');

        if ($shopifyOrderId && $reverbDetails && !empty($reverbDetails['shipping_code']) && in_array($reverbDetails['status'] ?? '', ['shipped', 'delivered'], true)) {
            $trackingUrl = $reverbDetails['_links']['web_tracking']['href'] ?? null;
            $this->addShopifyFulfillmentWithTracking(
                $storeUrl,
                $token,
                (int) $shopifyOrderId,
                (string) $reverbDetails['shipping_code'],
                (string) ($reverbDetails['shipping_provider'] ?? ''),
                $reverbDetails['shipped_at'] ?? null,
                is_string($trackingUrl) ? $trackingUrl : null
            );
        }

        return $shopifyOrderId;
    }

    /**
     * Add fulfillment with tracking to an existing Shopify order.
     */
    protected function addShopifyFulfillmentWithTracking(
        string $storeUrl,
        string $token,
        int $orderId,
        string $trackingNumber,
        string $trackingCompany,
        ?string $shippedAt,
        ?string $trackingUrl = null
    ): void {
        try {
            $ordersRes = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->get("https://{$storeUrl}/admin/api/2024-01/orders/{$orderId}/fulfillment_orders.json");

            if (! $ordersRes->successful()) {
                return;
            }
            $foData = $ordersRes->json();
            $fulfillmentOrders = $foData['fulfillment_orders'] ?? [];
            $lineItems = [];
            foreach ($fulfillmentOrders as $fo) {
                if (($fo['status'] ?? '') === 'open' || ($fo['status'] ?? '') === 'scheduled') {
                    $lineItems[] = ['fulfillment_order_id' => $fo['id']];
                }
            }
            if (empty($lineItems)) {
                return;
            }
            $fulfillment = [
                'line_items_by_fulfillment_order' => $lineItems,
                'tracking_number' => $trackingNumber,
                'tracking_company' => $trackingCompany ?: 'Other',
            ];
            if ($trackingUrl) {
                $fulfillment['tracking_urls'] = [$trackingUrl];
            }
            $fulfillmentPayload = ['fulfillment' => $fulfillment];
            Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("https://{$storeUrl}/admin/api/2024-01/fulfillments.json", $fulfillmentPayload);
        } catch (\Throwable $e) {
            Log::warning('ReverbOrderPushService: could not add fulfillment/tracking to Shopify order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function findShopifyVariantIdBySku(?string $sku): ?string
    {
        if (! $sku) {
            return null;
        }
        $sku = trim($sku);
        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');
        $url = "https://{$storeUrl}/admin/api/2024-01/products.json";
        $pageInfo = null;
        for ($i = 0; $i < 20; $i++) {
            $query = ['limit' => 250, 'fields' => 'id,variants'];
            if ($pageInfo) {
                $query['page_info'] = $pageInfo;
            }
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->get($url, $query);
            if (! $response->successful()) {
                return null;
            }
            $products = $response->json()['products'] ?? [];
            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $v) {
                    if (isset($v['sku']) && trim((string) $v['sku']) === $sku) {
                        return (string) $v['id'];
                    }
                }
            }
            $link = $response->header('Link');
            if (! $link || strpos($link, 'rel="next"') === false) {
                break;
            }
            if (preg_match('/<[^>]+page_info=([^&>]+)[^>]*>;\s*rel="next"/', $link, $m)) {
                $pageInfo = $m[1];
            } else {
                break;
            }
        }
        return null;
    }
}
