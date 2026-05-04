<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamLoggerHours extends Model
{
    use HasFactory;

    protected $table = 'team_logger_hours';

    protected $fillable = [
        'employee_email',
        'month',
        'start_date',
        'end_date',
        'productive_hours',
        'total_hours',
        'idle_hours',
        'active_hours',
        'fetched_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'productive_hours' => 'integer',
        'total_hours' => 'decimal:2',
        'idle_hours' => 'decimal:2',
        'active_hours' => 'decimal:2',
        'fetched_at' => 'datetime',
    ];
}

