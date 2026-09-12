<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\ShopifySku;
use App\Models\TopDawgProduct;
use App\Services\AmazonSpApiService;
use App\Services\TopDawgApiService;

/**
 * Inactive SKU counts from each marketplace's own status columns / listing JSON
 * (not MM live-cache heuristics). Used by /inactive-listings and MM Inactive tabs.
 */
final class MarketplacePortalInactiveCount
{
    /** @var array<string, list<string>> */
    private static array $skuMemo = [];

    /** @var array<string, array<string, true>> */
    private static array $activeMemo = [];

    /** @var array<string, list<string>> */
    private static array $sheetMemo = [];

    /** @var array{active: array<string, true>, inactive: list<string>}|null */
    private static ?array $amazonReportMemo = null;

    private static ?float $syncDeadline = null;

    public static bool $portalSyncIncomplete = false;

    /**
     * @return list<string>
     */
    public static function skus(string $mmChannel): array
    {
        $mmChannel = strtolower(trim($mmChannel));
        if (array_key_exists($mmChannel, self::$skuMemo)) {
            return self::$skuMemo[$mmChannel];
        }

        self::$skuMemo[$mmChannel] = match ($mmChannel) {
            'ebay1' => self::fromPortalAndJson(
                'ebay_metrics',
                'listing_status',
                ['ebay_listing_statuses', 'ebay_variation_listing_statuses'],
                'sku',
                ['missing', 'not_listed', 'sold']
            ),
            'ebay2' => self::ebaySkus(2),
            'ebay3' => self::ebaySkus(3),
            'temu' => self::fromPortalAndJson('temu_metrics', 'listing_status', []),
            'temu2' => self::fromPortalAndJson('temu2_metrics', 'listing_status', []),
            'tiktok' => self::mergeUnique(
                self::columnSkus('tiktok_products', 'listing_status', 'sku'),
                self::jsonLiveInactiveSkus('tiktok_shop_listing_statuses')
            ),
            'tiktok2' => self::mergeUnique(
                self::columnSkus('tiktok_products_two', 'listing_status', 'sku'),
                self::jsonLiveInactiveSkus('tiktok_two_shop_listing_statuses')
            ),
            'amazon' => self::amazonSkus(),
            'reverb' => self::mergeUnique(
                self::fromPortalAndJson('reverb_products', 'listing_state', ['reverb_listing_statuses']),
                self::liveCacheInactiveSkus(ReverbLiveListingsService::CACHE_KEY)
            ),
            'shein' => self::fromPortalAndJson('shein_metrics', 'status', ['shein_listing_statuses']),
            'topdawg' => self::topdawgSkus(),
            'newegg' => self::mergeUnique(
                self::neweggSkus(),
                self::jsonLiveInactiveSkus('newegg_b2c_listing_statuses')
            ),
            'pls' => self::mergeUnique(
                self::plsSkus(),
                self::jsonLiveInactiveSkus('pls_listing_statuses')
            ),
            'macy' => self::macySkus(),
            'wayfair' => self::jsonLiveInactiveSkus('wayfair_listing_statuses'),
            'bestbuy' => self::bestbuySkus(),
            'faire' => self::faireSkus(),
            'aliexpress' => self::aliexpressSkus(),
            default => [],
        };
        self::$skuMemo[$mmChannel] = self::withoutActiveSkus($mmChannel, self::$skuMemo[$mmChannel]);

        return self::$skuMemo[$mmChannel];
    }

    /**
     * SKUs that are currently Active / live on the seller portal.
     * These must never count as Inactive Listing.
     *
     * @return array<string, true>
     */
    public static function activeSkuKeys(string $mmChannel): array
    {
        $mmChannel = strtolower(trim($mmChannel));
        if (array_key_exists($mmChannel, self::$activeMemo)) {
            return self::$activeMemo[$mmChannel];
        }

        $keys = [];
        foreach (self::portalActiveKeys($mmChannel) as $key => $_) {
            $keys[$key] = true;
        }
        foreach (self::jsonActiveKeys($mmChannel) as $key => $_) {
            $keys[$key] = true;
        }
        foreach (self::liveCacheActiveKeys($mmChannel) as $key => $_) {
            $keys[$key] = true;
        }
        if ($mmChannel === 'amazon') {
            foreach (self::amazonActiveReportSkuKeys() as $key => $_) {
                $keys[$key] = true;
            }
        }

        self::$activeMemo[$mmChannel] = $keys;

        return $keys;
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    public static function withoutActiveSkus(string $mmChannel, array $skus): array
    {
        $active = self::activeSkuKeys($mmChannel);
        if ($active === []) {
            return array_values($skus);
        }

        $out = [];
        $seen = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $key = strtoupper($sku);
            if (isset($seen[$key]) || self::skuIsActive($sku, $active)) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $active
     */
    public static function skuIsActive(string $sku, array $active): bool
    {
        $sku = trim($sku);
        if ($sku === '' || $active === []) {
            return false;
        }
        if (isset($active[strtoupper($sku)])) {
            return true;
        }
        $norm = strtoupper(ShopifySku::normalizeSkuForShopifyLookup($sku) ?: '');
        if ($norm !== '' && isset($active[$norm])) {
            return true;
        }
        $compact = strtoupper(ShopifySku::compactSkuForLookup($sku) ?: '');

        return $compact !== '' && isset($active[$compact]);
    }

    /**
     * @return array<string, true>
     */
    protected static function portalActiveKeys(string $mmChannel): array
    {
        $skip = ['missing', 'not_listed'];
        $ebaySkip = ['missing', 'not_listed', 'sold'];

        $pairs = match ($mmChannel) {
            'ebay1' => [['ebay_metrics', 'listing_status', 'sku', $ebaySkip]],
            'ebay2' => [['ebay_2_metrics', 'listing_status', 'sku', $ebaySkip]],
            'ebay3' => [['ebay_3_metrics', 'listing_status', 'sku', $ebaySkip]],
            'temu' => [['temu_metrics', 'listing_status', 'sku', $skip]],
            'temu2' => [['temu2_metrics', 'listing_status', 'sku', $skip]],
            'tiktok' => [['tiktok_products', 'listing_status', 'sku', $skip]],
            'tiktok2' => [['tiktok_products_two', 'listing_status', 'sku', $skip]],
            'amazon' => [['amazon_datsheets', 'listing_status', 'sku', $skip]],
            'reverb' => [['reverb_products', 'listing_state', 'sku', $skip]],
            'shein' => [['shein_metrics', 'status', 'sku', $skip]],
            'topdawg' => [['topdawg_products', 'listing_state', 'sku', $skip]],
            'pls' => [],
            'macy' => [['macy_products', 'listing_status', 'sku', $skip]],
            'wayfair' => [],
            'bestbuy' => [['bestbuy_usa_products', 'listing_status', 'sku', $skip]],
            'faire' => [['faire_metric', 'listing_status', 'sku', $skip]],
            'aliexpress' => [
                ['aliexpress_metric', 'listing_status', 'sku', $skip],
                ['aliexpress_metric', 'status', 'sku', $skip],
            ],
            default => [],
        };

        $keys = [];
        foreach ($pairs as $pair) {
            [$table, $column, $skuCol, $skipStatuses] = $pair;
            foreach (self::columnKeySet($table, $column, $skuCol, 'active', $skipStatuses) as $key => $_) {
                $keys[$key] = true;
            }
        }
        if ($mmChannel === 'pls') {
            foreach (self::plsActiveSkus() as $sku) {
                $keys[strtoupper($sku)] = true;
            }
        }
        if ($mmChannel === 'newegg') {
            foreach (self::neweggActiveSkus() as $sku) {
                $keys[strtoupper($sku)] = true;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, true>
     */
    protected static function jsonActiveKeys(string $mmChannel): array
    {
        $tables = match ($mmChannel) {
            'ebay1' => ['ebay_listing_statuses', 'ebay_variation_listing_statuses'],
            'ebay2' => ['ebay_two_listing_statuses'],
            'ebay3' => ['ebay_three_listing_statuses'],
            'temu' => ['temu_listing_statuses'],
            'temu2' => ['temu2_listing_statuses'],
            'tiktok' => ['tiktok_shop_listing_statuses'],
            'tiktok2' => ['tiktok_two_shop_listing_statuses'],
            'amazon' => ['amazon_listing_statuses'],
            'reverb' => ['reverb_listing_statuses'],
            'shein' => ['shein_listing_statuses'],
            'newegg' => ['newegg_b2c_listing_statuses'],
            'pls' => ['pls_listing_statuses'],
            'macy' => ['macys_listing_statuses'],
            'wayfair' => ['wayfair_listing_statuses'],
            'bestbuy' => ['bestbuy_usa_listing_statuses'],
            'faire' => ['faire_listing_statuses'],
            'aliexpress' => ['aliexpress_listing_statuses'],
            default => [],
        };

        $keys = [];
        foreach ($tables as $table) {
            foreach (self::jsonLiveStatusSkus($table, 'active') as $sku) {
                $keys[strtoupper($sku)] = true;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, true>
     */
    protected static function liveCacheActiveKeys(string $mmChannel): array
    {
        $cacheKey = match ($mmChannel) {
            'ebay1' => 'mm.ebay1.live_listings.v3',
            'ebay2' => 'mm.ebay2.live_listings.v3',
            'ebay3' => 'mm.ebay3.live_listings.v3',
            'temu' => 'mm.temu.live_listings.v2',
            'temu2' => 'mm.temu2.live_listings.v4',
            'amazon' => 'mm.amazon.live_listings.v3',
            'reverb' => ReverbLiveListingsService::CACHE_KEY,
            'shein' => SheinLiveListingsService::CACHE_KEY,
            'topdawg' => 'mm.topdawg.live_listings.v1',
            'newegg' => NeweggLiveListingsService::CACHE_KEY,
            'pls' => 'mm.pls.live_listings.v1',
            'macy' => 'mm.macy.live_listings.v1',
            'wayfair' => 'mm.wayfair.live_listings.v1',
            'bestbuy' => 'mm.bestbuy.live_listings.v1',
            'faire' => 'mm.faire.live_listings.v2',
            'aliexpress' => AliexpressLiveListingsService::CACHE_KEY,
            default => null,
        };
        if ($cacheKey === null) {
            return [];
        }

        $keys = [];
        foreach (self::liveCacheStatusSkus($cacheKey, 'active') as $sku) {
            $keys[strtoupper($sku)] = true;
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    protected static function liveCacheStatusSkus(string $cacheKey, string $wantBucket): array
    {
        try {
            $cached = Cache::get($cacheKey);
        } catch (\Throwable $e) {
            return [];
        }
        if (! is_array($cached) || $cached === []) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($cached as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $key = strtoupper($sku);
            if (isset($seen[$key])) {
                continue;
            }
            if (MarketplacePortalStatusTabs::bucket((string) ($row['state'] ?? '')) !== $wantBucket) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    protected static function plsActiveSkus(): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }
        $out = [];
        $seen = [];
        $rows = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->where('v.store', 'pls')
            ->whereNotNull('v.sku')
            ->where('v.sku', '!=', '')
            ->select(['v.sku', 'p.status']);
        foreach ($rows->cursor() as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            $key = strtoupper($sku);
            if ($sku === '' || isset($seen[$key])) {
                continue;
            }
            if (MarketplacePortalStatusTabs::bucket((string) ($row->status ?? '')) !== 'active') {
                continue;
            }
            $seen[$key] = true;
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    protected static function neweggActiveSkus(): array
    {
        if (! Schema::hasTable('newegg_pricing') || ! Schema::hasColumn('newegg_pricing', 'seller_part_number')) {
            return [];
        }
        if (! Schema::hasColumn('newegg_pricing', 'active') && ! Schema::hasColumn('newegg_pricing', 'inventory_active')) {
            return [];
        }
        $q = DB::table('newegg_pricing')
            ->whereNotNull('seller_part_number')
            ->where('seller_part_number', '!=', '');
        $q->where(function ($inner) {
            if (Schema::hasColumn('newegg_pricing', 'active')) {
                $inner->where('active', 1)->orWhere('active', true)->orWhere('active', '1');
            }
            if (Schema::hasColumn('newegg_pricing', 'inventory_active')) {
                $inner->orWhere('inventory_active', 1)->orWhere('inventory_active', true);
            }
        });

        $out = [];
        $seen = [];
        foreach ($q->pluck('seller_part_number') as $sku) {
            $sku = trim((string) $sku);
            $key = strtoupper($sku);
            if ($sku === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $sku;
        }

        return $out;
    }

    public static function count(string $mmChannel): int
    {
        return count(self::skus($mmChannel));
    }

    /**
     * Amazon Inactive Listing starts from GET_MERCHANT_LISTINGS_ALL_DATA, then
     * drops any SKU Seller Central still shows as live. The listings report is
     * often stale (Closed FBA leftover / old Inactive) while FBM is Active.
     *
     * @return list<string>
     */
    protected static function amazonSkus(): array
    {
        $candidates = self::amazonReportSkuSets()['inactive'];
        if ($candidates === []) {
            return [];
        }

        try {
            $states = app(AmazonSpApiService::class)->sellerCentralListingStates($candidates);
        } catch (\Throwable $e) {
            Log::warning('MarketplacePortalInactiveCount: Amazon Seller Central check failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return AmazonListingStatusHelper::keepSellerCentralInactiveSkus($candidates, $states);
    }

    /**
     * @return array<string, true> UPPER/normalized sku => true
     */
    protected static function amazonActiveReportSkuKeys(): array
    {
        return self::amazonReportSkuSets()['active'];
    }

    /**
     * @return array{active: array<string, true>, inactive: list<string>}
     */
    protected static function amazonReportSkuSets(): array
    {
        if (self::$amazonReportMemo !== null) {
            return self::$amazonReportMemo;
        }

        $classified = [];
        if (! Schema::hasTable('amazon_listings_raw') || ! Schema::hasColumn('amazon_listings_raw', 'seller_sku')) {
            self::$amazonReportMemo = ['active' => [], 'inactive' => []];

            return self::$amazonReportMemo;
        }

        $cols = ['id', 'seller_sku'];
        foreach (['quantity', 'raw_data'] as $col) {
            if (Schema::hasColumn('amazon_listings_raw', $col)) {
                $cols[] = $col;
            }
        }

        DB::table('amazon_listings_raw')
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->select($cols)
            ->orderBy('id')
            ->chunkById(1000, function ($chunk) use (&$classified) {
                foreach ($chunk as $row) {
                    $sku = trim((string) ($row->seller_sku ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $isFba = AmazonListingStatusHelper::reportRowIsFba($row);
                    $classified[] = [
                        'sku' => $sku,
                        'live' => AmazonListingStatusHelper::reportRowIsLive($row),
                        'ignore' => $isFba || AmazonListingStatusHelper::reportRowIsClosedFba($row),
                        'fba' => $isFba,
                    ];
                }
            });

        self::$amazonReportMemo = AmazonListingStatusHelper::classifyReportSkus($classified);

        return self::$amazonReportMemo;
    }

    /**
     * @return list<string>
     */
    protected static function ebaySkus(int $store): array
    {
        self::ensureEbayPortalSynced($store);

        return match ($store) {
            2 => self::fromPortalAndJson(
                'ebay_2_metrics',
                'listing_status',
                ['ebay_two_listing_statuses'],
                'sku',
                ['missing', 'not_listed', 'sold']
            ),
            3 => self::fromPortalAndJson(
                'ebay_3_metrics',
                'listing_status',
                ['ebay_three_listing_statuses'],
                'sku',
                ['missing', 'not_listed', 'sold']
            ),
            default => self::fromPortalAndJson(
                'ebay_metrics',
                'listing_status',
                ['ebay_listing_statuses', 'ebay_variation_listing_statuses'],
                'sku',
                ['missing', 'not_listed', 'sold']
            ),
        };
    }

    /**
     * eBay 1 was filled by Refresh live. Pull Unsold for eBay 2/3 the same way
     * the first time Inactive Listings needs a count.
     */
    protected static function ensureEbayPortalSynced(int $store): void
    {
        if (! in_array($store, [2, 3], true)) {
            return;
        }
        $table = $store === 3 ? 'ebay_3_metrics' : 'ebay_2_metrics';
        $doneKey = 'mm.ebay'.$store.'.portal_inactive_synced_v2';
        try {
            if (self::columnHasAnyInactive($table, 'listing_status')) {
                Cache::put($doneKey, 1, now()->addHours(6));

                return;
            }
            if (Cache::get($doneKey)) {
                return;
            }
        } catch (\Throwable $e) {
            // continue
        }

        $budget = self::remainingSyncSeconds();
        if ($budget < 20) {
            self::$portalSyncIncomplete = true;

            return;
        }

        $lock = Cache::lock('mm.ebay'.$store.'.portal_inactive_sync', 400);
        if (! $lock->get()) {
            self::$portalSyncIncomplete = true;

            return;
        }
        try {
            @set_time_limit(min(180, $budget + 20));
            $result = app(EbayPortalListingStatusSync::class)->sync($store);
            if ($result['unsold_ok'] ?? false) {
                Cache::put($doneKey, 1, now()->addHours(6));
            } else {
                self::$portalSyncIncomplete = true;
            }
            Log::info('MarketplacePortalInactiveCount: eBay portal sync', [
                'store' => $store,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MarketplacePortalInactiveCount: eBay portal sync failed', [
                'store' => $store,
                'error' => $e->getMessage(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return list<string>
     */
    protected static function macySkus(): array
    {
        self::ensureMiraklPortalSynced('macy');

        return self::mergeUnique(
            self::fromPortalAndJson('macy_products', 'listing_status', ['macys_listing_statuses']),
            self::miraklSheetInactiveSkus('macys_price_data')
        );
    }

    /**
     * @return list<string>
     */
    protected static function bestbuySkus(): array
    {
        self::ensureMiraklPortalSynced('bestbuy');

        return self::mergeUnique(
            self::fromPortalAndJson('bestbuy_usa_products', 'listing_status', ['bestbuy_usa_listing_statuses']),
            self::miraklSheetInactiveSkus('bestbuy_price_data')
        );
    }

    /**
     * @return list<string>
     */
    protected static function faireSkus(): array
    {
        self::ensureFairePortalSynced();

        return self::fromPortalAndJson('faire_metric', 'listing_status', ['faire_listing_statuses']);
    }

    /**
     * @return list<string>
     */
    protected static function topdawgSkus(): array
    {
        self::ensureTopDawgPortalSynced();

        return self::mergeUnique(
            self::columnSkus('topdawg_products', 'listing_state', 'sku'),
            self::liveCacheInactiveSkus('mm.topdawg.live_listings.v1')
        );
    }

    protected static function ensureMiraklPortalSynced(string $channel): void
    {
        $table = $channel === 'bestbuy' ? 'bestbuy_usa_products' : 'macy_products';
        $doneKey = 'mm.'.$channel.'.portal_inactive_synced_v2';
        try {
            if (self::columnHasAnyInactive($table, 'listing_status')
                || self::miraklSheetInactiveSkus($channel === 'bestbuy' ? 'bestbuy_price_data' : 'macys_price_data') !== []) {
                Cache::put($doneKey, 1, now()->addHours(6));

                return;
            }
            if (Cache::get($doneKey)) {
                return;
            }
        } catch (\Throwable $e) {
            // continue
        }

        $budget = min(90, self::remainingSyncSeconds());
        if ($budget < 15) {
            self::$portalSyncIncomplete = true;

            return;
        }

        $lock = Cache::lock('mm.'.$channel.'.portal_inactive_sync', 400);
        if (! $lock->get()) {
            self::$portalSyncIncomplete = true;

            return;
        }
        try {
            @set_time_limit($budget + 20);
            $result = app(MiraklMcmOfferStatusSync::class)->sync($channel, $budget);
            if (($result['ok'] ?? false) && (int) ($result['inactive'] ?? 0) > 0) {
                Cache::put($doneKey, 1, now()->addHours(6));
            } else {
                self::$portalSyncIncomplete = true;
            }
            Log::info('MarketplacePortalInactiveCount: Mirakl portal sync', [
                'channel' => $channel,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MarketplacePortalInactiveCount: Mirakl portal sync failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    protected static function ensureFairePortalSynced(): void
    {
        $doneKey = 'mm.faire.portal_inactive_synced_v1';
        try {
            if (self::columnHasAnyInactive('faire_metric', 'listing_status')) {
                Cache::put($doneKey, 1, now()->addHours(6));

                return;
            }
            if (Cache::get($doneKey)) {
                return;
            }
        } catch (\Throwable $e) {
            // continue
        }

        $budget = min(90, self::remainingSyncSeconds());
        if ($budget < 15) {
            self::$portalSyncIncomplete = true;

            return;
        }

        $lock = Cache::lock('mm.faire.portal_inactive_sync', 400);
        if (! $lock->get()) {
            self::$portalSyncIncomplete = true;

            return;
        }
        try {
            @set_time_limit($budget + 20);
            $result = app(FaireLinkMapSyncService::class)->syncListingStatuses($budget);
            if (($result['done'] ?? false) && ($result['ok'] ?? false)) {
                Cache::put($doneKey, 1, now()->addHours(6));
            } else {
                self::$portalSyncIncomplete = true;
            }
            Log::info('MarketplacePortalInactiveCount: Faire portal sync', [
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MarketplacePortalInactiveCount: Faire portal sync failed', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    protected static function ensureTopDawgPortalSynced(): void
    {
        $doneKey = 'mm.topdawg.portal_inactive_synced_v2';
        try {
            if (self::columnHasAnyInactive('topdawg_products', 'listing_state')) {
                Cache::put($doneKey, 1, now()->addHours(6));

                return;
            }
            if (Cache::get($doneKey)) {
                return;
            }
        } catch (\Throwable $e) {
            // continue
        }

        $budget = min(90, self::remainingSyncSeconds());
        if ($budget < 15) {
            self::$portalSyncIncomplete = true;

            return;
        }

        $lock = Cache::lock('mm.topdawg.portal_inactive_sync', 400);
        if (! $lock->get()) {
            self::$portalSyncIncomplete = true;

            return;
        }
        try {
            @set_time_limit($budget + 20);
            $api = app(TopDawgApiService::class);
            if (! $api->isConfigured()) {
                self::$portalSyncIncomplete = true;

                return;
            }
            $result = $api->fetchProducts(null);
            $items = is_array($result['data'] ?? null) ? $result['data'] : [];
            $inactiveProbe = 0;
            foreach ($items as $probe) {
                if (is_array($probe) && MarketplacePortalStatusTabs::bucket((string) (TopDawgApiService::listingStateFromItem($probe) ?? '')) === 'inactive') {
                    $inactiveProbe++;
                    break;
                }
            }
            if ($inactiveProbe === 0) {
                foreach (['inactive', 'disabled', 'pending', 'rejected'] as $statusFilter) {
                    $more = $api->fetchProducts(null, null, ['status' => $statusFilter]);
                    $extra = is_array($more['data'] ?? null) ? $more['data'] : [];
                    if ($extra !== []) {
                        $items = array_merge($items, $extra);
                    }
                }
            }
            $loggedKeys = false;
            $inactive = 0;
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (! $loggedKeys) {
                    $loggedKeys = true;
                    Log::info('MarketplacePortalInactiveCount: TopDawg product keys', [
                        'keys' => array_keys($item),
                    ]);
                }
                $sku = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $state = TopDawgApiService::listingStateFromItem($item);
                if ($state === null) {
                    continue;
                }
                TopDawgProduct::query()->updateOrCreate(
                    ['sku' => $sku],
                    ['listing_state' => $state]
                );
                if (MarketplacePortalStatusTabs::bucket($state) === 'inactive') {
                    $inactive++;
                }
            }
            Cache::put($doneKey, 1, now()->addHours(6));
            Log::info('MarketplacePortalInactiveCount: TopDawg portal sync', [
                'products' => count($items),
                'inactive' => $inactive,
            ]);
            if ($inactive === 0) {
                self::$portalSyncIncomplete = true;
                Cache::forget($doneKey);
            }
        } catch (\Throwable $e) {
            self::$portalSyncIncomplete = true;
            Log::warning('MarketplacePortalInactiveCount: TopDawg portal sync failed', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    protected static function remainingSyncSeconds(): int
    {
        if (self::$syncDeadline === null) {
            self::$syncDeadline = microtime(true) + 280;
        }

        return max(0, (int) floor(self::$syncDeadline - microtime(true)));
    }

    protected static function columnHasAnyInactive(string $table, string $column, string $skuCol = 'sku'): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($table, $skuCol)) {
            return false;
        }
        $found = false;
        DB::table($table)
            ->whereNotNull($skuCol)
            ->where($skuCol, '!=', '')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select([$skuCol, $column])
            ->orderBy($skuCol)
            ->chunk(1000, function ($rows) use (&$found, $skuCol, $column) {
                foreach ($rows as $row) {
                    $raw = strtolower(trim((string) ($row->{$column} ?? '')));
                    $raw = str_replace([' ', '-'], '_', $raw);
                    if ($raw === '' || in_array($raw, ['missing', 'not_listed'], true)) {
                        continue;
                    }
                    if (MarketplacePortalStatusTabs::bucket($raw) === 'inactive') {
                        $found = true;

                        return false;
                    }
                }
            });

        return $found;
    }

    /**
     * MCM offers CSV uploaded on Macys/BestBuy pricing pages.
     * Activated=false is the seller-portal inactive flag (not qty 0 / unlisted).
     *
     * @return list<string>
     */
    protected static function miraklSheetInactiveSkus(string $table): array
    {
        if (array_key_exists($table, self::$sheetMemo)) {
            return self::$sheetMemo[$table];
        }
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
            self::$sheetMemo[$table] = [];

            return [];
        }
        $hasActivated = Schema::hasColumn($table, 'activated');
        $hasReason = Schema::hasColumn($table, 'inactivity_reason');
        if (! $hasActivated && ! $hasReason) {
            self::$sheetMemo[$table] = [];

            return [];
        }

        $out = [];
        $seen = [];
        DB::table($table)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->select(array_values(array_filter([
                'sku',
                $hasActivated ? 'activated' : null,
                $hasReason ? 'inactivity_reason' : null,
            ])))
            ->orderBy('sku')
            ->chunk(1000, function ($rows) use (&$out, &$seen, $hasActivated, $hasReason) {
                foreach ($rows as $row) {
                    $sku = trim((string) ($row->sku ?? ''));
                    $key = strtoupper($sku);
                    if ($sku === '' || isset($seen[$key])) {
                        continue;
                    }
                    $inactive = false;
                    if ($hasActivated) {
                        $raw = $row->activated;
                        if ($raw === false || $raw === 0 || $raw === '0' || strtolower(trim((string) $raw)) === 'false') {
                            $inactive = true;
                        }
                    } elseif ($hasReason) {
                        $reason = trim((string) ($row->inactivity_reason ?? ''));
                        if ($reason !== '') {
                            $inactive = true;
                        }
                    }
                    if (! $inactive) {
                        continue;
                    }
                    $seen[$key] = true;
                    $out[] = $sku;
                }
            });

        self::$sheetMemo[$table] = $out;

        return $out;
    }

    /**
     * @return list<string>
     */
    protected static function aliexpressSkus(): array
    {
        self::persistAliexpressFromLiveCache();

        return self::mergeUnique(
            self::fromPortalAndJson('aliexpress_metric', 'listing_status', ['aliexpress_listing_statuses']),
            self::fromPortalAndJson('aliexpress_metric', 'status', []),
            self::liveCacheInactiveSkus(AliexpressLiveListingsService::CACHE_KEY)
        );
    }

    protected static function persistAliexpressFromLiveCache(): void
    {
        try {
            $cached = Cache::get(AliexpressLiveListingsService::CACHE_KEY);
        } catch (\Throwable $e) {
            return;
        }
        if (! is_array($cached) || $cached === []) {
            return;
        }
        AliexpressLiveListingsService::persistListingStatuses($cached);
    }

    /**
     * @return list<string>
     */
    protected static function liveCacheInactiveSkus(string $cacheKey): array
    {
        try {
            $cached = Cache::get($cacheKey);
        } catch (\Throwable $e) {
            return [];
        }
        if (! is_array($cached) || $cached === []) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($cached as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $key = strtoupper($sku);
            if (isset($seen[$key])) {
                continue;
            }
            if (MarketplacePortalStatusTabs::bucket((string) ($row['state'] ?? '')) !== 'inactive') {
                continue;
            }
            $seen[$key] = true;
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * Mark / append portal-inactive SKUs on MM live rows (does not write cache).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function applyToLiveRows(string $mmChannel, array $rows): array
    {
        $inactive = [];
        foreach (self::skus($mmChannel) as $sku) {
            $inactive[strtoupper($sku)] = $sku;
        }
        if ($inactive === []) {
            return $rows;
        }

        $seen = [];
        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $key = strtoupper($sku);
            $seen[$key] = true;
            if (! isset($inactive[$key])) {
                continue;
            }
            $liveBucket = MarketplacePortalStatusTabs::bucket((string) ($row['state'] ?? ''));
            if ($liveBucket === 'active' || $liveBucket === 'inactive') {
                continue;
            }
            $rows[$i]['state'] = 'inactive';
            if (empty($rows[$i]['inactive_reason'])) {
                $rows[$i]['inactive_reason'] = 'Inactive listing';
            }
        }

        foreach ($inactive as $key => $sku) {
            if (isset($seen[$key])) {
                continue;
            }
            $rows[] = [
                'product_id' => $sku,
                'sku' => $sku,
                'state' => 'inactive',
                'inventory' => null,
                'title' => null,
                'price' => null,
                'inactive_reason' => 'Inactive listing',
            ];
        }

        return $rows;
    }

    /**
     * Portal listing_status wins when it is a real Active/Inactive value.
     * Empty portal status falls through to listing-manager live_inactive JSON.
     *
     * @param  list<string>  $jsonTables
     * @param  list<string>  $skipStatuses
     * @return list<string>
     */
    protected static function fromPortalAndJson(
        ?string $table,
        ?string $column,
        array $jsonTables,
        string $skuCol = 'sku',
        array $skipStatuses = ['missing', 'not_listed']
    ): array {
        $inactive = ($table !== null && $column !== null)
            ? self::columnSkus($table, $column, $skuCol, $skipStatuses)
            : [];
        $activeKeys = ($table !== null && $column !== null)
            ? self::columnKeySet($table, $column, $skuCol, 'active', $skipStatuses)
            : [];

        $json = [];
        foreach ($jsonTables as $jsonTable) {
            $json = self::mergeUnique($json, self::jsonLiveInactiveSkus($jsonTable));
        }

        foreach ($jsonTables as $jsonTable) {
            foreach (self::jsonLiveStatusSkus($jsonTable, 'active') as $sku) {
                $activeKeys[strtoupper($sku)] = $sku;
            }
        }

        $fromJson = [];
        foreach ($json as $sku) {
            if (isset($activeKeys[strtoupper($sku)])) {
                continue;
            }
            $fromJson[] = $sku;
        }

        $fromPortal = [];
        foreach ($inactive as $sku) {
            if (isset($activeKeys[strtoupper($sku)])) {
                continue;
            }
            $fromPortal[] = $sku;
        }

        return self::mergeUnique($fromPortal, $fromJson);
    }

    /**
     * @param  list<string>  $skipStatuses
     * @return list<string>
     */
    protected static function columnSkus(
        string $table,
        string $column,
        string $skuCol,
        array $skipStatuses = ['missing', 'not_listed']
    ): array {
        return array_values(self::columnKeySet($table, $column, $skuCol, 'inactive', $skipStatuses));
    }

    /**
     * @param  list<string>  $skipStatuses
     * @return array<string, string> UPPER(sku) => original sku
     */
    protected static function columnKeySet(
        string $table,
        string $column,
        string $skuCol,
        string $wantBucket,
        array $skipStatuses = ['missing', 'not_listed']
    ): array {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($table, $skuCol)) {
            return [];
        }

        $skip = [];
        foreach ($skipStatuses as $status) {
            $skip[strtolower(trim((string) $status))] = true;
        }

        $out = [];
        DB::table($table)
            ->whereNotNull($skuCol)
            ->where($skuCol, '!=', '')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select([$skuCol, $column])
            ->orderBy($skuCol)
            ->chunk(1000, function ($rows) use (&$out, $skuCol, $column, $wantBucket, $skip) {
                foreach ($rows as $row) {
                    $sku = trim((string) ($row->{$skuCol} ?? ''));
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }
                    $key = strtoupper($sku);
                    if (isset($out[$key])) {
                        continue;
                    }
                    $raw = strtolower(trim((string) ($row->{$column} ?? '')));
                    $raw = str_replace([' ', '-'], '_', $raw);
                    if ($raw === '' || isset($skip[$raw])) {
                        continue;
                    }
                    if (MarketplacePortalStatusTabs::bucket($raw) !== $wantBucket) {
                        continue;
                    }
                    $out[$key] = $sku;
                }
            });

        return $out;
    }

    /**
     * Listing-manager Live/Inactive flag stored on *_listing_statuses.value JSON.
     *
     * @return list<string>
     */
    protected static function jsonLiveInactiveSkus(string $table): array
    {
        return self::jsonLiveStatusSkus($table, 'inactive');
    }

    /**
     * @return list<string>
     */
    protected static function jsonLiveStatusSkus(string $table, string $wantBucket): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, 'value')) {
            return [];
        }

        $out = [];
        $seen = [];
        $query = DB::table($table)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->select(['id', 'sku', 'value'])
            ->orderBy('id');

        $walker = Schema::hasColumn($table, 'id')
            ? fn ($cb) => $query->chunkById(500, $cb)
            : fn ($cb) => $query->chunk(500, $cb);

        $walker(function ($rows) use (&$out, &$seen, $wantBucket) {
            foreach ($rows as $row) {
                $sku = trim((string) ($row->sku ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }
                $key = strtoupper($sku);
                if (isset($seen[$key])) {
                    continue;
                }
                $value = $row->value;
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $value = is_array($decoded) ? $decoded : [];
                }
                if (! is_array($value)) {
                    continue;
                }
                $raw = self::jsonStatusRaw($value);
                if (MarketplacePortalStatusTabs::bucket($raw) !== $wantBucket) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $sku;
            }
        });

        return $out;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected static function jsonStatusRaw(array $value): string
    {
        $map = [];
        foreach ($value as $key => $item) {
            if (is_array($item) || is_object($item)) {
                continue;
            }
            $norm = strtolower(str_replace([' ', '-', '/'], '_', (string) $key));
            $map[$norm] = $item;
        }
        foreach (['live_inactive', 'listing_status', 'status', 'state'] as $key) {
            if (! array_key_exists($key, $map)) {
                continue;
            }
            $raw = trim((string) $map[$key]);
            if ($raw !== '') {
                return $raw;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    protected static function neweggSkus(): array
    {
        if (! Schema::hasTable('newegg_pricing') || ! Schema::hasColumn('newegg_pricing', 'seller_part_number')) {
            return [];
        }
        if (! Schema::hasColumn('newegg_pricing', 'active') && ! Schema::hasColumn('newegg_pricing', 'inventory_active')) {
            return [];
        }
        $q = DB::table('newegg_pricing')
            ->whereNotNull('seller_part_number')
            ->where('seller_part_number', '!=', '');
        $q->where(function ($inner) {
            if (Schema::hasColumn('newegg_pricing', 'active')) {
                $inner->where('active', 0)->orWhere('active', false)->orWhere('active', '0');
            }
            if (Schema::hasColumn('newegg_pricing', 'inventory_active')) {
                $inner->orWhere('inventory_active', 0)->orWhere('inventory_active', false);
            }
        });

        $out = [];
        $seen = [];
        foreach ($q->pluck('seller_part_number') as $sku) {
            $sku = trim((string) $sku);
            $key = strtoupper($sku);
            if ($sku === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    protected static function plsSkus(): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }
        $out = [];
        $seen = [];
        $rows = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->where('v.store', 'pls')
            ->whereNotNull('v.sku')
            ->where('v.sku', '!=', '')
            ->select(['v.sku', 'p.status']);
        foreach ($rows->cursor() as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            $key = strtoupper($sku);
            if ($sku === '' || isset($seen[$key])) {
                continue;
            }
            if (MarketplacePortalStatusTabs::bucket((string) ($row->status ?? '')) !== 'inactive') {
                continue;
            }
            $seen[$key] = true;
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * @param  list<string>  ...$groups
     * @return list<string>
     */
    protected static function mergeUnique(array ...$groups): array
    {
        $seen = [];
        $out = [];
        foreach ($groups as $group) {
            foreach ($group as $sku) {
                $sku = trim((string) $sku);
                $key = strtoupper($sku);
                if ($sku === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $sku;
            }
        }

        return $out;
    }
}
