<?php

namespace App\Services\MarketplaceManager;

use App\Models\Temu2Metric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for Temu 2 listings UI (Reverb/AliExpress parity).
 */
class Temu2LiveListingsService
{
    private const CACHE_KEY = 'mm.temu2.live_listings.v3';

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

        if ($forceRefresh) {
            try {
                app(\App\Services\Temu2ApiService::class)->syncSkuListingStatuses();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Temu2LiveListingsService: status sync failed', [
                    'error' => $e->getMessage(),
                ]);
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

        if ($ids === [] || ! Schema::hasTable('temu2_metrics')) {
            return [];
        }

        $rows = Temu2Metric::query()
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
        if (! Schema::hasTable('temu2_metrics')) {
            return [];
        }

        $out = [];
        Temu2Metric::query()
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
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason?: ?string}|null
     */
    protected function mapProduct(Temu2Metric $row): ?array
    {
        $sku = trim((string) $row->sku);
        if ($sku === '') {
            return null;
        }
        $productId = trim((string) ($row->goods_id ?? ''));
        if ($productId === '' || $productId === $sku) {
            $productId = $sku;
        }

        $title = trim((string) ($row->goods_summary ?? ''));
        if ($title === '') {
            $title = $sku;
        }

        $status = strtolower(trim((string) ($row->listing_status ?? '')));
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = 'other';
        }

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => $status,
            'inventory' => $row->quantity !== null ? (int) $row->quantity : null,
            'title' => $title,
            'price' => $row->base_price !== null ? (float) $row->base_price : null,
            'inactive_reason' => $status === 'inactive'
                ? (trim((string) ($row->inactive_reason ?? '')) ?: null)
                : null,
        ];
    }
}
