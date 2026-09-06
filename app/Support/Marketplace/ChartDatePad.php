<?php

namespace App\Support\Marketplace;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Keep every Pacific calendar day on a metric chart through yesterday.
 * Days with no snapshot / no sales stay on the axis as $0.
 */
class ChartDatePad
{
    /**
     * @param  list<array{date: string, value: float}>  $chartData
     * @return list<array{date: string, value: float}>
     */
    public static function fillGapsThroughYesterday(
        array $chartData,
        int $days = 30,
        string $tz = 'America/Los_Angeles',
        ?DateTimeInterface $now = null
    ): array {
        $now = $now ? Carbon::parse($now)->timezone($tz) : Carbon::now($tz);
        $end = $now->copy()->subDay()->startOfDay();
        $span = $days > 0 ? $days : 30;
        $windowStart = $end->copy()->subDays($span - 1);

        $byYmd = [];
        foreach ($chartData as $pt) {
            $label = trim((string) ($pt['date'] ?? ''));
            if ($label === '') {
                continue;
            }
            try {
                $parsed = Carbon::parse($label, $tz)->startOfDay();
                if ($parsed->gt($now)) {
                    $parsed->subYear();
                }
            } catch (\Throwable $e) {
                continue;
            }
            $byYmd[$parsed->toDateString()] = (float) ($pt['value'] ?? 0);
        }

        if ($byYmd === []) {
            $start = $windowStart;
        } else {
            ksort($byYmd);
            $first = Carbon::parse(array_key_first($byYmd), $tz)->startOfDay();
            $start = $first->lt($windowStart) ? $windowStart->copy() : $first;
        }

        $out = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $ymd = $cursor->toDateString();
            $out[] = [
                'date' => $cursor->format('M d'),
                'value' => round($byYmd[$ymd] ?? 0.0, 2),
            ];
            $cursor->addDay();
        }

        return $out;
    }
}
