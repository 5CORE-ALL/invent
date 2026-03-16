<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonProductReview extends Model
{
    use HasFactory;

    protected $table = 'amazon_product_reviews';

    protected $fillable = [
        'sku',
        'product_rating',
        'review_count',
        'link',
        'remarks',
        'comp_link',
        'comp_rating',
        'comp_review_count',
        'comp_remarks',
        'negation_l90',
        'action',
        'corrective_action',
    ];

    public $timestamps = true; 
}
