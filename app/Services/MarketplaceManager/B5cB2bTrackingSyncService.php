<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bOrder;
use App\Models\MarketplaceSyncSettings;
use App\Services\Business5CoreB2bApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class B5cB2bTrackingSyncService
{
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
    public function pushTrackingForOrder(object $line): array
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

        $tracking = $this->trackingFromShopify($shopifyOrderId);
        if ($tracking === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No tracking number on Shopify yet.',
            ];
        }

        try {
            $this->api->updateOrder($storeOrderId, [
                'status' => 'processing',
                'tracking_reference' => $tracking,
            ]);
            if ($line instanceof B5cB2bOrder) {
                $line->tracking_reference = $tracking;
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
                $q->whereNull('tracking_reference')->orWhere('tracking_reference', '');
            })
            ->orderByDesc('id')
            ->limit(max(1, (int) $limit))
            ->get();

        $updated = 0;
        foreach ($rows as $row) {
            $tracking = $this->trackingFromShopify((string) $row->shopify_order_id);
            if ($tracking === '') {
                continue;
            }
            try {
                $this->api->updateOrder((int) $row->store_order_id, [
                    'status' => 'processing',
                    'tracking_reference' => $tracking,
                ]);
                $row->tracking_reference = $tracking;
                $row->save();
                $updated++;
            } catch (\Throwable $e) {
                Log::warning('B5C B2B tracking push failed', [
                    'order' => $row->store_order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'message' => "Pushed tracking for {$updated} Business 5 Core B2B order(s).",
            'updated' => $updated,
        ];
    }

    private function trackingFromShopify(string $shopifyOrderId): string
    {
        $shopify = app(\App\Services\ShopifyApiService::class);
        if (! method_exists($shopify, 'getOrder') && ! method_exists($shopify, 'fetchOrder')) {
            return '';
        }
        try {
            $order = method_exists($shopify, 'getOrder')
                ? $shopify->getOrder($shopifyOrderId)
                : $shopify->fetchOrder($shopifyOrderId);
        } catch (\Throwable) {
            return '';
        }
        if (! is_array($order)) {
            return '';
        }
        foreach ($order['fulfillments'] ?? [] as $fulfillment) {
            $num = trim((string) ($fulfillment['tracking_number'] ?? ''));
            if ($num !== '') {
                return $num;
            }
        }

        return '';
    }
}
