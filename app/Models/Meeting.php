<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE = 'live';
    public const STATUS_ENDED = 'ended';

    public const MINUTES_PENDING = 'pending';
    public const MINUTES_PROCESSING = 'processing';
    public const MINUTES_READY = 'ready';
    public const MINUTES_FAILED = 'failed';
    public const MINUTES_NO_RECORDING = 'no_recording';

    protected $fillable = [
        'title',
        'agenda',
        'room_name',
        'host_user_id',
        'scheduled_at',
        'started_at',
        'ended_at',
        'status',
        'minutes_status',
        'minutes_error',
        'minutes_started_at',
        'minutes_generated_at',
        'transcript',
        'summary',
        'minutes',
        'ai_provider',
        'tasks_generated_at',
    ];

    protected $casts = [
        'scheduled_at' => \App\Casts\MeetingDateTime::class,
        'started_at' => \App\Casts\MeetingDateTime::class,
        'ended_at' => \App\Casts\MeetingDateTime::class,
        'minutes_started_at' => \App\Casts\MeetingDateTime::class,
        'minutes_generated_at' => \App\Casts\MeetingDateTime::class,
        'tasks_generated_at' => \App\Casts\MeetingDateTime::class,
        'minutes' => 'array',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(MeetingRecording::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class)->orderBy('id');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }

    public function durationSeconds(): ?int
    {
        if (! $this->started_at) {
            return null;
        }
        $end = $this->ended_at ?? now();

        return max(0, $this->started_at->diffInSeconds($end));
    }
}
