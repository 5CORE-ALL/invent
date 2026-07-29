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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

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

    /**
     * Start Google sign-in for the desktop agent via the system browser.
     * Reuses the portal's web Google OAuth client (same as inventory login).
     */
    public function googleAuthStart(Request $request): RedirectResponse
    {
        $redirectUri = (string) $request->query('redirect_uri', '');
        $state = (string) $request->query('state', '');

        if ($state === '' || strlen($state) > 128) {
            abort(422, 'Invalid state.');
        }

        if (! preg_match('#^http://(127\.0\.0\.1|localhost):\d+(/callback)?$#', $redirectUri)) {
            abort(422, 'Invalid redirect URI.');
        }

        if (Auth::check()) {
            $user = Auth::user();
            if (! ($user->is_active ?? true)) {
                return self::desktopAgentLoopbackRedirect($redirectUri, $state, error: 'account_inactive');
            }

            return self::desktopAgentLoopbackRedirect($redirectUri, $state, user: $user);
        }

        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            abort(500, 'Google sign-in is not configured on the server.');
        }

        $request->session()->put('desktop_agent_oauth', [
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return Socialite::driver('google')->redirect();
    }

    public function googleLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'redirect_uri' => 'required|string|max:255',
            'machine_id' => 'required|string|max:120',
            'device_name' => 'nullable|string|max:120',
            'os_name' => 'nullable|string|max:50',
            'os_version' => 'nullable|string|max:100',
            'agent_version' => 'nullable|string|max:30',
        ]);

        // The loopback redirect URI is attacker-influenced input; restrict it to
        // localhost so it can only ever point back at the agent's own temporary server.
        if (! preg_match('#^http://(127\.0\.0\.1|localhost):\d+(/|$)#', $validated['redirect_uri'])) {
            throw ValidationException::withMessages(['redirect_uri' => ['Invalid redirect URI.']]);
        }

        // Preferred path: one-time code from portal Google OAuth (web Socialite).
        $cached = Cache::pull('desktop_agent_oauth_'.$validated['code']);
        if (is_array($cached) && ! empty($cached['user_id'])) {
            $user = User::query()->find($cached['user_id']);
            if (! $user) {
                throw ValidationException::withMessages(['email' => ['Google sign-in expired. Please try again.']]);
            }
            if (! ($user->is_active ?? true)) {
                throw ValidationException::withMessages(['email' => ['This account is inactive. Contact an administrator.']]);
            }

            return $this->issueAgentToken($user, $validated);
        }

        // Legacy path: direct desktop OAuth client (GOOGLE_DESKTOP_* credentials).
        $clientId = config('services.google_desktop.client_id');
        $clientSecret = config('services.google_desktop.client_secret');
        if (! $clientId || ! $clientSecret) {
            throw ValidationException::withMessages(['email' => ['Google sign-in failed. Please try again.']]);
        }

        try {
            $googleUser = Socialite::buildProvider(GoogleProvider::class, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect' => $validated['redirect_uri'],
            ])->stateless()->user();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['email' => ['Google sign-in failed. Please try again.']]);
        }

        if (! $googleUser || ! $googleUser->email) {
            throw ValidationException::withMessages(['email' => ['Google account has no email.']]);
        }

        $user = User::query()
            ->where('email', $googleUser->email)
            ->orWhere('google_id', $googleUser->id)
            ->first();

        if ($user) {
            if (! ($user->is_active ?? true)) {
                throw ValidationException::withMessages(['email' => ['This account is inactive. Contact an administrator.']]);
            }
            if (empty($user->google_id)) {
                $user->update(['google_id' => $googleUser->id]);
            }
        } else {
            $given = $googleUser->user['given_name'] ?? '';
            $family = $googleUser->user['family_name'] ?? '';
            $fullName = trim($given.($given && $family ? ' ' : '').$family);
            if ($fullName === '') {
                $fullName = $googleUser->name ?? explode('@', $googleUser->email)[0];
            }

            $user = User::create([
                'name' => $fullName,
                'email' => $googleUser->email,
                'google_id' => $googleUser->id,
                'password' => bcrypt(Str::random(24)),
                'email_verified_at' => now(),
            ]);
        }

        return $this->issueAgentToken($user, $validated);
    }

    /**
     * After portal Google OAuth succeeds, send the desktop agent a one-time code
     * via its loopback listener (when a desktop sign-in was in progress).
     */
    public static function completeDesktopGoogleIfPending(User $user): ?RedirectResponse
    {
        $pending = session()->pull('desktop_agent_oauth');
        if (! is_array($pending) || empty($pending['redirect_uri']) || empty($pending['state'])) {
            return null;
        }

        return self::desktopAgentLoopbackRedirect(
            (string) $pending['redirect_uri'],
            (string) $pending['state'],
            user: $user,
        );
    }

    public static function failDesktopGoogleIfPending(string $error): ?RedirectResponse
    {
        $pending = session()->pull('desktop_agent_oauth');
        if (! is_array($pending) || empty($pending['redirect_uri']) || empty($pending['state'])) {
            return null;
        }

        return self::desktopAgentLoopbackRedirect(
            (string) $pending['redirect_uri'],
            (string) $pending['state'],
            error: $error,
        );
    }

    private static function desktopAgentLoopbackRedirect(
        string $redirectUri,
        string $state,
        ?User $user = null,
        ?string $error = null,
    ): RedirectResponse {
        $params = ['state' => $state];

        if ($error) {
            $params['error'] = $error;
        } elseif ($user) {
            $code = Str::random(64);
            Cache::put('desktop_agent_oauth_'.$code, [
                'user_id' => $user->id,
            ], now()->addMinutes(5));
            $params['code'] = $code;
        } else {
            $params['error'] = 'sign_in_failed';
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return redirect()->away($redirectUri.$separator.http_build_query($params));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function issueAgentToken(User $user, array $validated): JsonResponse
    {
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

        $installedVersion = $request->input('agent_version') ?: $device?->agent_version;

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
            'agent_update' => $this->attendanceService->agentUpdatePayload(
                is_string($installedVersion) ? $installedVersion : null
            ),
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
            'agent_version' => 'nullable|string|max:30',
        ]);

        $result = $this->attendanceService->recordHeartbeat($request->user(), array_merge($validated, [
            'source' => 'desktop',
            'device_id' => $device?->id,
            'agent_version' => $validated['agent_version'] ?? $device?->agent_version,
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
        $base = rtrim((string) config('app.url'), '/');

        return [
            'heartbeat_interval_seconds' => (int) config('attendance.heartbeat_interval_seconds', 15),
            'screenshot_interval_seconds' => (int) config('attendance.screenshot_interval_seconds', 120),
            'idle_threshold_seconds' => (int) config('attendance.idle_threshold_seconds', 30),
            // Legacy v1.2.x popup threshold — keep high so old installs stop prompting.
            'idle_prompt_seconds' => (int) config('attendance.idle_prompt_seconds', 31536000),
            'idle_prompt_timeout_seconds' => (int) config('attendance.idle_prompt_timeout_seconds', 60),
            'screenshots_enabled' => (bool) config('attendance.screenshots_enabled', true),
            'agent_version' => (string) config('attendance.agent_version', '1.0.0'),
            'download_page_url' => $base.'/attendance/agent',
            'download_url' => $base.'/attendance/agent/download',
            'update_message' => 'A new version of 5Core Attendance is available. Run the installer to update — no uninstall needed. Your login stays saved.',
        ];
    }
}
