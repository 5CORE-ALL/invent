<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Models\TiktokOrder;
use App\Services\ShopifyStoreSelector;
use App\Services\TikTokShopService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokOrderPushService
{
    use FindsExistingShopifyOrderByChannelRef;
    use ResolvesTikTokOrderRawJson;
    use SyncsShopifyOrderAddress;
    use FulfillsShopifyAfterImport;

    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    /** Set when import linked an existing Shopify order instead of creating one. */
    public ?string $lastDuplicateLinkMessage = null;

    /** Tracking push creates many orders in one click; inventory sync can run after. */
    public bool $deferInventorySync = false;

    public function importToShopify(TiktokOrder $order): ?string
    {
        $this->lastDuplicateLinkMessage = null;

        if ($order->shopify_order_id) {
            $this->fulfillShopifyForImportedMarketplaceOrder('tiktok', (int) $order->id, ['order_id' => (string) $order->order_id]);

            return (string) $order->shopify_order_id;
        }

        $orderId = trim((string) $order->order_id);

        // Local sibling already linked → copy link, never create another Shopify order.
        if ($orderId !== '') {
            $localLinked = TiktokOrder::query()
                ->where('order_id', $orderId)
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->value('shopify_order_id');
            if ($localLinked) {
                $this->linkTikTokOrderToShopify($orderId, (string) $localLinked);
                $this->lastDuplicateLinkMessage = 'Linked to existing Shopify order '.$localLinked.' (local sibling).';
            $fresh = $order->fresh() ?? $order;
            $fresh->shopify_order_id = (string) $localLinked;
            $this->syncShippingAddressToShopify($fresh);
            $this->fulfillShopifyForImportedMarketplaceOrder('tiktok', (int) $fresh->id, ['order_id' => $orderId]);

            return (string) $localLinked;
            }
        }

        $localCatalog = $this->findLocalShopifyTikTokCopy($orderId, 'TT-', 'tiktok');
        if ($localCatalog) {
            $this->linkTikTokOrderToShopify($orderId, $localCatalog);
            $this->lastDuplicateLinkMessage = 'Linked to existing Shopify order '.$localCatalog.' (local catalog).';
            $fresh = $order->fresh() ?? $order;
            $fresh->shopify_order_id = $localCatalog;
            $this->syncShippingAddressToShopify($fresh);
            $this->fulfillShopifyForImportedMarketplaceOrder('tiktok', (int) $fresh->id, ['order_id' => $orderId]);

            return $localCatalog;
        }

        $config = $this->shopifyConfig();
        $existing = $this->findExistingShopifyOrderByRefs(
            $config,
            $this->tikTokShopifyDuplicateRefs($orderId, 'TT-'),
            ['tiktok-'],
            ['tiktok_order_id'],
            'TikTokOrderPushService'
        );
        if (($existing['error'] ?? null) !== null) {
            Log::warning('TikTokOrderPushService: Shopify duplicate search failed, creating the order anyway', [
                'order_id' => $orderId,
                'error' => $existing['error'],
            ]);
        } elseif (! empty($existing['id'])) {
            if ($orderId !== '') {
                $this->linkTikTokOrderToShopify($orderId, (string) $existing['id']);
            } else {
                $order->update([
                    'shopify_order_id' => (string) $existing['id'],
                    'pushed_to_shopify_at' => now(),
                    'import_status' => 'imported',
                ]);
            }
            $this->lastDuplicateLinkMessage = 'Linked to existing Shopify order '.$existing['id']
                .' (matched '.$existing['matched_by'].'). No new order created.';
            Log::info('TikTokOrderPushService: linked existing Shopify order (duplicate avoided)', [
                'order_id' => $orderId,
                'shopify_order_id' => $existing['id'],
                'matched_by' => $existing['matched_by'],
            ]);
            $fresh = $order->fresh() ?? $order;
            $fresh->shopify_order_id = (string) $existing['id'];
            $this->syncShippingAddressToShopify($fresh);
            $this->fulfillShopifyForImportedMarketplaceOrder('tiktok', (int) $fresh->id, ['order_id' => $orderId]);

            return (string) $existing['id'];
        }

        $plan = $this->buildImportPlan($order);
        if (empty($plan['success'])) {
            $this->lastFailureReason = $plan['message'] ?? 'Could not build Shopify import plan.';

            return null;
        }

        $order->refresh();
        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return (string) $order->shopify_order_id;
        }
        $refs = $this->tikTokShopifyDuplicateRefs($orderId, 'TT-');
        $shopifyOrderId = $this->createShopifyOrderOnce(
            'TikTokOrderPushService',
            $refs,
            function () use ($config, $refs) {
                $found = $this->findExistingShopifyOrderByRefs($config, $refs, ['tiktok-'], ['tiktok_order_id'], 'TikTokOrderPushService');
                // TikTok has always created when the search itself fails; the claim still stops parallel creates.
                return ['id' => $found['id'] ?? null, 'matched_by' => $found['matched_by'] ?? null, 'error' => null];
            },
            fn () => $this->postTikTokOrder($config, $plan['payload'])
        );
        if (! $shopifyOrderId) {
            return null;
        }

        TiktokOrder::query()
            ->where('order_id', (string) $order->order_id)
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
                'import_status' => 'imported',
            ]);

        $shipping = is_array($plan['payload']['shipping_address'] ?? null) ? $plan['payload']['shipping_address'] : [];
        $customer = is_array($plan['payload']['customer'] ?? null) ? $plan['payload']['customer'] : [];
        if (! empty($shipping['address1'])) {
            $this->syncShopifyCustomerFromAddress($config, $shopifyOrderId, $shipping, $customer);
        }

        if ($this->lastDuplicateLinkMessage === null && ! $this->deferInventorySync) {
            $this->syncInventoryAfterPush($order);
        }

        $fresh = $order->fresh() ?? $order;
        $fresh->shopify_order_id = $shopifyOrderId;
        $this->syncShippingAddressToShopify($fresh);
        $this->fulfillShopifyForImportedMarketplaceOrder('tiktok', (int) $fresh->id, ['order_id' => $orderId]);

        return $shopifyOrderId;
    }

    /**
     * Link a TikTok order to a Shopify order that already exists. Does not create one.
     */
    public function linkExistingShopifyOrder(TiktokOrder $order): ?string
    {
        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return (string) $order->shopify_order_id;
        }

        $orderId = trim((string) $order->order_id);
        if ($orderId === '') {
            return null;
        }

        $localLinked = TiktokOrder::query()
            ->where('order_id', $orderId)
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->value('shopify_order_id');
        if ($localLinked) {
            $this->linkTikTokOrderToShopify($orderId, (string) $localLinked);

            return (string) $localLinked;
        }

        $localCatalog = $this->findLocalShopifyTikTokCopy($orderId, 'TT-', 'tiktok');
        if ($localCatalog) {
            $this->linkTikTokOrderToShopify($orderId, $localCatalog);

            return $localCatalog;
        }

        $existing = $this->findExistingShopifyOrderByRefs(
            $this->shopifyConfig(),
            $this->tikTokShopifyDuplicateRefs($orderId, 'TT-'),
            ['tiktok-'],
            ['tiktok_order_id'],
            'TikTokOrderPushService'
        );
        if (! empty($existing['id'])) {
            $this->linkTikTokOrderToShopify($orderId, (string) $existing['id']);

            return (string) $existing['id'];
        }

        return null;
    }

    protected function linkTikTokOrderToShopify(string $orderId, string $shopifyOrderId): void
    {
        TiktokOrder::query()
            ->where('order_id', $orderId)
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
                'import_status' => 'imported',
            ]);
    }

    public function syncShippingAddressToShopify(TiktokOrder $order): array
    {
        $shopifyOrderId = trim((string) ($order->shopify_order_id ?? ''));
        if ($shopifyOrderId === '') {
            return ['success' => false, 'skipped' => true, 'message' => 'Order not linked to Shopify yet.'];
        }

        $shipping = $this->resolveShopifyShippingFromOrder($order, enrich: true);
        if (empty($shipping['address1'])) {
            return ['success' => false, 'skipped' => true, 'message' => 'No shipping address in TikTok order data.'];
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return ['success' => false, 'message' => 'Shopify store credentials not configured.'];
        }

        $shipping = $this->withShopifyCountryName($shipping);
        $update = [
            'id' => (int) $shopifyOrderId,
            'shipping_address' => $shipping,
            'billing_address' => $shipping,
        ];

        $ok = $this->putShopifyOrder($config, $shopifyOrderId, ['order' => $update]);
        if (! $ok) {
            return ['success' => false, 'message' => $this->lastFailureReason ?: 'Shopify address update failed.'];
        }

        $this->syncShopifyCustomerFromAddress($config, $shopifyOrderId, $shipping, []);

        return ['success' => true, 'message' => 'Shopify shipping address updated.'];
    }

    public function syncPendingAddressesToShopify(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        // Prefer open orders that still have address payload (newest-only often burns the
        // budget on rows Shopify already has, or TikTok privacy-masked with no address).
        $rows = TiktokOrder::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->whereIn('order_status', [
                'AWAITING_SHIPMENT',
                'PARTIALLY_SHIPPING',
                'AWAITING_COLLECTION',
                'ON_HOLD',
            ])
            ->where(function ($q) {
                $q->where('raw_json', 'like', '%recipient_address%')
                    ->orWhere('raw_json', 'like', '%address_line%')
                    ->orWhere('raw_json', 'like', '%address_detail%')
                    ->orWhere('raw_json', 'like', '%district_info%')
                    ->orWhere('raw_json', 'like', '%full_address%');
            })
            ->orderByDesc('id')
            ->limit($limit * 25)
            ->get();

        if ($rows->count() < $limit) {
            $extra = TiktokOrder::query()
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->whereIn('order_status', [
                    'AWAITING_SHIPMENT',
                    'PARTIALLY_SHIPPING',
                    'AWAITING_COLLECTION',
                    'ON_HOLD',
                ])
                ->whereNotIn('id', $rows->pluck('id')->all() ?: [0])
                ->orderByDesc('id')
                ->limit(($limit - $rows->count()) * 10)
                ->get();
            $rows = $rows->concat($extra);
        }

        $unique = [];
        foreach ($rows as $row) {
            $ref = trim((string) $row->order_id);
            if ($ref === '' || isset($unique[$ref])) {
                continue;
            }
            $unique[$ref] = $row;
            if (count($unique) >= $limit * 3) {
                break;
            }
        }

        $checked = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($unique as $line) {
            if ($updated + $failed >= $limit) {
                break;
            }

            $checked++;

            if (! $this->shopifyOrderNeedsAddress((string) $line->shopify_order_id, [['tiktok', 'customer']])) {
                $skipped++;
                usleep(200000);
                continue;
            }

            $result = $this->syncShippingAddressToShopify($line);
            if (! empty($result['success']) && empty($result['skipped'])) {
                $updated++;
            } elseif (! empty($result['skipped'])) {
                $skipped++;
                Log::warning('TikTokOrderPushService: address sync skipped', [
                    'order_id' => $line->order_id,
                    'shopify_order_id' => $line->shopify_order_id,
                    'message' => $result['message'] ?? null,
                ]);
            } else {
                $failed++;
                Log::warning('TikTokOrderPushService: address sync failed', [
                    'order_id' => $line->order_id,
                    'shopify_order_id' => $line->shopify_order_id,
                    'message' => $result['message'] ?? null,
                ]);
                $msg = (string) ($result['message'] ?? '');
                if (str_contains($msg, '429') || str_contains(strtolower($msg), 'exceeded 2 calls')) {
                    usleep(2000000);
                }
            }
            // Shopify REST: stay under 2 calls/sec (needsAddress + update).
            usleep(600000);
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
        $settings ??= MarketplaceSyncSettings::getFor('tiktok');

        return (bool) ($settings['order']['sync_address_to_shopify'] ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildImportPlan(TiktokOrder $order): array
    {
        if ($order->shopify_order_id) {
            return ['success' => false, 'message' => 'Already imported.', 'shopify_order_id' => (string) $order->shopify_order_id];
        }

        $orderId = (string) $order->order_id;
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Order ID is missing.'];
        }

        $lines = TiktokOrder::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $settings = MarketplaceSyncSettings::getFor('tiktok');
        $sourceName = (string) ($settings['order']['shopify_source_name'] ?? 'tiktok');
        $sourceDisplay = (string) ($settings['order']['shopify_source_display_name'] ?? 'TikTok Shop');
        $tags = array_values(array_unique(array_merge(
            ['tiktok', 'tiktok-'.$orderId],
            $settings['order']['shopify_order_tags'] ?? []
        )));

        $rawJson = $this->normalizeTikTokRawJson($order->raw_json);
        $shipping = $this->resolveShopifyShippingFromOrder($order, enrich: true);
        $order->refresh();
        $rawJson = $this->normalizeTikTokRawJson($order->raw_json) ?: $rawJson;

        $lineItems = [];
        foreach ($lines as $line) {
            $sku = $this->sellerSkuFromTikTokLine($line);
            if ($sku === '') {
                continue;
            }
            if (trim((string) ($line->seller_sku ?? '')) === '') {
                $line->seller_sku = $sku;
                try {
                    $line->save();
                } catch (\Throwable) {
                    // best-effort persist
                }
            }

            $qty = max(1, (int) ($line->quantity ?? 1));
            $price = number_format((float) ($line->sale_price ?? $line->original_price ?? 0), 2, '.', '');
            $title = mb_substr((string) ($line->product_name ?: $sku), 0, 255);
            $item = ['title' => $title, 'price' => $price, 'quantity' => $qty];
            if ($sku !== '') {
                $item['sku'] = $sku;
            }
            $lineItems[] = $item;
        }

        if ($lineItems === []) {
            return ['success' => false, 'message' => 'No resolvable line items for Shopify.'];
        }

        $payload = [
            'line_items' => $lineItems,
            'tags' => implode(', ', $tags),
            'source_name' => $sourceName,
            'source_identifier' => $orderId,
            'note' => "TikTok Shop order {$orderId}",
            'note_attributes' => [
                ['name' => 'tiktok_order_id', 'value' => $orderId],
                ['name' => 'marketplace', 'value' => 'tiktok'],
                ['name' => 'channel_display_name', 'value' => $sourceDisplay],
            ],
            'financial_status' => 'paid',
            'fulfillment_status' => null,
            'inventory_behaviour' => 'decrement_ignoring_policy',
            'send_receipt' => false,
            'send_fulfillment_receipt' => false,
        ];

        if (! empty($shipping['address1'])) {
            $shipping = $this->withShopifyCountryName($shipping);
            $payload['shipping_address'] = $shipping;
            $payload['billing_address'] = $shipping;
        }

        [$buyerEmail, $customer, $emailIsPlaceholder] = $this->tikTokShopifyCustomerAndEmail($orderId, $rawJson, $shipping, 'tiktok');
        $payload['email'] = $buyerEmail;
        $payload['customer'] = $customer;
        if ($emailIsPlaceholder) {
            $payload['note_attributes'][] = ['name' => 'tiktok_email_is_placeholder', 'value' => 'true'];
        }

        if (! empty($settings['order']['keep_order_number_from_channel'])) {
            $payload['name'] = '#TT-'.$orderId;
        }

        return [
            'success' => true,
            'tiktok_order_id' => $orderId,
            'payload' => $payload,
        ];
    }

    protected function syncInventoryAfterPush(TiktokOrder $order): void
    {
        $skus = TiktokOrder::query()
            ->where('order_id', (string) $order->order_id)
            ->pluck('seller_sku')
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
            $result = app(TikTokInventorySyncService::class)
                ->syncSkusFromShopify($skus, $this->shopifyConfig());

            Log::info('TikTokOrderPushService: post-push inventory sync', [
                'order_id' => $order->order_id,
                'skus' => $skus,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('TikTokOrderPushService: post-push inventory sync failed', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function shopifyFailureIsPhone(?string $reason): bool
    {
        $reason = strtolower((string) $reason);

        return str_contains($reason, 'phone');
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @return array<string, mixed>
     */
    protected function stripShopifyPhone(array $orderPayload): array
    {
        foreach (['shipping_address', 'billing_address', 'customer'] as $key) {
            if (isset($orderPayload[$key]) && is_array($orderPayload[$key])) {
                unset($orderPayload[$key]['phone']);
            }
        }

        return $orderPayload;
    }

    protected function postOrder(array $config, array $payload): ?string
    {
        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders.json';
        $maxAttempts = 5;
        $backoff = [2, 4, 8, 16, 30];

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = Http::withoutVerifying()->withHeaders([
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

                // 429 = not created, safe to resend. A 5xx may have created the order; the claim re-searches before any retry.
                $retryable = $response->status() === 429;
                if ($retryable && $attempt < $maxAttempts) {
                    sleep($backoff[$attempt - 1] ?? 30);

                    continue;
                }

                $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);

                return null;
            }

            return null;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();

            return null;
        }
    }

    protected function findShopifyVariantIdBySku(string $sku): ?int
    {
        $config = $this->shopifyConfig();

        return ShopifyVariantIdLookup::idForSku(
            (string) ($config['store_url'] ?? ''),
            (string) ($config['token'] ?? ''),
            $sku
        );
    }

    /**
     * @return array{store_url: string, token: string, store_key: string}
     */
    public function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');
        if ($storeKey === '' || $storeKey === 'business') {
            $storeKey = 'main';
        }

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     */
    protected function postTikTokOrder(array $config, array $orderPayload): ?string
    {
        $id = $this->postOrder($config, ['order' => $orderPayload]);
        if ($id) {
            return $id;
        }
        // The slim retry is only for a rejected payload; after a 5xx/timeout Shopify may already have the order.
        if (! ShopifyOrderCreateClaim::definitelyNotCreated($this->lastApiStatus)) {
            return null;
        }

        $reason = strtolower((string) $this->lastFailureReason);
        $slim = $this->stripShopifyPhone($orderPayload);
        unset($slim['source_name'], $slim['name'], $slim['source_identifier']);
        if (str_contains($reason, 'phone')
            || str_contains($reason, 'address')
            || str_contains($reason, 'province')
            || str_contains($reason, 'country')
            || str_contains($reason, 'zip')
            || str_contains($reason, 'postal')
        ) {
            unset($slim['shipping_address'], $slim['billing_address']);
        }

        return $this->postOrder($config, ['order' => $slim]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveShopifyShippingFromOrder(TiktokOrder $order, bool $enrich = false): array
    {
        $rawJson = $this->normalizeTikTokRawJson($order->raw_json);
        $address = $this->tikTokAddressFromRaw($rawJson);
        $shipping = $address !== [] ? $this->mapTikTokAddressToShopify($address) : [];

        if ($enrich && ! $this->tikTokShopifyAddressIsComplete($shipping)) {
            $this->enrichOrderRawJsonFromDetail($order);
            $order->refresh();
            $rawJson = $this->normalizeTikTokRawJson($order->raw_json);
            $address = $this->tikTokAddressFromRaw($rawJson);
            $shipping = $address !== [] ? $this->mapTikTokAddressToShopify($address) : [];
        }

        return $shipping;
    }

    protected function enrichOrderRawJsonFromDetail(TiktokOrder $order): void
    {
        $orderId = trim((string) $order->order_id);
        if ($orderId === '') {
            return;
        }

        try {
            $response = app(TikTokShopService::class)->getOrderDetails([$orderId]);
            $detail = $this->extractTikTokOrderFromDetailResponse($response, $orderId);
            if (! is_array($detail) || $detail === []) {
                return;
            }

            $existingRaw = $this->normalizeTikTokRawJson($order->raw_json);
            $merged = $this->mergePreservedTikTokRecipientAddress(
                array_replace_recursive($existingRaw !== [] ? $existingRaw : [], $detail),
                $existingRaw
            );
            if (is_array($detail['recipient_address'] ?? null) && $detail['recipient_address'] !== []) {
                $incomingMapped = $this->mapTikTokAddressToShopify($detail['recipient_address']);
                if ($this->tikTokShopifyAddressIsComplete($incomingMapped)) {
                    $merged['recipient_address'] = $detail['recipient_address'];
                }
            }

            TiktokOrder::query()
                ->where('order_id', $orderId)
                ->update([
                    'raw_json' => $merged,
                    'fetched_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('TikTokOrderPushService: order detail enrich failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
