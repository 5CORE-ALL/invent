<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaSyncRun extends Model
{
    use HasFactory;

    protected $table = 'meta_sync_runs';

    protected $fillable = [
        'user_id',
        'ad_account_meta_id',
        'sync_type',
        'status',
        'started_at',
        'finished_at',
        'accounts_synced',
        'campaigns_synced',
        'adsets_synced',
        'ads_synced',
        'insights_synced',
        'error_summary',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'accounts_synced' => 'integer',
        'campaigns_synced' => 'integer',
        'adsets_synced' => 'integer',
        'ads_synced' => 'integer',
        'insights_synced' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
