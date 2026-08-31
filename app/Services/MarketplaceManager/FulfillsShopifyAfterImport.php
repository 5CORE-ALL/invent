<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Log;

/**
 * After a Marketplace Manager order is linked to Shopify, copy Veeqo/GOFO
 * tracking onto that Shopify copy (and the hub then pushes it back to the channel).
 */
trait FulfillsShopifyAfterImport
{
    protected function fulfillShopifyForImportedMarketplaceOrder(string $marketplace, int $localId, array $context = []): void
    {
        if ($localId < 1) {
            return;
        }

        try {
            $result = app(VeeqoShopifyFulfillmentService::class)->fulfillMarketplaceOrder($marketplace, $localId);
            if (empty($result['success']) && empty($result['skipped'])) {
                Log::warning(static::class.': Shopify fulfillment after import failed', array_merge($context, [
                    'marketplace' => $marketplace,
                    'local_id' => $localId,
                    'result' => $result,
                ]));
            }
        } catch (\Throwable $e) {
            Log::warning(static::class.': Shopify fulfillment after import exception', array_merge($context, [
                'marketplace' => $marketplace,
                'local_id' => $localId,
                'error' => $e->getMessage(),
            ]));
        }
    }
}
