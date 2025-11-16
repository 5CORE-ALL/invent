<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventories'; 

    protected $fillable = [
        'sku',
        'is_ra_checked',
        'verified_stock',
        'to_adjust',
        'loss_gain',
        'reason',
        'is_approved',
        'approved_by',
        'is_ra_checked',
        'approved_at',
        'remarks',
        'is_hide',
        'type',
        'warehouse_id',
        'to_warehouse',
        'adjustment',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function warehouseTo()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse');
    }
    
}
