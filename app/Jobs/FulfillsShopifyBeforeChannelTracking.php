<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Support\Facades\Log;

/**
 * Channel tracking crons used to skip "no tracking on Shopify yet", then
 * burn their batch on the newest unlabeled rows. Always fulfill the Shopify
 * copy from Veeqo/GOFO first; do not rethrow so unique locks release.
 */
trait FulfillsShopifyBeforeChannelTracking
{
    protected function fulfillShopifyCopiesFirst(string $marketplace, int $limit): void
    {
        try {
            app(VeeqoShopifyFulfillmentService::class)
                ->syncPendingUnfulfilledForMarketplace($marketplace, $limit);
        } catch (\Throwable $e) {
            Log::warning(static::class.': Shopify fulfill-before-channel-push failed', [
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);
        }
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
    }
}
