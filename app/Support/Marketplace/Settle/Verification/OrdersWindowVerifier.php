<?php

namespace App\Support\Marketplace\Settle\Verification;

use App\Models\SettleVerification;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only verification of an order / sales table for one Pacific day.
 *
 * It does not call any marketplace API. It looks at what the pull really stored:
 *  - records_present : rows exist for the Pacific day, compared with the usual day (median of the
 *                      previous baseline_days). Zero rows on a channel that normally sells = FAIL.
 *  - duplicates      : the same natural key stored more than once for the day.
 *  - zero_price      : rows whose price is NULL / 0 (suspicious zero values).
 *  - order_items     : orders without any line item (Amazon / eBay), items priced 0.
 *  - latest_record   : the newest record of the day is much earlier than usual (hint of a pull
 *                      that stopped part-way through pagination).
 *  - table_shrunk    : the whole table is much smaller than at the last verification (wiped /
 *                      pruned after a failed fetch).
 *
 * spec keys:
 *   table, time_column, time_type ('datetime'|'date'), tz (timezone the column is stored in),
 *   key (natural key columns), price_column, status_column + exclude_statuses,
 *   items {table, fk, price}, group_by, mode ('window'|'table'), table_shrink, pending_prices.
 */
final class OrdersWindowVerifier implements Verifier
{
    private const PACIFIC = 'America/Los_Angeles';

    /** @param  array<string, mixed>  $spec */
    public function __construct(private array $spec) {}

    public function verify(string $date, VerificationContext $context): VerificationResult
    {
        $table = (string) $this->spec['table'];
        try {
            if (! Schema::hasTable($table)) {
                return VerificationResult::error('table_missing', "Table {$table} does not exist, so its data cannot be verified.");
            }
            if (($this->spec['mode'] ?? 'window') === 'table') {
                return $this->verifyTableOnly($context);
            }
            $col = (string) $this->spec['time_column'];
            if (! Schema::hasColumn($table, $col)) {
                return VerificationResult::error('column_missing', "Column {$table}.{$col} does not exist, so its data cannot be verified.");
            }

            return $this->verifyWindow($date, $context);
        } catch (\Throwable $e) {
            return VerificationResult::error('verifier_exception', 'Verification could not run: '.mb_substr($e->getMessage(), 0, 300));
        }
    }

    private function verifyTableOnly(VerificationContext $ctx): VerificationResult
    {
        $table = (string) $this->spec['table'];
        $rows = (int) DB::table($table)->count();
        $checks = [];
        if ($rows === 0) {
            $checks[] = VerificationResult::check('table_not_empty', 'fail', "Table {$table} is empty (an empty API response can wipe it).");
        } else {
            $checks[] = VerificationResult::check('table_not_empty', 'pass', "Table {$table} holds {$rows} rows.");
        }
        $checks[] = $this->shrinkCheck($ctx, $rows);

        return VerificationResult::fromChecks(array_values(array_filter($checks)), ['table_rows' => $rows]);
    }

    private function verifyWindow(string $date, VerificationContext $ctx): VerificationResult
    {
        $table = (string) $this->spec['table'];
        $groupBy = $this->spec['group_by'] ?? null;
        if ($groupBy !== null && ! Schema::hasColumn($table, $groupBy)) {
            $groupBy = null;
        }
        $isDatetime = ($this->spec['time_type'] ?? 'datetime') === 'datetime';
        $n = max(1, (int) $this->t('baseline_days'));

        $today = $this->dayStats($date, $groupBy);
        $baseline = [];
        for ($i = 1; $i <= $n; $i++) {
            $baseline[$i] = $this->dayStats(Carbon::parse($date, self::PACIFIC)->subDays($i)->toDateString(), $groupBy);
        }

        $checks = [];
        $metrics = ['rows' => $today['total'], 'by_group' => $today['groups']];

        // --- records present / volume ------------------------------------------------------
        $groups = array_unique(array_merge(array_keys($today['groups']), ...array_map(fn ($b) => array_keys($b['groups']), $baseline)));
        sort($groups);
        $problems = [];
        $worst = 'pass';
        $summaryBits = [];
        foreach ($groups as $g) {
            $c = $today['groups'][$g] ?? 0;
            $series = array_map(fn ($b) => (int) ($b['groups'][$g] ?? 0), $baseline);
            $median = $this->median($series);
            $nonEmpty = count(array_filter($series));
            $label = $g === '_all' ? '' : ' ['.$g.']';
            $summaryBits[] = ($g === '_all' ? '' : $g.': ').$c.' (usual ~'.round($median).')';
            $metrics['baseline'][$g] = ['median' => $median, 'non_empty_days' => $nonEmpty, 'series' => $series];

            if ($c === 0) {
                if ($median >= $this->t('zero_fail_min_median') && $nonEmpty >= $this->t('baseline_min_days')) {
                    $problems[] = ['fail', "0 records for the Pacific day{$label}, usual day has ~".round($median)];
                } else {
                    $problems[] = ['warn', "0 records for the Pacific day{$label}; history is too small (median ".round($median).') to call it a failure'];
                }
            } elseif ($median >= $this->t('low_volume_fail_min_median') && $c < $median * $this->t('low_volume_fail_ratio')) {
                $problems[] = ['fail', "Only {$c} records{$label}, usual day has ~".round($median)];
            } elseif ($median >= $this->t('low_volume_warn_min_median') && $c < $median * $this->t('low_volume_warn_ratio')) {
                $problems[] = ['warn', "Only {$c} records{$label}, usual day has ~".round($median)];
            }
        }
        if ($problems === []) {
            $checks[] = VerificationResult::check('records_present', 'pass', 'Records for the Pacific day: '.implode(', ', $summaryBits));
        } else {
            foreach ($problems as [$st, $msg]) {
                if ($st === 'fail') {
                    $worst = 'fail';
                } elseif ($worst !== 'fail') {
                    $worst = 'warn';
                }
            }
            $checks[] = VerificationResult::check('records_present', $worst, implode('; ', array_column($problems, 1)), ['groups' => $today['groups']]);
        }

        // --- latest record of the day (possible stopped pagination) -------------------------
        if ($isDatetime && $groupBy === null && $today['total'] > 0) {
            $checks[] = $this->latestRecordCheck($today, $baseline);
        }

        // --- duplicates ---------------------------------------------------------------------
        if ($today['total'] > 0) {
            $checks[] = $this->duplicatesCheck($date, $today['total'], $metrics);
            // --- zero prices ----------------------------------------------------------------
            $checks[] = $this->zeroPriceCheck($date, $today['total'], $ctx, $metrics);
            // --- order items ----------------------------------------------------------------
            foreach ($this->itemChecks($date, $today['total'], $ctx, $metrics) as $c) {
                $checks[] = $c;
            }
        }

        // --- whole-table shrink (pruned / rebuilt tables) ------------------------------------
        if (! empty($this->spec['table_shrink'])) {
            $rows = (int) DB::table($table)->count();
            $metrics['table_rows'] = $rows;
            $checks[] = $this->shrinkCheck($ctx, $rows);
        }

        return VerificationResult::fromChecks(array_values(array_filter($checks)), $metrics);
    }

    /**
     * @return array{total: int, groups: array<string, int>, latest: ?Carbon}
     */
    private function dayStats(string $ymd, ?string $groupBy): array
    {
        $q = $this->windowQuery($ymd);
        $table = (string) $this->spec['table'];
        $time = $this->wrap((string) $this->spec['time_column']);

        if ($groupBy !== null) {
            $g = $this->wrap($groupBy);
            $rows = $q->selectRaw("{$g} as g, COUNT(*) as c")->groupBy($table.'.'.$groupBy)->get();
            $groups = [];
            foreach ($rows as $r) {
                $groups[(string) ($r->g ?? '')] = (int) $r->c;
            }

            return ['total' => array_sum($groups), 'groups' => $groups, 'latest' => null];
        }

        $row = $q->selectRaw("COUNT(*) as c, MAX({$time}) as m")->first();
        $c = (int) ($row->c ?? 0);
        $latest = null;
        if ($c > 0 && ! empty($row->m) && ($this->spec['time_type'] ?? 'datetime') === 'datetime') {
            $latest = Carbon::parse((string) $row->m, (string) ($this->spec['tz'] ?? 'UTC'))->setTimezone(self::PACIFIC);
        }

        return ['total' => $c, 'groups' => ['_all' => $c], 'latest' => $latest];
    }

    private function windowQuery(string $ymd): Builder
    {
        $table = (string) $this->spec['table'];
        $col = $table.'.'.$this->spec['time_column'];
        $q = DB::table($table);

        if (($this->spec['time_type'] ?? 'datetime') === 'date') {
            $q->whereDate($col, $ymd);
        } else {
            $tz = (string) ($this->spec['tz'] ?? 'UTC');
            $day = Carbon::parse($ymd, self::PACIFIC);
            $q->where($col, '>=', $day->copy()->startOfDay()->setTimezone($tz)->format('Y-m-d H:i:s'))
                ->where($col, '<=', $day->copy()->endOfDay()->setTimezone($tz)->format('Y-m-d H:i:s'));
        }

        $statusCol = $this->spec['status_column'] ?? null;
        $exclude = $this->spec['exclude_statuses'] ?? [];
        if ($statusCol && $exclude !== [] && Schema::hasColumn($table, $statusCol)) {
            $q->where(function ($w) use ($table, $statusCol, $exclude) {
                $w->whereNull($table.'.'.$statusCol)->orWhereNotIn($table.'.'.$statusCol, $exclude);
            });
        }

        return $q;
    }

    /**
     * @param  array{total: int, groups: array<string, int>, latest: ?Carbon}  $today
     * @param  array<int, array{total: int, groups: array<string, int>, latest: ?Carbon}>  $baseline
     * @return array{code: string, status: string, message: string, data: array<string, mixed>}
     */
    private function latestRecordCheck(array $today, array $baseline): array
    {
        $hours = [];
        $counts = [];
        foreach ($baseline as $b) {
            $counts[] = $b['total'];
            if ($b['latest'] !== null) {
                $hours[] = $b['latest']->hour + $b['latest']->minute / 60;
            }
        }
        $todayHour = $today['latest'] !== null ? $today['latest']->hour + $today['latest']->minute / 60 : null;
        $medianHour = $this->median($hours);
        $enough = count($hours) >= $this->t('baseline_min_days') && $this->median($counts) >= $this->t('low_volume_warn_min_median');

        if ($todayHour !== null && $enough && ($medianHour - $todayHour) > $this->t('latest_record_gap_hours')) {
            return VerificationResult::check(
                'latest_record',
                'warn',
                sprintf('Newest record of the day is at %s PT, usually ~%s PT (pull may have stopped part-way).', $this->hhmm($todayHour), $this->hhmm($medianHour)),
                ['today_hour' => $todayHour, 'median_hour' => $medianHour]
            );
        }

        return VerificationResult::check(
            'latest_record',
            'pass',
            $todayHour === null ? 'No record time available.' : 'Newest record of the day at '.$this->hhmm($todayHour).' PT.'
        );
    }

    /** @param  array<string, mixed>  $metrics */
    private function duplicatesCheck(string $date, int $total, array &$metrics): ?array
    {
        $table = (string) $this->spec['table'];
        $cols = $this->spec['key'] ?? [];
        if ($cols === []) {
            return null;
        }
        foreach ($cols as $c) {
            if (! Schema::hasColumn($table, $c)) {
                return VerificationResult::check('duplicates', 'limited', "Duplicate check skipped: column {$table}.{$c} not found.");
            }
        }

        $sub = $this->windowQuery($date);
        $qualified = array_map(fn ($c) => $table.'.'.$c, $cols);
        $sub->select($qualified)->selectRaw('COUNT(*) as c');
        foreach ($qualified as $qc) {
            $sub->whereNotNull($qc);
        }
        $sub->groupBy($qualified)->havingRaw('COUNT(*) > 1');

        $r = DB::query()->fromSub($sub, 'd')->selectRaw('COUNT(*) as grp, COALESCE(SUM(c - 1), 0) as extra')->first();
        $extra = (int) ($r->extra ?? 0);
        $groups = (int) ($r->grp ?? 0);
        $metrics['duplicate_rows'] = $extra;
        $metrics['duplicate_keys'] = $groups;
        $pct = $total > 0 ? ($extra / $total) * 100 : 0.0;

        if ($extra === 0) {
            return VerificationResult::check('duplicates', 'pass', 'No duplicate '.implode('+', $cols).' keys.');
        }
        $status = $pct >= $this->t('duplicate_fail_pct') ? 'fail' : 'warn';

        return VerificationResult::check(
            'duplicates',
            $status,
            sprintf('%d duplicate row(s) across %d key(s) of %s (%.1f%% of the day).', $extra, $groups, implode('+', $cols), $pct),
            ['extra_rows' => $extra, 'keys' => $groups, 'pct' => round($pct, 2)]
        );
    }

    /** @param  array<string, mixed>  $metrics */
    private function zeroPriceCheck(string $date, int $total, VerificationContext $ctx, array &$metrics): ?array
    {
        $table = (string) $this->spec['table'];
        $price = $this->spec['price_column'] ?? null;
        if ($price === null || ! Schema::hasColumn($table, $price) || $total < $this->t('price_min_rows')) {
            return null;
        }
        $zero = (int) $this->windowQuery($date)->where(function ($w) use ($table, $price) {
            $w->whereNull($table.'.'.$price)->orWhere($table.'.'.$price, '<=', 0);
        })->count();
        $pct = ($zero / $total) * 100;
        $metrics['zero_price_rows'] = $zero;
        $metrics['zero_price_pct'] = round($pct, 2);

        return $this->priceStatusCheck('zero_price', $zero, $pct, $ctx, "{$table}.{$price}");
    }

    /**
     * @return list<array{code: string, status: string, message: string, data: array<string, mixed>}>
     */
    private function itemChecks(string $date, int $total, VerificationContext $ctx, array &$metrics): array
    {
        $items = $this->spec['items'] ?? null;
        if ($items === null) {
            return [];
        }
        $table = (string) $this->spec['table'];
        $it = (string) $items['table'];
        $fk = (string) $items['fk'];
        if (! Schema::hasTable($it) || ! Schema::hasColumn($it, $fk)) {
            return [VerificationResult::check('order_items', 'limited', "Line-item check skipped: {$it}.{$fk} not found.")];
        }

        $checks = [];
        $noItems = (int) $this->windowQuery($date)->whereNotExists(function ($q) use ($it, $fk, $table) {
            $q->select(DB::raw(1))->from($it)->whereColumn($it.'.'.$fk, $table.'.id');
        })->count();
        $pct = $total > 0 ? ($noItems / $total) * 100 : 0.0;
        $metrics['orders_without_items'] = $noItems;
        if ($noItems === 0) {
            $checks[] = VerificationResult::check('order_items', 'pass', 'Every order has line items.');
        } elseif ($pct >= $this->t('no_items_fail_pct')) {
            $checks[] = VerificationResult::check('order_items', 'fail', sprintf('%d of %d orders have no line items (%.1f%%) — item fetch likely failed.', $noItems, $total, $pct), ['orders' => $noItems, 'pct' => round($pct, 2)]);
        } elseif ($pct >= $this->t('no_items_warn_pct')) {
            $checks[] = VerificationResult::check('order_items', 'warn', sprintf('%d of %d orders have no line items (%.1f%%).', $noItems, $total, $pct), ['orders' => $noItems, 'pct' => round($pct, 2)]);
        } else {
            $checks[] = VerificationResult::check('order_items', 'pass', sprintf('%d of %d orders without line items (%.1f%%, tolerated).', $noItems, $total, $pct));
        }

        $priceCol = $items['price'] ?? null;
        if ($priceCol !== null && Schema::hasColumn($it, $priceCol)) {
            $p = $this->wrapTable($it, $priceCol);
            $row = $this->windowQuery($date)
                ->join($it, $it.'.'.$fk, '=', $table.'.id')
                ->selectRaw("COUNT(*) as n, COALESCE(SUM(CASE WHEN {$p} IS NULL OR {$p} <= 0 THEN 1 ELSE 0 END), 0) as z")
                ->first();
            $n = (int) ($row->n ?? 0);
            $z = (int) ($row->z ?? 0);
            if ($n >= $this->t('price_min_rows')) {
                $zp = ($z / $n) * 100;
                $metrics['zero_price_items'] = $z;
                $metrics['zero_price_items_pct'] = round($zp, 2);
                $checks[] = $this->priceStatusCheck('zero_price_items', $z, $zp, $ctx, "{$it}.{$priceCol}");
            }
        }

        return $checks;
    }

    private function priceStatusCheck(string $code, int $zero, float $pct, VerificationContext $ctx, string $what): array
    {
        $pending = ! empty($this->spec['pending_prices']);
        $msg = sprintf('%d rows have a missing / zero price in %s (%.1f%%).', $zero, $what, $pct);
        if ($pending && ! $ctx->finalPassDone) {
            // Pending Amazon orders have no price until the late pass prices them.
            if ($pct >= $this->t('pending_price_warn_pct')) {
                return VerificationResult::check($code, 'warn', $msg.' Pending orders are priced by the late pass.', ['pct' => round($pct, 2)]);
            }

            return VerificationResult::check($code, 'pass', $msg.' (pending orders awaiting the late pass).');
        }
        if ($pct >= $this->t('zero_price_fail_pct')) {
            return VerificationResult::check($code, 'fail', $msg, ['pct' => round($pct, 2), 'rows' => $zero]);
        }
        if ($pct >= $this->t('zero_price_warn_pct')) {
            return VerificationResult::check($code, 'warn', $msg, ['pct' => round($pct, 2), 'rows' => $zero]);
        }

        return VerificationResult::check($code, 'pass', $msg);
    }

    private function shrinkCheck(VerificationContext $ctx, int $rows): ?array
    {
        $prev = null;
        try {
            $last = SettleVerification::query()
                ->where('task_key', $ctx->taskKey)
                ->orderByDesc('id')
                ->limit(10)
                ->get(['metrics']);
            foreach ($last as $l) {
                if (isset($l->metrics['table_rows'])) {
                    $prev = (int) $l->metrics['table_rows'];
                    break;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }
        if ($prev === null) {
            return VerificationResult::check('table_shrunk', 'pass', "No earlier size to compare with ({$rows} rows now).");
        }
        if ($prev >= $this->t('table_shrink_min_rows') && $rows < $prev * $this->t('table_shrink_min_ratio')) {
            return VerificationResult::check(
                'table_shrunk',
                'fail',
                "Table shrank from {$prev} to {$rows} rows since the last verification (wiped or pruned after a failed fetch?).",
                ['previous' => $prev, 'now' => $rows]
            );
        }

        return VerificationResult::check('table_shrunk', 'pass', "Table size {$rows} rows (was {$prev}).");
    }

    private function t(string $key): float
    {
        return (float) config('settle.thresholds.'.$key, 0);
    }

    private function wrap(string $column): string
    {
        return DB::getQueryGrammar()->wrap($this->spec['table'].'.'.$column);
    }

    private function wrapTable(string $table, string $column): string
    {
        return DB::getQueryGrammar()->wrap($table.'.'.$column);
    }

    /** @param  list<int|float>  $values */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    private function hhmm(float $hours): string
    {
        return sprintf('%02d:%02d', (int) floor($hours), (int) round(($hours - floor($hours)) * 60) % 60);
    }
}
