<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleAdsMissingLink extends Model
{
    protected $table = 'google_ads_missing_links';

    public $timestamps = false;

    protected $fillable = [
        'channel',
        'sku',
        'campaign_id',
        'campaign_name',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
