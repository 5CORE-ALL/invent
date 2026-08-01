<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonOrder;
use App\Models\MarketplaceSyncSettings;

class AmazonOrderPushService
{
    public function __construct(
        protected AmazonOrderDetailService $orderDetailService,
        protected AmazonDetailFormatter $formatter
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(AmazonOrder $order): array
    {
        return [
            'success' => false,
            'dry_run' => true,
            'message' => 'Amazon → Shopify import is not implemented yet. Orders stay in Seller Central / local DB.',
            'amazon_order_id' => $order->amazon_order_id,
        ];
    }

    public function importToShopify(AmazonOrder $order): ?string
    {
        return null;
    }

    public static function canAutoSyncAddress(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('amazon');

        return (bool) ($settings['order']['sync_address_to_shopify'] ?? false);
    }

    /**
     * @return array{success: bool, checked: int, updated: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingAddressesToShopify(int $limit = 40): array
    {
        return [
            'success' => true,
            'checked' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'message' => 'Amazon address sync to Shopify is not implemented yet.',
        ];
    }
}
