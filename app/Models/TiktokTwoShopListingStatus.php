<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokTwoShopListingStatus extends Model
{
    protected $table = 'tiktok_two_shop_listing_statuses';

    protected $fillable = ['sku', 'value'];

    protected $casts = ['value' => 'array'];
}
