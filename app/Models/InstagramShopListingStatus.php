<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramShopListingStatus extends Model
{
    protected $table = 'instagram_shop_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
