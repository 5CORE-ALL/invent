<?php

namespace App\Support\Marketplace\Settle\Verification;

use Carbon\Carbon;

/**
 * Date helpers for "what is a usual day" comparisons.
 *
 * Two series are used together:
 *  - recent  : the previous N Pacific days (trend),
 *  - weekday : the same weekday in the previous W weeks (weekly pattern; weekends of B2B-style
 *              channels are legitimately near zero).
 * The expected value is the LOWER of the two medians (see OrdersWindowVerifier), so a quiet
 * weekend is not judged against weekday volume, while a normally busy weekday still is.
 */
final class Baseline
{
    private const PACIFIC = 'America/Los_Angeles';

    /** @return list<string> D-1 .. D-$days */
    public static function recentDates(string $date, int $days): array
    {
        $out = [];
        for ($i = 1; $i <= max(1, $days); $i++) {
            $out[] = Carbon::parse($date, self::PACIFIC)->subDays($i)->toDateString();
        }

        return $out;
    }

    /** @return list<string> D-7, D-14, ... D-7*$weeks */
    public static function weekdayDates(string $date, int $weeks): array
    {
        $out = [];
        for ($i = 1; $i <= max(0, $weeks); $i++) {
            $out[] = Carbon::parse($date, self::PACIFIC)->subWeeks($i)->toDateString();
        }

        return $out;
    }

    /** @param  list<int|float>  $values */
    public static function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /**
     * Expected value from the two series. With enough same-weekday history it is the lower of the
     * two medians; otherwise only the recent median is used.
     *
     * @param  list<int|float>  $recent
     * @param  list<int|float>  $weekday  only weekdays the table really covers
     * @return array{expected: float, median_recent: float, median_weekday: float, weekday_used: bool}
     */
    public static function expected(array $recent, array $weekday, int $minWeekdayWeeks): array
    {
        $mr = self::median($recent);
        $mw = self::median($weekday);
        $used = count($weekday) >= max(1, $minWeekdayWeeks);

        return [
            'expected' => $used ? min($mr, $mw) : $mr,
            'median_recent' => $mr,
            'median_weekday' => $mw,
            'weekday_used' => $used,
        ];
    }
}
