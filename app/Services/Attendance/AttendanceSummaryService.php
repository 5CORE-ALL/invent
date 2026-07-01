<?php

namespace App\Services\Attendance;

use App\Models\AttendancePayrollPeriodLine;
use App\Models\AttendanceSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceSummaryService
{
    public function __construct(
        private readonly AttendanceTimelineService $timelineService,
    ) {}

    /**
     * @param  Collection<int, User>  $employees
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, not_logged: int, total_employees: int}
     */
    public function teamSummary(
        Collection $employees,
        string $from,
        string $to,
        ?string $timezone = null,
    ): array {
        $timezone = $timezone ?: (string) config('attendance.timeline_timezone', 'Asia/Kolkata');
        $fromAt = Carbon::parse($from, $timezone)->startOfDay();
        $toAt = Carbon::parse($to, $timezone)->endOfDay();
        $now = now()->timezone($timezone);
        $userIds = $employees->pluck('id')->all();

        $manualByUser = AttendancePayrollPeriodLine::query()
            ->whereIn('user_id', $userIds)
            ->where('period_from', $fromAt->toDateString())
            ->where('period_to', $toAt->toDateString())
            ->get()
            ->keyBy('user_id');

        $sessionsByUser = AttendanceSession::query()
            ->whereIn('user_id', $userIds)
            ->where('started_at', '<=', $toAt)
            ->where(function ($q) use ($fromAt) {
                $q->whereNull('ended_at')->orWhere('ended_at', '>=', $fromAt);
            })
            ->orderBy('started_at')
            ->get()
            ->groupBy('user_id');

        $rows = [];
        $totals = [
            'worked_seconds' => 0,
            'active_seconds' => 0,
            'manual_seconds' => 0,
            'meeting_seconds' => 0,
            'idle_seconds' => 0,
            'employees_worked' => 0,
        ];

        foreach ($employees as $employee) {
            $stats = $this->timelineService->employeePeriodStats($employee, $from, $to, $timezone);
            $manualSeconds = (int) ($manualByUser->get($employee->id)?->manual_seconds ?? 0);

            $active = (int) $stats['active_seconds'];
            $idle = (int) $stats['idle_seconds'];
            $break = (int) $stats['break_seconds'];
            $worked = $active + $idle;
            $includingIdle = $worked;

            $activeMinPct = ($active + $idle) > 0 ? (int) round(($active / ($active + $idle)) * 100) : 0;
            $activeSecPct = ($active + $idle + $break) > 0
                ? (int) round(($active / ($active + $idle + $break)) * 100)
                : 0;

            $userSessions = $sessionsByUser->get($employee->id, collect());
            $activitySpan = $this->activitySpan($userSessions, $fromAt, $toAt, $now, $timezone);
            $isLive = $userSessions->contains(fn (AttendanceSession $s) => $s->isActive());

            if ($worked > 0 || $manualSeconds > 0) {
                $totals['employees_worked']++;
            }

            $totals['worked_seconds'] += $worked + $manualSeconds;
            $totals['active_seconds'] += $active;
            $totals['manual_seconds'] += $manualSeconds;
            $totals['idle_seconds'] += $idle;

            $rows[] = [
                'user_id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'designation' => $employee->designation,
                'is_live' => $isLive,
                'clock_source' => $userSessions->last()?->clock_source ?? 'desktop',
                'activity_span' => $activitySpan['label'],
                'activity_updated' => $activitySpan['updated_label'],
                'activity_is_live' => $activitySpan['is_live'],
                'worked_seconds' => $worked,
                'worked_clock' => $this->formatClock($worked),
                'manual_seconds' => $manualSeconds,
                'manual_clock' => $this->formatClock($manualSeconds),
                'meeting_seconds' => 0,
                'meeting_clock' => $this->formatClock(0),
                'active_min_pct' => $activeMinPct,
                'active_sec_pct' => $activeSecPct,
                'idle_seconds' => $idle,
                'idle_clock' => $this->formatClock($idle),
                'including_idle_clock' => $this->formatClock($includingIdle),
                'has_worked' => $worked > 0 || $manualSeconds > 0,
                'detail_url' => route('attendance.employee', $employee).'?period=custom&from='.$from.'&to='.$to,
                'timeline_url' => route('attendance.employee', $employee).'?period=custom&from='.$from.'&to='.$to,
            ];
        }

        usort($rows, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        $notLogged = collect($rows)->filter(fn (array $r) => ! $r['has_worked'])->count();

        return [
            'rows' => $rows,
            'totals' => [
                'time_worked' => $this->formatDurationLong($totals['worked_seconds']),
                'timer_active' => $this->formatDurationLong($totals['active_seconds']),
                'manual_entry' => $this->formatDurationLong($totals['manual_seconds']),
                'meeting_hours' => $this->formatDurationLong($totals['meeting_seconds']),
                'idle_time' => $this->formatDurationLong($totals['idle_seconds']),
                'employees_worked' => $totals['employees_worked'],
            ],
            'not_logged' => $notLogged,
            'total_employees' => count($rows),
        ];
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     * @return array{label: string, updated_label: string|null, is_live: bool}
     */
    private function activitySpan(
        Collection $sessions,
        Carbon $fromAt,
        Carbon $toAt,
        Carbon $now,
        string $timezone,
    ): array {
        if ($sessions->isEmpty()) {
            return ['label' => '—', 'updated_label' => null, 'is_live' => false];
        }

        $firstSession = $sessions->sortBy('started_at')->first();
        $lastSession = $sessions->sortByDesc(fn (AttendanceSession $s) => $s->ended_at?->timestamp ?? $now->timestamp)->first();
        $isLive = $sessions->contains(fn (AttendanceSession $s) => $s->isActive());

        $start = $firstSession?->started_at?->timezone($timezone) ?? $fromAt->copy();
        $end = $isLive
            ? $now
            : (($lastSession?->ended_at ?? $lastSession?->started_at ?? $now)->timezone($timezone));

        if ($start->lt($fromAt)) {
            $start = $fromAt->copy();
        }
        if ($end->gt($toAt)) {
            $end = $toAt->copy();
        }

        $updatedLabel = null;
        if ($isLive) {
            $updatedLabel = 'Updated '.$end->diffForHumans($now, true).' ago';
        } elseif ($lastSession?->ended_at) {
            $updatedLabel = 'Ended '.$lastSession->ended_at->timezone($timezone)->diffForHumans();
        }

        return [
            'label' => $start->format('H:i').' → '.$end->format('H:i'),
            'updated_label' => $updatedLabel,
            'is_live' => $isLive,
        ];
    }

    public function formatClock(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return sprintf('%02d:%02d', $h, $m);
    }

    public function formatDurationLong(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        if ($h > 0 && $m > 0) {
            return sprintf('%dh %dm', $h, $m);
        }
        if ($h > 0) {
            return sprintf('%dh', $h);
        }

        return sprintf('%dm', $m);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function toCsv(array $rows, string $from, string $to): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'Employee',
            'Activity Span',
            'Total Time Worked',
            'Meeting Hours',
            'Manual Entry',
            'Active Minutes %',
            'Active Seconds %',
            'Idle Deduction',
            'Including Idle',
            'Period From',
            'Period To',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['name'],
                $row['activity_span'],
                $row['worked_clock'],
                $row['meeting_clock'],
                $row['manual_clock'],
                $row['active_min_pct'].'%',
                $row['active_sec_pct'].'%',
                $row['idle_clock'],
                $row['including_idle_clock'],
                $from,
                $to,
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
