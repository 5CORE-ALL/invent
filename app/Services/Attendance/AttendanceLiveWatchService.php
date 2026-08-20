<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLiveSession;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AttendanceLiveWatchService
{
    public function commandForUser(User $employee): array
    {
        $this->expireStaleForUser($employee->id);

        $requested = $this->isWatchRequested($employee->id);

        return [
            'requested' => $requested,
            'fps' => max(1, (int) config('attendance.live_fps', 2)),
            'quality' => max(30, min(80, (int) config('attendance.live_quality', 55))),
        ];
    }

    public function start(User $employee, User $viewer, ?AttendanceSession $attendanceSession = null): AttendanceLiveSession
    {
        $session = AttendanceLiveSession::create([
            'user_id' => $employee->id,
            'viewer_user_id' => $viewer->id,
            'attendance_session_id' => $attendanceSession?->id,
            'status' => 'requested',
            'started_at' => now(),
            'last_viewer_ping_at' => now(),
        ]);

        $this->touchWatch($employee->id);

        return $session;
    }

    public function ping(AttendanceLiveSession $session): AttendanceLiveSession
    {
        if (! $session->isOpen()) {
            return $session;
        }

        $this->touchWatch($session->user_id);

        $tickKey = 'attendance:live:ping-tick:'.$session->id;
        if (Cache::has($tickKey)) {
            return $session;
        }
        Cache::put($tickKey, 1, 3);

        $session->forceFill(['last_viewer_ping_at' => now()])->save();

        return $session->fresh() ?: $session;
    }

    public function stop(AttendanceLiveSession $session, string $reason = 'viewer_closed'): AttendanceLiveSession
    {
        if ($session->isOpen()) {
            $endedAt = now();
            $seconds = max(0, $session->started_at->diffInSeconds($endedAt));
            $session->update([
                'status' => 'ended',
                'ended_at' => $endedAt,
                'ended_reason' => $reason,
                'recording_seconds' => $session->recording_seconds ?: $seconds,
            ]);
        }

        if (! $this->hasOpenSessions($session->user_id)) {
            $this->clearWatch($session->user_id);
        }

        return $session->fresh();
    }

    public function storeFrame(User $employee, UploadedFile $file, array $meta = []): bool
    {
        $this->expireStaleForUser($employee->id);

        if (! $this->isWatchRequested($employee->id)) {
            return false;
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || $bytes === '') {
            return false;
        }

        $title = isset($meta['window_title']) ? mb_substr((string) $meta['window_title'], 0, 500) : null;
        $app = isset($meta['app_name']) ? mb_substr((string) $meta['app_name'], 0, 200) : null;

        Cache::put($this->frameKey($employee->id), $bytes, 30);
        Cache::put($this->frameMetaKey($employee->id), [
            'at' => now()->toIso8601String(),
            'window_title' => $title,
            'app_name' => $app,
        ], 30);

        $open = AttendanceLiveSession::query()
            ->where('user_id', $employee->id)
            ->whereIn('status', ['requested', 'streaming'])
            ->get();

        foreach ($open as $session) {
            $this->markStreaming($session, $title, $app);
            $this->persistRecordingFrame($session, $bytes);
        }

        $this->touchWatch($employee->id);

        return true;
    }

    /**
     * @return array{bytes: string|null, meta: array<string, mixed>}
     */
    public function latestFrame(AttendanceLiveSession $session): array
    {
        $bytes = Cache::get($this->frameKey($session->user_id));
        $meta = Cache::get($this->frameMetaKey($session->user_id), []);

        return [
            'bytes' => is_string($bytes) && $bytes !== '' ? $bytes : null,
            'meta' => is_array($meta) ? $meta : [],
        ];
    }

    public function storeRecording(AttendanceLiveSession $session, UploadedFile $file): AttendanceLiveSession
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'webm');
        if (! in_array($ext, ['webm', 'mp4'], true)) {
            $ext = 'webm';
        }

        $path = "live-sessions/{$session->id}/recording.{$ext}";
        $stored = $file->storeAs("live-sessions/{$session->id}", "recording.{$ext}", (string) config('attendance.screenshot_disk', 'attendance'));

        $seconds = max(
            (int) $session->recording_seconds,
            max(0, $session->started_at->diffInSeconds($session->ended_at ?? now()))
        );

        $session->update([
            'recording_path' => $stored ?: $path,
            'recording_mime' => $file->getMimeType() ?: ($ext === 'mp4' ? 'video/mp4' : 'video/webm'),
            'recording_size' => (int) ($file->getSize() ?: 0),
            'recording_seconds' => $seconds,
        ]);

        $this->deleteFrameDump($session);

        return $session->fresh();
    }

    public function streamRecording(AttendanceLiveSession $session)
    {
        abort_unless($session->recording_path, 404);

        $disk = Storage::disk((string) config('attendance.screenshot_disk', 'attendance'));
        abort_unless($disk->exists($session->recording_path), 404);

        return $disk->response($session->recording_path, 'live-'.$session->id.'.'.pathinfo($session->recording_path, PATHINFO_EXTENSION), [
            'Content-Type' => $session->recording_mime ?: 'video/webm',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function isWatchRequested(int $userId): bool
    {
        if (! (bool) config('attendance.live_watch_enabled', true)) {
            return false;
        }

        if (Cache::get($this->watchKey($userId))) {
            return true;
        }

        return $this->hasOpenSessions($userId);
    }

    private function hasOpenSessions(int $userId): bool
    {
        $timeout = max(8, (int) config('attendance.live_viewer_timeout_seconds', 20));

        return AttendanceLiveSession::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['requested', 'streaming'])
            ->where('last_viewer_ping_at', '>=', now()->subSeconds($timeout))
            ->exists();
    }

    private function expireStaleForUser(int $userId): void
    {
        $timeout = max(8, (int) config('attendance.live_viewer_timeout_seconds', 20));
        $cutoff = now()->subSeconds($timeout);

        $stale = AttendanceLiveSession::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['requested', 'streaming'])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_viewer_ping_at')
                    ->orWhere('last_viewer_ping_at', '<', $cutoff);
            })
            ->get();

        foreach ($stale as $session) {
            $this->stop($session, 'expired');
        }
    }

    private function markStreaming(AttendanceLiveSession $session, ?string $title, ?string $app): void
    {
        $tickKey = 'attendance:live:meta-tick:'.$session->id;
        $shouldWrite = $session->status !== 'streaming' || ! Cache::has($tickKey);
        if (! $shouldWrite) {
            return;
        }
        Cache::put($tickKey, 1, 1);

        $session->forceFill([
            'status' => 'streaming',
            'last_frame_at' => now(),
            'window_title' => $title,
            'app_name' => $app,
            'frame_count' => (int) $session->frame_count + 1,
        ])->save();
    }

    private function persistRecordingFrame(AttendanceLiveSession $session, string $bytes): void
    {
        $tickKey = 'attendance:live:rec-tick:'.$session->id;
        if (Cache::has($tickKey)) {
            return;
        }
        Cache::put($tickKey, 1, 1);

        $disk = Storage::disk((string) config('attendance.screenshot_disk', 'attendance'));
        $n = (int) Cache::increment('attendance:live:rec-n:'.$session->id);
        $name = str_pad((string) max(1, $n), 6, '0', STR_PAD_LEFT).'.jpg';
        $disk->put("live-sessions/{$session->id}/frames/{$name}", $bytes);
    }

    private function deleteFrameDump(AttendanceLiveSession $session): void
    {
        $disk = Storage::disk((string) config('attendance.screenshot_disk', 'attendance'));
        $dir = "live-sessions/{$session->id}/frames";
        if ($disk->exists($dir)) {
            $disk->deleteDirectory($dir);
        }
    }

    private function touchWatch(int $userId): void
    {
        $ttl = max(15, (int) config('attendance.live_viewer_timeout_seconds', 20) + 10);
        Cache::put($this->watchKey($userId), 1, $ttl);
    }

    private function clearWatch(int $userId): void
    {
        Cache::forget($this->watchKey($userId));
        Cache::forget($this->frameKey($userId));
        Cache::forget($this->frameMetaKey($userId));
    }

    private function watchKey(int $userId): string
    {
        return 'attendance:live:watch:'.$userId;
    }

    private function frameKey(int $userId): string
    {
        return 'attendance:live:frame:'.$userId;
    }

    private function frameMetaKey(int $userId): string
    {
        return 'attendance:live:frame-meta:'.$userId;
    }
}
