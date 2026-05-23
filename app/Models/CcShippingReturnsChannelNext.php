<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shipping-page Returns-side "Next" priority value (1..9) per channel.
 */
class CcShippingReturnsChannelNext extends Model
{
    protected $table = 'cc_shipping_returns_channel_next';

    protected $fillable = [
        'channel_id',
        'next_value',
        'updated_by_user_id',
        'updated_by_name',
    ];

    protected $casts = [
        'channel_id'         => 'integer',
        'next_value'         => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
