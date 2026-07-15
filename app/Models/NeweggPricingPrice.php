<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeweggPricingPrice extends Model
{
    protected $table = 'newegg_pricing_prices';

    protected $fillable = [
        'sku',
        'price',
        'ne_stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ne_stock' => 'integer',
    ];
}
