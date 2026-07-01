<?php

namespace App\Services\Attendance;

use App\Models\AttendancePayrollPeriodLine;
use App\Models\AttendancePayrollProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendancePayrollService
{
    public function __construct(
        private readonly AttendanceTimelineService $timelineService,
    ) {}

    /**
     * @param  Collection<int, User>  $employees
     * @return array<int, array<string, mixed>>
     */
    public function buildTeamPayroll(
        Collection $employees,
        string $from,
        string $to,
        ?string $timezone = null,
    ): array {
        $timezone = $timezone ?: (string) config('attendance.timeline_timezone', 'Asia/Kolkata');
        $userIds = $employees->pluck('id')->all();

        $profiles = AttendancePayrollProfile::query()
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $lines = AttendancePayrollPeriodLine::query()
            ->whereIn('user_id', $userIds)
            ->where('period_from', $from)
            ->where('period_to', $to)
            ->get()
            ->keyBy('user_id');

        $rows = [];

        foreach ($employees as $employee) {
            $stats = $this->timelineService->employeePeriodStats($employee, $from, $to, $timezone);
            $workedSeconds = (int) $stats['active_seconds'] + (int) $stats['idle_seconds'];

            $profile = $profiles->get($employee->id);
            $line = $lines->get($employee->id);

            $hourlyRate = (float) ($line?->hourly_rate ?? $profile?->hourly_rate ?? 0);
            $currency = (string) ($line?->currency ?? $profile?->currency ?? 'USD');
            $manualSeconds = (int) ($line?->manual_seconds ?? 0);
            $adjustment = (float) ($line?->adjustment ?? 0);

            $payableSeconds = $workedSeconds + $manualSeconds;
            $payableHours = $payableSeconds / 3600;
            $totalPay = round(($payableHours * $hourlyRate) + $adjustment, 2);

            $rows[] = [
                'user_id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'designation' => $employee->designation,
                'worked_seconds' => $workedSeconds,
                'worked_label' => $this->formatDuration($workedSeconds),
                'manual_seconds' => $manualSeconds,
                'manual_label' => $this->formatDuration($manualSeconds),
                'hourly_rate' => $hourlyRate,
                'currency' => $currency,
                'adjustment' => $adjustment,
                'total_pay' => $totalPay,
                'total_pay_label' => $currency.' '.number_format($totalPay, 2),
            ];
        }

        usort($rows, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $rows;
    }

    public function saveLine(
        User $employee,
        string $from,
        string $to,
        int $manualSeconds,
        float $hourlyRate,
        string $currency,
        float $adjustment,
        ?int $updatedBy = null,
    ): AttendancePayrollPeriodLine {
        $line = AttendancePayrollPeriodLine::query()->updateOrCreate(
            [
                'user_id' => $employee->id,
                'period_from' => $from,
                'period_to' => $to,
            ],
            [
                'manual_seconds' => max(0, $manualSeconds),
                'hourly_rate' => max(0, $hourlyRate),
                'currency' => strtoupper(substr($currency, 0, 3)),
                'adjustment' => $adjustment,
                'updated_by' => $updatedBy,
            ],
        );

        AttendancePayrollProfile::query()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'hourly_rate' => max(0, $hourlyRate),
                'currency' => strtoupper(substr($currency, 0, 3)),
            ],
        );

        return $line;
    }

    public function parseManualDuration(string $input): int
    {
        $input = trim($input);
        if ($input === '') {
            return 0;
        }

        if (preg_match('/^(\d+)\s*h(?:\s*(\d+))?$/i', $input, $matches)) {
            $hours = (int) $matches[1];
            $minutes = isset($matches[2]) ? (int) $matches[2] : 0;

            return ($hours * 3600) + ($minutes * 60);
        }

        if (is_numeric($input)) {
            return (int) round((float) $input * 3600);
        }

        return 0;
    }

    public function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return sprintf('%dh %d', $h, $m);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function toCsv(array $rows, string $from, string $to, string $team): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'Name',
            'Email',
            'Team',
            'Period From',
            'Period To',
            'Total Time Worked',
            'Manual Time',
            'Hourly Pay Rate',
            'Currency',
            'Adjustment',
            'Total Pay',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['name'],
                $row['email'],
                $row['designation'] ?? '',
                $from,
                $to,
                $row['worked_label'],
                $row['manual_label'],
                number_format((float) $row['hourly_rate'], 2, '.', ''),
                $row['currency'],
                number_format((float) $row['adjustment'], 2, '.', ''),
                number_format((float) $row['total_pay'], 2, '.', ''),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    public function defaultDateRange(?string $timezone = null): array
    {
        $timezone = $timezone ?: (string) config('attendance.timeline_timezone', 'Asia/Kolkata');
        $to = now()->timezone($timezone)->toDateString();
        $from = Carbon::parse($to, $timezone)->subDays(7)->toDateString();

        return [$from, $to];
    }
}
