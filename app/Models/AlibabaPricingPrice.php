<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlibabaPricingPrice extends Model
{
    protected $table = 'alibaba_pricing_prices';

    protected $fillable = [
        'sku',
        'price',
        'sprice',
        'ab_stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sprice' => 'decimal:2',
        'ab_stock' => 'integer',
    ];
}
