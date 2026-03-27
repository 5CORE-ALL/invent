<?php

namespace App\Models;

use App\Models\Wms\Bin;
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
        'on_hand',
        'available_qty',
        'shopify_variant_id',
        'shopify_inventory_item_id',
        'loss_gain',
        'reason',
        'is_approved',
        'approved_by',
        'is_ra_checked',
        'approved_at',
        'remarks',
        'comment',
        'is_hide',
        'is_ia',
        'type',
        'is_archived',
        'warehouse_id',
        'bin_id',
        'pick_locked_qty',
        'to_warehouse',
        'adjustment',
        'is_verified',
        'verified_by',
        'is_doubtful',
        'action',
        'combo_action',
        'incoming_images',
        'replacement_tracking',
    ];

    protected $casts = [
        'pick_locked_qty' => 'integer',
        'incoming_images' => 'array',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function bin()
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

    public function warehouseTo()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse');
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }

    public function logs()
    {
        return $this->hasMany(InventoryLog::class, 'sku', 'sku');
    }
    
}
