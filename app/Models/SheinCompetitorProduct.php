<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per Shein product surfaced for a search query in
 * /repricer/shein-search. Source feed is SerpApi google_shopping
 * filtered to merchants whose `source` is Shein.
 */
class SheinCompetitorProduct extends Model
{
    use HasFactory;

    protected $table = 'shein_competitor_products';

    protected $fillable = [
        'marketplace',
        'search_query',
        'product_id',
        'product_link',
        'title',
        'source',
        'seller_name',
        'price',
        'extracted_old_price',
        'rating',
        'reviews',
        'position',
        'image',
        'delivery',
        'extensions',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'extracted_old_price' => 'decimal:2',
        'rating' => 'decimal:2',
        'reviews' => 'integer',
        'position' => 'integer',
        'delivery' => 'array',
        'extensions' => 'array',
    ];
}
