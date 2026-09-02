<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DobaWarehouseShip extends Model
{
    protected $table = 'doba_warehouse_ships';

    protected $fillable = [
        'order_no',
        'shipped',
        'shipped_at',
        'shipped_by',
    ];

    protected $casts = [
        'shipped' => 'boolean',
        'shipped_at' => 'datetime',
    ];
};
