<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRecording;
use App\Models\User;
use App\Services\Meetings\MeetingMinutesService;
use App\Support\SuperAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Company meeting rooms. Recording is mandatory: nobody can enter the room without their
 * microphone recorder running, and every recording feeds the AI minutes + task generation.
 */
class MeetingController extends Controller
{
    // ---------------------------------------------------------------------
    // Lobby
    // ---------------------------------------------------------------------

    public function index()
    {
        $me = Auth::user();

        $mine = fn ($q) => $q->where('host_user_id', $me->id)
            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $me->id));

        $live = Meeting::query()
            ->with('host:id,name')
            ->where('status', Meeting::STATUS_LIVE)
            ->withCount(['participants as in_room_count' => fn ($q) => $q
                ->whereNull('left_at')
                ->where('last_seen_at', '>=', now()->subSeconds(MeetingParticipant::STALE_AFTER_SECONDS))])
            ->orderByDesc('started_at')
            ->get();

        $upcoming = Meeting::query()
            ->with('host:id,name')
            ->withCount([
                'participants as accepted_count' => fn ($q) => $q->where('is_invited', true)->where('rsvp', 'accepted'),
                'participants as declined_count' => fn ($q) => $q->where('is_invited', true)->where('rsvp', 'declined'),
                'participants as pending_count' => fn ($q) => $q->where('is_invited', true)->where('rsvp', 'pending'),
            ])
            ->where('status', Meeting::STATUS_SCHEDULED)
            ->where(fn ($q) => $q->where('host_user_id', $me->id)
                ->orWhereHas('participants', fn ($p) => $p->where('user_id', $me->id)->where('rsvp', '!=', 'declined')))
            ->orderByRaw('scheduled_at is null')
            ->orderBy('scheduled_at')
            ->limit(30)
            ->get();

        $recent = Meeting::query()
            ->with('host:id,name')
            ->where('status', Meeting::STATUS_ENDED)
            ->where($mine)
            ->orderByDesc('ended_at')
            ->limit(8)
            ->get();

        $users = User::query()->active()->orderBy('name')->get(['id', 'name', 'designation']);

        return view('meetings.index', compact('live', 'upcoming', 'recent', 'users', 'me'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'agenda' => 'nullable|string|max:5000',
            'scheduled_at' => 'nullable|date',
            'invitee_ids' => 'nullable|array|max:200',
            'invitee_ids.*' => 'integer|exists:users,id',
            'start_now' => 'nullable|boolean',
        ]);

        $meeting = $this->createMeeting($data, Auth::user());

        if ($request->boolean('start_now')) {
            return redirect()->route('meetings.room', $meeting);
        }

        return redirect()->route('meetings.index')->with('success', 'Meeting scheduled.');
    }

    /**
     * @param  array{title: string, agenda?: ?string, scheduled_at?: ?string, invitee_ids?: array<int>}  $data
     */
    private function createMeeting(array $data, User $me): Meeting
    {
        return DB::transaction(function () use ($data, $me) {
            $meeting = Meeting::create([
                'title' => trim($data['title']),
                'agenda' => $data['agenda'] ?? null,
                'room_name' => 'invent-'.Str::lower(Str::random(24)),
                'host_user_id' => $me->id,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'status' => Meeting::STATUS_SCHEDULED,
                'minutes_status' => Meeting::MINUTES_PENDING,
            ]);

            MeetingParticipant::create([
                'meeting_id' => $meeting->id, 'user_id' => $me->id, 'is_invited' => false,
                'rsvp' => 'accepted', 'rsvp_at' => now(),
            ]);

            $inviteeIds = User::query()->active()
                ->whereIn('id', array_unique(array_map('intval', $data['invitee_ids'] ?? [])))
                ->where('id', '!=', $me->id)
                ->pluck('id');
            foreach ($inviteeIds as $uid) {
                MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $uid, 'is_invited' => true, 'rsvp' => 'pending']);
            }

            return $meeting;
        });
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
                ->get(['id', 'name', 'designation'])
                ->map(fn (User $u) => ['id' => (int) $u->id, 'name' => (string) $u->name, 'designation' => (string) ($u->designation ?? '')])
                ->values(),
        ]);
    }

    public function requestStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'agenda' => 'required|string|min:5|max:5000',
            'when' => 'required|in:now,later',
            'scheduled_at' => 'required_if:when,later|nullable|date|after:-5 minutes',
            'invitee_ids' => 'required|array|min:1|max:200',
            'invitee_ids.*' => 'integer|exists:users,id',
        ], [
            'invitee_ids.required' => 'Add at least one member to the meeting.',
            'invitee_ids.min' => 'Add at least one member to the meeting.',
            'agenda.required' => 'Please add a meeting agenda.',
            'agenda.min' => 'Please add a meeting agenda (at least a few words).',
            'scheduled_at.required_if' => 'Pick a date and time, or choose "Start now".',
            'scheduled_at.after' => 'The meeting time cannot be in the past.',
        ]);

        $meeting = $this->createMeeting([
            'title' => $data['title'],
            'agenda' => $data['agenda'],
            'scheduled_at' => $data['when'] === 'later' ? $data['scheduled_at'] : null,
            'invitee_ids' => $data['invitee_ids'],
        ], Auth::user());

        return response()->json([
            'ok' => true,
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'scheduled_at' => $meeting->scheduled_at?->format('D, d M Y · h:i A T'),
                'invited' => $meeting->participants()->where('is_invited', true)->count(),
            ],
            'when' => $data['when'],
            'room_url' => route('meetings.room', $meeting),
            'meetings_url' => route('meetings.index'),
        ]);
    }

    /** Pending meeting requests addressed to the current user. */
    public function requests(): JsonResponse
    {
        $rows = MeetingParticipant::query()
            ->where('user_id', Auth::id())
            ->where('is_invited', true)
            ->where('rsvp', 'pending')
            ->whereNull('first_joined_at')
            ->whereHas('meeting', fn ($q) => $q->whereIn('status', [Meeting::STATUS_SCHEDULED, Meeting::STATUS_LIVE]))
            ->with('meeting.host:id,name')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'count' => $rows->count(),
            'requests' => $rows->map(fn (MeetingParticipant $p) => [
                'id' => (int) $p->meeting_id,
                'title' => $p->meeting->title,
                'agenda' => $p->meeting->agenda,
                'host' => $p->meeting->host?->name,
                'live' => $p->meeting->isLive(),
                'scheduled_at' => $p->meeting->scheduled_at?->format('D, d M Y · h:i A T'),
                'rsvp_url' => route('meetings.rsvp', $p->meeting_id),
                'room_url' => route('meetings.room', $p->meeting_id),
            ])->values(),
        ]);
    }

    public function rsvp(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate(['response' => 'required|in:accepted,declined']);

        if ($meeting->isEnded()) {
            return response()->json(['ok' => false, 'message' => 'This meeting has already ended.'], 409);
        }

        $p = MeetingParticipant::where('meeting_id', $meeting->id)->where('user_id', Auth::id())->first();
        if (! $p) {
            return response()->json(['ok' => false, 'message' => 'You were not invited to this meeting.'], 403);
        }

        $p->forceFill(['rsvp' => $data['response'], 'rsvp_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'response' => $data['response'],
            'room_url' => route('meetings.room', $meeting),
            'live' => $meeting->isLive(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Room
    // ---------------------------------------------------------------------

    public function room(Meeting $meeting)
    {
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

    public function join(Meeting $meeting): JsonResponse
    {
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

        return response()->json([
            'ok' => true,
            'session' => Str::random(32),
            'server_time_ms' => (int) round(microtime(true) * 1000),
            'room_name' => $meeting->room_name,
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
        if (! $user) {
            return false;
        }
        if ((int) $meeting->host_user_id === (int) $user->id || SuperAdminAccess::isTaskAdmin($user)) {
            return true;
        }

        return $meeting->participants()->where('user_id', $user->id)->exists();
    }

    private function canManage(Meeting $meeting, ?User $user): bool
    {
        return $user !== null
            && ((int) $meeting->host_user_id === (int) $user->id || SuperAdminAccess::isTaskAdmin($user));
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
