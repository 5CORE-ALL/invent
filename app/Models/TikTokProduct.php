<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TikTokProduct extends Model
{
    protected $table = 'tiktok_products';

    protected $fillable = [
        'sku',
        'price',
        'stock',
        'sold'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'sold' => 'integer'
    ];
}

