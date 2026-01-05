<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalmartPriceData extends Model
{
    use HasFactory;

    protected $table = 'walmart_price_data';

    protected $fillable = [
        'sku', 'item_id', 'product_name', 'lifecycle_status', 'publish_status',
        'price', 'currency', 'comparison_price', 'buy_box_price', 
        'buy_box_shipping_price', 'msrp', 'ratings', 'reviews_count',
        'brand', 'product_category'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'comparison_price' => 'decimal:2',
        'buy_box_price' => 'decimal:2',
        'buy_box_shipping_price' => 'decimal:2',
        'msrp' => 'decimal:2',
        'ratings' => 'decimal:2',
        'reviews_count' => 'integer',
    ];
}

