<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRecording;
use App\Models\MeetingReschedule;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Company meeting section.
 *
 * Every meeting is a real Google Meet meeting (Google Calendar API). Meetings created before the Google
 * integration keep their saved minutes and recordings; unfinished ones are moved onto Google Meet when opened.
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
            // Everyone including the logged-in user ("You"), so a person can add themselves to the meeting too.
            'members' => User::query()->active()->orderBy('name')
                ->get(['id', 'name', 'designation', 'avatar'])
                ->map(fn (User $u) => MeetingPresenter::userCard($u) + ['is_me' => (int) $u->id === (int) $me])
                ->values(),
            'google' => $this->googleStatusPayload(),
            'rooms' => Meeting::ROOMS,
            'timezone' => \App\Casts\MeetingDateTime::timezone(),
        ]);
    }

    /** Live Google connection state for the floating widget (re-checked whenever it opens). */
    public function googleStatus(): JsonResponse
    {
        return response()->json($this->googleStatusPayload());
    }

    private function googleStatusPayload(): array
    {
        $s = $this->google->status();

        return [
            'ok' => (bool) $s['ok'],
            'email' => $s['email'] ?? null,
            'message' => $s['message'],
            'oauth_configured' => $this->google->oauthClientConfigured(),
            'can_connect' => SuperAdminAccess::isTaskAdmin(Auth::user()),
            'connect_url' => route('meetings.google.connect'),
            'redirect_uri' => (string) (config('services.meetings.google.redirect') ?: route('meetings.google.callback')),
            'meetings_url' => route('meetings.index'),
        ];
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

    private function draftLinkKey(string $token): string
    {
        return 'meetings.draft-link.'.Auth::id().'.'.$token;
    }

    /** "Create New Google Meeting" button: makes a fresh Google Meet link and returns it. */
    public function createLink(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => 'nullable|string|max:255']);

        try {
            $link = $this->google->createStandaloneMeetLink(trim((string) ($data['title'] ?? '')) ?: 'New Google Meet');
        } catch (GoogleMeetException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        // Remembered for the creator only, so the saved meeting can adopt this exact link (and nobody can attach someone else's).
        Cache::put($this->draftLinkKey($link['request_id']), $link['meet_url'], now()->addHours(6));

        return response()->json(['ok' => true, 'meet_url' => $link['meet_url'], 'link_token' => $link['request_id']]);
    }

    public function requestStore(Request $request): JsonResponse
    {
        $request->merge(['invitee_ids' => $request->input('invitee_ids', [])]);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'agenda' => 'nullable|string|max:5000',
            'priority' => 'required|in:'.implode(',', array_keys(Meeting::PRIORITIES)),
            // No room is chosen any more: the Google Meet link is the meeting place.
            'room_slot' => 'nullable|integer|in:'.implode(',', array_keys(Meeting::ROOMS)),
            // Token of a link made with "Create New Google Meeting"; the meeting then uses that exact link.
            'link_token' => 'nullable|string|regex:/^[a-f0-9]{32}$/',
            // Link pasted from https://meet.google.com/new (used when Google Calendar is not connected).
            'meet_url' => ['nullable', 'string', 'max:200', 'regex:#^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}(\?.*)?$#'],
            // Date and time are not asked: they are stamped automatically when the request is made.
            'approx_minutes' => 'required|integer|min:5|max:720',
            'recurrence' => 'nullable|in:'.implode(',', array_keys(Meeting::RECURRENCES)),
            'invitee_ids' => 'required|array|min:1|max:200',
            'invitee_ids.*' => 'integer|exists:users,id',
        ], [
            'invitee_ids.required' => 'Add at least one member to the meeting.',
            'invitee_ids.min' => 'Add at least one member to the meeting.',
        ]);

        $linkCacheKey = null;
        if (! empty($data['link_token'])) {
            $linkCacheKey = $this->draftLinkKey($data['link_token']);
            $url = Cache::get($linkCacheKey);
            if (is_string($url) && $url !== '') {
                $data['google_request_id'] = $data['link_token'];
                $data['google_meet_url'] = $url;
            }
        }
        unset($data['link_token']);
        if (! empty($data['meet_url'])) {
            $data['manual_meet_url'] = strtok((string) $data['meet_url'], '?');
        }
        unset($data['meet_url']);

        $data['start'] = now(\App\Casts\MeetingDateTime::timezone())->startOfMinute();
        $data['end'] = $data['start']->copy()->addMinutes((int) $data['approx_minutes']);

        try {
            $result = $this->scheduler->create($data, Auth::user());
        } catch (GoogleMeetException $e) {
            return response()->json(['ok' => false, 'config_error' => true, 'message' => $e->getMessage()], 422);
        }

        if ($linkCacheKey) {
            Cache::forget($linkCacheKey);
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
        $today = $this->todayYmd();
        // "RS" moved requests sit below the divider (soonest day first); they come back to the top when their day arrives.
        $rows = $this->pendingInvites(true)
            ->sortBy(fn (MeetingParticipant $p) => $this->isPostponed($p, $today) ? '1'.$p->postponed_until->format('Y-m-d') : '0')
            ->values();

        $reschedulePending = 0;
        try {
            if (Schema::hasTable('meeting_reschedules')) {
                $reschedulePending = $this->incomingReschedules(Auth::user())->count();
            }
        } catch (\Throwable $e) {
            $reschedulePending = 0;
        }

        return response()->json([
            'count' => $rows->filter(fn (MeetingParticipant $p) => $p->rsvp === 'pending' && ! $this->isPostponed($p, $today))->count(),
            'reschedule_count' => $reschedulePending,
            'requests' => $rows->map(fn (MeetingParticipant $p) => [
                'postponed_until' => $this->isPostponed($p, $today) ? $p->postponed_until->format('Y-m-d') : null,
                'postponed_label' => $this->isPostponed($p, $today) ? $p->postponed_until->format('D, d M Y') : null,
                'postpone_url' => route('meetings.postpone', $p->meeting_id),
                'recurrence_label' => $p->meeting->recurrenceLabel(),
                'reschedule_url' => route('meetings.reschedule', $p->meeting_id),
                'can_manage' => $this->canManage($p->meeting, Auth::user()),
                'scheduled_date' => $p->meeting->scheduled_at?->format('Y-m-d'),
                'scheduled_time' => $p->meeting->scheduled_at?->format('H:i'),
                'duration_minutes' => $p->meeting->scheduled_at && $p->meeting->scheduled_end_at ? (int) max(5, $p->meeting->scheduled_at->diffInMinutes($p->meeting->scheduled_end_at)) : 30,
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

    /** "Stop repeating": no further requests are generated for this meeting's series (existing meetings stay). */
    public function stopRecurring(Meeting $meeting): JsonResponse
    {
        if (! $this->canManage($meeting, Auth::user())) {
            return response()->json(['ok' => false, 'message' => 'Only the host can stop a repeating meeting.'], 403);
        }
        if (Meeting::supportsRecurrence()) {
            Meeting::query()
                ->where(fn ($q) => $q->where('id', $meeting->id)->orWhere(fn ($w) => $w->whereNotNull('series_id')->where('series_id', $meeting->series_id)))
                ->update(['recurrence' => 'once']);
        }

        return response()->json(['ok' => true]);
    }

    private function todayYmd(): string
    {
        return now(\App\Casts\MeetingDateTime::timezone())->toDateString();
    }

    private function isPostponed(MeetingParticipant $p, string $today): bool
    {
        return $p->postponed_until !== null && $p->postponed_until->format('Y-m-d') > $today;
    }

    /**
     * "RS": move MY copy of a meeting request to the bottom ("Scheduled" section) until a later day.
     * tomorrow | 7 | 15 | month, or "now" to bring it back to the top. Other people's lists and the meeting time are untouched.
     */
    public function postpone(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate(['option' => 'required|in:tomorrow,7,15,month,now']);

        if ($meeting->isEnded() || $meeting->isCancelled()) {
            return response()->json(['ok' => false, 'message' => $meeting->isCancelled() ? 'This meeting was cancelled.' : 'This meeting has already ended.'], 409);
        }
        if (! Schema::hasColumn('meeting_participants', 'postponed_until')) {
            return response()->json(['ok' => false, 'message' => 'Run "php artisan migrate" once to enable this option.'], 503);
        }

        $p = MeetingParticipant::where('meeting_id', $meeting->id)->where('user_id', Auth::id())->where('is_invited', true)->first();
        if (! $p) {
            return response()->json(['ok' => false, 'message' => 'You were not invited to this meeting.'], 403);
        }

        $today = now(\App\Casts\MeetingDateTime::timezone())->startOfDay();
        $until = match ($data['option']) {
            'tomorrow' => $today->copy()->addDay(),
            '7' => $today->copy()->addDays(7),
            '15' => $today->copy()->addDays(15),
            'month' => $today->copy()->addMonthNoOverflow(),
            default => null,
        };

        $p->forceFill(['postponed_until' => $until?->toDateString()])->save();

        return response()->json([
            'ok' => true,
            'postponed_until' => $until?->toDateString(),
            'label' => $until?->format('D, d M Y'),
        ]);
    }

    /** Meetings I requested that have not ended, with every invited member and their answer. */
    public function sent(Request $request): JsonResponse
    {
        $completedView = $request->query('filter') === 'completed';

        // "Completed" = the meeting has ended, or every invited member marked their request as completed.
        $allCompleted = fn ($q) => $q
            ->whereHas('participants', fn ($p) => $p->where('is_invited', true))
            ->whereDoesntHave('participants', fn ($p) => $p->where('is_invited', true)->where('rsvp', '!=', 'completed'));

        $query = Meeting::query()
            ->where('host_user_id', Auth::id())
            ->with(['participants' => fn ($q) => $q->where('is_invited', true)->with('user:id,name')]);

        if ($completedView) {
            $query->where('status', '!=', Meeting::STATUS_CANCELLED)
                ->where(fn ($q) => $q->where('status', Meeting::STATUS_ENDED)->orWhere(fn ($w) => $allCompleted($w)))
                ->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->whereIn('status', [Meeting::STATUS_SCHEDULED, Meeting::STATUS_LIVE])
                ->whereNot(fn ($q) => $allCompleted($q))
                ->orderBy('created_at') // old requests on top
                ->orderByRaw($this->priorityRankSql())
                ->orderBy('id');
        }

        $completedCount = Meeting::query()
            ->where('host_user_id', Auth::id())
            ->where('status', '!=', Meeting::STATUS_CANCELLED)
            ->where(fn ($q) => $q->where('status', Meeting::STATUS_ENDED)->orWhere(fn ($w) => $allCompleted($w)))
            ->count();

        $rows = $query->limit(20)->get();

        return response()->json([
            'completed_count' => $completedCount,
            'meetings' => $rows->map(fn (Meeting $m) => [
                'id' => $m->id,
                'ended' => $m->isEnded(),
                'title' => $m->title,
                'agenda' => $m->agenda,
                'live' => $m->isLive(),
                'scheduled_at' => $this->whenLabel($m),
                'priority' => $m->priorityMeta(),
                'room' => $m->roomLabel(),
                'meet_url' => $m->hasMeetLink() ? $m->google_meet_url : null,
                'google' => $m->isGoogleMeet(),
                'google_status' => $m->google_sync_status,
                'recurrence' => $m->isRecurring() ? $m->recurrence : null,
                'recurrence_label' => $m->recurrenceLabel(),
                'stop_recurring_url' => $m->isRecurring() ? route('meetings.recurrence.stop', $m) : null,
                'details_url' => route('meetings.show', $m),
                'room_url' => $m->isGoogleMeet() ? route('meetings.show', $m) : route('meetings.room', $m),
                'end_url' => route('meetings.end', $m),
                'reschedule_url' => route('meetings.reschedule', $m),
                'can_manage' => true,
                'scheduled_date' => $m->scheduled_at?->format('Y-m-d'),
                'scheduled_time' => $m->scheduled_at?->format('H:i'),
                'duration_minutes' => $m->scheduled_at && $m->scheduled_end_at ? (int) max(5, $m->scheduled_at->diffInMinutes($m->scheduled_end_at)) : 30,
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

        return in_array($p->rsvp, ['accepted', 'declined', 'completed'], true) ? $p->rsvp : 'pending';
    }

    public function rsvp(Request $request, Meeting $meeting): JsonResponse
    {
        // 'completed' = the invited member marks this meeting request as done; it leaves their "Requests for you" list.
        $data = $request->validate(['response' => 'required|in:accepted,declined,completed']);

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

        // An unfinished legacy (pre-Google) meeting is moved onto Google Meet and opened on its details page,
        // where the "Join Google Meet" button is.
        try {
            $error = $this->scheduler->convertToGoogleMeet($meeting);
        } catch (GoogleMeetException $e) {
            return redirect()->route('meetings.index')->with('error', $e->getMessage());
        }

        return redirect()->route('meetings.show', $meeting)->with(
            $error ? 'error' : 'success',
            $error ? 'Moved to Google Meet, but the link is not ready: '.$error : 'This meeting now uses Google Meet. Use the Join Google Meet button.'
        );
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

    /**
     * Move a meeting to a new date/time (also one already joined / completed).
     *  - the host (or a task admin) moves it straight away;
     *  - an invited member sends a reschedule request that the host approves or declines in the "Reschedule" tab.
     */
    public function reschedule(Request $request, Meeting $meeting): JsonResponse
    {
        $me = Auth::user();
        $manage = $this->canManage($meeting, $me);
        $invited = ! $manage && MeetingParticipant::where('meeting_id', $meeting->id)->where('user_id', $me?->id)->where('is_invited', true)->exists();
        if (! $manage && ! $invited) {
            return response()->json(['ok' => false, 'message' => 'Only the host or an invited member can reschedule this meeting.'], 403);
        }
        if ($meeting->isCancelled()) {
            return response()->json(['ok' => false, 'message' => 'A cancelled meeting cannot be rescheduled.'], 409);
        }

        $data = $request->validate([
            'meeting_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'approx_minutes' => 'required|integer|min:5|max:720',
            'reason' => 'nullable|string|max:500',
        ]);

        $tz = \App\Casts\MeetingDateTime::timezone();
        $start = \Carbon\Carbon::parse($data['meeting_date'].' '.$data['start_time'], $tz);
        if ($start->lt(now($tz)->subMinutes(5))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['start_time' => 'Pick a date and time in the future.']);
        }
        $end = $start->copy()->addMinutes((int) $data['approx_minutes']);
        $reason = trim((string) ($data['reason'] ?? '')) ?: null;
        $hasLog = Schema::hasTable('meeting_reschedules');

        if (! $manage) {
            if (! $hasLog) {
                return response()->json(['ok' => false, 'message' => 'Reschedule requests are not set up yet. Run "php artisan migrate" on the server.'], 503);
            }
            // One open request per person per meeting: a new one replaces the old.
            MeetingReschedule::where('meeting_id', $meeting->id)->where('requested_by', $me->id)->where('status', MeetingReschedule::PENDING)->delete();
            MeetingReschedule::create([
                'meeting_id' => $meeting->id, 'requested_by' => $me->id,
                'old_start' => $meeting->scheduled_at, 'old_end' => $meeting->scheduled_end_at,
                'new_start' => $start, 'new_end' => $end, 'reason' => $reason, 'status' => MeetingReschedule::PENDING,
            ]);

            return response()->json([
                'ok' => true,
                'applied' => false,
                'message' => 'Reschedule request sent to '.($meeting->host?->name ?: 'the host').'. It stays in the Reschedule tab until they decide.',
            ]);
        }

        $old = [$meeting->scheduled_at, $meeting->scheduled_end_at];
        $result = $this->scheduler->reschedule($meeting, $start, $end);
        $m = $result['meeting']->fresh();

        if ($hasLog) {
            // The host's own move is logged as an already-approved reschedule (and any open requests are settled).
            MeetingReschedule::where('meeting_id', $meeting->id)->where('status', MeetingReschedule::PENDING)
                ->update(['status' => MeetingReschedule::APPROVED, 'decided_by' => $me->id, 'decided_at' => now()]);
            MeetingReschedule::create([
                'meeting_id' => $meeting->id, 'requested_by' => $me->id,
                'old_start' => $old[0], 'old_end' => $old[1], 'new_start' => $start, 'new_end' => $end,
                'reason' => $reason, 'status' => MeetingReschedule::APPROVED, 'decided_by' => $me->id, 'decided_at' => now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'applied' => true,
            'google_error' => $result['google_error'],
            'scheduled_at' => $this->whenLabel($m),
        ]);
    }

    /** Reschedule tab: requests waiting for my decision, and the history of reschedules I am part of. */
    public function reschedules(): JsonResponse
    {
        if (! Schema::hasTable('meeting_reschedules')) {
            return response()->json(['ok' => true, 'incoming' => [], 'history' => [], 'count' => 0, 'setup_needed' => true]);
        }
        $me = Auth::user();
        $isAdmin = SuperAdminAccess::isTaskAdmin($me);

        $incoming = $this->incomingReschedules($me)->get();

        $history = MeetingReschedule::query()
            ->with(['meeting:id,title,host_user_id', 'requester:id,name', 'decider:id,name'])
            ->where(function ($q) use ($me, $isAdmin) {
                if ($isAdmin) {
                    return;
                }
                $q->where('requested_by', $me->id)
                    ->orWhereHas('meeting', fn ($m) => $m->where('host_user_id', $me->id)
                        ->orWhereHas('participants', fn ($p) => $p->where('user_id', $me->id)));
            })
            ->whereNotIn('id', $incoming->pluck('id'))
            ->orderByDesc('id')->limit(30)->get();

        return response()->json([
            'ok' => true,
            'count' => $incoming->count(),
            'incoming' => $incoming->map(fn ($r) => $this->rescheduleRow($r, $me))->values(),
            'history' => $history->map(fn ($r) => $this->rescheduleRow($r, $me))->values(),
        ]);
    }

    /** Host approves (the meeting is moved) or declines a reschedule request. */
    public function rescheduleDecide(Request $request, MeetingReschedule $reschedule): JsonResponse
    {
        $decision = $request->validate(['decision' => 'required|in:approve,decline'])['decision'];
        $me = Auth::user();
        $meeting = $reschedule->meeting;
        if (! $meeting || ! $this->canManage($meeting, $me)) {
            return response()->json(['ok' => false, 'message' => 'Only the host can decide on this request.'], 403);
        }
        if ($reschedule->status !== MeetingReschedule::PENDING) {
            return response()->json(['ok' => false, 'message' => 'This request was already '.$reschedule->status.'.'], 409);
        }

        if ($decision === 'decline') {
            $reschedule->forceFill(['status' => MeetingReschedule::DECLINED, 'decided_by' => $me->id, 'decided_at' => now()])->save();

            return response()->json(['ok' => true, 'message' => 'Request declined. The meeting keeps its time.']);
        }

        if ($meeting->isCancelled()) {
            return response()->json(['ok' => false, 'message' => 'This meeting was cancelled.'], 409);
        }
        if ($reschedule->new_start->lt(now(\App\Casts\MeetingDateTime::timezone())->subMinutes(5))) {
            return response()->json(['ok' => false, 'message' => 'The requested time has already passed. Decline it and ask for a new time, or reschedule from the Sent by you tab.'], 422);
        }

        $reschedule->forceFill(['old_start' => $meeting->scheduled_at, 'old_end' => $meeting->scheduled_end_at])->save();
        $result = $this->scheduler->reschedule($meeting, $reschedule->new_start, $reschedule->new_end);
        $reschedule->forceFill(['status' => MeetingReschedule::APPROVED, 'decided_by' => $me->id, 'decided_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'google_error' => $result['google_error'],
            'message' => 'Approved. The meeting is now '.$this->whenLabel($result['meeting']->fresh()).'.',
        ]);
    }

    /** Pending reschedule requests I am allowed to decide (hosts: their meetings; task admins: all). */
    private function incomingReschedules(?User $me)
    {
        return MeetingReschedule::query()
            ->with(['meeting:id,title,host_user_id', 'requester:id,name', 'decider:id,name'])
            ->where('status', MeetingReschedule::PENDING)
            ->when(! SuperAdminAccess::isTaskAdmin($me), fn ($q) => $q->whereHas('meeting', fn ($m) => $m->where('host_user_id', $me?->id)))
            ->whereHas('meeting', fn ($m) => $m->whereNotIn('status', [Meeting::STATUS_CANCELLED]))
            ->orderBy('id');
    }

    private function rescheduleRow(MeetingReschedule $r, ?User $me): array
    {
        $fmt = fn ($d) => $d ? $d->format('D, d M Y · h:i A') : null;
        $range = fn ($a, $b) => $a ? $fmt($a).($b ? ' – '.$b->format('h:i A') : '') : null;

        return [
            'id' => $r->id,
            'meeting_id' => (int) $r->meeting_id,
            'title' => $r->meeting?->title ?? 'Meeting',
            'status' => $r->status,
            'by' => $r->requester?->name ?? 'Someone',
            'by_me' => (int) $r->requested_by === (int) $me?->id,
            'reason' => $r->reason,
            'old' => $range($r->old_start, $r->old_end),
            'new' => $range($r->new_start, $r->new_end),
            'requested' => $r->created_at?->diffForHumans(),
            'decided_by' => $r->decider?->name,
            'decided' => $r->decided_at?->diffForHumans(),
            'self_applied' => $r->status === MeetingReschedule::APPROVED && (int) $r->requested_by === (int) $r->decided_by,
            'decide_url' => route('meetings.reschedules.decide', $r),
            'details_url' => route('meetings.show', $r->meeting_id),
        ];
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
}
