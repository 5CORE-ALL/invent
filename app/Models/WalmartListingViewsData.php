<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalmartListingViewsData extends Model
{
    use HasFactory;

    protected $table = 'walmart_listing_views_data';

    protected $fillable = [
        'sku', 'item_id', 'product_name', 'product_type', 'listing_quality',
        'content_discoverability', 'ratings_reviews', 'competitive_price_score',
        'shipping_score', 'transactibility_score', 'conversion_rate',
        'competitive_price', 'walmart_price', 'gmv', 'ratings', 'priority',
        'oos', 'condition', 'page_views', 'total_issues', 'customer_favourites',
        'collectible_grade', 'fast_free_shipping'
    ];

    protected $casts = [
        'conversion_rate' => 'decimal:2',
        'walmart_price' => 'decimal:2',
        'gmv' => 'decimal:2',
        'ratings' => 'decimal:2',
        'page_views' => 'integer',
        'total_issues' => 'integer',
    ];
}

