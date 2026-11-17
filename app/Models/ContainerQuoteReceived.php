<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContainerQuoteReceived extends Model
{
    protected $table = 'container_quote_received';

    protected $fillable = [
        'container_number',
        'request_id',
        'source',
        'forwarder',
        'shipping_line',
        'est_dt_sail',
        'pickup_charges',
        'freight',
        'total',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];
}