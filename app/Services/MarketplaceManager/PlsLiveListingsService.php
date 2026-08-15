<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifyCatalogVariant;
use App\Models\ShopifySku;
use App\Services\ShopifyCatalogSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for Shopify PLS listings UI.
 * Rows come from shopify_catalog_* (store=pls); Refresh live overlays Admin inventory.
 */
class PlsLiveListingsService
{
    private const CACHE_KEY = 'mm.pls.live_listings.v1';

    private const CACHE_GEN_KEY = 'mm.pls.live_listings.gen';

    public function clearCache(): void
    {
        Cache::increment(self::CACHE_GEN_KEY);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function all(bool $forceRefresh = false): array
    {
        if (! $forceRefresh) {
            $cached = $this->peekCached();
            if ($cached !== null) {
                return $cached;
            }
        }

        $liveInv = [];
        if ($forceRefresh) {
            try {
                $liveInv = app(ShopifyCatalogSyncService::class)->pullInventoryByNormalizedSku('pls');
            } catch (\Throwable $e) {
                Log::warning('PlsLiveListingsService: live inventory pull failed', ['error' => $e->getMessage()]);
            }
        } else {
            $liveInv = $this->peekCachedInventoryMap();
        }

        $gen = (int) Cache::get(self::CACHE_GEN_KEY, 0);
        $rows = $this->fetchFromLocal($liveInv);
        if ((int) Cache::get(self::CACHE_GEN_KEY, 0) === $gen) {
            Cache::put(self::CACHE_KEY, $rows, now()->addHours(6));
        }

        return $rows;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float}>|null
     */
    public function peekCached(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @return array<string, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function indexedBySku(bool $forceRefresh = false): array
    {
        $out = [];
        foreach ($this->all($forceRefresh) as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku !== '') {
                $out[$sku] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $productIds
     * @return array<string, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function liveDetailsByProductIds(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $productIds
        ), static fn ($id) => $id !== '')));

        if ($ids === []) {
            return [];
        }

        $source = $this->peekCached();
        if ($source === null) {
            $source = $this->all(false);
        }

        $out = [];
        foreach ($source as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pid = trim((string) ($row['product_id'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            $skuId = trim((string) ($row['sku_id'] ?? ''));
            $hit = in_array($pid, $ids, true)
                || in_array($sku, $ids, true)
                || in_array(strtoupper($sku), $ids, true)
                || ($skuId !== '' && in_array($skuId, $ids, true));
            if (! $hit) {
                continue;
            }
            if ($pid !== '') {
                $out[$pid] = $row;
            }
            if ($sku !== '') {
                $out[$sku] = $row;
                $out[strtoupper($sku)] = $row;
            }
            if ($skuId !== '') {
                $out[$skuId] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $liveInv  normalized SKU => qty
     * @return array<int, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    protected function fetchFromLocal(array $liveInv = []): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }

        $out = [];
        $productCache = [];
        ShopifyCatalogVariant::query()
            ->where('store', 'pls')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$out, &$productCache, $liveInv) {
                $productIds = $rows->pluck('shopify_catalog_product_id')->unique()->filter()->all();
                $missing = array_values(array_diff($productIds, array_keys($productCache)));
                if ($missing !== []) {
                    $prods = DB::table('shopify_catalog_products')
                        ->where('store', 'pls')
                        ->whereIn('id', $missing)
                        ->get(['id', 'status', 'title']);
                    foreach ($prods as $p) {
                        $productCache[(int) $p->id] = $p;
                    }
                }
                foreach ($rows as $row) {
                    $p = $productCache[(int) $row->shopify_catalog_product_id] ?? null;
                    $mapped = $this->mapRow((object) [
                        'sku' => $row->sku,
                        'shopify_variant_id' => $row->shopify_variant_id,
                        'shopify_product_id' => $row->shopify_product_id,
                        'price' => $row->price,
                        'inventory_quantity' => $row->inventory_quantity,
                        'status' => $p->status ?? 'active',
                        'title' => $p->title ?? null,
                    ], $liveInv);
                    if ($mapped !== null) {
                        $out[] = $mapped;
                    }
                }
            });

        return $out;
    }

    /**
     * @param  array<string, int>  $liveInv
     * @return array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float}|null
     */
    protected function mapRow(object $row, array $liveInv = []): ?array
    {
        $sku = trim((string) ($row->sku ?? ''));
        if ($sku === '' || MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
            return null;
        }

        $productId = trim((string) ($row->shopify_product_id ?? ''));
        if ($productId === '') {
            return null;
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        $inventory = $row->inventory_quantity !== null ? (int) $row->inventory_quantity : null;
        if ($norm !== '' && array_key_exists($norm, $liveInv)) {
            $inventory = (int) $liveInv[$norm];
        }

        $skuId = trim((string) ($row->shopify_variant_id ?? ''));
        $state = strtolower(trim((string) ($row->status ?? 'active')));
        if ($state === '') {
            $state = 'active';
        }

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'sku_id' => $skuId !== '' ? $skuId : null,
            'state' => $state,
            'inventory' => $inventory,
            'title' => isset($row->title) ? (string) $row->title : null,
            'price' => $row->price !== null ? (float) $row->price : null,
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function peekCachedInventoryMap(): array
    {
        try {
            $cached = Cache::get('mm.shopify.pls.inv.by_norm_sku.v1');

            return is_array($cached) ? $cached : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
