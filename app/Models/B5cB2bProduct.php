<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class B5cB2bProduct extends Model
{
    protected $table = 'b5c_b2b_products';

    protected $fillable = [
        'listing_id',
        'sku',
        'slug',
        'title',
        'qty',
        'price',
        'special_price',
        'in_stock',
        'is_active',
        'payload',
    ];

    protected $casts = [
        'qty' => 'integer',
        'price' => 'decimal:2',
        'special_price' => 'decimal:2',
        'in_stock' => 'boolean',
        'is_active' => 'boolean',
        'payload' => 'array',
    ];
}
