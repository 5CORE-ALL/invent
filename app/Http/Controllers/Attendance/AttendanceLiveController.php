<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLiveSession;
use App\Models\User;
use App\Services\Attendance\AttendanceLiveWatchService;
use App\Services\Attendance\AttendanceService;
use App\Support\AttendanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttendanceLiveController extends Controller
{
    public function __construct(
        private readonly AttendanceLiveWatchService $liveWatchService,
        private readonly AttendanceService $attendanceService,
    ) {
        $this->middleware('auth');
    }

    public function show(User $user)
    {
        abort_unless(AttendanceAccess::canMonitor() && AttendanceAccess::canViewUser($user->id), 403);
        abort_unless((bool) config('attendance.live_watch_enabled', true), 404);

        return view('attendance.live', [
            'title' => 'Live — '.$user->name,
            'employee' => $user,
            'start_url' => route('attendance.live.start', $user),
        ]);
    }

    public function start(Request $request, User $user): JsonResponse
    {
        abort_unless(AttendanceAccess::canMonitor() && AttendanceAccess::canViewUser($user->id), 403);
        abort_unless((bool) config('attendance.live_watch_enabled', true), 404);

        $session = $this->liveWatchService->start(
            $user,
            $request->user(),
            $this->attendanceService->activeSession($user)
        );

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
        return [
            'ok' => true,
            'session_id' => $session->id,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
            ],
            'urls' => [
                'frame' => route('attendance.live.frame', $session),
                'ping' => route('attendance.live.ping', $session),
                'stop' => route('attendance.live.stop', $session),
                'recording' => route('attendance.live.recording', $session),
            ],
        ];
    }
}
