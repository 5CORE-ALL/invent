<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingExternalRecording extends Model
{
    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_LINK = 'link';
    public const SOURCE_GOOGLE_MEET = 'google_meet';

    protected $fillable = [
        'meeting_id', 'source', 'title', 'url', 'path', 'original_name', 'mime',
        'size_bytes', 'google_resource', 'added_by',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function isFile(): bool
    {
        return $this->source === self::SOURCE_UPLOAD && filled($this->path);
    }
}
