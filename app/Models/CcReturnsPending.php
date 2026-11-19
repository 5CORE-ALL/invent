<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Live "pending returns" count per channel, pulled from each marketplace's
 * returns API. Shown in the Pending column of /customer-care/report.
 */
class CcReturnsPending extends Model
{
    protected $table = 'cc_returns_pending';

    protected $fillable = [
        'channel_id',
        'pending_count',
        'fetch_status',
        'fetch_note',
        'source',
        'last_fetched_at',
        'updated_by_user_id',
        'updated_by_name',
    ];

    protected $casts = [
        'channel_id' => 'integer',
        'pending_count' => 'integer',
        'updated_by_user_id' => 'integer',
        'last_fetched_at' => 'datetime',
    ];

    public static function pendingTotal(): int
    {
        if (! Schema::hasTable('cc_returns_pending')) {
            return 0;
        }

        return (int) static::query()->where('fetch_status', 'ok')->sum('pending_count');
    }
}
