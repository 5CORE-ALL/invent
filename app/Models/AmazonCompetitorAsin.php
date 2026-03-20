<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonCompetitorAsin extends Model
{
    use HasFactory;

    protected $table = 'amazon_competitor_asins';

    protected $fillable = [
        'marketplace',
        'search_query',
        'asin',
        'title',
        'seller_name',
        'price',
        'rating',
        'reviews',
        'position',
        'image',
        'extracted_old_price',
        'delivery',
    ];

    protected $casts = [
        'delivery' => 'array',
    ];
}
