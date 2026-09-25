<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bOrder;
use App\Models\MarketplaceSyncSettings;
use App\Services\Business5CoreB2bApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class B5cB2bTrackingSyncService
{
    use CopiesPurchaseLabelToShopify;

    public function __construct(protected Business5CoreB2bApiService $api)
    {
    }

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('b5cb2b');

        return (bool) ($settings['order']['push_tracking_to_b5cb2b'] ?? true);
    }

    /**
     * @return array{success: bool, skipped?: bool, message: string, updated?: int}
     */
    /**
     * @param  array<string, mixed>  $known  Tracking already found on the Veeqo / 4Seller label.
     */
    public function pushTrackingForOrder(object $line, array $known = []): array
    {
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Business 5 Core B2B API is not configured.'];
        }

        $storeOrderId = (int) ($line->store_order_id ?? 0);
        if ($storeOrderId <= 0) {
            return ['success' => false, 'message' => 'B2B store order id missing.'];
        }

        $shopifyOrderId = trim((string) ($line->shopify_order_id ?? ''));
        if ($shopifyOrderId === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to a Shopify order yet.',
            ];
        }

        $sku = $this->skuFromLine($line);
        $tracking = trim((string) ($known['tracking'] ?? ''));
        if ($tracking === '') {
            $tracking = $this->trackingFromShopify($shopifyOrderId, (string) $storeOrderId, $sku);
        }
        if ($tracking === '') {
            $copied = $this->copyPurchasedLabelToShopify('b5cb2b', (int) ($line->id ?? 0));
            $tracking = trim((string) ($copied['tracking'] ?? ''));
            if ($tracking === '') {
                $tracking = $this->trackingFromShopify($shopifyOrderId, (string) $storeOrderId, $sku);
            }
        }
        if ($tracking === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No Veeqo or 4Seller GOFO tracking for this order yet.',
            ];
        }

        $already = trim((string) ($line->tracking_reference ?? ''));
        $status = strtolower(trim((string) ($line->status ?? '')));
        if ($already !== '' && strcasecmp($already, $tracking) === 0 && $status === 'shipped') {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Business 5 Core already has tracking '.$tracking.'.',
                'updated' => 0,
            ];
        }

        try {
            $this->api->updateOrder($storeOrderId, [
                'status' => 'shipped',
                'tracking_reference' => $tracking,
            ]);
            if ($line instanceof B5cB2bOrder) {
                $line->tracking_reference = $tracking;
                $line->status = 'shipped';
                $line->save();
            }
        } catch (\Throwable $e) {
            Log::warning('B5C B2B tracking push failed', [
                'order' => $storeOrderId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Pushed tracking '.$tracking.' to Business 5 Core B2B order #'.$storeOrderId.'.',
            'updated' => 1,
        ];
    }

    /**
     * @return array{success: bool, message: string, updated: int}
     */
    public function syncFromShopify(?int $limit = 40): array
    {
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Business 5 Core B2B API is not configured.', 'updated' => 0];
        }
        if (! Schema::hasTable('b5c_b2b_orders')) {
            return ['success' => false, 'message' => 'b5c_b2b_orders table missing.', 'updated' => 0];
        }

        if (! self::canAutoPush()) {
            return ['success' => true, 'message' => 'Tracking push disabled.', 'updated' => 0];
        }

        $rows = B5cB2bOrder::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->where(function ($q) {
                $q->whereNull('tracking_reference')
                    ->orWhere('tracking_reference', '')
                    ->orWhereNotIn('status', ['shipped', 'completed', 'canceled', 'cancelled']);
            })
            ->orderByDesc('id')
            ->limit(max(1, (int) $limit))
            ->get();

        $updated = 0;
        foreach ($rows as $row) {
            $result = $this->pushTrackingForOrder($row);
            if (! empty($result['success']) && empty($result['skipped'])) {
                $updated++;
            }
        }

        return [
            'success' => true,
            'message' => "Pushed tracking for {$updated} Business 5 Core B2B order(s).",
            'updated' => $updated,
        ];
    }

    private function trackingFromShopify(string $shopifyOrderId, string $storeOrderId = '', string $sku = ''): string
    {
        $hit = app(ShopifyFulfillmentTrackingMatcher::class)->match(
            $this->shopifyConfig(),
            $shopifyOrderId,
            $storeOrderId,
            $sku,
            [],
            'B5cB2bTrackingSyncService'
        );

        return trim((string) ($hit['tracking'] ?? ''));
    }

    protected function skuFromLine(object $line): string
    {
        if ($line instanceof B5cB2bOrder) {
            foreach ($line->displayLines() as $item) {
                $sku = trim((string) ($item['sku'] ?? ''));
                if ($sku !== '') {
                    return $sku;
                }
            }
        }

        $payload = is_array($line->payload ?? null) ? $line->payload : [];
        foreach (['sku', 'seller_sku', 'variant_sku'] as $key) {
            $sku = trim((string) ($payload[$key] ?? ''));
            if ($sku !== '') {
                return $sku;
            }
        }
        foreach (is_array($payload['items'] ?? null) ? $payload['items'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? $item['seller_sku'] ?? ''));
            if ($sku !== '') {
                return $sku;
            }
        }

        return '';
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
}
