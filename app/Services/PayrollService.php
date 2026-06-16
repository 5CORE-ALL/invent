<?php

namespace App\Services;

use App\Models\PayrollArrear;
use App\Models\PayrollEmployeeSalary;
use App\Models\PayrollMonth;
use App\Models\PayrollPaymentDeduction;
use App\Models\PayrollPayslip;
use App\Models\PayrollSalaryComponent;
use App\Models\User;
use App\Models\UserSalary;
use Carbon\Carbon;

class PayrollService
{
    public function __construct(
        protected TeamSalaryCalculator $teamSalary
    ) {}

    public function teamLoggerDataForMonth(string $monthLabel): array
    {
        return $this->teamSalary->teamLoggerDataForMonth($monthLabel);
    }

    public function resolveTeamLoggerEmail(string $userEmail): string
    {
        return $this->teamSalary->teamLoggerEmail($userEmail);
    }

    public function roundNet(float $amount): float
    {
        return $this->teamSalary->roundAmountP($amount);
    }

    public function defaultMonthLabel(): string
    {
        return $this->teamSalary->defaultMonthLabel();
    }

    public function periodDatesFromLabel(string $label): array
    {
        try {
            $start = Carbon::parse('first day of '.$label);
            $end = $start->copy()->endOfMonth();

            return [$start->toDateString(), $end->toDateString()];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    public function monthIncrementLabel(string $monthLabel): string
    {
        try {
            return Carbon::parse('first day of '.$monthLabel)->format('F').' Month Increment';
        } catch (\Throwable) {
            $parts = explode(' ', trim($monthLabel));

            return ($parts[0] ?? 'Month').' Month Increment';
        }
    }

    /** Team tab Amt LM */
    public function amountLm(float $hours, float $salaryLm): float
    {
        $divisor = $this->teamSalary->hoursDivisor();

        return ($hours * $salaryLm) / $divisor;
    }

    /** Team tab Amt P (before round) */
    public function amountP(float $hours, float $salaryLm, float $other, float $advIncOther): float
    {
        return $this->amountLm($hours, $salaryLm) + $other - $advIncOther;
    }

    /**
     * Base query for employees eligible for payroll: every active user that is
     * marked to show in salary and has not been deleted. SoftDeletes excludes
     * `deleted_at` rows, deactivated users (is_active = false) are excluded, and
     * anyone with show_in_salary = false is left off payroll entirely.
     *
     * When a $month is supplied, the joining date is respected as well: a user
     * only belongs on a month's sheet once they have joined on or before that
     * month ends. Users joining later (e.g. a May hire relative to April) are
     * therefore excluded. Users without a recorded joining date are treated as
     * existing staff and stay eligible for every month.
     */
    protected function eligibleUsersQuery(?PayrollMonth $month = null)
    {
        $query = User::query()
            ->where('is_active', true)
            ->where('show_in_salary', true)
            ->with('userSalary');

        if ($month && $month->period_end) {
            $joinedBy = $month->period_end->copy()->endOfDay();

            $query->where(function ($q) use ($joinedBy) {
                $q->whereNull('date_of_joining')
                    ->orWhere('date_of_joining', '<=', $joinedBy);
            });
        }

        return $query;
    }

    /**
     * Drop rows on an unlocked month's sheet for anyone no longer eligible
     * (deactivated/deleted users, or hires who joined after the month ended), so
     * they stop showing automatically.
     */
    public function removeIneligibleEmployees(PayrollMonth $month): int
    {
        if ($month->is_locked) {
            return 0;
        }

        $eligibleIds = $this->eligibleUsersQuery($month)->pluck('id')->all();

        return PayrollEmployeeSalary::where('payroll_month_id', $month->id)
            ->when(
                $eligibleIds !== [],
                fn ($q) => $q->whereNotIn('user_id', $eligibleIds)
            )
            ->delete();
    }

    /**
     * The payroll month immediately before the given one (by period, falling back
     * to id when periods are missing). Used to carry a salary forward month over
     * month.
     */
    protected function previousMonth(PayrollMonth $month): ?PayrollMonth
    {
        $query = PayrollMonth::where('id', '!=', $month->id);

        if ($month->period_start) {
            return $query->where('period_start', '<', $month->period_start)
                ->orderByDesc('period_start')
                ->first();
        }

        return $query->where('id', '<', $month->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Carry-forward base for a user's Salary PP on a given month: the previous
     * month's (Salary PP + Increment). Returns null when there is no prior month
     * row for the user (e.g. the very first payroll month, or a brand-new hire),
     * so callers fall back to the user's stored salary.
     */
    protected function carryForwardSalaryPp(PayrollMonth $month, int $userId): ?float
    {
        $previous = $this->previousMonth($month);

        if (! $previous) {
            return null;
        }

        $previousRow = PayrollEmployeeSalary::where('payroll_month_id', $previous->id)
            ->where('user_id', $userId)
            ->first();

        if (! $previousRow) {
            return null;
        }

        return (float) $previousRow->salary_pp + (float) $previousRow->increment;
    }

    /**
     * Build the stored attributes for a freshly created month row. When a prior
     * month row exists, the Salary PP is carried forward as previous (PP +
     * Increment) and the new month starts with a 0 increment — i.e. last month's
     * raise is absorbed into this month's base pay. Hours / other / advance still
     * come from the live calculation.
     */
    protected function newRowAttributes(PayrollMonth $month, User $user, array $teamLogger): array
    {
        $calc = $this->teamSalary->calculateForUser($user, $teamLogger);

        $carry = $this->carryForwardSalaryPp($month, $user->id);
        if ($carry !== null) {
            $calc = $this->teamSalary->calculateFromValues(
                (float) $calc['hours_lm'],
                $carry,
                0.0,
                (float) $calc['other'],
                (float) $calc['adv_inc_other'],
            );
        }

        $salary = $user->userSalary;

        return [
            'salary_pp' => $calc['salary_pp'],
            'increment' => $calc['increment'],
            'other' => $calc['other'],
            'adv_inc_other' => $calc['adv_inc_other'],
            'hours_worked' => $calc['hours_lm'],
            'gross_amount' => $calc['amount_p'],
            'net_amount' => $calc['amount_p_rounded'],
            'bank_1' => $salary?->bank_1,
            'bank_2' => $salary?->bank_2,
            'upi_id' => $salary?->upi_id,
        ];
    }

    public function syncEmployeesFromUsers(PayrollMonth $month, array $userIds = [], bool $newHiresOnly = false): int
    {
        $teamLogger = $this->teamLoggerDataForMonth($month->month_label);

        $query = $this->eligibleUsersQuery($month);

        if ($userIds !== []) {
            $query->whereIn('id', $userIds);
        }

        $count = 0;
        foreach ($query->get() as $user) {
            $existingRow = PayrollEmployeeSalary::where('payroll_month_id', $month->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingRow && $newHiresOnly) {
                continue;
            }

            // Carry the salary forward only for rows we are creating; existing
            // rows keep whatever salary is already on the sheet (manual edits or a
            // previously carried-forward value) so re-syncing never clobbers them.
            $attributes = $existingRow
                ? $this->liveCalcAttributes($user, $teamLogger, $existingRow)
                : $this->newRowAttributes($month, $user, $teamLogger);
            $attributes['is_new_hire'] = $newHiresOnly || ! $existingRow;

            PayrollEmployeeSalary::updateOrCreate(
                ['payroll_month_id' => $month->id, 'user_id' => $user->id],
                $attributes
            );
            $count++;
        }

        return $count;
    }

    /**
     * Attributes computed straight from the user's stored salary + live hours,
     * without carry-forward. Used when refreshing an existing row.
     *
     * When an existing row is supplied, manually edited fields are honoured:
     * a row with `hours_overridden` keeps its edited Hours, and a row with
     * `salary_pp_overridden` keeps its edited Salary PP — so re-syncing from
     * Users / TeamLogger never wipes a manual edit.
     */
    protected function liveCalcAttributes(User $user, array $teamLogger, ?PayrollEmployeeSalary $existingRow = null): array
    {
        $calc = $this->teamSalary->calculateForUser($user, $teamLogger);
        $salary = $user->userSalary;

        $attributes = [
            'salary_pp' => $calc['salary_pp'],
            'increment' => $calc['increment'],
            'other' => $calc['other'],
            'adv_inc_other' => $calc['adv_inc_other'],
            'hours_worked' => $calc['hours_lm'],
            'gross_amount' => $calc['amount_p'],
            'net_amount' => $calc['amount_p_rounded'],
            'bank_1' => $salary?->bank_1,
            'bank_2' => $salary?->bank_2,
            'upi_id' => $salary?->upi_id,
        ];

        if ($existingRow) {
            if ($existingRow->hours_overridden) {
                unset($attributes['hours_worked']);
            }
            if ($existingRow->salary_pp_overridden) {
                unset($attributes['salary_pp']);
            }
        }

        return $attributes;
    }

    /**
     * Make sure every (non-deleted) user has a row on this month's sheet without
     * touching rows that already exist. This lets the payroll screen show all
     * users automatically on open, so a manual "Sync from Team" is never required.
     * Existing rows (and any manual salary edits on them) are left untouched.
     */
    public function ensureSheetPopulated(PayrollMonth $month): int
    {
        if ($month->is_locked) {
            return 0;
        }

        $existingUserIds = PayrollEmployeeSalary::where('payroll_month_id', $month->id)
            ->pluck('user_id')
            ->all();

        $missing = $this->eligibleUsersQuery($month)
            ->when($existingUserIds !== [], fn ($q) => $q->whereNotIn('id', $existingUserIds))
            ->get();

        if ($missing->isEmpty()) {
            return 0;
        }

        $teamLogger = $this->teamLoggerDataForMonth($month->month_label);
        $count = 0;

        foreach ($missing as $user) {
            PayrollEmployeeSalary::create(array_merge(
                $this->newRowAttributes($month, $user, $teamLogger),
                [
                    'payroll_month_id' => $month->id,
                    'user_id' => $user->id,
                    'is_new_hire' => false,
                ]
            ));
            $count++;
        }

        return $count;
    }

    /**
     * Keep each row's Salary PP in sync with the previous month's (Salary PP +
     * Increment), so a month's base pay always reflects last month's total. The
     * per-month Increment is left untouched (it is that month's fresh raise).
     * Rows with no previous-month counterpart (first month / new hires) keep their
     * own salary. Runs only on unlocked months and recalculates when anything
     * changed.
     */
    public function syncCarryForwardSalaries(PayrollMonth $month): bool
    {
        if ($month->is_locked) {
            return false;
        }

        $previous = $this->previousMonth($month);
        if (! $previous) {
            return false;
        }

        $previousByUser = PayrollEmployeeSalary::where('payroll_month_id', $previous->id)
            ->get()
            ->keyBy('user_id');

        $changed = false;
        foreach (PayrollEmployeeSalary::where('payroll_month_id', $month->id)->get() as $row) {
            // A manually edited Salary PP wins — never overwrite it with the
            // carried-forward value.
            if ($row->salary_pp_overridden) {
                continue;
            }

            $prev = $previousByUser->get($row->user_id);
            if (! $prev) {
                continue;
            }

            $carry = (float) $prev->salary_pp + (float) $prev->increment;
            if ((float) $row->salary_pp !== $carry) {
                $row->update(['salary_pp' => $carry]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->recalculateMonth($month);
        }

        return $changed;
    }

    /**
     * Refresh the bank payout details (B1 / B2 / UPI) on every existing row of an
     * unlocked month from the user's current saved bank details. Rows snapshot
     * bank details when first created, so any bank info added/updated on
     * /users/add afterwards never reached the payroll sheet — leaving some users
     * blank on the payout export. This re-pulls the latest details so the export
     * is always complete. Locked months keep their historical snapshot untouched.
     */
    public function syncBankDetails(PayrollMonth $month): bool
    {
        if ($month->is_locked) {
            return false;
        }

        $salariesByUser = UserSalary::query()
            ->whereIn('user_id', PayrollEmployeeSalary::where('payroll_month_id', $month->id)->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        $changed = false;
        foreach (PayrollEmployeeSalary::where('payroll_month_id', $month->id)->get() as $row) {
            $salary = $salariesByUser->get($row->user_id);
            if (! $salary) {
                continue;
            }

            $bank1 = $salary->bank_1;
            $bank2 = $salary->bank_2;
            $upi = $salary->upi_id;

            if ($row->bank_1 === $bank1 && $row->bank_2 === $bank2 && $row->upi_id === $upi) {
                continue;
            }

            $row->update([
                'bank_1' => $bank1,
                'bank_2' => $bank2,
                'upi_id' => $upi,
            ]);
            $changed = true;
        }

        return $changed;
    }

    public function recalculateMonth(PayrollMonth $month): void
    {
        $rows = PayrollEmployeeSalary::where('payroll_month_id', $month->id)->get();

        foreach ($rows as $row) {
            $calc = $this->teamSalary->calculateFromValues(
                (float) $row->hours_worked,
                (float) $row->salary_pp,
                (float) $row->increment,
                (float) $row->other,
                (float) $row->adv_inc_other,
                (float) $row->incentive
            );

            $userId = $row->user_id;
            $componentsEarning = (float) PayrollSalaryComponent::where('payroll_month_id', $month->id)
                ->where('user_id', $userId)->where('type', 'earning')->sum('amount');
            $componentsDeduction = (float) PayrollSalaryComponent::where('payroll_month_id', $month->id)
                ->where('user_id', $userId)->where('type', 'deduction')->sum('amount');
            $payments = (float) PayrollPaymentDeduction::where('payroll_month_id', $month->id)
                ->where('user_id', $userId)->where('entry_type', 'payment')->sum('amount');
            $deductions = (float) PayrollPaymentDeduction::where('payroll_month_id', $month->id)
                ->where('user_id', $userId)->where('entry_type', 'deduction')->sum('amount');
            $arrears = $this->appliedArrearsTotal($month->id, $userId);

            // "Amount" = ((PP + Increment) * Hours / 200) - Advance + Other + Incentive.
            // "Payable" (net) layers the month's components, payments and arrears on top.
            $amount = $calc['amount_p'];
            $hasExtras = $componentsEarning > 0 || $componentsDeduction > 0 || $payments > 0 || $deductions > 0 || abs($arrears) > 0.001;
            $net = $hasExtras
                ? $this->roundNet($amount + $componentsEarning - $componentsDeduction + $payments - $deductions + $arrears)
                : $calc['amount_p_rounded'];

            $row->update([
                'gross_amount' => $amount,
                'lop_amount' => 0,
                'arrears_amount' => $arrears,
                'payments_total' => $payments,
                'deductions_total' => $deductions + $componentsDeduction,
                'net_amount' => $net,
            ]);
        }
    }

    /**
     * Refresh stored working hours from live TeamLogger for an unlocked month, then
     * recompute amounts. Salary inputs (PP, increment, other, adv) are kept as stored,
     * and users with no live TeamLogger entry keep their existing hours.
     */
    public function refreshLiveHours(PayrollMonth $month): void
    {
        if ($month->is_locked) {
            return;
        }

        $teamLogger = $this->teamLoggerDataForMonth($month->month_label);
        $changed = false;

        foreach (PayrollEmployeeSalary::with('user')->where('payroll_month_id', $month->id)->get() as $row) {
            if (! $row->user) {
                continue;
            }
            // Manually edited hours win — don't overwrite them with live data.
            if ($row->hours_overridden) {
                continue;
            }
            $email = $this->resolveTeamLoggerEmail($row->user->email);
            if (! array_key_exists($email, $teamLogger)) {
                continue; // no live data for this user — keep the stored snapshot
            }
            $hours = (float) ($teamLogger[$email]['hours'] ?? 0);
            if ((float) $row->hours_worked !== $hours) {
                $row->update(['hours_worked' => $hours]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->recalculateMonth($month);
        }
    }

    /**
     * @return array<int, array{0: string, 1: float}>
     */
    public function buildPayslipEarnings(array $data): array
    {
        $monthLabel = (string) ($data['month'] ?? '');
        $hours = (float) ($data['hours_worked'] ?? 0);
        $salaryPp = (float) ($data['salary_pp'] ?? 0);
        $increment = (float) ($data['increment'] ?? 0);
        $divisor = $this->teamSalary->hoursDivisor();
        $skip = ['salary', 'basic', 'basic pay', 'salary lm', 'salary pp', 'incr', 'increment', 'gross', 'amt lm'];

        $lines = [];
        if ($hours > 0 && $salaryPp > 0) {
            $lines[] = [
                'Basic Pay',
                (float) round(($hours * $salaryPp) / $divisor),
            ];
        }
        if ($increment > 0) {
            $lines[] = [
                $this->monthIncrementLabel($monthLabel),
                (float) $increment,
            ];
        }
        if (($data['other'] ?? 0) > 0) {
            $lines[] = ['Other Allowance', (float) $data['other']];
        }
        if (($data['incentive'] ?? 0) > 0) {
            $lines[] = ['Incentive', (float) $data['incentive']];
        }
        foreach ($data['components'] ?? [] as $c) {
            if (($c['type'] ?? '') !== 'earning' || ($c['amount'] ?? 0) <= 0) {
                continue;
            }
            $label = strtolower(trim((string) ($c['label'] ?? '')));
            if (in_array($label, $skip, true) || str_contains($label, 'increment')) {
                continue;
            }
            $lines[] = [(string) $c['label'], (float) $c['amount']];
        }
        foreach ($data['arrear_lines'] ?? [] as $line) {
            $amt = (float) ($line['amount'] ?? 0);
            if (abs($amt) > 0.001) {
                $lines[] = [(string) ($line['label'] ?? 'Arrear'), $amt];
            }
        }

        return $lines;
    }

    public function appliedArrearsTotal(int $payrollMonthId, int $userId): float
    {
        return (float) PayrollArrear::where('payroll_month_id', $payrollMonthId)
            ->where('user_id', $userId)
            ->where('status', 'applied')
            ->get()
            ->sum(fn (PayrollArrear $a) => $a->signedAmount());
    }

    /** @return array<int, array{label: string, amount: float}> */
    public function appliedArrearLines(int $payrollMonthId, int $userId): array
    {
        return PayrollArrear::where('payroll_month_id', $payrollMonthId)
            ->where('user_id', $userId)
            ->where('status', 'applied')
            ->orderBy('id')
            ->get()
            ->map(fn (PayrollArrear $a) => [
                'label' => $a->displayLabel(),
                'amount' => $a->signedAmount(),
            ])
            ->values()
            ->all();
    }

    /** Build payslip payload from current payroll employee row (same as Employees tab). */
    public function buildPayslipData(PayrollMonth $month, PayrollEmployeeSalary $row, ?string $format = null): array
    {
        $row->loadMissing('user');
        $format ??= $month->payslip_format ?: 'standard';
        $userId = (int) $row->user_id;

        $calc = $this->teamSalary->calculateFromValues(
            (float) $row->hours_worked,
            (float) $row->salary_pp,
            (float) $row->increment,
            (float) $row->other,
            (float) $row->adv_inc_other,
            (float) $row->incentive
        );

        $components = PayrollSalaryComponent::where('payroll_month_id', $month->id)
            ->where('user_id', $userId)->get();
        $componentsEarning = (float) $components->where('type', 'earning')->sum('amount');
        $componentsDeduction = (float) $components->where('type', 'deduction')->sum('amount');
        $payments = (float) $row->payments_total;
        $deductions = (float) $row->deductions_total;
        $arrearLines = $this->appliedArrearLines($month->id, $userId);
        $arrears = array_sum(array_column($arrearLines, 'amount'));

        $data = [
            'employee' => $row->user?->name,
            'email' => $row->user?->email,
            'designation' => $row->user?->designation,
            'employee_id' => 'EMP-'.str_pad((string) $userId, 4, '0', STR_PAD_LEFT),
            'month' => $month->month_label,
            'period_start' => $month->period_start?->format('d M Y'),
            'period_end' => $month->period_end?->format('d M Y'),
            'payslip_no' => 'PS-'.$month->id.'-'.$userId,
            'format' => $format,
            'salary_pp' => $calc['salary_pp'],
            'increment' => $calc['increment'],
            'salary_lm' => $calc['salary_lm'],
            'other' => $calc['other'],
            'adv_inc_other' => $calc['adv_inc_other'],
            'incentive' => $calc['incentive'],
            'hours_worked' => $calc['hours_lm'],
            'amount_lm' => $calc['amount_lm'],
            'amount_lm_display' => $calc['amount_lm_display'],
            'amount_p' => $calc['amount_p'],
            'amount_p_rounded' => $calc['amount_p_rounded'],
            'amount_p_display' => $calc['amount_p_display'],
            'gross' => $calc['amount_lm'],
            'net' => (float) $row->net_amount,
            'arrears' => $arrears,
            'arrear_lines' => $arrearLines,
            'payments' => $payments,
            'deductions' => $deductions,
            'bank_1' => $row->bank_1,
            'bank_2' => $row->bank_2,
            'upi_id' => $row->upi_id,
            'components' => $components->map(fn ($c) => [
                'type' => $c->type,
                'label' => $c->label,
                'amount' => (float) $c->amount,
            ])->values()->all(),
            'generated_at' => now()->format('d M Y, h:i A'),
        ];

        $data['earning_lines'] = $this->buildPayslipEarnings($data);
        $data['total_earnings'] = round(array_sum(array_column($data['earning_lines'], 1)));

        return $data;
    }

    public function generatePayslips(PayrollMonth $month): int
    {
        $format = $month->payslip_format ?: 'standard';
        $rows = PayrollEmployeeSalary::with('user')->where('payroll_month_id', $month->id)->get();
        $count = 0;

        foreach ($rows as $row) {
            $data = $this->buildPayslipData($month, $row, $format);
            if ($format === 'compact') {
                $data['summary_only'] = true;
            }

            PayrollPayslip::updateOrCreate(
                ['payroll_month_id' => $month->id, 'user_id' => $row->user_id],
                ['format' => $format, 'data' => $data]
            );
            $count++;
        }

        return $count;
    }
}
