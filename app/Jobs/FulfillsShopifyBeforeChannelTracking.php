<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Support\Facades\Log;

/**
 * After a label is purchased (Veeqo / GOFO / Shopify): copy tracking onto the
 * Shopify copy first, then push that tracking to the marketplace and mark shipped.
 */
trait FulfillsShopifyBeforeChannelTracking
{
    protected ?string $pendingShopifyCopyMarketplace = null;

    protected int $pendingShopifyCopyLimit = 40;

    protected function fulfillShopifyCopiesFirst(string $marketplace, int $limit): void
    {
        $this->pendingShopifyCopyMarketplace = $marketplace;
        $this->pendingShopifyCopyLimit = max(1, min(120, $limit));
    }

    protected function runTrackingSafely(callable $callback): void
    {
        $marketplace = $this->pendingShopifyCopyMarketplace;
        $copyLimit = $this->pendingShopifyCopyLimit;
        $this->pendingShopifyCopyMarketplace = null;

        if ($marketplace !== null && $marketplace !== '') {
            try {
                app(VeeqoShopifyFulfillmentService::class)
                    ->syncPendingUnfulfilledForMarketplace($marketplace, $copyLimit);
            } catch (\Throwable $e) {
                Log::warning(static::class.': Shopify label copy before marketplace push failed', [
                    'marketplace' => $marketplace,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $result = $callback();
            Log::info(static::class.': completed', is_array($result) ? $result : []);
        } catch (\Throwable $e) {
            Log::error(static::class.': failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
