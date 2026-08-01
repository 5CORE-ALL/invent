<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\ProductStockMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for Amazon listings UI (Reverb/Temu parity).
 */
class AmazonLiveListingsService
{
    private const CACHE_KEY = 'mm.amazon.live_listings.v1';

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function all(bool $forceRefresh = false): array
    {
        if (! $forceRefresh) {
            $cached = $this->peekCached();
            if ($cached !== null) {
                return $cached;
            }
        }

        $rows = $this->fetchFromLocal();
        Cache::put(self::CACHE_KEY, $rows, now()->addHours(6));

        return $rows;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>|null
     */
    public function peekCached(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @return array<string, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
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
     * @return array<string, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function liveDetailsByProductIds(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $productIds
        ), static fn ($id) => $id !== '')));

        if ($ids === [] || ! Schema::hasTable('amazon_listing_statuses')) {
            return [];
        }

        $rows = AmazonListingStatus::query()
            ->where(function ($q) use ($ids) {
                $q->whereIn('sku', $ids);
                foreach ($ids as $id) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($id)]);
                }
            })
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $parsed = $this->mapProduct($row);
            if ($parsed === null) {
                continue;
            }
            $out[$parsed['product_id']] = $parsed;
            $out[$parsed['sku']] = $parsed;
            $out[strtoupper($parsed['sku'])] = $parsed;
        }

        return $out;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    protected function fetchFromLocal(): array
    {
        if (! Schema::hasTable('amazon_listing_statuses')) {
            return [];
        }

        $inventoryMap = [];
        if (Schema::hasTable('product_stock_mappings') && Schema::hasColumn('product_stock_mappings', 'inventory_amazon')) {
            ProductStockMapping::query()
                ->whereNotNull('inventory_amazon')
                ->select(['sku', 'inventory_amazon'])
                ->orderBy('id')
                ->chunkById(1000, function ($chunk) use (&$inventoryMap) {
                    foreach ($chunk as $row) {
                        $sku = trim((string) $row->sku);
                        if ($sku !== '') {
                            $inventoryMap[strtoupper($sku)] = (int) $row->inventory_amazon;
                        }
                    }
                });
        }

        $out = [];
        AmazonListingStatus::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$out, $inventoryMap) {
                foreach ($rows as $row) {
                    $mapped = $this->mapProduct($row, $inventoryMap);
                    if ($mapped !== null) {
                        $out[] = $mapped;
                    }
                }
            });

        return $out;
    }

    /**
     * @param  array<string, int>  $inventoryMap
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}|null
     */
    protected function mapProduct(AmazonListingStatus $row, array $inventoryMap = []): ?array
    {
        $sku = trim((string) $row->sku);
        if ($sku === '' || ! AmazonListingStatusHelper::isLinked($row)) {
            return null;
        }

        $productId = AmazonListingStatusHelper::resolveProductId($row);
        $value = AmazonListingStatusHelper::valueArray($row);
        $upper = strtoupper($sku);
        $inventory = $inventoryMap[$upper] ?? null;
        if ($inventory === null && isset($value['quantity']) && is_numeric($value['quantity'])) {
            $inventory = (int) $value['quantity'];
        }

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => AmazonListingStatusHelper::resolveListingState($row),
            'inventory' => $inventory,
            'title' => isset($value['title']) ? (string) $value['title'] : null,
            'price' => isset($value['price']) && is_numeric($value['price']) ? (float) $value['price'] : null,
        ];
    }
}
