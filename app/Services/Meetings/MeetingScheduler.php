<?php

namespace App\Services\Meetings;

use App\Casts\MeetingDateTime;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create / update / cancel Google Meet meetings booked into one of the four internal rooms.
 *
 * Order of operations (so a failure never leaves a fake meeting behind):
 *   1. When Google is connected the meeting becomes a real Google Meet. When it is NOT connected the meeting
 *      is still created, with an automatic in-app meeting-room link (provider = jitsi), so nobody is blocked
 *      waiting for a manager to connect the Google account. Connecting Google later switches new meetings
 *      back to Google Meet automatically.
 *   2. Room availability is checked and the meeting row is written in ONE database transaction, under a
 *      per-room lock so two people cannot grab the same slot at the same moment.
 *   3. Google Calendar is called after the commit. If Google fails, the meeting is kept with
 *      google_sync_status = failed and no Meet link; "Retry Google sync" reconciles it idempotently.
 */
class MeetingScheduler
{
    public function __construct(private GoogleMeetService $google)
    {
    }

    /**
     * Parse the date + start/end time typed by the user (company timezone) into instants.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function parseWindow(string $date, string $startTime, string $endTime, bool $allowPast = false): array
    {
        $tz = MeetingDateTime::timezone();
        try {
            $start = Carbon::parse($date.' '.$startTime, $tz);
            $end = Carbon::parse($date.' '.$endTime, $tz);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['start_time' => 'Enter a valid date and time.']);
        }

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['end_time' => 'The end time must be after the start time.']);
        }
        if ($start->diffInMinutes($end) > 12 * 60) {
            throw ValidationException::withMessages(['end_time' => 'A meeting can be at most 12 hours long.']);
        }
        if (! $allowPast && $start->lt(now($tz)->subMinutes(10))) {
            throw ValidationException::withMessages(['start_time' => 'The meeting cannot start in the past.']);
        }

        return [$start, $end];
    }

    /**
     * @param  array{title: string, agenda?: ?string, priority: string, room_slot: int, start: Carbon, end: Carbon, invitee_ids?: array<int>}  $data
     * @return array{meeting: Meeting, google_error: ?string}
     *
     * @throws ValidationException on room conflicts / unusable invitees
     */
    public function create(array $data, User $host): array
    {
        // Google connected -> real Google Meet. Not connected -> automatic in-app meeting-room link.
        $useGoogle = $this->google->isConfigured();

        $inviteeIds = $this->normaliseInvitees($data['invitee_ids'] ?? [], $host);
        if ($useGoogle) {
            $this->assertInviteesHaveEmail($inviteeIds, $host);
        }

        $meeting = $this->withRoomLock((int) $data['room_slot'], function () use ($data, $host, $inviteeIds, $useGoogle) {
            return DB::transaction(function () use ($data, $host, $inviteeIds, $useGoogle) {
                $this->assertRoomFree((int) $data['room_slot'], $data['start'], $data['end'], null);

                $meeting = Meeting::create([
                    'title' => trim($data['title']),
                    'agenda' => $data['agenda'] ?? null,
                    'room_name' => ($useGoogle ? 'gm-' : 'mr-').Str::lower(Str::random(24)),
                    'host_user_id' => $host->id,
                    'provider' => $useGoogle ? Meeting::PROVIDER_GOOGLE_MEET : Meeting::PROVIDER_JITSI,
                    'priority' => $data['priority'],
                    'room_slot' => (int) $data['room_slot'],
                    'scheduled_at' => $data['start'],
                    'scheduled_end_at' => $data['end'],
                    'status' => Meeting::STATUS_SCHEDULED,
                    'minutes_status' => Meeting::MINUTES_PENDING,
                ] + ($useGoogle ? [
                    'google_request_id' => bin2hex(random_bytes(16)),
                    'google_sync_status' => Meeting::SYNC_PENDING,
                ] : []));

                MeetingParticipant::create([
                    'meeting_id' => $meeting->id, 'user_id' => $host->id, 'is_invited' => false,
                    'rsvp' => 'accepted', 'rsvp_at' => now(),
                ]);
                foreach ($inviteeIds as $uid) {
                    MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $uid, 'is_invited' => true, 'rsvp' => 'pending']);
                }

                return $meeting;
            });
        });

        // In-app room meetings have nothing to sync: their link exists as soon as the meeting is saved.
        return ['meeting' => $meeting, 'google_error' => $useGoogle ? $this->trySync($meeting) : null];
    }

    /**
     * @param  array{title: string, agenda?: ?string, priority: string, room_slot: int, start: Carbon, end: Carbon, invitee_ids?: array<int>}  $data
     * @return array{meeting: Meeting, google_error: ?string}
     */
    public function update(Meeting $meeting, array $data, User $actor): array
    {
        if (! $meeting->isGoogleMeet()) {
            throw ValidationException::withMessages(['title' => 'Legacy meeting-room meetings cannot be edited.']);
        }
        if (! in_array($meeting->status, [Meeting::STATUS_SCHEDULED, Meeting::STATUS_LIVE], true)) {
            throw ValidationException::withMessages(['title' => 'Only scheduled or live meetings can be edited.']);
        }
        $this->google->assertConfigured();

        $host = $meeting->host ?? $actor;
        $inviteeIds = $this->normaliseInvitees($data['invitee_ids'] ?? [], $host);
        $this->assertInviteesHaveEmail($inviteeIds, $host);

        $meeting = $this->withRoomLock((int) $data['room_slot'], function () use ($meeting, $data, $inviteeIds) {
            return DB::transaction(function () use ($meeting, $data, $inviteeIds) {
                $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
                $this->assertRoomFree((int) $data['room_slot'], $data['start'], $data['end'], $meeting->id);

                $meeting->fill([
                    'title' => trim($data['title']),
                    'agenda' => $data['agenda'] ?? null,
                    'priority' => $data['priority'],
                    'room_slot' => (int) $data['room_slot'],
                    'scheduled_at' => $data['start'],
                    'scheduled_end_at' => $data['end'],
                ])->save();

                $keep = $inviteeIds;
                // Invitees dropped from the list go away unless they already joined (history is preserved).
                MeetingParticipant::query()
                    ->where('meeting_id', $meeting->id)
                    ->where('is_invited', true)
                    ->whereNotIn('user_id', $keep ?: [0])
                    ->whereNull('first_joined_at')
                    ->delete();
                foreach ($keep as $uid) {
                    MeetingParticipant::query()->firstOrCreate(
                        ['meeting_id' => $meeting->id, 'user_id' => $uid],
                        ['is_invited' => true, 'rsvp' => 'pending']
                    );
                }

                return $meeting;
            });
        });

        return ['meeting' => $meeting, 'google_error' => $this->trySync($meeting)];
    }

    /**
     * Retry the Google side of a meeting (create-or-update, idempotent).
     */
    public function retrySync(Meeting $meeting): ?string
    {
        if (! $meeting->isGoogleMeet() || $meeting->isCancelled()) {
            return null;
        }

        return $this->trySync($meeting);
    }

    /**
     * Cancel in Google Calendar (guests are notified) and free the internal room.
     *
     * @param  bool  $force  cancel locally even if Google cannot be reached (the Google event is left untouched)
     *
     * @throws GoogleMeetException
     */
    public function cancel(Meeting $meeting, User $actor, bool $force = false): ?string
    {
        if ($meeting->isCancelled()) {
            return null;
        }
        if ($meeting->isEnded()) {
            throw ValidationException::withMessages(['status' => 'An ended meeting cannot be cancelled.']);
        }

        $warning = null;
        if ($meeting->isGoogleMeet() && ($meeting->google_event_id || $meeting->google_request_id)) {
            try {
                $this->google->cancelMeeting($meeting);
            } catch (GoogleMeetException $e) {
                if (! $force) {
                    throw $e;
                }
                $warning = 'The meeting was cancelled here, but the Google Calendar event could not be removed ('.$e->getMessage().'). Delete it in Google Calendar by hand.';
                Log::warning('Meeting cancelled locally without removing Google event', ['meeting_id' => $meeting->id, 'reason' => $e->reason]);
            }
        }

        DB::transaction(function () use ($meeting, $actor) {
            $meeting->forceFill([
                'status' => Meeting::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ])->save();
        });

        return $warning;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /** Returns the user-facing Google error or null on success. */
    private function trySync(Meeting $meeting): ?string
    {
        try {
            $this->google->syncMeeting($meeting);

            return $meeting->google_sync_status === Meeting::SYNC_SYNCED ? null : $meeting->google_sync_error;
        } catch (GoogleMeetException $e) {
            Log::warning('Google Meet sync failed', ['meeting_id' => $meeting->id, 'reason' => $e->reason, 'retryable' => $e->retryable]);

            return $e->getMessage();
        }
    }

    /** @return list<int> */
    private function normaliseInvitees(array $ids, User $host): array
    {
        return User::query()->active()
            ->whereIn('id', array_unique(array_map('intval', $ids)))
            ->where('id', '!=', $host->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** Google can only invite people it can e-mail. */
    private function assertInviteesHaveEmail(array $userIds, User $host): void
    {
        $bad = User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email'])
            ->filter(fn (User $u) => ! filter_var(trim((string) $u->email), FILTER_VALIDATE_EMAIL))
            ->pluck('name');

        if ($bad->isNotEmpty()) {
            throw ValidationException::withMessages([
                'invitee_ids' => 'These people have no valid email address, so Google Calendar cannot invite them: '.$bad->implode(', ').'.',
            ]);
        }
        if (! filter_var(trim((string) $host->email), FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['title' => 'Your own account has no valid email address, so Google Calendar cannot invite you.']);
        }
    }

    private function assertRoomFree(int $room, Carbon $start, Carbon $end, ?int $ignoreId): void
    {
        $clash = Meeting::roomConflict($room, $start, $end, $ignoreId, lock: true);
        if ($clash) {
            $tz = MeetingDateTime::timezone();
            throw ValidationException::withMessages([
                'room_slot' => sprintf(
                    '%s is already booked from %s to %s ("%s"). Pick another room or time.',
                    Meeting::ROOMS[$room] ?? 'This room',
                    $clash->scheduled_at->copy()->setTimezone($tz)->format('h:i A'),
                    $clash->scheduled_end_at->copy()->setTimezone($tz)->format('h:i A'),
                    Str::limit((string) $clash->title, 40)
                ),
            ]);
        }
    }

    /**
     * Serialise bookings of the same room (the DB row lock alone cannot protect an empty time range).
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function withRoomLock(int $room, callable $fn)
    {
        try {
            return Cache::lock('meeting-room-booking-'.$room, 15)->block(8, $fn);
        } catch (LockTimeoutException $e) {
            throw ValidationException::withMessages(['room_slot' => 'Another booking for this room is being saved. Please try again in a few seconds.']);
        }
    }
}
