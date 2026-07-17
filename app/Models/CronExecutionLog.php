<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CronExecutionLog extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL_SUCCESS = 'partial_success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_TIMED_OUT = 'timed_out';
    public const STATUS_MISSED = 'missed';

    protected $fillable = [
        'job_name',
        'command',
        'status',
        'started_at',
        'finished_at',
        'duration_seconds',
        'expected_records',
        'fetched_records',
        'processed_records',
        'updated_records',
        'inserted_records',
        'skipped_records',
        'failed_records',
        'api_calls',
        'api_connected',
        'retry_count',
        'success_percentage',
        'health_score',
        'health_label',
        'validation_message',
        'error_message',
        'exception',
        'meta',
        'anomalies',
        'memory_usage',
        'execution_server',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'api_connected' => 'boolean',
        'success_percentage' => 'float',
        'meta' => 'array',
        'anomalies' => 'array',
    ];

    public function failures(): HasMany
    {
        return $this->hasMany(CronExecutionFailure::class, 'execution_log_id');
    }

    public function unresolvedFailures(): HasMany
    {
        return $this->failures()->where('resolved', false);
    }

    public function isHealthy(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'success',
            self::STATUS_PARTIAL_SUCCESS => 'warning',
            self::STATUS_RUNNING => 'info',
            default => 'danger',
        };
    }

    public function scopeForJob($query, string $jobName)
    {
        return $query->where('job_name', $jobName);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFinished($query)
    {
        return $query->whereNotNull('finished_at')
            ->where('status', '!=', self::STATUS_RUNNING);
    }
}
