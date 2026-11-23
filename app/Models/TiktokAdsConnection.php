<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokAdsConnection extends Model
{
    protected $table = 'tiktok_ads_connections';

    protected $fillable = [
        'advertiser_id',
        'advertiser_name',
        'currency',
        'access_token',
        'scope',
        'authorized_at',
    ];

    protected $hidden = ['access_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'scope' => 'array',
        'authorized_at' => 'datetime',
    ];
}
