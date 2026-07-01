<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceScreenshot;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendanceScreenshotService
{
    public function store(
        User $user,
        AttendanceSession $session,
        UploadedFile $file,
        ?AttendanceDevice $device = null,
        array $meta = []
    ): AttendanceScreenshot {
        $disk = (string) config('attendance.screenshot_disk', 'attendance');
        $date = now()->format('Y/m/d');
        $base = "user-{$user->id}/{$date}";
        $name = Str::uuid().'.jpg';

        $path = $file->storeAs($base, $name, $disk);

        return AttendanceScreenshot::create([
            'user_id' => $user->id,
            'attendance_session_id' => $session->id,
            'attendance_device_id' => $device?->id,
            'captured_at' => isset($meta['captured_at']) ? $meta['captured_at'] : now(),
            'storage_path' => $path,
            'thumbnail_path' => null,
            'window_title' => isset($meta['window_title']) ? mb_substr((string) $meta['window_title'], 0, 500) : null,
            'app_name' => isset($meta['app_name']) ? mb_substr((string) $meta['app_name'], 0, 200) : null,
            'file_size' => $file->getSize() ?: 0,
            'idle_seconds' => max(0, (int) ($meta['idle_seconds'] ?? 0)),
        ]);
    }

    public function stream(AttendanceScreenshot $screenshot, string $type = 'full')
    {
        $disk = Storage::disk((string) config('attendance.screenshot_disk', 'attendance'));
        $path = $screenshot->diskPath($type);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
