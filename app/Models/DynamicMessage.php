<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DynamicMessage extends Model
{
    protected $fillable = [
        'rule_id', 'recipient_user_id', 'event_key', 'page_label', 'title', 'body',
        'context', 'dedupe_key', 'allow_reply', 'acknowledged_at',
    ];

    protected $casts = [
        'context' => 'array',
        'allow_reply' => 'boolean',
        'acknowledged_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DynamicMessageRule::class, 'rule_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id')->withTrashed();
    }

    public function reply(): HasOne
    {
        return $this->hasOne(DynamicMessageReply::class, 'message_id');
    }
}
