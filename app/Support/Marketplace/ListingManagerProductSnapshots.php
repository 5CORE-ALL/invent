<?php

namespace App\Support\Marketplace;

use App\Http\Controllers\MarketPlace\ListingManagerController;
use App\Models\AmazonListingRaw;
use App\Models\ListingManagerProductSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\PhpExecutableFinder;

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
            self::releaseLock(self::lockKey($sku));

            return null;
        }

        self::store($sku, $payload, (int) round((microtime(true) - $started) * 1000));
        self::releaseLock(self::lockKey($sku));

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

        $lockKey = self::lockKey($sku);
        try {
            if (! Cache::add($lockKey, 1, now()->addSeconds(90))) {
                return;
            }
        } catch (\Throwable) {
            // cache unavailable: still refresh
        }

        // Preferred: a detached CLI process. Behind Apache/nginx the "after response" hook still
        // keeps the HTTP connection open until the rebuild (30-60s of marketplace calls) finishes,
        // which made saves / pushes time out in the browser.
        if (self::spawnDetachedRefresh($sku)) {
            return;
        }

        dispatch(function () use ($sku, $lockKey) {
            @set_time_limit(120);
            try {
                self::refresh($sku);
            } finally {
                self::releaseLock($lockKey);
            }
        })->afterResponse();
    }

    private static function lockKey(string $sku): string
    {
        return self::REFRESH_LOCK_PREFIX.md5(trim($sku));
    }

    private static function releaseLock(string $lockKey): void
    {
        try {
            Cache::forget($lockKey);
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Start `artisan listing-manager:snapshot-refresh --sku=…` in the background and return at once.
     */
    private static function spawnDetachedRefresh(string $sku): bool
    {
        if (PHP_OS_FAMILY === 'Windows' || ! function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('exec', $disabled, true)) {
            return false;
        }
        $php = self::phpCliBinary();
        if ($php === null) {
            return false;
        }

        $command = sprintf(
            'nohup %s %s listing-manager:snapshot-refresh --sku=%s --sleep-ms=0 >/dev/null 2>&1 &',
            escapeshellarg($php),
            escapeshellarg(base_path('artisan')),
            escapeshellarg($sku)
        );

        try {
            $exitCode = 1;
            exec($command, $output, $exitCode);
        } catch (\Throwable $e) {
            Log::warning('ListingManager snapshot: could not spawn background refresh', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }

        return $exitCode === 0;
    }

    /**
     * CLI php matching the running version (PHP_BINARY is php-fpm / apache in web requests).
     */
    private static function phpCliBinary(): ?string
    {
        static $resolved = false;
        static $binary = null;
        if ($resolved) {
            return $binary;
        }
        $resolved = true;

        $candidates = [];
        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            $candidates[] = PHP_BINARY;
        }
        $bindir = rtrim((string) PHP_BINDIR, '/');
        $version = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        foreach ([$bindir, '/usr/bin', '/usr/local/bin'] as $dir) {
            if ($dir !== '') {
                $candidates[] = $dir.'/php'.$version;
                $candidates[] = $dir.'/php';
            }
        }
        try {
            $found = (new PhpExecutableFinder())->find(false);
            if (is_string($found) && $found !== '') {
                $candidates[] = $found;
            }
        } catch (\Throwable) {
            // ignore
        }

        foreach (array_unique($candidates) as $candidate) {
            $base = basename($candidate);
            if (str_contains($base, 'fpm') || str_contains($base, 'cgi') || str_contains($base, 'apache') || str_contains($base, 'httpd')) {
                continue;
            }
            if (@is_file($candidate) && @is_executable($candidate)) {
                $binary = $candidate;
                break;
            }
        }

        return $binary;
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
