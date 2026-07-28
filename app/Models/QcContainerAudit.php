<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcContainerAudit extends Model
{
    protected $table = 'qc_container_audits';

    protected $fillable = [
        'arrived_container_id',
        'our_sku',
        'supplier_name',
        'items',
        'claim_links',
        'action_history',
        'audited_by',
    ];

    protected $casts = [
        'items' => 'array',
        'claim_links' => 'array',
        'action_history' => 'array',
    ];

    public function arrivedContainer(): BelongsTo
    {
        return $this->belongsTo(ArrivedContainer::class, 'arrived_container_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by');
    }
}
