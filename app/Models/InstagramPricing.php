<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramPricing extends Model
{
    protected $table = 'instagram_pricing';

    protected $fillable = [
        'sku',
        'price',
        'sprice',
        'l30',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sprice' => 'decimal:2',
        'l30' => 'integer',
    ];
}
