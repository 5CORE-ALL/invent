<?php

namespace App\Services;

/**
 * TikTok Shop 2 — second Partner Center app / shop.
 * Uses TIKTOK2_* credentials and tiktok2_* cache keys; never TikTok 1 tokens.
 */
class TikTok2ShopService extends TikTokShopService
{
    protected string $configKey = 'tiktok2';

    protected string $cachePrefix = 'tiktok2';
}
