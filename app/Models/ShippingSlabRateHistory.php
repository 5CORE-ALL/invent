<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingSlabRateHistory extends Model
{
    protected $table = 'shipping_slab_rate_history';

    public $timestamps = false;

    protected $fillable = [
        'slab_key',
        'slab_label',
        'field',
        'old_value',
        'new_value',
        'skus_updated',
        'scope',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
        'skus_updated' => 'integer',
    ];
}
