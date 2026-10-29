<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Why the last Alibaba S PRC push of a SKU failed, so /alibaba-analytics can show it on the red X.
 * Kept in the cache (no migration): the push status column only says "error".
 */
class AlibabaPushError
{
    private const TTL_DAYS = 14;

    private static function key(string $sku): string
    {
        return 'alibaba-push-error:'.md5(strtoupper(trim($sku)));
    }

    public static function remember(string $sku, string $message): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }
        try {
            Cache::put(self::key($sku), [
                'message' => self::friendly($message),
                'at' => now()->toDateTimeString(),
            ], now()->addDays(self::TTL_DAYS));
        } catch (Throwable $e) {
            // The reason is a convenience, never fail a push over it.
        }
    }

    public static function forget(string $sku): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }
        try {
            Cache::forget(self::key($sku));
        } catch (Throwable $e) {
            // ignore
        }
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{message:string,at:?string}> keyed by upper-case trimmed SKU
     */
    public static function many(array $skus): array
    {
        $keys = [];
        foreach ($skus as $sku) {
            $norm = strtoupper(trim((string) $sku));
            if ($norm !== '') {
                $keys[self::key($norm)] = $norm;
            }
        }
        if ($keys === []) {
            return [];
        }

        $out = [];
        try {
            foreach (Cache::many(array_keys($keys)) as $key => $value) {
                if (is_array($value) && ! empty($value['message'])) {
                    $out[$keys[$key]] = [
                        'message' => (string) $value['message'],
                        'at' => isset($value['at']) ? (string) $value['at'] : null,
                    ];
                }
            }
        } catch (Throwable $e) {
            return [];
        }

        return $out;
    }

    /**
     * A failure that retrying every few minutes cannot fix (dead product id, no category, SKU not on the listing).
     * The job retries these once a day, and the page does not count them as a blue triangle.
     */
    public static function isPermanentMessage(string $message): bool
    {
        foreach ([
            'Alibaba has no product for id',
            'No Alibaba product id',
            'Alibaba product id is missing',
            'category id is missing',
            'was not found on the Alibaba listing',
            'Alibaba SKU id is missing',
            'SKU prices could not be read',
        ] as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /** Throttling and "under review" clear on their own, so they are retried sooner than other errors. */
    public static function isTransientMessage(string $message): bool
    {
        foreach (['throttled', 'PUB_BIZCHECK_PRODUCT_IN_AUDITING', 'under review', 'Too Many Requests'] as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /** Minutes to wait before the job retries this SKU after a failed push. */
    public static function retryMinutes(string $sku, int $default): int
    {
        $hit = self::many([$sku])[strtoupper(trim($sku))] ?? null;
        if ($hit === null) {
            return $default;
        }
        if (self::isPermanentMessage($hit['message'])) {
            return max($default, 1440);
        }
        if (self::isTransientMessage($hit['message'])) {
            return min($default, 20);
        }

        return $default;
    }

    public static function isPermanent(string $sku): bool
    {
        $hit = self::many([$sku])[strtoupper(trim($sku))] ?? null;

        return $hit !== null && self::isPermanentMessage($hit['message']);
    }

    public static function friendly(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'Alibaba price push failed.';
        }
        if (stripos($message, 'SYS_ERROR') !== false) {
            return 'Alibaba system error (SYS_ERROR): Alibaba did not accept the price update for this listing. '
                .'It is retried automatically; if it keeps failing the listing has a property Alibaba rejects.';
        }

        return $message;
    }
}
