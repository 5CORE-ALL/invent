<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    protected static function booted(): void
    {
        // Some local/prod dumps lack AUTO_INCREMENT on id.
        static::creating(function (self $model) {
            if ($model->getKey() !== null) {
                return;
            }

            try {
                $col = DB::selectOne("SHOW COLUMNS FROM `{$model->getTable()}` WHERE Field = 'id'");
                $extra = strtolower((string) ($col->Extra ?? ''));
                if (str_contains($extra, 'auto_increment')) {
                    return;
                }
            } catch (\Throwable) {
                // fall through and assign manually
            }

            $model->id = ((int) (DB::table($model->getTable())->max('id') ?? 0)) + 1;
        });
    }

    public function executionLog(): BelongsTo
    {
        return $this->belongsTo(CronExecutionLog::class, 'execution_log_id');
    }
}
