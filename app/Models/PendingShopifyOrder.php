<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingShopifyOrder extends Model
{
    protected $fillable = [
        'reverb_order_metric_id',
        'order_data',
        'attempts',
        'last_attempt_at',
        'last_error',
    ];

    protected $casts = [
        'order_data' => 'array',
        'last_attempt_at' => 'datetime',
    ];

    public function reverbOrderMetric(): BelongsTo
    {
        return $this->belongsTo(ReverbOrderMetric::class, 'reverb_order_metric_id');
    }
}
