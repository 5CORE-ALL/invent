<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierLedger extends Model
{
    use HasFactory;

    protected $table = 'supplier_ledgers';

    protected $fillable = [
        'supplier_id',
        'pm_image',
        'purchase_link',
        'dr',
        'cr',
        'balance',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
