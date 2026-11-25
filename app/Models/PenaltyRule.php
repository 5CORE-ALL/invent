<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenaltyRule extends Model
{
    protected $fillable = [
        'name', 'is_active', 'metric', 'operator', 'threshold', 'points', 'reason', 'repeat',
        'run_time', 'weekdays_only', 'last_run_at', 'last_added_count', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weekdays_only' => 'boolean',
        'threshold' => 'float',
        'points' => 'integer',
        'last_run_at' => 'datetime',
        'last_added_count' => 'integer',
    ];
}
