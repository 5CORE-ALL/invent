<?php

namespace App\Support\Marketplace\Settle\Verification;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only verification of a campaign-ads report stored under the Pacific date key.
 *
 * The `--yesterday-only` ad commands exit 0 even when the report failed, and (Amazon) then write
 * zero-metric placeholder rows. So "rows exist" is not enough:
 *  - records_present : campaign rows exist under the date key (usual day has them).
 *  - ads_all_zero    : rows exist but total spend is 0 while the usual day has spend
 *                      (placeholder zeros from a failed report).
 *  - ads_low_spend   : spend far below the usual day (warning).
 *  - duplicates      : the same campaign stored twice for the date.
 *
 * spec: table, date_column, spend ({type: 'ebay_string', column} | {type: 'numeric', columns}), key.
 */
final class AdsReportVerifier implements Verifier
{
    /** @param  array<string, mixed>  $spec */
    public function __construct(private array $spec) {}

    public function verify(string $date, VerificationContext $context): VerificationResult
    {
        $table = (string) $this->spec['table'];
        $dateCol = (string) $this->spec['date_column'];
        try {
            if (! Schema::hasTable($table)) {
                return VerificationResult::error('table_missing', "Table {$table} does not exist, so its ads cannot be verified.");
            }
            if (! Schema::hasColumn($table, $dateCol)) {
                return VerificationResult::error('column_missing', "Column {$table}.{$dateCol} does not exist.");
            }
            $spendSql = $this->spendSql($table);
            if ($spendSql === null) {
                return VerificationResult::error('column_missing', "No spend column found in {$table}.");
            }

            $today = $this->stats($table, $dateCol, $spendSql, $date);
            $rows = [];
            $spends = [];
            $n = max(1, (int) config('settle.thresholds.baseline_days', 7));
            for ($i = 1; $i <= $n; $i++) {
                $s = $this->stats($table, $dateCol, $spendSql, Carbon::parse($date)->subDays($i)->toDateString());
                if ($s['rows'] > 0) {
                    $rows[] = $s['rows'];
                    $spends[] = $s['spend'];
                }
            }
            $minDays = (int) config('settle.thresholds.baseline_min_days', 3);
            $hasBaseline = count($rows) >= $minDays;
            $medianSpend = $this->median($spends);

            $checks = [];
            $metrics = ['rows' => $today['rows'], 'spend' => round($today['spend'], 2), 'baseline_days_with_rows' => count($rows), 'baseline_median_spend' => round($medianSpend, 2)];

            if ($today['rows'] === 0) {
                $checks[] = VerificationResult::check(
                    'records_present',
                    $hasBaseline ? 'fail' : 'warn',
                    $hasBaseline
                        ? "No campaign rows saved under {$dateCol}={$date} (usual days have ~".round($this->median($rows)).' rows) — the report did not land.'
                        : "No campaign rows under {$dateCol}={$date}; not enough history to call it a failure."
                );

                return VerificationResult::fromChecks($checks, $metrics);
            }

            $checks[] = VerificationResult::check('records_present', 'pass', $today['rows'].' campaign rows saved for the date.');

            if ($today['spend'] <= 0 && $hasBaseline && $medianSpend > 0) {
                $checks[] = VerificationResult::check(
                    'ads_all_zero',
                    'fail',
                    sprintf('%d campaign rows but total spend is 0 while a usual day is ~%.2f — likely zero placeholder rows from a failed report.', $today['rows'], $medianSpend),
                    ['rows' => $today['rows'], 'median_spend' => $medianSpend]
                );
            } elseif ($hasBaseline
                && $medianSpend >= (float) config('settle.thresholds.ads_low_spend_min_median', 20)
                && $today['spend'] < $medianSpend * (float) config('settle.thresholds.ads_low_spend_ratio', 0.1)) {
                $checks[] = VerificationResult::check(
                    'ads_low_spend',
                    'warn',
                    sprintf('Spend %.2f is far below a usual day (~%.2f); the report may be partial.', $today['spend'], $medianSpend)
                );
            } else {
                $checks[] = VerificationResult::check('ads_spend', 'pass', sprintf('Total spend %.2f (usual ~%.2f).', $today['spend'], $medianSpend));
            }

            $dup = $this->duplicates($table, $dateCol, $date);
            if ($dup !== null) {
                $metrics['duplicate_rows'] = $dup;
                $pct = ($dup / max(1, $today['rows'])) * 100;
                $checks[] = $dup === 0
                    ? VerificationResult::check('duplicates', 'pass', 'No duplicate campaign rows.')
                    : VerificationResult::check(
                        'duplicates',
                        $pct >= (float) config('settle.thresholds.duplicate_fail_pct', 5) ? 'fail' : 'warn',
                        sprintf('%d duplicate campaign row(s) for the date (%.1f%%) — spend may be double counted.', $dup, $pct)
                    );
            }

            return VerificationResult::fromChecks($checks, $metrics);
        } catch (\Throwable $e) {
            return VerificationResult::error('verifier_exception', 'Ads verification could not run: '.mb_substr($e->getMessage(), 0, 300));
        }
    }

    private function spendSql(string $table): ?string
    {
        $spec = $this->spec['spend'];
        $g = DB::getQueryGrammar();
        if (($spec['type'] ?? '') === 'ebay_string') {
            if (! Schema::hasColumn($table, $spec['column'])) {
                return null;
            }
            $c = $g->wrap($spec['column']);

            return "COALESCE(SUM(CAST(REPLACE(REPLACE({$c}, 'USD ', ''), ',', '') AS DECIMAL(15,2))), 0)";
        }
        $cols = array_values(array_filter($spec['columns'] ?? [], fn ($c) => Schema::hasColumn($table, $c)));
        if ($cols === []) {
            return null;
        }
        $parts = array_map(fn ($c) => $g->wrap($c), $cols);

        return 'COALESCE(SUM(COALESCE('.implode(', ', $parts).', 0)), 0)';
    }

    /** @return array{rows: int, spend: float} */
    private function stats(string $table, string $dateCol, string $spendSql, string $ymd): array
    {
        $r = DB::table($table)->where($dateCol, $ymd)->selectRaw("COUNT(*) as n, {$spendSql} as s")->first();

        return ['rows' => (int) ($r->n ?? 0), 'spend' => (float) ($r->s ?? 0)];
    }

    private function duplicates(string $table, string $dateCol, string $ymd): ?int
    {
        $cols = $this->spec['key'] ?? [];
        foreach ($cols as $c) {
            if (! Schema::hasColumn($table, $c)) {
                return null;
            }
        }
        if ($cols === []) {
            return null;
        }
        $sub = DB::table($table)->where($dateCol, $ymd)->select($cols)->selectRaw('COUNT(*) as c');
        foreach ($cols as $c) {
            $sub->whereNotNull($c);
        }
        $sub->groupBy($cols)->havingRaw('COUNT(*) > 1');
        $r = DB::query()->fromSub($sub, 'd')->selectRaw('COALESCE(SUM(c - 1), 0) as extra')->first();

        return (int) ($r->extra ?? 0);
    }

    /** @param  list<int|float>  $v */
    private function median(array $v): float
    {
        if ($v === []) {
            return 0.0;
        }
        sort($v);
        $n = count($v);
        $m = intdiv($n, 2);

        return $n % 2 ? (float) $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
    }
}
