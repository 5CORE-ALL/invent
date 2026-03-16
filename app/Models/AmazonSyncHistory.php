<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonSyncHistory extends Model
{
    protected $table = 'amazon_sync_history';

    protected $fillable = [
        'started_at',
        'finished_at',
        'status',
        'records_fetched',
        'records_updated',
        'records_created',
        'records_skipped',
        'api_calls_count',
        'retry_count',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PARTIAL = 'partial';
}
