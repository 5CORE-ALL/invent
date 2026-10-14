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
        'sprice_status',
        'sprice_status_at',
        'sprice_pushed_value',
        'sprice_pushed_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sprice' => 'decimal:2',
        'ab_stock' => 'integer',
        'sprice_pushed_value' => 'decimal:2',
    ];
}
