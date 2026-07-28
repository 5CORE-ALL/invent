<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvVerifyContainerAudit extends Model
{
    protected $table = 'inv_verify_container_audits';

    protected $fillable = [
        'arrived_container_id',
        'our_sku',
        'supplier_name',
        'action_history',
    ];

    protected $casts = [
        'action_history' => 'array',
    ];

    public function arrivedContainer(): BelongsTo
    {
        return $this->belongsTo(ArrivedContainer::class, 'arrived_container_id');
    }
}
