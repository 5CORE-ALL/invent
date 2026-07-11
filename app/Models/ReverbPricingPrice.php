<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReverbPricingPrice extends Model
{
    protected $table = 'reverb_pricing_prices';

    protected $fillable = [
        'sku',
        'price',
        'rv_stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'rv_stock' => 'integer',
    ];
}
