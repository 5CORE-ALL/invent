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
        'updated_by_user_id',
        'updated_by_name',
    ];

    protected $casts = [
        'channel_id' => 'integer',
        'pending_count' => 'integer',
        'updated_by_user_id' => 'integer',
    ];

    public static function pendingTotal(): int
    {
        if (! Schema::hasTable('cc_messages_pending')) {
            return 0;
        }

        return (int) static::query()->sum('pending_count');
    }
}
