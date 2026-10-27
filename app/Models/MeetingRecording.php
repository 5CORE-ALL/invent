<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRecording extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'session_token',
        'path',
        'mime',
        'chunks_received',
        'size_bytes',
        'started_at',
        'last_chunk_at',
        'finalized_at',
        'duration_seconds',
        'transcript_status',
        'transcript_segments',
        'transcript_error',
    ];

    protected $casts = [
        'started_at' => \App\Casts\MeetingDateTime::class.':ms',
        'last_chunk_at' => \App\Casts\MeetingDateTime::class,
        'finalized_at' => \App\Casts\MeetingDateTime::class,
        'transcript_segments' => 'array',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
