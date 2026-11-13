<?php

namespace App\Models;

use App\Casts\MeetingDateTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meeting extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE = 'live';
    public const STATUS_ENDED = 'ended';
    public const STATUS_CANCELLED = 'cancelled';

    public const MINUTES_PENDING = 'pending';
    public const MINUTES_PROCESSING = 'processing';
    public const MINUTES_READY = 'ready';
    public const MINUTES_FAILED = 'failed';
    public const MINUTES_NO_RECORDING = 'no_recording';

    public const PROVIDER_JITSI = 'jitsi';
    public const PROVIDER_GOOGLE_MEET = 'google_meet';

    public const SYNC_PENDING = 'pending';
    public const SYNC_SYNCED = 'synced';
    public const SYNC_FAILED = 'failed';
    public const SYNC_CANCELLED = 'cancelled';

    /** Internal meeting rooms people can book. */
    public const ROOMS = [
        1 => 'Meeting Room 1',
        2 => 'Meeting Room 2',
        3 => 'Meeting Room 3',
        4 => 'Meeting Room 4',
    ];

    /** key => [label, emoji, bootstrap colour, hex, sort rank, Google Calendar colorId] */
    public const PRIORITIES = [
        'urgent' => ['Urgent', '🔴', 'danger', '#dc2626', 1, '11'],
        'immediate' => ['Immediate', '🟠', 'warning', '#f97316', 2, '6'],
        'normal' => ['Normal', '🟢', 'success', '#16a34a', 3, '10'],
    ];

    /** Statuses that occupy an internal room. */
    public const ROOM_BLOCKING_STATUSES = [self::STATUS_SCHEDULED, self::STATUS_LIVE];

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
        'provider',
        'priority',
        'room_slot',
        'scheduled_end_at',
        'google_request_id',
        'google_event_id',
        'google_calendar_id',
        'google_meet_url',
        'google_html_link',
        'google_sync_status',
        'google_sync_error',
        'google_sync_attempts',
        'google_synced_at',
        'google_conference_synced_at',
        'actual_started_at',
        'actual_ended_at',
        'time_taken_seconds',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'scheduled_at' => MeetingDateTime::class,
        'scheduled_end_at' => MeetingDateTime::class,
        'started_at' => MeetingDateTime::class,
        'ended_at' => MeetingDateTime::class,
        'minutes_started_at' => MeetingDateTime::class,
        'minutes_generated_at' => MeetingDateTime::class,
        'tasks_generated_at' => MeetingDateTime::class,
        'google_synced_at' => MeetingDateTime::class,
        'google_conference_synced_at' => MeetingDateTime::class,
        'actual_started_at' => MeetingDateTime::class,
        'actual_ended_at' => MeetingDateTime::class,
        'cancelled_at' => MeetingDateTime::class,
        'minutes' => 'array',
    ];

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

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

    public function externalRecordings(): HasMany
    {
        return $this->hasMany(MeetingExternalRecording::class)->orderBy('id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class)->orderBy('id');
    }

    public function mom(): HasOne
    {
        return $this->hasOne(MeetingMinute::class);
    }

    // ------------------------------------------------------------------
    // State helpers
    // ------------------------------------------------------------------

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isGoogleMeet(): bool
    {
        return $this->provider === self::PROVIDER_GOOGLE_MEET;
    }

    /** True only when Google really returned a Meet link for this meeting. */
    public function hasMeetLink(): bool
    {
        return $this->isGoogleMeet()
            && filled($this->google_meet_url)
            && str_starts_with((string) $this->google_meet_url, 'https://meet.google.com/');
    }

    public function roomLabel(): ?string
    {
        return $this->room_slot ? (self::ROOMS[(int) $this->room_slot] ?? 'Meeting Room '.$this->room_slot) : null;
    }

    /** @return array{label: string, emoji: string, tone: string, hex: string, rank: int} */
    public function priorityMeta(): array
    {
        return self::priorityInfo($this->priority);
    }

    /** @return array{label: string, emoji: string, tone: string, hex: string, rank: int} */
    public static function priorityInfo(?string $priority): array
    {
        $p = self::PRIORITIES[$priority ?? 'normal'] ?? self::PRIORITIES['normal'];

        return ['label' => $p[0], 'emoji' => $p[1], 'tone' => $p[2], 'hex' => $p[3], 'rank' => $p[4]];
    }

    // ------------------------------------------------------------------
    // Durations (scheduled and actual are deliberately separate)
    // ------------------------------------------------------------------

    /** Planned length (scheduled end - scheduled start), seconds. */
    public function scheduledDurationSeconds(): ?int
    {
        if (! $this->scheduled_at || ! $this->scheduled_end_at) {
            return null;
        }

        return max(0, $this->scheduled_at->diffInSeconds($this->scheduled_end_at));
    }

    /**
     * Time actually taken, seconds. Google Meet meetings use the saved actual start/end;
     * legacy in-app rooms keep using started_at/ended_at exactly as before.
     */
    public function actualDurationSeconds(): ?int
    {
        if ($this->time_taken_seconds !== null) {
            return (int) $this->time_taken_seconds;
        }
        if ($this->actual_started_at && $this->actual_ended_at) {
            return max(0, $this->actual_started_at->diffInSeconds($this->actual_ended_at));
        }
        if (! $this->isGoogleMeet()) {
            return $this->durationSeconds();
        }

        return null;
    }

    public function durationSeconds(): ?int
    {
        if (! $this->started_at) {
            return null;
        }
        $end = $this->ended_at ?? now();

        return max(0, $this->started_at->diffInSeconds($end));
    }

    /** "95 min (1h 35m)" */
    public static function humanDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        $minutes = (int) round($seconds / 60);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        $hours = $h > 0 ? $h.'h '.$m.'m' : $m.'m';

        return $minutes.' min ('.$hours.')';
    }

    // ------------------------------------------------------------------
    // Permissions (same rules as the original meeting room: host + task admins manage, participants view)
    // ------------------------------------------------------------------

    public function canBeManagedBy(?User $user): bool
    {
        return $user !== null
            && ((int) $this->host_user_id === (int) $user->id || \App\Support\SuperAdminAccess::isTaskAdmin($user));
    }

    public function canBeViewedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->canBeManagedBy($user)) {
            return true;
        }

        return $this->participants()->where('user_id', $user->id)->exists();
    }

    // ------------------------------------------------------------------
    // Scopes / query helpers
    // ------------------------------------------------------------------

    /** Meetings a person is allowed to see: everything for task admins, otherwise hosted or invited. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if (\App\Support\SuperAdminAccess::isTaskAdmin($user)) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('host_user_id', $user->id)
            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id)));
    }

    /** Meetings that still occupy a room. */
    public function scopeBlockingRoom(Builder $q): Builder
    {
        return $q->whereIn('status', self::ROOM_BLOCKING_STATUSES);
    }

    /** Database (app timezone) representation of an instant, as MeetingDateTime stores it. */
    public static function dbTime(\DateTimeInterface $t): string
    {
        return Carbon::instance($t)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    /**
     * Another meeting already holds this room during [start, end) ?
     * Strict inequality so back-to-back meetings (10:00-11:00 then 11:00-12:00) are allowed.
     */
    public static function roomConflict(int $room, \DateTimeInterface $start, \DateTimeInterface $end, ?int $ignoreId = null, bool $lock = false): ?self
    {
        $q = self::query()
            ->where('room_slot', $room)
            ->blockingRoom()
            ->whereNotNull('scheduled_at')
            ->whereNotNull('scheduled_end_at')
            ->where('scheduled_at', '<', self::dbTime($end))
            ->where('scheduled_end_at', '>', self::dbTime($start))
            ->when($ignoreId, fn ($w) => $w->where('id', '!=', $ignoreId))
            ->orderBy('scheduled_at');

        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->first();
    }
}
