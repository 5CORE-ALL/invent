<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelsReviewsData extends Model
{
    use HasFactory;

    protected $table = 'channels_reviews_data';

    // Disable timestamps since the table doesn't have them
    public $timestamps = false;

    protected $fillable = [
        'sku',
        'values',
        'amazon_reviews',
        'ebay_one_reviews',
        'ebay_two_reviews',
        'ebay_three_reviews',
        'temu_reviews',
    ];

    protected $casts = [
        'values' => 'array',
    ];
}
