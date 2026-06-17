<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleCompetitorItem extends Model
{
    protected $table = 'google_competitor_items';

    protected $fillable = [
        'marketplace',
        'search_query',
        'product_id',
        'source',
        'title',
        'price',
        'link',
        'image',
        'rating',
        'reviews',
        'position',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'rating' => 'decimal:2',
        'reviews' => 'integer',
    ];
}
