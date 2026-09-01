<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmazonOrderPushService
{
    use FindsExistingShopifyOrderByChannelRef;
    use FulfillsShopifyAfterImport;
    use SyncsShopifyOrderAddress;

    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    /** Set when import linked an existing Shopify order instead of creating one. */
    public ?string $lastDuplicateLinkMessage = null;

    /** skipped_fba | skipped_pre_cutoff | skipped_cancelled */
    public ?string $lastSkipStatus = null;

    public function __construct(
        protected AmazonOrderDetailService $orderDetailService,
        protected AmazonDetailFormatter $formatter
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(AmazonOrder $order): array
    {
        $block = $this->createBlockReason($order);
        if ($block !== null && ($block['code'] ?? '') !== 'already_imported') {
            return [
                'success' => false,
                'dry_run' => true,
                'message' => $block['message'],
                'skip_status' => $block['code'],
                'amazon_order_id' => $order->amazon_order_id,
            ];
        }

        $plan = $this->buildImportPlan($order);
        $plan['dry_run'] = true;
        if (! empty($plan['success'])) {
            $plan['message'] = 'Dry run only — no Shopify order was created.';
        }

        return $plan;
    }

    /**
     * @return array{success: bool, skipped?: bool, message: string, shopify_order_id?: string}
     */
    public function syncShippingAddressToShopify(AmazonOrder $order): array
    {
        $shopifyOrderId = trim((string) ($order->shopify_order_id ?? ''));
        if ($shopifyOrderId === '' || str_starts_with($shopifyOrderId, 'manual')) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to Shopify yet — push the order first.',
            ];
        }

        $this->orderDetailService->hydrateRestrictedPii($order);
        $order->refresh();
        $orderRoot = $this->orderDetailService->resolveOrderRoot($order);
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->orderBy('id')->get();
        $detail = $this->formatter->formatOrder($orderRoot, $items, $order);
        $orderPayload = $this->formatter->buildShopifyOrderPayload($detail, $items, ['amazon']);
        $shipping = is_array($orderPayload['shipping_address'] ?? null)
            ? $orderPayload['shipping_address']
            : [];
        $address1 = trim((string) ($shipping['address1'] ?? ''));

        if ($address1 === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Amazon still has no shipping address for this order.',
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

        if (! Schema::hasColumn('amazon_orders', 'shopify_order_id')) {
            return [
                'success' => true,
                'checked' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'message' => 'Shopify import columns missing on amazon_orders.',
            ];
        }

        $rows = AmazonOrder::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderByDesc('id')
            ->limit($limit * 3)
            ->get();

        $checked = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows as $line) {
            if (str_starts_with((string) $line->shopify_order_id, 'manual')) {
                continue;
            }
            $checked++;
            if ($checked > $limit) {
                break;
            }

            if (! $this->shopifyOrderNeedsAddress((string) $line->shopify_order_id, [
                ['amazon', 'customer'],
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
                Log::warning('AmazonOrderPushService: auto address sync failed', [
                    'amazon_order_id' => $line->amazon_order_id,
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
        $settings ??= MarketplaceSyncSettings::getFor('amazon');

        return (bool) ($settings['order']['sync_address_to_shopify'] ?? true);
    }

    public function importToShopify(AmazonOrder $order): ?string
    {
        $this->lastDuplicateLinkMessage = null;
        $this->lastSkipStatus = null;
        $this->lastFailureReason = null;

        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            $this->fulfillShopifyForImportedMarketplaceOrder('amazon', (int) $order->id, [
                'amazon_order_id' => (string) $order->amazon_order_id,
            ]);

            return (string) $order->shopify_order_id;
        }

        $amazonOrderId = trim((string) $order->amazon_order_id);
        if ($amazonOrderId === '') {
            $this->lastFailureReason = 'Amazon order id is missing.';

            return null;
        }

        if ($order->isFba()) {
            $this->markSkip($order, 'skipped_fba');
            $this->lastFailureReason = 'FBA (AFN) orders are not created on Shopify.';

            return null;
        }

        if ($order->isCancelled()) {
            $this->markSkip($order, 'skipped_cancelled');
            $this->lastFailureReason = 'Cancelled Amazon orders are not created on Shopify.';

            return null;
        }

        $localLinked = AmazonOrder::query()
            ->where('amazon_order_id', $amazonOrderId)
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->value('shopify_order_id');
        if ($localLinked) {
            $this->linkAmazonOrderToShopify($order, (string) $localLinked);
            $this->lastDuplicateLinkMessage = 'Linked to existing Shopify order '.$localLinked.' (local sibling).';
            $this->fulfillShopifyForImportedMarketplaceOrder('amazon', (int) $order->id, [
                'amazon_order_id' => $amazonOrderId,
            ]);

            return (string) $localLinked;
        }

        $config = $this->shopifyConfig();
        $existing = $this->findExistingShopifyOrderByRefs(
            $config,
            [$amazonOrderId],
            ['amazon-', 'amz-'],
            ['amazon_order_id', 'amazon-order-id', 'AmazonOrderId'],
            'AmazonOrderPushService'
        );
        if (($existing['error'] ?? null) !== null) {
            $this->lastFailureReason = $existing['error'].' Push blocked to avoid duplicates.';

            return null;
        }
        if (! empty($existing['id'])) {
            $this->linkAmazonOrderToShopify($order, (string) $existing['id']);
            $this->lastDuplicateLinkMessage = 'Linked to existing Shopify order '.$existing['id']
                .' (matched '.$existing['matched_by'].'). No new order created.';
            Log::info('AmazonOrderPushService: linked existing Shopify order (duplicate avoided)', [
                'amazon_order_id' => $amazonOrderId,
                'shopify_order_id' => $existing['id'],
                'matched_by' => $existing['matched_by'],
            ]);
            try {
                $this->syncShippingAddressToShopify($order->fresh() ?? $order);
            } catch (\Throwable $e) {
                Log::warning('AmazonOrderPushService: address sync after link failed', [
                    'amazon_order_id' => $amazonOrderId,
                    'error' => $e->getMessage(),
                ]);
            }
            $this->fulfillShopifyForImportedMarketplaceOrder('amazon', (int) $order->id, [
                'amazon_order_id' => $amazonOrderId,
            ]);

            return (string) $existing['id'];
        }

        if (! $order->isOnOrAfterShopifyImportCutoff()) {
            $this->markSkip($order, 'skipped_pre_cutoff');
            $this->lastFailureReason = 'Order is before '.AmazonOrder::SHOPIFY_IMPORT_CUTOFF_DATE
                .' PT (previous Amazon sync app). Not creating a new Shopify order.';

            return null;
        }

        $plan = $this->buildImportPlan($order);
        if (empty($plan['success'])) {
            $this->lastFailureReason = $plan['message'] ?? 'Could not build Shopify import plan.';

            return null;
        }

        $order->refresh();
        $shopifyOrderId = $this->postOrderGuarded(
            $config,
            ['order' => $plan['payload']],
            [$amazonOrderId],
            ['amazon-', 'amz-'],
            ['amazon_order_id', 'amazon-order-id', 'AmazonOrderId'],
            'AmazonOrderPushService',
            $order->shopify_order_id
        );
        if (! $shopifyOrderId) {
            return null;
        }

        $this->linkAmazonOrderToShopify($order, $shopifyOrderId);
        $this->fulfillShopifyForImportedMarketplaceOrder('amazon', (int) ($order->fresh()?->id ?? $order->id), [
            'amazon_order_id' => $amazonOrderId,
        ]);
        if ($this->lastDuplicateLinkMessage === null) {
            $this->syncInventoryAfterPush($order);
        }

        return $shopifyOrderId;
    }

    /**
     * @return array{code: string, message: string}|null
     */
    public function createBlockReason(AmazonOrder $order): ?array
    {
        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return [
                'code' => 'already_imported',
                'message' => 'Already imported.',
            ];
        }
        if ($order->isFba()) {
            return [
                'code' => 'skipped_fba',
                'message' => 'FBA (AFN) orders are not created on Shopify.',
            ];
        }
        if ($order->isCancelled()) {
            return [
                'code' => 'skipped_cancelled',
                'message' => 'Cancelled Amazon orders are not created on Shopify.',
            ];
        }
        if (! $order->isOnOrAfterShopifyImportCutoff()) {
            return [
                'code' => 'skipped_pre_cutoff',
                'message' => 'Order is before '.AmazonOrder::SHOPIFY_IMPORT_CUTOFF_DATE
                    .' PT (previous Amazon sync app). Not creating a new Shopify order.',
            ];
        }

        return null;
    }

    protected function linkAmazonOrderToShopify(AmazonOrder $order, string $shopifyOrderId): void
    {
        if (! Schema::hasColumn('amazon_orders', 'shopify_order_id')) {
            return;
        }

        $payload = [
            'shopify_order_id' => $shopifyOrderId,
            'pushed_to_shopify_at' => now(),
            'import_status' => 'imported',
            'fulfillment_channel' => $order->fulfillmentChannel() ?: $order->fulfillment_channel,
        ];
        $amazonOrderId = trim((string) ($order->amazon_order_id ?? ''));
        if ($amazonOrderId !== '') {
            AmazonOrder::query()
                ->where('amazon_order_id', $amazonOrderId)
                ->update($payload);

            return;
        }

        $order->update($payload);
    }

    protected function markSkip(AmazonOrder $order, string $status): void
    {
        $this->lastSkipStatus = $status;
        if (! Schema::hasColumn('amazon_orders', 'import_status')) {
            return;
        }
        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return;
        }

        $order->update([
            'import_status' => $status,
            'fulfillment_channel' => $order->fulfillmentChannel() ?: $order->fulfillment_channel,
        ]);
    }

    protected function syncInventoryAfterPush(AmazonOrder $order): void
    {
        $skus = AmazonOrderItem::query()
            ->where('amazon_order_id', $order->id)
            ->pluck('sku')
            ->map(static fn ($sku) => trim((string) $sku))
            ->filter(static fn ($sku) => $sku !== '')
            ->unique()
            ->values()
            ->all();

        if ($skus === []) {
            return;
        }

        usleep(1500000);

        try {
            $result = app(AmazonInventorySyncService::class)
                ->syncSkusFromShopify($skus, $this->shopifyConfig());

            Log::info('AmazonOrderPushService: post-push inventory sync', [
                'amazon_order_id' => $order->amazon_order_id,
                'skus' => $skus,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AmazonOrderPushService: post-push inventory sync failed', [
                'amazon_order_id' => $order->amazon_order_id,
                'skus' => $skus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildImportPlan(AmazonOrder $order): array
    {
        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return [
                'success' => false,
                'message' => 'Already imported.',
                'shopify_order_id' => (string) $order->shopify_order_id,
            ];
        }

        $block = $this->createBlockReason($order);
        if ($block !== null && ($block['code'] ?? '') !== 'already_imported') {
            return [
                'success' => false,
                'message' => $block['message'],
                'skip_status' => $block['code'],
            ];
        }

        $pii = $this->orderDetailService->hydrateRestrictedPii($order);
        $order->refresh();
        $orderRoot = $this->orderDetailService->resolveOrderRoot($order);
        if ($orderRoot === []) {
            return [
                'success' => false,
                'message' => 'No Amazon order payload available to build a Shopify order.',
            ];
        }
        if (empty($pii['success'])) {
            Log::warning('AmazonOrderPushService: shipping PII hydrate failed before Shopify create', [
                'amazon_order_id' => $order->amazon_order_id,
                'message' => $pii['message'] ?? null,
            ]);
        }

        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->orderBy('id')->get();

        $detail = $this->formatter->formatOrder($orderRoot, $items, $order);

        $settings = MarketplaceSyncSettings::getFor('amazon');
        $tags = array_values(array_unique(array_merge(
            ['amazon', 'amazon-'.$order->amazon_order_id, 'fbm'],
            $settings['order']['shopify_order_tags'] ?? []
        )));

        $orderPayload = $this->formatter->buildShopifyOrderPayload($detail, $items, $this->cleanTags($tags));
        [$orderPayload, $lineResolution] = $this->resolveLineItemsForShopify($orderPayload, $items);

        $config = $this->shopifyConfig();
        $warnings = $this->buildImportWarnings($detail, $lineResolution, $orderPayload);

        return [
            'success' => true,
            'amazon_order_id' => $order->amazon_order_id,
            'shopify_store' => $config['store_url'],
            'shopify_store_key' => $config['store_key'] ?? null,
            'payload' => $orderPayload,
            'line_resolution' => $lineResolution,
            'warnings' => array_values(array_unique($warnings)),
            'preview' => $this->buildHumanPreview($detail, $orderPayload, $lineResolution),
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
        if (empty($orderPayload['shipping_address']['address1'] ?? null)) {
            $warnings[] = 'No shipping address — Shopify order will be created without shipping_address.';
        }
        if ($this->payloadUsesPlaceholderEmail($orderPayload)) {
            $warnings[] = 'Amazon did not provide a buyer email — a placeholder email will be used for Shopify.';
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
     * @return array<string, mixed>
     */
    protected function buildHumanPreview(array $detail, array $orderPayload, array $lineResolution): array
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
            'line_items' => $lineResolution,
            'tags' => $orderPayload['tags'] ?? null,
            'note' => $orderPayload['note'] ?? null,
            'channel' => [
                'display_name' => $this->formatter->shopifySourceDisplayName(),
                'source_name' => $orderPayload['source_name'] ?? null,
                'source_identifier' => $orderPayload['source_identifier'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @param  Collection<int, AmazonOrderItem>  $lines
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    protected function resolveLineItemsForShopify(array $orderPayload, Collection $lines): array
    {
        $resolved = [];
        $meta = [];
        $sourceItems = $orderPayload['line_items'] ?? [];

        if ($sourceItems === []) {
            foreach ($lines as $line) {
                $sku = trim((string) ($line->sku ?? ''));
                if ($sku === '') {
                    continue;
                }
                $qty = max(1, (int) ($line->quantity ?? 1));
                $unit = $qty > 0 ? ((float) ($line->price ?? 0)) / $qty : (float) ($line->price ?? 0);
                $sourceItems[] = [
                    'sku' => $sku,
                    'title' => (string) ($line->title ?: $sku),
                    'quantity' => $qty,
                    'price' => number_format($unit, 2, '.', ''),
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
                $title = $sku !== '' ? $sku : 'Amazon order item';
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
                'title' => $title !== '' ? $title : 'Amazon order item',
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

        $orderPayload['line_items'] = $resolved;

        if ($resolved === []) {
            $line = $lines->first();
            $qty = max(1, (int) ($line?->quantity ?? 1));
            $price = number_format((float) ($line?->price ?? 0), 2, '.', '');
            $title = (string) ($line?->title ?: 'Amazon order item');
            $resolved[] = [
                'title' => $title,
                'price' => $price,
                'quantity' => $qty,
            ];
            $meta[] = [
                'sku' => (string) ($line?->sku ?? ''),
                'title' => $title,
                'quantity' => $qty,
                'price' => $price,
                'variant_id' => null,
                'match_type' => 'custom',
            ];
            $orderPayload['line_items'] = $resolved;
        }

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
                    Log::warning('AmazonOrderPushService: Shopify order create retrying', [
                        'status' => $response->status(),
                        'attempt' => $attempt,
                        'wait' => $wait,
                    ]);
                    sleep($wait);

                    continue;
                }

                $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
                Log::error('AmazonOrderPushService: Shopify order create failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'attempts' => $attempt,
                ]);

                return null;
            }

            return null;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();
            Log::error('AmazonOrderPushService: exception', ['error' => $e->getMessage()]);

            return null;
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
            Log::warning('AmazonOrderPushService: variant lookup failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{store_url: string, token: string, store_key: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('amazon');
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
            if (($attr['name'] ?? '') === 'amazon_email_is_placeholder' && ($attr['value'] ?? '') === 'true') {
                return true;
            }
        }

        return false;
    }
}
