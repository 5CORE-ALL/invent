<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMasterHistory extends Model
{
    protected $table = 'shipping_master_history';

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'sku', 'field', 'old_value', 'new_value', 'updated_by', 'updated_at',
    ];

    protected $casts = ['updated_at' => 'datetime'];
}
