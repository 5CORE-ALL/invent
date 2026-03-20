<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NegativeReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_date',
        'marketplace',
        'sku',
        'rating',
        'review_category',
        'review_text',
        'review_summary',
        'reviewer_name',
        'action_status',
        'action_taken',
        'action_date',
    ];
}

