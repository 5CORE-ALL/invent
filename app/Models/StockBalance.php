<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBalance extends Model
{
    use HasFactory;

    protected $table = 'stock_balances';

    protected $fillable = [
        'from_warehouse_id',
        'from_parent_name',
        'from_sku',
        'from_dil_percent',
        'from_available_qty',
        'from_adjust_qty',

        'to_warehouse_id',
        'to_parent_name',
        'to_sku',
        'to_dil_percent',
        'to_available_qty',
        'to_adjust_qty',

        'transferred_by',
        'transferred_at',
    ];

     public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }
}
