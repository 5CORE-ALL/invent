<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $fillable = [
        'user_id',
        'attendance_device_id',
        'started_at',
        'ended_at',
        'status',
        'work_location',
        'clock_source',
        'total_active_seconds',
        'total_idle_seconds',
        'total_break_seconds',
        'paused_at',
        'last_activity_state',
        'heartbeat_count',
        'missed_heartbeat_count',
        'ip_address',
        'user_agent',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'paused_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(AttendanceScreenshot::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(AttendanceActivityLog::class);
    }

    public function aiFlags(): HasMany
    {
        return $this->hasMany(AttendanceAiFlag::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'paused'], true);
    }

    public function durationSeconds(): int
    {
        $end = $this->ended_at ?? now();

        return max(0, $this->started_at->diffInSeconds($end));
    }

    public function activePercent(): int
    {
        $total = $this->total_active_seconds + $this->total_idle_seconds;

        return $total > 0 ? (int) round(($this->total_active_seconds / $total) * 100) : 0;
    }
}
