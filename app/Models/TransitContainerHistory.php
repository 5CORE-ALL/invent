<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransitContainerHistory extends Model
{
    protected $table = 'transit_container_history';

    protected $fillable = [
        'action_type',
        'transit_container_detail_id',
        'from_tab',
        'to_tab',
        'our_sku',
        'details',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transitContainerDetail(): BelongsTo
    {
        return $this->belongsTo(TransitContainerDetail::class, 'transit_container_detail_id');
    }
}
