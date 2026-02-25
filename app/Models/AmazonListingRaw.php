<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonListingRaw extends Model
{
    protected $table = 'amazon_listings_raw';

    protected $fillable = [
        'report_imported_at',
        'seller_sku',
        'asin1',
        'raw_data',
    ];

    protected $casts = [
        'report_imported_at' => 'datetime',
        'raw_data' => 'array',
    ];
}
