<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live-verified Shopify SKUs stored locally in shopify_catalog_* (Admin API sync).
 * Listings "All" should use this — not the full shopify_skus dump.
 */
final class ShopifyLiveVerifiedCatalogService
{
    public const STORE_MAIN = 'main';

    public function tablesReady(): bool
    {
        return Schema::hasTable('shopify_catalog_products')
            && Schema::hasTable('shopify_catalog_variants');
    }

    /**
     * Active main-store variants with a non-empty SKU (joined to product for title/status).
     */
    public function activeVariantQuery(string $store = self::STORE_MAIN): Builder
    {
        return DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->where('p.store', $store)
            ->where('v.store', $store)
            ->whereRaw("LOWER(TRIM(COALESCE(p.status, ''))) = ?", ['active'])
            ->whereNotNull('v.sku')
            ->where('v.sku', '!=', '');
    }

    public function countDistinctActiveSkus(string $store = self::STORE_MAIN): int
    {
        if (! $this->tablesReady()) {
            return 0;
        }

        return (int) $this->activeVariantQuery($store)
            ->distinct()
            ->count('v.sku');
    }

    /**
     * @return list<string>
     */
    public function activeSkuList(string $store = self::STORE_MAIN): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        return $this->activeVariantQuery($store)
            ->orderBy('v.sku')
            ->distinct()
            ->pluck('v.sku')
            ->map(static fn ($s) => trim((string) $s))
            ->filter(static fn ($s) => $s !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, true> normalized SKU => true
     */
    public function normalizedKeys(string $store = self::STORE_MAIN): array
    {
        $keys = [];
        foreach ($this->activeSkuList($store) as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $keys[$norm] = true;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, string> normalized SKU => catalog SKU string
     */
    public function normalizedToSkuMap(string $store = self::STORE_MAIN): array
    {
        $map = [];
        foreach ($this->activeSkuList($store) as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && ! isset($map[$norm])) {
                $map[$norm] = $sku;
            }
        }

        return $map;
    }

    public function hasAnyActive(string $store = self::STORE_MAIN): bool
    {
        if (! $this->tablesReady()) {
            return false;
        }

        return $this->activeVariantQuery($store)->exists();
    }

    public function latestSyncedAt(string $store = self::STORE_MAIN): ?string
    {
        if (! $this->tablesReady()) {
            return null;
        }

        $at = DB::table('shopify_catalog_products')
            ->where('store', $store)
            ->max('synced_at');

        return $at ? (string) $at : null;
    }

    /**
     * Listing tab counts from live-verified active catalog (shared across marketplaces).
     *
     * @param  array<int, string>  $linkedSkus
     * @param  callable(array<string, true>): int  $countNotInShopify  receives normalized active keys
     * @return array{all: int, linked: int, unlinked: int, not_in_shopify: int}|null  null = catalog not ready
     */
    public function listingCounts(array $linkedSkus, callable $countNotInShopify, string $store = self::STORE_MAIN): ?array
    {
        if (! $this->tablesReady() || ! $this->hasAnyActive($store)) {
            return null;
        }

        $verifiedNorm = $this->normalizedKeys($store);
        $all = $this->countDistinctActiveSkus($store);
        $linked = 0;
        $seen = [];
        foreach ($linkedSkus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n === '' || isset($seen[$n]) || ! isset($verifiedNorm[$n])) {
                continue;
            }
            $seen[$n] = true;
            $linked++;
        }

        return [
            'all' => $all,
            'linked' => $linked,
            'unlinked' => max(0, $all - $linked),
            'not_in_shopify' => (int) $countNotInShopify($verifiedNorm),
        ];
    }

    /**
     * Restrict a ShopifySku eloquent query to live-verified active catalog SKUs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\ShopifySku>  $query
     * @param  list<string>|null  $extraSkuAllowList  when set, intersect with verified (e.g. linked/state)
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\ShopifySku>
     */
    public function restrictShopifySkuQuery($query, ?array $extraSkuAllowList = null, string $store = self::STORE_MAIN)
    {
        if (! $this->tablesReady() || ! $this->hasAnyActive($store)) {
            return $query->whereRaw('1 = 0');
        }

        if ($extraSkuAllowList !== null) {
            $allow = $this->filterLinkedToVerified($extraSkuAllowList, $store);
            if ($allow === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn('sku', $allow);
        }

        $verified = $this->activeSkuList($store);
        if ($verified === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('sku', $verified);
    }

    /**
     * Linked SKUs that exist in the live-verified active catalog (canonical catalog SKU strings).
     *
     * @param  array<int, string>  $linkedSkus
     * @return list<string>
     */
    public function filterLinkedToVerified(array $linkedSkus, string $store = self::STORE_MAIN): array
    {
        $map = $this->normalizedToSkuMap($store);
        if ($map === []) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($linkedSkus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n === '' || isset($seen[$n]) || ! isset($map[$n])) {
                continue;
            }
            $seen[$n] = true;
            $out[] = $map[$n];
        }

        return $out;
    }
}
