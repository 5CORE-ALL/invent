@extends('layouts.vertical', ['title' => $meeting->title, 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'hideMeetingFab' => false])

@section('css')
    @include('meetings._styles')
    <style>
        .mt-kv { display: grid; grid-template-columns: 150px 1fr; gap: 6px 14px; font-size: .92rem; }
        .mt-kv dt { color: #64748b; font-weight: 600; } .mt-kv dd { margin: 0; color: #0f172a; word-break: break-word; }
        .mt-panel { background: #fff; border: 1px solid #e5e9f0; border-radius: 10px; padding: 16px 18px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(15,23,42,.05); }
        .mt-panel h5 { font-weight: 700; color: #0f172a; font-size: 1.02rem; margin-bottom: 12px; }
        .mt-longtext { white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere; line-height: 1.65; }
        .mt-hero { background: linear-gradient(135deg, #f8fafc, #eef4ff); border: 1px solid #dbe6fb; border-radius: 12px; padding: 18px 22px; margin-bottom: 18px; }
        .mt-hero.prio-urgent { border-left: 6px solid #dc2626; } .mt-hero.prio-immediate { border-left: 6px solid #f97316; } .mt-hero.prio-normal { border-left: 6px solid #16a34a; }
        .nav-tabs .nav-link { font-weight: 600; }
        textarea.mt-big { min-height: 150px; }
    </style>
@endsection

@section('content')
@php
    $tz = $timezone;
    $isGoogle = $meeting->isGoogleMeet();
    $open = in_array($meeting->status, ['scheduled', 'live'], true);
    $actual = $meeting->actualDurationSeconds();
    $sched = $meeting->scheduledDurationSeconds();
    $people = $meeting->participants->sortBy(fn ($p) => $p->user?->name)->values();
    $mom = $meeting->mom;
    $momFill = fn (string $f) => old($f, $mom?->{$f});
@endphp
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between py-1 flex-wrap gap-2">
                <h4 class="page-title mb-0">Meeting details</h4>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('meetings.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Meeting Room</a>
                    <a href="{{ route('meetings.overview') }}" class="btn btn-outline-dark btn-sm"><i class="ri-presentation-line me-1"></i>President Summary</a>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
    @if(session('google_config_error'))<div class="alert alert-danger py-2"><strong>Google Meet:</strong> {{ session('google_config_error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul></div>@endif

    {{-- ================= HERO ================= --}}
    <div class="mt-hero prio-{{ $meeting->priority }}">
        <div class="d-flex justify-content-between flex-wrap gap-3">
            <div style="min-width:0">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    @include('meetings._priority', ['priority' => $meeting->priority])
                    @include('meetings._status', ['status' => $meeting->status])
                    @if($meeting->roomLabel())<span class="mt-room-pill"><i class="ri-door-open-line"></i>{{ $meeting->roomLabel() }}</span>@endif
                    @if(! $isGoogle)<span class="badge bg-light text-muted border">Legacy in-app room</span>@endif
                </div>
                <h3 class="mb-1" style="word-break:break-word">{{ $meeting->title }}</h3>
                <div class="text-muted">
                    @if($meeting->scheduled_at)
                        {{ $meeting->scheduled_at->format('l, d F Y') }} &middot; {{ $meeting->scheduled_at->format('h:i A') }}@if($meeting->scheduled_end_at) – {{ $meeting->scheduled_end_at->format('h:i A') }}@endif {{ $tz }}
                    @else
                        Started on demand
                    @endif
                </div>
                <div class="mt-2 mt-person">@include('meetings._avatar', ['user' => $meeting->host, 'size' => 30])<span>Organised by <strong>{{ $meeting->host?->name ?? '—' }}</strong></span></div>
            </div>
            <div class="d-flex flex-column align-items-start align-items-md-end gap-2">
                @if($meeting->isCancelled())
                    <span class="text-danger fw-bold">This meeting was cancelled{{ $meeting->cancelled_at ? ' on '.$meeting->cancelled_at->format('d M Y, h:i A') : '' }}.</span>
                @elseif($isGoogle)
                    @if($meeting->hasMeetLink())
                        <a href="{{ $meeting->google_meet_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-lg mt-join" id="joinMeet"><i class="ri-vidicon-fill me-1"></i>Join Google Meet</a>
                        <div class="small text-muted" style="word-break:break-all">{{ $meeting->google_meet_url }}</div>
                    @else
                        <button class="btn btn-lg btn-secondary" disabled><i class="ri-vidicon-fill me-1"></i>Google Meet link not ready</button>
                    @endif
                @else
                    <a href="{{ route('meetings.room', $meeting) }}" class="btn btn-success">Open legacy room</a>
                    <a href="{{ route('meetings.minutes.show', $meeting) }}" class="btn btn-outline-secondary btn-sm">Legacy minutes</a>
                @endif
                @if($canManage && $isGoogle && ! $meeting->isCancelled())
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                        @if($open)<button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editMeetingModal"><i class="ri-edit-line me-1"></i>Edit</button>@endif
                        @if(in_array($meeting->google_sync_status, ['pending', 'failed'], true) && $open)
                            <form method="POST" action="{{ route('meetings.google.sync', $meeting) }}">@csrf
                                <button class="btn btn-warning btn-sm"><i class="ri-refresh-line me-1"></i>Retry Google sync</button></form>
                        @endif
                        @if($meeting->google_html_link)<a href="{{ $meeting->google_html_link }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm"><i class="ri-calendar-event-line me-1"></i>Google Calendar</a>@endif
                        @if($open)<button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelMeetingModal"><i class="ri-close-circle-line me-1"></i>Cancel meeting</button>@endif
                    </div>
                @endif
            </div>
        </div>

        @if($isGoogle && ! $meeting->isCancelled() && $meeting->google_sync_status !== 'synced')
            <div class="alert alert-{{ $meeting->google_sync_status === 'failed' ? 'danger' : 'warning' }} mt-3 mb-0 py-2">
                <strong>{{ $meeting->google_sync_status === 'failed' ? 'Google Calendar sync failed.' : 'Waiting for Google.' }}</strong>
                {{ $meeting->google_sync_error ?: 'The Google Meet link has not been returned yet.' }}
                <div class="small mt-1">No link is shown until Google has really created it. The system retries automatically every few minutes{{ $canManage ? ', or press "Retry Google sync"' : '' }} (attempt {{ (int) $meeting->google_sync_attempts }}).</div>
            </div>
        @endif
    </div>

    {{-- ================= TABS ================= --}}
    <ul class="nav nav-tabs mb-3" id="mtTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-details" type="button">Details</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-recording" type="button">Recording
            @php $recCount = $meeting->externalRecordings->count() + $meeting->recordings->count(); @endphp @if($recCount)<span class="badge bg-secondary">{{ $recCount }}</span>@endif</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mom" type="button">Minutes of Meeting</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tasks" type="button">Tasks
            @if($meeting->actionItems->count())<span class="badge bg-secondary">{{ $meeting->actionItems->count() }}</span>@endif</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-summary" type="button">President Summary</button></li>
    </ul>

    <div class="tab-content">
        {{-- ---------------- DETAILS ---------------- --}}
        <div class="tab-pane fade show active" id="tab-details">
            <div class="row">
                <div class="col-lg-7">
                    <div class="mt-panel">
                        <h5>Participants ({{ $people->count() }})</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Person</th><th>Role</th><th>Reply</th></tr></thead>
                                <tbody>
                                @foreach($people as $p)
                                    <tr>
                                        <td><span class="mt-person">@include('meetings._avatar', ['user' => $p->user, 'size' => 36])<span>{{ $p->user?->name ?? 'Unknown' }}<small>{{ $p->user?->designation }}@if($p->user?->email) &middot; {{ $p->user->email }}@endif</small></span></span></td>
                                        <td>{{ (int) $p->user_id === (int) $meeting->host_user_id ? 'Organiser' : 'Invited' }}</td>
                                        <td>
                                            @if(! $p->is_invited)<span class="badge bg-primary">Host</span>
                                            @else
                                                @php [$l, $t] = match ($p->rsvp) { 'accepted' => ['Accepted', 'success'], 'declined' => ['Declined', 'danger'], 'completed' => ['Completed', 'primary'], default => ['No reply', 'warning'] }; @endphp
                                                <span class="badge bg-{{ $t }} {{ $t === 'warning' ? 'text-dark' : '' }}">{{ $l }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($isGoogle)<div class="mt-meta mt-2">Each participant received a Google Calendar invitation with the Google Meet link.</div>@endif
                    </div>

                    @if(filled($meeting->agenda))
                        <div class="mt-panel"><h5>Agenda</h5><div class="mt-longtext">{{ $meeting->agenda }}</div></div>
                    @endif
                </div>

                <div class="col-lg-5">
                    <div class="mt-panel">
                        <h5>Schedule &amp; time taken</h5>
                        <dl class="mt-kv mb-3">
                            <dt>Meeting room</dt><dd>{{ $meeting->roomLabel() ?? '—' }}</dd>
                            <dt>Scheduled</dt><dd>@if($meeting->scheduled_at){{ $meeting->scheduled_at->format('d M Y, h:i A') }}@if($meeting->scheduled_end_at) – {{ $meeting->scheduled_end_at->format('h:i A') }}@endif @else — @endif</dd>
                            <dt>Scheduled length</dt><dd>{{ $sched !== null ? \App\Models\Meeting::humanDuration($sched) : '—' }}</dd>
                            <dt>Actual start</dt><dd>{{ ($meeting->actual_started_at ?? ($isGoogle ? null : $meeting->started_at))?->format('d M Y, h:i A') ?? '—' }}</dd>
                            <dt>Actual end</dt><dd>{{ ($meeting->actual_ended_at ?? ($isGoogle ? null : $meeting->ended_at))?->format('d M Y, h:i A') ?? '—' }}</dd>
                            <dt>Time taken</dt><dd><strong>{{ $actual !== null ? \App\Models\Meeting::humanDuration($actual) : 'Not recorded yet' }}</strong></dd>
                            <dt>Requested</dt><dd>{{ $meeting->created_at?->setTimezone($tz)->format('d M Y, h:i A') }}</dd>
                            @if($isGoogle)
                                <dt>Google Calendar</dt>
                                <dd>@if($meeting->google_sync_status === 'synced')<span class="text-success">Synced{{ $meeting->google_synced_at ? ' '.$meeting->google_synced_at->diffForHumans() : '' }}</span>
                                    @elseif($meeting->google_sync_status === 'cancelled')<span class="text-danger">Event removed</span>
                                    @else<span class="text-warning">{{ ucfirst((string) $meeting->google_sync_status) }}</span>@endif</dd>
                            @endif
                        </dl>

                        @if($canManage && $isGoogle && ! $meeting->isCancelled())
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                @if(! $meeting->actual_started_at && $open)
                                    <form method="POST" action="{{ route('meetings.time', $meeting) }}">@csrf<input type="hidden" name="action" value="start">
                                        <button class="btn btn-success btn-sm"><i class="ri-play-circle-line me-1"></i>Mark started now</button></form>
                                @endif
                                @if($meeting->actual_started_at && ! $meeting->actual_ended_at)
                                    <form method="POST" action="{{ route('meetings.time', $meeting) }}">@csrf<input type="hidden" name="action" value="end">
                                        <button class="btn btn-danger btn-sm"><i class="ri-stop-circle-line me-1"></i>Mark ended now</button></form>
                                @endif
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#manualTime">Enter actual times</button>
                            </div>
                            <div class="collapse" id="manualTime">
                                <form method="POST" action="{{ route('meetings.time', $meeting) }}" class="row g-2 border rounded p-2">
                                    @csrf <input type="hidden" name="action" value="manual">
                                    <div class="col-12 col-md-4"><label class="form-label small mb-0">Date</label>
                                        <input type="date" name="actual_date" class="form-control form-control-sm" required value="{{ old('actual_date', ($meeting->actual_started_at ?? $meeting->scheduled_at)?->format('Y-m-d')) }}"></div>
                                    <div class="col-6 col-md-4"><label class="form-label small mb-0">Actual start</label>
                                        <input type="time" name="actual_start" class="form-control form-control-sm" required value="{{ old('actual_start', ($meeting->actual_started_at ?? $meeting->scheduled_at)?->format('H:i')) }}"></div>
                                    <div class="col-6 col-md-4"><label class="form-label small mb-0">Actual end</label>
                                        <input type="time" name="actual_end" class="form-control form-control-sm" required value="{{ old('actual_end', ($meeting->actual_ended_at ?? $meeting->scheduled_end_at)?->format('H:i')) }}"></div>
                                    <div class="col-12 d-flex justify-content-between align-items-center">
                                        <span class="mt-meta">{{ $tz }}. Time taken is calculated from these two times.</span>
                                        <button class="btn btn-primary btn-sm">Save time taken</button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------------- RECORDING ---------------- --}}
        <div class="tab-pane fade" id="tab-recording">
            <div class="mt-panel">
                <h5>Meeting recordings</h5>
                @if($meeting->externalRecordings->isEmpty() && $meeting->recordings->isEmpty())
                    <div class="alert alert-light border mb-0">
                        <strong>No recording is available yet.</strong>
                        A recording only appears here once it really exists — uploaded or linked by an authorised person, or generated by Google Meet and found through the Google API.
                    </div>
                @endif

                @foreach($meeting->externalRecordings as $rec)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <i class="ri-{{ $rec->source === 'upload' ? 'file-music-line' : ($rec->source === 'google_meet' ? 'google-fill' : 'link') }} me-1"></i>
                                <strong>{{ $rec->title ?: 'Recording' }}</strong>
                                <span class="badge bg-light text-dark border ms-1">{{ ['upload' => 'Uploaded file', 'link' => 'Link', 'google_meet' => 'Google Meet'][$rec->source] ?? $rec->source }}</span>
                                <div class="mt-meta">Added {{ $rec->created_at?->setTimezone($tz)->format('d M Y, h:i A') }}@if($rec->adder) by {{ $rec->adder->name }}@endif
                                    @if($rec->size_bytes) &middot; {{ number_format($rec->size_bytes / 1048576, 1) }} MB @endif</div>
                            </div>
                            <div class="d-flex gap-2 align-items-start">
                                @if($rec->isFile())
                                    <a href="{{ route('meetings.recordings.download', [$meeting, $rec]) }}" class="btn btn-outline-primary btn-sm" target="_blank"><i class="ri-download-line me-1"></i>Open / download</a>
                                @elseif($rec->url)
                                    <a href="{{ $rec->url }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer"><i class="ri-external-link-line me-1"></i>Open recording</a>
                                @endif
                                @if($canManage && $rec->source !== 'google_meet')
                                    <form method="POST" action="{{ route('meetings.recordings.delete', [$meeting, $rec]) }}" onsubmit="return confirm('Remove this recording from the meeting?');">@csrf @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm"><i class="ri-delete-bin-line"></i></button></form>
                                @endif
                            </div>
                        </div>
                        @if($rec->isFile())
                            @if(str_starts_with((string) $rec->mime, 'video/'))
                                <video controls preload="none" class="w-100 mt-2" style="max-height:420px" src="{{ route('meetings.recordings.download', [$meeting, $rec]) }}"></video>
                            @else
                                <audio controls preload="none" class="w-100 mt-2" src="{{ route('meetings.recordings.download', [$meeting, $rec]) }}"></audio>
                            @endif
                        @endif
                    </div>
                @endforeach

                @foreach($meeting->recordings as $rec)
                    @if($rec->size_bytes > 0)
                        <div class="border rounded p-3 mb-2">
                            <strong>In-app room audio</strong> <span class="mt-meta">{{ $rec->user?->name }} &middot; {{ $rec->started_at?->format('d M, h:i A') }}</span>
                            <audio controls preload="none" class="w-100 mt-2" src="{{ route('meetings.recording.file', [$meeting, $rec]) }}"></audio>
                        </div>
                    @endif
                @endforeach
            </div>

            @if($canManage)
                <div class="row">
                    <div class="col-lg-6">
                        <div class="mt-panel">
                            <h5>Save a recording link</h5>
                            <form method="POST" action="{{ route('meetings.recordings.add', $meeting) }}">
                                @csrf <input type="hidden" name="type" value="link">
                                <div class="mb-2"><input type="text" name="title" class="form-control form-control-sm" maxlength="255" placeholder="Title (optional)"></div>
                                <div class="mb-2"><input type="url" name="url" class="form-control form-control-sm" maxlength="2000" required placeholder="https://drive.google.com/file/d/…"></div>
                                <button class="btn btn-primary btn-sm">Save link</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="mt-panel">
                            <h5>Upload a recording</h5>
                            <form method="POST" action="{{ route('meetings.recordings.add', $meeting) }}" enctype="multipart/form-data">
                                @csrf <input type="hidden" name="type" value="upload">
                                <div class="mb-2"><input type="text" name="title" class="form-control form-control-sm" maxlength="255" placeholder="Title (optional)"></div>
                                <div class="mb-2"><input type="file" name="file" class="form-control form-control-sm" required accept=".mp4,.webm,.mov,.mkv,.m4a,.mp3,.wav,.ogg,.aac,audio/*,video/*"></div>
                                <div class="mt-meta mb-2">mp4, webm, mov, mkv, m4a, mp3, wav, ogg, aac &middot; up to 500 MB.</div>
                                <button class="btn btn-primary btn-sm">Upload</button>
                            </form>
                        </div>
                    </div>
                </div>
                @if($isGoogle && $meeting->hasMeetLink())
                    <div class="mt-panel">
                        <h5>Google Meet recordings</h5>
                        @if($google['recordings'])
                            <p class="small text-muted">Asks Google for recordings of this Meet link that have really been generated (this also runs automatically after the meeting).
                                {{ $meeting->google_conference_synced_at ? 'Last checked '.$meeting->google_conference_synced_at->diffForHumans().'.' : '' }}</p>
                            <form method="POST" action="{{ route('meetings.recordings.google', $meeting) }}">@csrf <button class="btn btn-outline-primary btn-sm"><i class="ri-google-fill me-1"></i>Check Google for recordings</button></form>
                        @else
                            <div class="alert alert-warning small mb-0">
                                <strong>Automatic Google recording import is not authorised.</strong> To enable it: set <code>MEETING_GOOGLE_RECORDING_SCOPES=true</code>, enable the <em>Google Meet REST API</em> and <em>Google Drive API</em> in the Google Cloud project,
                                reconnect the Google account (scopes <code>meetings.space.readonly</code> and <code>drive.meet.readonly</code>), and use a Google Workspace plan that allows Meet recording. Until then, upload the recording or paste its Drive link above.
                            </div>
                        @endif
                    </div>
                @endif
            @endif
        </div>

        {{-- ---------------- MOM ---------------- --}}
        <div class="tab-pane fade" id="tab-mom">
            @if(! $isGoogle && $meeting->summary)
                <div class="alert alert-info">This meeting used the in-app room; its AI-written minutes are on the <a href="{{ route('meetings.minutes.show', $meeting) }}">legacy minutes page</a>. You can still add manual minutes below.</div>
            @endif
            <div class="mt-panel">
                <h5>Meeting agenda</h5>
                @if(filled($meeting->agenda))<div class="mt-longtext">{{ $meeting->agenda }}</div>@else<div class="text-muted">No agenda was entered.@if($canManage && $open) Use <em>Edit</em> to add one.@endif</div>@endif
            </div>

            @if($canManage && ! $meeting->isCancelled())
                <form method="POST" action="{{ route('meetings.mom.save', $meeting) }}">
                    @csrf @method('PUT')
                    <div class="mt-panel">
                        <h5>Discussion details</h5>
                        <textarea name="discussion" class="form-control mt-big" maxlength="200000" placeholder="What was discussed, by topic…">{{ $momFill('discussion') }}</textarea>
                    </div>
                    <div class="mt-panel">
                        <h5>Complete meeting minutes</h5>
                        <textarea name="complete_minutes" class="form-control mt-big" style="min-height:260px" maxlength="500000" placeholder="Full minutes of the meeting…">{{ $momFill('complete_minutes') }}</textarea>
                    </div>
                    <div class="mt-panel">
                        <h5>Summary of minutes</h5>
                        <textarea name="summary" class="form-control mt-big" maxlength="100000" placeholder="Short summary for management…">{{ $momFill('summary') }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-lg-6"><div class="mt-panel"><h5>Important decisions</h5>
                            <textarea name="decisions" class="form-control mt-big" maxlength="100000" placeholder="One decision per line…">{{ $momFill('decisions') }}</textarea></div></div>
                        <div class="col-lg-6"><div class="mt-panel"><h5>Issues &amp; concerns</h5>
                            <textarea name="issues" class="form-control mt-big" maxlength="100000" placeholder="Problems, risks, open questions…">{{ $momFill('issues') }}</textarea></div></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="mt-meta">@if($mom?->editor)Last saved by {{ $mom->editor->name }} {{ $mom->updated_at?->diffForHumans() }}.@endif Assign tasks in the <strong>Tasks</strong> tab.</span>
                        <button class="btn btn-primary"><i class="ri-save-line me-1"></i>Save minutes</button>
                    </div>
                </form>
            @else
                @foreach(['discussion' => 'Discussion details', 'complete_minutes' => 'Complete meeting minutes', 'summary' => 'Summary of minutes', 'decisions' => 'Important decisions', 'issues' => 'Issues & concerns'] as $f => $label)
                    <div class="mt-panel"><h5>{{ $label }}</h5>
                        @if(filled($mom?->{$f}))<div class="mt-longtext">{{ $mom->{$f} }}</div>@else<div class="text-muted">Not written yet.</div>@endif</div>
                @endforeach
            @endif
        </div>

        {{-- ---------------- TASKS ---------------- --}}
        <div class="tab-pane fade" id="tab-tasks">
            <div class="mt-panel">
                <h5>Tasks assigned in this meeting</h5>
                @if($meeting->actionItems->isEmpty())
                    <div class="text-muted">No tasks have been assigned yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Task</th><th>Assigned to</th><th>Deadline</th><th style="width:170px">Status</th>@if($canManage)<th></th>@endif</tr></thead>
                            <tbody>
                            @foreach($meeting->actionItems as $it)
                                @php
                                    $mine = $it->assignee_user_id && (int) $it->assignee_user_id === (int) $me->id;
                                    [$stLabel, $stTone] = \App\Models\MeetingActionItem::STATUSES[$it->status] ?? ['Pending', 'warning'];
                                @endphp
                                <tr>
                                    <td style="min-width:260px">
                                        <div class="fw-semibold mt-longtext">{{ $it->title }}</div>
                                        @if($it->description)<div class="small text-muted mt-longtext">{{ $it->description }}</div>@endif
                                        @if($it->task_id)<a class="small" href="{{ route('tasks.show', $it->task_id) }}">Task Manager #{{ $it->task_id }}</a>@endif
                                    </td>
                                    <td><span class="mt-person">@include('meetings._avatar', ['user' => $it->assignee, 'size' => 34])<span>{{ $it->assignee?->name ?? ($it->assignee_name ?: 'Unassigned') }}<small>{{ $it->assignee?->designation }}</small></span></span></td>
                                    <td class="text-nowrap {{ $it->status !== 'completed' && $it->due_date && $it->due_date->isPast() ? 'text-danger fw-bold' : '' }}">{{ $it->due_date?->format('d M Y') ?? '—' }}</td>
                                    <td>
                                        @if($canManage || $mine)
                                            <form method="POST" action="{{ route('meetings.tasks.update', [$meeting, $it]) }}">@csrf @method('PUT')
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    @foreach(\App\Models\MeetingActionItem::STATUSES as $k => $st)<option value="{{ $k }}" @selected($it->status === $k)>{{ $st[0] }}</option>@endforeach
                                                </select>
                                            </form>
                                        @else
                                            <span class="badge bg-{{ $stTone }} {{ $stTone === 'warning' ? 'text-dark' : '' }}">{{ $stLabel }}</span>
                                        @endif
                                        @if($it->completed_at)<div class="mt-meta">Done {{ $it->completed_at->format('d M, h:i A') }}</div>@endif
                                    </td>
                                    @if($canManage)
                                        <td class="text-nowrap">
                                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#edit-task-{{ $it->id }}"><i class="ri-edit-line"></i></button>
                                            <form method="POST" action="{{ route('meetings.tasks.delete', [$meeting, $it]) }}" class="d-inline" onsubmit="return confirm('Remove this task?');">@csrf @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm"><i class="ri-delete-bin-line"></i></button></form>
                                        </td>
                                    @endif
                                </tr>
                                @if($canManage)
                                    <tr class="collapse" id="edit-task-{{ $it->id }}"><td colspan="5" class="bg-light">
                                        <form method="POST" action="{{ route('meetings.tasks.update', [$meeting, $it]) }}" class="row g-2">
                                            @csrf @method('PUT')
                                            <div class="col-md-4"><input name="title" class="form-control form-control-sm" required maxlength="1000" value="{{ $it->title }}"></div>
                                            <div class="col-md-3"><select name="assignee_user_id" class="form-select form-select-sm" required>
                                                @foreach($assignable as $u)<option value="{{ $u->id }}" @selected((int) $it->assignee_user_id === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                                            <div class="col-md-2"><input type="date" name="due_date" class="form-control form-control-sm" value="{{ $it->due_date?->format('Y-m-d') }}"></div>
                                            <div class="col-md-2"><select name="status" class="form-select form-select-sm">@foreach(\App\Models\MeetingActionItem::STATUSES as $k => $st)<option value="{{ $k }}" @selected($it->status === $k)>{{ $st[0] }}</option>@endforeach</select></div>
                                            <div class="col-md-1"><button class="btn btn-primary btn-sm w-100">Save</button></div>
                                            <div class="col-12"><textarea name="description" class="form-control form-control-sm" rows="2" maxlength="5000" placeholder="Details">{{ $it->description }}</textarea></div>
                                        </form>
                                    </td></tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if($canManage && ! $meeting->isCancelled())
                <div class="mt-panel">
                    <h5>Assign a task</h5>
                    <form method="POST" action="{{ route('meetings.tasks.add', $meeting) }}" class="row g-2">
                        @csrf
                        <div class="col-md-5"><label class="form-label small mb-0">Task <span class="text-danger">*</span></label><input name="title" class="form-control" required maxlength="1000" value="{{ old('title') }}"></div>
                        <div class="col-md-3"><label class="form-label small mb-0">Assigned employee <span class="text-danger">*</span></label>
                            <select name="assignee_user_id" class="form-select" required>
                                <option value="">Select employee…</option>
                                @foreach($assignable as $u)<option value="{{ $u->id }}" @selected((int) old('assignee_user_id') === (int) $u->id)>{{ $u->name }}{{ $u->designation ? ' — '.$u->designation : '' }}</option>@endforeach
                            </select></div>
                        <div class="col-6 col-md-2"><label class="form-label small mb-0">Deadline</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}"></div>
                        <div class="col-6 col-md-2"><label class="form-label small mb-0">Status</label>
                            <select name="status" class="form-select">@foreach(\App\Models\MeetingActionItem::STATUSES as $k => $st)<option value="{{ $k }}">{{ $st[0] }}</option>@endforeach</select></div>
                        <div class="col-12"><textarea name="description" class="form-control" rows="2" maxlength="5000" placeholder="Details (optional)">{{ old('description') }}</textarea></div>
                        <div class="col-12"><button class="btn btn-primary"><i class="ri-add-line me-1"></i>Assign task</button> <span class="mt-meta ms-2">Add the same task again for another employee to assign it to several people.</span></div>
                    </form>
                </div>
            @endif
        </div>

        {{-- ---------------- SUMMARY ---------------- --}}
        <div class="tab-pane fade" id="tab-summary">
            @include('meetings._summary', ['meeting' => $meeting, 'compact' => true])
        </div>
    </div>
</div>
@endsection

@section('modal')
    @if($canManage && $isGoogle && $open)
        <div class="modal fade" id="editMeetingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                @include('meetings._form', [
                    'formId' => 'editMeetingForm', 'action' => route('meetings.update', $meeting), 'method' => 'PUT',
                    'users' => $users, 'me' => $me, 'meeting' => $meeting,
                    'title' => 'Edit meeting', 'submitLabel' => 'Save & update Google Calendar',
                    'googleReady' => $google['ok'], 'googleMessage' => $google['message'],
                ])
            </div>
        </div>
        <div class="modal fade" id="cancelMeetingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('meetings.cancel', $meeting) }}">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Cancel this meeting?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>The Google Calendar event will be deleted, every participant is notified by Google, and <strong>{{ $meeting->roomLabel() }}</strong> becomes free again. The meeting record stays in the history.</p>
                        @if(session('offer_force_cancel'))
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="force" value="1" id="forceCancel">
                                <label class="form-check-label" for="forceCancel">Cancel here anyway, even if Google Calendar cannot be reached (I will delete the Google event by hand).</label></div>
                        @endif
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep meeting</button><button class="btn btn-danger">Cancel meeting</button></div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('script')
    @stack('mt-scripts')
    <script>
        (function () {
            var want = @json(session('open_tab')) || (location.hash || '').replace('#', '');
            if (want && document.getElementById(want) && window.bootstrap) {
                var btn = document.querySelector('[data-bs-target="#' + want + '"]');
                if (btn) window.bootstrap.Tab.getOrCreateInstance(btn).show();
            }
            @if($errors->any() && old('meeting_date') && $canManage && $isGoogle && $open)
                var m = document.getElementById('editMeetingModal');
                if (m && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(m).show();
            @endif
            @if(session('offer_force_cancel'))
                var c = document.getElementById('cancelMeetingModal');
                if (c && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(c).show();
            @endif
        })();
    </script>
@endsection
