<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MmWebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'mm_webhook_events';

    protected $fillable = [
        'source',
        'webhook_id',
        'topic',
        'inventory_item_id',
        'payload',
        'status',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
