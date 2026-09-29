<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFulfillmentTracking extends Model
{
    protected $table = 'order_fulfillment_trackings';

    protected $fillable = [
        'row_key',
        'mm_slug',
        'order_id',
        'sku',
        'tracking_number',
        'carrier',
        'source',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];
}
