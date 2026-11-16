<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContainerQuoteReceived extends Model
{
    protected $table = 'container_quote_received';

    protected $fillable = [
        'container_number',
        'forwarder',
        'pickup_charges',
        'freight',
        'total',
        'updated_by',
    ];
}