<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FBMarketplaceListingStatus extends Model
{
    protected $table = 'fb_marketplace_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
