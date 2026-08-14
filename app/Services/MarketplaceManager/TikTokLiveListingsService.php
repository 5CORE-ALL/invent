<?php

namespace App\Services\MarketplaceManager;

use App\Models\TikTokProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for TikTok Shop (1) listings UI (Reverb/TopDawg parity).
 * Link map + stock cache from tiktok_products (sku, product_id, sku_id, stock).
 */
class TikTokLiveListingsService
{
    protected string $cacheKey = 'mm.tiktok.live_listings.v1';

    /** @var class-string<Model> */
    protected string $productModel = TikTokProduct::class;

    protected string $table = 'tiktok_products';

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

        $rows = $this->fetchFromLocal();
        Cache::put($this->cacheKey, $rows, now()->addMinutes(10));

        return $rows;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float}>|null
     */
    public function peekCached(): ?array
    {
        $cached = Cache::get($this->cacheKey);

        return is_array($cached) ? $cached : null;
    }

    public function clearCache(): void
    {
        try {
            Cache::forget($this->cacheKey);
        } catch (\Throwable $e) {
            // ignore
        }
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

        if ($ids === [] || ! Schema::hasTable($this->table)) {
            return [];
        }

        $rows = ($this->productModel)::query()
            ->where(function ($q) use ($ids) {
                $q->whereIn('product_id', $ids)->orWhereIn('sku', $ids);
            })
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $parsed = $this->mapProduct($row);
            if ($parsed === null) {
                continue;
            }
            $out[$parsed['sku']] = $parsed;
            $out[strtoupper($parsed['sku'])] = $parsed;
            if (! empty($parsed['sku_id'])) {
                $out[(string) $parsed['sku_id']] = $parsed;
            }
            if (! isset($out[$parsed['product_id']])) {
                $out[$parsed['product_id']] = $parsed;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    protected function fetchFromLocal(): array
    {
        if (! Schema::hasTable($this->table)) {
            return [];
        }

        $out = [];
        ($this->productModel)::query()
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
     * @return array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float}|null
     */
    protected function mapProduct(Model $row): ?array
    {
        $sku = trim((string) ($row->sku ?? ''));
        if ($sku === '') {
            return null;
        }
        $productId = trim((string) ($row->product_id ?? ''));
        if ($productId === '') {
            return null;
        }
        $skuId = trim((string) ($row->sku_id ?? ''));

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'sku_id' => $skuId !== '' ? $skuId : null,
            'state' => 'active',
            'inventory' => $row->stock !== null ? (int) $row->stock : null,
            'title' => null,
            'price' => $row->price !== null ? (float) $row->price : null,
        ];
    }
}
