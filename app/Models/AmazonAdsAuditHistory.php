<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmazonAdsAuditHistory extends Model
{
    protected $table = 'amazon_ads_audit_histories';

    // Append-only log: only created_at is used.
    public $timestamps = false;

    protected $fillable = [
        'campaign_id',
        'campaign_name',
        'fixed',
        'details',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'fixed' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
