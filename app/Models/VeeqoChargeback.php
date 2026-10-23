<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeeqoChargeback extends Model
{
    protected $table = 'veeqo_chargebacks';

    protected $guarded = ['id'];

    protected $casts = [
        'components' => 'array',
        'raw' => 'array',
        'posted_at' => 'datetime',
    ];
}
