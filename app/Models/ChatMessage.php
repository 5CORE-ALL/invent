<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'channel_id',
        'user_id',
        'is_bot',
        'bot_name',
        'body',
        'attachment_path',
        'attachment_name',
        'mentions',
        'command',
    ];

    protected $casts = [
        'is_bot' => 'boolean',
        'mentions' => 'array',
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
