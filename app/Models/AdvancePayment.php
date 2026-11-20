<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvancePayment extends Model
{
    use HasFactory;
    protected $table = 'advance_payments';

    // Mass assignable fields
    protected $fillable = [
        'vo_number',
        'supplier_id',
        'purchase_contract_id',
        'amount',
        'advance_amount',
        'image',
        'balance_image',
        'remarks',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    /**
     * Next voucher number for today: VO-ddmmyy-NN.
     */
    public static function nextVoucherNumber(): string
    {
        $prefix = 'VO-' . now()->format('dmy');

        $last = static::where('vo_number', 'like', "$prefix-%")
            ->orderBy('vo_number', 'desc')
            ->value('vo_number');

        $serial = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

        return $prefix . '-' . str_pad((string) $serial, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Keep the Advance & Payments page in step with a purchase contract (PO):
     * - no payment row yet  -> create one (supplier, amount, advance from the PO)
     * - row(s) already exist -> refresh amount / advance so they follow the PO
     * Archived POs get an archived row. A row the user archived is never re-created.
     */
    public static function syncFromPurchaseOrder(PurchaseOrder $po): void
    {
        if (empty($po->supplier_id)) {
            return;
        }

        $existing = static::where('purchase_contract_id', $po->id);

        if ($existing->exists()) {
            $existing->update([
                'amount' => $po->total_amount ?? 0,
                'advance_amount' => $po->advance_amount ?? 0,
            ]);
            return;
        }

        static::create([
            'vo_number' => static::nextVoucherNumber(),
            'supplier_id' => $po->supplier_id,
            'purchase_contract_id' => $po->id,
            'amount' => $po->total_amount ?? 0,
            'advance_amount' => $po->advance_amount ?? 0,
            // An archived PO gets an archived row, so it shows under Status = Archived on both pages.
            'is_archived' => (bool) $po->is_archived,
            'archived_at' => $po->is_archived ? now() : null,
        ]);
    }

    /**
     * Archive state is shared by a PO and its payment row(s): archive / restore on either page
     * (Purchase Contracts or Advance & Payments) and both pages follow.
     */
    public static function mirrorArchiveFromPurchaseOrder(PurchaseOrder $po): void
    {
        if ($po->is_archived) {
            static::where('purchase_contract_id', $po->id)
                ->where('is_archived', false)
                ->update(['is_archived' => true, 'archived_at' => now()]);
        } else {
            static::where('purchase_contract_id', $po->id)
                ->where('is_archived', true)
                ->update(['is_archived' => false, 'archived_at' => null]);
        }
    }

    /**
     * Relation with Supplier
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Relation with Purchase Contract
     */
    public function purchaseContract()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_contract_id');
    }
}
