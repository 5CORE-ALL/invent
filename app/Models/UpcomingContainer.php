<?php

namespace App\Models;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UpcomingContainer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'container_number',
        'area',
        'order_link',
        'invoice_value',
        'paid',
        'balance',
        'payment_terms',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}

