<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamLoggerDailyHours extends Model
{
    use HasFactory;

    protected $table = 'team_logger_daily_hours';

    protected $fillable = [
        'employee_email',
        'work_date',
        'total_hours',
        'idle_hours',
        'active_hours',
        'productive_hours',
        'fetched_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'total_hours' => 'decimal:2',
        'idle_hours' => 'decimal:2',
        'active_hours' => 'decimal:2',
        'productive_hours' => 'integer',
        'fetched_at' => 'datetime',
    ];

    /**
     * Convenience scope to fetch a single day's snapshot keyed by email.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('work_date', $date);
    }
}
