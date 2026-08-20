<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2Metric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for eBay 2 listings UI.
 * Stock/price/title from ebay_2_metrics; product_id = item_id.
 */
final class Ebay2LiveListingsService
{
    public const CACHE_KEY = 'mm.ebay2.live_listings.v2';

    public const CACHE_GEN_KEY = 'mm.ebay2.live_listings.gen';

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
        if (! $forceRefresh) {
            $cached = $this->peekCached();
            if ($cached !== null) {
                return $cached;
            }
        } else {
            $this->syncPortalStatusFromEbay();
        }

        $gen = 0;
        try {
            $gen = (int) Cache::get(self::CACHE_GEN_KEY, 0);
        } catch (\Throwable $e) {
            $gen = 0;
        }

        $rows = $this->fetchFromLocal();
        try {
            if ((int) Cache::get(self::CACHE_GEN_KEY, 0) === $gen && $this->rowsHavePortalStatus($rows)) {
                Cache::put(self::CACHE_KEY, $rows, self::CACHE_TTL_SECONDS);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $rows;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>|null
     */
    public function peekCached(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (! is_array($cached) || $cached === []) {
                return null;
            }
            if (! $this->rowsHavePortalStatus($cached)) {
                Cache::forget(self::CACHE_KEY);

                return null;
            }

            return $cached;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function clearCache(): void
    {
        try {
            Cache::increment(self::CACHE_GEN_KEY);
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param  array<int, string>  $productIds  eBay item_ids and/or seller SKUs
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

        $all = $this->all();
        $out = [];
        foreach ($all as $row) {
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
        if (! Schema::hasTable('ebay_2_metrics')) {
            return [];
        }

        $hasStatus = Schema::hasColumn('ebay_2_metrics', 'listing_status');
        $hasReason = Schema::hasColumn('ebay_2_metrics', 'inactive_reason');
        $out = [];
        Ebay2Metric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$out, $hasStatus, $hasReason) {
                foreach ($chunk as $row) {
                    $parsed = EbayLiveListingMapper::mapMetricRow($row, $hasStatus, $hasReason);
                    if ($parsed !== null) {
                        $out[] = $parsed;
                    }
                }
            });

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function rowsHavePortalStatus(array $rows): bool
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (in_array(strtolower((string) ($row['state'] ?? '')), ['active', 'inactive'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function syncPortalStatusFromEbay(): void
    {
        $lock = Cache::lock('mm.ebay2.portal_status_sync', 400);
        if (! $lock->get()) {
            return;
        }
        try {
            $result = app(EbayPortalListingStatusSync::class)->sync(2);
            if (! ($result['ok'] ?? false)) {
                Log::warning('Ebay2LiveListingsService: portal status sync failed', $result);
            }
        } catch (\Throwable $e) {
            Log::warning('Ebay2LiveListingsService: portal status sync exception', ['error' => $e->getMessage()]);
        } finally {
            optional($lock)->release();
        }
    }
}
