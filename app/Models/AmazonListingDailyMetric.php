<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonListingDailyMetric extends Model
{
    use HasFactory;

    protected $table = 'amazon_listing_daily_metrics';

    protected $fillable = [
        'date',
        'missing_status_inv_count',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
