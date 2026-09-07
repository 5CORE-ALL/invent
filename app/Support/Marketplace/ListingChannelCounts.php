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
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTemu2Controller;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiktokShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiktokShopTwoController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWalmartController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWayfairController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingYamibuyController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingZendropController;
use App\Models\ApiVsSheetSetting;
use App\Models\ChannelMaster;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * REQ / NRL / Listed / Pending counts from each channel's listing page
 * (Listing*Controller::getNrReqCount / EbayTwoListingCounts).
 */
class ListingChannelCounts
{
    public const TOTAL_CACHE_KEY = 'listing_pages_missing_l_total_v1';

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
        'temu2' => ListingTemu2Controller::class,
        'temutwo' => ListingTemu2Controller::class,
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
        'newegg' => ListingNeweggB2CController::class,
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
        'temu2' => '/listing-temu2',
        'temutwo' => '/listing-temu2',
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
        'faire' => '/listing-faire',
        'mercariwoship' => '/listing-mercariwoship',
        'mercariwship' => null,
        'shopifywholesale' => null,
        'shopifywholesaleds' => null,
        'shopifyb2b' => null,
        'business5core' => null,
        'neweggb2c' => '/listing-neweggb2c',
        'neweggb2b' => '/listing-neweggb2b',
        'newegg' => '/listing-neweggb2c',
        'topdawg' => '/marketplace-manager/topdawg',
        'purchasingpower' => '/marketplace-manager/purchasingpower',
        'alibaba' => '/marketplace-manager/alibaba',
        'fbmarketplace' => '/listing-fbmarketplace',
        'facebookmarketplace' => '/listing-fbmarketplace',
        'fbshop' => '/listing-fbshop',
        'instagramshop' => '/listing-instagramshop',
        'syncee' => '/listing-syncee',
        'autods' => '/listing-autods',
        'zendrop' => '/listing-zendrop',
        'poshmark' => '/listing-poshmark',
        'appscenic' => '/listing-appscenic',
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
        if ($key === '') {
            return false;
        }

        return isset(self::$controllers[$key]) || ChannelListingRegistry::get($key) !== null;
    }

    /**
     * Active listing channels, plus inactive ones that have a marketplace API.
     */
    public static function shouldShowOnMissingListing(string $channel, mixed $status = 'active'): bool
    {
        if (! self::hasListingSource($channel)) {
            return false;
        }
        if (strtolower(trim((string) $status)) === 'active') {
            return true;
        }

        return ! self::isSheetSource($channel);
    }

    /**
     * Channels whose listing Listed/Missing L is sheet / manual-status based
     * (not marketplace API product IDs). Used when /api-vs-sheet has no override.
     *
     * @var list<string>
     */
    /**
     * Always treat as marketplace API for /missing-listing (overrides /api-vs-sheet).
     *
     * @var list<string>
     */
    private static array $forceApiListingSources = [
        'amazon',
        'amazonfba',
        'shopify',
        'shopifyb2c',
        'wayfair',
        'tiktok',
        'tiktokshop',
        'tiktok2',
        'tiktokshop2',
        'temu',
        'temu2',
        'temutwo',
        'ebay',
        'ebay1',
        'ebayone',
        'ebay2',
        'ebaytwo',
        'ebay3',
        'ebaythree',
        'doba',
        'walmart',
        'aliexpress',
        'shein',
        'faire',
        'reverb',
        'macys',
        'newegg',
        'neweggb2c',
        'neweggb2b',
        'bestbuyusa',
        'bestbuy',
        'pls',
        'topdawg',
        'purchasingpower',
        'alibaba',
    ];

    private static array $sheetListingSources = [
        'ebayvariation',
        'fbmarketplace',
        'facebookmarketplace',
        'fbshop',
        'instagramshop',
        'shopifywholesale',
        'shopifywholesaleds',
        'shopifyb2b',
        'mercariwship',
        'mercariwoship',
        'autods',
        'poshmark',
        'spocket',
        'zendrop',
        'syncee',
        'offerup',
        'appscenic',
        'yamibuy',
        'swgearexchange',
        'business5core',
    ];

    /**
     * Listing Missing L data source for /missing-listing: API, Sheet, or Offline.
     * Offline = marketplace credentials are not configured (do not invent Missing L).
     * Force-API channels win when connected; then /api-vs-sheet; else architecture default.
     */
    public static function dataSource(string $channel): string
    {
        $key = self::normalize($channel);
        if ($key === '') {
            return 'Sheet';
        }

        if (in_array($key, self::$forceApiListingSources, true)) {
            return self::marketplaceApiIsReady($key) ? 'API' : 'Offline';
        }

        $fromSettings = self::dataSourceFromApiVsSheet($key);
        if ($fromSettings !== null) {
            if ($fromSettings === 'API' && ! self::marketplaceApiIsReady($key)) {
                return 'Offline';
            }

            return $fromSettings;
        }

        if (in_array($key, self::$sheetListingSources, true)) {
            return 'Sheet';
        }

        return self::marketplaceApiIsReady($key) ? 'API' : 'Offline';
    }

    public static function isSheetSource(string $channel): bool
    {
        return self::dataSource($channel) !== 'API';
    }

    public static function isLiveApiSource(string $channel): bool
    {
        return self::dataSource($channel) === 'API';
    }

    /**
     * True when this channel has marketplace API credentials filled in.
     * Unknown / sheet-only slugs return false.
     */
    private static function marketplaceApiIsReady(string $normalizedKey): bool
    {
        try {
            return app(MarketplaceApiConfigService::class)->isConfigured($normalizedKey);
        } catch (\Throwable $e) {
            Log::warning('ListingChannelCounts marketplaceApiIsReady failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return 'API'|'Sheet'|null
     */
    private static function dataSourceFromApiVsSheet(string $normalizedKey): ?string
    {
        if (! Schema::hasTable('api_vs_sheet_settings') || ! Schema::hasTable('channel_master')) {
            return null;
        }

        static $map = null;
        if ($map === null) {
            $map = [];
            try {
                $channels = ChannelMaster::query()
                    ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                    ->whereNotNull('channel')
                    ->get(['id', 'channel']);
                $settings = ApiVsSheetSetting::query()
                    ->whereIn('channel_id', $channels->pluck('id'))
                    ->get(['channel_id', 'download_source'])
                    ->keyBy('channel_id');

                foreach ($channels as $c) {
                    $raw = trim((string) ($settings->get($c->id)?->download_source ?? ''));
                    if ($raw === '') {
                        continue;
                    }
                    $k = self::normalize((string) $c->channel);
                    if ($k === '') {
                        continue;
                    }
                    if (stripos($raw, 'API') !== false) {
                        $map[$k] = 'API';
                    } elseif (strcasecmp($raw, 'Sheet') === 0) {
                        $map[$k] = 'Sheet';
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('ListingChannelCounts dataSourceFromApiVsSheet failed: ' . $e->getMessage());
                $map = [];
            }
        }

        return $map[$normalizedKey] ?? null;
    }

    /**
     * Sum of Missing L (Pending) across active channel_master rows that have a listing page.
     * Same total as the Missing L badge on /missing-listing.
     */
    public static function totalMissingL(bool $useCache = true): int
    {
        $cacheKey = self::TOTAL_CACHE_KEY;

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
            Cache::put(self::TOTAL_CACHE_KEY, max(0, $total), now()->addMinutes(30));
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

        $channels = ChannelMaster::whereNotNull('channel')
            ->where('channel', '!=', '')
            ->get(['channel', 'status']);

        foreach ($channels as $row) {
            $name = (string) $row->channel;
            if (! self::shouldShowOnMissingListing($name, $row->status ?? '')) {
                continue;
            }
            $key = self::normalize((string) $name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            // Sheet / disconnected APIs are not counted
            if (! self::isLiveApiSource((string) $name)) {
                continue;
            }

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
    public static function forChannel(string $channel, bool $useCache = true, bool $requirePositiveInv = true): array
    {
        $empty = ['REQ' => 0, 'NRL' => 0, 'Listed' => 0, 'Pending' => 0];
        $key = self::normalize($channel);
        if ($key === '' || (! isset(self::$controllers[$key]) && ChannelListingRegistry::get($key) === null)) {
            return $empty;
        }

        if (! $useCache) {
            try {
                return self::loadCounts($key, $requirePositiveInv) ?: $empty;
            } catch (\Throwable $e) {
                Log::warning('ListingChannelCounts load failed for ' . $key . ': ' . $e->getMessage());

                return $empty;
            }
        }

        $cacheKey = 'listing_channel_counts_v1:'.($requirePositiveInv ? 'inv' : 'cp').':'.$key;

        try {
            return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($key, $empty, $requirePositiveInv) {
                return self::loadCounts($key, $requirePositiveInv) ?: $empty;
            });
        } catch (\Throwable $e) {
            Log::warning('ListingChannelCounts cache failed: ' . $e->getMessage());

            try {
                return self::loadCounts($key, $requirePositiveInv) ?: $empty;
            } catch (\Throwable $e2) {
                Log::warning('ListingChannelCounts load failed for ' . $key . ': ' . $e2->getMessage());

                return $empty;
            }
        }
    }

    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int}
     */
    private static function loadCounts(string $normalizedKey, bool $requirePositiveInv = true): array
    {
        // EbayTwo — shared helper (source of truth for /listing-ebaytwo)
        if (in_array($normalizedKey, ['ebay2', 'ebaytwo'], true)) {
            $c = EbayTwoListingCounts::counts($requirePositiveInv);

            return [
                'REQ' => (int) ($c['REQ'] ?? 0),
                'NRL' => (int) ($c['NRL'] ?? 0),
                'Listed' => (int) ($c['Listed'] ?? 0),
                'Pending' => (int) ($c['Pending'] ?? $c['MissingL'] ?? 0),
            ];
        }

        // Aliexpress — shared helper (source of truth for /listing-aliexpress)
        if ($normalizedKey === 'aliexpress') {
            $c = AliexpressListingCounts::counts($requirePositiveInv);

            return [
                'REQ' => (int) ($c['REQ'] ?? 0),
                'NRL' => (int) ($c['NRL'] ?? 0),
                'Listed' => (int) ($c['Listed'] ?? 0),
                'Pending' => (int) ($c['Pending'] ?? $c['MissingL'] ?? 0),
            ];
        }

        // Amazon — shared helper (source of truth for /listing-amazon)
        if ($normalizedKey === 'amazon') {
            $c = AmazonListingCounts::counts($requirePositiveInv);

            return [
                'REQ' => (int) ($c['REQ'] ?? 0),
                'NRL' => (int) ($c['NRL'] ?? 0),
                'Listed' => (int) ($c['Listed'] ?? 0),
                'Pending' => (int) ($c['Pending'] ?? $c['MissingL'] ?? 0),
            ];
        }

        // Registry-backed channels (EbayTwo pattern)
        if (ChannelListingRegistry::get($normalizedKey) !== null) {
            $c = ChannelListingRegistry::counts($normalizedKey, $requirePositiveInv);

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
        return strtolower(str_replace([' ', '-', '&', '/', '_', "'", '’'], '', trim($channel)));
    }
}
