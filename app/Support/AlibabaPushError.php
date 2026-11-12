<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Why the last Alibaba S PRC push of a SKU failed, so /alibaba-analytics can show it on the red X.
 * Kept in the cache and in alibaba_pricing_prices.sprice_error (the push status column only says "error"); the
 * database copy is the one that survives a cache clear.
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
        $friendly = self::friendly($message);
        try {
            Cache::put(self::key($sku), [
                'message' => $friendly,
                'at' => now()->toDateTimeString(),
            ], now()->addDays(self::TTL_DAYS));
        } catch (Throwable $e) {
            // The reason is a convenience, never fail a push over it.
        }
        // The database copy survives cache clears and expiry, so a dead listing stays recognised as one.
        self::writeDb($sku, $friendly);
    }

    private static function hasDbColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = Schema::hasTable('alibaba_pricing_prices') && Schema::hasColumn('alibaba_pricing_prices', 'sprice_error');
            } catch (Throwable $e) {
                $has = false;
            }
        }

        return $has;
    }

    private static function writeDb(string $sku, ?string $message): void
    {
        if (! self::hasDbColumn()) {
            return;
        }
        try {
            DB::table('alibaba_pricing_prices')
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                ->update(['sprice_error' => $message === null ? null : mb_substr($message, 0, 2000)]);
        } catch (Throwable $e) {
            // ignore
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
        self::writeDb($sku, null);
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
            $out = [];
        }

        // Cache miss (cleared, expired, other store): fall back to the reason saved on the pricing row.
        $missing = array_values(array_diff($keys, array_keys($out)));
        if ($missing !== [] && self::hasDbColumn()) {
            try {
                $rows = DB::table('alibaba_pricing_prices')
                    ->whereNotNull('sprice_error')
                    ->where('sprice_error', '!=', '')
                    ->where(function ($q) use ($missing) {
                        foreach ($missing as $norm) {
                            $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$norm]);
                        }
                    })
                    ->get(['sku', 'sprice_error', 'sprice_status_at']);
                foreach ($rows as $r) {
                    $out[strtoupper(trim((string) $r->sku))] = [
                        'message' => (string) $r->sprice_error,
                        'at' => $r->sprice_status_at !== null ? (string) $r->sprice_status_at : null,
                    ];
                }
            } catch (Throwable $e) {
                // ignore
            }
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
            // AlibabaSprcDilAutoPushService::extremeChangeReason: the listing needs a person, not another retry.
            'not pushed, check the listing',
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
