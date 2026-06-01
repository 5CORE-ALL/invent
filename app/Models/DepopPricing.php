<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepopPricing extends Model
{
    protected $table = 'depop_pricing';

    protected $fillable = [
        'sku',
        'price',
        'l30',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'l30'   => 'integer',
    ];
}
