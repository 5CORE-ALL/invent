<?php

namespace App\Support;

use App\Models\PenaltyCorrection;
use App\Models\PenaltyEntry;
use App\Models\PenaltyMonth;
use App\Models\PenaltyRule;
use App\Models\PenaltySlab;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Penalty points: rolling last-30-days numbers, slab pricing, monthly freeze and auto rules.
 *
 * Purpose: to evaluate mistakes and shortcomings in performance, not to deduct money.
 */
class Penalty
{
    public static function tablesReady(): bool
    {
        try {
            return Schema::hasTable('penalty_entries') && Schema::hasTable('penalty_slabs');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isSetter(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $setter = strtolower(trim((string) config('penalty.setter_email', 'president@5core.com')));

        return $setter !== '' && strtolower(trim((string) $user->email)) === $setter;
    }

    public static function today(): Carbon
    {
        return TaskBusinessTime::today();
    }

    public static function windowDays(): int
    {
        return max(1, (int) config('penalty.window_days', 30));
    }

    /** First day of the rolling window that ends on $asOf (inclusive). */
    public static function windowStart(?Carbon $asOf = null): Carbon
    {
        return ($asOf ?? self::today())->copy()->subDays(self::windowDays() - 1)->startOfDay();
    }

    // ---------- slabs ----------

    /**
     * Five slabs, lowest first: position, from, to (null = and above), rate (rupees per point), label.
     *
     * @return list<array{position: int, from: int, to: ?int, rate: float, label: string}>
     */
    public static function slabs(): array
    {
        $rows = PenaltySlab::query()->orderBy('position')->get();
        if ($rows->isEmpty()) {
            $rows = collect([[1, 5, 1], [2, 10, 2], [3, 15, 3], [4, 20, 4], [5, null, 5]])
                ->map(fn ($r) => new PenaltySlab(['position' => $r[0], 'max_points' => $r[1], 'rate' => $r[2]]));
        }

        $out = [];
        $from = 0;
        foreach ($rows as $row) {
            $to = $row->max_points === null ? null : (int) $row->max_points;
            $out[] = [
                'position' => (int) $row->position,
                'from' => $from,
                'to' => $to,
                'rate' => (float) $row->rate,
                'label' => $to === null ? $from.'+' : $from.'–'.$to,
            ];
            if ($to !== null) {
                $from = $to + 1;
            }
        }

        return $out;
    }

    /** @return array{position: int, from: int, to: ?int, rate: float, label: string} */
    public static function slabFor(int $points, ?array $slabs = null): array
    {
        $slabs ??= self::slabs();
        foreach ($slabs as $slab) {
            if ($slab['to'] === null || $points <= $slab['to']) {
                return $slab;
            }
        }

        return end($slabs);
    }

    public static function rateFor(int $points, ?array $slabs = null): float
    {
        return self::slabFor($points, $slabs)['rate'];
    }

    /** Rupees for a month total: every point is priced at the rate of the slab the total falls in. */
    public static function amountFor(int $points, ?array $slabs = null): float
    {
        return round($points * self::rateFor($points, $slabs), 2);
    }

    // ---------- rolling last-30-days numbers ----------

    public static function l30Points(int $userId, ?Carbon $asOf = null): int
    {
        $end = ($asOf ?? self::today())->copy();

        return (int) PenaltyEntry::query()
            ->where('user_id', $userId)
            ->whereDate('penalty_date', '>=', self::windowStart($end)->toDateString())
            ->whereDate('penalty_date', '<=', $end->toDateString())
            ->sum('points');
    }

    /** @return array<int, int> user id => points in the last 30 days */
    public static function l30PointsByUser(): array
    {
        return PenaltyEntry::query()
            ->whereDate('penalty_date', '>=', self::windowStart()->toDateString())
            ->whereDate('penalty_date', '<=', self::today()->toDateString())
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(points) as pts')
            ->pluck('pts', 'user_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Rolling last-30-days series for the history graphs: one point per day over $days days.
     *
     * @return list<array{date: string, value: float}>
     */
    public static function history(int $userId, int $days, string $metric): array
    {
        $days = max(7, min(180, $days));
        $win = self::windowDays();
        $end = self::today();
        $start = $end->copy()->subDays($days - 1);
        $from = $start->copy()->subDays($win - 1);

        $perDay = PenaltyEntry::query()
            ->where('user_id', $userId)
            ->whereDate('penalty_date', '>=', $from->toDateString())
            ->whereDate('penalty_date', '<=', $end->toDateString())
            ->groupBy('penalty_date')
            ->selectRaw('penalty_date, SUM(points) as pts')
            ->get()
            ->mapWithKeys(fn ($r) => [Carbon::parse($r->penalty_date)->toDateString() => (int) $r->pts]);

        $slabs = self::slabs();
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $sum = 0;
            for ($k = 0; $k < $win; $k++) {
                $sum += (int) ($perDay[$d->copy()->subDays($k)->toDateString()] ?? 0);
            }
            $out[] = [
                'date' => $d->toDateString(),
                'value' => $metric === 'amount' ? self::amountFor($sum, $slabs) : (float) $sum,
            ];
        }

        return $out;
    }

    // ---------- monthly freeze (1st of every month) ----------

    /**
     * Freeze the totals of one month (Y-m) and price them with the slabs. Safe to repeat: a closed
     * user/month is never changed. Returns how many users were newly closed.
     */
    public static function closeMonth(string $ym): int
    {
        if (preg_match('/^\d{4}-\d{2}$/', $ym) !== 1) {
            return 0;
        }

        $start = Carbon::createFromFormat('Y-m-d', $ym.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $totals = PenaltyEntry::query()
            ->whereDate('penalty_date', '>=', $start->toDateString())
            ->whereDate('penalty_date', '<=', $end->toDateString())
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(points) as pts')
            ->pluck('pts', 'user_id');

        if ($totals->isEmpty()) {
            return 0;
        }

        $already = PenaltyMonth::query()->where('month', $ym)->pluck('user_id')->flip();
        $slabs = self::slabs();
        $closed = 0;
        foreach ($totals as $userId => $pts) {
            if ($already->has($userId)) {
                continue;
            }
            $pts = (int) $pts;
            PenaltyMonth::query()->create([
                'user_id' => (int) $userId,
                'month' => $ym,
                'points' => $pts,
                'rate' => self::rateFor($pts, $slabs),
                'amount' => self::amountFor($pts, $slabs),
                'closed_at' => now(),
            ]);
            $closed++;
        }

        return $closed;
    }

    /** Close every finished month that still has open totals (catch-up in case the scheduler missed the 1st). */
    public static function ensureMonthsClosed(bool $force = false): void
    {
        $key = 'penalty_months_closed_'.self::today()->format('Y-m-d');
        if ($force) {
            Cache::forget($key);
        }
        Cache::remember($key, now()->addMinutes(10), function () {
            $current = self::today()->format('Y-m');
            $months = PenaltyEntry::query()
                ->selectRaw("DISTINCT DATE_FORMAT(penalty_date, '%Y-%m') as ym")
                ->pluck('ym');
            foreach ($months as $ym) {
                if ($ym && $ym < $current) {
                    self::closeMonth((string) $ym);
                }
            }

            return true;
        });
    }

    // ---------- auto rules ----------

    /** One-penalty-per-period key so a rule never penalises the same person twice in a day / week / month. */
    public static function periodKey(string $repeat, ?Carbon $day = null): string
    {
        $day ??= self::today();

        return match ($repeat) {
            'weekly' => $day->format('o-\WW'),
            'monthly' => $day->format('Y-m'),
            default => $day->format('Y-m-d'),
        };
    }

    public static function renderReason(PenaltyRule $rule, string $name, float $value): string
    {
        $metrics = ChatRules::metrics();

        return trim(strtr($rule->reason, [
            '{name}' => $name,
            '{value}' => ChatRules::formatNumber($value),
            '{threshold}' => ChatRules::formatNumber((float) $rule->threshold),
            '{metric}' => $metrics[$rule->metric]['label'] ?? $rule->metric,
            '{rule}' => $rule->name,
        ]));
    }

    /**
     * Apply one rule to the Task Summary numbers.
     *
     * @param  array<int, array<string, mixed>>  $rows  from ChatRules::memberRows()
     * @return array{matched: int, added: int, already: int, people: list<array<string, mixed>>}
     */
    public static function runRule(PenaltyRule $rule, array $rows, bool $dryRun = false): array
    {
        $activeIds = User::query()->where('is_active', true)->pluck('id')->flip();
        $key = self::periodKey($rule->repeat);
        $today = self::today()->toDateString();

        $matched = 0;
        $added = 0;
        $already = 0;
        $people = [];

        foreach ($rows as $id => $row) {
            $id = (int) $id;
            if (! $activeIds->has($id)) {
                continue;
            }
            $raw = $row[$rule->metric] ?? null;
            if ($raw === null || ! is_numeric($raw)) {
                continue;
            }
            $value = (float) $raw;
            if (! ChatRules::compare($value, (string) $rule->operator, (float) $rule->threshold)) {
                continue;
            }
            $matched++;
            $name = (string) ($row['team_member'] ?? '');

            $had = PenaltyEntry::query()
                ->where('rule_id', $rule->id)->where('user_id', $id)->where('period_key', $key)->exists();
            if ($had) {
                $already++;
                $status = 'already';
            } elseif ($dryRun) {
                $status = 'would_add';
            } else {
                try {
                    PenaltyEntry::query()->create([
                        'user_id' => $id,
                        'points' => (int) $rule->points,
                        'reason' => mb_substr(self::renderReason($rule, $name, $value), 0, 500),
                        'source' => 'auto',
                        'rule_id' => $rule->id,
                        'penalty_date' => $today,
                        'period_key' => $key,
                        'created_by' => 'rule: '.$rule->name,
                    ]);
                    $added++;
                    $status = 'added';
                } catch (\Throwable $e) {
                    // a parallel run already added it (unique key) — treat as already penalised
                    $already++;
                    $status = 'already';
                }
            }
            $people[] = ['user_id' => $id, 'name' => $name, 'value' => ChatRules::formatNumber($value), 'status' => $status];
        }

        if (! $dryRun) {
            $rule->forceFill(['last_run_at' => now(), 'last_added_count' => $added])->save();
        }

        return ['matched' => $matched, 'added' => $added, 'already' => $already, 'people' => $people];
    }

    // ---------- per-user detail for the window ----------

    /** Corrective-action tasks the user created / holds in the last 30 days (Task Manager "corrective action"). */
    public static function correctiveTasksL30(User $user): int
    {
        try {
            if (! Schema::hasColumn('tasks', 'is_corrective_action')) {
                return 0;
            }
            $email = strtolower(trim((string) $user->email));
            if ($email === '') {
                return 0;
            }

            return (int) \App\Models\Task::query()
                ->where('is_corrective_action', 1)
                ->where('created_at', '>=', self::windowStart()->toDateTimeString())
                ->where(function ($q) use ($email) {
                    $q->whereRaw('LOWER(TRIM(assign_to)) = ?', [$email])
                        ->orWhereRaw('LOWER(assign_to) LIKE ?', [$email.',%'])
                        ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email])
                        ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email.',%']);
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Everything the window shows for one user.
     *
     * @return array<string, mixed>
     */
    public static function detail(User $user): array
    {
        $slabs = self::slabs();
        $points = self::l30Points((int) $user->id);
        $slab = self::slabFor($points, $slabs);
        $next = null;
        foreach ($slabs as $s) {
            if ($s['position'] === $slab['position'] + 1) {
                $next = $s;
            }
        }

        $start = self::windowStart()->toDateString();
        $rules = PenaltyRule::query()->pluck('name', 'id');

        $entries = PenaltyEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('penalty_date', '>=', $start)
            ->orderByDesc('penalty_date')
            ->orderByDesc('id')
            ->get();

        $corrections = PenaltyCorrection::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', self::windowStart()->toDateTimeString())
            ->orderByDesc('id')
            ->get();
        $byEntry = $corrections->whereNotNull('penalty_entry_id')->groupBy('penalty_entry_id')->map->count();

        $lastDate = PenaltyEntry::query()->where('user_id', $user->id)->max('penalty_date');
        $cleanDays = $lastDate ? max(0, (int) Carbon::parse($lastDate)->startOfDay()->diffInDays(self::today())) : null;

        return [
            'user_id' => (int) $user->id,
            'name' => (string) $user->name,
            'l30_points' => $points,
            'l30_amount' => self::amountFor($points, $slabs),
            'rate' => $slab['rate'],
            'slab_position' => $slab['position'],
            'slab_label' => $slab['label'],
            'points_to_next_slab' => $next ? max(0, (int) $slab['to'] - $points + 1) : null,
            'next_slab_rate' => $next['rate'] ?? null,
            'clean_days' => $cleanDays,
            'corrective_notes_l30' => $corrections->count(),
            'corrective_tasks_l30' => self::correctiveTasksL30($user),
            'entries' => $entries->map(fn (PenaltyEntry $e) => [
                'id' => $e->id,
                'date' => $e->penalty_date->format('d M'),
                'points' => (int) $e->points,
                'reason' => (string) $e->reason,
                'source' => (string) $e->source,
                'rule' => $e->rule_id ? ($rules[$e->rule_id] ?? null) : null,
                'by' => (string) ($e->created_by ?? ''),
                'corrections' => (int) ($byEntry[$e->id] ?? 0),
            ])->values(),
            'corrections' => $corrections->map(fn (PenaltyCorrection $c) => [
                'id' => $c->id,
                'entry_id' => $c->penalty_entry_id,
                'note' => (string) $c->note,
                'date' => $c->created_at?->format('d M'),
            ])->values(),
            'months' => PenaltyMonth::query()
                ->where('user_id', $user->id)
                ->orderByDesc('month')
                ->limit(6)
                ->get()
                ->map(fn (PenaltyMonth $m) => [
                    'month' => $m->month,
                    'label' => Carbon::createFromFormat('Y-m-d', $m->month.'-01')->format('M Y'),
                    'points' => (int) $m->points,
                    'rate' => (float) $m->rate,
                    'amount' => (float) $m->amount,
                ])->values(),
        ];
    }

    /** Members for the president's lists: active users with their last-30-days points and amount. */
    public static function employees(): Collection
    {
        $slabs = self::slabs();
        $pts = self::l30PointsByUser();

        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(function (User $u) use ($pts, $slabs) {
                $p = (int) ($pts[$u->id] ?? 0);

                return [
                    'user_id' => (int) $u->id,
                    'name' => (string) $u->name,
                    'l30_points' => $p,
                    'l30_amount' => self::amountFor($p, $slabs),
                ];
            })
            ->values();
    }
}
