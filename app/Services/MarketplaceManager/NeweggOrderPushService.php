<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\NeweggOrderMetric;
use App\Models\ShopifySku;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NeweggOrderPushService
{
    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    public function __construct(
        protected NeweggOrderDetailService $orderDetailService,
        protected NeweggDetailFormatter $formatter
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(NeweggOrderMetric $order): array
    {
        $plan = $this->buildImportPlan($order);
        $plan['dry_run'] = true;
        if (! empty($plan['success'])) {
            $plan['message'] = 'Dry run only — no Shopify order was created.';
        }

        return $plan;
    }

    /**
     * Push Newegg ship-to onto an already-imported Shopify order.
     * Used by "Pull from Newegg" so address-only refreshes update Shopify.
     *
     * @return array{success: bool, skipped?: bool, message: string, shopify_order_id?: string}
     */
    public function syncShippingAddressToShopify(NeweggOrderMetric $order): array
    {
        $shopifyOrderId = trim((string) ($order->shopify_order_id ?? ''));
        if ($shopifyOrderId === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to Shopify yet — push the order first.',
            ];
        }

        $orderId = (string) $order->order_id;
        $lines = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $orderRoot = $this->orderDetailService->resolveOrderRoot($order);
        if ($orderRoot === []) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No Newegg order payload available to build an address.',
            ];
        }

        $detail = $this->formatter->formatOrder($orderRoot, $lines, $order);
        $orderPayload = $this->formatter->buildShopifyOrderPayload($detail, $lines, ['newegg']);
        $shipping = is_array($orderPayload['shipping_address'] ?? null)
            ? $orderPayload['shipping_address']
            : [];
        $address1 = trim((string) ($shipping['address1'] ?? ''));

        if ($address1 === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Newegg still has no shipping address for this order.',
            ];
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return [
                'success' => false,
                'message' => 'Shopify store credentials are not configured.',
            ];
        }

        $billing = is_array($orderPayload['billing_address'] ?? null)
            ? $orderPayload['billing_address']
            : $shipping;
        // Shopify customer-address writes prefer an explicit country name.
        $shipping = $this->withShopifyCountryName($shipping);
        $billing = $this->withShopifyCountryName($billing);

        $update = [
            'id' => (int) $shopifyOrderId,
            'shipping_address' => $shipping,
            'billing_address' => $billing,
        ];

        $customer = is_array($orderPayload['customer'] ?? null) ? $orderPayload['customer'] : [];
        $email = trim((string) ($customer['email'] ?? ''));
        if ($email !== '' && ! $this->payloadUsesPlaceholderEmail($orderPayload)) {
            $update['email'] = $email;
        }

        // Do not nest customer on order PUT — that can leave billing_address null
        // and does not reliably rename an existing Shopify customer.
        $ok = $this->putOrder($config, $shopifyOrderId, ['order' => $update]);
        if (! $ok) {
            return [
                'success' => false,
                'message' => $this->lastFailureReason ?: 'Shopify address update failed.',
                'shopify_order_id' => $shopifyOrderId,
            ];
        }

        $this->syncShopifyCustomerFromAddress($config, $shopifyOrderId, $shipping, $customer);

        return [
            'success' => true,
            'message' => 'Shopify shipping address updated.',
            'shopify_order_id' => $shopifyOrderId,
        ];
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $address
     * @param  array<string, mixed>  $customer
     */
    protected function syncShopifyCustomerFromAddress(
        array $config,
        string $shopifyOrderId,
        array $address,
        array $customer
    ): void {
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
            ])->timeout(30)->get(
                'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json',
                ['fields' => 'id,customer']
            );

            if (! $response->successful()) {
                return;
            }

            $customerId = (int) ($response->json('order.customer.id') ?? 0);
            if ($customerId <= 0) {
                return;
            }

            $payload = array_filter([
                'id' => $customerId,
                'first_name' => $customer['first_name'] ?? $address['first_name'] ?? null,
                'last_name' => $customer['last_name'] ?? $address['last_name'] ?? null,
                'phone' => $address['phone'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');

            $email = trim((string) ($customer['email'] ?? ''));
            if ($email !== '') {
                $payload['email'] = $email;
            }

            $addressPayload = array_filter(array_merge($address, ['default' => true]), static fn ($v) => $v !== null && $v !== '');
            if (! empty($addressPayload['address1'])) {
                $payload['addresses'] = [$addressPayload];
            }

            Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
                'Content-Type' => 'application/json',
            ])->timeout(30)->put(
                'https://'.$config['store_url'].'/admin/api/2024-01/customers/'.$customerId.'.json',
                ['customer' => $payload]
            );
        } catch (\Throwable $e) {
            Log::warning('NeweggOrderPushService: Shopify customer address sync failed', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    protected function withShopifyCountryName(array $address): array
    {
        $code = strtoupper(trim((string) ($address['country_code'] ?? '')));
        if ($code === '' || ! empty($address['country'])) {
            return $address;
        }

        $names = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'MX' => 'Mexico',
        ];
        if (isset($names[$code])) {
            $address['country'] = $names[$code];
        }

        return $address;
    }

    public function importToShopify(NeweggOrderMetric $order): ?string
    {
        if ($order->shopify_order_id) {
            return (string) $order->shopify_order_id;
        }

        $plan = $this->buildImportPlan($order);
        if (empty($plan['success'])) {
            $this->lastFailureReason = $plan['message'] ?? 'Could not build Shopify import plan.';

            return null;
        }

        $shopifyOrderId = $this->postOrder($this->shopifyConfig(), ['order' => $plan['payload']]);
        if (! $shopifyOrderId) {
            return null;
        }

        $fulfillment = is_array($plan['fulfillment'] ?? null) ? $plan['fulfillment'] : [];
        $tracking = (string) ($fulfillment['tracking'] ?? '');
        $carrier = (string) ($fulfillment['carrier'] ?? 'Newegg');
        if ($tracking !== '') {
            $this->addFulfillmentTracking($shopifyOrderId, $tracking, $carrier);
        }

        NeweggOrderMetric::query()
            ->where('order_id', (string) $order->order_id)
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
                'import_status' => 'imported',
            ]);

        $this->syncInventoryAfterPush($order);

        return $shopifyOrderId;
    }

    /**
     * After Shopify decrements stock for the imported order, push live qty to Newegg.
     */
    protected function syncInventoryAfterPush(NeweggOrderMetric $order): void
    {
        $skus = NeweggOrderMetric::query()
            ->where('order_id', (string) $order->order_id)
            ->pluck('sku')
            ->map(static fn ($sku) => trim((string) $sku))
            ->filter(static fn ($sku) => $sku !== '' && ! in_array($sku, ['__order__', '__unknown__'], true))
            ->unique()
            ->values()
            ->all();

        if ($skus === []) {
            return;
        }

        // Give Shopify a moment to apply inventory_behaviour decrement.
        usleep(1500000);

        try {
            $result = app(NeweggInventorySyncService::class)
                ->syncSkusFromShopify($skus, $this->shopifyConfig());

            Log::info('NeweggOrderPushService: post-push inventory sync', [
                'order_id' => $order->order_id,
                'skus' => $skus,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('NeweggOrderPushService: post-push inventory sync failed', [
                'order_id' => $order->order_id,
                'skus' => $skus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildImportPlan(NeweggOrderMetric $order): array
    {
        if ($order->shopify_order_id) {
            return [
                'success' => false,
                'message' => 'Already imported.',
                'shopify_order_id' => (string) $order->shopify_order_id,
            ];
        }

        $orderId = (string) $order->order_id;
        if ($orderId === '') {
            return [
                'success' => false,
                'message' => 'Order ID is missing on this row.',
            ];
        }

        // Snapshot cached root before live refresh — refresh used to wipe ShipTo and
        // create Shopify orders without shipping_address.
        $cachedRoot = $this->orderDetailService->resolveOrderRoot($order);

        $detailResult = $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
        $order->refresh();

        $orderRoot = $this->orderDetailService->resolveOrderRoot($order);
        if (empty($detailResult['success']) && $orderRoot === []) {
            $orderRoot = $cachedRoot;
        }
        if ($orderRoot === [] && $cachedRoot === []) {
            return [
                'success' => false,
                'message' => $detailResult['message'] ?? 'Could not load Newegg order details before Shopify push.',
            ];
        }

        // If live refresh lost ship-to fields, restore from pre-refresh cache.
        $orderRoot = $this->restoreShippingFromCache($orderRoot, $cachedRoot);

        $lines = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $detail = $this->formatter->formatOrder($orderRoot, $lines, $order);

        $settings = MarketplaceSyncSettings::getFor('newegg');
        $tags = array_values(array_unique(array_merge(
            ['newegg', 'newegg-'.$orderId],
            $settings['order']['shopify_order_tags'] ?? []
        )));

        $orderPayload = $this->formatter->buildShopifyOrderPayload($detail, $lines, $this->cleanTags($tags));
        [$orderPayload, $lineResolution] = $this->resolveLineItemsForShopify($orderPayload, $lines);

        $config = $this->shopifyConfig();
        $tracking = (string) (($detail['shipment']['tracking'] ?? '') ?: '');
        $carrier = (string) (($detail['shipment']['service'] ?? '') ?: 'Newegg');

        $warnings = $this->buildImportWarnings($detail, $lineResolution, $orderPayload);
        if (empty($detailResult['success'])) {
            $warnings[] = 'Could not refresh live Newegg details ('.($detailResult['message'] ?? 'unknown').') — using cached order payload.';
        }

        return [
            'success' => true,
            'newegg_order_id' => $orderId,
            'shopify_store' => $config['store_url'],
            'shopify_store_key' => $config['store_key'] ?? null,
            'payload' => $orderPayload,
            'fulfillment' => [
                'tracking' => $tracking !== '' ? $tracking : null,
                'carrier' => $carrier !== '' ? $carrier : null,
                'will_create' => $tracking !== '',
            ],
            'line_resolution' => $lineResolution,
            'warnings' => array_values(array_unique($warnings)),
            'preview' => $this->buildHumanPreview($detail, $orderPayload, $lineResolution, [
                'tracking' => $tracking,
                'carrier' => $carrier,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<int, array<string, mixed>>  $lineResolution
     * @param  array<string, mixed>  $orderPayload
     * @return array<int, string>
     */
    protected function buildImportWarnings(array $detail, array $lineResolution, array $orderPayload): array
    {
        $warnings = [];
        $shipping = $detail['shipping'] ?? [];

        if (empty($orderPayload['shipping_address']['address1'] ?? null)) {
            $warnings[] = 'No shipping address — Shopify order will be created without shipping_address.';
        }
        if ($this->payloadUsesPlaceholderEmail($orderPayload)) {
            $warnings[] = 'Newegg did not provide a buyer email — a placeholder email will be used for Shopify.';
        } elseif (empty($shipping['email'] ?? null) && empty($orderPayload['customer']['email'] ?? null)) {
            $warnings[] = 'No buyer email on Newegg order.';
        }

        $sourceName = trim((string) ($orderPayload['source_name'] ?? ''));
        if ($sourceName === '') {
            $warnings[] = 'No Shopify source_name set — Channel information may show your app name instead of Newegg.';
        }
        if (empty($shipping['phone'] ?? null)) {
            $warnings[] = 'No buyer phone on Newegg order.';
        }

        foreach ($lineResolution as $row) {
            if (($row['match_type'] ?? '') === 'custom') {
                $warnings[] = 'SKU not found in Shopify: '.($row['sku'] ?? '?').' — will use custom line item.';
            }
        }

        if ($lineResolution === []) {
            $warnings[] = 'No resolvable line items.';
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            $warnings[] = 'Shopify store credentials are not configured for the selected import store.';
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $orderPayload
     * @param  array<int, array<string, mixed>>  $lineResolution
     * @param  array<string, mixed>  $fulfillment
     * @return array<string, mixed>
     */
    protected function buildHumanPreview(array $detail, array $orderPayload, array $lineResolution, array $fulfillment): array
    {
        $shipping = $orderPayload['shipping_address'] ?? [];
        $customer = $orderPayload['customer'] ?? [];

        return [
            'customer' => [
                'name' => trim(trim((string) ($customer['first_name'] ?? '')).' '.trim((string) ($customer['last_name'] ?? ''))),
                'email' => $customer['email'] ?? null,
                'email_is_placeholder' => $this->payloadUsesPlaceholderEmail($orderPayload),
                'phone' => $shipping['phone'] ?? null,
            ],
            'shipping_address' => [
                'address1' => $shipping['address1'] ?? null,
                'address2' => $shipping['address2'] ?? null,
                'city' => $shipping['city'] ?? null,
                'province' => $shipping['province'] ?? null,
                'zip' => $shipping['zip'] ?? null,
                'country_code' => $shipping['country_code'] ?? null,
            ],
            'payment_method' => $detail['payment']['method'] ?? null,
            'tracking' => $fulfillment['tracking'] ?? null,
            'shipping_method' => $fulfillment['carrier'] ?? null,
            'line_items' => $lineResolution,
            'tags' => $orderPayload['tags'] ?? null,
            'note' => $orderPayload['note'] ?? null,
            'note_attributes' => $orderPayload['note_attributes'] ?? [],
            'shipping_lines' => $orderPayload['shipping_lines'] ?? [],
            'tax_lines' => $orderPayload['tax_lines'] ?? [],
            'channel' => [
                'display_name' => $this->formatter->shopifySourceDisplayName(),
                'source_name' => $orderPayload['source_name'] ?? null,
                'source_identifier' => $orderPayload['source_identifier'] ?? null,
                'source_url' => $orderPayload['source_url'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @param  Collection<int, NeweggOrderMetric>  $lines
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    protected function resolveLineItemsForShopify(array $orderPayload, Collection $lines): array
    {
        $resolved = [];
        $meta = [];
        $sourceItems = $orderPayload['line_items'] ?? [];

        if ($sourceItems === []) {
            foreach ($lines as $line) {
                $sku = (string) $line->sku;
                if (in_array($sku, ['__order__', '__unknown__', ''], true)) {
                    continue;
                }
                $sourceItems[] = [
                    'sku' => $sku,
                    'title' => (string) ($line->display_title ?: $sku),
                    'quantity' => max(1, (int) ($line->quantity ?? 1)),
                    'price' => number_format((float) ($line->amount ?? 0), 2, '.', ''),
                ];
            }
        }

        foreach ($sourceItems as $item) {
            $sku = (string) ($item['sku'] ?? '');
            $variantId = $sku !== '' ? $this->findShopifyVariantIdBySku($sku) : null;
            $matchSource = null;
            if ($sku !== '') {
                $localRow = ShopifySku::firstForProductSku($sku);
                if ($localRow && ! empty($localRow->variant_id)) {
                    $matchSource = 'shopify_skus';
                }
            }
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = (string) ($item['price'] ?? '0.00');
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                $title = $sku !== '' ? $sku : 'Newegg order item';
            }
            $title = mb_substr($title, 0, 255);

            if ($variantId) {
                $line = [
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                    'title' => $title,
                ];
                if ((float) $price > 0) {
                    $line['price'] = $price;
                }
                $resolved[] = $line;
                $meta[] = [
                    'sku' => $sku,
                    'title' => $title,
                    'quantity' => $quantity,
                    'price' => $price,
                    'variant_id' => $variantId,
                    'match_type' => 'variant',
                    'match_source' => $matchSource ?? 'shopify_api',
                ];

                continue;
            }

            $custom = [
                'title' => $title !== '' ? $title : 'Newegg order item',
                'price' => $price,
                'quantity' => $quantity,
            ];
            if ($sku !== '') {
                $custom['sku'] = $sku;
                $custom['properties'] = [['name' => 'SKU', 'value' => mb_substr($sku, 0, 255)]];
            }
            $resolved[] = $custom;
            $meta[] = [
                'sku' => $sku,
                'title' => $title,
                'quantity' => $quantity,
                'price' => $price,
                'variant_id' => null,
                'match_type' => 'custom',
            ];
        }

        if ($resolved === []) {
            $line = $lines->first();
            $resolved[] = [
                'title' => (string) ($line?->display_title ?: 'Newegg order item'),
                'price' => number_format((float) ($line?->amount ?? 0), 2, '.', ''),
                'quantity' => max(1, (int) ($line?->quantity ?? 1)),
            ];
            $meta[] = [
                'sku' => (string) ($line?->sku ?? ''),
                'title' => (string) ($line?->display_title ?: 'Newegg order item'),
                'quantity' => max(1, (int) ($line?->quantity ?? 1)),
                'price' => number_format((float) ($line?->amount ?? 0), 2, '.', ''),
                'variant_id' => null,
                'match_type' => 'custom',
            ];
        }

        $orderPayload['line_items'] = $resolved;

        $missing = collect($meta)->where('match_type', 'custom')->filter(fn ($r) => ($r['sku'] ?? '') !== '');
        if ($missing->isNotEmpty()) {
            $orderPayload['tags'] = trim((string) ($orderPayload['tags'] ?? '').', SKU Missing', ', ');
        }

        return [$orderPayload, $meta];
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $payload
     */
    protected function putOrder(array $config, string $shopifyOrderId, array $payload): bool
    {
        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json';
        $maxAttempts = 5;
        $backoff = [2, 4, 8, 16, 30];

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $config['token'],
                    'Content-Type' => 'application/json',
                ])->timeout(60)->put($url, $payload);

                $this->lastApiStatus = $response->status();

                if ($response->successful()) {
                    return true;
                }

                $retryable = $response->status() === 429 || $response->status() >= 500;
                if ($retryable && $attempt < $maxAttempts) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? 0);
                    $wait = max($retryAfter, $backoff[$attempt - 1] ?? 30);
                    Log::warning('NeweggOrderPushService: Shopify order address update retrying', [
                        'shopify_order_id' => $shopifyOrderId,
                        'status' => $response->status(),
                        'attempt' => $attempt,
                        'wait' => $wait,
                    ]);
                    sleep($wait);

                    continue;
                }

                $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
                Log::error('NeweggOrderPushService: Shopify order address update failed', [
                    'shopify_order_id' => $shopifyOrderId,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'attempts' => $attempt,
                ]);

                return false;
            }

            return false;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();
            Log::error('NeweggOrderPushService: Shopify order address update exception', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $payload
     */
    protected function postOrder(array $config, array $payload): ?string
    {
        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders.json';
        $maxAttempts = 5;
        $backoff = [2, 4, 8, 16, 30];

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
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

                $retryable = $response->status() === 429 || $response->status() >= 500;
                if ($retryable && $attempt < $maxAttempts) {
                    $wait = $backoff[$attempt - 1] ?? 30;
                    Log::warning('NeweggOrderPushService: Shopify order create retrying', [
                        'status' => $response->status(),
                        'attempt' => $attempt,
                        'wait' => $wait,
                    ]);
                    sleep($wait);

                    continue;
                }

                $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
                Log::error('NeweggOrderPushService: Shopify order create failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'attempts' => $attempt,
                ]);

                return null;
            }

            return null;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();
            Log::error('NeweggOrderPushService: exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function addFulfillmentTracking(string $shopifyOrderId, string $trackingNumber, string $carrier): void
    {
        $config = $this->shopifyConfig();
        $storeUrl = $config['store_url'];
        $token = $config['token'];

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}/fulfillment_orders.json");

            if (! $response->successful()) {
                return;
            }

            $lineItems = [];
            foreach ($response->json('fulfillment_orders') ?? [] as $fo) {
                if (! empty($fo['id'])) {
                    $lineItems[] = ['fulfillment_order_id' => $fo['id']];
                }
            }
            if ($lineItems === []) {
                return;
            }

            Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("https://{$storeUrl}/admin/api/2024-01/fulfillments.json", [
                'fulfillment' => [
                    'line_items_by_fulfillment_order' => $lineItems,
                    'tracking_info' => [
                        'number' => $trackingNumber,
                        'company' => mb_substr($carrier, 0, 100),
                    ],
                    'notify_customer' => false,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('NeweggOrderPushService: fulfillment tracking failed', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function findShopifyVariantIdBySku(string $sku): ?int
    {
        $row = ShopifySku::firstForProductSku($sku);
        if ($row && ! empty($row->variant_id)) {
            return (int) $row->variant_id;
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return null;
        }

        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/variants.json?sku='.urlencode($sku);

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
            ])->timeout(30)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $variants = $response->json('variants') ?? [];
            if (! is_array($variants) || $variants === []) {
                return null;
            }

            return (int) ($variants[0]['id'] ?? 0) ?: null;
        } catch (\Throwable $e) {
            Log::warning('NeweggOrderPushService: variant lookup failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{store_url: string, token: string, store_key: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('newegg');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    protected function cleanTags(array $tags): array
    {
        $cleaned = [];
        foreach ($tags as $tag) {
            $t = trim((string) $tag);
            if ($t === '') {
                continue;
            }
            $t = str_replace([',', "\r", "\n"], ' ', $t);
            $t = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $t);
            $t = trim(preg_replace('/\s+/', ' ', $t));
            if ($t !== '') {
                $cleaned[] = $t;
            }
        }

        return array_values(array_unique($cleaned));
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     */
    protected function payloadUsesPlaceholderEmail(array $orderPayload): bool
    {
        foreach ($orderPayload['note_attributes'] ?? [] as $attr) {
            if (($attr['name'] ?? '') === 'newegg_email_is_placeholder' && ($attr['value'] ?? '') === 'true') {
                return true;
            }
        }

        return false;
    }

    /**
     * If live Newegg refresh blanked ship-to / buyer email, restore from cached root.
     *
     * @param  array<string, mixed>  $fresh
     * @param  array<string, mixed>  $cached
     * @return array<string, mixed>
     */
    protected function restoreShippingFromCache(array $fresh, array $cached): array
    {
        if ($cached === []) {
            return $fresh;
        }
        if ($fresh === []) {
            return $cached;
        }

        $freshAddr = is_array($fresh['receipt_address'] ?? null) ? $fresh['receipt_address'] : [];
        $cachedAddr = is_array($cached['receipt_address'] ?? null) ? $cached['receipt_address'] : [];
        $freshLine1 = trim((string) ($freshAddr['address'] ?? $freshAddr['address1'] ?? ''));
        $cachedLine1 = trim((string) ($cachedAddr['address'] ?? $cachedAddr['address1'] ?? ''));

        if ($freshLine1 === '' && $cachedLine1 !== '') {
            $fresh['receipt_address'] = array_merge($cachedAddr, array_filter(
                $freshAddr,
                static fn ($v) => $v !== null && $v !== ''
            ));
        } elseif ($cachedLine1 !== '') {
            foreach (['address', 'address2', 'city', 'province', 'state', 'zip', 'zip_code', 'country', 'country_name', 'contact_person', 'company', 'mobile_no', 'phone_number', 'email'] as $key) {
                $newVal = trim((string) ($freshAddr[$key] ?? ''));
                $oldVal = trim((string) ($cachedAddr[$key] ?? ''));
                if ($newVal === '' && $oldVal !== '') {
                    $freshAddr[$key] = $cachedAddr[$key];
                }
            }
            $fresh['receipt_address'] = $freshAddr;
        }

        $freshEmail = trim((string) (
            $fresh['buyer_email']
            ?? ($fresh['buyer_info']['email'] ?? null)
            ?? ($fresh['receipt_address']['email'] ?? null)
            ?? ''
        ));
        $cachedEmail = trim((string) (
            $cached['buyer_email']
            ?? ($cached['buyer_info']['email'] ?? null)
            ?? ($cached['receipt_address']['email'] ?? null)
            ?? ''
        ));
        if ($freshEmail === '' && $cachedEmail !== '') {
            $fresh['buyer_email'] = $cachedEmail;
            $fresh['buyer_info'] = array_merge(
                is_array($fresh['buyer_info'] ?? null) ? $fresh['buyer_info'] : [],
                is_array($cached['buyer_info'] ?? null) ? $cached['buyer_info'] : [],
                ['email' => $cachedEmail]
            );
            if (is_array($fresh['receipt_address'] ?? null)) {
                $fresh['receipt_address']['email'] = $cachedEmail;
            }
        }

        return $fresh;
    }
}
