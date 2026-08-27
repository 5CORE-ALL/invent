<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2OrderMetric;
use App\Models\MarketplaceSyncSettings;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify tracking to eBay 2 via Sell Fulfillment createShippingFulfillment.
 */
class Ebay2TrackingSyncService
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
    public function pushTrackingForOrder(Ebay2OrderMetric $line): array
    {
        return $this->fulfillment->pushForChannel('ebay2', $line);
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
                'message' => 'Push Shopify tracking to eBay 2 is Off in settings.',
            ];
        }

        $result = $this->fulfillment->syncPending('ebay2', Ebay2OrderMetric::class, $limit);
        Log::info('Ebay2TrackingSyncService: completed', $result);

        return $result;
    }

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('ebay2');

        return (bool) ($settings['order']['push_tracking_to_ebay2'] ?? true);
    }
}
