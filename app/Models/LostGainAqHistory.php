<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LostGainAqHistory extends Model
{
    protected $table = 'lost_gain_aq_histories';

    protected $fillable = [
        'batch_uuid',
        'user_id',
        'inventory_id',
        'sku',
        'old_to_adjust',
        'new_to_adjust',
        'old_loss_gain',
        'new_loss_gain',
    ];

    protected $casts = [
        'old_loss_gain' => 'decimal:2',
        'new_loss_gain' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
