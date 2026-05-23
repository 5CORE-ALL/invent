<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shipping-page Messages-side "Next" priority value (1..9) per channel.
 */
class CcShippingChannelNext extends Model
{
    protected $table = 'cc_shipping_channel_next';

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
