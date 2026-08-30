<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonAdsCampaignSku extends Model
{
    protected $table = 'amazon_ads_campaign_skus';

    protected $fillable = [
        'profile_id',
        'ad_id',
        'campaign_id',
        'campaign_name',
        'ad_group_id',
        'sku',
        'asin',
        'state',
        'pulled_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'pulled_at' => 'datetime',
    ];
}
