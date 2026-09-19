<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatPresence extends Model
{
    protected $table = 'chat_presences';

    protected $fillable = [
        'user_id',
        'last_seen_at',
        'status',
        'typing_channel_id',
        'typing_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
