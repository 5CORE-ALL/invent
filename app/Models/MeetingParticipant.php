<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    /** Browser heartbeat is every ~15s; a participant silent for this long is treated as gone. */
    public const STALE_AFTER_SECONDS = 180;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'is_invited',
        'rsvp',
        'rsvp_at',
        'first_joined_at',
        'joined_at',
        'left_at',
        'last_seen_at',
        'recording_state',
        'recording_gaps',
    ];

    protected $casts = [
        'is_invited' => 'boolean',
        'rsvp_at' => \App\Casts\MeetingDateTime::class,
        'first_joined_at' => \App\Casts\MeetingDateTime::class,
        'joined_at' => \App\Casts\MeetingDateTime::class,
        'left_at' => \App\Casts\MeetingDateTime::class,
        'last_seen_at' => \App\Casts\MeetingDateTime::class,
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInRoom(): bool
    {
        return $this->joined_at !== null
            && $this->left_at === null
            && $this->last_seen_at !== null
            && $this->last_seen_at->gte(now()->subSeconds(self::STALE_AFTER_SECONDS));
    }
}
