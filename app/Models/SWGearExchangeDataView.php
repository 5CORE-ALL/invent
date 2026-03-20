<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SWGearExchangeDataView extends Model
{
    protected $table = 'sw_gear_exchange_data_views';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
