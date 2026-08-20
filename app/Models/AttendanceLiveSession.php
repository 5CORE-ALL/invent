<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLiveSession extends Model
{
    protected $fillable = [
        'user_id',
        'viewer_user_id',
        'attendance_session_id',
        'status',
        'started_at',
        'ended_at',
        'last_viewer_ping_at',
        'last_frame_at',
        'frame_count',
        'recording_path',
        'recording_mime',
        'recording_size',
        'recording_seconds',
        'ended_reason',
        'window_title',
        'app_name',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_viewer_ping_at' => 'datetime',
        'last_frame_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['requested', 'streaming'], true);
    }
}
