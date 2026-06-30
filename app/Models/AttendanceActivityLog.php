<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceActivityLog extends Model
{
    protected $fillable = [
        'attendance_session_id',
        'user_id',
        'recorded_at',
        'is_active',
        'idle_seconds',
        'window_title',
        'page_url',
        'source',
        'app_name',
        'process_name',
        'attendance_device_id',
        'keystroke_count',
        'mouse_click_count',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
