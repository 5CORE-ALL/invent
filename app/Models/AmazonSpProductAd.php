<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonSpProductAd extends Model
{
    protected $table = 'amazon_sp_product_ads';

    protected $fillable = [
        'profile_id',
        'ad_id',
        'campaign_id',
        'ad_group_id',
        'sku',
        'asin',
        'state',
        'pulled_at',
    ];

    protected $casts = [
        'pulled_at' => 'datetime',
    ];
}
