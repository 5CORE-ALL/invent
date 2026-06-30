<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAiFlag extends Model
{
    public const TYPES = [
        'excessive_idle' => 'Excessive Idle Time',
        'late_start' => 'Late Start',
        'early_leave' => 'Early Leave',
        'low_productivity' => 'Low Productivity',
        'missed_heartbeats' => 'Missed Activity Signals',
        'suspicious_pattern' => 'Suspicious Activity Pattern',
        'insufficient_hours' => 'Insufficient Work Hours',
        'ai_assessment' => 'AI Risk Assessment',
    ];

    protected $fillable = [
        'user_id',
        'attendance_session_id',
        'flag_date',
        'flag_type',
        'severity',
        'title',
        'description',
        'evidence',
        'ai_confidence',
        'source',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'flag_date' => 'date',
        'evidence' => 'array',
        'ai_confidence' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->flag_type] ?? $this->flag_type;
    }
}
