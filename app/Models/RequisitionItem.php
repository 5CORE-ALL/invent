<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItem extends Model
{
    protected $fillable = [
        'requisition_id',
        'part_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_issued',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_approved' => 'integer',
        'quantity_issued' => 'integer',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class, 'part_id');
    }

    public function quantityRemainingToIssue(): int
    {
        $approved = $this->quantity_approved ?? 0;

        return max(0, $approved - (int) $this->quantity_issued);
    }
}
