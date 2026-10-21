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
