<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AttendanceScreenshot extends Model
{
    protected $fillable = [
        'user_id',
        'attendance_session_id',
        'attendance_device_id',
        'captured_at',
        'storage_path',
        'thumbnail_path',
        'window_title',
        'app_name',
        'file_size',
        'idle_seconds',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function imageUrl(): ?string
    {
        if (! $this->storage_path) {
            return null;
        }

        return route('attendance.screenshots.show', $this);
    }

    public function thumbnailUrl(): ?string
    {
        if ($this->thumbnail_path) {
            return route('attendance.screenshots.show', ['screenshot' => $this->id, 'type' => 'thumb']);
        }

        return $this->imageUrl();
    }

    public function diskPath(string $type = 'full'): string
    {
        return $type === 'thumb' && $this->thumbnail_path
            ? $this->thumbnail_path
            : $this->storage_path;
    }
}
