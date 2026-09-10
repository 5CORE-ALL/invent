<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\Tiktok2Order;
use App\Services\ShopifyStoreSelector;
use App\Services\TikTok2ShopService;

class TikTok2TrackingSyncService
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
        protected TikTok2ShopService $tiktokApi
    ) {}

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('tiktok2');

        return (bool) ($settings['order']['push_tracking_to_tiktok2'] ?? true);
    }

    protected function trackingApi(): TikTok2ShopService
    {
        return $this->tiktokApi;
    }

    protected function trackingOrderModel(): string
    {
        return Tiktok2Order::class;
    }

    protected function trackingLogContext(): string
    {
        return 'TikTok2TrackingSyncService';
    }

    protected function trackingShopifyNamePrefix(): string
    {
        return 'TT2';
    }

    protected function trackingAuthMessage(): string
    {
        return 'TikTok 2 API not authenticated.';
    }

    protected function trackingShopLabel(): string
    {
        return 'TikTok 2';
    }

    /**
     * @return array{store_url: string, token: string, store_key?: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok2');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
