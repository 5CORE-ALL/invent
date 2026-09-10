<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\TiktokOrder;
use App\Services\ShopifyStoreSelector;
use App\Services\TikTokShopService;

class TikTokTrackingSyncService
{
    use PushesTikTokShopifyTracking;

    /**
     * Only push Shopify tracking for open TikTok orders still awaiting seller ship
     * (same intent as Reverb/AliExpress: skip delivered / completed history).
     *
     * @var list<string>
     */
    public const TRACKING_ELIGIBLE_STATUSES = [
        'AWAITING_SHIPMENT',
        'PARTIALLY_SHIPPING',
        'AWAITING_COLLECTION',
        'IN_TRANSIT',
        'SHIPPED',
    ];

    public function __construct(
        protected TikTokShopService $tiktokApi
    ) {}

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('tiktok');

        return (bool) ($settings['order']['push_tracking_to_tiktok'] ?? true);
    }

    protected function trackingApi(): TikTokShopService
    {
        return $this->tiktokApi;
    }

    protected function trackingOrderModel(): string
    {
        return TiktokOrder::class;
    }

    protected function trackingLogContext(): string
    {
        return 'TikTokTrackingSyncService';
    }

    protected function trackingShopifyNamePrefix(): string
    {
        return 'TT';
    }

    protected function trackingAuthMessage(): string
    {
        return 'TikTok Shop API not authenticated.';
    }

    protected function trackingShopLabel(): string
    {
        return 'TikTok Shop';
    }

    /**
     * @return array{store_url: string, token: string, store_key?: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
