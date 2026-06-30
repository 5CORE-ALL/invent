<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceDailySummary extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'first_clock_in',
        'last_clock_out',
        'total_work_seconds',
        'active_seconds',
        'idle_seconds',
        'session_count',
        'status',
        'team_logger_hours',
        'productivity_score',
        'ai_risk_score',
        'top_activities',
    ];

    protected $casts = [
        'work_date' => 'date',
        'first_clock_in' => 'datetime',
        'last_clock_out' => 'datetime',
        'team_logger_hours' => 'decimal:2',
        'top_activities' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workHours(): float
    {
        return round($this->total_work_seconds / 3600, 2);
    }

    public function activePercent(): int
    {
        $total = $this->active_seconds + $this->idle_seconds;

        return $total > 0 ? (int) round(($this->active_seconds / $total) * 100) : 0;
    }
}
