<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLiveSession;
use App\Models\AttendanceScreenshot;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\AttendanceForceLogout;
use App\Support\UserAccountStatus;
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
            'fps' => max(1, (int) config('attendance.live_fps', 5)),
            'quality' => max(30, min(80, (int) config('attendance.live_quality', 55))),
            'force_logout' => AttendanceForceLogout::isFlagged($employee)
                || UserAccountStatus::for($employee) === UserAccountStatus::INACTIVE,
        ];
    }

    public function start(User $employee, User $viewer, ?AttendanceSession $attendanceSession = null, string $source = 'watch'): AttendanceLiveSession
    {
        $source = in_array($source, ['watch', 'wall'], true) ? $source : 'watch';

        $session = AttendanceLiveSession::create([
            'user_id' => $employee->id,
            'viewer_user_id' => $viewer->id,
            'attendance_session_id' => $attendanceSession?->id,
            'status' => 'requested',
            'started_at' => now(),
            'last_viewer_ping_at' => now(),
        ]);

        Cache::put('attendance:live:source:'.$session->id, $source, 86400);
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
            'source' => 'live',
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
     * @return array{title: string, text: string}
     */
    public static function viewerWaitCopy(
        ?string $installedVersion,
        string $latestVersion,
        bool $seenRecently,
        bool $upToDate,
    ): array {
        $latest = $latestVersion !== '' ? $latestVersion : 'latest';
        $installed = $installedVersion !== null && $installedVersion !== '' ? $installedVersion : null;

        if ($upToDate || ($installed !== null && version_compare($latest, $installed, '<='))) {
            return [
                'title' => 'Desktop app is not streaming yet',
                'text' => 'This employee already has v'.$installed.'. Ask them to restart 5Core Attendance while this window stays open. Live video starts after the app reconnects.',
            ];
        }

        if ($seenRecently && $installed !== null) {
            return [
                'title' => 'Desktop app needs an update',
                'text' => 'This employee is on v'.$installed.'. Ask them to install v'.$latest.' from /attendance/agent, then restart the app while this window stays open.',
            ];
        }

        return [
            'title' => 'Desktop app is not streaming yet',
            'text' => 'Ask the employee to open 5Core Attendance v'.$latest.' and keep it running while this window stays open. Install it from /attendance/agent if needed.',
        ];
    }

    /**
     * @return array{bytes: string|null, meta: array<string, mixed>, source: string|null}
     */
    public function latestFrame(AttendanceLiveSession $session): array
    {
        $bytes = Cache::get($this->frameKey($session->user_id));
        $meta = Cache::get($this->frameMetaKey($session->user_id), []);
        $meta = is_array($meta) ? $meta : [];
        $hasBytes = is_string($bytes) && $bytes !== '';
        $source = $hasBytes ? (string) ($meta['source'] ?? 'live') : null;

        if ($hasBytes && $source === 'live') {
            return [
                'bytes' => $bytes,
                'meta' => $meta,
                'source' => 'live',
            ];
        }

        if ($hasBytes) {
            $captured = isset($meta['at']) ? \Carbon\Carbon::parse($meta['at']) : null;
            $age = $captured ? $captured->diffInSeconds(now()) : 0;
            if ($age <= 6 || $session->status === 'requested') {
                return [
                    'bytes' => $bytes,
                    'meta' => $meta,
                    'source' => $source ?: 'screenshot',
                ];
            }
        }

        if ($session->status === 'requested') {
            return $this->latestScreenshot($session->user_id);
        }

        return ['bytes' => null, 'meta' => [], 'source' => null];
    }

    public function seedStillFrame(User $employee, bool $force = false): void
    {
        $meta = Cache::get($this->frameMetaKey($employee->id), []);
        if (($meta['source'] ?? '') === 'live') {
            return;
        }
        if (! $force && Cache::get($this->frameKey($employee->id))) {
            return;
        }

        $still = $this->latestScreenshot($employee->id);
        if (! $still['bytes']) {
            return;
        }

        Cache::put($this->frameKey($employee->id), $still['bytes'], 30);
        Cache::put($this->frameMetaKey($employee->id), $still['meta'], 30);
    }

    /**
     * @return array{bytes: string|null, meta: array<string, mixed>, source: string|null}
     */
    public function latestScreenshot(int $userId): array
    {
        $shot = AttendanceScreenshot::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();

        if (! $shot?->storage_path) {
            return ['bytes' => null, 'meta' => [], 'source' => null];
        }

        $disk = Storage::disk((string) config('attendance.screenshot_disk', 'attendance'));
        if (! $disk->exists($shot->storage_path)) {
            return ['bytes' => null, 'meta' => [], 'source' => null];
        }

        $bytes = $disk->get($shot->storage_path);
        if (! is_string($bytes) || $bytes === '') {
            return ['bytes' => null, 'meta' => [], 'source' => null];
        }

        return [
            'bytes' => $bytes,
            'meta' => [
                'at' => $shot->captured_at?->toIso8601String(),
                'window_title' => $shot->window_title,
                'app_name' => $shot->app_name,
                'source' => 'screenshot',
            ],
            'source' => 'screenshot',
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
        if (Cache::get('attendance:live:source:'.$session->id) === 'wall') {
            return;
        }

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
