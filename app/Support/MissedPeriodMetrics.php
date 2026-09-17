<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

class MissedPeriodMetrics
{
    public const RECENT_DAYS = 30;

    public const LOOKBACK_DAYS = 60;

    /**
     * Bucket a miss timestamp into the last 30 days or the prior 30 (days 31–60).
     *
     * @return 'l30'|'p30'|null
     */
    public static function bucket(mixed $when, ?Carbon $now = null): ?string
    {
        $at = self::parseWhen($when);
        if ($at === null) {
            return null;
        }

        $now = $now ?? Carbon::now();
        $l30 = $now->copy()->subDays(self::RECENT_DAYS);
        $l60 = $now->copy()->subDays(self::LOOKBACK_DAYS);

        if ($at->lt($l60)) {
            return null;
        }

        return $at->greaterThanOrEqualTo($l30) ? 'l30' : 'p30';
    }

    public static function parseWhen(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof Carbon) {
                return $value;
            }
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value);
            }

            $parsed = Carbon::parse((string) $value);

            return $parsed ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
