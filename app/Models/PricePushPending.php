<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per SKU + channel that was pushed from the /price-increase modals.
 * status: pending (last push failed, retried hourly) | failed (gave up) | pushed (done).
 */
class PricePushPending extends Model
{
    protected $table = 'price_push_pendings';

    protected $fillable = [
        'sku', 'marketplace', 'channel', 'price', 'extras', 'status', 'attempts',
        'last_error', 'last_attempt_at', 'pushed_at', 'alerted_at', 'requested_by',
    ];

    protected $casts = [
        'price' => 'float',
        'extras' => 'array',
        'attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'pushed_at' => 'datetime',
        'alerted_at' => 'datetime',
    ];

    public function scopeOutstanding($q)
    {
        return $q->whereIn('status', ['pending', 'failed']);
    }
}
