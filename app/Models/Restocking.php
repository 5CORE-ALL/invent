<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restocking extends Model
{
    protected $table = 'restockings';

    protected $fillable = [
        'date',
        'order_id',
        'channel',
        'sku',
        'qty',
        'selling_price',
        'refunded_amount',
        'recovered_price',
        'restocked',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'qty' => 'integer',
        'selling_price' => 'float',
        'refunded_amount' => 'float',
        'recovered_price' => 'float',
        'restocked' => 'boolean',
    ];
}
