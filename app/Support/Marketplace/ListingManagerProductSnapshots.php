<?php

namespace App\Support\Marketplace;

use App\Http\Controllers\MarketPlace\ListingManagerController;
use App\Models\AmazonListingRaw;
use App\Models\ListingManagerProductSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Builds and stores the Listing Manager product-modal payload so the modal opens from the
 * database instead of waiting on Amazon / Main Store / marketplace API calls.
 */
class ListingManagerProductSnapshots
{
    /** Snapshots older than this are served immediately and refreshed after the response. */
    public const STALE_HOURS = 12;

    /** Scheduled refresh rebuilds snapshots older than this. */
    public const SCHEDULED_REFRESH_HOURS = 24;

    private const REFRESH_LOCK_PREFIX = 'lm.product_snapshot.refresh.';

    /**
     * Build the live payload for a SKU and store it. Returns the stored payload, or null on failure.
     */
    public static function refresh(string $sku): ?array
    {
        $sku = trim($sku);
        if ($sku === '' || ! ListingManagerProductSnapshot::tableReady()) {
            return null;
        }

        $started = microtime(true);
        try {
            $payload = app(ListingManagerController::class)->buildProductPayload($sku);
        } catch (\Throwable $e) {
            Log::warning('ListingManager snapshot build failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            self::recordFailure($sku, $e->getMessage());

            return null;
        }

        self::store($sku, $payload, (int) round((microtime(true) - $started) * 1000));

        return $payload;
    }

    public static function store(string $sku, array $payload, ?int $buildMs = null): void
    {
        if (! ListingManagerProductSnapshot::tableReady()) {
            return;
        }

        try {
            ListingManagerProductSnapshot::query()->updateOrCreate(
                ['sku' => trim($sku)],
                [
                    'payload' => $payload,
                    'build_ms' => $buildMs,
                    'built_at' => now(),
                    'last_error' => null,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('ListingManager snapshot store failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    private static function recordFailure(string $sku, string $message): void
    {
        try {
            $row = ListingManagerProductSnapshot::query()->firstOrNew(['sku' => trim($sku)]);
            $row->last_error = mb_substr($message, 0, 2000);
            $row->save();
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Rebuild the snapshot after the current HTTP response has been sent, deduped per SKU so a
     * burst of saves / opens does not trigger repeated marketplace calls.
     */
    public static function refreshAfterResponse(string $sku): void
    {
        $sku = trim($sku);
        if ($sku === '' || ! ListingManagerProductSnapshot::tableReady()) {
            return;
        }

        $lockKey = self::REFRESH_LOCK_PREFIX.md5($sku);
        try {
            if (! Cache::add($lockKey, 1, now()->addSeconds(90))) {
                return;
            }
        } catch (\Throwable) {
            // cache unavailable: still refresh
        }

        dispatch(function () use ($sku, $lockKey) {
            @set_time_limit(120);
            try {
                self::refresh($sku);
            } finally {
                try {
                    Cache::forget($lockKey);
                } catch (\Throwable) {
                    // ignore
                }
            }
        })->afterResponse();
    }

    /**
     * Drop the stored snapshot so the next open rebuilds live (used when a rebuild cannot be trusted).
     */
    public static function forget(string $sku): void
    {
        if (! ListingManagerProductSnapshot::tableReady()) {
            return;
        }
        try {
            ListingManagerProductSnapshot::query()->where('sku', trim($sku))->delete();
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * SKUs that appear in the Listing Manager grid (Amazon origin), excluding parent rows.
     *
     * @return list<string>
     */
    public static function candidateSkus(): array
    {
        if (! Schema::hasTable('amazon_listings_raw')) {
            return [];
        }

        return AmazonListingRaw::query()
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->where('seller_sku', 'not like', 'PARENT %')
            ->orderBy('seller_sku')
            ->distinct()
            ->pluck('seller_sku')
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Meta block added to every product payload returned to the UI.
     */
    public static function meta(?ListingManagerProductSnapshot $snapshot, bool $fromStore, bool $refreshing = false): array
    {
        return [
            'from_store' => $fromStore,
            'built_at' => $snapshot?->built_at?->toDateTimeString(),
            'built_at_human' => $snapshot?->built_at?->diffForHumans(),
            'build_ms' => $snapshot?->build_ms,
            'refreshing' => $refreshing,
            'stale_hours' => self::STALE_HOURS,
        ];
    }
}
