<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AliexpressPricingPrice extends Model
{
    protected $table = 'aliexpress_pricing_prices';

    protected $fillable = [
        'sku',
        'price',
        'ae_stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ae_stock' => 'integer',
    ];
}
