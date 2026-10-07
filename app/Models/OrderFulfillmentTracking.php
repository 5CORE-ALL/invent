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
        'shopify_order_id',
        'shopify_fulfilled_at',
        'shopify_push_attempts',
        'shopify_push_checked_at',
        'shopify_next_try_at',
        'shopify_push_message',
        'channel_pushed_at',
        'channel_push_attempts',
        'channel_push_message',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'shopify_fulfilled_at' => 'datetime',
        'shopify_push_checked_at' => 'datetime',
        'shopify_next_try_at' => 'datetime',
        'channel_pushed_at' => 'datetime',
        'shopify_push_attempts' => 'integer',
        'channel_push_attempts' => 'integer',
    ];
}
