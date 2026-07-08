<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsAuditHistory extends Model
{
    protected $table = 'google_ads_audit_histories';

    public $timestamps = false;

    protected $fillable = [
        'channel',
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
