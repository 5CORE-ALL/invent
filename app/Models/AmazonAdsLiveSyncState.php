<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonAdsLiveSyncState extends Model
{
    protected $table = 'amazon_ads_live_sync_states';

    protected $fillable = [
        'channel',
        'field',
        'campaign_id',
        'campaign_name',
        'desired_value',
        'live_value',
        'status',
        'reason',
        'attempts',
        'detail',
        'verified_at',
        'locked_at',
    ];

    protected $casts = [
        'desired_value' => 'decimal:2',
        'live_value' => 'decimal:2',
        'attempts' => 'integer',
        'detail' => 'array',
        'verified_at' => 'datetime',
        'locked_at' => 'datetime',
    ];
}
