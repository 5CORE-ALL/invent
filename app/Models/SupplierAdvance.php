<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAdvance extends Model
{
    protected $fillable = [
        'supplier_id',
        'purchase_order_id',
        'advance_percent',
        'advance_amount',
        'grand_total',
        'currency',
        'created_by',
        'created_by_name',
    ];

    protected $casts = [
        'advance_percent' => 'float',
        'advance_amount' => 'float',
        'grand_total' => 'float',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
