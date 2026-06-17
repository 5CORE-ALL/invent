<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimReimbursement extends Model
{

    protected $fillable = [
        'supplier_id', 'claim_number', 'claim_date', 'items', 'total_amount', 'created_by', 'action_history', 'received_amount', 'details_note', 'follow_up_date', 'is_archived', 'archived_by', 'archived_at',
    ];

    protected $casts = [
        'items' => 'array',
        'action_history' => 'array',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

}
