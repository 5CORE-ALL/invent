<?php

namespace App\Console\Commands;

use App\Casts\MeetingDateTime;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Meetings\MeetingScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Creates the next meeting request of every repeating series (daily / weekly / fortnightly / monthly)
 * on the day it is due. Runs from the scheduler; safe to run repeatedly (never creates the same day twice).
 *
 * Rules:
 *  - the next date is counted from the FIRST meeting of the series (so "monthly on the 31st" does not drift);
 *  - an occurrence is generated on its own day, with the same time of day, length, title, agenda, priority and members;
 *  - missed days (server was off) are skipped, never back-filled;
 *  - cancelling the latest occurrence, or "Stop repeating", ends the series.
 */
class MeetingsGenerateRecurring extends Command
{
    protected $signature = 'meetings:generate-recurring';

    protected $description = 'Create the next meeting request of repeating meeting series';

    public function handle(MeetingScheduler $scheduler): int
    {
        if (! Meeting::supportsRecurrence()) {
            return self::SUCCESS;
        }

        $tz = MeetingDateTime::timezone();
        $today = now($tz)->startOfDay();
        $made = 0;

        $seriesIds = Meeting::query()->where('recurrence', '!=', 'once')->whereNotNull('series_id')->distinct()->pluck('series_id');
        foreach ($seriesIds as $seriesId) {
            try {
                $made += $this->generateFor((int) $seriesId, $scheduler, $today) ? 1 : 0;
            } catch (\Throwable $e) {
                Log::warning('Recurring meeting not generated', ['series_id' => $seriesId, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Generated {$made} recurring meeting request(s).");

        return self::SUCCESS;
    }

    private function generateFor(int $seriesId, MeetingScheduler $scheduler, \Carbon\Carbon $today): bool
    {
        /** @var Meeting|null $latest */
        $latest = Meeting::query()->where('series_id', $seriesId)->orderByDesc('scheduled_at')->orderByDesc('id')->first();
        if (! $latest || $latest->recurrence === 'once' || $latest->isCancelled() || ! $latest->scheduled_at) {
            return false; // stopped
        }
        $root = Meeting::query()->where('series_id', $seriesId)->orderBy('scheduled_at')->orderBy('id')->first();
        $next = $this->nextOccurrence($root->scheduled_at, $latest->scheduled_at, $latest->recurrence, $today);
        if (! $next || $next->copy()->startOfDay()->gt($today)) {
            return false; // not due yet
        }

        $host = $latest->host;
        if (! $host instanceof User) {
            return false;
        }
        $minutes = $latest->scheduled_end_at ? max(5, (int) $latest->scheduled_at->diffInMinutes($latest->scheduled_end_at)) : 30;
        $manualLink = $latest->isManualMeet() && $latest->google_meet_url ? $latest->google_meet_url : null;

        // A link made by Google for the previous occurrence must not be repeated in the agenda of a new, separate link.
        $agenda = $latest->agenda;
        if (! $manualLink && $agenda) {
            $agenda = trim(implode("\n", array_filter(explode("\n", $agenda), fn ($l) => ! str_starts_with($l, 'Join Google Meet: '))));
        }

        $data = [
            'title' => $latest->title,
            'agenda' => $agenda ?: null,
            'priority' => $latest->priority,
            'room_slot' => $latest->room_slot,
            'start' => $next,
            'end' => $next->copy()->addMinutes($minutes),
            'invitee_ids' => $latest->participants()->where('is_invited', true)->pluck('user_id')->all(),
            'manual_meet_url' => $manualLink,
            'recurrence' => $latest->recurrence,
            'series_id' => $seriesId,
        ];

        try {
            $scheduler->create($data, $host);
        } catch (ValidationException $e) {
            if (empty($data['room_slot'])) {
                throw $e;
            }
            $scheduler->create(['room_slot' => null] + $data, $host); // room taken that day: keep the meeting, drop the room
        }

        return true;
    }

    /** First occurrence date strictly after $latest and not before today, counted from the series' first meeting. */
    private function nextOccurrence(\Carbon\Carbon $root, \Carbon\Carbon $latest, string $recurrence, \Carbon\Carbon $today): ?\Carbon\Carbon
    {
        for ($i = 1; $i <= 2000; $i++) {
            $c = match ($recurrence) {
                'daily' => $root->copy()->addDays($i),
                'weekly' => $root->copy()->addDays(7 * $i),
                'fortnightly' => $root->copy()->addDays(14 * $i),
                'monthly' => $root->copy()->addMonthsNoOverflow($i),
                default => null,
            };
            if (! $c) {
                return null;
            }
            if ($c->copy()->startOfDay()->gt($latest->copy()->startOfDay()) && $c->copy()->startOfDay()->gte($today)) {
                return $c;
            }
        }

        return null;
    }
}
