<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyB2CListingStatus extends Model
{
    protected $table = 'shopify_b2c_listing_statuses';
    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
