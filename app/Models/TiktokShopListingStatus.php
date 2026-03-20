<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokShopListingStatus extends Model
{
    protected $table = 'tiktok_shop_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
