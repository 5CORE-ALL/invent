<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-marketplace-task mirror of the yesterday settle ledger (new table, tracking only).
 */
class SettleTaskRun extends Model
{
    protected $table = 'settle_task_runs';

    protected $guarded = [];

    protected $casts = [
        'settle_date' => 'date:Y-m-d',
        'output_markers' => 'array',
        'verification_checks' => 'array',
        'last_run_started_at' => 'datetime',
        'last_run_finished_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
