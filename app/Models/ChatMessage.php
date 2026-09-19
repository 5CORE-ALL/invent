<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        'forwarded_from_id',
        'client_id',
        'parent_id',
        'edited_at',
        'pinned_at',
        'pinned_by',
        'task_id',
        'attachment_mime',
        'attachment_size',
        'deleted_at',
    ];

    protected $casts = [
        'is_bot' => 'boolean',
        'mentions' => 'array',
        'edited_at' => 'datetime',
        'pinned_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if ($model->getAttribute($model->getKeyName()) !== null) {
                return;
            }

            try {
                $col = DB::selectOne("SHOW COLUMNS FROM `{$model->getTable()}` WHERE Field = 'id'");
                $extra = strtolower((string) ($col->Extra ?? ''));
                if (str_contains($extra, 'auto_increment')) {
                    return;
                }
            } catch (\Throwable) {
            }

            $model->incrementing = false;
            $model->id = ((int) (DB::table($model->getTable())->max('id') ?? 0)) + 1;
        });
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChatChannel::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function forwardedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forwarded_from_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatReaction::class, 'message_id');
    }
}
