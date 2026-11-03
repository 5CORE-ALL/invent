<?php

namespace App\Support\Marketplace;

use App\Models\Ebay2Metric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Self-heal for "Variation SKU X was not found on eBay item N" on eBay 2 price pushes.
 *
 * That error means our (item_id, sku) row points at a listing whose variations no longer include the SKU
 * (variation removed / renamed / relisted under another item). Retrying can never work, so:
 *   1. the stale (item_id, sku) row is parked as INACTIVE, so the 10-minute catch-up and the push stop
 *      queueing it (EbayListingEnded already treats INACTIVE as "not pushable");
 *   2. a fresh pull of the live eBay 2 listings (app:fetch-ebay-two-metrics, the same report the daily job
 *      uses) is started in the background, at most once per throttle window. It re-creates or re-activates
 *      the row for whatever item the SKU is listed on now, so the SKU pushes again with the new item_id.
 *      SKUs that are not listed anywhere stay parked until they show up in the report.
 */
final class Ebay2VariationMissingHeal
{
    public const PARKED_REASON = 'Variation not on live eBay listing';

    /** Minimum hours between automatic listing pulls. */
    public const PULL_THROTTLE_HOURS = 6;

    private const PULL_CACHE_KEY = 'ebay2_listing_pull_requested';

    public static function looksVariationMissing(?string $message): bool
    {
        $blob = strtolower((string) $message);

        return str_contains($blob, 'variationskumissing')
            || (str_contains($blob, 'variation sku') && str_contains($blob, 'was not found on ebay item'));
    }

    public static function itemIdFromMessage(?string $message): ?string
    {
        if (preg_match('/ebay item\s+(\d{6,})/i', (string) $message, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Park the stale row and request a listing pull. Returns true when the error was a missing variation.
     */
    public static function handle(string $channel, string $sku, ?string $error): bool
    {
        if ($channel !== 'ebay2' || ! self::looksVariationMissing($error)) {
            return false;
        }

        $parked = self::parkStaleRow($sku, self::itemIdFromMessage($error));
        $pulled = self::requestListingPull();

        Log::info('eBay2 variation missing — parked stale listing row', [
            'sku' => $sku,
            'item_id' => self::itemIdFromMessage($error),
            'rows_parked' => $parked,
            'listing_pull_started' => $pulled,
        ]);

        return true;
    }

    public static function parkStaleRow(string $sku, ?string $itemId): int
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasColumn('ebay_2_metrics', 'listing_status')) {
            return 0;
        }

        try {
            $query = Ebay2Metric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
            if ($itemId !== null && $itemId !== '') {
                $query->where('item_id', $itemId);
            }
            $payload = ['listing_status' => 'INACTIVE'];
            if (Schema::hasColumn('ebay_2_metrics', 'inactive_reason')) {
                $payload['inactive_reason'] = self::PARKED_REASON;
            }

            // Only touch rows still shown as live: never overwrite a real ENDED status.
            return (int) $query
                ->where(function ($q) {
                    $q->whereNull('listing_status')->orWhereIn('listing_status', ['', 'ACTIVE']);
                })
                ->update($payload);
        } catch (\Throwable $e) {
            Log::warning('eBay2 variation missing: could not park stale row', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Start app:fetch-ebay-two-metrics in the background, at most once per PULL_THROTTLE_HOURS.
     */
    public static function requestListingPull(): bool
    {
        try {
            if (! Cache::add(self::PULL_CACHE_KEY, now()->toDateTimeString(), now()->addHours(self::PULL_THROTTLE_HOURS))) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        if (app()->environment('local')
            && ! filter_var(env('CHANNEL_PUSH_SPRICE_ALLOW_LOCAL', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/ebay2-listing-pull.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' app:fetch-ebay-two-metrics', 'r'));

                return true;
            }
            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' app:fetch-ebay-two-metrics >> '.escapeshellarg($log).' 2>&1 &';
            pclose(popen($cmd, 'r'));

            return true;
        } catch (\Throwable $e) {
            Log::warning('eBay2 listing pull spawn failed', ['error' => $e->getMessage()]);
            try {
                Cache::forget(self::PULL_CACHE_KEY);
            } catch (\Throwable) {
                // ignore
            }

            return false;
        }
    }
}
