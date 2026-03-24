<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SparePartPurchaseOrderItem extends Model
{
    protected $table = 'spare_part_purchase_order_items';

    protected $fillable = [
        'po_id',
        'part_id',
        'qty_ordered',
        'qty_received',
        'unit_cost',
    ];

    protected $casts = [
        'qty_ordered' => 'integer',
        'qty_received' => 'integer',
        'unit_cost' => 'float',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(SparePartPurchaseOrder::class, 'po_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class, 'part_id');
    }

    public function quantityRemainingToReceive(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }
}
