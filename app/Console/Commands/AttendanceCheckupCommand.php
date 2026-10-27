<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Attendance\AttendanceCheckupService;
use App\Services\Attendance\AttendanceService;
use App\Support\AttendanceFraudAlerts;
use Illuminate\Console\Command;

class AttendanceCheckupCommand extends Command
{
    protected $signature = 'attendance:checkup
                            {--days= : How many recent days to re-check (default from config, today counts as 1)}
                            {--user= : Only check this user id}';

    protected $description = 'Run the fraud checkup for executives; open alerts show a red triangle beside their name';

    public function handle(AttendanceService $attendanceService, AttendanceCheckupService $checkup): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $userId = $this->option('user');

        $employees = $attendanceService->monitorableEmployees($userId ? [(int) $userId] : null);
        if ($employees->isEmpty()) {
            $this->warn('No matching employees.');

            return self::SUCCESS;
        }

        $result = $checkup->run($employees, $days);
        $alerts = AttendanceFraudAlerts::forUsers($employees->pluck('id')->all());

        $this->info(sprintf(
            'Checked %d employee(s) over %d day(s): %d flag(s) raised/refreshed, %d failure(s).',
            $result['employees'],
            $result['days'],
            $result['flags'],
            $result['failed']
        ));

        if ($alerts !== []) {
            $this->warn(count($alerts).' executive(s) currently show the red triangle:');
            $names = User::query()->whereIn('id', array_keys($alerts))->pluck('name', 'id');
            foreach ($alerts as $id => $alert) {
                $this->line(sprintf('  ▲ %s — %s (%d open alert%s)', $names[$id] ?? $id, strtoupper($alert['level']), $alert['count'], $alert['count'] === 1 ? '' : 's'));
            }
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
