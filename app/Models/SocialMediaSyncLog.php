<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaSyncLog extends Model
{
    protected $fillable = [
        'social_media_account_id',
        'platform',
        'sync_type',
        'started_at',
        'completed_at',
        'status',
        'records_processed',
        'error_message',
        'reference',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialMediaAccount::class, 'social_media_account_id');
    }
}
