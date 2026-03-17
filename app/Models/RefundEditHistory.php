<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundEditHistory extends Model
{
    protected $table = 'refund_edit_history';

    public $timestamps = false;

    protected $fillable = [
        'inventory_id', 'sku', 'field', 'old_value', 'new_value', 'updated_by', 'updated_at',
    ];

    protected $casts = ['updated_at' => 'datetime'];
}
