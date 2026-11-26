<?php

namespace App\Services\Attendance;

use App\Models\AttendanceActivityLog;
use App\Models\AttendancePayrollPeriodLine;
use App\Models\AttendanceScreenshot;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\AttendanceFraudAlerts;
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
        $timezone = $timezone ?: AttendanceTimelineService::defaultTimezone();
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

        // Stored timestamps are naive app-timezone datetimes; convert the viewer-timezone window first.
        $appTz = (string) config('app.timezone', 'UTC');
        $fromBound = $fromAt->copy()->timezone($appTz)->format('Y-m-d H:i:s');
        $toBound = $toAt->copy()->timezone($appTz)->format('Y-m-d H:i:s');

        $sessionsByUser = AttendanceSession::query()
            ->whereIn('user_id', $userIds)
            ->where('started_at', '<=', $toBound)
            ->where(function ($q) use ($fromBound) {
                $q->whereNull('ended_at')->orWhere('ended_at', '>=', $fromBound);
            })
            ->orderBy('started_at')
            ->get()
            ->groupBy('user_id');

        $activeSessions = $userIds === []
            ? collect()
            : AttendanceSession::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('status', ['active', 'paused'])
                ->orderByDesc('started_at')
                ->get()
                ->unique('user_id')
                ->keyBy('user_id');

        $latestLogs = $userIds === []
            ? collect()
            : AttendanceActivityLog::query()
                ->whereIn('id', AttendanceActivityLog::query()
                    ->selectRaw('MAX(id)')
                    ->whereIn('user_id', $userIds)
                    ->groupBy('user_id'))
                ->get()
                ->keyBy('user_id');

        $latestShots = $userIds === []
            ? collect()
            : AttendanceScreenshot::query()
                ->whereIn('id', AttendanceScreenshot::query()
                    ->selectRaw('MAX(id)')
                    ->whereIn('user_id', $userIds)
                    ->groupBy('user_id'))
                ->get()
                ->keyBy('user_id');

        $fraudAlerts = AttendanceFraudAlerts::forUsers($userIds);
        $joining = $this->joiningInfo($userIds, $to);

        // Keyboard / click totals for the same period as the table. `tracked_beats` counts heartbeats
        // where the agent's input hook was verified working, so "0" can be told apart from "not tracked".
        $trackedExpr = AttendanceService::inputTrackedColumnExists()
            ? 'SUM(CASE WHEN input_tracked = 1 THEN 1 ELSE 0 END)'
            : 'SUM(CASE WHEN keystroke_count > 0 OR mouse_click_count > 0 THEN 1 ELSE 0 END)';
        $inputByUser = $userIds === []
            ? collect()
            : AttendanceActivityLog::query()
                ->selectRaw("user_id, COUNT(*) as beats, SUM(keystroke_count) as keys_total, SUM(mouse_click_count) as clicks_total, {$trackedExpr} as tracked_beats")
                ->whereIn('user_id', $userIds)
                ->where('recorded_at', '>=', $fromBound)
                ->where('recorded_at', '<=', $toBound)
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

        $rows = [];
        $totals = [
            'worked_seconds' => 0,
            'active_seconds' => 0,
            'manual_seconds' => 0,
            'meeting_seconds' => 0,
            'idle_seconds' => 0,
            'employees_worked' => 0,
        ];

        // "WT Current": work time for the current month so far, independent of the Range filter.
        $monthFrom = $now->copy()->startOfMonth()->toDateString();
        $monthTo = $now->toDateString();

        foreach ($employees as $employee) {
            $stats = $this->timelineService->employeePeriodStats($employee, $from, $to, $timezone);
            $manualSeconds = (int) ($manualByUser->get($employee->id)?->manual_seconds ?? 0);

            $active = (int) $stats['active_seconds'];
            $idle = (int) $stats['idle_seconds'];
            $worked = $active + $idle;
            $workTime = max(0, $worked - $idle + $manualSeconds);

            // Current month work time = Login time (active + idle) - Idle. Reuses $stats when the
            // Range filter is already "this month".
            $monthStats = ($from === $monthFrom && $to === $monthTo)
                ? $stats
                : $this->timelineService->employeePeriodStats($employee, $monthFrom, $monthTo, $timezone, null, false);
            $monthIdle = (int) $monthStats['idle_seconds'];
            $monthLogin = (int) $monthStats['active_seconds'] + $monthIdle;
            $wtCurrent = max(0, $monthLogin - $monthIdle);

            $input = $inputByUser->get($employee->id);
            $clickCount = (int) ($input?->clicks_total ?? 0);
            $keyCount = (int) ($input?->keys_total ?? 0);
            // Real numbers whenever input was tracked or any count was recorded; otherwise the agent
            // sent heartbeats without input data (old agent / no Accessibility permission).
            $inputTracked = (int) ($input?->tracked_beats ?? 0) > 0 || $clickCount > 0 || $keyCount > 0;
            $inputBeats = (int) ($input?->beats ?? 0);

            $userSessions = $sessionsByUser->get($employee->id, collect());
            $activitySpan = $this->activitySpan($userSessions, $fromAt, $toAt, $now, $timezone);
            $activeSession = $activeSessions->get($employee->id);
            $isLive = (bool) $activeSession;
            $live = $this->liveStatus($activeSession, $latestLogs->get($employee->id), $now);
            $lastShot = $this->lastScreenshotPayload($latestShots->get($employee->id), $timezone);

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
                'org_level' => $employee->org_level,
                'designation' => $employee->designation,
                'tm_url' => route('tasks.index', array_filter([
                    'assignee' => $employee->name,
                    'user_id' => $employee->id,
                ])),
                'ts_url' => route('tasks.summary', array_filter([
                    'member' => $employee->name,
                    'user_id' => $employee->id,
                ])),
                'avatar_url' => ! empty($employee->avatar)
                    ? asset('storage/'.$employee->avatar)
                    : asset('images/users/avatar-2.jpg'),
                'fraud_alert' => $fraudAlerts[$employee->id] ?? null,
                'is_live' => $isLive,
                'live_status' => $live['status'],
                'live_label' => $live['label'],
                'live_sort' => $live['sort'],
                'last_image_url' => $lastShot['url'],
                'last_image_thumb' => $lastShot['thumb'],
                'last_image_time' => $lastShot['time'],
                'last_image_label' => $lastShot['label'],
                'last_image_sort' => $lastShot['sort'],
                'clock_source' => $userSessions->last()?->clock_source ?? 'desktop',
                'activity_span' => $activitySpan['label'],
                'activity_updated' => $activitySpan['updated_label'],
                'activity_is_live' => $activitySpan['is_live'],
                'activity_start_minutes' => $this->spanStartMinutes($activitySpan['label']),
                'worked_seconds' => $worked,
                'worked_clock' => $this->formatClock($worked),
                'manual_seconds' => $manualSeconds,
                'manual_clock' => $this->formatClock($manualSeconds),
                'meeting_seconds' => 0,
                'meeting_clock' => $this->formatClock(0),
                // (Clicks + Keystrokes) ÷ 60, shown with a % sign. Not capped.
                'input_pct' => (int) round(($clickCount + $keyCount) / 60),
                'click_count' => $clickCount,
                'key_count' => $keyCount,
                'input_tracked' => $inputTracked,
                'input_has_activity' => $inputBeats > 0,
                'idle_seconds' => $idle,
                'idle_clock' => $this->formatClock($idle),
                'idle_count' => (int) ($stats['idle_count'] ?? 0),
                'wt_current_seconds' => $wtCurrent,
                'wt_current_clock' => $this->formatClock($wtCurrent),
                'work_time_seconds' => $workTime,
                'work_time_clock' => $this->formatClock($workTime),
                'including_idle_seconds' => $workTime,
                'including_idle_clock' => $this->formatClock($workTime),
                'has_worked' => $worked > 0 || $manualSeconds > 0,
                'detail_url' => route('attendance.employee', $employee).'?period=custom&from='.$from.'&to='.$to,
                'timeline_url' => url('/attendance/employee/'.$employee->id).'?period=custom&from='.$from.'&to='.$to.'&timezone='.$timezone,
                'timeline_api' => url('/attendance/employee/'.$employee->id.'/timeline'),
                'captures_url' => url('/attendance/employee/'.$employee->id.'/captures').'?date='.$to.'&timezone='.$timezone,
                'live_url' => route('attendance.live.show', $employee),
            ] + ($joining['by_user'][$employee->id] ?? $this->emptyJoiningFields());
        }

        usort($rows, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        $notLogged = collect($rows)->filter(fn (array $r) => ! $r['has_worked'])->count();
        $joining['meta']['late_count'] = collect($rows)->where('join_late', true)->count();

        return [
            'joining' => $joining['meta'],
            'rows' => $rows,
            'totals' => $this->formatKpiTotals($totals),
            'not_logged' => $notLogged,
            'total_employees' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyJoiningFields(): array
    {
        return [
            'join_status' => 'none',
            'join_late' => false,
            'join_late_minutes' => 0,
            'join_pt' => null,
            'join_ist' => null,
            'join_label' => '—',
        ];
    }

    /**
     * Late joining check: first login of the day against the joining time (9:00 AM California by default).
     * The day checked is the last day of the range, capped at today in the joining timezone.
     *
     * @param  array<int, int>  $userIds
     * @return array{by_user: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function joiningInfo(array $userIds, string $to): array
    {
        $tz = (string) config('attendance.joining_timezone', 'America/Los_Angeles');
        $time = (string) config('attendance.joining_time', '09:00');
        $appTz = (string) config('app.timezone', 'UTC');
        $grace = max(0, (int) config('attendance.joining_grace_minutes', 10));

        $nowTz = now()->timezone($tz);
        $day = min($to, $nowTz->toDateString());
        $isToday = $day === $nowTz->toDateString();
        $target = Carbon::parse($day.' '.$time, $tz);
        $weekend = (bool) config('attendance.joining_skip_weekends', true) && $target->isWeekend();

        $meta = [
            'date' => $day,
            'is_today' => $isToday,
            'weekend' => $weekend,
            'grace_minutes' => $grace,
            'late_after_pt' => $target->copy()->addMinutes($grace)->format('g:i A'),
            'late_after_ist' => $target->copy()->addMinutes($grace)->timezone('Asia/Kolkata')->format('g:i A'),
            'tz_label' => $tz === 'America/Los_Angeles' ? 'California' : $tz,
            'target_pt' => $target->format('g:i A'),
            'target_ist' => $target->copy()->timezone('Asia/Kolkata')->format('g:i A'),
            'now_pt' => $nowTz->format('g:i A'),
            'now_ist' => now()->timezone('Asia/Kolkata')->format('g:i A'),
            'late_count' => 0,
        ];

        if ($userIds === []) {
            return ['by_user' => [], 'meta' => $meta];
        }

        $first = AttendanceSession::query()
            ->whereIn('user_id', $userIds)
            ->where('started_at', '>=', Carbon::parse($day, $tz)->startOfDay()->timezone($appTz)->format('Y-m-d H:i:s'))
            ->where('started_at', '<=', Carbon::parse($day, $tz)->endOfDay()->timezone($appTz)->format('Y-m-d H:i:s'))
            ->orderBy('started_at')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $byUser = [];
        foreach ($userIds as $userId) {
            $fields = $this->emptyJoiningFields();
            $session = $first->get($userId);

            if ($session?->started_at) {
                $in = $session->started_at->copy()->timezone($tz);
                $lateSeconds = max(0, $in->timestamp - $target->timestamp);
                $lateMinutes = intdiv($lateSeconds, 60);
                // Late only after the grace period (9:00 AM + 10 min); the "Late" time counts from 9:00 AM.
                $late = ! $weekend && $lateSeconds > $grace * 60;
                $fields['join_pt'] = $in->format('h:i A');
                $fields['join_ist'] = $in->copy()->timezone('Asia/Kolkata')->format('h:i A');
                $fields['join_status'] = $weekend ? 'off' : ($late ? 'late' : 'on_time');
                $fields['join_late'] = $late;
                $fields['join_late_minutes'] = $late ? $lateMinutes : 0;
                $fields['join_label'] = $late ? 'Late '.$this->formatLateMinutes($lateMinutes) : ($weekend ? 'Weekend' : 'On time');
            } elseif ($weekend) {
                $fields['join_status'] = 'off';
                $fields['join_label'] = 'Weekend';
            } elseif ($isToday && $nowTz->gt($target)) {
                // Past the joining time plus grace today and not logged in yet.
                $lateSeconds = $nowTz->timestamp - $target->timestamp;
                $lateMinutes = intdiv($lateSeconds, 60);
                if ($lateSeconds > $grace * 60) {
                    $fields['join_status'] = 'not_joined';
                    $fields['join_late'] = true;
                    $fields['join_late_minutes'] = $lateMinutes;
                    $fields['join_label'] = 'Not joined · '.$this->formatLateMinutes($lateMinutes);
                } else {
                    $fields['join_status'] = 'pending';
                    $fields['join_label'] = 'Not yet';
                }
            } elseif ($isToday) {
                $fields['join_status'] = 'pending';
                $fields['join_label'] = 'Not yet';
            }

            $byUser[(int) $userId] = $fields;
        }

        return ['by_user' => $byUser, 'meta' => $meta];
    }

    private function formatLateMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? sprintf('%dh %02dm', $h, $m) : sprintf('%dm', $m);
    }

    /**
     * KPI totals only (no live/screenshot/row payload). Used for L30 badges.
     *
     * @param  Collection<int, User>  $employees
     * @return array<string, mixed>
     */
    public function kpiTotals(
        Collection $employees,
        string $from,
        string $to,
        ?string $timezone = null,
    ): array {
        $timezone = $timezone ?: AttendanceTimelineService::defaultTimezone();
        $userIds = $employees->pluck('id')->all();

        $manualByUser = $userIds === []
            ? collect()
            : AttendancePayrollPeriodLine::query()
                ->whereIn('user_id', $userIds)
                ->where('period_from', $from)
                ->where('period_to', $to)
                ->get()
                ->keyBy('user_id');

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
            $worked = $active + $idle;

            if ($worked > 0 || $manualSeconds > 0) {
                $totals['employees_worked']++;
            }

            $totals['worked_seconds'] += $worked + $manualSeconds;
            $totals['active_seconds'] += $active;
            $totals['manual_seconds'] += $manualSeconds;
            $totals['idle_seconds'] += $idle;
        }

        return $this->formatKpiTotals($totals);
    }

    /**
     * Rolling last-30-days window ending on $asOf (any calendar day).
     *
     * @return array{0: string, 1: string}
     */
    public function l30DateRange(?string $timezone = null, ?string $asOf = null): array
    {
        $timezone = $timezone ?: AttendanceTimelineService::defaultTimezone();
        $to = Carbon::parse($asOf ?: now()->timezone($timezone)->toDateString(), $timezone)->toDateString();
        $from = Carbon::parse($to, $timezone)->subDays(30)->toDateString();

        return [$from, $to];
    }

    /**
     * @param  array<string, int>  $totals
     * @return array<string, mixed>
     */
    private function formatKpiTotals(array $totals): array
    {
        return [
            'time_worked' => $this->formatDurationLong($totals['worked_seconds']),
            'timer_active' => $this->formatDurationLong($totals['active_seconds']),
            'manual_entry' => $this->formatDurationLong($totals['manual_seconds']),
            'meeting_hours' => $this->formatDurationLong($totals['meeting_seconds']),
            'idle_time' => $this->formatDurationLong($totals['idle_seconds']),
            'employees_worked' => $totals['employees_worked'],
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

        // Last activity only: the most recent session's login → logout (not the first login of the range).
        $lastSession = $sessions->sortByDesc(fn (AttendanceSession $s) => $s->started_at?->timestamp ?? 0)->first();
        $isLive = $lastSession->isActive();

        $start = $lastSession->started_at->copy()->timezone($timezone);
        $end = $isLive
            ? $now
            : (($lastSession->ended_at ?? $lastSession->started_at)->copy()->timezone($timezone));

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

    /**
     * @return array{url: string|null, thumb: string|null, time: string, label: string, sort: int}
     */
    private function lastScreenshotPayload(?AttendanceScreenshot $shot, string $timezone): array
    {
        if (! $shot?->captured_at) {
            return ['url' => null, 'thumb' => null, 'time' => '—', 'label' => 'No screenshot', 'sort' => 0];
        }

        $at = $shot->captured_at->timezone($timezone);

        return [
            'url' => $shot->imageUrl(),
            'thumb' => $shot->thumbnailUrl() ?: $shot->imageUrl(),
            'time' => $at->format('H:i'),
            'label' => $at->format('M j, h:i A').($shot->activityLabel() ? ' — '.$shot->activityLabel() : ''),
            'sort' => $shot->captured_at->timestamp,
        ];
    }

    /**
     * @return array{status: string, label: string, sort: int}
     */
    private function liveStatus(?AttendanceSession $activeSession, ?AttendanceActivityLog $latestLog, Carbon $now): array
    {
        if (! $activeSession) {
            return ['status' => 'absent', 'label' => 'Absent', 'sort' => 3];
        }

        $state = $activeSession->last_activity_state
            ?? ($activeSession->status === 'paused' ? 'break' : 'working');
        $idleSeconds = (int) ($latestLog?->idle_seconds ?? 0);
        $staleSeconds = $latestLog?->recorded_at
            ? max(0, $latestLog->recorded_at->diffInSeconds($now))
            : max(0, ($activeSession->updated_at ?? $activeSession->started_at)->diffInSeconds($now));

        if ($state === 'idle' || $staleSeconds >= 300) {
            $idleFor = max($idleSeconds, $staleSeconds);
            if ($idleFor >= 300) {
                return ['status' => 'idle', 'label' => 'Idle', 'sort' => 2];
            }
        }

        return ['status' => 'working', 'label' => 'Working', 'sort' => 1];
    }

    private function spanStartMinutes(string $label): int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $label, $m)) {
            return ((int) $m[1] * 60) + (int) $m[2];
        }

        return -1;
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

    public function defaultDateRange(?string $timezone = null): array
    {
        return $this->l30DateRange($timezone);
    }
}
