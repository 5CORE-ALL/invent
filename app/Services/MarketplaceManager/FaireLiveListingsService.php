<?php

namespace App\Services\MarketplaceManager;

use App\Models\FaireMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for Faire listings UI.
 */
final class FaireLiveListingsService
{
    public const CACHE_KEY = 'mm.faire.live_listings.v2';

    public const CACHE_TTL_SECONDS = 7200;

    public const STATUS_TYPES = [
        'active',
        'inactive',
    ];

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function all(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            try {
                Cache::forget(self::CACHE_KEY);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        try {
            $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
                return $this->fetchFromLocal();
            });

            return MarketplacePortalInactiveCount::applyToLiveRows('faire', is_array($rows) ? $rows : []);
        } catch (\Throwable $e) {
            Log::warning('FaireLiveListingsService: cache unavailable', ['error' => $e->getMessage()]);

            return MarketplacePortalInactiveCount::applyToLiveRows('faire', $this->fetchFromLocal());
        }
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>|null
     */
    public function peekCached(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);

            return is_array($cached) ? MarketplacePortalInactiveCount::applyToLiveRows('faire', $cached) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function clearCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
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

        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($this->all() as $row) {
            if (in_array($row['product_id'], $ids, true) || in_array($row['sku'], $ids, true)) {
                $out[$row['product_id']] = $row;
                $out[$row['sku']] = $row;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    protected function fetchFromLocal(): array
    {
        // Faire products API cache only (faire_metric) — no sheet / pricing_prices fallback.
        if (! Schema::hasTable('faire_metric')) {
            return [];
        }

        $out = [];
        FaireMetric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$out) {
                foreach ($chunk as $row) {
                    $sku = trim((string) $row->sku);
                    if ($sku === '') {
                        continue;
                    }
                    $inv = $row->inventory !== null ? (int) $row->inventory : null;
                    $out[] = [
                        'product_id' => trim((string) ($row->product_id ?: $sku)),
                        'sku' => $sku,
                        'state' => 'active',
                        'inventory' => $inv,
                        'title' => $row->product_name,
                        'price' => $row->price !== null ? (float) $row->price : null,
                    ];
                }
            });

        return $out;
    }
}
