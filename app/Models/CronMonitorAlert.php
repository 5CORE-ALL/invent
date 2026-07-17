<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronMonitorAlert extends Model
{
    protected $fillable = [
        'execution_log_id',
        'job_name',
        'alert_type',
        'severity',
        'title',
        'message',
        'payload',
        'notified',
        'notified_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'notified' => 'boolean',
        'notified_at' => 'datetime',
    ];

    public function executionLog(): BelongsTo
    {
        return $this->belongsTo(CronExecutionLog::class, 'execution_log_id');
    }
}
