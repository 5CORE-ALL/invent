<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronExecutionFailure extends Model
{
    protected $fillable = [
        'execution_log_id',
        'sku',
        'marketplace',
        'failure_reason',
        'failure_category',
        'http_status',
        'recoverable',
        'root_cause',
        'api_response',
        'retry_count',
        'last_retry_at',
        'resolved',
        'resolved_at',
        'meta',
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'recoverable' => 'boolean',
        'resolved_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'meta' => 'array',
    ];

    public function executionLog(): BelongsTo
    {
        return $this->belongsTo(CronExecutionLog::class, 'execution_log_id');
    }

    public function markResolved(): void
    {
        $this->update([
            'resolved' => true,
            'resolved_at' => now(),
        ]);
    }

    public function canRetry(?int $max = null): bool
    {
        $max ??= (int) config('cron-monitor.retry.max_attempts', 3);

        return ! $this->resolved && $this->retry_count < $max;
    }
}
