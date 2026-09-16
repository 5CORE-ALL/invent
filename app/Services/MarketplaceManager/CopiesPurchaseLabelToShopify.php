<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Log;

/**
 * After a label is purchased, write Veeqo/GOFO tracking onto the Shopify copy.
 */
trait CopiesPurchaseLabelToShopify
{
    /**
     * @return array<string, mixed>
     */
    protected function copyPurchasedLabelToShopify(string $marketplace, int $orderId): array
    {
        if ($orderId < 1) {
            return ['success' => false];
        }

        try {
            return app(VeeqoShopifyFulfillmentService::class)->fulfillMarketplaceOrder($marketplace, $orderId);
        } catch (\Throwable $e) {
            Log::debug(static::class.': Shopify label copy skipped', [
                'marketplace' => $marketplace,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
