<?php

namespace App\Support\Marketplace;

use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAliexpressController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAmazonController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAppscenicController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAutoDSController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingBestbuyUSAController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingBusiness5CoreController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingDobaController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayThreeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayTwoController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayVariationController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFaireController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFBMarketplaceController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFBShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingInstagramShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMacysController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMercariWoShipController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMercariWShipController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingNeweggB2BController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingNeweggB2CController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingOfferupController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingPlsController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingPoshmarkController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingReverbController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSheinController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingShopifyB2CController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingShopifyWholesaleController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSpocketController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSWGearExchangeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSynceeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTemuController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiendamiaController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiktokShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiktokShopTwoController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWalmartController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWayfairController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingYamibuyController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingZendropController;
use App\Models\ChannelMaster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * REQ / NRL / Listed / Pending counts from each channel's listing page
 * (Listing*Controller::getNrReqCount / EbayTwoListingCounts).
 */
class ListingChannelCounts
{
    /**
     * Normalized channel key → listing controller class.
     *
     * @var array<string, class-string>
     */
    private static array $controllers = [
        'amazon' => ListingAmazonController::class,
        'ebay' => ListingEbayController::class,
        'ebay1' => ListingEbayController::class,
        'ebayone' => ListingEbayController::class,
        'ebay2' => ListingEbayTwoController::class,
        'ebaytwo' => ListingEbayTwoController::class,
        'ebay3' => ListingEbayThreeController::class,
        'ebaythree' => ListingEbayThreeController::class,
        'ebayvariation' => ListingEbayVariationController::class,
        'temu' => ListingTemuController::class,
        'doba' => ListingDobaController::class,
        'macys' => ListingMacysController::class,
        'walmart' => ListingWalmartController::class,
        'wayfair' => ListingWayfairController::class,
        'shopifyb2c' => ListingShopifyB2CController::class,
        'shopify' => ListingShopifyB2CController::class,
        'shopifywholesaleds' => ListingShopifyWholesaleController::class,
        'shopifywholesale' => ListingShopifyWholesaleController::class,
        'shopifyb2b' => ListingShopifyWholesaleController::class,
        'reverb' => ListingReverbController::class,
        'aliexpress' => ListingAliexpressController::class,
        'shein' => ListingSheinController::class,
        'tiktokshop' => ListingTiktokShopController::class,
        'tiktok' => ListingTiktokShopController::class,
        'tiktokshop2' => ListingTiktokShopTwoController::class,
        'tiktok2' => ListingTiktokShopTwoController::class,
        'faire' => ListingFaireController::class, // counts via ChannelListingRegistry
        'mercariwship' => ListingMercariWShipController::class,
        'mercariwoship' => ListingMercariWoShipController::class,
        'neweggb2c' => ListingNeweggB2CController::class,
        'neweggb2b' => ListingNeweggB2BController::class,
        'fbmarketplace' => ListingFBMarketplaceController::class,
        'facebookmarketplace' => ListingFBMarketplaceController::class,
        'fbshop' => ListingFBShopController::class,
        'instagramshop' => ListingInstagramShopController::class,
        'syncee' => ListingSynceeController::class,
        'autods' => ListingAutoDSController::class,
        'business5core' => ListingBusiness5CoreController::class,
        'zendrop' => ListingZendropController::class,
        'poshmark' => ListingPoshmarkController::class,
        'appscenic' => ListingAppscenicController::class,
        'tiendamia' => ListingTiendamiaController::class,
        'spocket' => ListingSpocketController::class,
        'offerup' => ListingOfferupController::class,
        'yamibuy' => ListingYamibuyController::class,
        'bestbuyusa' => ListingBestbuyUSAController::class,
        'bestbuy' => ListingBestbuyUSAController::class,
        'swgearexchange' => ListingSWGearExchangeController::class,
        'pls' => ListingPlsController::class,
    ];

    /**
     * Normalized channel key → listing page path (same /listing-* routes).
     *
     * @var array<string, string>
     */
    private static array $listingPaths = [
        'amazon' => '/listing-amazon',
        'ebay' => '/listing-ebay',
        'ebay1' => '/listing-ebay',
        'ebayone' => '/listing-ebay',
        'ebay2' => '/listing-ebaytwo',
        'ebaytwo' => '/listing-ebaytwo',
        'ebay3' => '/listing-ebaythree',
        'ebaythree' => '/listing-ebaythree',
        'ebayvariation' => '/listing-ebayvariation',
        'temu' => '/listing-temu',
        'doba' => '/listing-doba',
        'macys' => '/listing-macys',
        'walmart' => '/listing-walmart',
        'wayfair' => '/listing-wayfair',
        'shopifyb2c' => '/listing-shopifyb2c',
        'shopify' => '/listing-shopifyb2c',
        'reverb' => '/listing-reverb',
        'aliexpress' => '/listing-aliexpress',
        'shein' => '/listing-shein',
        'tiktokshop' => '/listing-tiktokshop',
        'tiktok' => '/listing-tiktokshop',
        'tiktokshop2' => '/listing-tiktokshop2',
        'tiktok2' => '/listing-tiktokshop2',
        'faire' => null, // counts-only (no listing page route)
        'mercariwoship' => '/listing-mercariwoship',
        'mercariwship' => null,
        'shopifywholesale' => null,
        'shopifywholesaleds' => null,
        'shopifyb2b' => null,
        'business5core' => null,
        'neweggb2c' => '/listing-neweggb2c',
        'neweggb2b' => '/listing-neweggb2b',
        'fbmarketplace' => '/listing-fbmarketplace',
        'facebookmarketplace' => '/listing-fbmarketplace',
        'fbshop' => '/listing-fbshop',
        'instagramshop' => '/listing-instagramshop',
        'syncee' => '/listing-syncee',
        'autods' => '/listing-autods',
        'zendrop' => '/listing-zendrop',
        'poshmark' => '/listing-poshmark',
        'appscenic' => '/listing-appscenic',
        'tiendamia' => '/listing-tiendamia',
        'spocket' => '/listing-spocket',
        'offerup' => '/listing-offerup',
        'yamibuy' => '/listing-yamibuy',
        'bestbuyusa' => '/listing-bestbuyusa',
        'bestbuy' => '/listing-bestbuyusa',
        'swgearexchange' => '/listing-swgearexchange',
        'pls' => '/listing-pls',
    ];

    /**
     * Absolute URL to the channel's listing page, or null when none is registered.
     */
    public static function listingUrl(string $channel): ?string
    {
        $key = self::normalize($channel);
        $path = self::$listingPaths[$key] ?? null;
        if ($path === null || $path === '') {
            return null;
        }

        return url($path);
    }

    /**
     * Whether this channel has a listing-page count source (controller / helper).
     */
    public static function hasListingSource(string $channel): bool
    {
        $key = self::normalize($channel);

        return $key !== '' && isset(self::$controllers[$key]);
    }

    /**
     * Sum of Missing L (Pending) across active channel_master rows that have a listing page.
     * Same total as the Missing L badge on /missing-listing.
     */
    public static function totalMissingL(bool $useCache = true): int
    {
        $cacheKey = 'listing_pages_missing_l_total_v1';

        if (! $useCache) {
            $total = self::computeTotalMissingL();
            try {
                Cache::put($cacheKey, $total, now()->addMinutes(30));
            } catch (\Throwable $e) {
                // ignore cache write failures
            }

            return $total;
        }

        try {
            return (int) Cache::remember($cacheKey, now()->addMinutes(10), function () {
                return self::computeTotalMissingL();
            });
        } catch (\Throwable $e) {
            Log::warning('ListingChannelCounts totalMissingL cache failed: ' . $e->getMessage());

            return self::computeTotalMissingL();
        }
    }

    /**
     * Persist a precomputed total (e.g. after /missing-listing/data loads).
     */
    public static function storeTotalMissingL(int $total): void
    {
        try {
            Cache::put('listing_pages_missing_l_total_v1', max(0, $total), now()->addMinutes(30));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private static function computeTotalMissingL(): int
    {
        if (! Schema::hasTable('channel_master')) {
            return 0;
        }

        $seen = [];
        $total = 0;

        $channels = ChannelMaster::whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->pluck('channel');

        foreach ($channels as $name) {
            if (! self::hasListingSource((string) $name)) {
                continue;
            }
            $key = self::normalize((string) $name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            try {
                $c = self::forChannel((string) $name, false);
                $total += (int) ($c['Pending'] ?? 0);
            } catch (\Throwable $e) {
                Log::warning('ListingChannelCounts totalMissingL channel failed (' . $key . '): ' . $e->getMessage());
            }
        }

        return $total;
    }

    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int}
     */
    public static function forChannel(string $channel, bool $useCache = true): array
    {
        $empty = ['REQ' => 0, 'NRL' => 0, 'Listed' => 0, 'Pending' => 0];
        $key = self::normalize($channel);
        if ($key === '' || ! isset(self::$controllers[$key])) {
            return $empty;
        }

        if (! $useCache) {
            try {
                return self::loadCounts($key) ?: $empty;
            } catch (\Throwable $e) {
                Log::warning('ListingChannelCounts load failed for ' . $key . ': ' . $e->getMessage());

                return $empty;
            }
        }

        $cacheKey = 'listing_channel_counts_v1:' . $key;

        try {
            return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($key, $empty) {
                return self::loadCounts($key) ?: $empty;
            });
        } catch (\Throwable $e) {
            Log::warning('ListingChannelCounts cache failed: ' . $e->getMessage());

            try {
                return self::loadCounts($key) ?: $empty;
            } catch (\Throwable $e2) {
                Log::warning('ListingChannelCounts load failed for ' . $key . ': ' . $e2->getMessage());

                return $empty;
            }
        }
    }

    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int}
     */
    private static function loadCounts(string $normalizedKey): array
    {
        // EbayTwo — shared helper (source of truth for /listing-ebaytwo)
        if (in_array($normalizedKey, ['ebay2', 'ebaytwo'], true)) {
            $c = EbayTwoListingCounts::counts();

            return [
                'REQ' => (int) ($c['REQ'] ?? 0),
                'NRL' => (int) ($c['NRL'] ?? 0),
                'Listed' => (int) ($c['Listed'] ?? 0),
                'Pending' => (int) ($c['Pending'] ?? $c['MissingL'] ?? 0),
            ];
        }

        // Aliexpress — shared helper (source of truth for /listing-aliexpress)
        if ($normalizedKey === 'aliexpress') {
            $c = AliexpressListingCounts::counts();

            return [
                'REQ' => (int) ($c['REQ'] ?? 0),
                'NRL' => (int) ($c['NRL'] ?? 0),
                'Listed' => (int) ($c['Listed'] ?? 0),
                'Pending' => (int) ($c['Pending'] ?? $c['MissingL'] ?? 0),
            ];
        }

        // Amazon — shared helper (source of truth for /listing-amazon)
        if ($normalizedKey === 'amazon') {
            $c = AmazonListingCounts::counts();

            return [
                'REQ' => (int) ($c['REQ'] ?? 0),
                'NRL' => (int) ($c['NRL'] ?? 0),
                'Listed' => (int) ($c['Listed'] ?? 0),
                'Pending' => (int) ($c['Pending'] ?? $c['MissingL'] ?? 0),
            ];
        }

        // Registry-backed channels (EbayTwo pattern)
        if (ChannelListingRegistry::get($normalizedKey) !== null) {
            $c = ChannelListingRegistry::counts($normalizedKey);

            return [
                'REQ' => (int) ($c['REQ'] ?? 0),
                'NRL' => (int) ($c['NRL'] ?? 0),
                'Listed' => (int) ($c['Listed'] ?? 0),
                'Pending' => (int) ($c['Pending'] ?? $c['MissingL'] ?? 0),
            ];
        }

        $class = self::$controllers[$normalizedKey];
        $raw = app($class)->getNrReqCount();
        if (! is_array($raw)) {
            return ['REQ' => 0, 'NRL' => 0, 'Listed' => 0, 'Pending' => 0];
        }

        return [
            'REQ' => (int) ($raw['REQ'] ?? 0),
            'NRL' => (int) ($raw['NRL'] ?? $raw['NR'] ?? 0),
            'Listed' => (int) ($raw['Listed'] ?? 0),
            'Pending' => (int) ($raw['Pending'] ?? 0),
        ];
    }

    public static function normalize(string $channel): string
    {
        return strtolower(str_replace([' ', '-', '&', '/', '_'], '', trim($channel)));
    }
}
