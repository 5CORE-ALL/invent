<?php

namespace App\Services\Attendance;

use App\Models\AttendanceActivityLog;
use App\Models\AttendanceDailySummary;
use App\Models\AttendanceDevice;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\AttendanceAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceAnalysisService $analysisService,
    ) {}

    public function activeSession(User $user): ?AttendanceSession
    {
        return AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->latest('started_at')
            ->first();
    }

    public function clockIn(User $user, string $workLocation = 'wfh', ?string $ip = null, ?string $userAgent = null, ?int $deviceId = null, string $clockSource = 'desktop'): AttendanceSession
    {
        if ($clockSource !== 'desktop') {
            throw new \RuntimeException('Clock-in is only available from the desktop app. Mobile and browser clock-in are not allowed.');
        }

        $existing = $this->activeSession($user);
        if ($existing) {
            return $existing;
        }

        $policy = AttendancePolicy::resolveForUser($user);
        if ($policy && ! $policy->wfh_allowed && $workLocation === 'wfh') {
            throw new \RuntimeException('Work from home is not allowed under your attendance policy.');
        }

        return AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_device_id' => $deviceId,
            'started_at' => now(),
            'status' => 'active',
            'work_location' => $workLocation,
            'clock_source' => $clockSource,
            'last_activity_state' => 'working',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    public function clockOut(User $user): ?AttendanceSession
    {
        $session = $this->activeSession($user);
        if (! $session) {
            return null;
        }

        $session->update([
            'ended_at' => now(),
            'status' => 'completed',
        ]);

        $this->analysisService->buildDailySummary($user, $session->started_at->toDateString());

        return $session->fresh();
    }

    public function pause(User $user): ?AttendanceSession
    {
        $session = $this->activeSession($user);
        if (! $session || $session->status === 'paused') {
            return $session;
        }

        $session->update([
            'status' => 'paused',
            'paused_at' => now(),
            'last_activity_state' => 'break',
        ]);

        return $session->fresh();
    }

    public function resume(User $user): ?AttendanceSession
    {
        $session = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'paused')
            ->latest('started_at')
            ->first();

        if (! $session) {
            return $this->activeSession($user);
        }

        $breakSeconds = 0;
        if ($session->paused_at) {
            $breakSeconds = max(0, $session->paused_at->diffInSeconds(now()));
        }

        $session->update([
            'status' => 'active',
            'paused_at' => null,
            'last_activity_state' => 'working',
            'total_break_seconds' => $session->total_break_seconds + $breakSeconds,
        ]);

        return $session->fresh();
    }

    /**
     * @param  array{is_active?: bool, idle_seconds?: int, elapsed_seconds?: int, activity_state?: string|null, window_title?: string|null, page_url?: string|null, source?: string, app_name?: string|null, process_name?: string|null, device_id?: int|null, keystroke_count?: int, mouse_click_count?: int}  $payload
     */
    public function recordHeartbeat(User $user, array $payload): array
    {
        $session = $this->activeSession($user);
        if (! $session) {
            return ['ok' => false, 'message' => 'No active session'];
        }

        if ($session->status === 'paused') {
            return [
                'ok' => true,
                'paused' => true,
                'session_id' => $session->id,
                'active_seconds' => $session->total_active_seconds,
                'idle_seconds' => $session->total_idle_seconds,
                'break_seconds' => $session->total_break_seconds,
                'activity_state' => 'break',
                'today' => $this->todayStats($user),
            ];
        }

        $systemIdle = max(0, (int) ($payload['idle_seconds'] ?? 0));
        $idleThreshold = max(1, (int) config('attendance.idle_threshold_seconds', 30));
        $requestedState = in_array($payload['activity_state'] ?? 'working', ['working', 'idle', 'break'], true)
            ? $payload['activity_state']
            : 'working';
        $source = 'desktop';

        // Count idle from OS idle time so old desktop agents (v1.2.x) still capture idle
        // even when they never flip activity_state / never show the idle popup.
        if ($requestedState === 'break') {
            $activityState = 'break';
            $isActive = false;
        } elseif ($systemIdle >= $idleThreshold || $requestedState === 'idle' || ! (bool) ($payload['is_active'] ?? true)) {
            $activityState = 'idle';
            $isActive = false;
        } else {
            $activityState = 'working';
            $isActive = true;
        }

        $interval = max(1, min(120, (int) ($payload['elapsed_seconds'] ?? config('attendance.heartbeat_interval_seconds', 15))));

        DB::transaction(function () use ($session, $user, $payload, $isActive, $interval, $source, $activityState, $systemIdle) {
            AttendanceActivityLog::create([
                'attendance_session_id' => $session->id,
                'user_id' => $user->id,
                'recorded_at' => now(),
                'is_active' => $isActive,
                'activity_state' => $activityState,
                'idle_seconds' => $systemIdle,
                'window_title' => isset($payload['window_title']) ? mb_substr((string) $payload['window_title'], 0, 500) : null,
                'page_url' => isset($payload['page_url']) ? mb_substr((string) $payload['page_url'], 0, 1000) : null,
                'source' => $source,
                'app_name' => isset($payload['app_name']) ? mb_substr((string) $payload['app_name'], 0, 200) : null,
                'process_name' => isset($payload['process_name']) ? mb_substr((string) $payload['process_name'], 0, 200) : null,
                'attendance_device_id' => $payload['device_id'] ?? null,
                'keystroke_count' => max(0, (int) ($payload['keystroke_count'] ?? 0)),
                'mouse_click_count' => max(0, (int) ($payload['mouse_click_count'] ?? 0)),
            ]);

            $session->increment('heartbeat_count');
            if ($isActive) {
                $session->increment('total_active_seconds', $interval);
            } else {
                $session->increment('total_idle_seconds', $interval);
            }
            $session->update(['last_activity_state' => $activityState]);
            $session->touch();
        });

        $fresh = $session->fresh();

        return [
            'ok' => true,
            'session_id' => $fresh->id,
            'active_seconds' => $fresh->total_active_seconds,
            'idle_seconds' => $fresh->total_idle_seconds,
            'break_seconds' => $fresh->total_break_seconds,
            'activity_state' => $fresh->last_activity_state,
            'today' => $this->todayStats($user),
            'agent_update' => $this->agentUpdatePayload($payload['agent_version'] ?? null),
        ];
    }

    /**
     * @return array{available: bool, current_version: string|null, latest_version: string, download_page_url: string, download_url: string, message: string}
     */
    public function agentUpdatePayload(?string $installedVersion): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $latest = (string) config('attendance.agent_version', '1.0.0');
        $current = $installedVersion !== null && $installedVersion !== '' ? (string) $installedVersion : null;
        $available = $current !== null && version_compare($latest, $current, '>');

        return [
            'available' => $available,
            'current_version' => $current,
            'latest_version' => $latest,
            'download_page_url' => $base.'/attendance/agent',
            'download_url' => $base.'/attendance/agent/download',
            'message' => 'A new version of 5Core Attendance is available. Run the installer to update — no uninstall needed.',
        ];
    }

    /**
     * Portal UI status for the signed-in user's desktop agent install.
     *
     * @return array{
     *   has_installed: bool,
     *   installed_version: string|null,
     *   latest_version: string,
     *   update_available: bool,
     *   up_to_date: bool,
     *   device_name: string|null
     * }
     */
    public function desktopAgentStatusForUser(User $user): array
    {
        $latest = (string) config('attendance.agent_version', '1.0.0');

        // Only treat as installed if the desktop agent was seen recently and not
        // marked uninstalled. Stale DB rows after uninstall must not keep showing Update.
        $device = AttendanceDevice::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subDays(2))
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->first();

        $hasInstalled = $device !== null;
        $installed = $device?->agent_version ? (string) $device->agent_version : null;

        $updateAvailable = $hasInstalled && (
            $installed === null || version_compare($latest, $installed, '>')
        );
        $upToDate = $hasInstalled && $installed !== null && version_compare($latest, $installed, '<=');

        return [
            'has_installed' => $hasInstalled,
            'installed_version' => $installed,
            'latest_version' => $latest,
            'update_available' => $updateAvailable,
            'up_to_date' => $upToDate,
            'device_name' => $device?->device_name,
        ];
    }

    /**
     * User confirmed the desktop app is not installed (uninstalled / different PC).
     * Stops Update prompts until the agent connects again.
     */
    public function markDesktopAgentUninstalled(User $user): int
    {
        return AttendanceDevice::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    public function autoCloseStaleSessions(): int
    {
        $cutoff = now()->subMinutes((int) config('attendance.auto_close_minutes', 30));
        $closed = 0;

        $sessions = AttendanceSession::query()
            ->whereIn('status', ['active', 'paused'])
            ->where('updated_at', '<', $cutoff)
            ->get();

        foreach ($sessions as $session) {
            $session->update([
                'ended_at' => $session->updated_at,
                'status' => 'auto_closed',
            ]);
            $session->increment('missed_heartbeat_count');
            $user = $session->user;
            if ($user) {
                $this->analysisService->buildDailySummary($user, $session->started_at->toDateString());
            }
            $closed++;
        }

        return $closed;
    }

    /**
     * @return array<string, mixed>
     */
    public function employeeDashboardData(User $user, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();
        $carbon = Carbon::parse($date);

        $sessions = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereDate('started_at', $carbon)
            ->orderBy('started_at')
            ->get();

        $summary = AttendanceDailySummary::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $carbon)
            ->first();

        $weekSummaries = AttendanceDailySummary::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [
                $carbon->copy()->startOfWeek()->toDateString(),
                $carbon->copy()->endOfWeek()->toDateString(),
            ])
            ->orderBy('work_date')
            ->get();

        $policy = AttendancePolicy::resolveForUser($user);
        $activeSession = $this->activeSession($user);

        return [
            'user' => $user,
            'date' => $date,
            'sessions' => $sessions,
            'summary' => $summary,
            'week_summaries' => $weekSummaries,
            'policy' => $policy,
            'active_session' => $activeSession,
            'monitoring_enabled' => $policy?->monitoring_enabled ?? true,
            'can_track' => AttendanceAccess::isInternalEmployee($user),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function monitorableEmployees(?array $userIds = null)
    {
        $query = User::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $domain = config('attendance.internal_email_domain', '@5core.com');
                $q->where('email', 'like', '%'.$domain)
                    ->orWhere('show_in_salary', true);
            });

        if ($userIds !== null) {
            $query->whereIn('id', $userIds);
        }

        return $query->orderBy('name')->get(['id', 'name', 'email', 'designation', 'avatar']);
    }

    /**
     * Calendar-day totals (active / idle / break) for the employee.
     *
     * @return array{date: string, date_label: string, active_seconds: int, idle_seconds: int, break_seconds: int}
     */
    public function todayStats(User $user, ?string $date = null): array
    {
        $carbon = Carbon::parse($date ?: now()->toDateString());
        $dateStr = $carbon->toDateString();

        $sessions = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('started_at', $dateStr)
                    ->orWhereIn('status', ['active', 'paused']);
            })
            ->get();

        $active = 0;
        $idle = 0;
        $break = 0;

        foreach ($sessions as $session) {
            if ($session->started_at->toDateString() !== $dateStr && ! $session->isActive()) {
                continue;
            }

            $active += (int) $session->total_active_seconds;
            $idle += (int) $session->total_idle_seconds;
            $break += (int) ($session->total_break_seconds ?? 0);

            if ($session->status === 'paused' && $session->paused_at) {
                $break += max(0, $session->paused_at->diffInSeconds(now()));
            }
        }

        return [
            'date' => $dateStr,
            'date_label' => $carbon->format('l, M j, Y'),
            'active_seconds' => $active,
            'idle_seconds' => $idle,
            'break_seconds' => $break,
        ];
    }
}
