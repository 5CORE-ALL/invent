<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronAlertBatch extends Model
{
    protected $fillable = [
        'window_started_at',
        'window_ended_at',
        'summary',
        'payload',
        'notified',
        'notified_at',
    ];

    protected $casts = [
        'window_started_at' => 'datetime',
        'window_ended_at' => 'datetime',
        'payload' => 'array',
        'notified' => 'boolean',
        'notified_at' => 'datetime',
    ];
}
