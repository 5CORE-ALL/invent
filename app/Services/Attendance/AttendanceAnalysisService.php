<?php

namespace App\Services\Attendance;

use App\Models\AttendanceActivityLog;
use App\Models\AttendanceDailySummary;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\TeamLoggerService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceAnalysisService
{
    public function __construct(
        private readonly TeamLoggerService $teamLoggerService,
    ) {}

    public function buildDailySummary(User $user, string $date): AttendanceDailySummary
    {
        $carbon = Carbon::parse($date);
        $sessions = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereDate('started_at', $carbon)
            ->get();

        $activeSeconds = (int) $sessions->sum('total_active_seconds');
        $idleSeconds = (int) $sessions->sum('total_idle_seconds');
        $workSeconds = (int) $sessions->sum(fn (AttendanceSession $s) => $s->durationSeconds());

        $firstIn = $sessions->min('started_at');
        $lastOut = $sessions->max(fn (AttendanceSession $s) => $s->ended_at ?? $s->started_at);

        $policy = AttendancePolicy::resolveForUser($user);
        $status = $this->resolveDayStatus($sessions, $policy, $workSeconds, $firstIn);

        $topActivities = $this->aggregateTopActivities($user->id, $carbon);
        $productivityScore = $this->computeProductivityScore($activeSeconds, $idleSeconds, $workSeconds, $policy);

        $teamLoggerHours = $this->fetchTeamLoggerHoursForDate($user, $carbon);

        return AttendanceDailySummary::updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $carbon->toDateString()],
            [
                'first_clock_in' => $firstIn,
                'last_clock_out' => $lastOut,
                'total_work_seconds' => $workSeconds,
                'active_seconds' => $activeSeconds,
                'idle_seconds' => $idleSeconds,
                'session_count' => $sessions->count(),
                'status' => $status,
                'team_logger_hours' => $teamLoggerHours,
                'productivity_score' => $productivityScore,
                'top_activities' => $topActivities,
            ]
        );
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     */
    private function resolveDayStatus(Collection $sessions, ?AttendancePolicy $policy, int $workSeconds, mixed $firstIn): string
    {
        if ($sessions->isEmpty()) {
            return 'absent';
        }

        $minSeconds = ($policy?->min_daily_hours ?? 8) * 3600;
        if ($workSeconds < $minSeconds * 0.5) {
            return 'half_day';
        }

        if ($policy && $firstIn) {
            $expected = Carbon::parse($firstIn->toDateString().' '.Carbon::parse($policy->expected_start)->format('H:i:s'))
                ->addMinutes($policy->grace_minutes);
            if (Carbon::parse($firstIn)->gt($expected)) {
                return 'late';
            }
        }

        return 'present';
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function aggregateTopActivities(int $userId, Carbon $date): array
    {
        $logs = AttendanceActivityLog::query()
            ->where('user_id', $userId)
            ->whereDate('recorded_at', $date)
            ->get(['page_url', 'window_title', 'app_name']);

        $counts = [];
        foreach ($logs as $log) {
            $label = $log->app_name ?: ($log->window_title ?: (parse_url((string) $log->page_url, PHP_URL_PATH) ?: 'Unknown'));
            $label = mb_substr($label, 0, 120);
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        arsort($counts);

        return collect($counts)->take(8)->map(fn ($count, $label) => [
            'label' => $label,
            'count' => $count,
        ])->values()->all();
    }

    private function computeProductivityScore(int $active, int $idle, int $work, ?AttendancePolicy $policy): int
    {
        $total = $active + $idle;
        $activePct = $total > 0 ? ($active / $total) * 100 : 0;
        $minPct = $policy?->min_active_percent ?? 60;

        $hourScore = min(100, ($work / max(1, ($policy?->min_daily_hours ?? 8) * 3600)) * 100);
        $activeScore = min(100, ($activePct / max(1, $minPct)) * 100);

        return (int) round(($hourScore * 0.45) + ($activeScore * 0.55));
    }

    private function fetchTeamLoggerHoursForDate(User $user, Carbon $date): ?float
    {
        try {
            $data = $this->teamLoggerService->fetchByDateRange(
                $date->toDateString(),
                $date->toDateString(),
                true
            );
            $email = strtolower((string) $user->email);
            $mapped = config('payroll.email_mapping', []);
            $lookupEmail = $mapped[$email] ?? $email;

            if (isset($data[$lookupEmail]['total_hours'])) {
                return round((float) $data[$lookupEmail]['total_hours'], 2);
            }
        } catch (\Throwable) {
            // TeamLogger optional
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionAnalytics(AttendanceSession $session): array
    {
        $logs = $session->activityLogs()->orderBy('recorded_at')->get();
        $hourlyIdle = [];

        foreach ($logs as $log) {
            $hour = $log->recorded_at->format('H:00');
            if (! $log->is_active) {
                $hourlyIdle[$hour] = ($hourlyIdle[$hour] ?? 0) + 1;
            }
        }

        return [
            'duration_seconds' => $session->durationSeconds(),
            'active_percent' => $session->activePercent(),
            'heartbeat_count' => $session->heartbeat_count,
            'missed_heartbeats' => $session->missed_heartbeat_count,
            'hourly_idle_counts' => $hourlyIdle,
            'work_location' => $session->work_location,
        ];
    }
}
