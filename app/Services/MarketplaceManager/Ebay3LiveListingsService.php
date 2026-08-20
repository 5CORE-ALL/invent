<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay3Metric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for eBay 3 listings UI.
 * Stock/price/title from ebay_3_metrics; product_id = item_id.
 */
final class Ebay3LiveListingsService
{
    public const CACHE_KEY = 'mm.ebay3.live_listings.v2';

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
                Cache::forget('mm.ebay3.live_listings.v1');
            } catch (\Throwable $e) {
                // ignore
            }
            $this->syncPortalStatusFromEbay();
        }

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
                return $this->fetchFromLocal();
            });
        } catch (\Throwable $e) {
            Log::warning('Ebay3LiveListingsService: cache unavailable', ['error' => $e->getMessage()]);

            return $this->fetchFromLocal();
        }
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
            Cache::forget(self::CACHE_KEY);
            Cache::forget('mm.ebay3.live_listings.v1');
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
        if (! Schema::hasTable('ebay_3_metrics')) {
            return [];
        }

        $hasStatus = Schema::hasColumn('ebay_3_metrics', 'listing_status');
        $hasReason = Schema::hasColumn('ebay_3_metrics', 'inactive_reason');
        $out = [];
        Ebay3Metric::query()
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
        $lock = Cache::lock('mm.ebay3.portal_status_sync', 400);
        if (! $lock->get()) {
            return;
        }
        try {
            $result = app(EbayPortalListingStatusSync::class)->sync(3);
            if (! ($result['ok'] ?? false)) {
                Log::warning('Ebay3LiveListingsService: portal status sync failed', $result);
            }
        } catch (\Throwable $e) {
            Log::warning('Ebay3LiveListingsService: portal status sync exception', ['error' => $e->getMessage()]);
        } finally {
            optional($lock)->release();
        }
    }
}
