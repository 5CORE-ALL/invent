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
        'is_archived'
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'approvals' => 'array',
        'advance_amount' => 'float',
        'advance_percent' => 'float',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function supplierAdvances()
    {
        return $this->hasMany(SupplierAdvance::class);
    }
}
