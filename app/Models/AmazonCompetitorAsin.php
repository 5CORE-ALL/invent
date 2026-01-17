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
        'price',
        'rating',
        'reviews',
        'position',
        'image',
    ];
}
