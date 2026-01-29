<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    protected $table = 'shipping_rates';

    protected $fillable = [
        'channel_id',
        'carrier_name',
        'status',
        'lbs_values',
    ];

    protected $casts = [
        'lbs_values' => 'array',
    ];

    public function channel()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }
}
