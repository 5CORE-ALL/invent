<?php

namespace App\Services\MarketplaceManager;

use App\Models\DobaDailyData;
use App\Models\MarketplaceSyncSettings;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DobaOrderPushService
{
    use FindsExistingShopifyOrderByChannelRef;

    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    public ?string $lastDuplicateLinkMessage = null;

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(DobaDailyData $order): array
    {
        $plan = $this->buildImportPlan($order);
        $plan['dry_run'] = true;
        if (! empty($plan['success'])) {
            $plan['message'] = 'Dry run only — no Shopify order was created.';
        }

        return $plan;
    }

    public function importToShopify(DobaDailyData $order): ?string
    {
        $this->lastDuplicateLinkMessage = null;

        if ($order->shopify_order_id) {
            $this->ensureShopifyTypeTag($order, (string) $order->shopify_order_id);

            return (string) $order->shopify_order_id;
        }

        $orderId = trim((string) $order->order_no);
        if ($orderId === '') {
            $this->lastFailureReason = 'Doba order number is missing.';

            return null;
        }

        $status = strtoupper(trim((string) ($order->order_status ?? '')));
        if ($this->statusShouldNotImport($status)) {
            $this->lastFailureReason = 'Closed Doba orders are not imported to Shopify.';

            return null;
        }

        $lock = Cache::lock('doba-shopify-import:'.$orderId, 180);
        $gotLock = false;
        try {
            $gotLock = $lock->block(90);
        } catch (\Throwable $e) {
            Log::warning('DobaOrderPushService: import lock unavailable', [
                'order_no' => $orderId,
                'error' => $e->getMessage(),
            ]);
            $gotLock = true;
        }
        if (! $gotLock) {
            $this->lastFailureReason = 'Another Shopify import is already running for this Doba order.';

            return null;
        }

        try {
            return $this->importToShopifyLocked($order, $orderId);
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $e) {
                // Lock may have expired.
            }
        }
    }

    protected function importToShopifyLocked(DobaDailyData $order, string $orderId): ?string
    {
        $order->refresh();
        if ($order->shopify_order_id) {
            $this->ensureShopifyTypeTag($order, (string) $order->shopify_order_id);

            return (string) $order->shopify_order_id;
        }

        $localLinked = DobaDailyData::query()
            ->where('order_no', $orderId)
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->value('shopify_order_id');
        if ($localLinked) {
            $this->ensureShopifyTypeTag($order, (string) $localLinked);
            $this->linkDobaOrderToShopify($orderId, (string) $localLinked);
            $this->lastDuplicateLinkMessage = 'Linked to existing Shopify order '.$localLinked.' (local sibling).';

            return (string) $localLinked;
        }

        $refs = $this->shopifyRefsForOrder($order);
        $config = $this->shopifyConfig();
        $existing = $this->findExistingShopifyOrderByRefs(
            $config,
            $refs,
            ['doba-'],
            ['doba order no', 'doba order number', 'doba_order_no'],
            'DobaOrderPushService'
        );
        if (($existing['error'] ?? null) !== null) {
            $this->lastFailureReason = $existing['error'].' Push blocked to avoid duplicates.';

            return null;
        }
        if (! empty($existing['id'])) {
            $this->ensureShopifyTypeTag($order, (string) $existing['id']);
            $this->linkDobaOrderToShopify($orderId, (string) $existing['id']);
            $this->lastDuplicateLinkMessage = 'Linked to existing Shopify order '.$existing['id']
                .' (matched '.$existing['matched_by'].'). No new order created.';
            Log::info('DobaOrderPushService: linked existing Shopify order', [
                'order_no' => $orderId,
                'shopify_order_id' => $existing['id'],
                'matched_by' => $existing['matched_by'],
            ]);

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
            $refs,
            ['doba-'],
            ['doba order no', 'doba order number', 'doba_order_no'],
            'DobaOrderPushService',
            $order->fresh()?->shopify_order_id
        );
        if (! $shopifyOrderId) {
            return null;
        }

        $this->linkDobaOrderToShopify($orderId, $shopifyOrderId);

        return $shopifyOrderId;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildImportPlan(DobaDailyData $order): array
    {
        $orderId = trim((string) $order->order_no);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Doba order number is missing on this row.'];
        }

        $lines = DobaDailyData::query()->where('order_no', $orderId)->orderBy('id')->get();
        if ($lines->isEmpty()) {
            return ['success' => false, 'message' => 'No Doba line items found for this order.'];
        }

        $head = $lines->first();
        $payload = $this->shopifyPayload($head, $lines);
        if ($payload['line_items'] === []) {
            return ['success' => false, 'message' => 'Doba order has no line items to send to Shopify.'];
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return ['success' => false, 'message' => 'Shopify store credentials are not configured for the 5-core store.'];
        }

        return [
            'success' => true,
            'order_id' => $orderId,
            'order_number' => (string) ($head->platform_order_no ?: $orderId),
            'sku' => (string) ($head->sku ?? ''),
            'shopify_store' => $config['store_url'],
            'shopify_store_key' => $config['store_key'] ?? 'main',
            'payload' => $payload,
            'message' => 'Ready to create on '.$config['store_url'].'.',
        ];
    }

    /**
     * @param  Collection<int, DobaDailyData>  $lines
     * @return array<string, mixed>
     */
    protected function shopifyPayload(DobaDailyData $head, Collection $lines): array
    {
        $orderId = trim((string) $head->order_no);
        $config = $this->shopifyConfig();
        $lineItems = [];
        foreach ($lines as $line) {
            $sku = trim((string) ($line->sku ?? ''));
            $title = trim((string) ($line->product_name ?? ''));
            if ($sku === '' && $title === '') {
                continue;
            }
            $item = [
                'title' => $title !== '' ? $title : $sku,
                'quantity' => max(1, (int) ($line->quantity ?? 1)),
                'price' => number_format((float) ($line->item_price ?? 0), 2, '.', ''),
            ];
            if ($sku !== '') {
                $item['sku'] = $sku;
                $variantId = ShopifyVariantIdLookup::idForSku(
                    (string) ($config['store_url'] ?? ''),
                    (string) ($config['token'] ?? ''),
                    $sku
                );
                if ($variantId) {
                    $item['variant_id'] = $variantId;
                }
            }
            $lineItems[] = $item;
        }

        [$first, $last] = $this->splitName((string) ($head->receiver_name ?? ''));
        $email = strtolower(trim((string) ($head->receiver_email ?? '')));
        $emailIsPlaceholder = false;
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $slug = preg_replace('/[^a-zA-Z0-9]/', '', $orderId) ?: 'order';
            $email = 'doba-'.$slug.'@import.5coremanagement.com';
            $emailIsPlaceholder = true;
        }

        $country = $this->countryCode((string) ($head->shipping_country ?? ''));
        $address = array_filter([
            'first_name' => $first,
            'last_name' => $last,
            'address1' => trim((string) ($head->shipping_address1 ?? '')),
            'address2' => trim((string) ($head->shipping_address2 ?? '')),
            'city' => trim((string) ($head->shipping_city ?? '')),
            'province' => trim((string) ($head->shipping_state ?? '')),
            'zip' => trim((string) ($head->shipping_postal_code ?? '')),
            'country_code' => $country,
            'phone' => trim((string) ($head->receiver_phone ?? '')),
            'name' => trim((string) ($head->receiver_name ?? '')),
        ], static fn ($value) => $value !== '');

        $note = 'Doba order '.$orderId;
        $platform = trim((string) ($head->platform_order_no ?? ''));
        if ($platform !== '' && strcasecmp($platform, $orderId) !== 0) {
            $note .= ' / platform '.$platform;
        }

        $settings = MarketplaceSyncSettings::getFor('doba');
        $tags = array_values(array_unique(array_merge(
            ['Doba', 'doba-'.$orderId, $this->shopifyFulfillmentTag($head)],
            is_array($settings['order']['shopify_order_tags'] ?? null) ? $settings['order']['shopify_order_tags'] : []
        )));

        $noteAttributes = [
            ['name' => 'doba order no', 'value' => $orderId],
            ['name' => 'doba order number', 'value' => $orderId],
            ['name' => 'doba_order_no', 'value' => $orderId],
        ];
        if ($emailIsPlaceholder) {
            $noteAttributes[] = ['name' => 'doba_email_is_placeholder', 'value' => 'true'];
        }

        $payload = [
            'line_items' => $lineItems,
            'email' => $email,
            'customer' => [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
            ],
            'financial_status' => 'paid',
            'inventory_behaviour' => 'decrement_ignoring_policy',
            'send_receipt' => false,
            'send_fulfillment_receipt' => false,
            'currency' => strtoupper(trim((string) ($head->currency ?? 'USD'))) ?: 'USD',
            'tags' => implode(', ', $tags),
            'note' => $note,
            'note_attributes' => $noteAttributes,
            'source_name' => 'doba',
            'source_identifier' => $orderId,
        ];
        if (isset($address['address1']) && $country !== '') {
            $payload['shipping_address'] = $address;
            $payload['billing_address'] = $address;
        }
        $shippingFee = (float) ($head->shipping_fee ?? 0);
        if ($shippingFee > 0) {
            $payload['shipping_lines'] = [[
                'title' => trim((string) ($head->shipping_method ?? '')) ?: 'Shipping',
                'price' => number_format($shippingFee, 2, '.', ''),
                'code' => 'doba',
            ]];
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    protected function shopifyRefsForOrder(DobaDailyData $order): array
    {
        $refs = [];
        foreach ([$order->order_no, $order->platform_order_no] as $ref) {
            $ref = trim((string) $ref);
            if ($ref !== '' && ! in_array($ref, $refs, true)) {
                $refs[] = $ref;
            }
        }

        return $refs;
    }

    protected function linkDobaOrderToShopify(string $orderId, string $shopifyOrderId): void
    {
        DobaDailyData::query()
            ->where('order_no', $orderId)
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
                'import_status' => 'imported',
            ]);
    }

    protected function statusShouldNotImport(string $status): bool
    {
        if ($status === '') {
            return false;
        }
        foreach (['CANCEL', 'REFUND', 'VOID', 'DELIVERED', 'COMPLETED'] as $needle) {
            if (str_contains($status, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['Doba', 'Buyer'];
        }
        $parts = explode(' ', $name);
        if (count($parts) === 1) {
            return [$parts[0], '.'];
        }
        $last = (string) array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    protected function countryCode(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z]{2}$/', $raw) === 1) {
            return strtoupper($raw);
        }
        $map = [
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'USA' => 'US',
            'CANADA' => 'CA',
            'MEXICO' => 'MX',
        ];

        return $map[strtoupper($raw)] ?? '';
    }

    /**
     * Same labels the Doba Shopify app writes: "Prepaid label" or "Seller-Delivery".
     */
    public function shopifyFulfillmentTag(DobaDailyData $order): string
    {
        $type = strtolower(trim((string) ($order->order_type ?? '')));
        $json = $order->order_json;
        if (is_string($json)) {
            $decoded = json_decode($json, true);
            $json = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($json)) {
            $json = [];
        }
        if ($type === '' || $type === 'shopify') {
            $type = strtolower(trim((string) ($json['deliveryMethod'] ?? $json['orderType'] ?? '')));
        }
        if (str_contains($type, 'seller') && str_contains($type, 'deliver')) {
            return 'Seller-Delivery';
        }
        if (str_contains($type, 'prepaid')) {
            return 'Prepaid label';
        }
        $labels = $json['buyerPrepaidLabelList'] ?? $json['shippingLabels'] ?? null;
        if (is_array($labels) && $labels !== []) {
            return 'Prepaid label';
        }

        return 'Seller-Delivery';
    }

    /**
     * Add Prepaid label or Seller-Delivery when the order was created with only the Doba id tag.
     */
    public function ensureShopifyTypeTag(DobaDailyData $order, string $shopifyOrderId): bool
    {
        $shopifyOrderId = trim($shopifyOrderId);
        if ($shopifyOrderId === '' || Cache::has('doba.shopify.type-tag.'.$shopifyOrderId)) {
            return false;
        }
        $config = $this->shopifyConfig();
        $store = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($store === '' || $token === '') {
            return false;
        }

        $tag = $this->shopifyFulfillmentTag($order);
        $headers = [
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ];
        $base = 'https://'.$store.'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json';
        try {
            $response = Http::withoutVerifying()->withHeaders($headers)->timeout(15)->get($base, [
                'fields' => 'id,tags',
            ]);
        } catch (\Throwable $e) {
            Log::info('DobaOrderPushService: could not read Shopify tags', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
        if (! $response->successful()) {
            return false;
        }

        $existing = (string) ($response->json('order.tags') ?? '');
        $lower = strtolower($existing);
        if (str_contains($lower, 'prepaid label') || preg_match('/seller[\s\-]*delivery/', $lower) === 1) {
            Cache::put('doba.shopify.type-tag.'.$shopifyOrderId, 1, now()->addDays(7));

            return false;
        }

        $tags = trim($existing) === '' ? $tag : trim($existing).', '.$tag;
        try {
            $put = Http::withoutVerifying()->withHeaders($headers)->timeout(20)->put($base, [
                'order' => [
                    'id' => (int) $shopifyOrderId,
                    'tags' => $tags,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::info('DobaOrderPushService: could not write Shopify type tag', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
        if (! $put->successful()) {
            Log::info('DobaOrderPushService: Shopify type tag update failed', [
                'shopify_order_id' => $shopifyOrderId,
                'status' => $put->status(),
            ]);

            return false;
        }
        Cache::put('doba.shopify.type-tag.'.$shopifyOrderId, 1, now()->addDays(7));

        return true;
    }

    /**
     * Orders already on Shopify from the importer are missing the type tag.
     */
    public function backfillMissingShopifyTypeTags(int $limit = 20): int
    {
        $limit = max(1, min(40, $limit));
        $deadline = microtime(true) + 18.0;
        $rows = DobaDailyData::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->where('order_time', '>=', now()->subDays(14))
            ->orderByDesc('order_time')
            ->limit(250)
            ->get();

        $seen = [];
        $updated = 0;
        foreach ($rows as $row) {
            if (microtime(true) >= $deadline || count($seen) >= $limit) {
                break;
            }
            $shopifyId = trim((string) $row->shopify_order_id);
            if ($shopifyId === '' || isset($seen[$shopifyId])) {
                continue;
            }
            $seen[$shopifyId] = true;
            if ($this->ensureShopifyTypeTag($row, $shopifyId)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * 5-core (main). The Business 5 Core store is a different shop and must not receive these orders.
     *
     * @return array{store_url: string, token: string, store_key: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('doba');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');
        if ($storeKey === '' || $storeKey === 'business') {
            $storeKey = 'main';
        }

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
            $response = Http::withoutVerifying()->withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($url, $payload);

            $this->lastApiStatus = $response->status();
            if ($response->successful()) {
                $id = (string) ($response->json('order.id') ?? '');
                if ($id === '') {
                    $this->lastFailureReason = 'Shopify returned no order id.';

                    return null;
                }

                return $id;
            }

            $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 300);
            Log::error('DobaOrderPushService: Shopify order create failed', [
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();
            Log::error('DobaOrderPushService: exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public static function canAutoSyncAddress(): bool
    {
        $settings = MarketplaceSyncSettings::getFor('doba');

        return (bool) ($settings['order']['sync_address_to_shopify'] ?? false);
    }

    /**
     * @return array{success: bool, checked: int, updated: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingAddressesToShopify(int $limit = 40): array
    {
        unset($limit);

        return [
            'success' => true,
            'checked' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'message' => 'Doba address sync is not fully implemented yet.',
        ];
    }
}
