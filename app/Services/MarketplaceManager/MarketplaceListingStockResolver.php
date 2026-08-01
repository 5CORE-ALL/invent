<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressPricingPrice;
use App\Models\ProductStockMapping;
use App\Models\ReverbPricingPrice;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source for listing INDEX + DETAIL stock columns so Shopify qty and
 * marketplace qty stay consistent across pages.
 *
 * Marketplace lookup order:
 *   1) *_pricing_prices stock cache (written by inventory sync)
 *   2) product_stock_mappings.inventory_*
 *   3) reverb_products.remaining_inventory (Reverb only)
 */
final class MarketplaceListingStockResolver
{
    public const CHANNEL_REVERB = 'reverb';

    public const CHANNEL_ALIEXPRESS = 'aliexpress';

    public const CHANNEL_ALIBABA = 'alibaba';

    public const CHANNEL_NEWEGG = 'newegg';

    public const CHANNEL_SHEIN = 'shein';

    public const CHANNEL_AMAZON = 'amazon';

    public const CHANNEL_TOPDAWG = 'topdawg';

    public const CHANNEL_TEMU = 'temu';

    public const CHANNEL_EBAY1 = 'ebay1';

    public const CHANNEL_EBAY2 = 'ebay2';

    public const CHANNEL_EBAY3 = 'ebay3';

    public const CHANNEL_FAIRE = 'faire';

    public static function shopifyQtyFromRow(?ShopifySku $row): ?int
    {
        if (! $row) {
            return null;
        }

        foreach (['available_to_sell', 'inv', 'on_hand'] as $col) {
            $raw = $row->{$col} ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            if (is_numeric($raw)) {
                return (int) $raw;
            }
        }

        return null;
    }

    /**
     * Refresh one shopify_skus row from Shopify Admin inventory_quantity (fast).
     * Keeps detail pages from showing stale Ohio/local zeros when live store stock differs.
     */
    public static function refreshShopifyRowFromLiveVariantApi(ShopifySku $row): ShopifySku
    {
        $variantId = preg_replace('/\D+/', '', (string) ($row->variant_id ?? ''));
        $sku = trim((string) ($row->sku ?? ''));
        $store = preg_replace('#^https?://#', '', rtrim((string) config('services.shopify.store_url'), '/'));
        $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');
        if ($store === '' || $token === '') {
            return $row;
        }

        $qty = null;

        if ($variantId !== '') {
            for ($attempt = 1; $attempt <= 6; $attempt++) {
                try {
                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                    ])->timeout(25)->get("https://{$store}/admin/api/2025-01/variants/{$variantId}.json", [
                        'fields' => 'id,sku,inventory_quantity',
                    ]);
                    if ($response->status() === 429) {
                        sleep(max(1, (int) ($response->header('Retry-After') ?: $attempt)));
                        continue;
                    }
                    if ($response->successful()) {
                        $variant = $response->json('variant');
                        if (is_array($variant) && array_key_exists('inventory_quantity', $variant)) {
                            $qty = (int) $variant['inventory_quantity'];
                        }
                    }
                } catch (\Throwable $e) {
                    // fall through to GraphQL
                }
                break;
            }
        }

        if ($qty === null && $sku !== '') {
            $qty = self::liveShopifyQtyBySkuGraphql($store, $token, $sku);
        }

        if ($qty === null) {
            return $row;
        }

        $row->available_to_sell = $qty;
        $row->inv = $qty;
        $row->on_hand = $qty;
        $row->save();

        // Keep listings-table catalog in sync with live Admin qty (avoids 165 vs 158 drift).
        self::writeThroughCatalogInventoryQuantity($sku, $variantId, $qty);

        return $row->fresh() ?? $row;
    }

    /**
     * Update shopify_catalog_variants.inventory_quantity for this SKU/variant.
     */
    protected static function writeThroughCatalogInventoryQuantity(string $sku, string $variantId, int $qty): void
    {
        if (! Schema::hasTable('shopify_catalog_variants')) {
            return;
        }

        $qty = max(0, $qty);
        $now = now();
        $updated = 0;

        if ($variantId !== '') {
            $updated = DB::table('shopify_catalog_variants')
                ->where('store', 'main')
                ->where('shopify_variant_id', $variantId)
                ->update([
                    'inventory_quantity' => $qty,
                    'updated_at' => $now,
                ]);
        }

        if ($updated === 0 && $sku !== '') {
            DB::table('shopify_catalog_variants')
                ->where('store', 'main')
                ->where(function ($q) use ($sku) {
                    $q->where('sku', $sku)
                        ->orWhereRaw('UPPER(sku) = ?', [strtoupper($sku)]);
                })
                ->update([
                    'inventory_quantity' => $qty,
                    'updated_at' => $now,
                ]);
        }
    }

    protected static function liveShopifyQtyBySkuGraphql(string $store, string $token, string $sku): ?int
    {
        $escaped = addslashes($sku);
        $query = <<<'GQL'
        query ($q: String!) {
          productVariants(first: 5, query: $q) {
            edges {
              node {
                sku
                inventoryQuantity
              }
            }
          }
        }
        GQL;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post("https://{$store}/admin/api/2025-01/graphql.json", [
                    'query' => $query,
                    'variables' => ['q' => 'sku:'.$escaped],
                ]);
                if ($response->status() === 429) {
                    sleep(max(1, (int) ($response->header('Retry-After') ?: $attempt)));
                    continue;
                }
                if (! $response->successful()) {
                    return null;
                }
                $edges = $response->json('data.productVariants.edges') ?? [];
                $target = strtoupper(trim($sku));
                foreach ($edges as $edge) {
                    $node = $edge['node'] ?? [];
                    if (strtoupper(trim((string) ($node['sku'] ?? ''))) !== $target) {
                        continue;
                    }
                    if (array_key_exists('inventoryQuantity', $node)) {
                        return (int) $node['inventoryQuantity'];
                    }
                }
            } catch (\Throwable $e) {
                return null;
            }

            return null;
        }

        return null;
    }

    /**
     * Batch live Shopify inventory for listing INDEX (current page only).
     * Uses GraphQL variant GIDs when variant_id exists, else SKU search.
     * Write-through updates shopify_skus so other screens stay warmer.
     *
     * @param  array<int, ShopifySku>  $rows
     * @return array<string, int> keyed by UPPER(trim(sku))
     */
    public static function liveShopifyQtyMapForRows(array $rows, bool $persist = true): array
    {
        $store = preg_replace('#^https?://#', '', rtrim((string) config('services.shopify.store_url'), '/'));
        $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');
        if ($store === '' || $token === '' || $rows === []) {
            return [];
        }

        $fallback = [];
        $gids = [];
        foreach ($rows as $row) {
            if (! $row instanceof ShopifySku) {
                continue;
            }
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $upper = strtoupper($sku);
            $dbQty = self::shopifyQtyFromRow($row);
            if ($dbQty !== null) {
                $fallback[$upper] = $dbQty;
            }
            $variantId = preg_replace('/\D+/', '', (string) ($row->variant_id ?? ''));
            if ($variantId !== '') {
                $gids['gid://shopify/ProductVariant/'.$variantId] = $upper;
            }
        }

        $fromLive = [];
        if ($gids !== []) {
            foreach (array_chunk($gids, 40, true) as $chunk) {
                foreach (self::liveShopifyQtyByVariantGids($store, $token, $chunk) as $upper => $qty) {
                    $fromLive[$upper] = $qty;
                }
            }
        }

        $needSku = [];
        foreach ($rows as $row) {
            if (! $row instanceof ShopifySku) {
                continue;
            }
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $upper = strtoupper($sku);
            if (! array_key_exists($upper, $fromLive)) {
                $needSku[$upper] = $sku;
            }
        }
        if ($needSku !== []) {
            foreach (array_chunk(array_values($needSku), 15) as $chunk) {
                foreach (self::liveShopifyQtyBySkuListGraphql($store, $token, $chunk) as $upper => $qty) {
                    $fromLive[$upper] = $qty;
                }
            }
        }

        // Live only — never blend in stale DB fallback after a successful live fetch.
        if ($fromLive !== []) {
            if ($persist) {
                foreach ($fromLive as $upper => $qty) {
                    ShopifySku::query()
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [$upper])
                        ->update([
                            'available_to_sell' => $qty,
                            'inv' => $qty,
                            'on_hand' => $qty,
                            'updated_at' => now(),
                        ]);
                }
            }

            return $fromLive;
        }

        // Live fetch failed completely — last resort DB for display only.
        return $fallback;
    }

    /**
     * Public wrapper for live Shopify qty by SKU list (listings index / live catalogs).
     *
     * @param  list<string>  $skus
     * @return array<string, int> UPPER(sku) => qty
     */
    public static function liveShopifyQtyBySkuListGraphqlPublic(string $store, string $token, array $skus): array
    {
        return self::liveShopifyQtyBySkuListGraphql($store, $token, $skus);
    }

    /**
     * @param  array<string, string>  $gidToUpperSku
     * @return array<string, int>
     */
    protected static function liveShopifyQtyByVariantGids(string $store, string $token, array $gidToUpperSku): array
    {
        if ($gidToUpperSku === []) {
            return [];
        }

        $query = <<<'GQL'
        query ($ids: [ID!]!) {
          nodes(ids: $ids) {
            ... on ProductVariant {
              id
              sku
              inventoryQuantity
            }
          }
        }
        GQL;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(45)->post("https://{$store}/admin/api/2025-01/graphql.json", [
                    'query' => $query,
                    'variables' => ['ids' => array_keys($gidToUpperSku)],
                ]);
                if ($response->status() === 429) {
                    sleep(max(1, (int) ($response->header('Retry-After') ?: $attempt)));
                    continue;
                }
                if (! $response->successful()) {
                    return [];
                }
                $out = [];
                foreach ($response->json('data.nodes') ?? [] as $node) {
                    if (! is_array($node) || ! array_key_exists('inventoryQuantity', $node)) {
                        continue;
                    }
                    $sku = strtoupper(trim((string) ($node['sku'] ?? '')));
                    $id = (string) ($node['id'] ?? '');
                    $upper = $sku !== '' ? $sku : ($gidToUpperSku[$id] ?? '');
                    if ($upper === '') {
                        continue;
                    }
                    $out[$upper] = (int) $node['inventoryQuantity'];
                }

                return $out;
            } catch (\Throwable $e) {
                return [];
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    protected static function liveShopifyQtyBySkuListGraphql(string $store, string $token, array $skus): array
    {
        if ($skus === []) {
            return [];
        }
        $parts = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $parts[] = 'sku:'.json_encode($sku);
        }
        if ($parts === []) {
            return [];
        }

        $query = <<<'GQL'
        query ($q: String!) {
          productVariants(first: 50, query: $q) {
            edges {
              node {
                sku
                inventoryQuantity
              }
            }
          }
        }
        GQL;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(45)->post("https://{$store}/admin/api/2025-01/graphql.json", [
                    'query' => $query,
                    'variables' => ['q' => implode(' OR ', $parts)],
                ]);
                if ($response->status() === 429) {
                    sleep(max(1, (int) ($response->header('Retry-After') ?: $attempt)));
                    continue;
                }
                if (! $response->successful()) {
                    return [];
                }
                $wanted = [];
                foreach ($skus as $sku) {
                    $wanted[strtoupper(trim((string) $sku))] = true;
                }
                $out = [];
                foreach ($response->json('data.productVariants.edges') ?? [] as $edge) {
                    $node = $edge['node'] ?? [];
                    $sku = strtoupper(trim((string) ($node['sku'] ?? '')));
                    if ($sku === '' || ! isset($wanted[$sku]) || ! array_key_exists('inventoryQuantity', $node)) {
                        continue;
                    }
                    $out[$sku] = (int) $node['inventoryQuantity'];
                }

                return $out;
            } catch (\Throwable $e) {
                return [];
            }
        }

        return [];
    }

    /**
     * Qty map for listings fallback: shopify_skus.available_to_sell first,
     * then catalog variants if the live SKU column is missing.
     *
     * @param  array<int, ShopifySku>  $rows
     * @return array<string, int> keyed by UPPER(trim(sku))
     */
    public static function dbShopifyQtyMapForRows(array $rows): array
    {
        $skus = [];
        foreach ($rows as $row) {
            if ($row instanceof ShopifySku) {
                $sku = trim((string) ($row->sku ?? ''));
                if ($sku !== '') {
                    $skus[] = $sku;
                }
            }
        }

        $out = self::liveSkuShopifyQtyMapForSkus($skus);

        foreach ($rows as $row) {
            if (! $row instanceof ShopifySku) {
                continue;
            }
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $upper = strtoupper($sku);
            if (array_key_exists($upper, $out)) {
                continue;
            }
            $qty = self::shopifyQtyFromRow($row);
            if ($qty !== null) {
                $out[$upper] = $qty;
            }
        }

        if ($out === []) {
            $out = self::catalogShopifyQtyMapForSkus($skus);
        } else {
            foreach (self::catalogShopifyQtyMapForSkus($skus) as $upper => $qty) {
                if (! array_key_exists($upper, $out)) {
                    $out[$upper] = $qty;
                }
            }
        }

        return $out;
    }

    /**
     * Listings Shopify Qty SoT: shopify_skus.available_to_sell / inv / on_hand
     * (written by SyncShopifyLiveInventory / Ohio live sync — not catalog variants).
     * Keys: UPPER(trim(sku)) and normalizeSkuForShopifyLookup(sku).
     *
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    public static function liveSkuShopifyQtyMapForSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
        if ($skus === [] || ! Schema::hasTable('shopify_skus')) {
            return [];
        }

        $out = [];
        $rows = ShopifySku::query()
            ->where(function ($q) use ($skus) {
                $q->whereIn('sku', $skus);
                foreach ($skus as $sku) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
                }
            })
            ->get(['sku', 'available_to_sell', 'inv', 'on_hand']);

        foreach ($rows as $row) {
            $qty = self::shopifyQtyFromRow($row);
            if ($qty === null) {
                continue;
            }
            self::put($out, (string) $row->sku, $qty);
        }

        return $out;
    }

    /**
     * Full-map version for mismatch classification (all linked SKUs).
     *
     * @return array<string, int> normalized SKU => qty
     */
    public static function liveSkuShopifyInventoryByNorm(): array
    {
        if (! Schema::hasTable('shopify_skus')) {
            return [];
        }

        $out = [];
        ShopifySku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$out) {
                foreach ($rows as $row) {
                    $n = ShopifySku::normalizeSkuForShopifyLookup((string) ($row->sku ?? ''));
                    if ($n === '') {
                        continue;
                    }
                    $qty = self::shopifyQtyFromRow($row);
                    if ($qty === null) {
                        continue;
                    }
                    if (! isset($out[$n]) || $qty > $out[$n]) {
                        $out[$n] = $qty;
                    }
                }
            });

        return $out;
    }

    /**
     * Qty from shopify_catalog_variants (Refresh Shopify + inventory/product webhooks).
     * No Admin API. Keys are UPPER(trim(sku)).
     *
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    public static function catalogShopifyQtyMapForSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
        if ($skus === [] || ! Schema::hasTable('shopify_catalog_variants')) {
            return [];
        }

        $out = [];
        $rows = DB::table('shopify_catalog_variants')
            ->where('store', 'main')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($q) use ($skus) {
                $q->whereIn('sku', $skus);
                foreach ($skus as $sku) {
                    $q->orWhereRaw('UPPER(sku) = ?', [strtoupper($sku)]);
                }
            })
            ->get(['sku', 'inventory_quantity']);

        foreach ($rows as $row) {
            $upper = strtoupper(trim((string) ($row->sku ?? '')));
            if ($upper === '' || $row->inventory_quantity === null || $row->inventory_quantity === '') {
                continue;
            }
            $qty = (int) $row->inventory_quantity;
            if (! isset($out[$upper]) || $qty > $out[$upper]) {
                $out[$upper] = $qty;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $liveMap
     */
    public static function shopifyQtyFromLiveMapOrRow(array $liveMap, ?ShopifySku $row, string $sku = ''): ?int
    {
        $key = strtoupper(trim($sku !== '' ? $sku : (string) ($row->sku ?? '')));
        if ($key !== '' && array_key_exists($key, $liveMap)) {
            return $liveMap[$key];
        }

        return self::shopifyQtyFromRow($row);
    }

    /**
     * Re-check mismatch SKUs with the same live qty sources the listings table shows.
     * Moves live qty-matched SKUs into matched; live Shopify <= 0 into zero.
     * SKUs with missing live data stay in mismatch.
     *
     * @param  list<string>  $matched
     * @param  list<string>  $mismatch
     * @param  list<string>  $zero
     * @param  array<string, int>  $liveShopifyByUpper  UPPER(sku) => qty
     * @param  array<string, int>  $liveMpByUpper  UPPER/norm sku => marketplace qty
     * @return array{matched: list<string>, mismatch: list<string>, zero: list<string>}
     */
    public static function reconcileLinkedTabsWithLiveQty(
        array $matched,
        array $mismatch,
        array $zero,
        array $liveShopifyByUpper,
        array $liveMpByUpper
    ): array {
        if ($mismatch === [] || ($liveShopifyByUpper === [] && $liveMpByUpper === [])) {
            return [
                'matched' => array_values($matched),
                'mismatch' => array_values($mismatch),
                'zero' => array_values($zero),
            ];
        }

        $stillMismatch = [];
        $addMatched = [];
        $addZero = [];

        foreach ($mismatch as $sku) {
            $sku = (string) $sku;
            $upper = strtoupper(trim($sku));
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);

            $shopifyQty = null;
            if ($upper !== '' && array_key_exists($upper, $liveShopifyByUpper)) {
                $shopifyQty = (int) $liveShopifyByUpper[$upper];
            } elseif ($norm !== '' && array_key_exists($norm, $liveShopifyByUpper)) {
                $shopifyQty = (int) $liveShopifyByUpper[$norm];
            }

            $mpQty = null;
            if ($upper !== '' && array_key_exists($upper, $liveMpByUpper)) {
                $mpQty = (int) $liveMpByUpper[$upper];
            } elseif ($norm !== '' && array_key_exists($norm, $liveMpByUpper)) {
                $mpQty = (int) $liveMpByUpper[$norm];
            }

            if ($shopifyQty === null || $mpQty === null) {
                $stillMismatch[] = $sku;
                continue;
            }

            if ($shopifyQty <= 0) {
                $addZero[] = $sku;
            } elseif ($shopifyQty === $mpQty) {
                $addMatched[] = $sku;
            } else {
                $stillMismatch[] = $sku;
            }
        }

        $dedupe = static function (array $skus): array {
            $out = [];
            $seen = [];
            foreach ($skus as $sku) {
                $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                $key = $n !== '' ? $n : strtoupper(trim((string) $sku));
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = (string) $sku;
            }

            return $out;
        };

        return [
            'matched' => $dedupe(array_merge($matched, $addMatched)),
            'mismatch' => $dedupe($stillMismatch),
            'zero' => $dedupe(array_merge($zero, $addZero)),
        ];
    }

    /**
     * Resolve marketplace stock for one Shopify/listing SKU (tries shopify + metric SKU).
     */
    public static function resolveMarketplaceQty(string $channel, string $shopifySku, ?string $metricSku = null): ?int
    {
        $map = self::stockMapForSkus($channel, array_values(array_filter([
            $shopifySku,
            $metricSku,
        ])));

        foreach ([$shopifySku, $metricSku] as $candidate) {
            if ($candidate === null || trim((string) $candidate) === '') {
                continue;
            }
            $key = strtoupper(trim((string) $candidate));
            if (array_key_exists($key, $map)) {
                return $map[$key];
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $candidate);
            if ($norm !== '' && array_key_exists($norm, $map)) {
                return $map[$norm];
            }
        }

        return null;
    }

    /**
     * Build UPPER/norm SKU => inventory map from a warm live-listings cache payload.
     *
     * @param  array<int, array{sku?: string, inventory?: int|null}>|null  $rows
     * @return array<string, int>
     */
    public static function stockMapFromLiveListingRows(?array $rows): array
    {
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            if (! array_key_exists('inventory', $row) || $row['inventory'] === null) {
                continue;
            }
            $qty = (int) $row['inventory'];
            $map[strtoupper($sku)] = $qty;
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $map[$norm] = $qty;
            }
        }

        return $map;
    }

    /**
     * Overlay live-cache stock onto local pricing/mapping stock.
     * Live values win when present; local fills gaps (common for inactive
     * listings where live inventory is null).
     *
     * @param  array<string, int>  $localMap
     * @param  array<string, int>  $liveMap
     * @return array<string, int>
     */
    public static function mergeLocalAndLiveStockMaps(array $localMap, array $liveMap): array
    {
        if ($localMap === []) {
            return $liveMap;
        }
        if ($liveMap === []) {
            return $localMap;
        }

        foreach ($liveMap as $key => $qty) {
            $localMap[$key] = (int) $qty;
        }

        return $localMap;
    }

    /**
     * Prefer warm live-listings inventory; fall back to local stock for SKUs
     * the live payload omitted or left null.
     *
     * @param  array<int, array{sku?: string, inventory?: int|null}>|null  $liveRows
     * @param  array<string, int>  $localMap
     * @return array<string, int>
     */
    public static function classifyStockMapFromLiveOrLocal(?array $liveRows, array $localMap): array
    {
        return self::mergeLocalAndLiveStockMaps(
            $localMap,
            self::stockMapFromLiveListingRows($liveRows)
        );
    }

    /**
     * Batch stock map for listings index.
     * Keys include UPPER(trim(sku)) and normalizeSkuForShopifyLookup(sku) for each found row.
     *
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    public static function stockMapForSkus(string $channel, array $skus): array
    {
        $keys = self::expandSkuKeys($skus);
        if ($keys === []) {
            return [];
        }

        $map = [];

        if ($channel === self::CHANNEL_REVERB) {
            self::hydrateFromPricing($map, $keys, 'reverb');
            self::hydrateFromMappings($map, $keys, 'inventory_reverb');
            self::hydrateFromReverbProducts($map, $keys);
        } elseif ($channel === self::CHANNEL_ALIEXPRESS) {
            self::hydrateFromPricing($map, $keys, 'aliexpress');
            self::hydrateFromMappings($map, $keys, 'inventory_aliexpress');
        } elseif ($channel === self::CHANNEL_ALIBABA) {
            self::hydrateFromPricing($map, $keys, 'alibaba');
            self::hydrateFromMappings($map, $keys, 'inventory_alibaba');
        } elseif ($channel === self::CHANNEL_NEWEGG) {
            self::hydrateFromPricing($map, $keys, 'newegg');
            self::hydrateFromNeweggPricing($map, $keys);
            self::hydrateFromMappings($map, $keys, 'inventory_newegg');
        } elseif ($channel === self::CHANNEL_SHEIN) {
            self::hydrateFromPricing($map, $keys, 'shein');
            self::hydrateFromMappings($map, $keys, 'inventory_shein');
        } elseif ($channel === self::CHANNEL_TOPDAWG) {
            self::hydrateFromPricing($map, $keys, 'topdawg');
            self::hydrateFromMappings($map, $keys, 'inventory_topdawg');
        } elseif ($channel === self::CHANNEL_AMAZON) {
            self::hydrateFromAmazonListingStatuses($map, $keys);
            self::hydrateFromMappings($map, $keys, 'inventory_amazon');
        } elseif ($channel === self::CHANNEL_TEMU) {
            self::hydrateFromTemuMetrics($map, $keys);
            self::hydrateFromMappings($map, $keys, 'inventory_temu');
        } elseif ($channel === self::CHANNEL_EBAY1) {
            self::hydrateFromPricing($map, $keys, 'ebay1');
            self::hydrateFromMappings($map, $keys, 'inventory_ebay1');
        } elseif ($channel === self::CHANNEL_EBAY2) {
            self::hydrateFromPricing($map, $keys, 'ebay2');
            self::hydrateFromMappings($map, $keys, 'inventory_ebay2');
        } elseif ($channel === self::CHANNEL_EBAY3) {
            self::hydrateFromPricing($map, $keys, 'ebay3');
            self::hydrateFromMappings($map, $keys, 'inventory_ebay3');
        } elseif ($channel === self::CHANNEL_FAIRE) {
            self::hydrateFromPricing($map, $keys, 'faire');
            self::hydrateFromMappings($map, $keys, 'inventory_faire');
        }

        return $map;
    }

    /**
     * Look up qty for a shopify SKU from a prebuilt map (tries upper + normalize).
     *
     * @param  array<string, int>  $map
     */
    public static function qtyFromMap(array $map, string $sku, ?string $altSku = null): ?int
    {
        foreach ([$sku, $altSku] as $candidate) {
            if ($candidate === null || trim((string) $candidate) === '') {
                continue;
            }
            $upper = strtoupper(trim((string) $candidate));
            if (array_key_exists($upper, $map)) {
                return $map[$upper];
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $candidate);
            if ($norm !== '' && array_key_exists($norm, $map)) {
                return $map[$norm];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $skus
     * @return list<string>
     */
    protected static function expandSkuKeys(array $skus): array
    {
        $keys = [];
        foreach ($skus as $sku) {
            $trim = trim((string) $sku);
            if ($trim === '') {
                continue;
            }
            $keys[] = $trim;
            $keys[] = strtoupper($trim);
            $norm = ShopifySku::normalizeSkuForShopifyLookup($trim);
            if ($norm !== '') {
                $keys[] = $norm;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $keys
     */
    protected static function hydrateFromPricing(array &$map, array $keys, string $channel): void
    {
        if ($channel === 'reverb') {
            if (! Schema::hasTable('reverb_pricing_prices') || ! Schema::hasColumn('reverb_pricing_prices', 'rv_stock')) {
                return;
            }
            ReverbPricingPrice::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('rv_stock')
                ->get(['sku', 'rv_stock'])
                ->each(function (ReverbPricingPrice $row) use (&$map) {
                    self::put($map, (string) $row->sku, (int) $row->rv_stock);
                });

            return;
        }

        if ($channel === 'newegg') {
            if (! Schema::hasTable('newegg_pricing_prices') || ! Schema::hasColumn('newegg_pricing_prices', 'ne_stock')) {
                return;
            }
            \App\Models\NeweggPricingPrice::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('ne_stock')
                ->get(['sku', 'ne_stock'])
                ->each(function ($row) use (&$map) {
                    self::put($map, (string) $row->sku, (int) $row->ne_stock);
                });

            return;
        }

        if ($channel === 'shein') {
            if (! Schema::hasTable('shein_pricing_prices') || ! Schema::hasColumn('shein_pricing_prices', 'shein_stock')) {
                return;
            }
            \App\Models\SheinPricingPrice::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('shein_stock')
                ->get(['sku', 'shein_stock'])
                ->each(function ($row) use (&$map) {
                    self::put($map, (string) $row->sku, (int) $row->shein_stock);
                });

            return;
        }

        if ($channel === 'topdawg') {
            if (! Schema::hasTable('topdawg_products') || ! Schema::hasColumn('topdawg_products', 'remaining_inventory')) {
                return;
            }
            \App\Models\TopDawgProduct::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('remaining_inventory')
                ->get(['sku', 'remaining_inventory'])
                ->each(function ($row) use (&$map) {
                    self::put($map, (string) $row->sku, (int) $row->remaining_inventory);
                });

            return;
        }

        if ($channel === 'temu') {
            self::hydrateFromTemuMetrics($map, $keys);

            return;
        }

        if ($channel === 'ebay1') {
            if (! Schema::hasTable('ebay_metrics') || ! Schema::hasColumn('ebay_metrics', 'ebay_stock')) {
                return;
            }
            \App\Models\EbayMetric::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('ebay_stock')
                ->get(['sku', 'ebay_stock'])
                ->each(function ($row) use (&$map) {
                    self::put($map, (string) $row->sku, (int) $row->ebay_stock);
                });

            return;
        }

        if ($channel === 'ebay2') {
            if (! Schema::hasTable('ebay_2_metrics') || ! Schema::hasColumn('ebay_2_metrics', 'ebay_stock')) {
                return;
            }
            \App\Models\Ebay2Metric::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('ebay_stock')
                ->get(['sku', 'ebay_stock'])
                ->each(function ($row) use (&$map) {
                    self::put($map, (string) $row->sku, (int) $row->ebay_stock);
                });

            return;
        }

        if ($channel === 'ebay3') {
            if (! Schema::hasTable('ebay_3_metrics') || ! Schema::hasColumn('ebay_3_metrics', 'ebay_stock')) {
                return;
            }
            \App\Models\Ebay3Metric::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('ebay_stock')
                ->get(['sku', 'ebay_stock'])
                ->each(function ($row) use (&$map) {
                    self::put($map, (string) $row->sku, (int) $row->ebay_stock);
                });

            return;
        }

        if ($channel === 'faire') {
            if (! Schema::hasTable('faire_pricing_prices') || ! Schema::hasColumn('faire_pricing_prices', 'faire_stock')) {
                return;
            }
            \App\Models\FairePricingPrice::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('faire_stock')
                ->get(['sku', 'faire_stock'])
                ->each(function ($row) use (&$map) {
                    self::put($map, (string) $row->sku, (int) $row->faire_stock);
                });

            return;
        }

        if ($channel === 'alibaba') {
            if (! Schema::hasTable('alibaba_pricing_prices') || ! Schema::hasColumn('alibaba_pricing_prices', 'ab_stock')) {
                return;
            }
            \App\Models\AlibabaPricingPrice::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('ab_stock')
                ->get(['sku', 'ab_stock'])
                ->each(function ($row) use (&$map) {
                    self::put($map, (string) $row->sku, (int) $row->ab_stock);
                });

            return;
        }

        if (! Schema::hasTable('aliexpress_pricing_prices') || ! Schema::hasColumn('aliexpress_pricing_prices', 'ae_stock')) {
            return;
        }
        AliexpressPricingPrice::query()
            ->whereIn('sku', $keys)
            ->whereNotNull('ae_stock')
            ->get(['sku', 'ae_stock'])
            ->each(function (AliexpressPricingPrice $row) use (&$map) {
                self::put($map, (string) $row->sku, (int) $row->ae_stock);
            });
    }

    /**
     * Fallback: operational Newegg catalog table (seller_part_number + available_quantity).
     *
     * @param  array<string, int>  $map
     * @param  list<string>  $keys
     */
    protected static function hydrateFromNeweggPricing(array &$map, array $keys): void
    {
        if (! Schema::hasTable('newegg_pricing') || ! Schema::hasColumn('newegg_pricing', 'available_quantity')) {
            return;
        }

        $missingKeys = array_values(array_filter($keys, static function ($sku) use ($map) {
            $upper = strtoupper(trim((string) $sku));
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);

            return ! array_key_exists($upper, $map) && ($norm === '' || ! array_key_exists($norm, $map));
        }));
        if ($missingKeys === []) {
            return;
        }

        DB::table('newegg_pricing')
            ->whereNotNull('available_quantity')
            ->where(function ($q) use ($missingKeys) {
                $q->whereIn('seller_part_number', $missingKeys);
                foreach ($missingKeys as $sku) {
                    $q->orWhereRaw('UPPER(TRIM(seller_part_number)) = ?', [strtoupper(trim((string) $sku))]);
                }
            })
            ->get(['seller_part_number', 'available_quantity'])
            ->each(function ($row) use (&$map) {
                self::put($map, (string) $row->seller_part_number, (int) $row->available_quantity);
            });
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $keys
     */
    protected static function hydrateFromMappings(array &$map, array $keys, string $column): void
    {
        if (! Schema::hasTable('product_stock_mappings') || ! Schema::hasColumn('product_stock_mappings', $column)) {
            return;
        }

        $missingKeys = array_values(array_filter($keys, static function ($sku) use ($map) {
            $upper = strtoupper(trim((string) $sku));
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);

            return ! array_key_exists($upper, $map) && ($norm === '' || ! array_key_exists($norm, $map));
        }));
        if ($missingKeys === []) {
            return;
        }

        ProductStockMapping::query()
            ->whereIn('sku', $missingKeys)
            ->whereNotNull($column)
            ->get(['sku', $column])
            ->each(function ($row) use (&$map, $column) {
                $raw = $row->{$column};
                if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                    return;
                }
                self::put($map, (string) $row->sku, (int) $raw);
            });
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $keys
     */
    protected static function hydrateFromTemuMetrics(array &$map, array $keys): void
    {
        if (! Schema::hasTable('temu_metrics') || ! Schema::hasColumn('temu_metrics', 'quantity')) {
            return;
        }

        \App\Models\TemuMetric::query()
            ->whereIn('sku', $keys)
            ->whereNotNull('quantity')
            ->get(['sku', 'quantity'])
            ->each(function ($row) use (&$map) {
                self::put($map, (string) $row->sku, (int) $row->quantity);
            });
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $keys
     */
    protected static function hydrateFromAmazonListingStatuses(array &$map, array $keys): void
    {
        if (! Schema::hasTable('amazon_listing_statuses')) {
            return;
        }

        \App\Models\AmazonListingStatus::query()
            ->whereIn('sku', $keys)
            ->get(['sku', 'value'])
            ->each(function ($row) use (&$map) {
                $value = is_array($row->value) ? $row->value : [];
                if (isset($value['quantity']) && is_numeric($value['quantity'])) {
                    self::put($map, (string) $row->sku, (int) $value['quantity']);
                }
            });
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $keys
     */
    protected static function hydrateFromReverbProducts(array &$map, array $keys): void
    {
        if (! Schema::hasTable('reverb_products') || ! Schema::hasColumn('reverb_products', 'remaining_inventory')) {
            return;
        }

        $stillMissing = [];
        foreach ($keys as $sku) {
            $upper = strtoupper(trim((string) $sku));
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if (! array_key_exists($upper, $map) && ($norm === '' || ! array_key_exists($norm, $map))) {
                $stillMissing[] = $upper;
            }
        }
        $stillMissing = array_values(array_unique(array_filter($stillMissing)));
        if ($stillMissing === []) {
            return;
        }

        DB::table('reverb_products')
            ->whereNotNull('remaining_inventory')
            ->where(function ($q) use ($keys, $stillMissing) {
                $q->whereIn('sku', $keys);
                foreach ($stillMissing as $sku) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$sku]);
                }
            })
            ->get(['sku', 'remaining_inventory'])
            ->each(function ($row) use (&$map) {
                if ($row->remaining_inventory === null) {
                    return;
                }
                self::put($map, (string) $row->sku, (int) $row->remaining_inventory);
            });
    }

    /**
     * @param  array<string, int>  $map
     */
    protected static function put(array &$map, string $sku, int $qty): void
    {
        $trim = trim($sku);
        if ($trim === '') {
            return;
        }
        $upper = strtoupper($trim);
        $norm = ShopifySku::normalizeSkuForShopifyLookup($trim);
        // First write wins so pricing cache beats later sources for aliases.
        if (! array_key_exists($upper, $map)) {
            $map[$upper] = $qty;
        }
        if ($norm !== '' && ! array_key_exists($norm, $map)) {
            $map[$norm] = $qty;
        }
    }
}
