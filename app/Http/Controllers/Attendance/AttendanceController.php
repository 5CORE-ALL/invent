<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAiFlag;
use App\Models\AttendanceDailySummary;
use App\Models\AttendancePolicy;
use App\Models\User;
use App\Services\Attendance\AttendanceAiMisuseService;
use App\Services\Attendance\AttendanceService;
use App\Support\AttendanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceAiMisuseService $aiMisuseService,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(AttendanceAccess::isInternalEmployee($user), 403, 'Attendance tracking is for internal team members only.');

        $date = $request->input('date', now()->toDateString());
        $data = $this->attendanceService->employeeDashboardData($user, $date);

        $agentStatus = $this->attendanceService->desktopAgentStatusForUser($user);

        return view('attendance.index', array_merge($data, [
            'title' => 'My Attendance',
            'agent_update_available' => $agentStatus['update_available'],
            'agent_has_installed' => $agentStatus['has_installed'],
            'agent_installed_version' => $agentStatus['installed_version'],
            'agent_latest_version' => $agentStatus['latest_version'],
            'agent_download_url' => route('attendance.agent.download'),
        ]));
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AttendanceAccess::isInternalEmployee($user), 403);

        $session = $this->attendanceService->activeSession($user);
        $policy = \App\Models\AttendancePolicy::resolveForUser($user);

        return response()->json([
            'has_session' => (bool) $session,
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'started_at' => $session->started_at->toIso8601String(),
                'work_location' => $session->work_location,
                'active_seconds' => $session->total_active_seconds,
                'idle_seconds' => $session->total_idle_seconds,
                'break_seconds' => $session->total_break_seconds,
                'activity_state' => $session->last_activity_state ?? ($session->status === 'paused' ? 'break' : 'working'),
            ] : null,
            'monitoring_enabled' => $policy?->monitoring_enabled ?? true,
            'heartbeat_interval' => (int) config('attendance.heartbeat_interval_seconds', 15),
            'idle_threshold' => (int) config('attendance.idle_threshold_seconds', 30),
        ]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Clock-in is only available from the desktop app. Mobile and browser clock-in are not allowed.',
        ], 422);
    }

    public function clockOut(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Clock-out is only available from the desktop app.',
        ], 422);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Browser and mobile activity tracking is disabled. Use the desktop app.',
        ], 422);
    }

    public function pause(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Breaks are only available from the desktop app.',
        ], 422);
    }

    public function resume(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Resume is only available from the desktop app.',
        ], 422);
    }
}
