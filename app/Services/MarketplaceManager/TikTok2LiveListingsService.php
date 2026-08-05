<?php

namespace App\Services\MarketplaceManager;

use App\Models\TikTokProductTwo;

/**
 * Live listing helpers for TikTok 2 listings UI.
 */
class TikTok2LiveListingsService extends TikTokLiveListingsService
{
    protected string $cacheKey = 'mm.tiktok2.live_listings.v1';

    protected string $productModel = TikTokProductTwo::class;

    protected string $table = 'tiktok_products_two';
}
