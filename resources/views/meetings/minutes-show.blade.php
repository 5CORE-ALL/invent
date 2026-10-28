@extends('layouts.vertical', ['title' => $meeting->title.' — Minutes', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .mm-card { background: #fff; border: 1px solid #e5e9f0; border-radius: 10px; padding: 16px 18px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(15,23,42,.05); }
        .mm-card h5 { font-weight: 700; color: #0f172a; margin-bottom: 10px; font-size: 1.02rem; }
        .mm-kv { display: grid; grid-template-columns: 130px 1fr; gap: 4px 12px; font-size: .9rem; }
        .mm-kv dt { color: #64748b; font-weight: 600; }
        .mm-kv dd { margin: 0; color: #0f172a; }
        .mm-transcript { max-height: 460px; overflow-y: auto; font-size: .86rem; line-height: 1.55; white-space: pre-wrap; background: #f8fafc; border-radius: 8px; padding: 12px; }
        .mm-topic h6 { font-weight: 700; margin: 10px 0 4px; }
        .mm-pill { font-size: 11px; border-radius: 10px; padding: 2px 8px; font-weight: 700; }
        .mm-pill.high { background: #fee2e2; color: #991b1b; }
        .mm-pill.normal { background: #e0f2fe; color: #075985; }
        .mm-pill.low { background: #e2e8f0; color: #475569; }
        .mm-ok { color: #16a34a; } .mm-bad { color: #dc2626; }
    </style>
@endsection

@section('content')
    @php
        $deleteConfirm = 'Delete this meeting permanently? Its recordings, transcript and minutes will be erased. Tasks already created from it are kept. This cannot be undone.';
        $dur = $meeting->durationSeconds();
        $fmt = fn (?int $s) => $s === null ? '—' : sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
        $inProgress = in_array($meeting->minutes_status, ['pending', 'processing'], true) && $meeting->isEnded();
    @endphp
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between py-1 flex-wrap gap-2">
                    <h4 class="page-title mb-0">{{ $meeting->title }}</h4>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('meetings.minutes.index') }}" class="btn btn-outline-secondary btn-sm">&larr; All minutes</a>
                        @if($meeting->isLive())
                            <a href="{{ route('meetings.room', $meeting) }}" class="btn btn-success btn-sm">Meeting is live — Join</a>
                        @endif
                        @if($canManage && $meeting->isEnded() && $meeting->minutes_status !== 'processing')
                            <form method="POST" action="{{ route('meetings.minutes.regenerate', $meeting) }}" onsubmit="return confirm('Generate the minutes again from the recordings? Tasks already created are kept and not duplicated.');">
                                @csrf
                                <button class="btn btn-outline-primary btn-sm"><i class="ri-refresh-line me-1"></i>{{ $meeting->minutes_status === 'ready' ? 'Regenerate minutes' : 'Generate minutes now' }}</button>
                            </form>
                        @endif
                        @if($canManage)
                            <form method="POST" action="{{ route('meetings.destroy', $meeting) }}" onsubmit="return confirm(@js($deleteConfirm));">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm"><i class="ri-delete-bin-line me-1"></i>Delete meeting</button>
                            </form>
                        @endif
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="ri-printer-line me-1"></i>Print</button>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif

        <div class="row">
            <div class="col-lg-4">
                {{-- Meeting details --}}
                <div class="mm-card">
                    <h5>Meeting details</h5>
                    <dl class="mm-kv mb-0">
                        <dt>Host</dt><dd>{{ $meeting->host?->name ?? '—' }}</dd>
                        <dt>Status</dt><dd>{{ ucfirst($meeting->status) }}</dd>
                        @if($meeting->scheduled_at)<dt>Scheduled</dt><dd>{{ $meeting->scheduled_at->format('d M Y, h:i A') }}</dd>@endif
                        <dt>Started</dt><dd>{{ $meeting->started_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                        <dt>Ended</dt><dd>{{ $meeting->ended_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                        <dt>Duration</dt><dd>{{ $fmt($dur) }}</dd>
                        <dt>Timezone</dt><dd>{{ \App\Casts\MeetingDateTime::timezone() }}</dd>
                        <dt>Minutes</dt><dd>@include('meetings._minutes-badge', ['status' => $meeting->minutes_status])
                            @if($meeting->minutes_generated_at)<div class="small text-muted">{{ $meeting->minutes_generated_at->format('d M Y, h:i A') }}@if($meeting->ai_provider) &middot; {{ $meeting->ai_provider }}@endif</div>@endif
                        </dd>
                    </dl>
                    @if($meeting->agenda)
                        <hr class="my-2">
                        <div class="small text-muted fw-semibold">Agenda</div>
                        <div class="small" style="white-space:pre-line">{{ $meeting->agenda }}</div>
                    @endif
                </div>

                {{-- Attendees --}}
                <div class="mm-card">
                    <h5>Attendees &amp; recording</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Name</th><th>In</th><th>Out</th><th class="text-center">Rec</th></tr></thead>
                            <tbody>
                                @foreach($meeting->participants->sortBy(fn ($p) => $p->user?->name) as $p)
                                    @php $recs = $meeting->recordings->where('user_id', $p->user_id); @endphp
                                    <tr>
                                        <td>
                                            {{ $p->user?->name ?? 'Unknown' }}
                                            @if((int) $p->user_id === (int) $meeting->host_user_id)<span class="badge bg-primary">Host</span>@endif
                                            @if($p->is_invited && ! $p->first_joined_at)<div class="small text-muted">Invited · {{ $p->rsvp === 'declined' ? 'declined' : ($p->rsvp === 'accepted' ? 'accepted, did not join' : 'no response') }}</div>@endif
                                        </td>
                                        <td class="small text-nowrap">{{ $p->first_joined_at?->format('h:i A') ?? '—' }}</td>
                                        <td class="small text-nowrap">{{ $p->left_at?->format('h:i A') ?? '—' }}</td>
                                        <td class="text-center">
                                            @if($p->first_joined_at)
                                                @if($recs->isNotEmpty())
                                                    <i class="ri-checkbox-circle-fill mm-ok" title="Recorded {{ $fmt((int) $recs->sum('duration_seconds')) }}"></i>
                                                @else
                                                    <i class="ri-close-circle-fill mm-bad" title="No recording received"></i>
                                                @endif
                                                @if($p->recording_gaps > 0)<span class="badge bg-warning text-dark" title="Times the recording stopped while in the room">{{ $p->recording_gaps }} gap</span>@endif
                                            @else — @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Recordings --}}
                @if($meeting->recordings->isNotEmpty())
                    <div class="mm-card">
                        <h5>Recordings</h5>
                        @foreach($meeting->recordings as $rec)
                            <div class="mb-3">
                                <div class="small fw-semibold">
                                    {{ $rec->user?->name ?? 'Unknown' }}
                                    <span class="text-muted fw-normal">&middot; {{ $rec->started_at?->format('h:i A') }} &middot; {{ number_format($rec->size_bytes / 1024 / 1024, 1) }} MB
                                        &middot; transcript: {{ $rec->transcript_status }}</span>
                                </div>
                                @if($rec->transcript_error)<div class="small text-danger">{{ $rec->transcript_error }}</div>@endif
                                @if($rec->size_bytes > 0)
                                    <audio controls preload="none" class="w-100" src="{{ route('meetings.recording.file', [$meeting, $rec]) }}"></audio>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-lg-8">
                @if($inProgress)
                    <div class="alert alert-info">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        <strong>{{ $meeting->minutes_status === 'processing' ? 'AI is writing the minutes…' : 'Waiting for recordings to finish uploading…' }}</strong>
                        This usually takes a few minutes. The page refreshes by itself.
                    </div>
                @elseif($meeting->minutes_status === 'failed')
                    <div class="alert alert-danger"><strong>Minutes could not be generated.</strong> {{ $meeting->minutes_error }}</div>
                @elseif($meeting->minutes_status === 'no_recording')
                    <div class="alert alert-secondary"><strong>No minutes.</strong> {{ $meeting->minutes_error }}</div>
                @elseif(! $meeting->isEnded())
                    <div class="alert alert-warning">This meeting has not ended yet. Minutes are generated after it ends.</div>
                @endif

                @if($meeting->summary)
                    <div class="mm-card">
                        <h5>Summary</h5>
                        <p class="mb-0">{{ $meeting->summary }}</p>
                    </div>
                @endif

                @php $mm = $meeting->minutes ?? []; @endphp
                @if(!empty($mm['topics']))
                    <div class="mm-card">
                        <h5>Discussion</h5>
                        @foreach($mm['topics'] as $t)
                            <div class="mm-topic">
                                <h6>{{ $t['title'] }}</h6>
                                @if(!empty($t['points']))<ul class="mb-1">@foreach($t['points'] as $pt)<li>{{ $pt }}</li>@endforeach</ul>@endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($mm['decisions']))
                    <div class="mm-card">
                        <h5>Decisions</h5>
                        <ul class="mb-0">@foreach($mm['decisions'] as $d)<li>{{ $d }}</li>@endforeach</ul>
                    </div>
                @endif

                @if($meeting->actionItems->isNotEmpty())
                    <div class="mm-card">
                        <h5>Action items &amp; tasks
                            @if($meeting->tasks_generated_at)<small class="text-muted fw-normal">&middot; tasks created {{ $meeting->tasks_generated_at->format('d M, h:i A') }}</small>@endif
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Task</th><th>Owner</th><th>Priority</th><th>Due</th><th>Task Manager</th></tr></thead>
                                <tbody>
                                    @foreach($meeting->actionItems as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item->title }}</div>
                                                @if($item->description)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($item->description, 200) }}</div>@endif
                                            </td>
                                            <td>
                                                @if($item->assignee_matched && $item->assignee){{ $item->assignee->name }}
                                                @else
                                                    <span class="text-warning" title="The owner was not clear in the meeting{{ $item->assignee_name ? ' (AI heard: '.$item->assignee_name.')' : '' }}; assigned to the host.">
                                                        {{ $meeting->host?->name }} <small>(unclear owner)</small></span>
                                                @endif
                                            </td>
                                            <td><span class="mm-pill {{ $item->priority }}">{{ ucfirst($item->priority) }}</span></td>
                                            <td class="text-nowrap small">{{ $item->due_date?->format('d M Y') ?? '—' }}</td>
                                            <td class="text-nowrap">
                                                @if($item->task_id)
                                                    <a href="{{ route('tasks.show', $item->task_id) }}">Task #{{ $item->task_id }}</a>
                                                    @if($tasks->has($item->task_id))<span class="badge bg-light text-dark border">{{ $tasks[$item->task_id]->status }}</span>
                                                    @else<span class="badge bg-light text-muted border">deleted</span>@endif
                                                @else
                                                    <span class="text-muted small">not created</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif($meeting->minutes_status === 'ready')
                    <div class="mm-card"><h5>Action items</h5><div class="text-muted">No action items were found in this meeting.</div></div>
                @endif

                @if(!empty($mm['open_questions']))
                    <div class="mm-card">
                        <h5>Open questions / follow-ups</h5>
                        <ul class="mb-0">@foreach($mm['open_questions'] as $qn)<li>{{ $qn }}</li>@endforeach</ul>
                    </div>
                @endif

                @if($meeting->transcript)
                    <div class="mm-card">
                        <h5>Full transcript</h5>
                        <details>
                            <summary class="text-primary" style="cursor:pointer">Show transcript</summary>
                            <div class="mm-transcript mt-2">{{ $meeting->transcript }}</div>
                        </details>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@if(in_array($meeting->minutes_status, ['pending', 'processing'], true) && $meeting->isEnded())
    @section('script')
        <script>setTimeout(function () { window.location.reload(); }, 20000);</script>
    @endsection
@endif
