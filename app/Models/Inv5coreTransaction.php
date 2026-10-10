<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inv5coreTransaction extends Model
{
    protected $table = 'inv_5core_transactions';

    protected $fillable = [
        'balance_id',
        'product_master_id',
        'sku',
        'txn_type',
        'qty_delta',
        'qty_before',
        'qty_after',
        'unavailable_delta',
        'unavailable_after',
        'committed_delta',
        'committed_after',
        'available_delta',
        'available_after',
        'source',
        'source_id',
        'reference',
        'channel',
        'detail',
        'occurred_at',
        'created_by',
    ];

    protected $casts = [
        'qty_delta' => 'float',
        'qty_before' => 'float',
        'qty_after' => 'float',
        'unavailable_delta' => 'float',
        'unavailable_after' => 'float',
        'committed_delta' => 'float',
        'committed_after' => 'float',
        'available_delta' => 'float',
        'available_after' => 'float',
        'occurred_at' => 'datetime',
    ];

    public function balance(): BelongsTo
    {
        return $this->belongsTo(Inv5coreBalance::class, 'balance_id');
    }
}
