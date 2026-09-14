<?php

namespace App\Support\Marketplace;

use App\Models\ChannelMasterSummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Company inventory $ (inv / Inv@SP / Inv@LP) cannot collapse 20%+ in one
 * day and bounce back. Those days are incomplete Amazon/Shopify snapshots.
 */
class ChannelMasterInventoryGuard
{
    /** Drop larger than this vs the trusted day is a glitch, not stock movement. */
    public const COLLAPSE_RATIO = 0.80;

    /**
     * @return list<string>
     */
    public static function metricKeys(): array
    {
        return ['inventory_value_amazon', 'inv_at_sp', 'inv_at_lp'];
    }

    public static function isInventoryChartMetric(string $metric): bool
    {
        return in_array($metric, ['inventory', 'inv_at_sp', 'inv_at_lp'], true);
    }

    public static function isCollapsed(float $candidate, float $baseline): bool
    {
        if ($baseline <= 0) {
            return false;
        }
        if ($candidate <= 0) {
            return true;
        }

        return $candidate < $baseline * self::COLLAPSE_RATIO;
    }

    /**
     * Isolated V-dip: a day much lower than both neighbors.
     */
    public static function isIsolatedDip(float $prev, float $current, float $next): bool
    {
        if ($prev <= 0 || $next <= 0 || $current <= 0) {
            return $current <= 0 && ($prev > 0 || $next > 0);
        }

        return $current < min($prev, $next) * self::COLLAPSE_RATIO;
    }

    /**
     * Carry the previous trusted inventory across collapsed / V-dip days.
     * Uses immediate neighbors so a single crash (e.g. $1.78M → $972k → $1.53M)
     * is always replaced, even if earlier days already moved the baseline.
     *
     * @param  list<array{date?: string, value: float|int|string|null}>  $chartData
     * @return list<array{date?: string, value: float}>
     */
    public static function repairChartPoints(array $chartData): array
    {
        $chartData = array_values($chartData);
        $n = count($chartData);
        if ($n < 2) {
            return $chartData;
        }

        // Pass 1: isolated V vs immediate neighbors.
        for ($i = 1; $i < $n - 1; $i++) {
            $prev = (float) ($chartData[$i - 1]['value'] ?? 0);
            $cur = (float) ($chartData[$i]['value'] ?? 0);
            $next = (float) ($chartData[$i + 1]['value'] ?? 0);
            if (self::isIsolatedDip($prev, $cur, $next)) {
                $chartData[$i]['value'] = round($prev > 0 ? $prev : $next, 2);
            }
        }

        // Pass 2: any remaining 20%+ crash vs the last trusted day.
        $trusted = (float) ($chartData[0]['value'] ?? 0);
        for ($i = 1; $i < $n; $i++) {
            $cur = (float) ($chartData[$i]['value'] ?? 0);
            if ($trusted > 0 && self::isCollapsed($cur, $trusted)) {
                $chartData[$i]['value'] = round($trusted, 2);
                continue;
            }
            if ($cur > 0) {
                $trusted = $cur;
            }
        }

        return $chartData;
    }

    /**
     * @param  list<float|null>  $series  oldest → newest
     * @return list<float|null>
     */
    public static function repairValueSeries(array $series): array
    {
        $points = [];
        foreach ($series as $i => $value) {
            $points[] = ['date' => (string) $i, 'value' => (float) ($value ?? 0)];
        }
        $repaired = self::repairChartPoints($points);
        $out = [];
        foreach ($repaired as $i => $pt) {
            $out[$i] = $series[$i] === null && (float) ($pt['value'] ?? 0) <= 0
                ? null
                : (float) $pt['value'];
        }

        return $out;
    }

    /**
     * Replace collapsed company-inventory fields with yesterday's trusted totals.
     *
     * @param  array<string, mixed>  $candidate
     * @param  array<string, float>  $trusted
     * @return array<string, mixed>
     */
    public static function stabilizeSummary(array $candidate, array $trusted): array
    {
        foreach (self::metricKeys() as $key) {
            $base = (float) ($trusted[$key] ?? 0);
            $cur = (float) ($candidate[$key] ?? 0);
            if ($base > 0 && self::isCollapsed($cur, $base)) {
                $candidate[$key] = round($base, 2);
            }
        }

        return $candidate;
    }

    /**
     * Max company-inventory $ stored on any channel for a Pacific snapshot day.
     *
     * @return array<string, float>
     */
    public static function trustedTotalsOnDate(string $ymd): array
    {
        $out = array_fill_keys(self::metricKeys(), 0.0);
        try {
            $rows = ChannelMasterSummary::query()
                ->whereDate('snapshot_date', $ymd)
                ->get(['summary_data']);
            foreach ($rows as $row) {
                $sd = ChannelMasterSummary::decodeSummaryData($row->summary_data ?? []);
                foreach (self::metricKeys() as $key) {
                    $out[$key] = max($out[$key], (float) ($sd[$key] ?? 0));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Inventory guard trusted totals failed: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * Inv@SP / Inv@LP were not stored on older days. Fill from that day's inv
     * using the live SP/inv (or LP/inv) ratio so the history graph has points.
     *
     * @param  iterable<int, ChannelMasterSummary>  $rows
     */
    public static function backfillMissingOnRows(iterable $rows, float $spRatio, float $lpRatio, bool $persist = true): int
    {
        $filled = 0;
        $spRatio = $spRatio > 0 ? $spRatio : 1.0;
        $lpRatio = $lpRatio > 0 ? $lpRatio : 1.0;

        foreach ($rows as $row) {
            $sd = ChannelMasterSummary::decodeSummaryData($row->summary_data ?? []);
            $inv = (float) ($sd['inventory_value_amazon'] ?? 0);
            if ($inv <= 0) {
                continue;
            }

            $expectedSp = $inv * $spRatio;
            $expectedLp = $inv * $lpRatio;
            $sp = (float) ($sd['inv_at_sp'] ?? 0);
            $lp = (float) ($sd['inv_at_lp'] ?? 0);
            $changed = false;

            if ($sp <= 0 || self::isCollapsed($sp, $expectedSp)) {
                $sd['inv_at_sp'] = round($expectedSp, 2);
                $changed = true;
            }
            if ($lp <= 0 || self::isCollapsed($lp, $expectedLp)) {
                $sd['inv_at_lp'] = round($expectedLp, 2);
                $changed = true;
            }
            if (! $changed) {
                continue;
            }

            $row->summary_data = $sd;
            if ($persist) {
                try {
                    $row->save();
                } catch (\Throwable $e) {
                    Log::warning('Inventory guard backfill save failed: '.$e->getMessage());
                }
            }
            $filled++;
        }

        return $filled;
    }

    public static function backfillRecentMissing(float $spRatio, float $lpRatio, int $lookbackDays = 45): int
    {
        try {
            $from = now('America/Los_Angeles')->subDays($lookbackDays)->toDateString();
            $rows = ChannelMasterSummary::query()
                ->where('snapshot_date', '>=', $from)
                ->orderBy('snapshot_date')
                ->get();

            return self::backfillMissingOnRows($rows, $spRatio, $lpRatio, true);
        } catch (\Throwable $e) {
            Log::warning('Inventory guard recent backfill failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Rewrite isolated inventory V-dips on the given rows (in memory + DB).
     *
     * @param  iterable<int, ChannelMasterSummary>  $rows
     */
    public static function healIsolatedDipsOnRows(iterable $rows, bool $persist = true): int
    {
        $healed = 0;
        $list = [];
        foreach ($rows as $row) {
            $list[] = $row;
        }
        if (count($list) < 3) {
            return 0;
        }

        $byDate = [];
        foreach ($list as $row) {
            $date = Carbon::parse($row->snapshot_date, 'America/Los_Angeles')->toDateString();
            $byDate[$date][] = $row;
        }
        $dates = array_keys($byDate);
        sort($dates);
        if (count($dates) < 3) {
            return 0;
        }

        foreach (self::metricKeys() as $key) {
            $maxByDate = [];
            $holderByDate = [];
            foreach ($dates as $date) {
                $max = 0.0;
                $holder = null;
                foreach ($byDate[$date] as $row) {
                    $sd = ChannelMasterSummary::decodeSummaryData($row->summary_data ?? []);
                    $v = (float) ($sd[$key] ?? 0);
                    if ($v >= $max) {
                        $max = $v;
                        $holder = $row;
                    }
                }
                $maxByDate[$date] = $max;
                $holderByDate[$date] = $holder;
            }

            for ($i = 1; $i < count($dates) - 1; $i++) {
                $date = $dates[$i];
                $prev = $maxByDate[$dates[$i - 1]] ?? 0.0;
                $cur = $maxByDate[$date] ?? 0.0;
                $next = $maxByDate[$dates[$i + 1]] ?? 0.0;
                if (! self::isIsolatedDip($prev, $cur, $next)) {
                    continue;
                }
                $row = $holderByDate[$date] ?? null;
                if (! $row) {
                    continue;
                }
                $sd = ChannelMasterSummary::decodeSummaryData($row->summary_data ?? []);
                $sd[$key] = round($prev, 2);
                $row->summary_data = $sd;
                if ($persist) {
                    try {
                        $row->save();
                    } catch (\Throwable $e) {
                        Log::warning('Inventory guard heal save failed: '.$e->getMessage());
                    }
                }
                $maxByDate[$date] = $prev;
                $healed++;
            }
        }

        return $healed;
    }

    /**
     * Rewrite isolated inventory V-dips in recent daily snapshots so history
     * and dots stop showing an impossible one-day collapse.
     */
    public static function healRecentIsolatedDips(int $lookbackDays = 45): int
    {
        static $ran = false;
        if ($ran) {
            return 0;
        }
        $ran = true;

        try {
            $from = now('America/Los_Angeles')->subDays($lookbackDays)->toDateString();
            $rows = ChannelMasterSummary::query()
                ->where('snapshot_date', '>=', $from)
                ->orderBy('snapshot_date')
                ->get(['id', 'channel', 'snapshot_date', 'summary_data']);

            return self::healIsolatedDipsOnRows($rows, true);
        } catch (\Throwable $e) {
            Log::warning('Inventory guard heal failed: '.$e->getMessage());
        }

        return 0;
    }
}
