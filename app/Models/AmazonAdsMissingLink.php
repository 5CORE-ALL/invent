<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonAdsMissingLink extends Model
{
    protected $table = 'amazon_ads_missing_links';

    public $timestamps = false;

    protected $fillable = [
        'sku',
        'type',
        'campaign_id',
        'campaign_name',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
