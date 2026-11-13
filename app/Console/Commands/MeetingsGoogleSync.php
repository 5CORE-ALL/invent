<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Services\Meetings\GoogleMeetException;
use App\Services\Meetings\GoogleMeetService;
use App\Services\Meetings\MeetingScheduler;
use Illuminate\Console\Command;

/**
 * Background upkeep for Google Meet meetings (runs every five minutes from the scheduler):
 *
 *  1. Meetings whose Google Calendar sync failed or is still waiting for the Meet link are retried
 *     (idempotently: the same Calendar event is updated, never duplicated). Gives up after MAX_SYNC_ATTEMPTS.
 *  2. Scheduled / live meetings that are long over are closed (status ended).
 *  3. Recently ended meetings are checked for real Google Meet recordings and the real call times,
 *     when the recording scopes are authorised.
 */
class MeetingsGoogleSync extends Command
{
    protected $signature = 'meetings:google-sync {--retry : Only retry failed syncs} {--close : Only close overdue meetings} {--conference : Only pull recordings / call times}';

    protected $description = 'Retry Google Calendar syncs, close overdue Google Meet meetings and pull Google recordings';

    private const MAX_SYNC_ATTEMPTS = 8;

    /** A meeting is closed this long after its scheduled end. */
    private const CLOSE_AFTER_MINUTES = 60;

    public function handle(GoogleMeetService $google, MeetingScheduler $scheduler): int
    {
        $all = ! $this->option('retry') && ! $this->option('close') && ! $this->option('conference');

        if ($all || $this->option('retry')) {
            $this->retryFailed($google, $scheduler);
        }
        if ($all || $this->option('close')) {
            $this->closeOverdue();
        }
        if ($all || $this->option('conference')) {
            $this->pullConference($google);
        }

        return self::SUCCESS;
    }

    private function retryFailed(GoogleMeetService $google, MeetingScheduler $scheduler): void
    {
        if (! $google->isConfigured()) {
            return;
        }

        Meeting::query()
            ->where('provider', Meeting::PROVIDER_GOOGLE_MEET)
            ->whereIn('status', [Meeting::STATUS_SCHEDULED, Meeting::STATUS_LIVE])
            ->whereIn('google_sync_status', [Meeting::SYNC_PENDING, Meeting::SYNC_FAILED])
            ->where('google_sync_attempts', '<', self::MAX_SYNC_ATTEMPTS)
            ->where('updated_at', '<=', now()->subMinutes(2))
            ->where('scheduled_end_at', '>', now())
            ->orderBy('id')->limit(20)->get()
            ->each(function (Meeting $m) use ($scheduler) {
                $error = $scheduler->retrySync($m);
                $this->line("Meeting #{$m->id}: ".($error ? 'still failing - '.$error : 'synced'));
            });
    }

    private function closeOverdue(): void
    {
        Meeting::query()
            ->where('provider', Meeting::PROVIDER_GOOGLE_MEET)
            ->whereIn('status', [Meeting::STATUS_SCHEDULED, Meeting::STATUS_LIVE])
            ->whereNotNull('scheduled_end_at')
            ->where('scheduled_end_at', '<', now()->subMinutes(self::CLOSE_AFTER_MINUTES))
            ->get()
            ->each(function (Meeting $m) {
                // Actual times are NOT invented: they stay empty until someone enters them or Google reports them.
                $m->forceFill(['status' => Meeting::STATUS_ENDED, 'ended_at' => $m->actual_ended_at ?? $m->scheduled_end_at])->save();
                $this->line("Closed overdue meeting #{$m->id}");
            });
    }

    private function pullConference(GoogleMeetService $google): void
    {
        if (! $google->isConfigured() || ! $google->status()['recordings']) {
            return;
        }

        Meeting::query()
            ->where('provider', Meeting::PROVIDER_GOOGLE_MEET)
            ->where('status', Meeting::STATUS_ENDED)
            ->whereNotNull('google_meet_url')
            ->where('ended_at', '>=', now()->subDays(3))
            ->where(fn ($q) => $q->whereNull('google_conference_synced_at')->orWhere('google_conference_synced_at', '<=', now()->subMinutes(30)))
            ->orderBy('id')->limit(20)->get()
            ->each(function (Meeting $m) use ($google) {
                try {
                    $r = $google->syncConference($m);
                    $this->line("Meeting #{$m->id}: {$r['recordings_added']} recording(s) added");
                } catch (GoogleMeetException $e) {
                    $this->line("Meeting #{$m->id}: ".$e->getMessage());
                    $m->forceFill(['google_conference_synced_at' => now()])->save(); // do not hammer Google
                }
            });
    }
}
