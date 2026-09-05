<?php

namespace App\Support;

use Carbon\Carbon;

class DarL30Metrics
{
    public const TARGET = 25;

    public const WINDOW_DAYS = 30;

    public static function percent(int $submittedDays): int
    {
        if ($submittedDays < 0) {
            $submittedDays = 0;
        }

        return (int) round(($submittedDays / self::TARGET) * 100);
    }

    /**
     * Colour band for the Task Summary DAR cell.
     * >90% pink, 80–90% green, otherwise red.
     */
    public static function band(int $percent): string
    {
        if ($percent > 90) {
            return 'high';
        }
        if ($percent >= 80) {
            return 'mid';
        }

        return 'low';
    }

    /**
     * @param  list<string>  $submittedYmd
     * @return list<array{date: string, label: string, submitted: int, weekend: bool}>
     */
    public static function series(array $submittedYmd, ?Carbon $end = null): array
    {
        $end = ($end ?? Carbon::now())->copy()->startOfDay();
        $set = [];
        foreach ($submittedYmd as $ymd) {
            $ymd = trim((string) $ymd);
            if ($ymd !== '') {
                $set[$ymd] = true;
            }
        }

        $out = [];
        $cursor = $end->copy()->subDays(self::WINDOW_DAYS - 1);
        for ($i = 0; $i < self::WINDOW_DAYS; $i++) {
            $ymd = $cursor->format('Y-m-d');
            $out[] = [
                'date' => $ymd,
                'label' => $cursor->format('d M'),
                'submitted' => isset($set[$ymd]) ? 1 : 0,
                'weekend' => $cursor->isWeekend(),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @param  list<string>  $submittedYmd
     * @return array{dar_l30_count: int, dar_l30_target: int, dar_l30_pct: int, dar_l30_band: string, dar_l30_series: list<array{date: string, label: string, submitted: int, weekend: bool}>}
     */
    public static function forUser(array $submittedYmd, ?Carbon $end = null): array
    {
        $series = self::series($submittedYmd, $end);
        $count = 0;
        foreach ($series as $pt) {
            $count += (int) $pt['submitted'];
        }
        $pct = self::percent($count);

        return [
            'dar_l30_count' => $count,
            'dar_l30_target' => self::TARGET,
            'dar_l30_pct' => $pct,
            'dar_l30_band' => self::band($pct),
            'dar_l30_series' => $series,
        ];
    }

    /**
     * Compact 30-day submitted / missed strip for the Task Summary cell.
     *
     * @param  list<array{submitted?: int|bool, weekend?: bool}>  $series
     */
    public static function sparklineSvg(array $series, int $width = 72, int $height = 16): string
    {
        $n = count($series);
        if ($n === 0) {
            return '';
        }

        $gap = 0.6;
        $barW = $width / $n;
        $inner = max(0.6, $barW - $gap);
        $parts = [
            '<svg class="task-summary-dar-spark" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" aria-hidden="true" focusable="false">',
        ];

        foreach ($series as $i => $pt) {
            $submitted = ! empty($pt['submitted']);
            $weekend = ! empty($pt['weekend']);
            $bh = $submitted ? $height : max(2, (int) round($height * 0.18));
            $x = $i * $barW + ($gap / 2);
            $y = $height - $bh;
            if ($submitted) {
                $fill = '#0f766e';
            } elseif ($weekend) {
                $fill = '#e5e7eb';
            } else {
                $fill = '#fca5a5';
            }
            $parts[] = '<rect x="'.round($x, 2).'" y="'.$y.'" width="'.round($inner, 2).'" height="'.$bh.'" rx="0.6" fill="'.$fill.'"></rect>';
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }
}
