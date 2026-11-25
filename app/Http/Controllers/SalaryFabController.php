<?php

namespace App\Http\Controllers;

use App\Models\PayrollEmployeeSalary;
use App\Models\SalaryProjection;
use App\Models\User;
use App\Models\UserSalary;
use App\Services\PayrollService;
use App\Support\DarL30Metrics;
use App\Support\UserOverdueNudge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

        // "Current" month = the month the Salary page opens on (last calendar month); when that month
        // has no sheet yet, the newest payroll month. "Next" month = the one right after it.
        $defaultLabel = app(PayrollService::class)->defaultMonthLabel();
        $anchor = $months->firstWhere('month_label', $defaultLabel) ?? $months->last();
        $anchorPos = $anchor ? (int) $position->get($anchor->id) : -1;
        $next = $anchor ? $months->get($anchorPos + 1) : null;

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

            $row->setAttribute('next_month_label', $next?->month_label);
            $row->setAttribute('next_increment', $inc > 0 ? $inc : null);
            // Next month's pay rate = carried-forward base (this month's PP + Incr, unless the sheet
            // has a manual PP override) + the approved increment.
            $base = $nextRow && $nextRow->salary_pp_overridden
                ? (float) $nextRow->salary_pp
                : (float) $row->salary_pp + (float) $row->increment;
            $row->setAttribute('next_salary', $inc > 0 ? $base + $inc : null);
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
            $monthsMin = (int) ceil($gap / $stepMax);
            $monthsMax = (int) ceil($gap / $stepMin);
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
            'set_by' => $projection?->set_by,
            'future_updated_at' => $projection?->updated_at?->format('d M Y'),
        ];
    }

    /**
     * @return array{dar_pct: int, dar_count: int, dar_target: int, dar_band: string, overdue: int}
     */
    protected function performance(User $user): array
    {
        $out = [
            'dar_pct' => 0,
            'dar_count' => 0,
            'dar_target' => DarL30Metrics::TARGET,
            'dar_band' => 'low',
            'overdue' => 0,
        ];

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

        return $out;
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
