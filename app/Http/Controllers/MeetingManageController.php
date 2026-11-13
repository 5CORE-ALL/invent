<?php

namespace App\Http\Controllers;

use App\Casts\MeetingDateTime;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingExternalRecording;
use App\Models\User;
use App\Services\Meetings\GoogleMeetException;
use App\Services\Meetings\GoogleMeetService;
use App\Services\Meetings\MeetingScheduler;
use App\Support\SuperAdminAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Details, edit/cancel, time taken, recordings, Minutes of Meeting, tasks, President Summary and the
 * Google connection of the Meeting section. (The lobby, the floating widget and the legacy room live in
 * MeetingController.)
 */
class MeetingManageController extends Controller
{
    private const RECORDING_EXTENSIONS = ['mp4', 'webm', 'mov', 'mkv', 'm4a', 'mp3', 'wav', 'ogg', 'aac'];

    public function __construct(private GoogleMeetService $google, private MeetingScheduler $scheduler)
    {
    }

    // ------------------------------------------------------------------
    // Details page
    // ------------------------------------------------------------------

    public function show(Meeting $meeting)
    {
        $me = Auth::user();
        abort_unless($meeting->canBeViewedBy($me), 403);

        $meeting->load([
            'host:id,name,email,designation,avatar',
            'participants.user:id,name,email,designation,avatar',
            'externalRecordings.adder:id,name',
            'recordings.user:id,name',
            'actionItems.assignee:id,name,designation,avatar',
            'mom.editor:id,name',
        ]);

        $canManage = $meeting->canBeManagedBy($me);

        return view('meetings.show', [
            'meeting' => $meeting,
            'me' => $me,
            'canManage' => $canManage,
            'users' => $canManage ? User::query()->active()->orderBy('name')->get(['id', 'name', 'designation', 'avatar', 'email']) : collect(),
            'assignable' => $canManage
                ? User::query()->active()->orderBy('name')->get(['id', 'name', 'designation', 'avatar'])
                : $meeting->participants->map->user->filter()->unique('id')->values(),
            'google' => $this->google->status(),
            'timezone' => MeetingDateTime::timezone(),
        ]);
    }

    // ------------------------------------------------------------------
    // Edit / cancel / Google sync
    // ------------------------------------------------------------------

    public function update(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'agenda' => 'nullable|string|max:5000',
            'priority' => ['required', Rule::in(array_keys(Meeting::PRIORITIES))],
            'room_slot' => ['required', 'integer', Rule::in(array_keys(Meeting::ROOMS))],
            'meeting_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'invitee_ids' => 'nullable|array|max:200',
            'invitee_ids.*' => 'integer|exists:users,id',
        ]);
        // Editing an existing meeting may keep a start time that has already passed (e.g. extend a running meeting).
        [$data['start'], $data['end']] = $this->scheduler->parseWindow($data['meeting_date'], $data['start_time'], $data['end_time'], allowPast: true);

        try {
            $result = $this->scheduler->update($meeting, $data, Auth::user());
        } catch (GoogleMeetException $e) {
            return back()->withInput()->with('google_config_error', $e->getMessage());
        }

        return redirect()->route('meetings.show', $meeting)->with(
            $result['google_error'] ? 'error' : 'success',
            $result['google_error']
                ? 'Changes saved here, but Google Calendar was not updated: '.$result['google_error'].' Use "Retry Google sync" to push them.'
                : 'Meeting updated and synchronised with Google Calendar. Guests were notified.'
        );
    }

    public function cancel(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);

        try {
            $warning = $this->scheduler->cancel($meeting, Auth::user(), $request->boolean('force'));
        } catch (GoogleMeetException $e) {
            return back()->with('error', 'The meeting was NOT cancelled because Google Calendar could not be updated: '.$e->getMessage())
                ->with('offer_force_cancel', true);
        }

        return redirect()->route('meetings.show', $meeting)->with(
            $warning ? 'error' : 'success',
            $warning ?: 'Meeting cancelled. The Google Calendar event was removed, guests were notified and the room is free again.'
        );
    }

    public function googleSync(Meeting $meeting)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);

        try {
            $error = $this->scheduler->retrySync($meeting);
        } catch (GoogleMeetException $e) {
            $error = $e->getMessage();
        }

        return back()->with(
            $error ? 'error' : 'success',
            $error ?: 'Google Calendar is in sync and the Google Meet link is ready.'
        );
    }

    // ------------------------------------------------------------------
    // Time taken
    // ------------------------------------------------------------------

    public function saveTime(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);
        abort_if($meeting->isCancelled(), 409, 'A cancelled meeting has no time taken.');

        $action = $request->validate(['action' => 'required|in:start,end,manual'])['action'];

        if ($action === 'start') {
            if ($meeting->actual_started_at) {
                return back()->with('error', 'The meeting was already marked as started.');
            }
            $meeting->forceFill([
                'actual_started_at' => now(),
                'started_at' => now(),
                'status' => $meeting->isEnded() ? $meeting->status : Meeting::STATUS_LIVE,
            ])->save();

            return back()->with('success', 'Meeting marked as started.');
        }

        if ($action === 'end') {
            if (! $meeting->actual_started_at) {
                return back()->with('error', 'Mark the meeting as started first, or enter the actual start and end times.');
            }
            $this->finish($meeting, $meeting->actual_started_at, now());

            return back()->with('success', 'Meeting ended. Time taken: '.Meeting::humanDuration($meeting->time_taken_seconds).'.');
        }

        $d = $request->validate([
            'actual_date' => 'required|date_format:Y-m-d',
            'actual_start' => 'required|date_format:H:i',
            'actual_end' => 'required|date_format:H:i',
        ]);
        $tz = MeetingDateTime::timezone();
        $start = Carbon::parse($d['actual_date'].' '.$d['actual_start'], $tz);
        $end = Carbon::parse($d['actual_date'].' '.$d['actual_end'], $tz);
        if ($end->lte($start)) {
            throw ValidationException::withMessages(['actual_end' => 'The actual end time must be after the actual start time.']);
        }
        if ($end->gt(now($tz)->addMinutes(5))) {
            throw ValidationException::withMessages(['actual_end' => 'The actual end time cannot be in the future.']);
        }
        $this->finish($meeting, $start, $end);

        return back()->with('success', 'Actual time saved. Time taken: '.Meeting::humanDuration($meeting->time_taken_seconds).'.');
    }

    private function finish(Meeting $meeting, Carbon $start, Carbon $end): void
    {
        $meeting->forceFill([
            'actual_started_at' => $start,
            'actual_ended_at' => $end,
            'time_taken_seconds' => max(0, $start->diffInSeconds($end)),
            'started_at' => $start,
            'ended_at' => $end,
            'status' => Meeting::STATUS_ENDED,
        ])->save();
    }

    // ------------------------------------------------------------------
    // Recordings
    // ------------------------------------------------------------------

    public function addRecording(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);

        $type = $request->validate(['type' => 'required|in:link,upload'])['type'];

        if ($type === 'link') {
            $d = $request->validate([
                'url' => ['required', 'string', 'max:2000', 'url:http,https'],
                'title' => 'nullable|string|max:255',
            ]);
            MeetingExternalRecording::create([
                'meeting_id' => $meeting->id,
                'source' => MeetingExternalRecording::SOURCE_LINK,
                'title' => $d['title'] ?: 'Recording link',
                'url' => $d['url'],
                'added_by' => Auth::id(),
            ]);

            return $this->done('Recording link saved.', '#tab-recording');
        }

        $request->validate([
            'file' => ['required', 'file', 'max:512000', 'mimes:'.implode(',', self::RECORDING_EXTENSIONS)],
            'title' => 'nullable|string|max:255',
        ], [
            'file.max' => 'The recording must be 500 MB or smaller. For bigger files paste a link instead.',
            'file.mimes' => 'Upload an audio/video recording ('.implode(', ', self::RECORDING_EXTENSIONS).').',
            'file.uploaded' => 'The upload failed. The file may be larger than the server allows (check upload_max_filesize / post_max_size); paste a link instead.',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $disk = Storage::disk(config('services.meetings.disk', 'local'));
        $path = $disk->putFileAs('meetings/'.$meeting->id.'/uploads', $file, Str::lower(Str::random(32)).'.'.$ext);
        if (! $path) {
            return back()->with('error', 'Could not store the recording file.');
        }

        MeetingExternalRecording::create([
            'meeting_id' => $meeting->id,
            'source' => MeetingExternalRecording::SOURCE_UPLOAD,
            'title' => $request->input('title') ?: Str::limit($file->getClientOriginalName(), 120, ''),
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'added_by' => Auth::id(),
        ]);

        return $this->done('Recording uploaded.', '#tab-recording');
    }

    public function deleteRecording(Meeting $meeting, MeetingExternalRecording $recording)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);
        abort_unless((int) $recording->meeting_id === (int) $meeting->id, 404);
        if ($recording->source === MeetingExternalRecording::SOURCE_GOOGLE_MEET) {
            return back()->with('error', 'Recordings generated by Google Meet cannot be removed here. Delete the file in Google Drive instead.');
        }

        if ($recording->path) {
            $disk = Storage::disk(config('services.meetings.disk', 'local'));
            if ($disk->exists($recording->path)) {
                $disk->delete($recording->path);
            }
        }
        $recording->delete();

        return $this->done('Recording removed.', '#tab-recording');
    }

    public function recordingDownload(Meeting $meeting, MeetingExternalRecording $recording)
    {
        abort_unless($meeting->canBeViewedBy(Auth::user()), 403);
        abort_unless((int) $recording->meeting_id === (int) $meeting->id && $recording->isFile(), 404);

        $disk = Storage::disk(config('services.meetings.disk', 'local'));
        abort_unless($disk->exists($recording->path), 404);

        $ext = strtolower(pathinfo($recording->path, PATHINFO_EXTENSION));
        $mime = [
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska',
            'm4a' => 'audio/mp4', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'aac' => 'audio/aac',
        ][$ext] ?? 'application/octet-stream';

        return response()->file($disk->path($recording->path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.Str::ascii($recording->original_name ?: 'recording.'.$ext).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0',
        ]);
    }

    public function syncGoogleRecordings(Meeting $meeting)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);

        try {
            $r = $this->google->syncConference($meeting);
        } catch (GoogleMeetException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($r['conferences'] === 0) {
            return back()->with('error', 'Google has no conference record for this Meet link yet. Nobody has joined it, or the call is still in progress.');
        }
        $msg = $r['recordings_added'].' new recording(s) found.';
        if ($r['recordings_pending'] > 0) {
            $msg .= ' '.$r['recordings_pending'].' recording(s) are still being processed by Google and will appear once the file exists.';
        }
        if ($r['started_at'] && $r['ended_at']) {
            $msg .= ' Real call time from Google: '.$r['started_at']->setTimezone(MeetingDateTime::timezone())->format('h:i A').' – '.$r['ended_at']->setTimezone(MeetingDateTime::timezone())->format('h:i A').'.';
        }

        return $this->done(trim($msg), '#tab-recording');
    }

    // ------------------------------------------------------------------
    // Minutes of Meeting + tasks
    // ------------------------------------------------------------------

    public function saveMom(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);
        abort_if($meeting->isCancelled(), 409, 'A cancelled meeting has no minutes.');

        $d = $request->validate([
            'discussion' => 'nullable|string|max:200000',
            'complete_minutes' => 'nullable|string|max:500000',
            'summary' => 'nullable|string|max:100000',
            'decisions' => 'nullable|string|max:100000',
            'issues' => 'nullable|string|max:100000',
        ]);

        DB::transaction(function () use ($meeting, $d) {
            $mom = $meeting->mom()->lockForUpdate()->first();
            $payload = $d + ['updated_by' => Auth::id()];
            if ($mom) {
                $mom->update($payload);
            } else {
                $meeting->mom()->create($payload + ['created_by' => Auth::id()]);
            }
        });

        return $this->done('Minutes of Meeting saved.', '#tab-mom');
    }

    public function addTask(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);

        $d = $this->validatedTask($request);
        $d['meeting_id'] = $meeting->id;
        $d['created_by'] = Auth::id();
        $d['assignee_matched'] = ! empty($d['assignee_user_id']);
        $d['assignee_name'] = $d['assignee_user_id'] ? User::whereKey($d['assignee_user_id'])->value('name') : null;
        $d['completed_at'] = $d['status'] === 'completed' ? now() : null;

        MeetingActionItem::create($d);

        return $this->done('Task assigned.', '#tab-tasks');
    }

    public function updateTask(Request $request, Meeting $meeting, MeetingActionItem $item)
    {
        abort_unless((int) $item->meeting_id === (int) $meeting->id, 404);
        $me = Auth::user();
        $manage = $meeting->canBeManagedBy($me);
        $isAssignee = $item->assignee_user_id && (int) $item->assignee_user_id === (int) $me->id;
        abort_unless($manage || $isAssignee, 403);

        if ($manage && $request->has('title')) {
            $d = $this->validatedTask($request);
            $d['assignee_matched'] = ! empty($d['assignee_user_id']);
            $d['assignee_name'] = $d['assignee_user_id'] ? User::whereKey($d['assignee_user_id'])->value('name') : $item->assignee_name;
        } else {
            // Assignees can only report their own progress.
            $d = $request->validate(['status' => ['required', Rule::in(array_keys(MeetingActionItem::STATUSES))]]);
        }

        $d['completed_at'] = $d['status'] === 'completed' ? ($item->completed_at ?? now()) : null;
        $item->update($d);

        return $this->done('Task updated.', '#tab-tasks');
    }

    public function deleteTask(Meeting $meeting, MeetingActionItem $item)
    {
        abort_unless($meeting->canBeManagedBy(Auth::user()), 403);
        abort_unless((int) $item->meeting_id === (int) $meeting->id, 404);
        $item->delete();

        return $this->done('Task removed.', '#tab-tasks');
    }

    private function validatedTask(Request $request): array
    {
        $d = $request->validate([
            'title' => 'required|string|max:1000',
            'description' => 'nullable|string|max:5000',
            'assignee_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'due_date' => 'nullable|date',
            'status' => ['required', Rule::in(array_keys(MeetingActionItem::STATUSES))],
        ], ['assignee_user_id.required' => 'Choose the employee this task is assigned to.']);

        return $d;
    }

    // ------------------------------------------------------------------
    // President Summary / management overview
    // ------------------------------------------------------------------

    public function overview(Request $request)
    {
        $me = Auth::user();

        $priority = (string) $request->query('priority', '');
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $tz = MeetingDateTime::timezone();

        $query = Meeting::query()
            ->visibleTo($me)
            ->with([
                'host:id,name,designation,avatar',
                'participants.user:id,name,designation,avatar',
                'actionItems.assignee:id,name,designation,avatar',
                'mom',
                'externalRecordings',
            ])
            ->when(array_key_exists($priority, Meeting::PRIORITIES), fn ($w) => $w->where('priority', $priority))
            ->when(in_array($status, ['scheduled', 'live', 'ended', 'cancelled'], true), fn ($w) => $w->where('status', $status))
            ->when($status === '', fn ($w) => $w->where('status', '!=', Meeting::STATUS_CANCELLED))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', '%'.$q.'%')->orWhere('summary', 'like', '%'.$q.'%')->orWhere('agenda', 'like', '%'.$q.'%')))
            ->when($this->validDate($from), fn ($w) => $w->where(DB::raw('COALESCE(scheduled_at, created_at)'), '>=', Meeting::dbTime(Carbon::parse($from.' 00:00', $tz))))
            ->when($this->validDate($to), fn ($w) => $w->where(DB::raw('COALESCE(scheduled_at, created_at)'), '<', Meeting::dbTime(Carbon::parse($to.' 00:00', $tz)->addDay())))
            ->orderByRaw('COALESCE(scheduled_at, created_at) desc')
            ->orderByDesc('id');

        $meetings = $query->paginate(10)->withQueryString();

        return view('meetings.overview', [
            'meetings' => $meetings,
            'priority' => $priority,
            'status' => $status,
            'q' => $q,
            'from' => $from,
            'to' => $to,
        ]);
    }

    private function validDate(string $d): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    }

    // ------------------------------------------------------------------
    // Google connection (OAuth 2.0)
    // ------------------------------------------------------------------

    private function redirectUri(): string
    {
        return (string) (config('services.meetings.google.redirect') ?: route('meetings.google.callback'));
    }

    public function googleConnect(Request $request)
    {
        abort_unless(SuperAdminAccess::isTaskAdmin(Auth::user()), 403);

        if (! $this->google->oauthClientConfigured()) {
            return redirect()->route('meetings.index')->with('google_config_error', 'Google OAuth client credentials are missing. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET (or MEETING_GOOGLE_CLIENT_ID / MEETING_GOOGLE_CLIENT_SECRET) in .env, then try again.');
        }

        $state = Str::random(40);
        $request->session()->put('meeting_google_oauth_state', $state);
        $client = $this->google->oauthClient($this->redirectUri());
        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    public function googleCallback(Request $request)
    {
        abort_unless(SuperAdminAccess::isTaskAdmin(Auth::user()), 403);

        $expected = (string) $request->session()->pull('meeting_google_oauth_state', '');
        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state', ''))) {
            return redirect()->route('meetings.index')->with('google_config_error', 'The Google sign-in expired or was tampered with. Please click "Connect Google account" again.');
        }
        if ($request->filled('error')) {
            return redirect()->route('meetings.index')->with('google_config_error', 'Google sign-in was cancelled or denied ('.e((string) $request->query('error')).').');
        }

        try {
            $conn = $this->google->completeOAuth((string) $request->query('code'), $this->redirectUri(), Auth::id());
        } catch (GoogleMeetException $e) {
            return redirect()->route('meetings.index')->with('google_config_error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Google Meet OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('meetings.index')->with('google_config_error', 'Could not complete the Google connection. See the log for details.');
        }

        return redirect()->route('meetings.index')->with('success', 'Google account '.($conn->email ?: '').' connected. Meetings now create real Google Meet links.');
    }

    public function googleDisconnect()
    {
        abort_unless(SuperAdminAccess::isTaskAdmin(Auth::user()), 403);
        $this->google->disconnect();

        return redirect()->route('meetings.index')->with('success', 'Google account disconnected. New meetings cannot be created until a Google account is connected again.');
    }

    // ------------------------------------------------------------------

    /** Redirect back with a success flash, re-opening the tab the user was working in. */
    private function done(string $message, string $tab = '')
    {
        return back()->with('success', $message)->with('open_tab', ltrim($tab, '#'));
    }
}
