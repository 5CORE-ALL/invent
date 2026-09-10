<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Log;

/**
 * Shared Shopify → TikTok 1 / TikTok 2 tracking push.
 *
 * @method object trackingApi()
 * @method class-string trackingOrderModel()
 */
trait PushesTikTokShopifyTracking
{
    /**
     * @return array{success: bool, skipped?: bool, message: string, shopify_tracking?: string|null}
     */
    public function pushTrackingForOrder(object $line): array
    {
        $api = $this->trackingApi();
        if (! $api->isAuthenticated()) {
            return ['success' => false, 'message' => $this->trackingAuthMessage()];
        }

        $orderId = trim((string) ($line->order_id ?? ''));
        if ($orderId === '') {
            return ['success' => false, 'message' => 'TikTok order_id missing.'];
        }

        $status = $this->normalizeTrackingStatus((string) ($line->order_status ?? ''));
        if ($status !== '' && ! in_array($status, static::TRACKING_ELIGIBLE_STATUSES, true)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => "Skip tracking push for status {$status}.",
            ];
        }

        $model = $this->trackingOrderModel();
        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: $model::query()
                ->where('order_id', $orderId)
                ->whereNotNull('shopify_order_id')
                ->value('shopify_order_id')
        ));

        if ($shopifyOrderId === '') {
            return ['success' => false, 'skipped' => true, 'message' => 'Order not linked to Shopify yet.'];
        }

        $skus = $this->sellerSkusForOrder($model, $orderId, $line);
        $extraIds = $this->extraMarketplaceOrderIds($orderId);
        $shopifyFulfillment = [
            'tracking' => null,
            'carrier' => null,
            'error' => $skus === [] ? 'Marketplace SKU missing — tracking not attached.' : null,
        ];
        foreach ($skus as $sku) {
            $hit = $this->fetchShopifyTracking($shopifyOrderId, $orderId, $sku, $extraIds);
            if (! empty($hit['tracking'])) {
                $shopifyFulfillment = $hit;
                break;
            }
            $shopifyFulfillment = $hit;
        }

        if (empty($shopifyFulfillment['tracking'])) {
            $error = trim((string) ($shopifyFulfillment['error'] ?? ''));
            if ($error !== '' && $this->matcherErrorIsHardFail($error)) {
                return [
                    'success' => false,
                    'message' => 'Shopify tracking fetch failed: '.$error,
                    'shopify_tracking' => null,
                ];
            }

            return [
                'success' => false,
                'skipped' => true,
                'message' => $error !== ''
                    ? $error
                    : 'No tracking number on Shopify yet. Buy/download a shipping label in Shopify first.',
                'shopify_tracking' => null,
            ];
        }

        $shopifyTracking = (string) $shopifyFulfillment['tracking'];
        $shopifyCarrier = (string) ($shopifyFulfillment['carrier'] ?? '');
        $deliveryOptionId = $this->extractDeliveryOptionId($line);
        $shippingProviderId = $this->resolveShippingProviderId($orderId, $shopifyCarrier, $deliveryOptionId, $line);
        if ($shippingProviderId === '') {
            return [
                'success' => false,
                'message' => 'Could not resolve TikTok shipping_provider_id (need delivery_option_id + Logistics providers).',
                'shopify_tracking' => $shopifyTracking,
            ];
        }

        $result = $api->markOrderShipped(
            $orderId,
            $shopifyTracking,
            $shippingProviderId,
            $this->lineItemIdsForOrder($model, $orderId, $line)
        );

        if (! empty($result['success'])) {
            $model::query()
                ->where('order_id', $orderId)
                ->update(['tracking_pushed_at' => now()]);

            Log::info($this->trackingLogContext().': tracking pushed', [
                'order_id' => $orderId,
                'shopify_order_id' => $shopifyOrderId,
                'tracking' => $shopifyTracking,
            ]);

            return [
                'success' => true,
                'message' => $this->trackingShopLabel()." order {$orderId} marked shipped with {$shopifyTracking}.",
                'shopify_tracking' => $shopifyTracking,
            ];
        }

        $msg = (string) ($result['message'] ?? 'Failed to push tracking.');

        if ($this->looksLikeAlreadyShipped($msg)) {
            $model::query()
                ->where('order_id', $orderId)
                ->update(['tracking_pushed_at' => now()]);

            return [
                'success' => true,
                'skipped' => true,
                'message' => $this->trackingShopLabel().' order already shipped.',
                'shopify_tracking' => $shopifyTracking,
            ];
        }

        Log::warning($this->trackingLogContext().': push failed', [
            'order_id' => $orderId,
            'tracking' => $shopifyTracking,
            'message' => $msg,
        ]);

        return ['success' => false, 'message' => $msg, 'shopify_tracking' => $shopifyTracking];
    }

    /**
     * @return array{success: bool, checked: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingFromShopify(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));
        $model = $this->trackingOrderModel();

        $rows = $model::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->whereNull('tracking_pushed_at')
            ->orderByRaw('pushed_to_shopify_at IS NULL')
            ->orderBy('pushed_to_shopify_at')
            ->orderBy('id')
            ->limit($limit * 40)
            ->get();

        $unique = [];
        foreach ($rows as $row) {
            $ref = trim((string) $row->order_id);
            if ($ref === '' || isset($unique[$ref])) {
                continue;
            }
            $status = $this->normalizeTrackingStatus((string) ($row->order_status ?? ''));
            if ($status !== '' && ! in_array($status, static::TRACKING_ELIGIBLE_STATUSES, true)) {
                continue;
            }
            $unique[$ref] = $row;
            if (count($unique) >= $limit) {
                break;
            }
        }

        $checked = 0;
        $pushed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($unique as $line) {
            $checked++;
            $result = $this->pushTrackingForOrder($line);
            if (! empty($result['success']) && empty($result['skipped'])) {
                $pushed++;
            } elseif (! empty($result['skipped'])) {
                $skipped++;
            } else {
                $failed++;
            }
            usleep(250000);
        }

        return [
            'success' => $failed === 0,
            'checked' => $checked,
            'pushed' => $pushed,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "Tracking sync: checked {$checked}, pushed {$pushed}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    /**
     * @param  list<string>  $extraOrderIds
     * @return array{tracking: ?string, carrier: ?string, tracking_url: ?string, error?: ?string}
     */
    public function fetchShopifyTracking(
        string $shopifyOrderId,
        string $marketplaceOrderId = '',
        string $sku = '',
        array $extraOrderIds = []
    ): array {
        return app(ShopifyFulfillmentTrackingMatcher::class)->match(
            $this->shopifyConfig(),
            $shopifyOrderId,
            $marketplaceOrderId,
            $sku,
            $extraOrderIds,
            $this->trackingLogContext()
        );
    }

    /**
     * @param  class-string  $model
     * @return list<string>
     */
    protected function sellerSkusForOrder(string $model, string $orderId, object $line): array
    {
        $skus = $model::query()
            ->where('order_id', $orderId)
            ->pluck('seller_sku')
            ->map(static fn ($sku) => trim((string) $sku))
            ->filter(static fn ($sku) => $sku !== '' && ! in_array($sku, ['__order__', '__unknown__'], true))
            ->unique()
            ->values()
            ->all();

        $fromLine = trim((string) ($line->seller_sku ?? ''));
        if ($fromLine !== '' && ! in_array($fromLine, ['__order__', '__unknown__'], true) && ! in_array($fromLine, $skus, true)) {
            $skus[] = $fromLine;
        }

        if ($skus === []) {
            foreach ($this->skusFromRawJson($line) as $sku) {
                if (! in_array($sku, $skus, true)) {
                    $skus[] = $sku;
                }
            }
        }

        return array_values($skus);
    }

    /**
     * @return list<string>
     */
    protected function skusFromRawJson(object $line): array
    {
        $raw = $this->rawArray($line);
        $out = [];
        foreach (['line_items', 'order_line_list', 'items'] as $key) {
            $items = $raw[$key] ?? null;
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $sku = trim((string) ($item['seller_sku'] ?? $item['sku'] ?? ''));
                if ($sku !== '' && $sku !== '__order__' && ! in_array($sku, $out, true)) {
                    $out[] = $sku;
                }
            }
        }

        return $out;
    }

    /**
     * @param  class-string  $model
     * @return list<string>
     */
    protected function lineItemIdsForOrder(string $model, string $orderId, object $line): array
    {
        $ids = $model::query()
            ->where('order_id', $orderId)
            ->pluck('line_item_id')
            ->map(static fn ($id) => trim((string) $id))
            ->filter(static fn ($id) => $id !== '' && $id !== '__order__' && $id !== '__unknown__')
            ->unique()
            ->values()
            ->all();

        $raw = $this->rawArray($line);
        foreach (['line_items', 'order_line_list', 'items'] as $key) {
            $items = $raw[$key] ?? null;
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $id = trim((string) ($item['id'] ?? $item['order_line_id'] ?? ''));
                if ($id !== '' && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @return list<string>
     */
    protected function extraMarketplaceOrderIds(string $orderId): array
    {
        $prefix = trim($this->trackingShopifyNamePrefix(), '#-');
        $extra = [];
        if ($prefix !== '') {
            $extra[] = $prefix.'-'.$orderId;
            $extra[] = '#'.$prefix.'-'.$orderId;
        }

        return $extra;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rawArray(object $line): array
    {
        $raw = $line->raw_json ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            $raw = [];
        }
        if (is_array($raw['order'] ?? null) && ! isset($raw['delivery_option_id']) && ! isset($raw['line_items'])) {
            $raw = $raw['order'];
        }
        if (is_array($raw['data'] ?? null) && ! isset($raw['delivery_option_id']) && isset($raw['data']['id'])) {
            $raw = $raw['data'];
        }

        return $raw;
    }

    protected function extractDeliveryOptionId(object $line): string
    {
        $raw = $this->rawArray($line);
        $packages = $raw['packages'] ?? null;
        $candidates = [
            $raw['delivery_option_id'] ?? null,
            data_get($raw, 'packages.0.delivery_option_id'),
            is_array($packages) ? ($packages['delivery_option_id'] ?? null) : null,
            data_get($raw, 'fulfillment_type.delivery_option_id'),
            data_get($raw, 'delivery_option.id'),
            data_get($raw, 'shipping_info.delivery_option_id'),
            $line->delivery_option_id ?? null,
        ];
        foreach ($candidates as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }

    protected function extractShippingProviderIdFromRaw(object $line): string
    {
        $raw = $this->rawArray($line);
        $packages = $raw['packages'] ?? null;
        $candidates = [
            $raw['shipping_provider_id'] ?? null,
            data_get($raw, 'packages.0.shipping_provider_id'),
            is_array($packages) ? ($packages['shipping_provider_id'] ?? null) : null,
            data_get($raw, 'shipping_info.shipping_provider_id'),
            $line->shipping_provider ?? null,
        ];
        foreach ($candidates as $id) {
            $id = trim((string) $id);
            if ($id !== '' && preg_match('/^\d/', $id)) {
                return $id;
            }
        }

        return '';
    }

    protected function resolveShippingProviderId(
        string $orderId,
        string $shopifyCarrier,
        string $deliveryOptionId = '',
        ?object $line = null
    ): string {
        $fromRaw = $line ? $this->extractShippingProviderIdFromRaw($line) : '';
        $providers = $this->trackingApi()->getShippingProviders($orderId, $deliveryOptionId);
        $list = [];
        if (is_array($providers)) {
            if (array_is_list($providers)) {
                $list = $providers;
            } else {
                $list = $providers['shipping_providers']
                    ?? $providers['shipping_services']
                    ?? $providers['data']['shipping_providers']
                    ?? $providers['data']['shipping_services']
                    ?? [];
            }
        }

        $carrier = strtolower(trim($shopifyCarrier));
        $firstId = '';
        foreach ($list as $provider) {
            if (! is_array($provider)) {
                continue;
            }
            $id = trim((string) (
                $provider['id']
                ?? $provider['shipping_provider_id']
                ?? $provider['provider_id']
                ?? ''
            ));
            if ($id === '' || ! preg_match('/^\d/', $id)) {
                continue;
            }
            if ($firstId === '') {
                $firstId = $id;
            }
            $name = strtolower(trim((string) (
                $provider['name']
                ?? $provider['shipping_provider_name']
                ?? $provider['provider_name']
                ?? ''
            )));
            if ($carrier !== '' && $name !== '' && (str_contains($name, $carrier) || str_contains($carrier, $name))) {
                return $id;
            }
            foreach (['usps', 'ups', 'fedex', 'dhl', 'ontrac', 'uniuni'] as $needle) {
                if ($carrier !== '' && str_contains($carrier, $needle) && str_contains($name, $needle)) {
                    return $id;
                }
            }
        }

        if ($firstId !== '') {
            return $firstId;
        }

        return $fromRaw;
    }

    protected function looksLikeAlreadyShipped(string $message): bool
    {
        $m = strtolower($message);

        return (str_contains($m, 'already') && (str_contains($m, 'shipped') || str_contains($m, 'ship') || str_contains($m, 'fulfill')))
            || str_contains($m, 'has been shipped')
            || str_contains($m, 'package already');
    }

    protected function matcherErrorIsHardFail(string $error): bool
    {
        $error = strtolower($error);

        return str_contains($error, 'credentials')
            || str_contains($error, 'not authenticated')
            || (str_contains($error, 'fetch failed') && ! str_contains($error, 'fulfillment tracking'));
    }

    protected function normalizeTrackingStatus(string $status): string
    {
        return str_replace([' ', '-'], '_', strtoupper(trim($status)));
    }
}
