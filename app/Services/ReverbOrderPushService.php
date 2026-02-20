<?php

namespace App\Services;

use App\Models\ReverbOrderMetric;
use App\Models\ReverbSyncSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReverbOrderPushService
{
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
     * Create a REAL Shopify order from a Reverb order (used by automatic import).
     * Tries variant by SKU first; falls back to custom line item if SKU not found or inventory zero.
     * Returns Shopify order ID or null on failure.
     */
    public function createOrderFromMarketplace(ReverbOrderMetric $order): ?string
    {
        $variantId = $order->sku ? $this->findShopifyVariantIdBySku($order->sku) : null;
        $quantity = (int) ($order->quantity ?: 1);

        if (!$variantId) {
            Log::info('ReverbOrderPushService: SKU not found, creating order with custom line item', [
                'order_number' => $order->order_number,
                'sku' => $order->sku,
            ]);
            return $this->createShopifyOrderWithCustomItem($order, 'SKU not found in Shopify - added as custom item', ['SKU Missing']);
        }

        $inventoryQty = $this->getVariantInventoryQuantity($variantId);
        if ($inventoryQty !== null && $inventoryQty < 1) {
            Log::info('ReverbOrderPushService: inventory zero, falling back to custom line item', [
                'order_number' => $order->order_number,
                'sku' => $order->sku,
            ]);
            return $this->createShopifyOrderWithCustomItem($order, 'Inventory zero - added as custom item', ['SKU Missing', 'Inventory Zero']);
        }
        if ($inventoryQty !== null && $inventoryQty < $quantity) {
            Log::warning('ReverbOrderPushService: low inventory, proceeding with variant', [
                'order_number' => $order->order_number,
                'sku' => $order->sku,
                'available' => $inventoryQty,
                'requested' => $quantity,
            ]);
        }

        try {
            return $this->createShopifyOrderWithVariant($order, (int) $variantId);
        } catch (\Throwable $e) {
            $body = $e->getMessage();
            $isInventoryError = (
                stripos($body, 'inventory') !== false ||
                stripos($body, 'Unable to reserve') !== false ||
                stripos($body, 'variant') !== false
            );
            if ($isInventoryError) {
                Log::warning('ReverbOrderPushService: Shopify inventory error, falling back to custom line item', [
                    'order_number' => $order->order_number,
                    'error' => $body,
                ]);
                return $this->createShopifyOrderWithCustomItem($order, 'Shopify inventory error - added as custom item: ' . substr($body, 0, 100), ['SKU Missing', 'Inventory Error']);
            }
            throw $e;
        }
    }

    /**
     * Create Shopify order using variant_id (decrements inventory). Throws on API error.
     */
    protected function createShopifyOrderWithVariant(ReverbOrderMetric $order, int $variantId): ?string
    {
        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');

        $price = number_format((float) ($order->amount ?? 0), 2, '.', '');
        $quantity = (int) ($order->quantity ?: 1);
        $orderNumber = (string) ($order->order_number ?? $order->id);

        $payload = [
            'order' => [
                'line_items' => [
                    [
                        'variant_id' => $variantId,
                        'quantity' => $quantity,
                        'price' => $price,
                    ],
                ],
                'financial_status' => 'paid',
                'inventory_behaviour' => 'decrement_obeying_policy',
                'tags' => 'Reverb Order',
                'note' => 'Imported from Reverb Order #' . $orderNumber,
                'source_name' => 'reverb',
                'note_attributes' => [
                    ['name' => 'reverb_order_number', 'value' => $orderNumber],
                ],
            ],
        ];

        $reverbDetails = $this->enrichOrderPayloadFromReverb($payload['order'], $order);
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post("https://{$storeUrl}/admin/api/2024-01/orders.json", $payload);

        if (!$response->successful()) {
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
     * Create Shopify order with custom line item (title, price, quantity) when variant/SKU unavailable.
     * Does not decrement inventory.
     */
    public function createShopifyOrderWithCustomItem(ReverbOrderMetric $order, string $reasonNote, array $extraTags = []): ?string
    {
        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');

        $orderNumber = (string) ($order->order_number ?? $order->id);
        $baseTags = ['Reverb Order', 'SKU Missing'];
        $tags = implode(', ', array_values(array_unique(array_merge($baseTags, $extraTags))));

        $lineItem = [
            'title' => (string) ($order->display_sku ?? $order->sku ?? 'Reverb order item'),
            'price' => (string) number_format((float) ($order->amount ?? 0), 2, '.', ''),
            'quantity' => (int) ($order->quantity ?: 1),
        ];
        if ($order->sku) {
            $lineItem['properties'] = [['name' => 'SKU', 'value' => (string) $order->sku]];
        }

        $payload = [
            'order' => [
                'line_items' => [$lineItem],
                'financial_status' => 'paid',
                'inventory_behaviour' => 'bypass',
                'tags' => $tags,
                'note' => 'Imported from Reverb Order #' . $orderNumber . "\n" . $reasonNote,
                'source_name' => 'reverb',
                'note_attributes' => [
                    ['name' => 'reverb_order_number', 'value' => $orderNumber],
                ],
            ],
        ];

        $reverbDetails = $this->enrichOrderPayloadFromReverb($payload['order'], $order);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post("https://{$storeUrl}/admin/api/2024-01/orders.json", $payload);

        if (!$response->successful()) {
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
