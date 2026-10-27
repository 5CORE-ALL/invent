<?php

namespace App\Services\Attendance;

use App\Models\User;
use App\Support\AttendanceFraudAlerts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Runs every fraud check for a set of executives over their most recent days. The results are
 * stored as attendance AI flags; AttendanceFraudAlerts turns the open ones into the red triangle.
 */
class AttendanceCheckupService
{
    public function __construct(
        private readonly AttendanceAiMisuseService $misuseService,
    ) {}

    /**
     * @param  Collection<int, User>  $employees
     * @return array{employees: int, days: int, flags: int, failed: int}
     */
    public function run(Collection $employees, ?int $days = null): array
    {
        $days = max(1, $days ?? (int) config('attendance.checkup.run_days', 2));
        $flags = 0;
        $failed = 0;

        foreach ($employees as $employee) {
            $flags += $this->runForUser($employee, $days, $failed);
        }

        return ['employees' => $employees->count(), 'days' => $days, 'flags' => $flags, 'failed' => $failed];
    }

    /**
     * @return int number of flags raised or refreshed for the user
     */
    public function runForUser(User $user, ?int $days = null, int &$failed = 0): int
    {
        $days = max(1, $days ?? (int) config('attendance.checkup.run_days', 2));
        $count = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->toDateString();
            try {
                $count += count($this->misuseService->checkupUserDay($user, $date));
            } catch (\Throwable $e) {
                // One employee's bad data must not stop the checkup for everyone else.
                $failed++;
                Log::warning('Attendance checkup failed', [
                    'user_id' => $user->id,
                    'date' => $date,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * @return array<string, mixed>|null the triangle alert for the user after the checkup, if any
     */
    public function alertFor(User $user): ?array
    {
        return AttendanceFraudAlerts::forUsers([(int) $user->id])[(int) $user->id] ?? null;
    }
}
