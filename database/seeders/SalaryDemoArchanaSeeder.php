<?php

namespace Database\Seeders;

use App\Models\PayrollEmployeeSalary;
use App\Models\PayrollMonth;
use App\Models\TeamLoggerHours;
use App\Models\User;
use App\Models\UserSalary;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Demo salary-tab row matching Team Management → Salary (Archana example).
 *
 * Salary PP 40,000 + Incr 500 = Salary LM 40,500
 * Hours LM 104h → Amt LM = (104 × 40500) / 200 = 21,060
 * Amt P rounded to nearest ₹100 = 21,100
 */
class SalaryDemoArchanaSeeder extends Seeder
{
    public function run(): void
    {
        $monthLabel = Carbon::now()->subMonth()->format('F Y');
        [$periodStart, $periodEnd] = (new PayrollService())->periodDatesFromLabel($monthLabel);

        $user = User::query()->firstOrCreate(
            ['email' => 'archana.demo@5core.com'],
            [
                'name' => 'Archana',
                'password' => bcrypt('demo-archana-2026'),
                'role' => 'user',
                'designation' => 'Demo Employee',
                'is_active' => true,
                'show_in_salary' => true,
                'logined' => 0,
            ]
        );

        $user->update([
            'name' => 'Archana',
            'is_active' => true,
            'show_in_salary' => true,
        ]);

        UserSalary::updateOrCreate(
            ['user_id' => $user->id],
            [
                'salary_pp' => 40000,
                'increment' => 500,
                'other' => null,
                'adv_inc_other' => null,
                'bank_1' => 'HDFC A/c •••• 4521',
                'bank_2' => null,
                'upi_id' => 'archana@5coreupi',
            ]
        );

        TeamLoggerHours::updateOrCreate(
            [
                'employee_email' => strtolower($user->email),
                'month' => $monthLabel,
            ],
            [
                'start_date' => $periodStart ?? Carbon::now()->subMonth()->startOfMonth()->toDateString(),
                'end_date' => $periodEnd ?? Carbon::now()->subMonth()->endOfMonth()->toDateString(),
                'productive_hours' => 104,
                'total_hours' => 104,
                'idle_hours' => 0,
                'active_hours' => 104,
                'fetched_at' => now(),
            ]
        );

        $payrollMonth = PayrollMonth::firstOrCreate(
            ['month_label' => $monthLabel],
            [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'draft',
                'payslip_format' => 'standard',
                'notes' => 'Demo month — Archana sample row for training',
            ]
        );

        $service = new PayrollService();
        $service->syncEmployeesFromUsers($payrollMonth, [$user->id]);
        $service->recalculateMonth($payrollMonth);

        $row = PayrollEmployeeSalary::where('payroll_month_id', $payrollMonth->id)
            ->where('user_id', $user->id)
            ->first();

        $this->command?->info('Salary demo seeded for Archana (archana.demo@5core.com)');
        $this->command?->info("Team Management → Salary tab: month \"{$monthLabel}\", 104h, Amt LM ≈ ₹21,060, Amt P ≈ ₹21,100");
        if ($row) {
            $this->command?->info(sprintf(
                'Payroll module: month "%s" — gross ₹%s, net ₹%s',
                $monthLabel,
                number_format((float) $row->gross_amount, 0),
                number_format((float) $row->net_amount, 0)
            ));
        }
    }
}
