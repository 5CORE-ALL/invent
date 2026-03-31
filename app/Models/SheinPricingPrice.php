<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SheinPricingPrice extends Model
{
    use HasFactory;

    protected $table = 'shein_pricing_prices';

    protected $fillable = [
        'sku',
        'price',
        'special_offer_price',
        'shein_stock',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'special_offer_price' => 'decimal:2',
        'shein_stock'         => 'integer',
    ];
}
