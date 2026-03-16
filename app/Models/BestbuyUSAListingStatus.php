<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BestbuyUSAListingStatus extends Model
{
    protected $table = 'bestbuy_usa_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
