<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRecording;
use App\Models\User;
use App\Services\Meetings\GoogleMeetException;
use App\Services\Meetings\GoogleMeetService;
use App\Services\Meetings\MeetingMinutesService;
use App\Services\Meetings\MeetingScheduler;
use App\Support\MeetingPresenter;
use App\Support\SuperAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Company meeting section.
 *
 * New meetings are real Google Meet meetings (Google Calendar API) booked into one of four internal
 * rooms. Meetings created before the Google integration (provider = jitsi) keep working through the
 * original in-app room, with mandatory microphone recording and AI minutes.
 */
class MeetingController extends Controller
{
    public function __construct(private GoogleMeetService $google, private MeetingScheduler $scheduler)
    {
    }

    // ---------------------------------------------------------------------
    // Lobby
    // ---------------------------------------------------------------------

    public function index(Request $request)
    {
        $me = Auth::user();

        $mine = fn ($q) => $q->where('host_user_id', $me->id)
            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $me->id));

        $live = Meeting::query()
            ->with(['host:id,name,avatar', 'participants' => fn ($q) => $q->where('is_invited', true)->with('user:id,name,avatar')])
            ->where('status', Meeting::STATUS_LIVE)
            ->where(fn ($q) => $q->where('host_user_id', $me->id)
                ->orWhereHas('participants', fn ($p) => $p->where('user_id', $me->id))
                ->orWhere('provider', Meeting::PROVIDER_JITSI)) // legacy rooms were visible to everyone
            ->withCount(['participants as in_room_count' => fn ($q) => $q
                ->whereNull('left_at')
                ->where('last_seen_at', '>=', now()->subSeconds(MeetingParticipant::STALE_AFTER_SECONDS))])
            ->orderByDesc('started_at')
            ->get();

        $upcoming = Meeting::query()
            ->with(['host:id,name,avatar', 'participants' => fn ($q) => $q->where('is_invited', true)->with('user:id,name,avatar')])
            ->withCount([
                'participants as accepted_count' => fn ($q) => $q->where('is_invited', true)->where('rsvp', 'accepted'),
                'participants as declined_count' => fn ($q) => $q->where('is_invited', true)->where('rsvp', 'declined'),
                'participants as pending_count' => fn ($q) => $q->where('is_invited', true)->where('rsvp', 'pending'),
            ])
            ->where('status', Meeting::STATUS_SCHEDULED)
            ->where(fn ($q) => $q->where('host_user_id', $me->id)
                ->orWhereHas('participants', fn ($p) => $p->where('user_id', $me->id)->where('rsvp', '!=', 'declined')))
            // Old requests on top: the request that has been waiting longest comes first, urgent ones win ties.
            ->orderBy('created_at')
            ->orderByRaw($this->priorityRankSql())
            ->limit(30)
            ->get();

        $recent = Meeting::query()
            ->with('host:id,name,avatar')
            ->where('status', Meeting::STATUS_ENDED)
            ->where($mine)
            ->orderByDesc('ended_at')
            ->limit(8)
            ->get();

        $users = User::query()->active()->orderBy('name')->get(['id', 'name', 'designation', 'avatar', 'email']);

        $invites = $this->pendingInvites();

        // All meetings: sortable by request date, priority, status or meeting time.
        $sort = (string) $request->query('sort', 'requested');
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $statusFilter = (string) $request->query('status', 'open');
        $priorityFilter = (string) $request->query('priority', '');
        $roomFilter = (int) $request->query('room', 0);

        $all = Meeting::query()
            ->visibleTo($me)
            ->with(['host:id,name,avatar', 'participants' => fn ($q) => $q->with('user:id,name,avatar')])
            ->when($statusFilter === 'open', fn ($q) => $q->whereIn('status', [Meeting::STATUS_SCHEDULED, Meeting::STATUS_LIVE]))
            ->when(in_array($statusFilter, ['scheduled', 'live', 'ended', 'cancelled'], true), fn ($q) => $q->where('status', $statusFilter))
            ->when(array_key_exists($priorityFilter, Meeting::PRIORITIES), fn ($q) => $q->where('priority', $priorityFilter))
            ->when(array_key_exists($roomFilter, Meeting::ROOMS), fn ($q) => $q->where('room_slot', $roomFilter));

        switch ($sort) {
            case 'priority':
                $all->orderByRaw($this->priorityRankSql().' '.$dir)->orderBy('created_at');
                break;
            case 'status':
                $all->orderByRaw("CASE status WHEN 'live' THEN 1 WHEN 'scheduled' THEN 2 WHEN 'ended' THEN 3 ELSE 4 END ".$dir)->orderBy('created_at');
                break;
            case 'meeting':
                $all->orderByRaw('scheduled_at is null')->orderBy('scheduled_at', $dir)->orderBy('created_at');
                break;
            default:
                $sort = 'requested';
                $all->orderBy('created_at', $dir)->orderByRaw($this->priorityRankSql());
        }
        $all->orderBy('id');
        $meetings = $all->paginate(15)->withQueryString();

        return view('meetings.index', [
            'live' => $live,
            'upcoming' => $upcoming,
            'recent' => $recent,
            'users' => $users,
            'me' => $me,
            'invites' => $invites,
            'meetings' => $meetings,
            'sort' => $sort,
            'dir' => $dir,
            'statusFilter' => $statusFilter,
            'priorityFilter' => $priorityFilter,
            'roomFilter' => $roomFilter,
            'google' => $this->google->status(),
            'canConnectGoogle' => SuperAdminAccess::isTaskAdmin($me),
            'googleReady' => $this->google->isConfigured(),
            'googleOauthReady' => $this->google->oauthClientConfigured(),
        ]);
    }

    /** SQL expression giving 1 (urgent) / 2 (immediate) / 3 (normal). */
    private function priorityRankSql(): string
    {
        return "CASE priority WHEN 'urgent' THEN 1 WHEN 'immediate' THEN 2 ELSE 3 END";
    }

    /** Shared validation for the lobby form and the floating widget. */
    private function validatedBooking(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'agenda' => 'nullable|string|max:5000',
            'priority' => 'required|in:'.implode(',', array_keys(Meeting::PRIORITIES)),
            'room_slot' => 'required|integer|in:'.implode(',', array_keys(Meeting::ROOMS)),
            'meeting_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'invitee_ids' => 'nullable|array|max:200',
            'invitee_ids.*' => 'integer|exists:users,id',
        ], [
            'room_slot.required' => 'Choose one of the four meeting rooms.',
            'room_slot.in' => 'Choose one of the four meeting rooms.',
            'priority.required' => 'Choose a priority: Urgent, Immediate or Normal.',
            'meeting_date.required' => 'Pick the meeting date.',
            'start_time.required' => 'Pick the start time.',
            'end_time.required' => 'Pick the end time.',
        ]);

        [$data['start'], $data['end']] = $this->scheduler->parseWindow($data['meeting_date'], $data['start_time'], $data['end_time']);

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->validatedBooking($request);

        try {
            $result = $this->scheduler->create($data, Auth::user());
        } catch (GoogleMeetException $e) {
            return back()->withInput()->with('google_config_error', $e->getMessage());
        }

        $meeting = $result['meeting'];
        $redirect = redirect()->route('meetings.show', $meeting);

        if (! $meeting->isGoogleMeet()) {
            return $redirect->with('success', 'Meeting scheduled in '.$meeting->roomLabel().' and the meeting-room link was created automatically. Members can join from Requests for you.');
        }

        return $result['google_error']
            ? $redirect->with('error', 'Meeting saved, but Google Meet is not ready: '.$result['google_error'])
            : $redirect->with('success', 'Meeting scheduled in '.$meeting->roomLabel().' and the Google Meet link was created. Invitations were sent by Google Calendar.');
    }

    // ---------------------------------------------------------------------
    // Floating "Request a meeting" widget (available on every page)
    // ---------------------------------------------------------------------

    /** Active team members for the member picker (loaded lazily when the widget opens). */
    public function members(): JsonResponse
    {
        $me = Auth::id();

        return response()->json([
            'members' => User::query()->active()->where('id', '!=', $me)->orderBy('name')
                ->get(['id', 'name', 'designation', 'avatar'])
                ->map(fn (User $u) => MeetingPresenter::userCard($u))
                ->values(),
            'google' => ['ok' => $this->google->isConfigured(), 'message' => $this->google->status()['message']],
            'rooms' => Meeting::ROOMS,
            'timezone' => \App\Casts\MeetingDateTime::timezone(),
        ]);
    }

    /** Busy slots of one room on a day, used for live conflict hints in the forms. */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_slot' => 'required|integer|in:'.implode(',', array_keys(Meeting::ROOMS)),
            'meeting_date' => 'required|date_format:Y-m-d',
            'exclude' => 'nullable|integer',
        ]);

        $tz = \App\Casts\MeetingDateTime::timezone();
        $dayStart = \Carbon\Carbon::parse($data['meeting_date'].' 00:00', $tz);
        $dayEnd = $dayStart->copy()->addDay();

        $busy = Meeting::query()
            ->where('room_slot', $data['room_slot'])->blockingRoom()
            ->whereNotNull('scheduled_at')->whereNotNull('scheduled_end_at')
            ->where('scheduled_at', '<', Meeting::dbTime($dayEnd))
            ->where('scheduled_end_at', '>', Meeting::dbTime($dayStart))
            ->when($data['exclude'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
            ->orderBy('scheduled_at')->get()
            ->map(fn (Meeting $m) => [
                'from' => $m->scheduled_at->format('h:i A'),
                'to' => $m->scheduled_end_at->format('h:i A'),
                'title' => (string) $m->title,
            ])->values();

        return response()->json(['room' => Meeting::ROOMS[(int) $data['room_slot']], 'busy' => $busy]);
    }

    public function requestStore(Request $request): JsonResponse
    {
        $request->merge(['invitee_ids' => $request->input('invitee_ids', [])]);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'agenda' => 'required|string|max:5000',
            'priority' => 'required|in:'.implode(',', array_keys(Meeting::PRIORITIES)),
            'room_slot' => 'required|integer|in:'.implode(',', array_keys(Meeting::ROOMS)),
            // Date and time are not asked: they are stamped automatically when the request is made.
            'approx_minutes' => 'required|integer|min:5|max:720',
            'invitee_ids' => 'required|array|min:1|max:200',
            'invitee_ids.*' => 'integer|exists:users,id',
        ], [
            'invitee_ids.required' => 'Add at least one member to the meeting.',
            'invitee_ids.min' => 'Add at least one member to the meeting.',
            'agenda.required' => 'Please add a meeting agenda.',
            'room_slot.required' => 'Choose one of the four meeting rooms.',
            'room_slot.in' => 'Choose one of the four meeting rooms.',
        ]);
        $data['start'] = now(\App\Casts\MeetingDateTime::timezone())->startOfMinute();
        $data['end'] = $data['start']->copy()->addMinutes((int) $data['approx_minutes']);

        try {
            $result = $this->scheduler->create($data, Auth::user());
        } catch (GoogleMeetException $e) {
            return response()->json(['ok' => false, 'config_error' => true, 'message' => $e->getMessage()], 422);
        }

        /** @var Meeting $meeting */
        $meeting = $result['meeting']->fresh();
        $p = $meeting->priorityMeta();

        return response()->json([
            'ok' => true,
            'google_error' => $result['google_error'],
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'priority' => $p['label'],
                'priority_emoji' => $p['emoji'],
                'room' => $meeting->roomLabel(),
                'scheduled_at' => $meeting->scheduled_at?->format('D, d M Y · h:i A').' – '.$meeting->scheduled_end_at?->format('h:i A').' '.\App\Casts\MeetingDateTime::timezone(),
                'meet_url' => $meeting->hasMeetLink() ? $meeting->google_meet_url : null,
                'is_google' => $meeting->isGoogleMeet(),
                // Google Meet link when Google is connected, otherwise the automatic in-app meeting-room link.
                'join_url' => $meeting->hasMeetLink()
                    ? $meeting->google_meet_url
                    : (! $meeting->isGoogleMeet() ? route('meetings.room', $meeting) : null),
                'invited' => $meeting->participants()->where('is_invited', true)->count(),
                'invitees' => $meeting->participants()->where('is_invited', true)->with('user:id,name')->get()
                    ->map(fn (MeetingParticipant $mp) => (string) ($mp->user?->name ?? 'Unknown'))->sort()->values(),
            ],
            'details_url' => route('meetings.show', $meeting),
            'meetings_url' => route('meetings.index'),
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, MeetingParticipant> */
    private function pendingInvites(bool $includeAccepted = false)
    {
        return MeetingParticipant::query()
            ->where('user_id', Auth::id())
            ->where('is_invited', true)
            ->whereIn('rsvp', $includeAccepted ? ['pending', 'accepted'] : ['pending'])
            ->whereNull('first_joined_at')
            ->whereHas('meeting', fn ($q) => $q->whereIn('status', [Meeting::STATUS_SCHEDULED, Meeting::STATUS_LIVE]))
            ->with('meeting.host:id,name,avatar')
            // Old requests on top: the request that has waited longest is listed first.
            ->orderBy(Meeting::query()->select('created_at')->whereColumn('meetings.id', 'meeting_participants.meeting_id'))
            ->orderBy('id')
            ->limit(20)
            ->get();
    }

    /** Pending meeting requests addressed to the current user. */
    public function requests(): JsonResponse
    {
        // Accepted-but-not-yet-joined invites stay listed so the user can still join; only unanswered ones count for the badge.
        $rows = $this->pendingInvites(true);

        return response()->json([
            'count' => $rows->where('rsvp', 'pending')->count(),
            'requests' => $rows->map(fn (MeetingParticipant $p) => [
                'id' => (int) $p->meeting_id,
                'rsvp' => (string) $p->rsvp,
                'title' => $p->meeting->title,
                'agenda' => $p->meeting->agenda,
                'host' => $p->meeting->host?->name,
                'live' => $p->meeting->isLive(),
                'scheduled_at' => $this->whenLabel($p->meeting),
                'priority' => $p->meeting->priorityMeta(),
                'room' => $p->meeting->roomLabel(),
                'requested_at' => $p->meeting->created_at?->diffForHumans(),
                'meet_url' => $p->meeting->hasMeetLink() ? $p->meeting->google_meet_url : null,
                'google' => $p->meeting->isGoogleMeet(),
                'details_url' => route('meetings.show', $p->meeting_id),
                'rsvp_url' => route('meetings.rsvp', $p->meeting_id),
                'room_url' => $p->meeting->isGoogleMeet() ? route('meetings.show', $p->meeting_id) : route('meetings.room', $p->meeting_id),
            ])->values(),
        ]);
    }

    /** Meetings I requested that have not ended, with every invited member and their answer. */
    public function sent(): JsonResponse
    {
        $rows = Meeting::query()
            ->where('host_user_id', Auth::id())
            ->whereIn('status', [Meeting::STATUS_SCHEDULED, Meeting::STATUS_LIVE])
            ->with(['participants' => fn ($q) => $q->where('is_invited', true)->with('user:id,name')])
            ->orderBy('created_at') // old requests on top
            ->orderByRaw($this->priorityRankSql())
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json([
            'meetings' => $rows->map(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'agenda' => $m->agenda,
                'live' => $m->isLive(),
                'scheduled_at' => $this->whenLabel($m),
                'priority' => $m->priorityMeta(),
                'room' => $m->roomLabel(),
                'meet_url' => $m->hasMeetLink() ? $m->google_meet_url : null,
                'google' => $m->isGoogleMeet(),
                'google_status' => $m->google_sync_status,
                'details_url' => route('meetings.show', $m),
                'room_url' => $m->isGoogleMeet() ? route('meetings.show', $m) : route('meetings.room', $m),
                'invitees' => $m->participants->map(fn (MeetingParticipant $p) => [
                    'name' => (string) ($p->user?->name ?? 'Unknown'),
                    'status' => $this->inviteeStatus($p),
                ])->sortBy('name')->values(),
            ])->values(),
        ]);
    }

    private function whenLabel(Meeting $m): ?string
    {
        if (! $m->scheduled_at) {
            return null;
        }
        $label = $m->scheduled_at->format('D, d M Y · h:i A');
        if ($m->scheduled_end_at) {
            $label .= ' – '.$m->scheduled_end_at->format('h:i A');
        }

        return $label.' '.$m->scheduled_at->format('T');
    }

    /** in_room | left | accepted | declined | pending */
    private function inviteeStatus(MeetingParticipant $p): string
    {
        if ($p->isInRoom()) {
            return 'in_room';
        }
        if ($p->first_joined_at) {
            return 'left';
        }

        return in_array($p->rsvp, ['accepted', 'declined'], true) ? $p->rsvp : 'pending';
    }

    public function rsvp(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate(['response' => 'required|in:accepted,declined']);

        if ($meeting->isEnded() || $meeting->isCancelled()) {
            return response()->json(['ok' => false, 'message' => $meeting->isCancelled() ? 'This meeting was cancelled.' : 'This meeting has already ended.'], 409);
        }

        $p = MeetingParticipant::where('meeting_id', $meeting->id)->where('user_id', Auth::id())->first();
        if (! $p) {
            return response()->json(['ok' => false, 'message' => 'You were not invited to this meeting.'], 403);
        }

        $p->forceFill(['rsvp' => $data['response'], 'rsvp_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'response' => $data['response'],
            'room_url' => $meeting->isGoogleMeet() ? route('meetings.show', $meeting) : route('meetings.room', $meeting),
            'live' => $meeting->isLive(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Room
    // ---------------------------------------------------------------------

    public function room(Meeting $meeting)
    {
        // Google Meet meetings have no in-app room: details + the Join Google Meet button live on the details page.
        if ($meeting->isGoogleMeet() || $meeting->isCancelled()) {
            return redirect()->route('meetings.show', $meeting);
        }

        if ($meeting->isEnded()) {
            return redirect()->route('meetings.minutes.show', $meeting)
                ->with('success', 'This meeting has ended. Minutes are below once they are ready.');
        }

        $me = Auth::user();

        return view('meetings.room', [
            'meeting' => $meeting->load('host:id,name'),
            'me' => $me,
            'isHost' => $this->canManage($meeting, $me),
            'jitsiDomain' => (string) config('services.meetings.jitsi_domain', 'meet.jit.si'),
        ]);
    }

    /**
     * Video server settings for one participant.
     *
     * With JaaS (8x8) credentials configured, every participant gets a signed JWT, so nobody is ever
     * asked to log in (the host is the moderator). Without them it falls back to the plain Jitsi
     * domain, where meet.jit.si makes the first person log in as moderator.
     *
     * @return array{domain: string, room_name: string, script_url: string, jwt: ?string}
     */
    private function videoConfig(Meeting $meeting, User $me): array
    {
        $domain = (string) config('services.meetings.jitsi_domain', 'meet.jit.si');
        $appId = (string) config('services.meetings.jaas.app_id');
        $kid = (string) config('services.meetings.jaas.key_id');
        $key = (string) config('services.meetings.jaas.private_key');

        if ($appId === '' || $kid === '' || $key === '') {
            return ['domain' => $domain, 'room_name' => $meeting->room_name, 'script_url' => 'https://'.$domain.'/external_api.js', 'jwt' => null];
        }

        if (! str_contains($key, 'BEGIN') && is_file($key)) {
            $key = (string) file_get_contents($key); // value was a path to the .pk file
        }
        $key = str_replace('\n', "\n", $key);

        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $signing = $b64(json_encode(['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT'])).'.'.$b64(json_encode([
            'aud' => 'jitsi',
            'iss' => 'chat',
            'iat' => $now,
            'nbf' => $now - 10,
            'exp' => $now + 6 * 3600,
            'sub' => $appId,
            'room' => '*',
            'context' => [
                'user' => [
                    'id' => (string) $me->id,
                    'name' => (string) $me->name,
                    'email' => (string) $me->email,
                    'moderator' => $this->canManage($meeting, $me) ? 'true' : 'false',
                ],
                'features' => ['recording' => 'false', 'livestreaming' => 'false', 'transcription' => 'false', 'outbound-call' => 'false'],
            ],
        ]));

        $signature = '';
        if (! openssl_sign($signing, $signature, $key, OPENSSL_ALGO_SHA256)) {
            report(new \RuntimeException('Could not sign the JaaS meeting token; check MEETING_JAAS_PRIVATE_KEY.'));

            return ['domain' => $domain, 'room_name' => $meeting->room_name, 'script_url' => 'https://'.$domain.'/external_api.js', 'jwt' => null];
        }

        return [
            'domain' => '8x8.vc',
            'room_name' => $appId.'/'.$meeting->room_name,
            'script_url' => 'https://8x8.vc/'.$appId.'/external_api.js',
            'jwt' => $signing.'.'.$b64($signature),
        ];
    }

    public function join(Meeting $meeting): JsonResponse
    {
        if ($meeting->isGoogleMeet()) {
            return response()->json(['ok' => false, 'message' => 'Use the Join Google Meet button on the meeting page.'], 409);
        }
        if ($meeting->isEnded()) {
            return response()->json(['ok' => false, 'message' => 'This meeting has already ended.'], 409);
        }

        $me = Auth::user();
        $now = now();

        DB::transaction(function () use ($meeting, $me, $now) {
            $p = MeetingParticipant::firstOrNew(['meeting_id' => $meeting->id, 'user_id' => $me->id]);
            $p->first_joined_at ??= $now;
            $p->joined_at = $now;
            $p->left_at = null;
            $p->last_seen_at = $now;
            $p->recording_state = 'none';
            $p->rsvp = 'accepted'; // walking into the room is an acceptance
            $p->rsvp_at ??= $now;
            $p->save();

            if ($meeting->status === Meeting::STATUS_SCHEDULED) {
                $meeting->forceFill(['status' => Meeting::STATUS_LIVE, 'started_at' => $now])->save();
            }
        });

        $video = $this->videoConfig($meeting, $me);

        return response()->json([
            'ok' => true,
            'session' => Str::random(32),
            'server_time_ms' => (int) round(microtime(true) * 1000),
            'room_name' => $video['room_name'],
            'domain' => $video['domain'],
            'script_url' => $video['script_url'],
            'jwt' => $video['jwt'],
            'display_name' => (string) $me->name,
            'email' => (string) $me->email,
        ]);
    }

    public function heartbeat(Request $request, Meeting $meeting): JsonResponse
    {
        $me = Auth::user();
        $recording = $request->boolean('recording');

        $p = MeetingParticipant::where('meeting_id', $meeting->id)->where('user_id', $me->id)->first();
        if ($p) {
            $p->last_seen_at = now();
            if ($p->left_at !== null && ! $meeting->isEnded()) {
                $p->left_at = null; // swept as stale during a network blip; they are clearly still here
            }
            if (! $recording) {
                $p->recording_gaps = (int) $p->recording_gaps + 1;
                $p->recording_state = 'stopped';
            }
            $p->save();
        }

        return response()->json([
            'status' => $meeting->fresh()->status,
            'participants' => $this->roomParticipants($meeting),
        ]);
    }

    /**
     * Receives sequential audio chunks of one browser recording session and appends them to a
     * single file. Out-of-order chunks are refused with the sequence number the server expects.
     */
    public function recording(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate([
            'session' => 'required|string|min:16|max:64',
            'seq' => 'required|integer|min:0',
            'chunk' => 'required|file|max:8192',
            'mime' => 'nullable|string|max:64',
            'started_at_ms' => 'nullable|integer|min:0',
        ]);

        $me = Auth::user();

        if ($meeting->minutes_status !== Meeting::MINUTES_PENDING) {
            return response()->json(['ok' => false, 'message' => 'Recording is closed for this meeting.'], 409);
        }

        $participant = MeetingParticipant::where('meeting_id', $meeting->id)->where('user_id', $me->id)->first();
        if (! $participant) {
            return response()->json(['ok' => false, 'message' => 'You have not joined this meeting.'], 403);
        }

        $disk = Storage::disk(config('services.meetings.disk', 'local'));
        $seq = (int) $data['seq'];

        $result = DB::transaction(function () use ($data, $meeting, $me, $participant, $disk, $request, $seq) {
            $rec = MeetingRecording::query()
                ->where('meeting_id', $meeting->id)
                ->where('user_id', $me->id)
                ->where('session_token', $data['session'])
                ->lockForUpdate()
                ->first();

            if (! $rec) {
                if ($seq !== 0) {
                    return ['conflict' => true, 'expected' => 0];
                }
                $mime = (string) ($data['mime'] ?? 'audio/webm');
                $ext = str_contains($mime, 'ogg') ? 'ogg' : (str_contains($mime, 'mp4') ? 'm4a' : 'webm');
                $startedMs = (int) ($data['started_at_ms'] ?? 0);
                $rec = MeetingRecording::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $me->id,
                    'session_token' => $data['session'],
                    'path' => 'meetings/'.$meeting->id.'/rec-'.$me->id.'-'.substr(sha1($data['session']), 0, 12).'.'.$ext,
                    'mime' => $mime,
                    'started_at' => $startedMs > 0 ? \Carbon\Carbon::createFromTimestampMs($startedMs) : now(),
                ]);
            }

            if ($seq < $rec->chunks_received) {
                return ['conflict' => false, 'received' => $rec->chunks_received]; // duplicate retry
            }
            if ($seq > $rec->chunks_received) {
                return ['conflict' => true, 'expected' => $rec->chunks_received];
            }

            $absolute = $disk->path($rec->path);
            File::ensureDirectoryExists(dirname($absolute));
            $bytes = $request->file('chunk')->get();
            if (file_put_contents($absolute, $bytes, FILE_APPEND | LOCK_EX) === false) {
                throw new \RuntimeException('Could not write recording chunk.');
            }

            $rec->chunks_received = $seq + 1;
            $rec->size_bytes = (int) $rec->size_bytes + strlen($bytes);
            $rec->last_chunk_at = now();
            $rec->save();

            // A chunk that arrives after the person left (final flush) must not make them look present again.
            if ($participant->left_at === null) {
                $participant->forceFill(['recording_state' => 'recording', 'last_seen_at' => now()])->save();
            }

            return ['conflict' => false, 'received' => $rec->chunks_received];
        });

        if ($result['conflict'] ?? false) {
            return response()->json(['ok' => false, 'expected' => $result['expected']], 409);
        }

        return response()->json(['ok' => true, 'received' => $result['received']]);
    }

    public function leave(Request $request, Meeting $meeting): JsonResponse
    {
        $me = Auth::user();

        $p = MeetingParticipant::where('meeting_id', $meeting->id)->where('user_id', $me->id)->first();
        if ($p) {
            $p->forceFill(['left_at' => now(), 'recording_state' => 'stopped'])->save();
        }

        $session = (string) $request->input('session', '');
        if ($session !== '') {
            $rec = MeetingRecording::where('meeting_id', $meeting->id)
                ->where('user_id', $me->id)
                ->where('session_token', $session)
                ->first();
            if ($rec && $rec->finalized_at === null) {
                $rec->finalized_at = now();
                $rec->duration_seconds = $rec->started_at && $rec->last_chunk_at
                    ? max(0, (int) $rec->started_at->diffInSeconds($rec->last_chunk_at))
                    : null;
                $rec->save();
            }
        }

        return response()->json(['ok' => true, 'minutes_url' => route('meetings.minutes.show', $meeting)]);
    }

    public function end(Meeting $meeting): JsonResponse
    {
        if (! $this->canManage($meeting, Auth::user())) {
            return response()->json(['ok' => false, 'message' => 'Only the host can end the meeting for everyone.'], 403);
        }

        if (! $meeting->isEnded()) {
            $meeting->forceFill(['status' => Meeting::STATUS_ENDED, 'ended_at' => now()])->save();
        }

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------------
    // Minutes pages
    // ---------------------------------------------------------------------

    public function minutesIndex(Request $request)
    {
        $me = Auth::user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $query = Meeting::query()
            ->with('host:id,name')
            ->withCount(['participants as attended_count' => fn ($p) => $p->whereNotNull('joined_at')])
            ->withCount('actionItems')
            ->where('status', Meeting::STATUS_ENDED);

        if (! SuperAdminAccess::isTaskAdmin($me)) {
            $query->where(function ($w) use ($me) {
                $w->where('host_user_id', $me->id)
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', $me->id));
            });
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', '%'.$q.'%')->orWhere('summary', 'like', '%'.$q.'%');
            });
        }
        if (in_array($status, ['pending', 'processing', 'ready', 'failed', 'no_recording'], true)) {
            $query->where('minutes_status', $status);
        }

        $meetings = $query->orderByDesc('ended_at')->paginate(20)->withQueryString();

        return view('meetings.minutes-index', compact('meetings', 'q', 'status'));
    }

    public function minutesShow(Meeting $meeting)
    {
        $me = Auth::user();
        abort_unless($this->canView($meeting, $me), 403);

        if ($meeting->isGoogleMeet()) {
            return redirect()->to(route('meetings.show', $meeting).'#tab-mom');
        }

        $meeting->load([
            'host:id,name,email',
            'participants.user:id,name,designation',
            'recordings.user:id,name',
            'actionItems.assignee:id,name',
        ]);

        $tasks = \App\Models\Task::query()
            ->whereIn('id', $meeting->actionItems->pluck('task_id')->filter()->all())
            ->get(['id', 'status'])
            ->keyBy('id');

        return view('meetings.minutes-show', [
            'meeting' => $meeting,
            'tasks' => $tasks,
            'canManage' => $this->canManage($meeting, $me),
        ]);
    }

    public function regenerate(Meeting $meeting)
    {
        abort_unless($this->canManage($meeting, Auth::user()), 403);

        if (! $meeting->isEnded()) {
            return back()->with('error', 'End the meeting before generating minutes.');
        }
        if ($meeting->minutes_status === Meeting::MINUTES_PROCESSING) {
            return back()->with('error', 'Minutes are already being generated.');
        }

        $meeting->forceFill(['minutes_status' => Meeting::MINUTES_PENDING, 'minutes_error' => null])->save();
        $id = $meeting->id;

        dispatch(function () use ($id) {
            $m = Meeting::find($id);
            if ($m) {
                app(MeetingMinutesService::class)->process($m, true);
            }
        })->afterResponse();

        return back()->with('success', 'Generating minutes. This page refreshes automatically.');
    }

    /**
     * Permanently delete a meeting with its recordings (files too), transcript, minutes and action
     * items. Tasks already created from it are kept: people may be working on them.
     */
    public function destroy(Meeting $meeting)
    {
        abort_unless($this->canManage($meeting, Auth::user()), 403);

        $inRoom = $meeting->participants()->whereNotNull('joined_at')->whereNull('left_at')
            ->where('last_seen_at', '>=', now()->subSeconds(MeetingParticipant::STALE_AFTER_SECONDS))->exists();
        if ($meeting->isLive() && $inRoom) {
            return back()->with('error', 'Someone is still in this meeting. End the meeting first, then delete it.');
        }
        if ($meeting->minutes_status === Meeting::MINUTES_PROCESSING) {
            return back()->with('error', 'The AI is writing the minutes right now. Try again in a few minutes.');
        }

        // A Google Meet meeting must disappear from Google Calendar first, otherwise guests keep a dead invitation.
        if ($meeting->isGoogleMeet() && ! $meeting->isCancelled() && ($meeting->google_event_id || $meeting->google_request_id)) {
            try {
                $this->google->cancelMeeting($meeting);
            } catch (GoogleMeetException $e) {
                return back()->with('error', 'The meeting was not deleted because its Google Calendar event could not be removed: '.$e->getMessage());
            }
        }

        $disk = Storage::disk(config('services.meetings.disk', 'local'));
        $id = $meeting->id;
        $title = $meeting->title;

        DB::transaction(function () use ($meeting, $disk) {
            foreach ($meeting->recordings()->get(['id', 'path']) as $rec) {
                if ($rec->path && $disk->exists($rec->path)) {
                    $disk->delete($rec->path);
                }
            }
            foreach ($meeting->externalRecordings()->get(['id', 'path']) as $rec) {
                if ($rec->path && $disk->exists($rec->path)) {
                    $disk->delete($rec->path);
                }
            }
            $meeting->externalRecordings()->delete();
            $meeting->mom()->delete();
            $meeting->recordings()->delete();
            $meeting->actionItems()->delete();
            $meeting->participants()->delete();
            $meeting->delete();
        });
        $disk->deleteDirectory('meetings/'.$id);

        \Log::info('Meeting deleted', ['meeting_id' => $id, 'title' => $title, 'by_user_id' => Auth::id()]);

        return redirect()->route('meetings.minutes.index')->with('success', 'Meeting "'.$title.'" and its recordings were deleted.');
    }

    public function recordingFile(Meeting $meeting, MeetingRecording $recording)
    {
        abort_unless($this->canView($meeting, Auth::user()), 403);
        abort_unless((int) $recording->meeting_id === (int) $meeting->id && $recording->path, 404);

        $disk = Storage::disk(config('services.meetings.disk', 'local'));
        abort_unless($disk->exists($recording->path), 404);

        return response()->file($disk->path($recording->path), [
            'Content-Type' => $recording->mime ?: 'audio/webm',
            'Cache-Control' => 'private, max-age=0',
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function canView(Meeting $meeting, ?User $user): bool
    {
        return $meeting->canBeViewedBy($user);
    }

    private function canManage(Meeting $meeting, ?User $user): bool
    {
        return $meeting->canBeManagedBy($user);
    }

    /**
     * @return list<array{name: string, in_room: bool, recording: bool}>
     */
    private function roomParticipants(Meeting $meeting): array
    {
        return $meeting->participants()
            ->with('user:id,name')
            ->whereNotNull('joined_at')
            ->get()
            ->map(fn (MeetingParticipant $p) => [
                'name' => (string) ($p->user?->name ?? 'Unknown'),
                'in_room' => $p->isInRoom(),
                'recording' => $p->isInRoom() && $p->recording_state === 'recording',
            ])
            ->sortByDesc('in_room')
            ->values()
            ->all();
    }
}
