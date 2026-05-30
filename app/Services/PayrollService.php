<?php

namespace App\Services;

use App\Models\PayrollArrear;
use App\Models\PayrollEmployeeSalary;
use App\Models\PayrollMonth;
use App\Models\PayrollPaymentDeduction;
use App\Models\PayrollPayslip;
use App\Models\PayrollSalaryComponent;
use App\Models\User;
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

    public function syncEmployeesFromUsers(PayrollMonth $month, array $userIds = [], bool $newHiresOnly = false): int
    {
        $teamLogger = $this->teamLoggerDataForMonth($month->month_label);

        $query = User::query()
            ->where('is_active', true)
            ->where('show_in_salary', true)
            ->with('userSalary');

        if ($userIds !== []) {
            $query->whereIn('id', $userIds);
        }

        $count = 0;
        foreach ($query->get() as $user) {
            $exists = PayrollEmployeeSalary::where('payroll_month_id', $month->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($exists && $newHiresOnly) {
                continue;
            }

            $calc = $this->teamSalary->calculateForUser($user, $teamLogger);
            $salary = $user->userSalary;

            PayrollEmployeeSalary::updateOrCreate(
                ['payroll_month_id' => $month->id, 'user_id' => $user->id],
                [
                    'salary_pp' => $calc['salary_pp'],
                    'increment' => $calc['increment'],
                    'other' => $calc['other'],
                    'adv_inc_other' => $calc['adv_inc_other'],
                    'hours_worked' => $calc['hours_lm'],
                    'gross_amount' => $calc['amount_lm'],
                    'net_amount' => $calc['amount_p_rounded'],
                    'bank_1' => $salary?->bank_1,
                    'bank_2' => $salary?->bank_2,
                    'upi_id' => $salary?->upi_id,
                    'is_new_hire' => $newHiresOnly || ! $exists,
                ]
            );
            $count++;
        }

        return $count;
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
                (float) $row->adv_inc_other
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

            $hasExtras = $componentsEarning > 0 || $componentsDeduction > 0 || $payments > 0 || $deductions > 0 || abs($arrears) > 0.001;
            $net = $hasExtras
                ? $this->roundNet($calc['amount_p'] + $componentsEarning - $componentsDeduction + $payments - $deductions + $arrears)
                : $calc['amount_p_rounded'];

            $row->update([
                'gross_amount' => $calc['amount_lm'],
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
        if ($hours > 0 && $increment > 0) {
            $lines[] = [
                $this->monthIncrementLabel($monthLabel),
                (float) round(($hours * $increment) / $divisor),
            ];
        }
        if (($data['other'] ?? 0) > 0) {
            $lines[] = ['Other Allowance', (float) $data['other']];
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
            (float) $row->adv_inc_other
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
