<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatChannelMember extends Model
{
    protected $fillable = [
        'channel_id',
        'user_id',
        'last_read_message_id',
        'last_read_at',
        'muted',
        'notify_pref',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'muted' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChatChannel::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
