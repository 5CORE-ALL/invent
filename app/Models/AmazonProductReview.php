<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonProductReview extends Model
{
    use HasFactory;

    protected $table = 'amazon_product_reviews';

    protected $fillable = [
        'channel',
        'sku',
        'asin',
        'product_rating',
        'review_count',
        'source',
        'link',
        'remarks',
        'comp_link',
        'comp_rating',
        'comp_review_count',
        'comp_remarks',
        'negation_l90',
        'action',
        'corrective_action',
        'fetched_at',
    ];

    protected $casts = [
        'product_rating' => 'float',
        'review_count' => 'integer',
        'fetched_at' => 'datetime',
    ];

    public $timestamps = true;
}
