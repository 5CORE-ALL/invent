<?php

namespace App\Services\Meetings;

use App\Casts\MeetingDateTime;
use App\Models\GoogleMeetConnection;
use App\Models\Meeting;
use App\Models\MeetingExternalRecording;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\ConferenceSolutionKey;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\EventExtendedProperties;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real Google Meet meetings through the Google Calendar API.
 *
 *  - events.insert with conferenceDataVersion=1 and conferenceData.createRequest (hangoutsMeet)
 *  - the Meet link is only ever read back from Google (hangoutLink / video entryPoint), never invented
 *  - idempotent: a client-chosen event id + a stable createRequest.requestId mean retries and edits
 *    can never create a second calendar event or a second conference
 *  - transient failures (429 / 5xx / network) are retried with exponential back-off
 */
class GoogleMeetService
{
    public const SCOPE_CALENDAR_EVENTS = 'https://www.googleapis.com/auth/calendar.events';
    public const SCOPE_MEET_READONLY = 'https://www.googleapis.com/auth/meetings.space.readonly';
    public const SCOPE_DRIVE_MEET_READONLY = 'https://www.googleapis.com/auth/drive.meet.readonly';

    private const MEET_API = 'https://meet.googleapis.com/v2/';

    // ------------------------------------------------------------------
    // Configuration
    // ------------------------------------------------------------------

    public function connection(): ?GoogleMeetConnection
    {
        try {
            return GoogleMeetConnection::query()->latest('id')->first();
        } catch (\Throwable $e) {
            return null; // table not migrated yet
        }
    }

    /** @return list<string> */
    public function requestedScopes(): array
    {
        $scopes = [self::SCOPE_CALENDAR_EVENTS];
        if (config('services.meetings.google.recording_scopes')) {
            $scopes[] = self::SCOPE_MEET_READONLY;
            $scopes[] = self::SCOPE_DRIVE_MEET_READONLY;
        }

        return $scopes;
    }

    public function oauthClientConfigured(): bool
    {
        return filled(config('services.meetings.google.client_id')) && filled(config('services.meetings.google.client_secret'));
    }

    public function serviceAccountPath(): ?string
    {
        $path = config('services.meetings.google.service_account_json');

        return filled($path) && is_file((string) $path) ? (string) $path : null;
    }

    public function serviceAccountConfigured(): bool
    {
        return $this->serviceAccountPath() !== null && filled(config('services.meetings.google.impersonate_email'));
    }

    /** 'oauth' | 'service_account' | null */
    public function mode(): ?string
    {
        if ($this->connection()) {
            return 'oauth';
        }
        if ($this->serviceAccountConfigured()) {
            return 'service_account';
        }

        return null;
    }

    /**
     * @return array{ok: bool, mode: ?string, email: ?string, message: ?string, recordings: bool, missing: list<string>}
     */
    public function status(): array
    {
        $mode = $this->mode();
        $missing = [];

        if ($mode === null) {
            if (! $this->oauthClientConfigured()) {
                $missing[] = 'GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET (or MEETING_GOOGLE_CLIENT_ID / MEETING_GOOGLE_CLIENT_SECRET) for a Google OAuth 2.0 web client';
                $message = 'Google Meet is not configured. Add the Google OAuth client credentials to .env, enable the Google Calendar API for that project, then a manager must click "Connect Google account" on the Meeting Room page. Until then meetings cannot be created.';
            } else {
                $message = 'Google Meet is not connected yet. A manager must click "Connect Google account" on the Meeting Room page and approve Google Calendar access. Until then meetings cannot be created.';
                $missing[] = 'Connect a Google account (Google Calendar events scope)';
            }

            return ['ok' => false, 'mode' => null, 'email' => null, 'message' => $message, 'recordings' => false, 'missing' => $missing];
        }

        $conn = $this->connection();
        $recordings = false;
        if ($mode === 'oauth' && $conn) {
            $recordings = $conn->hasScope(self::SCOPE_MEET_READONLY);
        } elseif ($mode === 'service_account') {
            $recordings = (bool) config('services.meetings.google.recording_scopes');
        }

        return [
            'ok' => true,
            'mode' => $mode,
            'email' => $mode === 'oauth' ? $conn?->email : config('services.meetings.google.impersonate_email'),
            'message' => null,
            'recordings' => $recordings,
            'missing' => $recordings ? [] : ['Google Meet recordings: '.self::SCOPE_MEET_READONLY.' and '.self::SCOPE_DRIVE_MEET_READONLY.' (set MEETING_GOOGLE_RECORDING_SCOPES=true and reconnect; Workspace plan with recording + admin approval)'],
        ];
    }

    public function isConfigured(): bool
    {
        return $this->status()['ok'];
    }

    /** @throws GoogleMeetException */
    public function assertConfigured(): void
    {
        $s = $this->status();
        if (! $s['ok']) {
            throw GoogleMeetException::notConfigured((string) $s['message']);
        }
    }

    // ------------------------------------------------------------------
    // OAuth connect flow (used by the controller)
    // ------------------------------------------------------------------

    public function oauthClient(string $redirectUri): Client
    {
        $client = new Client();
        $client->setApplicationName('Inventory Meetings');
        $client->setClientId((string) config('services.meetings.google.client_id'));
        $client->setClientSecret((string) config('services.meetings.google.client_secret'));
        $client->setRedirectUri($redirectUri);
        $client->setScopes(array_merge($this->requestedScopes(), ['openid', 'email']));
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // guarantees a refresh token is returned
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    /**
     * Exchange the OAuth code and persist the (encrypted) refresh token.
     *
     * @throws GoogleMeetException
     */
    public function completeOAuth(string $code, string $redirectUri, ?int $userId): GoogleMeetConnection
    {
        $client = $this->oauthClient($redirectUri);
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            Log::warning('Google Meet OAuth code exchange failed', ['error' => $token['error'], 'description' => $token['error_description'] ?? null]);
            throw new GoogleMeetException('Google refused the sign-in ('.$token['error'].'). Check that the redirect URI '.$redirectUri.' is authorised on the OAuth client, then try again.', 'auth');
        }
        if (empty($token['refresh_token'])) {
            throw new GoogleMeetException('Google did not return a refresh token. Remove this app at myaccount.google.com/permissions and connect again.', 'auth');
        }

        $granted = (string) ($token['scope'] ?? '');
        if (! str_contains($granted, self::SCOPE_CALENDAR_EVENTS)) {
            throw new GoogleMeetException('The Google Calendar permission was not granted. Tick the calendar permission on the Google consent screen and try again.', 'scope');
        }

        // The id_token came straight from Google's token endpoint over TLS; we only read the e-mail from it.
        $email = null;
        if (! empty($token['id_token'])) {
            $parts = explode('.', (string) $token['id_token']);
            $payload = isset($parts[1]) ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true) : null;
            $email = is_array($payload) ? ($payload['email'] ?? null) : null;
        }

        $conn = GoogleMeetConnection::query()->latest('id')->first() ?? new GoogleMeetConnection();
        $conn->fill([
            'email' => $email,
            'refresh_token' => $token['refresh_token'],
            'scopes' => $granted,
            'connected_by' => $userId,
        ])->save();
        Cache::forget($this->tokenCacheKey());

        return $conn;
    }

    public function disconnect(): void
    {
        $conn = $this->connection();
        if ($conn) {
            try {
                $this->oauthClient(url('/'))->revokeToken($conn->refresh_token);
            } catch (\Throwable $e) {
                // best effort: the local token is removed regardless
            }
            GoogleMeetConnection::query()->delete();
        }
        Cache::forget($this->tokenCacheKey());
    }

    // ------------------------------------------------------------------
    // Client plumbing (overridable in tests)
    // ------------------------------------------------------------------

    protected function httpClient(): GuzzleClient
    {
        return new GuzzleClient(['timeout' => 25, 'connect_timeout' => 8]);
    }

    private function tokenCacheKey(): string
    {
        return 'meetings.google.access_token.'.sha1((string) ($this->connection()?->id).'|'.config('services.meetings.google.impersonate_email'));
    }

    /**
     * Authorised Google client. The access token is cached (encrypted) until shortly before it expires.
     *
     * @throws GoogleMeetException
     */
    protected function makeClient(): Client
    {
        $this->assertConfigured();

        $client = new Client();
        $client->setApplicationName('Inventory Meetings');
        $client->setHttpClient($this->httpClient());
        $client->setScopes($this->requestedScopes());

        $cacheKey = $this->tokenCacheKey();
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            try {
                $client->setAccessToken(json_decode(Crypt::decryptString($cached), true) ?: []);
                if (! $client->isAccessTokenExpired()) {
                    return $client;
                }
            } catch (\Throwable $e) {
                Cache::forget($cacheKey);
            }
        }

        try {
            if ($this->mode() === 'oauth') {
                $conn = $this->connection();
                $client->setClientId((string) config('services.meetings.google.client_id'));
                $client->setClientSecret((string) config('services.meetings.google.client_secret'));
                $token = $client->fetchAccessTokenWithRefreshToken($conn->refresh_token);
            } else {
                $client->setAuthConfig((string) $this->serviceAccountPath());
                $client->setSubject((string) config('services.meetings.google.impersonate_email'));
                $token = $client->fetchAccessTokenWithAssertion();
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Google answered with an error (HTTP 400/401 for revoked or invalid credentials, 5xx for outages).
            $status = $e->getResponse()?->getStatusCode();
            $payload = $e->getResponse() ? (json_decode((string) $e->getResponse()->getBody(), true) ?: []) : [];
            if ($status === null || $status >= 500) {
                throw new GoogleMeetException('Could not reach Google to sign in. Please retry in a moment.', 'network', true, $e);
            }
            $token = ['error' => (string) ($payload['error'] ?? 'invalid_request'), 'error_description' => $payload['error_description'] ?? null];
        } catch (GuzzleException $e) {
            throw new GoogleMeetException('Could not reach Google to sign in. Please retry in a moment.', 'network', true, $e);
        } catch (\Throwable $e) {
            Log::error('Google Meet token request failed', ['error' => $e->getMessage()]);
            throw new GoogleMeetException('Google sign-in failed. A manager should reconnect the Google account on the Meeting Room page.', 'auth', false, $e);
        }

        if (isset($token['error'])) {
            Log::error('Google Meet token refused', ['error' => $token['error'], 'description' => $token['error_description'] ?? null]);
            $msg = ($token['error'] === 'invalid_grant')
                ? 'Google access was revoked or expired. A manager must reconnect the Google account on the Meeting Room page.'
                : 'Google refused the credentials ('.$token['error'].'). Check the Google OAuth client / service-account delegation settings.';
            throw new GoogleMeetException($msg, 'auth');
        }

        $client->setAccessToken($token);
        $ttl = max(60, ((int) ($token['expires_in'] ?? 3600)) - 120);
        Cache::put($cacheKey, Crypt::encryptString(json_encode($token)), $ttl);

        return $client;
    }

    protected function calendarService(): Calendar
    {
        return new Calendar($this->makeClient());
    }

    private function calendarId(Meeting $m): string
    {
        return $m->google_calendar_id ?: (string) config('services.meetings.google.calendar_id', 'primary');
    }

    // ------------------------------------------------------------------
    // Retry / error translation
    // ------------------------------------------------------------------

    /**
     * Run a Google call, retrying transient failures with exponential back-off.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     *
     * @throws GoogleMeetException|GoogleServiceException
     */
    protected function withRetry(callable $fn)
    {
        $max = max(1, (int) config('services.meetings.google.max_attempts', 3));
        $base = (int) config('services.meetings.google.retry_sleep_ms', 500);

        for ($attempt = 1; ; $attempt++) {
            try {
                return $fn();
            } catch (GoogleServiceException $e) {
                if ($attempt >= $max || ! $this->isTransient($e)) {
                    throw $e;
                }
                Log::warning('Google Calendar transient error, retrying', ['attempt' => $attempt, 'code' => $e->getCode()]);
            } catch (ConnectException $e) {
                if ($attempt >= $max) {
                    throw new GoogleMeetException('Could not reach Google Calendar. Please retry in a moment.', 'network', true, $e);
                }
                Log::warning('Google Calendar connection error, retrying', ['attempt' => $attempt]);
            }
            usleep($base * (2 ** ($attempt - 1)) * 1000);
        }
    }

    private function isTransient(GoogleServiceException $e): bool
    {
        $code = (int) $e->getCode();
        if ($code === 429 || ($code >= 500 && $code < 600)) {
            return true;
        }
        if ($code === 403) {
            foreach ($e->getErrors() ?? [] as $err) {
                if (in_array($err['reason'] ?? '', ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function translate(\Throwable $e): GoogleMeetException
    {
        if ($e instanceof GoogleMeetException) {
            return $e;
        }
        if ($e instanceof GoogleServiceException) {
            $code = (int) $e->getCode();
            $reasons = array_map(fn ($x) => (string) ($x['reason'] ?? ''), $e->getErrors() ?? []);
            Log::error('Google Calendar API error', ['code' => $code, 'reasons' => $reasons, 'message' => mb_substr($e->getMessage(), 0, 500)]);

            if ($code === 401) {
                return new GoogleMeetException('Google rejected the saved authorisation. A manager must reconnect the Google account on the Meeting Room page.', 'auth', false, $e);
            }
            if (array_intersect($reasons, ['accessNotConfigured', 'SERVICE_DISABLED']) || str_contains($e->getMessage(), 'has not been used in project')) {
                return new GoogleMeetException('The Google Calendar API is not enabled for this Google Cloud project. Enable "Google Calendar API" in Google Cloud Console (APIs & Services > Library).', 'api_disabled', false, $e);
            }
            if ($code === 403 && array_intersect($reasons, ['insufficientPermissions', 'forbidden', 'ACCESS_TOKEN_SCOPE_INSUFFICIENT'])) {
                return new GoogleMeetException('The connected Google account lacks calendar permission. Reconnect it and approve the Google Calendar scope.', 'scope', false, $e);
            }
            if ($this->isTransient($e)) {
                return new GoogleMeetException('Google Calendar is busy or rate-limiting requests. It will be retried automatically; you can also press "Retry Google sync".', 'quota', true, $e);
            }
            if ($code === 404 || $code === 410) {
                return new GoogleMeetException('The Google Calendar event no longer exists.', 'not_found', false, $e);
            }

            return new GoogleMeetException('Google Calendar rejected the request (HTTP '.$code.'). '.mb_substr(strip_tags($e->getMessage()), 0, 200), 'api', $code >= 500, $e);
        }
        if ($e instanceof GuzzleException) {
            return new GoogleMeetException('Could not reach Google Calendar. Please retry in a moment.', 'network', true, $e);
        }
        Log::error('Google Meet unexpected error', ['class' => get_class($e), 'message' => $e->getMessage()]);

        return new GoogleMeetException('Unexpected error while talking to Google Calendar.', 'api', true, $e);
    }

    // ------------------------------------------------------------------
    // Create / update (idempotent)
    // ------------------------------------------------------------------

    /**
     * Make Google Calendar match this meeting, creating the event + Meet conference if it does not
     * exist yet. Safe to call repeatedly: it never creates a second event for the same meeting.
     *
     * The result is stored on the meeting row (event id, Meet URL, sync status). The exception, if any,
     * is also recorded on the row and then re-thrown so callers can show it.
     *
     * @throws GoogleMeetException
     */
    public function syncMeeting(Meeting $meeting): Meeting
    {
        $this->assertConfigured(); // nothing is recorded on the row: no Google attempt was made

        // Stable idempotency key, persisted BEFORE the first API call.
        if (! $meeting->google_request_id) {
            $meeting->forceFill(['google_request_id' => bin2hex(random_bytes(16))])->save();
        }
        $meeting->forceFill(['google_sync_attempts' => (int) $meeting->google_sync_attempts + 1])->save();

        try {
            $service = $this->calendarService();
            $calendarId = $this->calendarId($meeting);
            $event = $this->upsertEvent($service, $meeting, $calendarId);
            // Persist the event id at once: if anything below fails, a retry updates this event instead of creating another.
            $meeting->forceFill([
                'google_event_id' => $event->getId(),
                'google_calendar_id' => $calendarId,
                'google_html_link' => $event->getHtmlLink(),
            ])->save();
            $event = $this->awaitConference($service, $calendarId, $event, $meeting);

            $link = $this->extractMeetLink($event);

            $meeting->forceFill([
                'google_event_id' => $event->getId(),
                'google_calendar_id' => $calendarId,
                'google_html_link' => $event->getHtmlLink(),
                'google_meet_url' => $link,
                'google_synced_at' => now(),
            ]);

            if ($link) {
                $meeting->forceFill(['google_sync_status' => Meeting::SYNC_SYNCED, 'google_sync_error' => null])->save();
            } else {
                $meeting->forceFill([
                    'google_sync_status' => Meeting::SYNC_PENDING,
                    'google_sync_error' => 'Google is still generating the Meet link. It will be picked up automatically; press "Retry Google sync" to check again.',
                ])->save();
            }

            return $meeting;
        } catch (\Throwable $e) {
            $ex = $this->translate($e);
            $meeting->forceFill([
                'google_sync_status' => Meeting::SYNC_FAILED,
                'google_sync_error' => mb_substr($ex->getMessage(), 0, 1000),
            ])->save();
            throw $ex;
        }
    }

    private function upsertEvent(Calendar $service, Meeting $meeting, string $calendarId): Event
    {
        $eventId = $meeting->google_event_id ?: 'inv'.$meeting->google_request_id;

        // 1. We already know the event (or a previous attempt may have created it): read it first.
        $existing = null;
        try {
            $existing = $this->withRetry(fn () => $service->events->get($calendarId, $eventId));
        } catch (GoogleServiceException $e) {
            if (! in_array((int) $e->getCode(), [404, 410], true)) {
                throw $e;
            }
            if ($meeting->google_event_id) {
                // The saved event was deleted on Google's side. Re-creating it would resurrect a meeting somebody
                // removed on purpose, so report it instead.
                throw new GoogleMeetException('This meeting\'s Google Calendar event was deleted in Google Calendar. Cancel the meeting here, or create a new meeting.', 'not_found');
            }
            // No saved id and Google has no such event yet: fall through and create it.
        }

        if ($existing instanceof Event && $existing->getStatus() === 'cancelled') {
            throw new GoogleMeetException('This meeting\'s Google Calendar event is cancelled in Google Calendar. Create a new meeting instead.', 'not_found');
        }

        if ($existing instanceof Event) {
            return $this->patchEvent($service, $meeting, $calendarId, $existing);
        }

        // 2. Create it. The client-chosen id makes a retry after a timeout idempotent (409 = it exists).
        $event = $this->buildEvent($meeting, 'conf-'.$meeting->google_request_id);
        $event->setId($eventId);

        try {
            return $this->withRetry(fn () => $service->events->insert($calendarId, $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all',
            ]));
        } catch (GoogleServiceException $e) {
            if ((int) $e->getCode() === 409) {
                // The earlier attempt did create it. Adopt it instead of creating a duplicate.
                $adopted = $this->withRetry(fn () => $service->events->get($calendarId, $eventId));

                return $this->patchEvent($service, $meeting, $calendarId, $adopted);
            }
            throw $e;
        }
    }

    private function patchEvent(Calendar $service, Meeting $meeting, string $calendarId, Event $existing): Event
    {
        $needsConference = $this->extractMeetLink($existing) === null
            && ! $this->conferencePending($existing);

        // A fresh requestId is needed when an earlier conference request failed (Google replays the old result
        // for a repeated requestId); a still-pending or finished request is never re-requested.
        $patch = $this->buildEvent($meeting, $needsConference ? 'conf-'.$meeting->google_request_id.'-'.(int) $meeting->google_sync_attempts : null);

        return $this->withRetry(fn () => $service->events->patch($calendarId, $existing->getId(), $patch, [
            'conferenceDataVersion' => 1,
            'sendUpdates' => 'all',
        ]));
    }

    private function buildEvent(Meeting $meeting, ?string $conferenceRequestId): Event
    {
        $meeting->loadMissing(['host:id,name,email', 'participants.user:id,name,email']);
        $tz = MeetingDateTime::timezone();

        $event = new Event();
        $event->setSummary($meeting->title);
        $event->setDescription($this->description($meeting));
        $event->setLocation($meeting->roomLabel() ?: null);
        $event->setColorId(Meeting::PRIORITIES[$meeting->priority][5] ?? null);
        $event->setGuestsCanModify(false);

        $start = new EventDateTime();
        $start->setDateTime($meeting->scheduled_at->copy()->setTimezone($tz)->toRfc3339String());
        $start->setTimeZone($tz);
        $end = new EventDateTime();
        $end->setDateTime($meeting->scheduled_end_at->copy()->setTimezone($tz)->toRfc3339String());
        $end->setTimeZone($tz);
        $event->setStart($start);
        $event->setEnd($end);

        $event->setAttendees($this->attendees($meeting));

        $props = new EventExtendedProperties();
        $props->setPrivate(['inventMeetingId' => (string) $meeting->id, 'room' => (string) $meeting->room_slot]);
        $event->setExtendedProperties($props);

        if ($conferenceRequestId !== null) {
            $key = new ConferenceSolutionKey();
            $key->setType('hangoutsMeet');
            $req = new CreateConferenceRequest();
            // Same requestId for the same meeting: Google returns the existing conference rather than a new one.
            $req->setRequestId($conferenceRequestId);
            $req->setConferenceSolutionKey($key);
            $conf = new ConferenceData();
            $conf->setCreateRequest($req);
            $event->setConferenceData($conf);
        }

        return $event;
    }

    private function description(Meeting $meeting): string
    {
        $p = $meeting->priorityMeta();
        $lines = [];
        if (filled($meeting->agenda)) {
            $lines[] = "Agenda:\n".trim((string) $meeting->agenda);
            $lines[] = '';
        }
        $lines[] = 'Priority: '.$p['label'];
        if ($meeting->roomLabel()) {
            $lines[] = 'Internal room: '.$meeting->roomLabel();
        }
        $lines[] = 'Organised by: '.($meeting->host?->name ?? 'Team');
        $lines[] = 'Details: '.route('meetings.show', $meeting);

        return implode("\n", $lines);
    }

    /** @return list<EventAttendee> */
    private function attendees(Meeting $meeting): array
    {
        $seen = [];
        $out = [];
        $add = function (?string $email, ?string $name) use (&$seen, &$out) {
            $email = strtolower(trim((string) $email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                return;
            }
            $seen[$email] = true;
            $a = new EventAttendee();
            $a->setEmail($email);
            if ($name) {
                $a->setDisplayName($name);
            }
            $out[] = $a;
        };

        $add($meeting->host?->email, $meeting->host?->name);
        foreach ($meeting->participants as $p) {
            $add($p->user?->email, $p->user?->name);
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Asynchronous conference generation
    // ------------------------------------------------------------------

    private function conferenceStatus(Event $event): ?string
    {
        return $event->getConferenceData()?->getCreateRequest()?->getStatus()?->getStatusCode();
    }

    private function conferencePending(Event $event): bool
    {
        return $this->conferenceStatus($event) === 'pending';
    }

    /**
     * The real link, or null. Only https://meet.google.com/... URLs that Google returned are accepted.
     */
    public function extractMeetLink(Event $event): ?string
    {
        $candidates = [$event->getHangoutLink()];
        foreach ($event->getConferenceData()?->getEntryPoints() ?? [] as $ep) {
            if ($ep->getEntryPointType() === 'video') {
                $candidates[] = $ep->getUri();
            }
        }
        foreach ($candidates as $url) {
            if (is_string($url) && preg_match('~^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}(\?.*)?$~', $url)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Conference creation can be asynchronous (createRequest.status = pending). Poll events.get until
     * Google reports a hangoutLink or gives up.
     *
     * @throws GoogleMeetException
     */
    private function awaitConference(Calendar $service, string $calendarId, Event $event, Meeting $meeting): Event
    {
        $attempts = max(1, (int) config('services.meetings.google.conference_poll_attempts', 6));
        $sleepMs = (int) config('services.meetings.google.conference_poll_sleep_ms', 1000);

        for ($i = 0; $i < $attempts; $i++) {
            if ($this->extractMeetLink($event)) {
                return $event;
            }
            $status = $this->conferenceStatus($event);
            if ($status === 'failure') {
                throw new GoogleMeetException('Google could not generate a Meet link for this event. Press "Retry Google sync" (a fresh request is sent for the same event, no duplicate is created). If it keeps failing, the connected account may not be allowed to create Meet conferences.', 'conference_failed', true);
            }
            if ($status === null && $i === 0 && $event->getConferenceData() === null) {
                // Event exists without any conference request (e.g. patched by someone else): nothing to wait for.
                return $event;
            }
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
            $event = $this->withRetry(fn () => $service->events->get($calendarId, $event->getId()));
        }

        return $event;
    }

    // ------------------------------------------------------------------
    // Cancel
    // ------------------------------------------------------------------

    /**
     * Delete the calendar event (guests are notified). A missing event counts as already cancelled.
     *
     * @throws GoogleMeetException
     */
    public function cancelMeeting(Meeting $meeting): void
    {
        if (! $meeting->google_event_id) {
            // Nothing was ever created on Google (or the create never completed).
            if ($meeting->google_request_id && $this->isConfigured()) {
                $this->deleteEvent($meeting, 'inv'.$meeting->google_request_id, true);
            }
            $meeting->forceFill(['google_sync_status' => Meeting::SYNC_CANCELLED, 'google_sync_error' => null])->save();

            return;
        }

        $this->assertConfigured();
        $this->deleteEvent($meeting, $meeting->google_event_id, false);
        $meeting->forceFill(['google_sync_status' => Meeting::SYNC_CANCELLED, 'google_sync_error' => null, 'google_synced_at' => now()])->save();
    }

    private function deleteEvent(Meeting $meeting, string $eventId, bool $ignoreMissing): void
    {
        try {
            $service = $this->calendarService();
            $this->withRetry(fn () => $service->events->delete($this->calendarId($meeting), $eventId, ['sendUpdates' => 'all']));
        } catch (GoogleServiceException $e) {
            if (in_array((int) $e->getCode(), [404, 410], true)) {
                return; // already gone
            }
            $ex = $this->translate($e);
            $meeting->forceFill(['google_sync_error' => mb_substr($ex->getMessage(), 0, 1000)])->save();
            throw $ex;
        } catch (\Throwable $e) {
            $ex = $this->translate($e);
            if ($ignoreMissing && $ex->reason === 'not_found') {
                return;
            }
            $meeting->forceFill(['google_sync_error' => mb_substr($ex->getMessage(), 0, 1000)])->save();
            throw $ex;
        }
    }

    // ------------------------------------------------------------------
    // Recordings + real conference times (Google Meet REST API)
    // ------------------------------------------------------------------

    public function meetingCode(Meeting $meeting): ?string
    {
        if (! $meeting->hasMeetLink()) {
            return null;
        }
        $path = trim((string) parse_url((string) $meeting->google_meet_url, PHP_URL_PATH), '/');

        return preg_match('/^[a-z]{3}-[a-z]{4}-[a-z]{3}$/', $path) ? $path : null;
    }

    /**
     * Pull what Google really has: finished recordings (state FILE_GENERATED) and the real start/end of the
     * conference. Recordings are only stored once Google reports the file exists.
     *
     * @return array{conferences: int, recordings_added: int, recordings_pending: int, started_at: ?Carbon, ended_at: ?Carbon}
     *
     * @throws GoogleMeetException
     */
    public function syncConference(Meeting $meeting): array
    {
        $this->assertConfigured();
        $status = $this->status();
        if (! $status['recordings']) {
            throw new GoogleMeetException('Reading Google Meet recordings is not authorised. Required: scopes '.self::SCOPE_MEET_READONLY.' and '.self::SCOPE_DRIVE_MEET_READONLY.' (set MEETING_GOOGLE_RECORDING_SCOPES=true, reconnect the Google account, and make sure the Workspace plan allows Meet recording).', 'scope');
        }
        $code = $this->meetingCode($meeting);
        if (! $code) {
            throw new GoogleMeetException('This meeting has no Google Meet link yet.', 'not_found');
        }

        $token = (string) ($this->makeClient()->getAccessToken()['access_token'] ?? '');
        $http = fn () => Http::withToken($token)->acceptJson()->timeout(25)->retry(3, 500, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, throw: false);

        $resp = $http()->get(self::MEET_API.'conferenceRecords', ['filter' => 'space.meeting_code="'.$code.'"']);
        $this->assertMeetResponse($resp);

        $records = $resp->json('conferenceRecords', []);
        $added = 0;
        $pending = 0;
        $start = null;
        $end = null;

        foreach ($records as $rec) {
            if (! empty($rec['startTime'])) {
                $s = Carbon::parse($rec['startTime']);
                $start = $start === null || $s->lt($start) ? $s : $start;
            }
            if (! empty($rec['endTime'])) {
                $e = Carbon::parse($rec['endTime']);
                $end = $end === null || $e->gt($end) ? $e : $end;
            }

            $recResp = $http()->get(self::MEET_API.$rec['name'].'/recordings');
            $this->assertMeetResponse($recResp);

            foreach ($recResp->json('recordings', []) as $r) {
                if (($r['state'] ?? '') !== 'FILE_GENERATED' || empty($r['driveDestination']['exportUri'])) {
                    $pending++; // started / ended but the file is not generated yet: do NOT show it as available
                    continue;
                }
                $row = MeetingExternalRecording::query()->firstOrCreate(
                    ['google_resource' => $r['name']],
                    [
                        'meeting_id' => $meeting->id,
                        'source' => MeetingExternalRecording::SOURCE_GOOGLE_MEET,
                        'title' => 'Google Meet recording',
                        'url' => $r['driveDestination']['exportUri'],
                    ]
                );
                if ($row->wasRecentlyCreated) {
                    $added++;
                }
            }
        }

        if ($start && $end && $end->gte($start)) {
            // Only fill what nobody entered by hand.
            $updates = [];
            if (! $meeting->actual_started_at) {
                $updates['actual_started_at'] = $start;
            }
            if (! $meeting->actual_ended_at) {
                $updates['actual_ended_at'] = $end;
            }
            if ($updates) {
                $meeting->forceFill($updates);
                if ($meeting->actual_started_at && $meeting->actual_ended_at) {
                    $meeting->time_taken_seconds = max(0, $meeting->actual_started_at->diffInSeconds($meeting->actual_ended_at));
                }
            }
        }
        $meeting->forceFill(['google_conference_synced_at' => now()])->save();

        return ['conferences' => count($records), 'recordings_added' => $added, 'recordings_pending' => $pending, 'started_at' => $start, 'ended_at' => $end];
    }

    private function assertMeetResponse(\Illuminate\Http\Client\Response $resp): void
    {
        if ($resp->successful()) {
            return;
        }
        $status = $resp->status();
        Log::warning('Google Meet REST API error', ['status' => $status, 'body' => mb_substr($resp->body(), 0, 500)]);

        if ($status === 401) {
            throw new GoogleMeetException('Google rejected the saved authorisation for Google Meet. Reconnect the Google account.', 'auth');
        }
        if ($status === 403) {
            throw new GoogleMeetException('Google denied access to this meeting\'s recordings. Only the organiser account (or an account granted access by a Workspace admin) can read them, and the Google Meet API must be enabled for the project.', 'scope');
        }
        if ($status === 429 || $status >= 500) {
            throw new GoogleMeetException('Google Meet is temporarily unavailable. Try again shortly.', 'quota', true);
        }
        throw new GoogleMeetException('Google Meet API error (HTTP '.$status.').', 'api');
    }
}
