<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonAdsAdGroup extends Model
{
    public const AD_TYPE_SP = 'SPONSORED_PRODUCTS';

    public const AD_TYPE_SB = 'SPONSORED_BRANDS';

    protected $table = 'amazon_ads_ad_groups';

    protected $fillable = [
        'profile_id',
        'ad_type',
        'ad_group_id',
        'campaign_id',
        'campaignName',
        'adGroupName',
        'state',
        'defaultBid',
        'pulled_at',
    ];

    protected $casts = [
        'defaultBid' => 'float',
        'pulled_at' => 'datetime',
    ];
}
