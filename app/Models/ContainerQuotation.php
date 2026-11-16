<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContainerQuotation extends Model
{
    protected $table = 'container_quotations';

    protected $fillable = [
        'container_number',
        'supplier',
        'container_size',
        'pickup_address',
        'loading_port',
        'discharge_port',
        'gross_weight',
        'freight_all_inclusive',
        'forwarder',
        'updated_by',
    ];
}