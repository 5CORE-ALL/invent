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
 * If a channel has no parent / variation listings, the full inactive count goes in Child.
 */
class ListingInactiveParentChildCounts
{
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
            } else {
                $child[$key] = true;
            }
        }

        // No parent / variation listings on this seller → all inactive counts in Child.
        if ($parent === []) {
            $child = [];
            foreach ($skus as $sku) {
                $sku = trim((string) $sku);
                if ($sku === '') {
                    continue;
                }
                $child[strtoupper($sku)] = true;
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
                    $parent['p:'.(int) $row->product_pk] = true;
                    $sku = trim((string) ($row->sku ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    if (MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
                        continue;
                    }
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
