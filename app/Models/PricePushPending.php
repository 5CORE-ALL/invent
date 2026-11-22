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

    /** Marker written while the server is pushing a row in the background (see PricePushPendingController::pushBackground). */
    public const QUEUED_PREFIX = 'Queued';

    /**
     * Rows that still need attention. A row that is merely "Queued" (being pushed right now by the background push)
     * only counts once it has been stuck for 15 minutes — then the hourly retry takes it over.
     */
    public function scopeOutstanding($q)
    {
        return $q->whereIn('status', ['pending', 'failed'])
            ->where(function ($w) {
                $w->whereNull('last_error')
                    ->orWhere('last_error', 'not like', self::QUEUED_PREFIX . '%')
                    ->orWhere('last_attempt_at', '<', now()->subMinutes(15));
            });
    }
}
