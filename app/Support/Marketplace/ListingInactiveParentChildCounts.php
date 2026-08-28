<?php

namespace App\Support\Marketplace;

use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use App\Services\MarketplaceManager\MarketplacePortalInactiveCount;
use App\Services\MarketplaceManager\MarketplacePortalStatusTabs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Inactive parent vs child counts for /missing-listing.
 *
 * Source of truth is the same seller-platform inactive SKU list as /inactive-listings.
 * Zero-inventory SKUs (shopify_skus.inv null / empty / 0) are excluded from both columns.
 * If a channel has no parent / variation listings, the full inactive count goes in Child.
 */
class ListingInactiveParentChildCounts
{
    /** @var array<string, true>|null */
    private static ?array $positiveInvKeys = null;

    /** @var array<string, list<string>>|null uppercase parent sku => child sku keys */
    private static ?array $childrenByParent = null;

    /**
     * @return array{parent: int, child: int, url: ?string}
     */
    public static function forChannel(string $channel): array
    {
        $norm = ListingChannelCounts::normalize($channel);

        if (in_array($norm, ['shopify', 'shopifyb2c'], true)) {
            return self::shopifyCatalogInactive('main', $norm);
        }
        if ($norm === 'pls') {
            return self::shopifyCatalogInactive('pls', $norm);
        }

        $mm = MarketplaceListingQtyMatchService::fromMapIssuesSlug($norm);

        $skus = [];
        try {
            if ($mm !== null) {
                $skus = MarketplacePortalInactiveCount::skus($mm);
            }
            if ($skus === []) {
                $skus = self::fallbackInactiveSkus($norm);
            }
        } catch (\Throwable $e) {
            Log::warning('ListingInactiveParentChildCounts failed for '.$channel.': '.$e->getMessage());
            $skus = [];
        }

        $parent = [];
        $child = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $key = strtoupper($sku);
            if (MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
                $parent[$key] = true;

                continue;
            }
            if (! self::skuHasPositiveInv($sku)) {
                continue;
            }
            $child[$key] = true;
        }

        // No parent / variation listings on this seller → all inactive counts in Child.
        if ($parent === []) {
            $child = [];
            foreach ($skus as $sku) {
                $sku = trim((string) $sku);
                if ($sku === '' || ! self::skuHasPositiveInv($sku)) {
                    continue;
                }
                $child[strtoupper($sku)] = true;
            }
        } else {
            foreach (array_keys($parent) as $parentKey) {
                if (self::skuHasPositiveInv($parentKey)) {
                    continue;
                }
                $keep = false;
                foreach (self::childKeysForParent($parentKey) as $childKey) {
                    if (isset($child[$childKey])) {
                        $keep = true;
                        break;
                    }
                }
                if (! $keep) {
                    unset($parent[$parentKey]);
                }
            }
        }

        $url = null;
        try {
            $url = MappingChannelCounts::listingsInactiveUrlForSlug($norm !== '' ? $norm : $channel);
        } catch (\Throwable $e) {
            $url = null;
        }

        return [
            'parent' => count($parent),
            'child' => count($child),
            'url' => $url,
        ];
    }

    /**
     * Shopify Admin catalog (store=main is B2C / Shopify; store=pls is PLS).
     * Parent = inactive products (draft / archived / unlisted).
     * Child = variant SKUs on those products. If there are no variants, the
     * product count is shown in Child.
     *
     * @return array{parent: int, child: int, url: ?string}
     */
    protected static function shopifyCatalogInactive(string $store, string $norm): array
    {
        $parent = [];
        $child = [];
        if (
            Schema::hasTable('shopify_catalog_products')
            && Schema::hasColumn('shopify_catalog_products', 'status')
        ) {
            $hasVariants = Schema::hasTable('shopify_catalog_variants')
                && Schema::hasColumn('shopify_catalog_variants', 'sku');
            if ($hasVariants) {
                $rows = DB::table('shopify_catalog_variants as v')
                    ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
                    ->where('v.store', $store)
                    ->whereNotNull('v.sku')
                    ->where('v.sku', '!=', '')
                    ->select(['v.sku', 'p.id as product_pk', 'p.status']);
                foreach ($rows->cursor() as $row) {
                    if (MarketplacePortalStatusTabs::bucket((string) ($row->status ?? '')) !== 'inactive') {
                        continue;
                    }
                    $sku = trim((string) ($row->sku ?? ''));
                    if ($sku === '' || MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
                        continue;
                    }
                    if (! self::skuHasPositiveInv($sku)) {
                        continue;
                    }
                    $parent['p:'.(int) $row->product_pk] = true;
                    $child[strtoupper($sku)] = true;
                }
            } else {
                $q = DB::table('shopify_catalog_products')->where('status', '!=', '');
                if (Schema::hasColumn('shopify_catalog_products', 'store')) {
                    $q->where('store', $store);
                }
                foreach ($q->get(['id', 'status']) as $row) {
                    if (MarketplacePortalStatusTabs::bucket((string) ($row->status ?? '')) !== 'inactive') {
                        continue;
                    }
                    $parent['p:'.(int) $row->id] = true;
                }
            }
        }

        if ($parent !== [] && $child === []) {
            $child = $parent;
            $parent = [];
        }

        $url = null;
        try {
            $url = MappingChannelCounts::listingsInactiveUrlForSlug($norm);
        } catch (\Throwable $e) {
            $url = null;
        }

        return [
            'parent' => count($parent),
            'child' => count($child),
            'url' => $url,
        ];
    }

    /**
     * True when shopify_skus.inv is a positive number (same 0 Inv rule as CP Master).
     */
    protected static function skuHasPositiveInv(string $sku): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return false;
        }
        $keys = self::positiveInvKeys();
        $upper = strtoupper($sku);
        if (isset($keys[$upper])) {
            return true;
        }
        $norm = \App\Models\ShopifySku::normalizeSkuForShopifyLookup($sku);

        return $norm !== '' && isset($keys[$norm]);
    }

    /**
     * @return array<string, true>
     */
    protected static function positiveInvKeys(): array
    {
        if (self::$positiveInvKeys !== null) {
            return self::$positiveInvKeys;
        }

        self::$positiveInvKeys = [];
        try {
            if (! Schema::hasTable('shopify_skus') || ! Schema::hasColumn('shopify_skus', 'inv')) {
                return self::$positiveInvKeys;
            }
            foreach (DB::table('shopify_skus')->select(['sku', 'inv'])->whereNotNull('sku')->cursor() as $row) {
                $inv = $row->inv ?? null;
                if ($inv === null || $inv === '' || ! is_numeric($inv) || (float) $inv <= 0) {
                    continue;
                }
                $sku = trim((string) ($row->sku ?? ''));
                if ($sku === '') {
                    continue;
                }
                self::$positiveInvKeys[strtoupper($sku)] = true;
                $norm = \App\Models\ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm !== '') {
                    self::$positiveInvKeys[$norm] = true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ListingInactiveParentChildCounts: load inv failed: '.$e->getMessage());
        }

        return self::$positiveInvKeys;
    }

    /**
     * @return list<string>
     */
    protected static function childKeysForParent(string $parentKey): array
    {
        $map = self::childrenByParent();
        $parentKey = strtoupper(trim($parentKey));

        return $map[$parentKey] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    protected static function childrenByParent(): array
    {
        if (self::$childrenByParent !== null) {
            return self::$childrenByParent;
        }

        self::$childrenByParent = [];
        try {
            if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'sku')) {
                return self::$childrenByParent;
            }
            $q = DB::table('product_master')->whereNotNull('sku')->where('sku', '!=', '');
            if (Schema::hasColumn('product_master', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $select = ['sku'];
            if (Schema::hasColumn('product_master', 'parent')) {
                $select[] = 'parent';
            }
            foreach ($q->get($select) as $row) {
                $child = strtoupper(trim((string) ($row->sku ?? '')));
                $parent = strtoupper(trim((string) ($row->parent ?? '')));
                if ($child === '' || $parent === '' || MarketplaceLiveInventoryRules::isParentPlaceholderSku($child)) {
                    continue;
                }
                self::$childrenByParent[$parent][] = $child;
            }
        } catch (\Throwable $e) {
            Log::warning('ListingInactiveParentChildCounts: load parent map failed: '.$e->getMessage());
        }

        return self::$childrenByParent;
    }

    /**
     * Channels that are not in Marketplace Manager (Doba, Walmart, FB, …)
     * still use listing-status JSON / live_inactive when present.
     *
     * @return list<string>
     */
    protected static function fallbackInactiveSkus(string $norm): array
    {
        $tables = self::listingStatusTables($norm);
        $out = [];
        $seen = [];
        foreach ($tables as $table) {
            foreach (self::inactiveSkusFromJsonTable($table) as $sku) {
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

    /**
     * @return list<string>
     */
    protected static function listingStatusTables(string $norm): array
    {
        $regKey = match ($norm) {
            'ebay1', 'ebayone', 'ebay' => 'ebay',
            'ebay2' => 'ebaytwo',
            'ebay3' => 'ebaythree',
            'tiktok' => 'tiktokshop',
            'tiktok2' => 'tiktokshop2',
            'bestbuy' => 'bestbuyusa',
            'macy' => 'macys',
            'newegg' => 'neweggb2c',
            'shopify', 'shopifyb2c' => 'shopifyb2c',
            'facebookmarketplace' => 'fbmarketplace',
            default => $norm,
        };

        $cfg = ChannelListingRegistry::get($regKey);
        $statusClass = $cfg['status'] ?? null;
        if (! is_string($statusClass) || ! class_exists($statusClass)) {
            return [];
        }
        try {
            return [(new $statusClass)->getTable()];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    protected static function inactiveSkusFromJsonTable(string $table): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, 'value')) {
            return [];
        }

        $out = [];
        $seen = [];
        $query = DB::table($table)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->select(['sku', 'value'])
            ->orderBy('sku');

        $query->chunk(500, function ($rows) use (&$out, &$seen) {
            foreach ($rows as $row) {
                $sku = trim((string) ($row->sku ?? ''));
                if ($sku === '') {
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
}
