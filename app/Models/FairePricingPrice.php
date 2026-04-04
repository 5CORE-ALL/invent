<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FairePricingPrice extends Model
{
    use HasFactory;

    protected $table = 'faire_pricing_prices';

    protected $fillable = [
        'sku',
        'price',
        'faire_stock',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'faire_stock'  => 'integer',
    ];
}
