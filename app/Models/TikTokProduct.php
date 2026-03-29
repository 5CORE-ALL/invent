<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TikTokProduct extends Model
{
    protected $table = 'tiktok_products';

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'stock',
        'sold',
        'views',
        'video_views',
        'ads_views',
        'affl_views',
        'reviews',
        'rating'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'sold' => 'integer',
        'views' => 'decimal:2',
        'video_views' => 'integer',
        'ads_views' => 'integer',
        'affl_views' => 'integer',
        'reviews' => 'integer',
        'rating' => 'decimal:2'
    ];
}

