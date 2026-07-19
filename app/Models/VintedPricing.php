<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VintedPricing extends Model
{
    protected $table = 'vinted_pricing';

    protected $fillable = [
        'sku',
        'price',
        'sprice',
        'l30',
        'nr_req',
        'buyer_link',
        'seller_link',
    ];

    protected $casts = [
        'price'  => 'decimal:2',
        'sprice' => 'decimal:2',
        'l30'    => 'integer',
    ];
}
