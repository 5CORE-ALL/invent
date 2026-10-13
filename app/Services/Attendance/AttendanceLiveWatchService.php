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
    /**
     * @param  bool  $onDuty  false when the calling machine has no active (clocked-in) session;
     *                        the agent is then told not to stream even if a viewer is waiting.
     */
    public function commandForUser(User $employee, bool $onDuty = true): array
    {
        $this->expireStaleForUser($employee->id);

        $requested = $onDuty && $this->isWatchRequested($employee->id);

        return [
            'requested' => $requested,
            'on_duty' => $onDuty,
            'fps' => max(1, (int) config('attendance.live_fps', 5)),
            'quality' => max(30, min(80, (int) config('attendance.live_quality', 55))),
            'force_logout' => AttendanceForceLogout::isFlagged($employee)
                || UserAccountStatus::for($employee) === UserAccountStatus::INACTIVE,
            'stream' => [
                'enabled' => (bool) config('attendance.live_stream_enabled', true),
                'fps' => max(5, min(30, (int) config('attendance.live_stream_fps', 20))),
                'width' => max(640, min(1920, (int) config('attendance.live_stream_width', 1280))),
                'bitrate' => max(200000, (int) config('attendance.live_stream_bitrate', 1200000)),
                'chunk_ms' => max(200, min(2000, (int) config('attendance.live_stream_chunk_ms', 600))),
            ],
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
            // Lets a viewer revalidate instead of re-downloading a frame it already shows.
            'seq' => (string) (int) round(microtime(true) * 1000),
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
     * Accept one slice of the agent's encoded screen stream.
     *
     * MediaRecorder emits a single continuous WebM: the first blob carries the
     * header and track definitions, every later blob is a raw continuation that
     * only decodes when appended after the ones before it. So the init blob is
     * kept apart (a late viewer still needs it) and the rest live in a ring.
     *
     * @param  string  $gen  Identifies one recorder run, so a restarted encoder
     *                       cannot have its output spliced onto the previous stream.
     */
    public function storeVideoChunk(User $employee, string $bytes, string $gen, int $seq, bool $isInit, string $mime = 'video/webm'): bool
    {
        if ($bytes === '' || $gen === '' || ! $this->isWatchRequested($employee->id)) {
            return false;
        }

        $keep = max(10, (int) config('attendance.live_stream_keep_chunks', 60));
        $ttl = 120;

        if ($isInit) {
            Cache::put($this->videoInitKey($employee->id, $gen), $bytes, $ttl);
            Cache::put($this->videoStateKey($employee->id), [
                'gen' => $gen,
                'head' => 0,
                'tail' => 1,
                'mime' => $mime,
                'at' => now()->toIso8601String(),
            ], 40);

            // Only on init. Chunks arrive a couple of times a second, and the
            // viewer's own ping already keeps the session from expiring, so
            // touching the row per chunk would be a query for nothing.
            $open = AttendanceLiveSession::query()
                ->where('user_id', $employee->id)
                ->whereIn('status', ['requested', 'streaming'])
                ->get();

            foreach ($open as $session) {
                $this->markStreaming($session, $session->window_title, $session->app_name);
            }

            $this->touchWatch($employee->id);

            return true;
        }

        $state = $this->videoState($employee->id);
        // A chunk from a run we never saw the header for can never be decoded.
        if ($state === null || $state['gen'] !== $gen) {
            return false;
        }

        Cache::put($this->videoChunkKey($employee->id, $gen, $seq), $bytes, $ttl);

        $tail = max((int) $state['tail'], $seq - $keep + 1, 1);
        for ($old = (int) $state['tail']; $old < $tail; $old++) {
            Cache::forget($this->videoChunkKey($employee->id, $gen, $old));
        }

        Cache::put($this->videoStateKey($employee->id), [
            'gen' => $gen,
            'head' => max((int) $state['head'], $seq),
            'tail' => $tail,
            'mime' => $mime,
            'at' => now()->toIso8601String(),
        ], 40);

        $this->touchWatch($employee->id);

        return true;
    }

    /**
     * Next slice a viewer should append, or null when it is already at the live edge.
     *
     * @param  string|null  $gen  Recorder run the viewer is currently decoding.
     * @param  int  $after  Last sequence the viewer appended.
     * @return array{bytes: string, gen: string, seq: int, init: bool, gap: bool, mime: string}|null
     */
    public function nextVideoChunk(int $userId, ?string $gen, int $after): ?array
    {
        $state = $this->videoState($userId);
        if ($state === null) {
            return null;
        }

        $current = (string) $state['gen'];
        $mime = (string) ($state['mime'] ?? 'video/webm');

        // Either a fresh viewer or the agent restarted its encoder. Both have to
        // begin again from the header before anything else will decode.
        if ($gen !== $current) {
            $init = Cache::get($this->videoInitKey($userId, $current));
            if (! is_string($init) || $init === '') {
                return null;
            }

            return ['bytes' => $init, 'gen' => $current, 'seq' => 0, 'init' => true, 'gap' => false, 'mime' => $mime];
        }

        $want = $after + 1;
        $gap = false;
        if ($want < (int) $state['tail']) {
            $want = (int) $state['tail'];
            $gap = true;
        }
        if ($want > (int) $state['head']) {
            return null;
        }

        $bytes = Cache::get($this->videoChunkKey($userId, $current, $want));
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return ['bytes' => $bytes, 'gen' => $current, 'seq' => $want, 'init' => false, 'gap' => $gap, 'mime' => $mime];
    }

    public function hasVideoStream(int $userId): bool
    {
        return $this->videoState($userId) !== null;
    }

    /**
     * @return array{gen: string, head: int, tail: int, mime: string, at: string}|null
     */
    private function videoState(int $userId): ?array
    {
        $state = Cache::get($this->videoStateKey($userId));

        return is_array($state) && isset($state['gen']) ? $state : null;
    }

    /**
     * @return array{title: string, text: string}
     */
    public static function viewerWaitCopy(
        ?string $installedVersion,
        string $latestVersion,
        bool $seenRecently,
        bool $upToDate,
        bool $supportsLiveVideo = true,
    ): array {
        $latest = $latestVersion !== '' ? $latestVersion : 'latest';
        $installed = $installedVersion !== null && $installedVersion !== '' ? $installedVersion : null;

        // Screen streaming did not exist in older builds, so "restart the app" would never
        // work — the install has to be replaced before any video can appear.
        if ($installed !== null && ! $supportsLiveVideo) {
            $sameVersion = version_compare($latest, $installed, '<=');

            return [
                'title' => 'Desktop app is too old for live video',
                'text' => $sameVersion
                    ? 'This employee is on v'.$installed.', which cannot stream the screen, and that is also the newest build published for their computer. Publish a newer agent before live video can work.'
                    : 'This employee is on v'.$installed.', which cannot stream the screen. Ask them to install v'.$latest.' from /attendance/agent, then keep this window open.',
            ];
        }

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
                'seq' => 'shot-'.$shot->id,
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
        // Chunk bodies are left to expire on their own TTL; dropping the state is
        // enough to stop a later viewer decoding a stream that has gone away.
        Cache::forget($this->videoStateKey($userId));
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

    private function videoStateKey(int $userId): string
    {
        return 'attendance:live:video-state:'.$userId;
    }

    private function videoInitKey(int $userId, string $gen): string
    {
        return 'attendance:live:video-init:'.$userId.':'.$gen;
    }

    private function videoChunkKey(int $userId, string $gen, int $seq): string
    {
        return 'attendance:live:video-chunk:'.$userId.':'.$gen.':'.$seq;
    }
}
