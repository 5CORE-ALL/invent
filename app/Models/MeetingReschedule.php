<?php

namespace App\Models;

use App\Casts\MeetingDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to move a meeting to a new time (from an invited member), or the record of the host moving it.
 * status: pending (waiting for the host) | approved (the meeting was moved) | declined
 */
class MeetingReschedule extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';

    protected $fillable = [
        'meeting_id', 'requested_by', 'old_start', 'old_end', 'new_start', 'new_end',
        'reason', 'status', 'decided_by', 'decided_at',
    ];

    protected $casts = [
        'old_start' => MeetingDateTime::class,
        'old_end' => MeetingDateTime::class,
        'new_start' => MeetingDateTime::class,
        'new_end' => MeetingDateTime::class,
        'decided_at' => MeetingDateTime::class,
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
