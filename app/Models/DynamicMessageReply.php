<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicMessageReply extends Model
{
    protected $fillable = ['message_id', 'user_id', 'body', 'seen_at'];

    protected $casts = ['seen_at' => 'datetime'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(DynamicMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
