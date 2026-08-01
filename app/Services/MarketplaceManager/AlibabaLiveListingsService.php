<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaMetric;
use App\Models\AlibabaPricingPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for Alibaba listings UI (local metrics/pricing parity with Temu).
 */
class AlibabaLiveListingsService
{
    private const CACHE_KEY = 'mm.alibaba.live_listings.v1';

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

        if ($ids === [] || ! Schema::hasTable('alibaba_metrics')) {
            return [];
        }

        $stockBySku = $this->stockMapForSkus([]);
        $rows = AlibabaMetric::query()
            ->where(function ($q) use ($ids) {
                $q->whereIn('product_id', $ids)->orWhereIn('sku', $ids);
            })
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $parsed = $this->mapProduct($row, $stockBySku);
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
        if (! Schema::hasTable('alibaba_metrics')) {
            return [];
        }

        $stockBySku = $this->stockMapForSkus([]);
        $out = [];
        AlibabaMetric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$out, $stockBySku) {
                foreach ($rows as $row) {
                    $mapped = $this->mapProduct($row, $stockBySku);
                    if ($mapped !== null) {
                        $out[] = $mapped;
                    }
                }
            });

        return $out;
    }

    /**
     * @return array<string, int>
     */
    protected function stockMapForSkus(array $skus): array
    {
        if (! Schema::hasTable('alibaba_pricing_prices')) {
            return [];
        }

        $query = AlibabaPricingPrice::query()->whereNotNull('ab_stock');
        if ($skus !== []) {
            $keys = [];
            foreach ($skus as $sku) {
                $trim = trim((string) $sku);
                if ($trim !== '') {
                    $keys[] = $trim;
                    $keys[] = strtoupper($trim);
                }
            }
            $query->whereIn('sku', array_values(array_unique($keys)));
        }

        $map = [];
        $query->get(['sku', 'ab_stock'])->each(function (AlibabaPricingPrice $row) use (&$map) {
            $key = strtoupper(trim((string) $row->sku));
            if ($key !== '' && $row->ab_stock !== null) {
                $map[$key] = (int) $row->ab_stock;
            }
        });

        return $map;
    }

    /**
     * @param  array<string, int>  $stockBySku
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}|null
     */
    protected function mapProduct(AlibabaMetric $row, array $stockBySku): ?array
    {
        $sku = trim((string) $row->sku);
        if ($sku === '') {
            return null;
        }
        $productId = trim((string) ($row->product_id ?? ''));
        if ($productId === '' || $productId === $sku) {
            return null;
        }

        $title = trim((string) ($row->product_name ?? ''));
        if ($title === '') {
            $title = $sku;
        }

        $stockKey = strtoupper($sku);

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => 'active',
            'inventory' => $stockBySku[$stockKey] ?? null,
            'title' => $title,
            'price' => $row->price !== null ? (float) $row->price : null,
        ];
    }
}
