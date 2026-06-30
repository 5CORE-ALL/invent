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

        return view('attendance.index', array_merge($data, [
            'title' => 'My Attendance',
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
            ] : null,
            'monitoring_enabled' => $policy?->monitoring_enabled ?? true,
            'heartbeat_interval' => (int) config('attendance.heartbeat_interval_seconds', 60),
            'idle_threshold' => (int) config('attendance.idle_threshold_seconds', 120),
        ]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AttendanceAccess::isInternalEmployee($user), 403);

        $validated = $request->validate([
            'work_location' => 'nullable|in:wfh,office,hybrid',
        ]);

        try {
            $session = $this->attendanceService->clockIn(
                $user,
                $validated['work_location'] ?? 'wfh',
                $request->ip(),
                $request->userAgent()
            );
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'session' => [
                'id' => $session->id,
                'started_at' => $session->started_at->toIso8601String(),
                'status' => $session->status,
            ],
        ]);
    }

    public function clockOut(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AttendanceAccess::isInternalEmployee($user), 403);

        $session = $this->attendanceService->clockOut($user);

        return response()->json([
            'ok' => (bool) $session,
            'session' => $session ? [
                'id' => $session->id,
                'ended_at' => $session->ended_at?->toIso8601String(),
                'active_seconds' => $session->total_active_seconds,
                'idle_seconds' => $session->total_idle_seconds,
            ] : null,
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AttendanceAccess::isInternalEmployee($user), 403);

        $validated = $request->validate([
            'is_active' => 'nullable|boolean',
            'idle_seconds' => 'nullable|integer|min:0|max:3600',
            'window_title' => 'nullable|string|max:500',
            'page_url' => 'nullable|string|max:1000',
        ]);

        $result = $this->attendanceService->recordHeartbeat($user, $validated);

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function pause(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AttendanceAccess::isInternalEmployee($user), 403);

        $session = $this->attendanceService->pause($user);

        return response()->json(['ok' => (bool) $session, 'status' => $session?->status]);
    }

    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AttendanceAccess::isInternalEmployee($user), 403);

        $session = $this->attendanceService->resume($user);

        return response()->json(['ok' => (bool) $session, 'status' => $session?->status]);
    }
}
