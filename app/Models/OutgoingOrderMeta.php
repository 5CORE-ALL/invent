<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingOrderMeta extends Model
{
    protected $table = 'outgoing_order_meta';

    protected $fillable = [
        'inventory_id',
        'order_id',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }
}
