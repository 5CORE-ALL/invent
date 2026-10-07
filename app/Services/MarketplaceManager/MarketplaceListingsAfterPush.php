<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * After a successful inventory push: the listings page and /map-issues read the
 * channel's warm listings cache, so it must show the pushed qty right away.
 * DB-backed caches are dropped (they rebuild from the rows the push just wrote).
 * API-backed caches (Reverb, AliExpress) are patched in place, because dropping
 * them also drops the Active/Inactive split until the next full crawl.
 */
final class MarketplaceListingsAfterPush
{
    /** Channels whose listings cache is built from a remote API, not our DB. */
    private const API_BACKED = [
        'aliexpress' => [AliexpressLiveListingsService::CACHE_KEY, AliexpressLiveListingsService::CACHE_TTL_SECONDS],
        'reverb' => [ReverbLiveListingsService::CACHE_KEY, ReverbLiveListingsService::CACHE_TTL_SECONDS],
    ];

    /**
     * @param  array<string, int>  $pushedQty  SKU => qty written to the marketplace
     */
    public static function refresh(string $mmChannel, array $pushedQty = []): void
    {
        $mm = strtolower(trim($mmChannel));
        if ($mm === '') {
            return;
        }

        try {
            if (isset(self::API_BACKED[$mm])) {
                [$key, $ttl] = self::API_BACKED[$mm];
                self::patchCache($key, $ttl, $pushedQty);
            } else {
                app(MarketplaceMismatchInventoryPass::class)->forgetLiveRows($mm);
            }
            Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.$mm);
        } catch (\Throwable $e) {
            Log::warning('MarketplaceListingsAfterPush: cache refresh failed', [
                'channel' => $mm,
                'error' => $e->getMessage(),
            ]);
        }

        MappingChannelCounts::markChannelStale($mm);
    }

    /**
     * Rows the marketplace accepted, so local stock never records a qty the
     * marketplace rejected (that hid the failure and the SKU was never retried).
     * Uses the API's updated_skus list when present; with no list, a batch with
     * any failure cannot say which rows landed, so none are kept.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $result
     * @return list<array<string, mixed>>
     */
    public static function acceptedRows(array $rows, array $result): array
    {
        if (isset($result['updated_skus']) && is_array($result['updated_skus'])) {
            $ok = [];
            foreach ($result['updated_skus'] as $sku) {
                $ok[strtoupper(trim((string) $sku))] = true;
            }

            return array_values(array_filter($rows, static function ($row) use ($ok) {
                $sku = strtoupper(trim((string) ($row['sku'] ?? $row['sku_code'] ?? '')));

                return $sku !== '' && isset($ok[$sku]);
            }));
        }

        return (int) ($result['failed'] ?? 0) > 0 ? [] : array_values($rows);
    }

    /**
     * Write pushed qty onto cached rows whose SKU matches (hyphen/space normalized).
     *
     * @param  array<int, mixed>  $rows
     * @param  array<string, int>  $pushedQty
     * @return array<int, mixed>
     */
    public static function patchRows(array $rows, array $pushedQty): array
    {
        $byNorm = [];
        foreach ($pushedQty as $sku => $qty) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($norm !== '') {
                $byNorm[$norm] = (int) $qty;
            }
        }
        if ($byNorm === []) {
            return $rows;
        }

        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''));
            if ($norm !== '' && array_key_exists($norm, $byNorm)) {
                $rows[$i]['inventory'] = $byNorm[$norm];
            }
        }

        return $rows;
    }

    /**
     * Patching must not extend the cache life, or the API catalog (and its
     * Active/Inactive status) would never be re-read while pushes keep coming.
     *
     * @param  array<string, int>  $pushedQty
     */
    private static function patchCache(string $key, int $ttl, array $pushedQty): void
    {
        $expiresKey = $key.'.expires_at';
        $cached = Cache::get($key);
        if (! is_array($cached) || $cached === []) {
            Cache::forget($expiresKey);

            return;
        }
        if ($pushedQty === []) {
            return;
        }

        $expiresAt = (int) Cache::get($expiresKey, 0);
        $now = time();
        if ($expiresAt <= 0) {
            $expiresAt = $now + $ttl;
            Cache::put($expiresKey, $expiresAt, $ttl);
        }
        $remaining = $expiresAt - $now;
        if ($remaining <= 0) {
            Cache::forget($key);
            Cache::forget($expiresKey);

            return;
        }

        Cache::put($key, self::patchRows($cached, $pushedQty), $remaining);
    }
}
