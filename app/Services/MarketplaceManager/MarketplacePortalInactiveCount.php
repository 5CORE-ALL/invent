<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Inactive SKU counts from each marketplace's own status columns / listing JSON
 * (not MM live-cache heuristics). Used by /inactive-listings and MM Inactive tabs.
 */
final class MarketplacePortalInactiveCount
{
    /** @var array<string, list<string>> */
    private static array $skuMemo = [];

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
            'temu' => self::fromPortalAndJson('temu_metrics', 'listing_status', ['temu_listing_statuses']),
            'temu2' => self::fromPortalAndJson('temu2_metrics', 'listing_status', ['temu2_listing_statuses']),
            'tiktok' => self::mergeUnique(
                self::columnSkus('tiktok_products', 'listing_status', 'sku'),
                self::jsonLiveInactiveSkus('tiktok_shop_listing_statuses')
            ),
            'tiktok2' => self::mergeUnique(
                self::columnSkus('tiktok_products_two', 'listing_status', 'sku'),
                self::jsonLiveInactiveSkus('tiktok_two_shop_listing_statuses')
            ),
            'amazon' => self::fromPortalAndJson('amazon_datsheets', 'listing_status', ['amazon_listing_statuses']),
            'reverb' => self::mergeUnique(
                self::fromPortalAndJson('reverb_products', 'listing_state', ['reverb_listing_statuses']),
                self::liveCacheInactiveSkus(ReverbLiveListingsService::CACHE_KEY)
            ),
            'shein' => self::fromPortalAndJson('shein_metrics', 'status', ['shein_listing_statuses']),
            'topdawg' => self::mergeUnique(
                self::columnSkus('topdawg_products', 'listing_state', 'sku'),
                self::liveCacheInactiveSkus('mm.topdawg.live_listings.v1')
            ),
            'newegg' => self::mergeUnique(
                self::neweggSkus(),
                self::jsonLiveInactiveSkus('newegg_b2c_listing_statuses')
            ),
            'pls' => self::mergeUnique(
                self::plsSkus(),
                self::jsonLiveInactiveSkus('pls_listing_statuses')
            ),
            'macy' => self::jsonLiveInactiveSkus('macys_listing_statuses'),
            'wayfair' => self::jsonLiveInactiveSkus('wayfair_listing_statuses'),
            'bestbuy' => self::jsonLiveInactiveSkus('bestbuy_usa_listing_statuses'),
            'faire' => self::jsonLiveInactiveSkus('faire_listing_statuses'),
            'aliexpress' => self::aliexpressSkus(),
            default => [],
        };

        return self::$skuMemo[$mmChannel];
    }

    public static function count(string $mmChannel): int
    {
        return count(self::skus($mmChannel));
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
        $doneKey = 'mm.ebay'.$store.'.portal_inactive_synced';
        try {
            if (Cache::get($doneKey)) {
                return;
            }
        } catch (\Throwable $e) {
            // continue
        }

        $lock = Cache::lock('mm.ebay'.$store.'.portal_inactive_sync', 400);
        if (! $lock->get()) {
            return;
        }
        try {
            @set_time_limit(180);
            $result = app(EbayPortalListingStatusSync::class)->sync($store);
            if ($result['ok'] ?? false) {
                Cache::put($doneKey, 1, now()->addHours(6));
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
            if (MarketplacePortalStatusTabs::bucket((string) ($row['state'] ?? '')) === 'inactive') {
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

        $fromJson = [];
        foreach ($json as $sku) {
            if (isset($activeKeys[strtoupper($sku)])) {
                continue;
            }
            $fromJson[] = $sku;
        }

        return self::mergeUnique($inactive, $fromJson);
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

        $walker(function ($rows) use (&$out, &$seen) {
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
                if (MarketplacePortalStatusTabs::bucket($raw) !== 'inactive') {
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
