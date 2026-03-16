<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FBShopListingStatus extends Model
{
    protected $table = 'fb_shop_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
