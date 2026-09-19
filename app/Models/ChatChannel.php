<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatChannel extends Model
{
    public const TYPE_PUBLIC = 'public';

    public const TYPE_PRIVATE = 'private';

    public const TYPE_DM = 'dm';

    public const TYPE_GROUP = 'group';

    public const TYPE_BOT = 'bot';

    protected $fillable = [
        'type',
        'name',
        'slug',
        'topic',
        'dm_key',
        'created_by',
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ChatChannelMember::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'channel_id');
    }

    public function isDm(): bool
    {
        return $this->type === self::TYPE_DM;
    }

    public function isBotInbox(): bool
    {
        return $this->type === self::TYPE_BOT;
    }

    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }
}
