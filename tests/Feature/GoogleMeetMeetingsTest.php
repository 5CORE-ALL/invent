<?php

namespace Tests\Feature;

use App\Models\GoogleMeetConnection;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Services\Meetings\GoogleMeetException;
use App\Services\Meetings\GoogleMeetService;
use App\Services\Meetings\MeetingScheduler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * End-to-end tests of the Google Meet meeting workflow against a small in-process fake of the
 * Google OAuth + Calendar HTTP API (no network, no real Google account needed).
 *
 * The real GoogleMeetService code runs unchanged: only the HTTP transport is replaced.
 */
class GoogleMeetMeetingsTest extends TestCase
{
    private FakeGoogle $fake;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'services.meetings.google.client_id' => 'test-client',
            'services.meetings.google.client_secret' => 'test-secret',
            'services.meetings.google.retry_sleep_ms' => 0,
            'services.meetings.google.conference_poll_sleep_ms' => 0,
            'services.meetings.google.conference_poll_attempts' => 4,
            'services.meetings.google.max_attempts' => 3,
            'services.meetings.timezone' => 'Asia/Kolkata',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Cache::flush();
        $this->withoutMiddleware(\App\Http\Middleware\CloseDbConnections::class); // would purge the in-memory sqlite DB

        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('avatar')->nullable();
            $t->string('designation')->nullable();
            $t->string('role')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        // The real layout runs MySQL-only queries; use a stand-in with the same @yield sections.
        $this->app['view']->getFinder()->prependLocation(__DIR__.'/stubs/views');
        foreach (['2026_10_28_000000_create_meeting_tables', '2026_10_28_010000_add_rsvp_to_meeting_participants', '2026_11_14_040000_add_google_meet_features_to_meetings'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }

        $this->fake = new FakeGoogle();
        $this->app->singleton(GoogleMeetService::class, fn () => new FakeTransportGoogleMeetService($this->fake));
        $this->app->forgetInstance(MeetingScheduler::class);

        GoogleMeetConnection::create(['email' => 'organiser@example.com', 'refresh_token' => 'refresh-secret', 'scopes' => GoogleMeetService::SCOPE_CALENDAR_EVENTS]);
    }

    // ------------------------------------------------------------------ helpers

    private function user(string $name, array $extra = []): User
    {
        return User::create($extra + ['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'is_active' => true]);
    }

    private function scheduler(): MeetingScheduler
    {
        return $this->app->make(MeetingScheduler::class);
    }

    private function payload(int $room, string $start = '10:00', string $end = '11:00', array $invitees = [], array $extra = []): array
    {
        $date = now('Asia/Kolkata')->addDays(2)->format('Y-m-d');
        [$s, $e] = $this->scheduler()->parseWindow($date, $start, $end);

        return $extra + [
            'title' => 'Weekly review '.$room, 'agenda' => 'Budget, hiring', 'priority' => 'normal',
            'room_slot' => $room, 'start' => $s, 'end' => $e, 'invitee_ids' => $invitees,
        ];
    }

    private function book(User $host, int $room, string $start = '10:00', string $end = '11:00', array $invitees = [], array $extra = []): Meeting
    {
        return $this->scheduler()->create($this->payload($room, $start, $end, $invitees, $extra), $host)['meeting'];
    }

    // ------------------------------------------------------------------ 1/2/3: four rooms -> real Meet links

    public function test_each_of_the_four_rooms_creates_a_real_google_meet_link_and_join_button(): void
    {
        $host = $this->user('Host Person');
        $guest = $this->user('Guest One');

        foreach ([1, 2, 3, 4] as $room) {
            $result = $this->scheduler()->create($this->payload($room, '10:00', '11:00', [$guest->id]), $host);
            $m = $result['meeting']->fresh();

            $this->assertNull($result['google_error']);
            $this->assertSame($room, (int) $m->room_slot);
            $this->assertSame('Meeting Room '.$room, $m->roomLabel());
            $this->assertSame('synced', $m->google_sync_status);
            $this->assertNotEmpty($m->google_event_id);
            $this->assertMatchesRegularExpression('~^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}$~', $m->google_meet_url);
            $this->assertTrue($m->hasMeetLink());

            $html = view('meetings._join', ['m' => $m])->render();
            $this->assertStringContainsString('Join Google Meet', $html);
            $this->assertStringContainsString('href="'.$m->google_meet_url.'"', $html);
            $this->assertStringContainsString('target="_blank"', $html);
        }

        $this->assertSame(4, $this->fake->eventCount());
        $this->assertCount(4, array_unique(array_map(fn ($e) => $e['_link'], $this->fake->events)), 'every room gets its own Meet link');
        foreach ($this->fake->inserts as $ins) {
            $this->assertSame('1', $ins['query']['conferenceDataVersion'], 'conferenceDataVersion=1 is mandatory');
            $this->assertSame('hangoutsMeet', $ins['body']['conferenceData']['createRequest']['conferenceSolutionKey']['type']);
            $this->assertNotEmpty($ins['body']['conferenceData']['createRequest']['requestId']);
            $this->assertSame('all', $ins['query']['sendUpdates'], 'Google must send the invitations');
        }
    }

    public function test_a_fake_or_foreign_link_is_never_accepted(): void
    {
        $this->fake->forcedHangoutLink = 'https://evil.example.com/not-meet';
        $host = $this->user('Host Person');
        $result = $this->scheduler()->create($this->payload(1), $host);
        $m = $result['meeting']->fresh();

        $this->assertNull($m->google_meet_url);
        $this->assertFalse($m->hasMeetLink());
        $this->assertNotNull($result['google_error']);
        $this->assertStringNotContainsString('Join Google Meet', view('meetings._join', ['m' => $m])->render());
    }

    public function test_asynchronous_conference_generation_is_polled_until_google_returns_the_link(): void
    {
        $this->fake->pendingPolls = 2; // createRequest.status = pending for the first two reads
        $host = $this->user('Host Person');
        $m = $this->book($host, 3)->fresh();

        $this->assertTrue($m->hasMeetLink());
        $this->assertSame('synced', $m->google_sync_status);
        $this->assertSame(1, $this->fake->eventCount());
        $this->assertGreaterThanOrEqual(2, $this->fake->gets);
    }

    public function test_conference_that_stays_pending_is_not_faked_and_is_completed_by_a_retry_without_duplicates(): void
    {
        $this->fake->pendingPolls = 99;
        $host = $this->user('Host Person');
        $result = $this->scheduler()->create($this->payload(2), $host);
        $m = $result['meeting']->fresh();

        $this->assertNull($m->google_meet_url);
        $this->assertSame('pending', $m->google_sync_status);
        $this->assertNotEmpty($m->google_event_id, 'event id is saved even while the link is pending');

        $this->fake->pendingPolls = 0;
        $this->assertNull($this->scheduler()->retrySync($m));
        $m->refresh();
        $this->assertTrue($m->hasMeetLink());
        $this->assertSame(1, $this->fake->eventCount(), 'retry must not create a second calendar event');
        $this->assertCount(1, $this->fake->inserts);
    }

    // ------------------------------------------------------------------ 4/5: participants

    public function test_multiple_participants_become_calendar_attendees_and_are_saved(): void
    {
        $host = $this->user('Host Person');
        $guests = collect(['Alice A', 'Bob B', 'Carol C', 'Dan D'])->map(fn ($n) => $this->user($n));

        $m = $this->book($host, 1, '10:00', '11:00', $guests->pluck('id')->all())->fresh();

        $emails = collect($this->fake->inserts[0]['body']['attendees'])->pluck('email')->all();
        $this->assertCount(5, $emails);
        $this->assertContains('host.person@example.com', $emails);
        foreach ($guests as $g) {
            $this->assertContains($g->email, $emails);
        }
        $this->assertSame(5, $m->participants()->count());
        $this->assertSame(4, $m->participants()->where('is_invited', true)->count());
        $this->assertSame('Weekly review 1', $this->fake->inserts[0]['body']['summary']);
        $this->assertSame('Meeting Room 1', $this->fake->inserts[0]['body']['location']);
    }

    public function test_people_without_an_email_are_refused_before_anything_is_saved(): void
    {
        $host = $this->user('Host Person');
        $noMail = User::create(['name' => 'No Mail', 'email' => null, 'is_active' => true]);

        try {
            $this->scheduler()->create($this->payload(1, invitees: [$noMail->id]), $host);
            $this->fail('expected validation error');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('No Mail', $e->errors()['invitee_ids'][0]);
        }
        $this->assertSame(0, Meeting::count());
        $this->assertSame(0, $this->fake->eventCount());
    }

    // ------------------------------------------------------------------ 6: conflicts

    public function test_room_conflicts_are_blocked_but_back_to_back_and_other_rooms_are_allowed(): void
    {
        $host = $this->user('Host Person');
        $first = $this->book($host, 1, '10:00', '11:00');

        foreach ([['10:30', '11:30'], ['09:00', '10:01'], ['10:00', '11:00'], ['09:00', '12:00'], ['10:15', '10:45']] as [$s, $e]) {
            try {
                $this->book($host, 1, $s, $e);
                $this->fail("overlap $s-$e should be rejected");
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('room_slot', $ex->errors());
                $this->assertStringContainsString('Meeting Room 1 is already booked', $ex->errors()['room_slot'][0]);
            }
        }
        $this->assertSame(1, Meeting::count());
        $this->assertSame(1, $this->fake->eventCount(), 'a rejected booking must not touch Google');

        $this->book($host, 1, '11:00', '12:00');   // back to back
        $this->book($host, 1, '09:00', '10:00');   // ends exactly when the first starts
        $this->book($host, 2, '10:00', '11:00');   // other room, same time
        $this->assertSame(4, Meeting::count());

        // cancelling frees the room
        $this->scheduler()->cancel($first->fresh(), $host);
        $this->book($host, 1, '10:00', '11:00');
        $this->assertSame(5, Meeting::count());
    }

    public function test_editing_into_a_taken_slot_is_refused_and_editing_in_place_is_allowed(): void
    {
        $host = $this->user('Host Person');
        $a = $this->book($host, 1, '10:00', '11:00');
        $b = $this->book($host, 1, '12:00', '13:00');

        $clash = $this->payload(1, '10:30', '11:30');
        try {
            $this->scheduler()->update($b->fresh(), $clash, $host);
            $this->fail('should conflict with meeting A');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('room_slot', $e->errors());
        }

        $same = $this->payload(1, '10:00', '11:30'); // extend A itself: no self-conflict
        $r = $this->scheduler()->update($a->fresh(), $same, $host);
        $this->assertNull($r['google_error']);
    }

    // ------------------------------------------------------------------ 7/8: update + cancel + sync

    public function test_update_patches_the_same_calendar_event_and_never_creates_a_duplicate(): void
    {
        $host = $this->user('Host Person');
        $g1 = $this->user('Guest One');
        $g2 = $this->user('Guest Two');
        $m = $this->book($host, 2, '10:00', '11:00', [$g1->id])->fresh();
        $eventId = $m->google_event_id;
        $link = $m->google_meet_url;

        $r = $this->scheduler()->update($m, $this->payload(2, '14:00', '15:30', [$g1->id, $g2->id], ['title' => 'Renamed', 'priority' => 'urgent']), $host);
        $m = $r['meeting']->fresh();

        $this->assertNull($r['google_error']);
        $this->assertSame($eventId, $m->google_event_id);
        $this->assertSame($link, $m->google_meet_url, 'the Meet link survives an edit');
        $this->assertSame(1, $this->fake->eventCount());
        $this->assertCount(1, $this->fake->inserts);
        $this->assertCount(1, $this->fake->patches);
        $patch = $this->fake->patches[0];
        $this->assertSame('Renamed', $patch['body']['summary']);
        $this->assertCount(3, $patch['body']['attendees']);
        $this->assertSame('11', $patch['body']['colorId'], 'urgent = red in Google Calendar');
        $this->assertArrayNotHasKey('conferenceData', $patch['body'], 'an edit must not ask for a second conference');
        $this->assertStringContainsString('T14:00:00', $this->fake->events[$eventId]['start']['dateTime']);
        $this->assertSame('urgent', $m->priority);
        $this->assertSame(3, $m->participants()->where('is_invited', true)->count() + 1);
    }

    public function test_cancel_deletes_the_google_event_and_frees_the_room(): void
    {
        $host = $this->user('Host Person');
        $m = $this->book($host, 4)->fresh();
        $eventId = $m->google_event_id;

        $this->assertNull($this->scheduler()->cancel($m, $host));
        $m->refresh();

        $this->assertSame('cancelled', $m->status);
        $this->assertSame('cancelled', $m->google_sync_status);
        $this->assertNotNull($m->cancelled_at);
        $this->assertSame(0, $this->fake->eventCount());
        $this->assertSame('all', $this->fake->deletes[0]['query']['sendUpdates']);
        $this->assertSame($eventId, $this->fake->deletes[0]['id']);
        $this->assertStringNotContainsString('Join Google Meet', view('meetings._join', ['m' => $m])->render());
    }

    public function test_cancel_is_refused_when_google_is_down_unless_forced(): void
    {
        $host = $this->user('Host Person');
        $m = $this->book($host, 1)->fresh();

        $this->fake->failNext(3, 500, 'DELETE');
        try {
            $this->scheduler()->cancel($m, $host);
            $this->fail('expected GoogleMeetException');
        } catch (GoogleMeetException $e) {
            $this->assertTrue($e->retryable);
        }
        $this->assertSame('scheduled', $m->fresh()->status, 'not cancelled locally while the Google event is still alive');

        $this->fake->failNext(3, 500, 'DELETE');
        $warning = $this->scheduler()->cancel($m->fresh(), $host, force: true);
        $this->assertNotNull($warning);
        $this->assertSame('cancelled', $m->fresh()->status);
    }

    // ------------------------------------------------------------------ 15: failures, retries, duplicates, config

    public function test_not_configured_shows_a_clear_message_and_creates_nothing(): void
    {
        GoogleMeetConnection::query()->delete();
        config(['services.meetings.google.client_id' => null, 'services.meetings.google.client_secret' => null]);
        Cache::flush();
        $host = $this->user('Host Person');

        try {
            $this->scheduler()->create($this->payload(1), $host);
            $this->fail('expected GoogleMeetException');
        } catch (GoogleMeetException $e) {
            $this->assertSame('not_configured', $e->reason);
            $this->assertStringContainsString('GOOGLE_CLIENT_ID', $e->getMessage() . implode(' ', app(GoogleMeetService::class)->status()['missing']));
        }
        $this->assertSame(0, Meeting::count(), 'no fake meeting is created');
        $this->assertSame(0, $this->fake->requestCount());
    }

    public function test_transient_google_errors_are_retried_with_backoff(): void
    {
        $this->fake->failNext(2, 503, 'POST', '/events');
        $host = $this->user('Host Person');
        $result = $this->scheduler()->create($this->payload(1), $host);

        $this->assertNull($result['google_error']);
        $this->assertTrue($result['meeting']->fresh()->hasMeetLink());
        $this->assertSame(1, $this->fake->eventCount());
    }

    public function test_failed_create_keeps_the_booking_without_a_link_and_retry_reuses_the_same_event_id(): void
    {
        $this->fake->failNext(3, 500, 'POST', '/events'); // exhaust the 3 attempts
        $host = $this->user('Host Person');
        $result = $this->scheduler()->create($this->payload(2), $host);
        $m = $result['meeting']->fresh();

        $this->assertNotNull($result['google_error']);
        $this->assertSame('failed', $m->google_sync_status);
        $this->assertNull($m->google_meet_url);
        $this->assertNull($m->google_event_id);
        $this->assertSame(0, $this->fake->eventCount());

        $this->assertNull($this->scheduler()->retrySync($m));
        $m->refresh();
        $this->assertTrue($m->hasMeetLink());
        $this->assertSame(1, $this->fake->eventCount());
        // the ids sent on the failed attempts and on the retry are identical => Google could never hold two events
        $ids = array_unique(array_map(fn ($i) => $i['body']['id'], $this->fake->insertAttempts));
        $this->assertCount(1, $ids);
    }

    public function test_lost_response_after_a_successful_insert_adopts_the_existing_event_on_409(): void
    {
        $host = $this->user('Host Person');
        $this->fake->loseNextInsertResponse = true; // Google created it, but we saw a network error
        $result = $this->scheduler()->create($this->payload(1), $host);
        $m = $result['meeting']->fresh();

        $this->assertSame(1, $this->fake->eventCount());
        $this->assertCount(2, $this->fake->insertAttempts, 'second attempt hit 409 (the event already existed)');
        $this->assertCount(1, $this->fake->patches, 'the existing event was adopted and updated instead of duplicated');
        // either the retry inside the same call hit 409 and adopted the event, or a later retry does
        if (! $m->hasMeetLink()) {
            $this->assertNull($this->scheduler()->retrySync($m));
            $m->refresh();
        }
        $this->assertTrue($m->hasMeetLink());
        $this->assertSame(1, $this->fake->eventCount(), 'no duplicate event');
    }

    public function test_repeated_retries_never_create_duplicates(): void
    {
        $host = $this->user('Host Person');
        $m = $this->book($host, 1)->fresh();
        for ($i = 0; $i < 3; $i++) {
            $this->assertNull($this->scheduler()->retrySync($m->fresh()));
        }
        $this->assertSame(1, $this->fake->eventCount());
        $this->assertCount(1, $this->fake->inserts);
    }

    public function test_event_deleted_in_google_is_reported_not_resurrected(): void
    {
        $host = $this->user('Host Person');
        $m = $this->book($host, 1)->fresh();
        $this->fake->events = []; // someone deleted it in Google Calendar

        $error = $this->scheduler()->retrySync($m);
        $this->assertStringContainsString('deleted in Google Calendar', $error);
        $this->assertSame(0, $this->fake->eventCount());
    }

    public function test_revoked_authorisation_gives_a_reconnect_message(): void
    {
        $this->fake->tokenError = 'invalid_grant';
        $host = $this->user('Host Person');
        $result = $this->scheduler()->create($this->payload(1), $host);

        $this->assertStringContainsString('reconnect', strtolower((string) $result['google_error']));
        $this->assertSame('failed', $result['meeting']->fresh()->google_sync_status);
    }

    public function test_oauth_tokens_are_encrypted_at_rest_and_hidden(): void
    {
        $raw = DB::table('google_meet_connections')->value('refresh_token');
        $this->assertStringNotContainsString('refresh-secret', $raw);
        $this->assertSame('refresh-secret', GoogleMeetConnection::first()->refresh_token);
        $this->assertArrayNotHasKey('refresh_token', GoogleMeetConnection::first()->toArray());
    }

    // ------------------------------------------------------------------ 9: duration

    public function test_time_taken_is_calculated_and_kept_separate_from_the_schedule(): void
    {
        $host = $this->user('Host Person');
        $m = $this->book($host, 1, '10:00', '11:00')->fresh();
        $this->assertSame(3600, $m->scheduledDurationSeconds());
        $this->assertNull($m->actualDurationSeconds());

        $date = $m->scheduled_at->format('Y-m-d');
        $this->actingAs($host)->post(route('meetings.time', $m), [
            'action' => 'manual', 'actual_date' => '2026-01-05', 'actual_start' => '10:05', 'actual_end' => '11:40',
        ])->assertRedirect();

        $m->refresh();
        $this->assertSame(5700, $m->time_taken_seconds);
        $this->assertSame(5700, $m->actualDurationSeconds());
        $this->assertSame(3600, $m->scheduledDurationSeconds(), 'scheduled length is untouched');
        $this->assertSame('ended', $m->status);
        $this->assertSame('95 min (1h 35m)', Meeting::humanDuration(5700));
        $this->assertSame('45 min (45m)', Meeting::humanDuration(2700));
        $this->assertNotNull($date);
    }

    public function test_actual_end_before_start_is_rejected(): void
    {
        $host = $this->user('Host Person');
        $m = $this->book($host, 1)->fresh();
        $this->actingAs($host)->post(route('meetings.time', $m), [
            'action' => 'manual', 'actual_date' => '2026-01-05', 'actual_start' => '11:00', 'actual_end' => '10:00',
        ])->assertSessionHasErrors('actual_end');
        $this->assertNull($m->fresh()->time_taken_seconds);
    }

    // ------------------------------------------------------------------ 10: recordings

    public function test_recording_link_and_upload_are_saved_shown_and_protected(): void
    {
        Storage::fake('local');
        config(['services.meetings.disk' => 'local']);
        $host = $this->user('Host Person');
        $guest = $this->user('Guest One');
        $outsider = $this->user('Outsider');
        $m = $this->book($host, 1, invitees: [$guest->id])->fresh();

        $this->actingAs($host)->post(route('meetings.recordings.add', $m), ['type' => 'link', 'url' => 'https://drive.google.com/file/d/abc/view', 'title' => 'Drive copy'])->assertRedirect();
        $this->actingAs($host)->post(route('meetings.recordings.add', $m), ['type' => 'link', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->actingAs($host)->post(route('meetings.recordings.add', $m), ['type' => 'upload', 'file' => UploadedFile::fake()->create('call.mp4', 2048, 'video/mp4')])->assertRedirect();
        $this->actingAs($host)->post(route('meetings.recordings.add', $m), ['type' => 'upload', 'file' => UploadedFile::fake()->create('virus.php', 10, 'text/x-php')])->assertSessionHasErrors('file');

        $m->refresh();
        $this->assertSame(2, $m->externalRecordings()->count());
        $upload = $m->externalRecordings()->where('source', 'upload')->first();
        Storage::disk('local')->assertExists($upload->path);

        // participants can open it, strangers cannot, non-managers cannot add
        $this->actingAs($guest)->get(route('meetings.recordings.download', [$m, $upload]))->assertOk();
        $this->actingAs($outsider)->get(route('meetings.recordings.download', [$m, $upload]))->assertForbidden();
        $this->actingAs($guest)->post(route('meetings.recordings.add', $m), ['type' => 'link', 'url' => 'https://example.com/x'])->assertForbidden();

        $this->actingAs($host)->delete(route('meetings.recordings.delete', [$m, $upload]))->assertRedirect();
        Storage::disk('local')->assertMissing($upload->path);
        $this->assertSame(1, $m->externalRecordings()->count());
    }

    public function test_google_recordings_are_only_stored_once_the_file_really_exists(): void
    {
        config(['services.meetings.google.recording_scopes' => true]);
        GoogleMeetConnection::first()->update(['scopes' => GoogleMeetService::SCOPE_CALENDAR_EVENTS.' '.GoogleMeetService::SCOPE_MEET_READONLY]);
        $host = $this->user('Host Person');
        $m = $this->book($host, 1)->fresh();
        $code = basename((string) $m->google_meet_url);

        Http::fake([
            'meet.googleapis.com/v2/conferenceRecords?*' => Http::response(['conferenceRecords' => [[
                'name' => 'conferenceRecords/c1', 'startTime' => '2026-01-05T04:35:00Z', 'endTime' => '2026-01-05T06:10:00Z',
            ]]]),
            'meet.googleapis.com/v2/conferenceRecords/c1/recordings' => Http::response(['recordings' => [
                ['name' => 'conferenceRecords/c1/recordings/r1', 'state' => 'FILE_GENERATED', 'driveDestination' => ['exportUri' => 'https://drive.google.com/file/d/ZZZ/view']],
                ['name' => 'conferenceRecords/c1/recordings/r2', 'state' => 'STARTED'],
                ['name' => 'conferenceRecords/c1/recordings/r3', 'state' => 'ENDED'],
            ]]),
        ]);

        $r = app(GoogleMeetService::class)->syncConference($m);
        $this->assertSame(1, $r['recordings_added']);
        $this->assertSame(2, $r['recordings_pending']);
        $this->assertSame(1, $m->externalRecordings()->count());
        $this->assertSame('https://drive.google.com/file/d/ZZZ/view', $m->externalRecordings()->first()->url);
        Http::assertSent(fn ($req) => str_contains(urldecode($req->url()), 'space.meeting_code="'.$code.'"'));

        // real call times from Google fill the time taken (95 min), idempotently
        $m->refresh();
        $this->assertSame(5700, $m->time_taken_seconds);
        app(GoogleMeetService::class)->syncConference($m);
        $this->assertSame(1, $m->externalRecordings()->count(), 'sync is idempotent');
    }

    public function test_google_recording_import_without_the_scope_explains_what_is_missing(): void
    {
        $host = $this->user('Host Person');
        $m = $this->book($host, 1)->fresh();
        try {
            app(GoogleMeetService::class)->syncConference($m);
            $this->fail('expected scope error');
        } catch (GoogleMeetException $e) {
            $this->assertSame('scope', $e->reason);
            $this->assertStringContainsString('meetings.space.readonly', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ 11/12: MOM + tasks

    public function test_minutes_of_meeting_and_multi_employee_tasks_with_permissions(): void
    {
        $host = $this->user('Host Person');
        $alice = $this->user('Alice A');
        $bob = $this->user('Bob B');
        $outsider = $this->user('Outsider');
        $m = $this->book($host, 1, invitees: [$alice->id, $bob->id])->fresh();

        $long = str_repeat("Long discussion line with détail ✓\n", 3000);
        $this->actingAs($host)->put(route('meetings.mom.save', $m), [
            'discussion' => $long, 'complete_minutes' => $long, 'summary' => 'Short summary', 'decisions' => 'Hire two', 'issues' => 'Budget gap',
        ])->assertRedirect();
        $this->actingAs($host)->put(route('meetings.mom.save', $m), ['summary' => 'Updated summary'])->assertRedirect();
        $this->assertSame(1, DB::table('meeting_minutes')->count(), 'MOM is updated in place, one row per meeting');
        $mom = $m->fresh()->mom;
        $this->assertSame('Updated summary', $mom->summary);

        $this->actingAs($alice)->put(route('meetings.mom.save', $m), ['summary' => 'hack'])->assertForbidden();

        foreach ([[$alice, 'Prepare budget'], [$bob, 'Draft job post']] as [$u, $title]) {
            $this->actingAs($host)->post(route('meetings.tasks.add', $m), [
                'title' => $title, 'assignee_user_id' => $u->id, 'due_date' => '2026-12-01', 'status' => 'pending',
            ])->assertRedirect();
        }
        $this->assertSame(2, $m->actionItems()->count());
        $this->actingAs($host)->post(route('meetings.tasks.add', $m), ['title' => 'x', 'status' => 'pending'])->assertSessionHasErrors('assignee_user_id');

        $item = $m->actionItems()->where('assignee_user_id', $alice->id)->first();
        $other = $m->actionItems()->where('assignee_user_id', $bob->id)->first();

        // the assignee may move their own task through the statuses, nothing else
        foreach (['in_progress', 'completed'] as $st) {
            $this->actingAs($alice)->put(route('meetings.tasks.update', [$m, $item]), ['status' => $st])->assertRedirect();
        }
        $item->refresh();
        $this->assertSame('completed', $item->status);
        $this->assertNotNull($item->completed_at);
        $this->actingAs($alice)->put(route('meetings.tasks.update', [$m, $other]), ['status' => 'completed'])->assertForbidden();
        $this->actingAs($alice)->put(route('meetings.tasks.update', [$m, $item]), ['status' => 'bogus'])->assertSessionHasErrors('status');
        $this->actingAs($outsider)->put(route('meetings.tasks.update', [$m, $item]), ['status' => 'pending'])->assertForbidden();
        $this->actingAs($alice)->delete(route('meetings.tasks.delete', [$m, $item]))->assertForbidden();

        $this->actingAs($host)->put(route('meetings.tasks.update', [$m, $other]), [
            'title' => 'Draft job post v2', 'assignee_user_id' => $alice->id, 'status' => 'in_progress', 'due_date' => '2026-12-05',
        ])->assertRedirect();
        $this->assertSame($alice->id, (int) $other->fresh()->assignee_user_id);
    }

    // ------------------------------------------------------------------ 13/14: avatars + president summary

    public function test_president_summary_shows_everything_untruncated_with_avatars_and_fallbacks(): void
    {
        $host = $this->user('Host Person', ['avatar' => 'avatars/host.jpg']);
        $alice = $this->user('Alice A');
        $m = $this->book($host, 3, invitees: [$alice->id], extra: ['priority' => 'urgent'])->fresh();
        $long = str_repeat('Very long decision text. ', 400);
        $m->mom()->create(['summary' => $long, 'decisions' => "Decision A\nDecision B", 'issues' => 'Supplier delay']);
        $m->actionItems()->create(['title' => 'Prepare budget', 'assignee_user_id' => $alice->id, 'status' => 'pending', 'due_date' => '2026-12-01']);
        $m->actionItems()->create(['title' => 'Ship samples', 'assignee_user_id' => $host->id, 'status' => 'completed']);
        $m->update(['time_taken_seconds' => 4500]);

        $html = view('meetings._summary', ['meeting' => $m->fresh(['host', 'participants.user', 'actionItems.assignee', 'mom', 'externalRecordings'])])->render();

        $this->assertStringContainsString('Weekly review 3', $html);
        $this->assertStringContainsString('Urgent', $html);
        $this->assertStringContainsString('Meeting Room 3', $html);
        $this->assertStringContainsString($m->google_meet_url, $html);
        $this->assertStringContainsString('75 min (1h 15m)', $html);
        $this->assertStringContainsString(trim($long), html_entity_decode($html), 'long minutes are not cut off');
        $this->assertStringContainsString('Decision A', $html);
        $this->assertStringContainsString('Supplier delay', $html);
        $this->assertStringContainsString('Prepare budget', $html);
        $this->assertStringContainsString('1 <small class="text-success">completed</small> &middot; 1 <small class="text-warning">pending</small>', $html);
        $this->assertStringContainsString('storage/avatars/host.jpg', $html, 'existing profile photo is reused');
        $this->assertMatchesRegularExpression('~<span class="mt-av"[^>]*title="Alice A">AA</span>~', $html, 'no photo -> initials avatar');
        $this->assertStringContainsString('onerror="this.remove()"', $html, 'broken photo falls back to initials');
    }

    // ------------------------------------------------------------------ 1: priority + oldest on top

    public function test_requests_are_listed_oldest_first_and_carry_priority_room_and_join_link(): void
    {
        $host = $this->user('Host Person');
        $me = $this->user('Me Invitee');

        $new = $this->book($host, 1, '10:00', '11:00', [$me->id], ['title' => 'Newest urgent', 'priority' => 'urgent'])->fresh();
        $old = $this->book($host, 2, '10:00', '11:00', [$me->id], ['title' => 'Oldest normal', 'priority' => 'normal'])->fresh();
        $mid = $this->book($host, 3, '10:00', '11:00', [$me->id], ['title' => 'Middle immediate', 'priority' => 'immediate'])->fresh();
        DB::table('meetings')->where('id', $old->id)->update(['created_at' => now()->subDays(5)]);
        DB::table('meetings')->where('id', $mid->id)->update(['created_at' => now()->subDays(2)]);
        DB::table('meetings')->where('id', $new->id)->update(['created_at' => now()->subHour()]);

        $json = $this->actingAs($me)->getJson(route('meetings.requests'))->assertOk()->json();
        $this->assertSame(['Oldest normal', 'Middle immediate', 'Newest urgent'], array_column($json['requests'], 'title'));
        $first = $json['requests'][0];
        $this->assertSame('Normal', $first['priority']['label']);
        $this->assertSame('Meeting Room 2', $first['room']);
        $this->assertStringStartsWith('https://meet.google.com/', $first['meet_url']);

        $sent = $this->actingAs($host)->getJson(route('meetings.sent'))->assertOk()->json();
        $this->assertSame(['Oldest normal', 'Middle immediate', 'Newest urgent'], array_column($sent['meetings'], 'title'));
        $this->assertSame('Urgent', $sent['meetings'][2]['priority']['label']);
    }

    public function test_priority_labels_and_ranks(): void
    {
        $this->assertSame(['Urgent', '🔴'], [Meeting::priorityInfo('urgent')['label'], Meeting::priorityInfo('urgent')['emoji']]);
        $this->assertSame(['Immediate', '🟠'], [Meeting::priorityInfo('immediate')['label'], Meeting::priorityInfo('immediate')['emoji']]);
        $this->assertSame(['Normal', '🟢'], [Meeting::priorityInfo('normal')['label'], Meeting::priorityInfo('normal')['emoji']]);
        $this->assertSame('Normal', Meeting::priorityInfo('garbage')['label']);
    }

    // ------------------------------------------------------------------ HTTP layer

    public function test_widget_endpoint_books_a_room_and_returns_the_join_link_or_a_config_error(): void
    {
        $host = $this->user('Host Person');
        $guest = $this->user('Guest One');
        $date = now('Asia/Kolkata')->addDay()->format('Y-m-d');
        $body = ['title' => 'Quick sync', 'agenda' => 'Agenda', 'priority' => 'immediate', 'room_slot' => 4,
            'meeting_date' => $date, 'start_time' => '16:00', 'end_time' => '16:30', 'invitee_ids' => [$guest->id]];

        $res = $this->actingAs($host)->postJson(route('meetings.request'), $body)->assertOk()->assertJsonPath('ok', true);
        $this->assertStringStartsWith('https://meet.google.com/', $res->json('meeting.meet_url'));
        $this->assertSame('Meeting Room 4', $res->json('meeting.room'));

        // same slot again -> 422 conflict
        $this->actingAs($host)->postJson(route('meetings.request'), $body)->assertStatus(422)->assertJsonValidationErrors('room_slot');
        // bad inputs
        $this->actingAs($host)->postJson(route('meetings.request'), array_merge($body, ['room_slot' => 5]))->assertStatus(422);
        $this->actingAs($host)->postJson(route('meetings.request'), array_merge($body, ['priority' => 'whenever']))->assertStatus(422);
        $this->actingAs($host)->postJson(route('meetings.request'), array_merge($body, ['room_slot' => 1, 'end_time' => '15:00']))->assertStatus(422);

        // availability hint
        $this->actingAs($host)->getJson(route('meetings.availability', ['room_slot' => 4, 'meeting_date' => $date]))
            ->assertOk()->assertJsonCount(1, 'busy')->assertJsonPath('busy.0.from', '04:00 PM');

        // Google disconnected -> configuration message, nothing saved
        GoogleMeetConnection::query()->delete();
        config(['services.meetings.google.client_id' => null]);
        Cache::flush();
        $before = Meeting::count();
        $this->actingAs($host)->postJson(route('meetings.request'), array_merge($body, ['room_slot' => 1]))
            ->assertStatus(422)->assertJsonPath('config_error', true);
        $this->assertSame($before, Meeting::count());
    }

    public function test_only_the_host_or_an_admin_can_edit_cancel_or_sync(): void
    {
        $host = $this->user('Host Person');
        $guest = $this->user('Guest One');
        $admin = $this->user('Admin Boss', ['role' => 'admin']);
        $m = $this->book($host, 1, invitees: [$guest->id])->fresh();

        $this->actingAs($guest)->post(route('meetings.cancel', $m))->assertForbidden();
        $this->actingAs($guest)->post(route('meetings.google.sync', $m))->assertForbidden();
        $this->actingAs($guest)->post(route('meetings.time', $m), ['action' => 'start'])->assertForbidden();
        $this->actingAs($guest)->put(route('meetings.update', $m), [])->assertForbidden();

        $this->actingAs($admin)->post(route('meetings.cancel', $m))->assertRedirect();
        $this->assertSame('cancelled', $m->fresh()->status);
        $this->assertSame(0, $this->fake->eventCount());
    }

    public function test_pages_render(): void
    {
        $host = $this->user('Host Person', ['avatar' => 'avatars/host.jpg']);
        $guest = $this->user('Guest One');
        $m = $this->book($host, 2, invitees: [$guest->id], extra: ['priority' => 'urgent'])->fresh();
        $m->mom()->create(['summary' => 'S']);
        $m->actionItems()->create(['title' => 'T1', 'assignee_user_id' => $guest->id]);
        $m->externalRecordings()->create(['source' => 'link', 'title' => 'Rec', 'url' => 'https://example.com/r']);

        foreach ([$host, $guest] as $who) {
            $this->actingAs($who)->withoutExceptionHandling()->get(route('meetings.show', $m))->assertOk()
                ->assertSee('Join Google Meet', false)->assertSee($m->google_meet_url, false)->assertSee('Meeting Room 2')->assertSee('Urgent');
            $this->actingAs($who)->get(route('meetings.index'))->assertOk()->assertSee('Weekly review 2')->assertSee('All meetings');
            $this->actingAs($who)->get(route('meetings.overview'))->assertOk()->assertSee('President Summary')->assertSee('Weekly review 2');
        }
        $this->actingAs($host)->get(route('meetings.show', $m))->assertSee('Edit')->assertSee('Cancel meeting')->assertDontSee('Retry Google sync');
        $this->actingAs($guest)->get(route('meetings.show', $m))->assertDontSee('Cancel meeting')->assertDontSee('Save &amp; update Google Calendar', false);

        // a meeting whose Google sync failed shows the real state, a Retry button and NO join button
        $m->update(['google_sync_status' => 'failed', 'google_sync_error' => 'Google Calendar is busy', 'google_meet_url' => null]);
        $this->actingAs($host)->get(route('meetings.show', $m))->assertOk()
            ->assertSee('Retry Google sync')->assertSee('Google Calendar is busy')->assertDontSee('Join Google Meet', false);
    }

    public function test_all_meetings_table_sorts_by_request_date_priority_status_and_meeting_time(): void
    {
        $host = $this->user('Host Person');
        $a = $this->book($host, 1, '09:00', '10:00', [], ['title' => 'A-oldest-normal', 'priority' => 'normal']);
        $b = $this->book($host, 2, '15:00', '16:00', [], ['title' => 'B-middle-urgent', 'priority' => 'urgent']);
        $c = $this->book($host, 3, '12:00', '13:00', [], ['title' => 'C-newest-immediate', 'priority' => 'immediate']);
        $d = $this->book($host, 4, '11:00', '12:00', [], ['title' => 'D-cancelled', 'priority' => 'urgent']);
        $this->scheduler()->cancel($d->fresh(), $host);
        DB::table('meetings')->where('id', $a->id)->update(['created_at' => now()->subDays(3)]);
        DB::table('meetings')->where('id', $b->id)->update(['created_at' => now()->subDays(2)]);
        DB::table('meetings')->where('id', $c->id)->update(['created_at' => now()->subDays(1)]);

        // only the "All meetings" table (the cards above it show the user's own upcoming meetings regardless of filters)
        $table = function (array $q) use ($host): string {
            $html = $this->actingAs($host)->get(route('meetings.index', $q))->assertOk()->getContent();
            $this->assertStringContainsString('<table', $html);
            return substr($html, strpos($html, '<table'), strpos($html, '</table>') - strpos($html, '<table'));
        };
        $seeInOrder = function (string $html, array $titles) {
            $pos = array_map(fn ($t) => strpos($html, $t), $titles);
            $this->assertNotContains(false, $pos, 'missing one of: '.implode(', ', $titles));
            $sorted = $pos;
            sort($sorted);
            $this->assertSame($sorted, $pos, 'wrong order for: '.implode(', ', $titles));
        };

        $seeInOrder($table([]), ['A-oldest-normal', 'B-middle-urgent', 'C-newest-immediate']);
        $this->assertStringNotContainsString('D-cancelled', $table([]));
        $seeInOrder($table(['sort' => 'requested', 'dir' => 'desc']), ['C-newest-immediate', 'B-middle-urgent', 'A-oldest-normal']);
        $seeInOrder($table(['sort' => 'priority']), ['B-middle-urgent', 'C-newest-immediate', 'A-oldest-normal']);
        $seeInOrder($table(['sort' => 'meeting']), ['A-oldest-normal', 'C-newest-immediate', 'B-middle-urgent']);
        $this->assertStringContainsString('D-cancelled', $table(['sort' => 'status', 'status' => 'all']));

        $t = $table(['priority' => 'urgent']);
        $this->assertStringContainsString('B-middle-urgent', $t);
        $this->assertStringNotContainsString('A-oldest-normal', $t);
        $t = $table(['room' => 3]);
        $this->assertStringContainsString('C-newest-immediate', $t);
        $this->assertStringNotContainsString('B-middle-urgent', $t);
        $t = $table(['status' => 'cancelled']);
        $this->assertStringContainsString('D-cancelled', $t);
        $this->assertStringNotContainsString('A-oldest-normal', $t);
    }

    public function test_floating_widget_markup_and_script_are_valid(): void
    {
        $host = $this->user('Host Person');
        $this->actingAs($host);
        $html = view('layouts.shared.meeting-request-fab')->render();
        $this->assertStringContainsString('mrqRoom', $html);
        $this->assertStringContainsString('Meeting Room 4', $html);
        $this->assertStringContainsString('🔴 Urgent', $html);
        $this->assertStringContainsString('🟠 Immediate', $html);
        $this->assertStringContainsString('🟢 Normal', $html);
        $this->assertStringContainsString('Join Google Meet', $html);

        $this->assertInlineJsParses($html);

        // the booking modal + RSVP script on the index page and the detail page scripts
        $m = $this->book($host, 1)->fresh();
        $this->assertInlineJsParses($this->actingAs($host)->get(route('meetings.index'))->getContent());
        $this->assertInlineJsParses($this->actingAs($host)->get(route('meetings.show', $m))->getContent());
    }

    /** Syntax-checks every inline <script> block with node, or macOS JavaScriptCore (osascript) when node is absent. */
    private function assertInlineJsParses(string $html): void
    {
        preg_match_all('~<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>~s', $html, $mm);
        $this->assertNotEmpty($mm[1], 'no inline script found');
        $js = implode("\n;\n", $mm[1]);
        $file = tempnam(sys_get_temp_dir(), 'js');
        file_put_contents($file, $js);

        exec('command -v node', $o, $hasNode);
        if ($hasNode === 0) {
            exec('node --check '.escapeshellarg($file).' 2>&1', $out, $code);
        } else {
            exec('command -v osascript', $o2, $hasOsa);
            if ($hasOsa !== 0) {
                $this->markTestSkipped('neither node nor osascript available for JS syntax check');
            }
            $jxa = "ObjC.import('Foundation'); var src = $.NSString.stringWithContentsOfFileEncodingError("
                .json_encode($file).", 4, $()).js; new Function(src); 'ok';";
            exec('osascript -l JavaScript -e '.escapeshellarg($jxa).' 2>&1', $out, $code);
        }
        $this->assertSame(0, $code, "JS syntax error:\n".implode("\n", $out ?? []));
    }

    public function test_background_command_retries_failed_syncs_and_closes_overdue_meetings(): void
    {
        $host = $this->user('Host Person');

        // failed sync -> retried by the scheduler, same event, link appears
        $this->fake->failNext(3, 500, 'POST', '/events');
        $failed = $this->scheduler()->create($this->payload(1, '10:00', '11:00'), $host)['meeting']->fresh();
        $this->assertSame('failed', $failed->google_sync_status);
        DB::table('meetings')->where('id', $failed->id)->update(['updated_at' => now()->subMinutes(10)]);

        // overdue meeting -> ended, actual times NOT invented
        $overdue = $this->book($host, 2, '10:00', '11:00')->fresh();
        DB::table('meetings')->where('id', $overdue->id)->update([
            'scheduled_at' => Meeting::dbTime(now()->subHours(5)), 'scheduled_end_at' => Meeting::dbTime(now()->subHours(4)),
        ]);

        $this->artisan('meetings:google-sync')->assertExitCode(0);

        $this->assertTrue($failed->fresh()->hasMeetLink());
        $overdue->refresh();
        $this->assertSame('ended', $overdue->status);
        $this->assertNull($overdue->time_taken_seconds);
        $this->assertNull($overdue->actual_started_at);
        $this->assertSame(2, $this->fake->eventCount());
    }

    public function test_legacy_meetings_are_untouched(): void
    {
        $host = $this->user('Host Person');
        $legacy = Meeting::create(['title' => 'Old jitsi meeting', 'room_name' => 'invent-abc', 'host_user_id' => $host->id, 'status' => 'ended',
            'started_at' => '2026-01-01 10:00:00', 'ended_at' => '2026-01-01 10:30:00']);
        $legacy->refresh();

        $this->assertSame('jitsi', $legacy->provider);
        $this->assertSame('normal', $legacy->priority);
        $this->assertNull($legacy->room_slot);
        $this->assertFalse($legacy->isGoogleMeet());
        $this->assertSame(1800, $legacy->actualDurationSeconds(), 'legacy duration still comes from started_at/ended_at');
        $this->assertNull(Meeting::roomConflict(1, now(), now()->addHour()));
    }
}

// ======================================================================
// Test doubles
// ======================================================================

/** Real GoogleMeetService, but every HTTP call goes to the in-process FakeGoogle. */
class FakeTransportGoogleMeetService extends GoogleMeetService
{
    public function __construct(private FakeGoogle $fake)
    {
    }

    protected function httpClient(): GuzzleClient
    {
        return new GuzzleClient(['handler' => HandlerStack::create($this->fake)]);
    }
}

/** Tiny fake of oauth2.googleapis.com/token and the Calendar v3 events endpoints. */
class FakeGoogle
{
    public array $events = [];
    public array $inserts = [];
    public array $insertAttempts = [];
    public array $patches = [];
    public array $deletes = [];
    public int $gets = 0;
    private int $requests = 0;
    private int $counter = 0;
    private array $failures = [];
    public int $pendingPolls = 0;
    public ?string $forcedHangoutLink = null;
    public bool $loseNextInsertResponse = false;
    public ?string $tokenError = null;

    public function failNext(int $times, int $status, string $method, string $pathContains = ''): void
    {
        $this->failures[] = ['times' => $times, 'status' => $status, 'method' => $method, 'path' => $pathContains];
    }

    public function eventCount(): int
    {
        return count($this->events);
    }

    public function requestCount(): int
    {
        return $this->requests;
    }

    public function __invoke(RequestInterface $request, array $options)
    {
        $this->requests++;
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        parse_str($request->getUri()->getQuery(), $query);
        $body = json_decode((string) $request->getBody(), true) ?: [];

        if (str_contains($path, '/token')) {
            if ($this->tokenError) {
                return $this->respond(400, ['error' => $this->tokenError, 'error_description' => 'Token has been expired or revoked.']);
            }

            return $this->respond(200, ['access_token' => 'fake-access', 'expires_in' => 3600, 'token_type' => 'Bearer']);
        }

        foreach ($this->failures as &$f) {
            if ($f['times'] > 0 && $f['method'] === $method && ($f['path'] === '' || str_contains($path, $f['path']))) {
                $f['times']--;
                if ($method === 'POST') {
                    $this->insertAttempts[] = ['body' => $body];
                }

                return $this->respond($f['status'], ['error' => ['code' => $f['status'], 'message' => 'Backend Error', 'errors' => [['reason' => 'backendError']]]]);
            }
        }
        unset($f);

        if (! preg_match('~/calendars/([^/]+)/events(?:/([^/]+))?$~', $path, $mm)) {
            return $this->respond(404, ['error' => ['code' => 404, 'message' => 'Not Found']]);
        }
        $id = $mm[2] ?? null;

        if ($method === 'POST' && $id === null) {
            $this->insertAttempts[] = ['body' => $body];
            $eid = $body['id'];
            if (isset($this->events[$eid])) {
                return $this->respond(409, ['error' => ['code' => 409, 'message' => 'The requested identifier already exists.', 'errors' => [['reason' => 'duplicate']]]]);
            }
            $this->inserts[] = ['body' => $body, 'query' => $query];
            $this->events[$eid] = $this->materialise($eid, $body);
            if ($this->loseNextInsertResponse) {
                $this->loseNextInsertResponse = false;

                return Create::rejectionFor(new \GuzzleHttp\Exception\ConnectException('cURL error 28: timed out', $request));
            }

            return $this->respond(200, $this->view($eid));
        }

        if ($method === 'GET' && $id !== null) {
            $this->gets++;
            if (! isset($this->events[$id])) {
                return $this->respond(404, ['error' => ['code' => 404, 'message' => 'Not Found', 'errors' => [['reason' => 'notFound']]]]);
            }

            return $this->respond(200, $this->view($id));
        }

        if ($method === 'PATCH' && $id !== null) {
            if (! isset($this->events[$id])) {
                return $this->respond(404, ['error' => ['code' => 404, 'message' => 'Not Found']]);
            }
            $this->patches[] = ['body' => $body, 'query' => $query, 'id' => $id];
            $keep = $this->events[$id];
            $this->events[$id] = array_merge($keep, array_diff_key($body, ['conferenceData' => 1]));
            if (isset($body['conferenceData'])) {
                $this->events[$id] = $this->materialise($id, array_merge($keep, $body));
            }

            return $this->respond(200, $this->view($id));
        }

        if ($method === 'DELETE' && $id !== null) {
            $this->deletes[] = ['id' => $id, 'query' => $query];
            if (! isset($this->events[$id])) {
                return $this->respond(410, ['error' => ['code' => 410, 'message' => 'Resource has been deleted']]);
            }
            unset($this->events[$id]);

            return $this->respond(204, null);
        }

        return $this->respond(400, ['error' => ['code' => 400, 'message' => 'Unsupported in fake']]);
    }

    private function materialise(string $id, array $body): array
    {
        $this->counter++;
        $code = sprintf('abc-defg-%s%s%s', chr(97 + $this->counter % 26), chr(97 + ($this->counter * 7) % 26), chr(97 + ($this->counter * 3) % 26));
        $link = $this->forcedHangoutLink ?? 'https://meet.google.com/'.$code;

        // Keep the link of an event that already has one (an edit never changes the conference).
        $existing = $this->events[$id]['_link'] ?? null;

        return $body + ['id' => $id, 'status' => 'confirmed', 'htmlLink' => 'https://www.google.com/calendar/event?eid='.$id, '_link' => $existing ?? $link];
    }

    /** What Google would return for an event right now. */
    private function view(string $id): array
    {
        $e = $this->events[$id];
        $link = $e['_link'];
        unset($e['_link']);

        if ($this->pendingPolls > 0) {
            $this->pendingPolls--; // asynchronous conference: still being generated
            $e['conferenceData'] = ['createRequest' => ['requestId' => 'x', 'status' => ['statusCode' => 'pending']]];

            return $e;
        }

        $e['hangoutLink'] = $link;
        $e['conferenceData'] = [
            'createRequest' => ['requestId' => 'x', 'status' => ['statusCode' => 'success']],
            'entryPoints' => [['entryPointType' => 'video', 'uri' => $link]],
        ];

        return $e;
    }

    private function respond(int $status, ?array $json)
    {
        return Create::promiseFor(new Response($status, ['Content-Type' => 'application/json'], $json === null ? '' : json_encode($json)));
    }
}
