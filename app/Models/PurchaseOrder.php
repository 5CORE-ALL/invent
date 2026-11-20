<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [ 
        'po_number',
        'total_amount',
        'po_date',            
        'supplier_id', 
        'items',
        'approvals',
        'advance_amount',
        'advance_percent',
        'is_archived',
        'po_stage',
        'currency',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'approvals' => 'array',
        'advance_amount' => 'float',
        'advance_percent' => 'float',
    ];

    protected static function booted(): void
    {
        // New purchase contract -> appears on /ledger-master/advance-and-payments automatically.
        static::created(function (PurchaseOrder $po) {
            try {
                AdvancePayment::syncFromPurchaseOrder($po);
            } catch (\Throwable $e) {
                \Log::warning('Advance payment auto-create failed for PO ' . $po->po_number . ': ' . $e->getMessage());
            }
        });

        // Total / advance changed (items added, advance updated, ...) -> keep the row in step.
        static::updated(function (PurchaseOrder $po) {
            if (! $po->wasChanged(['total_amount', 'advance_amount', 'supplier_id', 'is_archived'])) {
                return;
            }
            try {
                // Archive / restore on a PO is mirrored to its payment row(s).
                if ($po->wasChanged('is_archived')) {
                    AdvancePayment::mirrorArchiveFromPurchaseOrder($po);
                }
                AdvancePayment::syncFromPurchaseOrder($po);
            } catch (\Throwable $e) {
                \Log::warning('Advance payment sync failed for PO ' . $po->po_number . ': ' . $e->getMessage());
            }
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function supplierAdvances()
    {
        return $this->hasMany(SupplierAdvance::class);
    }
}
