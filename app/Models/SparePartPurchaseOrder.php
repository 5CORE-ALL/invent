<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparePartPurchaseOrder extends Model
{
    protected $table = 'spare_part_purchase_orders';

    protected $fillable = [
        'po_number',
        'supplier_id',
        'status',
        'expected_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'expected_at' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SparePartPurchaseOrderItem::class, 'po_id');
    }
}
