<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SWGearExchangeListingStatus extends Model
{
    protected $table = 'sw_gear_exchange_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
