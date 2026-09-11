<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Support\Facades\Log;

/**
 * Channel tracking crons used to skip "no tracking on Shopify yet", then
 * burn their unique-lock window on Veeqo copies. Push marketplace tracking
 * first (Shopify already has the label); copy a small unlabeled batch after.
 */
trait FulfillsShopifyBeforeChannelTracking
{
    protected ?string $pendingShopifyCopyMarketplace = null;

    protected int $pendingShopifyCopyLimit = 40;

    protected function fulfillShopifyCopiesFirst(string $marketplace, int $limit): void
    {
        $this->pendingShopifyCopyMarketplace = $marketplace;
        $this->pendingShopifyCopyLimit = max(1, min(40, $limit));
    }

    protected function runTrackingSafely(callable $callback): void
    {
        try {
            $result = $callback();
            Log::info(static::class.': completed', is_array($result) ? $result : []);
        } catch (\Throwable $e) {
            Log::error(static::class.': failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $marketplace = $this->pendingShopifyCopyMarketplace;
        if ($marketplace === null || $marketplace === '') {
            return;
        }
        $this->pendingShopifyCopyMarketplace = null;

        try {
            app(VeeqoShopifyFulfillmentService::class)
                ->syncPendingUnfulfilledForMarketplace($marketplace, $this->pendingShopifyCopyLimit);
        } catch (\Throwable $e) {
            Log::warning(static::class.': Shopify fulfill-after-channel-push failed', [
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
