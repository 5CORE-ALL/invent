<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAiFlag;
use App\Models\AttendanceDailySummary;
use App\Models\AttendancePolicy;
use App\Models\User;
use App\Services\Attendance\AttendanceAiMisuseService;
use App\Services\Attendance\AttendanceAnalysisService;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimelineService;
use App\Support\AttendanceAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceMonitorController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceAnalysisService $analysisService,
        private readonly AttendanceAiMisuseService $aiMisuseService,
        private readonly AttendanceTimelineService $timelineService,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        abort_unless(AttendanceAccess::canMonitor(), 403, 'You do not have access to the attendance monitor.');

        $date = $request->input('date', now()->toDateString());
        $team = $request->input('team', 'all');
        $timezone = $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata'));
        $dayReset = $request->input('day_reset', config('attendance.timeline_day_reset', '04:00'));

        $viewableIds = AttendanceAccess::viewableUserIds();
        $employees = $this->attendanceService->monitorableEmployees($viewableIds);

        if ($team !== 'all') {
            $employees = $employees->filter(fn (User $u) => (string) $u->designation === $team)->values();
        }

        $teams = $this->attendanceService->monitorableEmployees($viewableIds)
            ->pluck('designation')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $timeline = $this->timelineService->teamTimeline($employees, $date, $timezone, $dayReset);

        return view('attendance.monitor', [
            'title' => 'Team Timeline',
            'date' => $date,
            'team' => $team,
            'timezone' => $timezone,
            'day_reset' => $dayReset,
            'teams' => $teams,
            'timeline' => $timeline,
            'can_admin' => AttendanceAccess::canAdmin(),
        ]);
    }

    public function employeeDetail(Request $request, User $user)
    {
        abort_unless(AttendanceAccess::canViewUser($user->id), 403);

        $timezone = $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata'));
        $dayReset = $request->input('day_reset', config('attendance.timeline_day_reset', '04:00'));

        [$from, $to, $periodKey] = $this->resolveEmployeePeriod($request, $timezone);

        $date = $to;

        $period = $this->timelineService->employeePeriodStats($user, $from, $to, $timezone);
        $day = $this->timelineService->employeeDayDetail($user, $date, $timezone, $dayReset);
        $desktopApps = $this->timelineService->employeePeriodDesktopApps($user, $from, $to, $timezone);
        $suspiciousSignals = $this->timelineService->employeePeriodSuspiciousSignals($user, $from, $to, $timezone);

        $flags = AttendanceAiFlag::query()
            ->where('user_id', $user->id)
            ->whereBetween('flag_date', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $policy = AttendancePolicy::resolveForUser($user);

        $periodOptions = $this->employeePeriodOptions($timezone);

        return view('attendance.employee-detail', [
            'title' => $user->name.' — Activity',
            'employee' => $user,
            'day' => $day,
            'period' => $period,
            'desktop_apps' => $desktopApps,
            'suspicious_signals' => $suspiciousSignals,
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'period_key' => $periodKey,
            'period_options' => $periodOptions,
            'timezone' => $timezone,
            'day_reset' => $dayReset,
            'flags' => $flags,
            'policy' => $policy,
            'can_admin' => AttendanceAccess::canAdmin(),
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveEmployeePeriod(Request $request, string $timezone): array
    {
        $now = now()->timezone($timezone);
        $today = $now->toDateString();
        $period = $request->input('period');

        if (! $period) {
            if ($request->has('date') && ! $request->has('from') && ! $request->has('to') && ! $request->has('month')) {
                $day = (string) $request->input('date', $today);

                return [$day, $day, $day === $today ? 'today' : 'custom'];
            }

            $legacyMonth = $request->input('month');
            if ($legacyMonth && preg_match('/^\d{4}-\d{2}$/', (string) $legacyMonth)) {
                $start = Carbon::parse($legacyMonth.'-01', $timezone);
                $from = $start->copy()->startOfMonth()->toDateString();
                $to = $start->copy()->endOfMonth()->toDateString();
                if ($legacyMonth === $now->format('Y-m')) {
                    $to = $today;
                }

                return [$from, $to, 'custom'];
            } elseif ($request->has('from') || $request->has('to')) {
                $period = 'custom';
            } else {
                $period = 'today';
            }
        }

        if ($period === 'today') {
            return [$today, $today, 'today'];
        }

        if ($period === 'week') {
            return [
                $now->copy()->startOfWeek()->toDateString(),
                $today,
                'week',
            ];
        }

        if ($period === 'month') {
            return [
                $now->copy()->startOfMonth()->toDateString(),
                $today,
                'month',
            ];
        }

        if ($period === 'prev_month') {
            $prev = $now->copy()->subMonth();

            return [
                $prev->copy()->startOfMonth()->toDateString(),
                $prev->copy()->endOfMonth()->toDateString(),
                'prev_month',
            ];
        }

        if (preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            $start = Carbon::parse($period.'-01', $timezone);
            $from = $start->copy()->startOfMonth()->toDateString();
            $to = $start->copy()->endOfMonth()->toDateString();
            if ($period === $now->format('Y-m')) {
                $to = $today;
            }

            return [$from, $to, 'custom'];
        }

        $to = $request->input('to', $today);
        $from = $request->input('from', Carbon::parse($to)->subDays(6)->toDateString());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, 'custom'];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function employeePeriodOptions(string $timezone): array
    {
        $prevMonth = now()->timezone($timezone)->subMonth();

        return [
            ['value' => 'today', 'label' => 'Today'],
            ['value' => 'week', 'label' => 'This week'],
            ['value' => 'month', 'label' => 'This month'],
            ['value' => 'prev_month', 'label' => 'Previous month ('.$prevMonth->format('F Y').')'],
            ['value' => 'custom', 'label' => 'Custom range'],
        ];
    }

    public function employeeScreenshots(Request $request, User $user): JsonResponse
    {
        abort_unless(AttendanceAccess::canViewUser($user->id), 403);

        $date = $request->input('date', now()->toDateString());
        $timezone = $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata'));
        $dayReset = $request->input('day_reset', config('attendance.timeline_day_reset', '04:00'));
        $page = max(1, (int) $request->input('page', 1));

        return response()->json(
            $this->timelineService->employeeScreenshots($user, $date, $page, $timezone, $dayReset)
        );
    }

    public function agentDownload()
    {
        abort_unless(AttendanceAccess::canSeeMenu(), 403);

        $installer = $this->resolveAgentInstaller();

        return view('attendance.agent-download', [
            'title' => 'Desktop Agent',
            'agent_version' => config('attendance.agent_version', '1.0.0'),
            'server_url' => rtrim((string) config('app.url'), '/'),
            'download_available' => $installer !== null,
            'download_url' => $installer ? route('attendance.agent.download') : null,
            'download_filename' => config('attendance.agent_download_filename', '5Core-Attendance-Setup.exe'),
            'screenshots_enabled' => (bool) config('attendance.screenshots_enabled', true),
        ]);
    }

    public function agentInstallerDownload(): BinaryFileResponse
    {
        abort_unless(AttendanceAccess::canSeeMenu(), 403);

        $installer = $this->resolveAgentInstaller();
        abort_unless($installer, 404, 'The desktop agent installer is not available yet. Please contact IT.');

        return response()->download(
            $installer,
            (string) config('attendance.agent_download_filename', '5Core-Attendance-Setup.exe'),
        );
    }

    private function resolveAgentInstaller(): ?string
    {
        $configured = config('attendance.agent_installer_path');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $public = public_path('downloads/5core-attendance-setup.exe');
        if (is_file($public)) {
            return $public;
        }

        $distDir = base_path('attendance-agent/dist');
        if (! is_dir($distDir)) {
            return null;
        }

        $matches = glob($distDir.DIRECTORY_SEPARATOR.'*Setup*.exe') ?: [];
        if ($matches === []) {
            $matches = glob($distDir.DIRECTORY_SEPARATOR.'*.exe') ?: [];
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $matches[0];
    }

    public function teamData(Request $request): JsonResponse
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);

        $date = $request->input('date', now()->toDateString());
        $team = $request->input('team', 'all');
        $timezone = $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata'));
        $dayReset = $request->input('day_reset', config('attendance.timeline_day_reset', '04:00'));
        $viewableIds = AttendanceAccess::viewableUserIds();

        $employees = $this->attendanceService->monitorableEmployees($viewableIds);
        if ($team !== 'all') {
            $employees = $employees->filter(fn (User $u) => (string) $u->designation === $team)->values();
        }

        return response()->json($this->timelineService->teamTimeline($employees, $date, $timezone, $dayReset));
    }

    public function analyzeDay(Request $request, User $user): JsonResponse
    {
        abort_unless(AttendanceAccess::canAdmin() || AttendanceAccess::canViewUser($user->id), 403);

        $date = $request->input('date', now()->toDateString());
        $result = $this->aiMisuseService->analyzeUserDay($user, $date, true);

        return response()->json([
            'ok' => true,
            'risk_score' => $result['risk_score'],
            'flags_count' => count($result['flags']),
            'summary' => $result['summary'],
        ]);
    }

    public function reviewFlag(Request $request, AttendanceAiFlag $flag): JsonResponse
    {
        abort_unless(AttendanceAccess::canAdmin(), 403);
        abort_unless(AttendanceAccess::canViewUser($flag->user_id), 403);

        $validated = $request->validate([
            'status' => 'required|in:reviewed,dismissed',
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $flag->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'flag' => $flag->fresh()]);
    }

    public function policies()
    {
        abort_unless(AttendanceAccess::canAdmin(), 403);

        $policies = AttendancePolicy::query()
            ->with('designation:id,name')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $designations = \App\Models\Designation::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('attendance.policies', [
            'title' => 'Attendance Policies',
            'policies' => $policies,
            'designations' => $designations,
        ]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        abort_unless(AttendanceAccess::canAdmin(), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'designation_id' => 'nullable|exists:designations,id',
            'expected_start' => 'required|date_format:H:i',
            'expected_end' => 'required|date_format:H:i',
            'grace_minutes' => 'nullable|integer|min:0|max:120',
            'min_daily_hours' => 'nullable|numeric|min:1|max:16',
            'max_idle_minutes_per_hour' => 'nullable|integer|min:5|max:60',
            'min_active_percent' => 'nullable|integer|min:20|max:100',
            'wfh_allowed' => 'nullable|boolean',
            'monitoring_enabled' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($validated['is_default'])) {
            AttendancePolicy::query()->update(['is_default' => false]);
        }

        $policy = AttendancePolicy::create([
            'name' => $validated['name'],
            'designation_id' => $validated['designation_id'] ?? null,
            'expected_start' => $validated['expected_start'],
            'expected_end' => $validated['expected_end'],
            'grace_minutes' => $validated['grace_minutes'] ?? 15,
            'min_daily_hours' => $validated['min_daily_hours'] ?? 8,
            'max_idle_minutes_per_hour' => $validated['max_idle_minutes_per_hour'] ?? 15,
            'min_active_percent' => $validated['min_active_percent'] ?? 60,
            'wfh_allowed' => $validated['wfh_allowed'] ?? true,
            'monitoring_enabled' => $validated['monitoring_enabled'] ?? true,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => true,
        ]);

        return response()->json(['ok' => true, 'policy' => $policy]);
    }
}
