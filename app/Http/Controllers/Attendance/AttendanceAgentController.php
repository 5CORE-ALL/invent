<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AttendanceScreenshot;
use App\Models\User;
use App\Services\Attendance\AttendanceDeviceService;
use App\Services\Attendance\AttendanceScreenshotService;
use App\Services\Attendance\AttendanceService;
use App\Support\AttendanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AttendanceAgentController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceDeviceService $deviceService,
        private readonly AttendanceScreenshotService $screenshotService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'machine_id' => 'required|string|max:120',
            'device_name' => 'nullable|string|max:120',
            'os_name' => 'nullable|string|max:50',
            'os_version' => 'nullable|string|max:100',
            'agent_version' => 'nullable|string|max:30',
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        abort_unless(AttendanceAccess::isInternalEmployee($user), 403, 'Attendance agent is for internal team members only.');

        $device = $this->deviceService->registerOrUpdate($user, $validated);

        $user->tokens()->where('name', 'like', 'attendance-agent-%')->where('created_at', '<', now()->subDays(90))->delete();

        $token = $user->createToken('attendance-agent-'.$device->machine_id, ['attendance:agent'])->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'device' => [
                'id' => $device->id,
                'machine_id' => $device->machine_id,
            ],
            'config' => $this->agentConfig(),
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'config' => $this->agentConfig()]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $device = $this->resolveDevice($request);
        $session = $this->attendanceService->activeSession($user);

        if ($device) {
            $this->deviceService->touch($device, $request->input('agent_version'));
        }

        return response()->json([
            'ok' => true,
            'has_session' => (bool) $session,
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'started_at' => $session->started_at->toIso8601String(),
                'active_seconds' => $session->total_active_seconds,
                'idle_seconds' => $session->total_idle_seconds,
                'break_seconds' => $session->total_break_seconds,
                'activity_state' => $session->last_activity_state ?? ($session->status === 'paused' ? 'break' : 'working'),
            ] : null,
            'today' => $this->attendanceService->todayStats($user),
            'config' => $this->agentConfig(),
        ]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $device = $this->resolveDevice($request);

        $validated = $request->validate([
            'work_location' => 'nullable|in:wfh,office,hybrid',
        ]);

        try {
            $session = $this->attendanceService->clockIn(
                $user,
                $validated['work_location'] ?? 'wfh',
                $request->ip(),
                '5core-attendance-agent',
                $device?->id,
                'desktop'
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
                'active_seconds' => $session->total_active_seconds,
                'idle_seconds' => $session->total_idle_seconds,
                'break_seconds' => $session->total_break_seconds,
                'activity_state' => $session->last_activity_state ?? 'working',
            ],
            'today' => $this->attendanceService->todayStats($user),
        ]);
    }

    public function clockOut(Request $request): JsonResponse
    {
        $session = $this->attendanceService->clockOut($request->user());

        return response()->json([
            'ok' => (bool) $session,
            'session' => $session ? [
                'id' => $session->id,
                'ended_at' => $session->ended_at?->toIso8601String(),
            ] : null,
            'today' => $this->attendanceService->todayStats($request->user()),
        ]);
    }

    public function pause(Request $request): JsonResponse
    {
        $session = $this->attendanceService->pause($request->user());

        return response()->json([
            'ok' => (bool) $session,
            'status' => $session?->status,
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'started_at' => $session->started_at->toIso8601String(),
                'active_seconds' => $session->total_active_seconds,
                'idle_seconds' => $session->total_idle_seconds,
                'break_seconds' => $session->total_break_seconds,
                'activity_state' => 'break',
            ] : null,
            'today' => $this->attendanceService->todayStats($request->user()),
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        $session = $this->attendanceService->resume($request->user());

        return response()->json([
            'ok' => (bool) $session,
            'status' => $session?->status,
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'started_at' => $session->started_at->toIso8601String(),
                'active_seconds' => $session->total_active_seconds,
                'idle_seconds' => $session->total_idle_seconds,
                'break_seconds' => $session->total_break_seconds,
                'activity_state' => 'working',
            ] : null,
            'today' => $this->attendanceService->todayStats($request->user()),
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $device = $this->resolveDevice($request);
        if ($device) {
            $this->deviceService->touch($device, $request->input('agent_version'));
        }

        $validated = $request->validate([
            'is_active' => 'nullable|boolean',
            'activity_state' => 'nullable|in:working,idle,break',
            'idle_seconds' => 'nullable|integer|min:0|max:86400',
            'elapsed_seconds' => 'nullable|integer|min:1|max:120',
            'window_title' => 'nullable|string|max:500',
            'page_url' => 'nullable|string|max:1000',
            'app_name' => 'nullable|string|max:200',
            'process_name' => 'nullable|string|max:200',
            'keystroke_count' => 'nullable|integer|min:0|max:9999',
            'mouse_click_count' => 'nullable|integer|min:0|max:9999',
        ]);

        $result = $this->attendanceService->recordHeartbeat($request->user(), array_merge($validated, [
            'source' => 'desktop',
            'device_id' => $device?->id,
        ]));

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function screenshot(Request $request): JsonResponse
    {
        $user = $request->user();
        $device = $this->resolveDevice($request);
        $session = $this->attendanceService->activeSession($user);

        if (! $session || $session->status !== 'active') {
            return response()->json(['ok' => false, 'message' => 'No active session'], 422);
        }

        $request->validate([
            'screenshot' => 'required|image|max:'.((int) config('attendance.screenshot_max_kb', 5120)),
            'window_title' => 'nullable|string|max:500',
            'app_name' => 'nullable|string|max:200',
            'idle_seconds' => 'nullable|integer|min:0',
        ]);

        $shot = $this->screenshotService->store(
            $user,
            $session,
            $request->file('screenshot'),
            $device,
            [
                'window_title' => $request->input('window_title'),
                'app_name' => $request->input('app_name'),
                'idle_seconds' => $request->input('idle_seconds', 0),
            ]
        );

        return response()->json([
            'ok' => true,
            'screenshot_id' => $shot->id,
            'captured_at' => $shot->captured_at->toIso8601String(),
        ]);
    }

    public function showScreenshot(Request $request, AttendanceScreenshot $screenshot)
    {
        abort_unless(AttendanceAccess::canViewUser($screenshot->user_id), 403);

        $type = $request->query('type') === 'thumb' ? 'thumb' : 'full';

        return $this->screenshotService->stream($screenshot, $type);
    }

    private function resolveDevice(Request $request, bool $required = false): ?AttendanceDevice
    {
        $machineId = $request->header('X-Machine-Id') ?: $request->input('machine_id');
        if (! $machineId) {
            if ($required) {
                abort(422, 'machine_id required');
            }

            return null;
        }

        $device = AttendanceDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('machine_id', $machineId)
            ->first();

        if ($required && ! $device) {
            abort(422, 'Device not registered. Login again.');
        }

        return $device;
    }

    /**
     * @return array<string, mixed>
     */
    private function agentConfig(): array
    {
        return [
            'heartbeat_interval_seconds' => (int) config('attendance.heartbeat_interval_seconds', 15),
            'screenshot_interval_seconds' => (int) config('attendance.screenshot_interval_seconds', 30),
            'idle_threshold_seconds' => (int) config('attendance.idle_threshold_seconds', 30),
            'idle_prompt_seconds' => (int) config('attendance.idle_prompt_seconds', 30),
            'idle_prompt_timeout_seconds' => (int) config('attendance.idle_prompt_timeout_seconds', 60),
            'screenshots_enabled' => (bool) config('attendance.screenshots_enabled', true),
            'agent_version' => (string) config('attendance.agent_version', '1.0.0'),
        ];
    }
}
