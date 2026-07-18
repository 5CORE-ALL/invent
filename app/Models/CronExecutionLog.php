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
    public const STATUS_STUCK = 'stuck';
    public const STATUS_RECOVERED = 'recovered';
    public const STATUS_CANCELLED = 'cancelled';

    public const RECOVERY_NONE = 'none';
    public const RECOVERY_ATTEMPTING = 'attempting';
    public const RECOVERY_RECOVERED = 'recovered';
    public const RECOVERY_EXHAUSTED = 'exhausted';

    protected $fillable = [
        'job_name',
        'command',
        'status',
        'started_at',
        'finished_at',
        'duration_seconds',
        'expected_runtime_seconds',
        'expected_records',
        'fetched_records',
        'processed_records',
        'updated_records',
        'inserted_records',
        'skipped_records',
        'failed_records',
        'api_calls',
        'api_latency_ms_avg',
        'api_connected',
        'retry_count',
        'last_retry_at',
        'consecutive_failures',
        'success_percentage',
        'health_score',
        'health_label',
        'failure_category',
        'root_cause',
        'recovery_status',
        'checkpoint',
        'resume_from',
        'validation_message',
        'error_message',
        'exception',
        'meta',
        'anomalies',
        'memory_usage',
        'cpu_time_ms',
        'execution_server',
        'lock_key',
        'pid',
        'cancelled_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'api_connected' => 'boolean',
        'success_percentage' => 'float',
        'meta' => 'array',
        'anomalies' => 'array',
        'checkpoint' => 'array',
    ];

    public function failures(): HasMany
    {
        return $this->hasMany(CronExecutionFailure::class, 'execution_log_id');
    }

    public function unresolvedFailures(): HasMany
    {
        return $this->failures()->where('resolved', false);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(CronExecutionCheckpoint::class, 'execution_log_id');
    }

    public function isHealthy(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_RECOVERED], true);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS, self::STATUS_RECOVERED => 'success',
            self::STATUS_PARTIAL_SUCCESS => 'warning',
            self::STATUS_RUNNING => 'info',
            self::STATUS_STUCK => 'warning',
            default => 'danger',
        };
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_RUNNING, self::STATUS_STUCK], true)
            && $this->cancelled_at === null;
    }

    public function scopeForJob($query, string $jobName)
    {
        return $query->where('job_name', $jobName);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [self::STATUS_SUCCESS, self::STATUS_RECOVERED]);
    }

    public function scopeFinished($query)
    {
        return $query->whereNotNull('finished_at')
            ->where('status', '!=', self::STATUS_RUNNING);
    }
}
