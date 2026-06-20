<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokCompetitorProduct extends Model
{
    use HasFactory;

    protected $table = 'tiktok_competitor_products';

    protected $fillable = [
        'marketplace',
        'region',
        'search_query',
        'product_id',
        'product_link',
        'title',
        'brand_name',
        'seller_name',
        'price',
        'min_price',
        'max_price',
        'rating',
        'reviews',
        'sold_count',
        'position',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'rating' => 'decimal:2',
        'reviews' => 'integer',
        'sold_count' => 'integer',
        'position' => 'integer',
    ];
}
