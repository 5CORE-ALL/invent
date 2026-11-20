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
