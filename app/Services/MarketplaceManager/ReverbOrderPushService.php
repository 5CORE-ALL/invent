<?php

namespace App\Services\MarketplaceManager;

use App\Models\ReverbOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReverbOrderPushService
{
    use SyncsShopifyOrderAddress;
    use FindsExistingShopifyOrderByChannelRef;
    use FulfillsShopifyAfterImport;

    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    public ?string $lastDuplicateLinkMessage = null;

    public function __construct(
        protected ReverbOrderDetailService $orderDetailService,
        protected ReverbDetailFormatter $formatter
    ) {}

    /**
     * Push Reverb shipping address onto an already-imported Shopify order.
     *
     * @return array{success: bool, skipped?: bool, message: string, shopify_order_id?: string}
     */
    public function syncShippingAddressToShopify(ReverbOrderMetric $order): array
    {
        $shopifyOrderId = trim((string) ($order->shopify_order_id ?? ''));
        if ($shopifyOrderId === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to Shopify yet — push the order first.',
            ];
        }

        $orderRef = $order->orderRef();
        if ($orderRef === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Reverb order id missing.',
            ];
        }

        $lines = ReverbOrderMetric::query()
            ->where(function ($q) use ($orderRef) {
                $q->where('order_id', $orderRef)->orWhere('order_number', $orderRef);
            })
            ->orderBy('id')
            ->get();

        $orderRoot = $this->orderDetailService->resolveOrderRoot($order);
        if ($orderRoot === []) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No Reverb order payload available to build an address.',
            ];
        }

        $detail = $this->formatter->formatOrder($orderRoot, $lines, $order);
        $orderPayload = $this->formatter->buildShopifyOrderPayload($detail, $lines, ['reverb']);
        $shipping = is_array($orderPayload['shipping_address'] ?? null)
            ? $orderPayload['shipping_address']
            : [];
        $address1 = trim((string) ($shipping['address1'] ?? ''));

        if ($address1 === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Reverb still has no shipping address for this order.',
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

        $ok = $this->putShopifyOrder($config, $shopifyOrderId, ['order' => $update]);
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
     * @return array{success: bool, checked: int, updated: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingAddressesToShopify(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        $rows = ReverbOrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderByDesc('id')
            ->limit($limit * 5)
            ->get(['id', 'order_id', 'order_number', 'shopify_order_id']);

        $unique = [];
        foreach ($rows as $row) {
            $ref = $row->orderRef();
            if ($ref === '' || isset($unique[$ref])) {
                continue;
            }
            $unique[$ref] = $row;
            if (count($unique) >= $limit) {
                break;
            }
        }

        $checked = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($unique as $line) {
            $checked++;
            $ref = $line->orderRef();

            try {
                $this->orderDetailService->fetchAndPersistOrderDetail($ref);
                $line->refresh();
            } catch (\Throwable $e) {
                // Use cached payload.
            }

            if (! $this->shopifyOrderNeedsAddress((string) $line->shopify_order_id, [
                ['reverb', 'customer'],
            ])) {
                $skipped++;
                usleep(200000);

                continue;
            }

            $result = $this->syncShippingAddressToShopify($line);
            if (! empty($result['success']) && empty($result['skipped'])) {
                $updated++;
            } elseif (! empty($result['skipped'])) {
                $skipped++;
            } else {
                $failed++;
                Log::warning('ReverbOrderPushService: auto address sync failed', [
                    'order_id' => $ref,
                    'shopify_order_id' => $line->shopify_order_id,
                    'message' => $result['message'] ?? null,
                ]);
            }
            usleep(350000);
        }

        return [
            'success' => $failed === 0,
            'checked' => $checked,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "Address sync: checked {$checked}, updated {$updated}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    public static function canAutoSyncAddress(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('reverb');

        return (bool) ($settings['order']['sync_address_to_shopify'] ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(ReverbOrderMetric $order): array
    {
        $plan = $this->buildImportPlan($order);
        $plan['dry_run'] = true;
        if (! empty($plan['success'])) {
            $plan['message'] = 'Dry run only — no Shopify order was created.';
        }

        return $plan;
    }

    public function importToShopify(ReverbOrderMetric $order): ?string
    {
        $this->lastDuplicateLinkMessage = null;

        if ($order->shopify_order_id) {
            return (string) $order->shopify_order_id;
        }

        $orderRef = trim((string) $order->orderRef());
        $orderId = trim((string) ($order->order_id ?? ''));
        $orderNumber = trim((string) ($order->order_number ?? ''));

        if ($orderRef !== '') {
            $localLinked = ReverbOrderMetric::query()
                ->where(function ($q) use ($orderRef) {
                    $q->where('order_id', $orderRef)->orWhere('order_number', $orderRef);
                })
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->value('shopify_order_id');
            if ($localLinked) {
                $this->linkReverbOrderToShopify($orderRef, (string) $localLinked);
                $this->lastDuplicateLinkMessage = 'Linked to existing Shopify order '.$localLinked.' (local sibling).';
                $this->fulfillShopifyForImportedMarketplaceOrder('reverb', (int) $order->id, ['order_ref' => $orderRef]);

                return (string) $localLinked;
            }
        }

        $config = $this->shopifyConfig();
        $existing = $this->findExistingShopifyOrderByRefs(
            $config,
            array_values(array_filter([$orderRef, $orderId, $orderNumber])),
            ['reverb-'],
            ['reverb_order_id'],
            'ReverbOrderPushService'
        );
        if (($existing['error'] ?? null) !== null) {
            $this->lastFailureReason = $existing['error'].' Push blocked to avoid duplicates.';

            return null;
        }
        if (! empty($existing['id'])) {
            if ($orderRef !== '') {
                $this->linkReverbOrderToShopify($orderRef, (string) $existing['id']);
            } else {
                $order->update([
                    'shopify_order_id' => (string) $existing['id'],
                    'pushed_to_shopify_at' => now(),
                    'import_status' => 'imported',
                ]);
            }
            $this->lastDuplicateLinkMessage = 'Linked to existing Shopify order '.$existing['id']
                .' (matched '.$existing['matched_by'].'). No new order created.';
            Log::info('ReverbOrderPushService: linked existing Shopify order (duplicate avoided)', [
                'order_ref' => $orderRef,
                'shopify_order_id' => $existing['id'],
                'matched_by' => $existing['matched_by'],
            ]);
            $this->fulfillShopifyForImportedMarketplaceOrder('reverb', (int) $order->id, ['order_ref' => $orderRef]);

            return (string) $existing['id'];
        }

        $plan = $this->buildImportPlan($order);
        if (empty($plan['success'])) {
            $this->lastFailureReason = $plan['message'] ?? 'Could not build Shopify import plan.';

            return null;
        }

        $shopifyOrderId = $this->postOrderGuarded(
            $config,
            ['order' => $plan['payload']],
            array_values(array_filter([$orderRef, $orderId, $orderNumber])),
            ['reverb-'],
            ['reverb_order_id'],
            'ReverbOrderPushService',
            $order->fresh()?->shopify_order_id
        );
        if (! $shopifyOrderId) {
            return null;
        }

        if ($this->lastDuplicateLinkMessage === null) {
            $fulfillment = is_array($plan['fulfillment'] ?? null) ? $plan['fulfillment'] : [];
            $tracking = (string) ($fulfillment['tracking'] ?? '');
            $carrier = (string) ($fulfillment['carrier'] ?? 'Reverb');
            if ($tracking !== '') {
                $this->addFulfillmentTracking($shopifyOrderId, $tracking, $carrier);
            }
        }

        $this->linkReverbOrderToShopify($orderRef !== '' ? $orderRef : (string) $order->orderRef(), $shopifyOrderId);
        $this->fulfillShopifyForImportedMarketplaceOrder('reverb', (int) ($order->fresh()?->id ?? $order->id), ['order_ref' => $orderRef]);

        if ($this->lastDuplicateLinkMessage === null) {
            $this->syncInventoryAfterPush($order);
        }

        return $shopifyOrderId;
    }

    protected function linkReverbOrderToShopify(string $orderRef, string $shopifyOrderId): void
    {
        ReverbOrderMetric::query()
            ->where(function ($q) use ($orderRef) {
                $q->where('order_id', $orderRef)->orWhere('order_number', $orderRef);
            })
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
                'import_status' => 'imported',
            ]);
    }

    /**
     * After Shopify decrements stock for the imported order, push live qty to Reverb
     * and refresh local AE/Shopify quantity fields used by the platform UI.
     */
    protected function syncInventoryAfterPush(ReverbOrderMetric $order): void
    {
        $skus = ReverbOrderMetric::query()
            ->where(function ($q) use ($order) {
                $ref = $order->orderRef();
                $q->where('order_id', $ref)->orWhere('order_number', $ref);
            })
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
            $result = app(ReverbInventorySyncService::class)
                ->syncSkusFromShopify($skus, $this->shopifyConfig());

            Log::info('ReverbOrderPushService: post-push inventory sync', [
                'order_id' => $order->order_id,
                'skus' => $skus,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ReverbOrderPushService: post-push inventory sync failed', [
                'order_id' => $order->order_id,
                'skus' => $skus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildImportPlan(ReverbOrderMetric $order): array
    {
        if ($order->shopify_order_id) {
            return [
                'success' => false,
                'message' => 'Already imported.',
                'shopify_order_id' => (string) $order->shopify_order_id,
            ];
        }

        $orderRef = $order->orderRef();
        if ($orderRef === '') {
            return [
                'success' => false,
                'message' => 'Order ID is missing on this row (order_id/order_number empty).',
            ];
        }

        $detailResult = $this->orderDetailService->fetchAndPersistOrderDetail($orderRef);
        if (empty($detailResult['success'])) {
            return [
                'success' => false,
                'message' => $detailResult['message'] ?? 'Could not load Reverb order details before Shopify push.',
            ];
        }

        $order->refresh();
        $orderRef = $order->orderRef() ?: $orderRef;

        $lines = ReverbOrderMetric::query()
            ->where(function ($q) use ($orderRef) {
                $q->where('order_id', $orderRef)->orWhere('order_number', $orderRef);
            })
            ->orderBy('id')
            ->get();

        $orderRoot = $this->orderDetailService->resolveOrderRoot($order);
        $detail = $this->formatter->formatOrder($orderRoot, $lines, $order);

        $settings = MarketplaceSyncSettings::getFor('reverb');
        $tags = array_values(array_unique(array_merge(
            ['reverb', 'reverb-'.$orderRef],
            $settings['order']['shopify_order_tags'] ?? []
        )));

        $orderPayload = $this->formatter->buildShopifyOrderPayload($detail, $lines, $this->cleanTags($tags));
        [$orderPayload, $lineResolution] = $this->resolveLineItemsForShopify($orderPayload, $lines);

        $config = $this->shopifyConfig();
        $tracking = (string) (($detail['shipment']['tracking'] ?? '') ?: '');
        $carrier = (string) (($detail['shipment']['service'] ?? '') ?: 'Reverb');

        $warnings = $this->buildImportWarnings($detail, $lineResolution, $orderPayload);

        return [
            'success' => true,
            'reverb_order_id' => $orderRef,
            'shopify_store' => $config['store_url'],
            'shopify_store_key' => $config['store_key'] ?? null,
            'payload' => $orderPayload,
            'fulfillment' => [
                'tracking' => $tracking !== '' ? $tracking : null,
                'carrier' => $carrier !== '' ? $carrier : null,
                'will_create' => $tracking !== '',
            ],
            'line_resolution' => $lineResolution,
            'warnings' => $warnings,
            'preview' => $this->buildHumanPreview($detail, $orderPayload, $lineResolution, $fulfillment = [
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
            $warnings[] = 'Reverb did not provide a buyer email — a placeholder email will be used for Shopify.';
        } elseif (empty($shipping['email'] ?? null) && empty($orderPayload['customer']['email'] ?? null)) {
            $warnings[] = 'No buyer email on Reverb order.';
        }

        $sourceName = trim((string) ($orderPayload['source_name'] ?? ''));
        if ($sourceName === '') {
            $warnings[] = 'No Shopify source_name set — Channel information may show your app name instead of Reverb.';
        }
        if (empty($shipping['phone'] ?? null)) {
            $warnings[] = 'No buyer phone on Reverb order.';
        }

        foreach ($lineResolution as $row) {
            if (($row['match_type'] ?? '') === 'custom') {
                $warnings[] = 'SKU not found in Shopify: '.($row['sku'] ?? '?').' — will use custom line item.';
            }
        }

        if (($lineResolution === [])) {
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
     * @param  Collection<int, ReverbOrderMetric>  $lines
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
                $title = $sku !== '' ? $sku : 'Reverb order item';
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
                'title' => $title !== '' ? $title : 'Reverb order item',
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
                'title' => (string) ($line?->display_title ?: 'Reverb order item'),
                'price' => number_format((float) ($line?->amount ?? 0), 2, '.', ''),
                'quantity' => max(1, (int) ($line?->quantity ?? 1)),
            ];
            $meta[] = [
                'sku' => (string) ($line?->sku ?? ''),
                'title' => (string) ($line?->display_title ?: 'Reverb order item'),
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
                    Log::warning('ReverbOrderPushService: Shopify order create retrying', [
                        'status' => $response->status(),
                        'attempt' => $attempt,
                        'wait' => $wait,
                    ]);
                    sleep($wait);

                    continue;
                }

                $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
                Log::error('ReverbOrderPushService: Shopify order create failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'attempts' => $attempt,
                ]);

                return null;
            }

            return null;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();
            Log::error('ReverbOrderPushService: exception', ['error' => $e->getMessage()]);

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
            Log::warning('ReverbOrderPushService: fulfillment tracking failed', [
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
            Log::warning('ReverbOrderPushService: variant lookup failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{store_url: string, token: string, store_key: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('reverb');
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
            if (($attr['name'] ?? '') === 'reverb_email_is_placeholder' && ($attr['value'] ?? '') === 'true') {
                return true;
            }
        }

        return false;
    }
}
