<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CcMessagesPending extends Model
{
    protected $table = 'cc_messages_pending';

    protected $fillable = [
        'channel_id',
        'pending_count',
        'messages_link',
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
        if (! Schema::hasTable('cc_messages_pending')) {
            return 0;
        }

        $query = static::query();
        if (Schema::hasColumn('cc_messages_pending', 'fetch_status')) {
            $query->where('fetch_status', 'ok');
        }

        return (int) $query->sum('pending_count');
    }
}
