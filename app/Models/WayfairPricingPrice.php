<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WayfairPricingPrice extends Model
{
    use HasFactory;

    protected $table = 'wayfair_pricing_prices';

    protected $fillable = [
        'sku',
        'price',
        'wayfair_stock',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'wayfair_stock'  => 'integer',
    ];
}
