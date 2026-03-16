<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToOrderReview extends Model
{
    protected $table = 'to_order_review';

    public $timestamps = false; // Disable timestamps

    protected $fillable = [
        'parent',
        'sku',
        'supplier',
        'positive_review',
        'negative_review',
        'improvement',
        'date_updated',
    ];
}
