<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay3OrderMetric;
use App\Models\MarketplaceSyncSettings;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify tracking to eBay 3 via Sell Fulfillment createShippingFulfillment.
 */
class Ebay3TrackingSyncService
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
    public function pushTrackingForOrder(Ebay3OrderMetric $line): array
    {
        return $this->fulfillment->pushForChannel('ebay3', $line);
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
                'message' => 'Push Shopify tracking to eBay 3 is Off in settings.',
            ];
        }

        $result = $this->fulfillment->syncPending('ebay3', Ebay3OrderMetric::class, $limit);
        Log::info('Ebay3TrackingSyncService: completed', $result);

        return $result;
    }

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('ebay3');

        return (bool) ($settings['order']['push_tracking_to_ebay3'] ?? true);
    }
}
