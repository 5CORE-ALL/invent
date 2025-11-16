<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EbayVariationListingStatus extends Model
{
    protected $table = 'ebay_variation_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
