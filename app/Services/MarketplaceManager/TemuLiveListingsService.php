<?php

namespace App\Services\MarketplaceManager;

use App\Models\TemuMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for Temu listings UI (Reverb/AliExpress parity).
 */
class TemuLiveListingsService
{
    private const CACHE_KEY = 'mm.temu.live_listings.v1';

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

        if ($ids === [] || ! Schema::hasTable('temu_metrics')) {
            return [];
        }

        $rows = TemuMetric::query()
            ->where(function ($q) use ($ids) {
                $q->whereIn('goods_id', $ids)->orWhereIn('sku', $ids);
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
        }

        return $out;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    protected function fetchFromLocal(): array
    {
        if (! Schema::hasTable('temu_metrics')) {
            return [];
        }

        $out = [];
        TemuMetric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$out) {
                foreach ($rows as $row) {
                    $mapped = $this->mapProduct($row);
                    if ($mapped !== null) {
                        $out[] = $mapped;
                    }
                }
            });

        return $out;
    }

    /**
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}|null
     */
    protected function mapProduct(TemuMetric $row): ?array
    {
        $sku = trim((string) $row->sku);
        if ($sku === '') {
            return null;
        }
        $productId = trim((string) ($row->goods_id ?? ''));
        if ($productId === '' || $productId === $sku) {
            return null;
        }

        $title = trim((string) ($row->goods_summary ?? ''));
        if ($title === '') {
            $title = $sku;
        }

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => 'active',
            'inventory' => $row->quantity !== null ? (int) $row->quantity : null,
            'title' => $title,
            'price' => $row->base_price !== null ? (float) $row->base_price : null,
        ];
    }
}
