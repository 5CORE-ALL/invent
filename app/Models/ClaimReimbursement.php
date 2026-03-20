<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimReimbursement extends Model
{

    protected $fillable = [
        'supplier_id', 'claim_number', 'claim_date', 'items', 'total_amount',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

}
