<?php

namespace App\Support;

/**
 * Portfolio daily series for the Yesterday badges on /amazon-ads/all.
 * Green = improved, red = worse, gray = unchanged or no prior day.
 * Spend and ads sales improve when they rise. ACOS improves when it falls.
 */
final class AmazonAdsYesterdayBadgeHistory
{
    /**
     * @param  array<string, array{spend: float, sales: float}>  $byDate
     * @return list<array{date: string, spend: float, sales: float, acos: float}>
     */
    public static function series(array $byDate, string $start, string $end): array
    {
        if ($start === '' || $end === '' || $start > $end) {
            return [];
        }

        $out = [];
        $cursor = new \DateTimeImmutable($start);
        $last = new \DateTimeImmutable($end);
        while ($cursor <= $last) {
            $day = $cursor->format('Y-m-d');
            $row = $byDate[$day] ?? ['spend' => 0.0, 'sales' => 0.0];
            $spend = round((float) ($row['spend'] ?? 0), 2);
            $sales = round((float) ($row['sales'] ?? 0), 2);
            $out[] = [
                'date' => $day,
                'spend' => $spend,
                'sales' => $sales,
                'acos' => self::acos($spend, $sales),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $out;
    }

    public static function acos(float $spend, float $sales): float
    {
        if ($sales > 0) {
            $v = ($spend / $sales) * 100;

            return is_finite($v) ? (float) round($v, 0) : 0.0;
        }
        if ($spend > 0) {
            return 100.0;
        }

        return 0.0;
    }

    public static function tone(string $metric, ?float $current, ?float $previous): string
    {
        if ($current === null || $previous === null) {
            return 'gray';
        }
        $eps = $metric === 'yacos' ? 0.05 : 0.005;
        if (abs($current - $previous) <= $eps) {
            return 'gray';
        }
        $up = $current > $previous;
        $improved = $metric === 'yacos' ? ! $up : $up;

        return $improved ? 'green' : 'red';
    }

    /**
     * @param  list<array{date: string, spend: float, sales: float, acos: float}>  $series
     * @return array{yspend: string, ysales: string, yacos: string}
     */
    public static function tones(array $series): array
    {
        $prev = count($series) >= 2 ? $series[count($series) - 2] : null;
        $cur = $series !== [] ? $series[count($series) - 1] : null;

        return [
            'yspend' => self::tone('yspend', isset($cur['spend']) ? (float) $cur['spend'] : null, isset($prev['spend']) ? (float) $prev['spend'] : null),
            'ysales' => self::tone('ysales', isset($cur['sales']) ? (float) $cur['sales'] : null, isset($prev['sales']) ? (float) $prev['sales'] : null),
            'yacos' => self::tone('yacos', isset($cur['acos']) ? (float) $cur['acos'] : null, isset($prev['acos']) ? (float) $prev['acos'] : null),
        ];
    }

    /**
     * @param  list<array{date: string, spend: float, sales: float, acos: float}>  $series
     * @return list<array<string, float|string>>
     */
    public static function chartPoints(array $series, string $metric): array
    {
        $key = match ($metric) {
            'ysales' => 'ysales',
            'yacos' => 'yacos',
            default => 'yspend',
        };
        $field = match ($metric) {
            'ysales' => 'sales',
            'yacos' => 'acos',
            default => 'spend',
        };
        $points = [];
        foreach ($series as $row) {
            $points[] = [
                'date' => $row['date'],
                $key => $row[$field],
            ];
        }

        return $points;
    }

    /**
     * @param  array<string, array{spend: float, sales: float}>  $into
     * @param  array<string, array{spend: float, sales: float}>  $add
     * @return array<string, array{spend: float, sales: float}>
     */
    public static function mergeDaily(array $into, array $add): array
    {
        foreach ($add as $day => $row) {
            if (! isset($into[$day])) {
                $into[$day] = ['spend' => 0.0, 'sales' => 0.0];
            }
            $into[$day]['spend'] += (float) ($row['spend'] ?? 0);
            $into[$day]['sales'] += (float) ($row['sales'] ?? 0);
        }

        return $into;
    }
}
