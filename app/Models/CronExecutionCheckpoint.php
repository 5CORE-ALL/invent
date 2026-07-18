<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronExecutionCheckpoint extends Model
{
    protected $fillable = [
        'execution_log_id',
        'job_name',
        'command',
        'cursor',
        'processed_offset',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'processed_offset' => 'integer',
    ];

    public function executionLog(): BelongsTo
    {
        return $this->belongsTo(CronExecutionLog::class, 'execution_log_id');
    }

    public function decodedCursor(): mixed
    {
        if ($this->cursor === null) {
            return null;
        }

        $decoded = json_decode($this->cursor, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->cursor;
    }
}
