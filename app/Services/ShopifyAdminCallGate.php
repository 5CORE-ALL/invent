<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache-backed Shopify Admin REST throttle.
 *
 * Shopify restores ~2 calls/sec (leaky bucket). Multiple PHP workers (price
 * push, pull, inventory) used to fire at once and get "Exceeded 2 calls per
 * second". This gate stores the next allowed slot + last bucket in cache so
 * every caller waits instead of failing.
 */
class ShopifyAdminCallGate
{
    public const STORE_B2C = 'b2c';

    public const STORE_PLS = 'pls';

    /** Stay under Shopify's 2/sec restore rate. */
    private const BASE_GAP_SECONDS = 0.65;

    private const MAX_WAIT_SECONDS = 45.0;

    public static function acquire(string $store = self::STORE_B2C): void
    {
        $store = self::normalizeStore($store);
        $deadline = microtime(true) + self::MAX_WAIT_SECONDS;

        while (microtime(true) < $deadline) {
            $now = microtime(true);
            $waitUntil = max(
                (float) Cache::get(self::nextKey($store), 0),
                (float) Cache::get(self::cooldownKey($store), 0)
            );

            if ($waitUntil > $now + 0.01) {
                $sleep = min(4.0, $waitUntil - $now);
                usleep((int) ($sleep * 1_000_000));
                continue;
            }

            if (self::claimSlot($store, $now + self::gapSeconds($store))) {
                return;
            }

            usleep(80_000);
        }

        Log::warning('Shopify Admin call gate: waited max window, sending anyway', [
            'store' => $store,
        ]);
    }

    public static function record(?Response $response, string $store = self::STORE_B2C): void
    {
        if (! $response) {
            return;
        }

        $store = self::normalizeStore($store);
        $limitHeader = $response->header('X-Shopify-Shop-Api-Call-Limit')
            ?? $response->header('X-Shopify-API-Call-Limit');
        if (is_string($limitHeader) && preg_match('/^(\d+)\s*\/\s*(\d+)$/', trim($limitHeader), $m)) {
            Cache::put(self::bucketKey($store), [
                'used' => (int) $m[1],
                'limit' => max(1, (int) $m[2]),
            ], 60);
        }

        if ($response->status() === 429 || self::bodyLooksRateLimited($response)) {
            $retryAfter = $response->header('Retry-After');
            $wait = is_numeric($retryAfter) ? (float) $retryAfter : 2.5;
            $wait = min(30.0, max(1.5, $wait));
            Cache::put(self::cooldownKey($store), microtime(true) + $wait, 90);
            Log::info('Shopify Admin call gate: cooldown after rate limit', [
                'store' => $store,
                'wait_seconds' => $wait,
                'status' => $response->status(),
            ]);
        }
    }

    public static function isRateLimited(?Response $response): bool
    {
        if (! $response) {
            return false;
        }

        return $response->status() === 429 || self::bodyLooksRateLimited($response);
    }

    public static function bodyLooksRateLimited(?Response $response): bool
    {
        if (! $response) {
            return false;
        }
        $body = strtolower((string) $response->body());

        return str_contains($body, 'exceeded')
            && (str_contains($body, 'calls per second')
                || str_contains($body, 'rate limit')
                || str_contains($body, 'call limit'));
    }

    private static function claimSlot(string $store, float $nextAt): bool
    {
        $lock = null;
        try {
            $lock = Cache::lock(self::lockKey($store), 12);
            if (! $lock->get()) {
                return false;
            }
            $now = microtime(true);
            $blockedUntil = max(
                (float) Cache::get(self::nextKey($store), 0),
                (float) Cache::get(self::cooldownKey($store), 0)
            );
            if ($blockedUntil > $now + 0.02) {
                return false;
            }
            Cache::put(self::nextKey($store), $nextAt, 60);

            return true;
        } catch (\Throwable $e) {
            Cache::put(self::nextKey($store), $nextAt, 60);

            return true;
        } finally {
            try {
                $lock?->release();
            } catch (\Throwable $e) {
                // file cache / already released
            }
        }
    }

    private static function gapSeconds(string $store): float
    {
        $bucket = Cache::get(self::bucketKey($store));
        if (! is_array($bucket) || (int) ($bucket['limit'] ?? 0) <= 0) {
            return self::BASE_GAP_SECONDS;
        }
        $ratio = ((int) $bucket['used']) / max(1, (int) $bucket['limit']);
        if ($ratio >= 0.92) {
            return 2.8;
        }
        if ($ratio >= 0.80) {
            return 1.4;
        }
        if ($ratio >= 0.65) {
            return 0.9;
        }

        return self::BASE_GAP_SECONDS;
    }

    private static function normalizeStore(string $store): string
    {
        $store = strtolower(trim($store));

        return in_array($store, [self::STORE_PLS, 'prolightsounds'], true)
            ? self::STORE_PLS
            : self::STORE_B2C;
    }

    private static function nextKey(string $store): string
    {
        return 'shopify_admin_gate:'.$store.':next';
    }

    private static function cooldownKey(string $store): string
    {
        return 'shopify_admin_gate:'.$store.':cooldown';
    }

    private static function bucketKey(string $store): string
    {
        return 'shopify_admin_gate:'.$store.':bucket';
    }

    private static function lockKey(string $store): string
    {
        return 'shopify_admin_gate:'.$store.':lock';
    }
}
