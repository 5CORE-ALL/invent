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

    /**
     * Active variants with shared live inventory > 0 (catalog qty from Shopify sync).
     */
    public function inStockActiveVariantQuery(string $store = self::STORE_MAIN): Builder
    {
        return $this->activeVariantQuery($store)
            ->where('v.inventory_quantity', '>', 0);
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

    public function countDistinctInStockActiveSkus(string $store = self::STORE_MAIN): int
    {
        if (! $this->tablesReady()) {
            return 0;
        }

        return (int) $this->inStockActiveVariantQuery($store)
            ->distinct()
            ->count('v.sku');
    }

    /**
     * @return list<string>
     */
    public function activeSkuList(string $store = self::STORE_MAIN): array
    {
        return $this->skuListFromQuery($this->tablesReady() ? $this->activeVariantQuery($store) : null);
    }

    /**
     * Active + inventory_quantity > 0 — used for listings "All" tab.
     *
     * @return list<string>
     */
    public function inStockActiveSkuList(string $store = self::STORE_MAIN): array
    {
        return $this->skuListFromQuery($this->tablesReady() ? $this->inStockActiveVariantQuery($store) : null);
    }

    /**
     * @param  Builder|null  $query
     * @return list<string>
     */
    protected function skuListFromQuery(?Builder $query): array
    {
        if ($query === null) {
            return [];
        }

        return $query
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
     * Shared Shopify live inventory by normalized SKU (max across variants).
     *
     * @return array<string, int> normalized SKU => qty
     */
    public function shopifyInventoryByNorm(string $store = self::STORE_MAIN): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        $out = [];
        $this->activeVariantQuery($store)
            ->select(['v.sku', 'v.inventory_quantity'])
            ->orderBy('v.id')
            ->chunk(1000, function ($rows) use (&$out) {
                foreach ($rows as $row) {
                    $n = ShopifySku::normalizeSkuForShopifyLookup((string) ($row->sku ?? ''));
                    if ($n === '') {
                        continue;
                    }
                    $qty = (int) ($row->inventory_quantity ?? 0);
                    if (! isset($out[$n]) || $qty > $out[$n]) {
                        $out[$n] = $qty;
                    }
                }
            });

        return $out;
    }

    /**
     * Classify linked SKUs vs marketplace stock (shared Shopify catalog qty).
     * Priority: shopify qty <= 0 → zero; else qty match → matched; else → mismatch.
     *
     * @param  array<int, string>  $linkedSkus
     * @param  array<string, int>  $marketplaceStockMap  UPPER / normalize keys from stockMapForSkus
     * @return array{
     *   matched: list<string>,
     *   mismatch: list<string>,
     *   zero: list<string>,
     *   counts: array{matched: int, mismatch: int, zero: int, unlinked: int, linked: int}
     * }|null
     */
    public function classifyLinkedInventoryMatch(
        array $linkedSkus,
        array $marketplaceStockMap,
        string $store = self::STORE_MAIN
    ): ?array {
        if (! $this->tablesReady() || ! $this->hasAnyActive($store)) {
            return null;
        }

        $shopifyInv = $this->shopifyInventoryByNorm($store);
        $map = $this->normalizedToSkuMap($store);
        $inStockNorm = [];
        foreach ($shopifyInv as $n => $qty) {
            if ($qty > 0) {
                $inStockNorm[$n] = true;
            }
        }

        $matched = [];
        $mismatch = [];
        $zero = [];
        $seen = [];

        foreach ($linkedSkus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n === '' || isset($seen[$n]) || ! isset($map[$n])) {
                continue;
            }
            $seen[$n] = true;
            $canonical = $map[$n];
            $shopifyQty = (int) ($shopifyInv[$n] ?? 0);
            $mpQty = MarketplaceListingStockResolver::qtyFromMap($marketplaceStockMap, $canonical, (string) $sku);

            if ($shopifyQty <= 0) {
                $zero[] = $canonical;
            } elseif ($mpQty !== null && $mpQty === $shopifyQty) {
                $matched[] = $canonical;
            } else {
                $mismatch[] = $canonical;
            }
        }

        $unlinked = 0;
        foreach ($inStockNorm as $n => $_) {
            if (! isset($seen[$n])) {
                $unlinked++;
            }
        }

        return [
            'matched' => $matched,
            'mismatch' => $mismatch,
            'zero' => $zero,
            'counts' => [
                'all' => $this->countDistinctActiveSkus($store),
                'matched' => count($matched),
                'mismatch' => count($mismatch),
                'zero' => count($zero),
                'unlinked' => $unlinked,
                'linked' => count($matched) + count($mismatch) + count($zero),
                // Back-compat aliases used briefly in blades
                'linked_with_inv' => count($matched),
                'linked_zero_inv' => count($zero),
            ],
        ];
    }

    /**
     * Keep SKUs whose normalized form appears in $allowSkus (marketplace-active set, etc.).
     *
     * @param  array<int, string>  $skus
     * @param  array<int, string>  $allowSkus
     * @return list<string>
     */
    public function filterSkusByNormalizedAllowList(array $skus, array $allowSkus): array
    {
        $allow = [];
        foreach ($allowSkus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n !== '') {
                $allow[$n] = true;
            }
        }
        if ($allow === []) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($skus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n === '' || isset($seen[$n]) || ! isset($allow[$n])) {
                continue;
            }
            $seen[$n] = true;
            $out[] = (string) $sku;
        }

        return $out;
    }

    /**
     * Drop SKUs whose normalized form appears in $excludeSkus.
     *
     * @param  array<int, string>  $skus
     * @param  array<int, string>  $excludeSkus
     * @return list<string>
     */
    public function excludeSkusByNormalizedList(array $skus, array $excludeSkus): array
    {
        $deny = [];
        foreach ($excludeSkus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n !== '') {
                $deny[$n] = true;
            }
        }
        if ($deny === []) {
            return array_values(array_map('strval', $skus));
        }

        $out = [];
        $seen = [];
        foreach ($skus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n === '' || isset($seen[$n]) || isset($deny[$n])) {
                continue;
            }
            $seen[$n] = true;
            $out[] = (string) $sku;
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $linkedSkus
     * @param  array<string, int>  $marketplaceStockMap
     * @return array{matched: int, mismatch: int, zero: int, unlinked: int, linked: int}|null
     */
    public function listingCounts(array $linkedSkus, array $marketplaceStockMap = [], string $store = self::STORE_MAIN): ?array
    {
        $classified = $this->classifyLinkedInventoryMatch($linkedSkus, $marketplaceStockMap, $store);

        return $classified['counts'] ?? null;
    }

    /**
     * @deprecated use classifyLinkedInventoryMatch
     * @param  array<int, string>  $linkedSkus
     * @return array{with_inv: list<string>, zero_inv: list<string>}
     */
    public function splitLinkedByInventory(array $linkedSkus, string $store = self::STORE_MAIN): array
    {
        $classified = $this->classifyLinkedInventoryMatch($linkedSkus, [], $store);
        if ($classified === null) {
            return ['with_inv' => [], 'zero_inv' => []];
        }

        // Without marketplace map, "with_inv" = shopify > 0 (matched+mismatch), zero = shopify 0
        return [
            'with_inv' => array_values(array_merge($classified['matched'], $classified['mismatch'])),
            'zero_inv' => $classified['zero'],
        ];
    }

    /**
     * Restrict a ShopifySku eloquent query to live-verified active catalog SKUs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\ShopifySku>  $query
     * @param  list<string>|null  $extraSkuAllowList  when set, intersect with verified (e.g. linked/state)
     * @param  bool  $inStockOnly  when true and no allow-list, only SKUs with catalog inventory > 0
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\ShopifySku>
     */
    public function restrictShopifySkuQuery($query, ?array $extraSkuAllowList = null, bool $inStockOnly = false, string $store = self::STORE_MAIN)
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

        $verified = $inStockOnly ? $this->inStockActiveSkuList($store) : $this->activeSkuList($store);
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
