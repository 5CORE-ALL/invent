<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLiveSession;
use App\Models\User;
use App\Services\Attendance\AttendanceLiveWatchService;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceSummaryService;
use App\Services\Attendance\AttendanceTimelineService;
use App\Support\AttendanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttendanceLiveController extends Controller
{
    public function __construct(
        private readonly AttendanceLiveWatchService $liveWatchService,
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceSummaryService $summaryService,
    ) {
        $this->middleware('auth');
    }

    public function teamWall(Request $request)
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);
        abort_unless((bool) config('attendance.live_watch_enabled', true), 404);

        $timezone = $request->input('timezone', AttendanceTimelineService::defaultTimezone());
        $team = $request->input('team', 'all');
        $viewableIds = AttendanceAccess::viewableUserIds();
        $all = $this->attendanceService->monitorableEmployees($viewableIds);
        $teams = $all->pluck('designation')->filter()->unique()->sort()->values();
        $employees = $team === 'all'
            ? $all
            : $all->filter(fn (User $u) => (string) $u->designation === $team)->values();

        $today = now()->timezone($timezone)->toDateString();
        $summary = $this->summaryService->teamSummary($employees, $today, $today, $timezone);

        $tiles = collect($summary['rows'])->map(fn (array $row) => [
            'user_id' => (int) $row['user_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'live_status' => $row['live_status'] ?? 'absent',
            'live_label' => $row['live_label'] ?? 'Absent',
            'poster' => $row['last_image_thumb'] ?? null,
            'start_url' => route('attendance.live.start', $row['user_id']),
        ])->values();

        return view('attendance.team-video', [
            'title' => 'Team Monitor Video',
            'team' => $team,
            'timezone' => $timezone,
            'teams' => $teams,
            'tiles' => $tiles,
            'summary_url' => route('attendance.summary', array_filter([
                'team' => $team,
                'timezone' => $timezone,
            ])),
        ]);
    }

    public function show(User $user)
    {
        abort_unless(AttendanceAccess::canMonitor() && AttendanceAccess::canViewUser($user->id), 403);
        abort_unless((bool) config('attendance.live_watch_enabled', true), 404);

        $agent = $this->employeeAgentContext($user);

        return view('attendance.live', [
            'title' => 'Live — '.$user->name,
            'employee' => $user,
            'start_url' => '/attendance/live/'.$user->id.'/start',
            'latest_agent_version' => $agent['latest_version'],
            'installed_agent_version' => $agent['installed_version'],
            'wait_title' => $agent['wait_title'],
            'wait_text' => $agent['wait_text'],
        ]);
    }

    public function start(Request $request, User $user): JsonResponse
    {
        abort_unless(AttendanceAccess::canMonitor() && AttendanceAccess::canViewUser($user->id), 403);
        abort_unless((bool) config('attendance.live_watch_enabled', true), 404);

        $source = (string) $request->input('source', 'watch');

        $session = $this->liveWatchService->start(
            $user,
            $request->user(),
            $this->attendanceService->activeSession($user),
            $source
        );
        $this->liveWatchService->seedStillFrame($user, true);

        return response()->json($this->sessionPayload($session, $user));
    }

    public function frame(AttendanceLiveSession $liveSession): Response
    {
        $this->authorizeViewer($liveSession);
        $this->liveWatchService->ping($liveSession);

        $latest = $this->liveWatchService->latestFrame($liveSession);
        if (! $latest['bytes']) {
            return response('', 204);
        }

        $meta = $latest['meta'];
        $headerSafe = static fn (?string $value): string => preg_replace('/[^\x20-\x7E]/', ' ', (string) $value) ?? '';

        return response($latest['bytes'], 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Live-Window-Title' => $headerSafe($meta['window_title'] ?? $liveSession->window_title ?? ''),
            'X-Live-App-Name' => $headerSafe($meta['app_name'] ?? $liveSession->app_name ?? ''),
            'X-Live-Captured-At' => $headerSafe($meta['at'] ?? ''),
            'X-Live-Source' => $headerSafe($latest['source'] ?? 'live'),
            'Access-Control-Expose-Headers' => 'X-Live-Source, X-Live-Window-Title, X-Live-App-Name, X-Live-Captured-At',
        ]);
    }

    public function ping(AttendanceLiveSession $liveSession): JsonResponse
    {
        $this->authorizeViewer($liveSession);

        $session = $this->liveWatchService->ping($liveSession);
        $latest = $this->liveWatchService->latestFrame($session);

        return response()->json([
            'ok' => true,
            'status' => $session->status,
            'has_frame' => (bool) $latest['bytes'],
            'window_title' => $session->window_title,
            'app_name' => $session->app_name,
            'last_frame_at' => $session->last_frame_at?->toIso8601String(),
        ]);
    }

    public function stop(Request $request, AttendanceLiveSession $liveSession): JsonResponse
    {
        $this->authorizeViewer($liveSession);

        $reason = (string) $request->input('reason', 'viewer_closed');
        if (! in_array($reason, ['viewer_closed', 'expired', 'stopped'], true)) {
            $reason = 'viewer_closed';
        }

        $session = $this->liveWatchService->stop($liveSession, $reason);

        return response()->json([
            'ok' => true,
            'status' => $session->status,
            'recording_seconds' => $session->recording_seconds,
        ]);
    }

    public function storeRecording(Request $request, AttendanceLiveSession $liveSession): JsonResponse
    {
        $this->authorizeViewer($liveSession);

        $maxKb = (int) config('attendance.live_recording_max_kb', 204800);
        $request->validate([
            'recording' => 'required|file|mimetypes:video/webm,video/mp4,application/octet-stream|max:'.$maxKb,
        ]);

        $session = $this->liveWatchService->storeRecording($liveSession, $request->file('recording'));

        return response()->json([
            'ok' => true,
            'recording_url' => $session->recording_path
                ? route('attendance.live.recording.show', $session)
                : null,
            'recording_size' => $session->recording_size,
            'recording_seconds' => $session->recording_seconds,
        ]);
    }

    public function showRecording(AttendanceLiveSession $liveSession)
    {
        abort_unless(AttendanceAccess::canMonitor() && AttendanceAccess::canViewUser($liveSession->user_id), 403);

        return $this->liveWatchService->streamRecording($liveSession);
    }

    private function authorizeViewer(AttendanceLiveSession $session): void
    {
        abort_unless(AttendanceAccess::canMonitor() && AttendanceAccess::canViewUser($session->user_id), 403);
        abort_unless($session->viewer_user_id === auth()->id(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(AttendanceLiveSession $session, User $employee): array
    {
        $agent = $this->employeeAgentContext($employee);

        return [
            'ok' => true,
            'session_id' => $session->id,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
            ],
            'urls' => [
                'frame' => '/attendance/live/session/'.$session->id.'/frame',
                'ping' => '/attendance/live/session/'.$session->id.'/ping',
                'stop' => '/attendance/live/session/'.$session->id.'/stop',
                'recording' => '/attendance/live/session/'.$session->id.'/recording',
            ],
            'wait_title' => $agent['wait_title'],
            'wait_text' => $agent['wait_text'],
        ];
    }

    /**
     * @return array{installed_version: string|null, latest_version: string, has_installed: bool, up_to_date: bool, wait_title: string, wait_text: string}
     */
    private function employeeAgentContext(User $employee): array
    {
        $status = $this->attendanceService->desktopAgentStatusForUser($employee);
        $latest = (string) ($status['latest_version'] ?? config('attendance.agent_version', '1.0.0'));
        $wait = AttendanceLiveWatchService::viewerWaitCopy(
            $status['installed_version'] ?? null,
            $latest,
            (bool) ($status['has_installed'] ?? false),
            (bool) ($status['up_to_date'] ?? false),
        );

        return [
            'installed_version' => $status['installed_version'] ?? null,
            'latest_version' => $latest,
            'has_installed' => (bool) ($status['has_installed'] ?? false),
            'up_to_date' => (bool) ($status['up_to_date'] ?? false),
            'wait_title' => $wait['title'],
            'wait_text' => $wait['text'],
        ];
    }
}
