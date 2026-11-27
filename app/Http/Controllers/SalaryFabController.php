<?php

namespace App\Http\Controllers;

use App\Models\DeletedTask;
use App\Models\PayrollEmployeeSalary;
use App\Models\SalaryProjection;
use App\Models\ScopeOfImprovement;
use App\Models\Task;
use App\Models\User;
use App\Models\UserSalary;
use App\Services\PayrollService;
use App\Support\DarL30Metrics;
use App\Support\UserOverdueNudge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Floating "Salary" icon.
 *
 * - Every logged-in user sees ONLY their own current salary (same figures as the Salary page:
 *   Salary PP + Incr from their latest payroll month) and the future salary the company set for them.
 * - Only the configured setter (president@5core.com) can set / clear the future salary of any employee.
 */
class SalaryFabController extends Controller
{
    protected function isSetter(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $setter = strtolower(trim((string) config('payroll.future_salary.setter_email', 'president@5core.com')));

        return $setter !== '' && strtolower(trim((string) $user->email)) === $setter;
    }

    /** Latest payroll-sheet row per user (the figures the Salary page shows for the newest month). */
    protected function latestRows(array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        // Payroll months in chronological order.
        $months = \App\Models\PayrollMonth::query()
            ->orderBy('period_start')
            ->orderBy('id')
            ->get(['id', 'month_label'])
            ->values();
        $position = $months->pluck('id')->flip();

        // "Current" month = the running calendar month (e.g. November); when that month has no sheet
        // yet, the newest payroll month. "Next" month = the following calendar month (e.g. December),
        // whose increment is whatever is entered for it on the Payroll → Increment page.
        $currentLabel = now()->format('F Y');
        $nextLabel = now()->startOfMonth()->addMonth()->format('F Y');
        $anchor = $months->firstWhere('month_label', $currentLabel) ?? $months->last();
        $anchorPos = $anchor ? (int) $position->get($anchor->id) : -1;
        $next = $months->firstWhere('month_label', $nextLabel);

        $rows = PayrollEmployeeSalary::query()
            ->select('payroll_employee_salaries.*', 'payroll_months.month_label as month_label')
            ->join('payroll_months', 'payroll_months.id', '=', 'payroll_employee_salaries.payroll_month_id')
            ->whereIn('payroll_employee_salaries.user_id', $userIds)
            ->orderByDesc('payroll_months.period_start')
            ->orderByDesc('payroll_employee_salaries.id')
            ->get();

        // Increments saved ahead of time for the next month (Salary → Increment page).
        $nextRows = $next
            ? $rows->where('payroll_month_id', $next->id)->keyBy('user_id')
            : collect();

        // Each user's newest row that is not after the current month.
        $latest = $rows
            ->filter(fn ($r) => ($position->get($r->payroll_month_id) ?? PHP_INT_MAX) <= $anchorPos)
            ->unique('user_id')
            ->keyBy('user_id');

        foreach ($latest as $userId => $row) {
            $nextRow = $nextRows->get($userId);
            $inc = $nextRow ? (float) $nextRow->increment : 0.0;

            $row->setAttribute('next_month_label', $nextLabel);
            $row->setAttribute('next_from_label', null);

            if ($inc > 0) {
                $row->setAttribute('next_increment', $inc);
                // Next month's pay rate = carried-forward base (this month's PP + Incr, unless the sheet
                // has a manual PP override) + the approved increment.
                $base = $nextRow->salary_pp_overridden
                    ? (float) $nextRow->salary_pp
                    : (float) $row->salary_pp + (float) $row->increment;
                $row->setAttribute('next_salary', $base + $inc);
            } elseif ((float) $row->increment > 0) {
                // Next month isn't entered yet: show the increment listed on the Increment page
                // (Payroll → Increment) for the current month.
                $row->setAttribute('next_increment', (float) $row->increment);
                $row->setAttribute('next_salary', null);
                $row->setAttribute('next_from_label', $row->month_label);
            } else {
                $row->setAttribute('next_increment', null);
                $row->setAttribute('next_salary', null);
            }
        }

        return $latest;
    }

    /** @return array{prefix: string, locale: string} */
    protected function currency(User $user): array
    {
        return match ($user->salaryRegion()) {
            'china' => ['prefix' => 'RMB ', 'locale' => 'en-US'],
            'usa' => ['prefix' => 'US $', 'locale' => 'en-US'],
            default => ['prefix' => '₹', 'locale' => 'en-IN'],
        };
    }

    protected function payload(User $user, ?PayrollEmployeeSalary $row, ?SalaryProjection $projection): array
    {
        $pp = null;
        $incr = null;
        $monthLabel = null;

        if ($row) {
            $pp = (float) $row->salary_pp;
            $incr = (float) $row->increment;
            $monthLabel = $row->month_label ?? null;
        } else {
            /** @var UserSalary|null $stored */
            $stored = $user->userSalary;
            if ($stored) {
                $pp = (float) $stored->salary_pp;
                $incr = (float) $stored->increment;
            }
        }

        $current = $pp === null ? null : $pp + (float) $incr;
        $future = $projection ? (float) $projection->future_salary : null;
        $gap = ($current !== null && $future !== null) ? $future - $current : null;

        $stepMin = max(1, (int) config('payroll.future_salary.step_min', 500));
        $stepMax = max($stepMin, (int) config('payroll.future_salary.step_max', 1000));
        $monthsMin = $monthsMax = null;
        if ($gap !== null && $gap > 0) {
            // Estimate uses the top step (1000 / month) only.
            $monthsMin = $monthsMax = (int) ceil($gap / $stepMax);
        }

        // Progress (0-100) from the salary when the target was set to the target itself.
        $start = $projection && $projection->start_salary !== null ? (float) $projection->start_salary : null;
        $progress = null;
        if ($current !== null && $future !== null) {
            if ($future <= $current) {
                $progress = 100.0;
            } elseif ($start !== null && $start < $future) {
                $progress = max(0.0, min(100.0, ($current - $start) / ($future - $start) * 100));
            } else {
                $progress = max(0.0, min(100.0, $current / $future * 100));
            }
            $progress = round($progress, 1);
        }

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'currency' => $this->currency($user),
            'month_label' => $monthLabel,
            'salary_pp' => $pp,
            'increment' => $incr,
            'current_salary' => $current,
            'future_salary' => $future,
            'gap' => $gap,
            'start_salary' => $start,
            'progress' => $progress,
            'months_min' => $monthsMin,
            'months_max' => $monthsMax,
            'next_month_label' => $row?->next_month_label,
            'next_increment' => $row?->next_increment,
            'next_salary' => $row?->next_salary,
            'next_from_label' => $row?->next_from_label,
            'set_by' => $projection?->set_by,
            'future_updated_at' => $projection?->updated_at?->format('d M Y'),
        ];
    }

    /**
     * @return array{dar_pct: int, dar_count: int, dar_target: int, dar_band: string, overdue: int, si_count: int}
     */
    protected function performance(User $user): array
    {
        $out = [
            'dar_pct' => 0,
            'dar_count' => 0,
            'dar_target' => DarL30Metrics::TARGET,
            'dar_band' => 'low',
            'overdue' => 0,
            'overdue_avg' => null,
            'tat' => null,
            'si_count' => 0,
        ];

        try {
            // Same count as the top-bar SI (Scope of Improvement) button.
            if (Schema::hasTable('scope_of_improvements')) {
                $out['si_count'] = (int) ScopeOfImprovement::query()->where('user_id', (int) $user->id)->count();
            }
        } catch (\Throwable $e) {
        }

        try {
            $dar = DarL30Metrics::forUserId((int) $user->id);
            $out['dar_pct'] = (int) ($dar['dar_l30_pct'] ?? 0);
            $out['dar_count'] = (int) ($dar['dar_l30_count'] ?? 0);
            $out['dar_target'] = (int) ($dar['dar_l30_target'] ?? DarL30Metrics::TARGET);
            $out['dar_band'] = (string) ($dar['dar_l30_band'] ?? 'low');
        } catch (\Throwable $e) {
            // never break the Salary window because of a metrics problem
        }

        try {
            $out['overdue'] = UserOverdueNudge::countForUser($user);
        } catch (\Throwable $e) {
        }

        // TAT = average days from task start to completion for the user's Done tasks of the last 30 days.
        try {
            $series = $this->tatSeries($user, 1);
            $out['tat'] = $series[0]['value'] ?? null;
        } catch (\Throwable $e) {
        }

        // Team average of overdue tasks (everyone on the Salary list), for comparison.
        try {
            $out['overdue_avg'] = Cache::remember('salary_fab_overdue_avg_'.now()->toDateString(), now()->addMinutes(10), function () {
                $users = User::query()->where('is_active', true)->where('show_in_salary', true)->get();
                if ($users->isEmpty()) {
                    return null;
                }
                $total = $users->sum(fn (User $u) => UserOverdueNudge::countForUser($u));

                return round($total / $users->count(), 1);
            });
        } catch (\Throwable $e) {
        }

        return $out;
    }

    /**
     * Rolling TAT series for the last $days days (one point per day, oldest first).
     * Each point = average days (1 decimal) from start_date to completion for the user's Done tasks
     * that were completed in the 30 days ending on that day; null when there were none.
     * Done tasks are read from the Task Manager and from deleted_tasks (checked and archived).
     *
     * @return list<array{date: string, value: float|null}>
     */
    protected function tatSeries(User $user, int $days): array
    {
        $email = strtolower(trim((string) $user->email));
        $window = 30;
        $days = max(1, min(120, $days));
        if ($email === '') {
            return [];
        }

        $today = now()->startOfDay();
        $since = $today->copy()->subDays($days - 1 + $window - 1);

        $assignee = function ($q) use ($email) {
            $q->whereRaw('LOWER(TRIM(assign_to)) = ?', [$email])
                ->orWhereRaw('LOWER(assign_to) LIKE ?', [$email.',%'])
                ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email])
                ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email.',%']);
        };
        // Completion date, falling back to updated_at when it is empty / a zero date.
        $inWindow = function ($q) use ($since) {
            $s = $since->toDateTimeString();
            $q->where(function ($a) use ($s) {
                $a->where('completion_date', '>=', $s)
                    ->orWhere(function ($b) use ($s) {
                        $b->where(function ($c) {
                            $c->whereNull('completion_date')->orWhere('completion_date', '<', '1000-01-01');
                        })->where('updated_at', '>=', $s);
                    });
            });
        };

        $rows = collect();
        foreach ([Task::query(), DeletedTask::query()] as $query) {
            try {
                $rows = $rows->merge(
                    $query->where('status', 'Done')
                        ->whereNotNull('start_date')
                        ->where($assignee)
                        ->where($inWindow)
                        ->toBase()
                        ->get(['start_date', 'completion_date', 'updated_at'])
                );
            } catch (\Throwable $e) {
                // a missing table / column must never break the window
            }
        }

        // [completion day (Y-m-d), tat days]
        $items = [];
        foreach ($rows as $r) {
            try {
                $start = \Carbon\Carbon::parse($r->start_date)->startOfDay();
                $rawEnd = $r->completion_date;
                $end = ($rawEnd && ! str_starts_with((string) $rawEnd, '0000'))
                    ? \Carbon\Carbon::parse($rawEnd)
                    : \Carbon\Carbon::parse($r->updated_at);
                $end = $end->startOfDay();
            } catch (\Throwable $e) {
                continue;
            }
            $items[] = [$end->toDateString(), abs($end->getTimestamp() - $start->getTimestamp()) / 86400];
        }

        $points = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $from = $day->copy()->subDays($window - 1)->toDateString();
            $to = $day->toDateString();
            $vals = [];
            foreach ($items as [$d, $tat]) {
                if ($d >= $from && $d <= $to) {
                    $vals[] = $tat;
                }
            }
            $points[] = [
                'date' => $to,
                'value' => $vals ? round(array_sum($vals) / count($vals), 1) : null,
            ];
        }

        return $points;
    }

    /** History graph data for the TAT badge (rolling 30-day average, one point per day). */
    public function tatHistory(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 60);
        $days = in_array($days, [30, 60, 90], true) ? $days : 60;

        return response()->json([
            'success' => true,
            'days' => $days,
            'points' => $this->tatSeries($request->user(), $days),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $me = $request->user();
        $isSetter = $this->isSetter($me);

        $me->loadMissing('userSalary');
        $mine = $this->payload(
            $me,
            $this->latestRows([$me->id])->get($me->id),
            SalaryProjection::where('user_id', $me->id)->first()
        );

        // Performance: the logged-in user's OWN DAR % (last 30 days, same as the top bar) and overdue tasks.
        $mine['performance'] = $this->performance($me);

        $data = [
            'success' => true,
            'is_setter' => $isSetter,
            'step_min' => (int) config('payroll.future_salary.step_min', 500),
            'step_max' => (int) config('payroll.future_salary.step_max', 1000),
            'me' => $mine,
        ];

        if ($isSetter) {
            $users = User::query()
                ->where('is_active', true)
                ->where('show_in_salary', true)
                ->with('userSalary')
                ->orderBy('name')
                ->get();
            $ids = $users->pluck('id')->all();
            $rows = $this->latestRows($ids);
            $projections = SalaryProjection::whereIn('user_id', $ids)->get()->keyBy('user_id');

            $data['employees'] = $users
                ->map(fn (User $u) => $this->payload($u, $rows->get($u->id), $projections->get($u->id)))
                ->values();
        }

        return response()->json($data);
    }

    public function setFuture(Request $request): JsonResponse
    {
        abort_unless($this->isSetter($request->user()), 403, 'Only president@5core.com can set the future salary.');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'future_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $user = User::with('userSalary')->findOrFail($validated['user_id']);
        $amount = $validated['future_salary'] ?? null;

        if ($amount === null || $amount === '') {
            SalaryProjection::where('user_id', $user->id)->delete();
            $projection = null;
        } else {
            $existing = SalaryProjection::where('user_id', $user->id)->first();
            $currentNow = $this->payload($user, $this->latestRows([$user->id])->get($user->id), null)['current_salary'];

            $projection = SalaryProjection::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'future_salary' => round((float) $amount, 2),
                    // The starting point is fixed when the target is first set, so progress keeps accumulating.
                    'start_salary' => $existing?->start_salary ?? $currentNow,
                    'set_by' => $request->user()->email,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'employee' => $this->payload($user, $this->latestRows([$user->id])->get($user->id), $projection),
        ]);
    }
}
