<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArrivedContainerHistory extends Model
{
    protected $table = 'arrived_container_history';

    protected $fillable = [
        'action_type',
        'arrived_container_id',
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

    public function arrivedContainer(): BelongsTo
    {
        return $this->belongsTo(ArrivedContainer::class, 'arrived_container_id');
    }
}
