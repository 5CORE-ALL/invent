<?php

namespace App\Services;

use App\Models\TeamLoggerHours;
use App\Models\User;
use Carbon\Carbon;

/** Matches Team Management → Salary tab (/users/add). */
class TeamSalaryCalculator
{
    public function hoursDivisor(): int
    {
        return (int) config('payroll.hours_divisor', 200);
    }

    public function defaultMonthLabel(): string
    {
        return Carbon::now()->subMonth()->format('F Y');
    }

    /** @return array<string, string> */
    public function emailMapping(): array
    {
        return array_change_key_case(config('payroll.email_mapping', []), CASE_LOWER);
    }

    public function teamLoggerEmail(string $userEmail, ?array $mapping = null): string
    {
        $mapping ??= $this->emailMapping();
        $key = strtolower(trim($userEmail));

        return $mapping[$key] ?? $key;
    }

    public function teamLoggerDataForMonth(string $monthLabel, bool $useCache = true, bool $preferApi = false): array
    {
        $service = new TeamLoggerService();
        $data = $service->fetchByMonth($monthLabel, $useCache);

        foreach (TeamLoggerHours::where('month', $monthLabel)->get() as $record) {
            $email = strtolower($record->employee_email);
            // Always exclude idle: prefer total - idle when both are present.
            $productive = $this->productiveHoursFromRecord($record);

            if (isset($data[$email])) {
                // On a forced refresh, live API hours win over stale DB snapshots.
                if (! $preferApi) {
                    $data[$email]['hours'] = $productive;
                }
            } else {
                $data[$email] = [
                    'hours' => $productive,
                    'total_hours' => (float) ($record->total_hours ?? 0),
                    'idle_hours' => (float) ($record->idle_hours ?? 0),
                    'active_hours' => $productive,
                ];
            }
        }

        return $data;
    }

    /**
     * Productive hours for salary/payroll (idle excluded).
     * When total + idle are stored, recompute so we never pay on idle time.
     */
    public function productiveHoursFromRecord(TeamLoggerHours $record): int
    {
        $total = (float) ($record->total_hours ?? 0);
        $idle = (float) ($record->idle_hours ?? 0);

        if ($total > 0) {
            return (int) round(max(0, $total - max(0, $idle)));
        }

        return (int) round((float) ($record->productive_hours ?? $record->active_hours ?? 0));
    }

    public function calculateForUser(User $user, array $teamLoggerData, ?array $mapping = null): array
    {
        $user->loadMissing('userSalary');
        $mapping ??= $this->emailMapping();
        $email = $this->teamLoggerEmail($user->email, $mapping);
        $hours = (float) ($teamLoggerData[$email]['hours'] ?? 0);

        return $this->calculateFromValues(
            $hours,
            (float) ($user->userSalary?->salary_pp ?? 0),
            (float) ($user->userSalary?->increment ?? 0),
            (float) ($user->userSalary?->other ?? 0),
            (float) ($user->userSalary?->adv_inc_other ?? 0)
        );
    }

    /** @return array{salary_pp: float, increment: float, salary_lm: float, hours_lm: float, other: float, adv_inc_other: float, incentive: float, amount_lm: float, amount_lm_display: int, amount_p: float, amount_p_rounded: float, amount_p_display: int} */
    public function calculateFromValues(
        float $hoursLm,
        float $salaryPp,
        float $increment,
        float $other = 0,
        float $advIncOther = 0,
        float $incentive = 0
    ): array {
        $divisor = $this->hoursDivisor();
        $salaryLm = $salaryPp + $increment;
        $amountLm = ($hoursLm * $salaryLm) / $divisor;
        // Amount = ((Salary PP + Increment) * Hours LM / 200) - Advance + Other + Incentive
        $amountP = $amountLm - $advIncOther + $other + $incentive;
        $amountPRounded = $this->roundAmountP($amountP);

        return [
            'salary_pp' => $salaryPp,
            'increment' => $increment,
            'salary_lm' => $salaryLm,
            'hours_lm' => $hoursLm,
            'other' => $other,
            'adv_inc_other' => $advIncOther,
            'incentive' => $incentive,
            'amount_lm' => $amountLm,
            'amount_lm_display' => (int) round($amountLm),
            'amount_p' => $amountP,
            'amount_p_rounded' => $amountPRounded,
            'amount_p_display' => (int) $amountPRounded,
        ];
    }

    public function roundAmountP(float $amountP): float
    {
        $step = (int) config('payroll.round_net_to', 100);
        if ($step <= 0) {
            return round($amountP, 2);
        }

        return round($amountP / $step) * $step;
    }
}
