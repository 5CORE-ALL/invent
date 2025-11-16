<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokGmvAd extends Model
{
    use HasFactory;

    protected $table = 'tiktok_gmv_ads';

    protected $fillable = [
        'sku',
        'ad_sold',
        'ad_sales',
        'spend',
        'budget',
        'status',
    ];
}
