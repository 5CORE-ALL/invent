<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAiFlag;
use App\Models\AttendanceDailySummary;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\Attendance\AttendanceAiMisuseService;
use App\Services\Attendance\AttendanceAnalysisService;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimelineService;
use App\Support\AttendanceAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $date = $request->input('date', now()->toDateString());
        $from = $request->input('from', Carbon::parse($date)->subDays(6)->toDateString());
        $to = $request->input('to', $date);

        $summaries = AttendanceDailySummary::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        $sessions = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereBetween('started_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->orderByDesc('started_at')
            ->limit(30)
            ->get();

        $flags = AttendanceAiFlag::query()
            ->where('user_id', $user->id)
            ->whereBetween('flag_date', [$from, $to])
            ->orderByDesc('created_at')
            ->get();

        $screenshots = \App\Models\AttendanceScreenshot::query()
            ->where('user_id', $user->id)
            ->whereBetween('captured_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->orderByDesc('captured_at')
            ->limit(48)
            ->get();

        $appUsage = \App\Models\AttendanceActivityLog::query()
            ->where('user_id', $user->id)
            ->where('source', 'desktop')
            ->whereBetween('recorded_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->whereNotNull('app_name')
            ->selectRaw('app_name, COUNT(*) as hits')
            ->groupBy('app_name')
            ->orderByDesc('hits')
            ->limit(12)
            ->get();

        $policy = AttendancePolicy::resolveForUser($user);

        return view('attendance.employee-detail', [
            'title' => $user->name.' — Attendance',
            'employee' => $user,
            'summaries' => $summaries,
            'sessions' => $sessions,
            'flags' => $flags,
            'screenshots' => $screenshots,
            'app_usage' => $appUsage,
            'policy' => $policy,
            'from' => $from,
            'to' => $to,
            'can_admin' => AttendanceAccess::canAdmin(),
        ]);
    }

    public function agentDownload()
    {
        abort_unless(AttendanceAccess::canSeeMenu(), 403);

        return view('attendance.agent-download', [
            'title' => 'Desktop Agent',
            'api_url' => url('/attendance/desktop-api'),
            'agent_version' => config('attendance.agent_version', '1.0.0'),
        ]);
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
