<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SheinSkuCompetitor extends Model
{
    protected $table = 'shein_sku_competitors';

    protected $fillable = [
        'sku',
        'product_id',
        'marketplace',
        'product_title',
        'seller_name',
        'product_link',
        'image',
        'price',
        'extracted_old_price',
        'rating',
        'reviews',
        'delivery',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'extracted_old_price' => 'decimal:2',
        'rating' => 'decimal:2',
        'reviews' => 'integer',
        'delivery' => 'array',
    ];
}
