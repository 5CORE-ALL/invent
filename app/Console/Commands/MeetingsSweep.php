<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Services\Meetings\MeetingMinutesService;
use Illuminate\Console\Command;

/**
 * Housekeeping + AI minutes pipeline for meeting rooms. Runs every minute from the scheduler.
 *
 *  1. Participants whose browser stopped sending heartbeats are marked as left.
 *  2. Live meetings that have been empty for a couple of minutes are ended.
 *  3. Ended meetings with all recordings uploaded get transcribed -> minutes -> tasks.
 */
class MeetingsSweep extends Command
{
    protected $signature = 'meetings:sweep {--housekeeping : Only close stale participants/rooms} {--minutes : Only generate minutes for ended meetings}';

    protected $description = 'Close stale meeting participants/rooms and generate AI minutes + tasks for ended meetings';

    /** Empty live room is closed after this long. */
    private const EMPTY_ROOM_GRACE_SECONDS = 120;

    /** Wait this long after a meeting ends so browsers can flush their last recording chunks. */
    private const UPLOAD_GRACE_SECONDS = 60;

    /** Don't wait on a stuck participant forever. */
    private const MAX_WAIT_SECONDS = 600;

    public function handle(MeetingMinutesService $minutes): int
    {
        $both = ! $this->option('housekeeping') && ! $this->option('minutes');

        if ($both || $this->option('housekeeping')) {
            $this->housekeeping();
        }
        if ($both || $this->option('minutes')) {
            $this->generateMinutes($minutes);
        }

        return self::SUCCESS;
    }

    private function housekeeping(): void
    {
        $stale = now()->subSeconds(MeetingParticipant::STALE_AFTER_SECONDS);

        // 1. Stale participants -> left (left_at = the last time we actually heard from them).
        MeetingParticipant::query()
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $stale))
            ->update([
                'left_at' => \Illuminate\Support\Facades\DB::raw('COALESCE(last_seen_at, joined_at)'),
                'recording_state' => 'stopped',
            ]);

        // 2. Empty live rooms -> ended.
        Meeting::query()->where('status', Meeting::STATUS_LIVE)->get()->each(function (Meeting $m) {
            $inRoom = $m->participants()->whereNotNull('joined_at')->whereNull('left_at')->exists();
            if ($inRoom) {
                return;
            }
            $lastLeft = $m->participants()->max('left_at');
            if ($lastLeft && now()->diffInSeconds(\Carbon\Carbon::parse($lastLeft)) < self::EMPTY_ROOM_GRACE_SECONDS) {
                return;
            }
            $m->forceFill([
                'status' => Meeting::STATUS_ENDED,
                'ended_at' => $lastLeft ? \Carbon\Carbon::parse($lastLeft) : now(),
            ])->save();
            $this->line("Ended empty meeting #{$m->id}");
        });

        // A claim that died mid-way (worker killed) must not block the meeting forever.
        Meeting::query()
            ->where('minutes_status', Meeting::MINUTES_PROCESSING)
            ->where('minutes_started_at', '<', now()->subMinutes(45))
            ->update([
                'minutes_status' => Meeting::MINUTES_FAILED,
                'minutes_error' => 'Minutes generation timed out. Use "Regenerate minutes".',
            ]);

    }

    private function generateMinutes(MeetingMinutesService $minutes): void
    {
        // 3. Ended meetings -> minutes.
        $ready = Meeting::query()
            ->where('status', Meeting::STATUS_ENDED)
            ->where('minutes_status', Meeting::MINUTES_PENDING)
            ->where('ended_at', '<=', now()->subSeconds(self::UPLOAD_GRACE_SECONDS))
            ->orderBy('ended_at')
            ->get();

        foreach ($ready as $m) {
            // The host can end the meeting while others are still in the room finishing uploads.
            $stillUploading = $m->participants()->whereNotNull('joined_at')->whereNull('left_at')->exists();
            if ($stillUploading && $m->ended_at->gt(now()->subSeconds(self::MAX_WAIT_SECONDS))) {
                continue;
            }
            $this->line("Generating minutes for meeting #{$m->id}");
            $minutes->process($m);
        }
    }
}
