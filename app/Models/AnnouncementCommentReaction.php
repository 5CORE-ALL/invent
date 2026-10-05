<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementCommentReaction extends Model
{
    protected $fillable = [
        'comment_id',
        'user_id',
        'emoji',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(AnnouncementComment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
