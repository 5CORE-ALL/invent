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
        'page_created',
        'created_at',
    ];

    protected $casts = [
        'page_created' => 'boolean',
        'created_at' => 'datetime',
    ];
}
