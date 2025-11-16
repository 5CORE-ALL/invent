<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContainerPlanning extends Model
{
    use HasFactory;

    protected $table = 'container_plannings';

    protected $fillable = [
        'container_number',
        'po_number',
        'supplier_id',
        'area',
        'packing_list_link',
        'currency',
        'invoice_value',
        'paid',
        'balance',
        'pay_term',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_number');
    }
}
