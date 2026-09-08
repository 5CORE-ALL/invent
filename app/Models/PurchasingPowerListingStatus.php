<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasingPowerListingStatus extends Model
{
    protected $table = 'purchasing_power_listing_statuses';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
