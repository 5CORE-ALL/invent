<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingReturnChannel extends Model
{
    protected $table = 'incoming_return_channels';

    protected $fillable = [
        'inventory_id',
        'channel',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }
}
