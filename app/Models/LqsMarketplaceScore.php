<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LqsMarketplaceScore extends Model
{
    protected $table = 'lqs_marketplace_scores';

    protected $fillable = [
        'marketplace',
        'sku',
        'lqs',
        'rating',
        'reviews',
        'l30',
        'sessions',
        'price',
        'listing_id',
        'audit_findings',
        'audit_suggestions',
    ];

    protected $casts = [
        'lqs' => 'float',
        'rating' => 'float',
        'reviews' => 'integer',
        'l30' => 'integer',
        'sessions' => 'integer',
        'price' => 'float',
    ];
}
