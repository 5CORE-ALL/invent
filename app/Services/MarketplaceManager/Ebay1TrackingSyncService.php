<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay1OrderMetric;
use App\Models\MarketplaceSyncSettings;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify tracking to eBay 1 via Sell Fulfillment createShippingFulfillment.
 */
class Ebay1TrackingSyncService
{
    public function __construct(
        protected EbaySellFulfillmentTracking $fulfillment,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   shopify_tracking?: string|null,
     *   shopify_carrier?: string|null
     * }
     */
    public function pushTrackingForOrder(Ebay1OrderMetric $line): array
    {
        return $this->fulfillment->pushForChannel('ebay1', $line);
    }

    /**
     * @return array{success: bool, processed: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingFromShopify(int $limit = 40): array
    {
        if (! self::canAutoPush()) {
            return [
                'success' => true,
                'processed' => 0,
                'pushed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'message' => 'Push Shopify tracking to eBay 1 is Off in settings.',
            ];
        }

        $result = $this->fulfillment->syncPending('ebay1', Ebay1OrderMetric::class, $limit);
        Log::info('Ebay1TrackingSyncService: completed', $result);

        return $result;
    }

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('ebay1');

        return (bool) ($settings['order']['push_tracking_to_ebay1'] ?? true);
    }
}
