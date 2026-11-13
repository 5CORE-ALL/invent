{{--
    President Summary / management overview of ONE meeting. Wide, readable, never truncates long text.
    Used on the meeting page (Summary tab) and on the President Summary list.
    Needs: $meeting with host, participants.user, actionItems.assignee, mom, externalRecordings loaded.
--}}
@php
    $mom = $meeting->mom;
    $tz = \App\Casts\MeetingDateTime::timezone();
    $summaryText = $mom?->summary ?: $meeting->summary;
    $decisionsText = $mom?->decisions;
    $legacyDecisions = ! $decisionsText ? (array) ($meeting->minutes['decisions'] ?? []) : [];
    $issuesText = $mom?->issues;
    $legacyIssues = ! $issuesText ? (array) ($meeting->minutes['open_questions'] ?? []) : [];
    $items = $meeting->actionItems;
    $pending = $items->where('status', '!=', 'completed')->count();
    $done = $items->where('status', 'completed')->count();
    $people = $meeting->participants->sortBy(fn ($p) => $p->user?->name)->values();
    $actual = $meeting->actualDurationSeconds();
    $sched = $meeting->scheduledDurationSeconds();
@endphp
<div class="ps-card prio-{{ $meeting->priority }}">
    <div class="ps-head">
        <div style="min-width:0">
            <h4 class="ps-title">{{ $meeting->title }}</h4>
            <div class="ps-sub">
                {{ $meeting->scheduled_at ? $meeting->scheduled_at->format('l, d F Y') : ($meeting->started_at?->format('l, d F Y') ?? '—') }}
                @if($meeting->scheduled_at) &middot; {{ $meeting->scheduled_at->format('h:i A') }}@if($meeting->scheduled_end_at) – {{ $meeting->scheduled_end_at->format('h:i A') }}@endif {{ $tz }}@endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            @include('meetings._priority', ['priority' => $meeting->priority])
            @include('meetings._status', ['status' => $meeting->status])
            @if($meeting->roomLabel())<span class="mt-room-pill">{{ $meeting->roomLabel() }}</span>@endif
            @if($meeting->hasMeetLink())
                <a href="{{ $meeting->google_meet_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm mt-join"><i class="ri-vidicon-fill me-1"></i>Google Meet</a>
            @endif
        </div>
    </div>

    <div class="ps-grid">
        <div class="ps-box">
            <div class="ps-label">Created by</div>
            <div class="mt-person">@include('meetings._avatar', ['user' => $meeting->host, 'size' => 40])<span><strong>{{ $meeting->host?->name ?? '—' }}</strong><small>{{ $meeting->host?->designation }}</small></span></div>
        </div>
        <div class="ps-box">
            <div class="ps-label">Time taken</div>
            <div class="ps-big">{{ $actual !== null ? \App\Models\Meeting::humanDuration($actual) : 'Not recorded' }}</div>
            <div class="mt-meta">Scheduled {{ $sched !== null ? \App\Models\Meeting::humanDuration($sched) : '—' }}</div>
        </div>
        <div class="ps-box">
            <div class="ps-label">Actions</div>
            <div class="ps-big">{{ $done }} <small class="text-success">completed</small> &middot; {{ $pending }} <small class="text-warning">pending</small></div>
            <div class="mt-meta">{{ $items->count() }} task(s) assigned</div>
        </div>
        <div class="ps-box">
            <div class="ps-label">Google Meet link</div>
            @if($meeting->hasMeetLink())
                <a href="{{ $meeting->google_meet_url }}" target="_blank" rel="noopener noreferrer" style="word-break:break-all">{{ $meeting->google_meet_url }}</a>
            @elseif($meeting->isGoogleMeet())
                <span class="text-warning">Not created yet</span>
            @else
                <span class="text-muted">Legacy in-app room</span>
            @endif
        </div>
    </div>

    <div class="ps-section">
        <div class="ps-label">Participants ({{ $people->count() }})</div>
        <div class="d-flex flex-wrap gap-3">
            @foreach($people as $p)
                <span class="mt-person">@include('meetings._avatar', ['user' => $p->user, 'size' => 34])<span>{{ $p->user?->name ?? 'Unknown' }}<small>{{ $p->user?->designation }}@if($p->is_invited) &middot; {{ ['accepted' => 'accepted', 'declined' => 'declined'][$p->rsvp] ?? 'no reply' }}@else &middot; host @endif</small></span></span>
            @endforeach
        </div>
    </div>

    @if(filled($meeting->agenda))
        <div class="ps-section"><div class="ps-label">Agenda</div><div class="ps-text">{{ $meeting->agenda }}</div></div>
    @endif

    <div class="ps-section">
        <div class="ps-label">Minutes summary</div>
        @if(filled($summaryText))<div class="ps-text">{{ $summaryText }}</div>@else<div class="text-muted">No minutes summary has been written yet.</div>@endif
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="ps-section">
                <div class="ps-label">Important decisions</div>
                @if(filled($decisionsText))<div class="ps-text">{{ $decisionsText }}</div>
                @elseif($legacyDecisions)<ul class="mb-0">@foreach($legacyDecisions as $d)<li class="ps-text">{{ $d }}</li>@endforeach</ul>
                @else<div class="text-muted">None recorded.</div>@endif
            </div>
        </div>
        <div class="col-lg-6">
            <div class="ps-section">
                <div class="ps-label">Issues &amp; concerns</div>
                @if(filled($issuesText))<div class="ps-text">{{ $issuesText }}</div>
                @elseif($legacyIssues)<ul class="mb-0">@foreach($legacyIssues as $d)<li class="ps-text">{{ $d }}</li>@endforeach</ul>
                @else<div class="text-muted">None recorded.</div>@endif
            </div>
        </div>
    </div>

    <div class="ps-section">
        <div class="ps-label">Tasks &amp; responsible employees</div>
        @if($items->isEmpty())
            <div class="text-muted">No tasks assigned.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Task</th><th>Responsible</th><th>Deadline</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($items->sortBy([['status', 'desc'], ['due_date', 'asc']]) as $it)
                            @php [$stLabel, $stTone] = \App\Models\MeetingActionItem::STATUSES[$it->status] ?? ['Pending', 'warning']; @endphp
                            <tr>
                                <td style="min-width:260px"><div class="fw-semibold ps-text">{{ $it->title }}</div>@if($it->description)<div class="small text-muted ps-text">{{ $it->description }}</div>@endif</td>
                                <td><span class="mt-person">@include('meetings._avatar', ['user' => $it->assignee, 'size' => 30])<span>{{ $it->assignee?->name ?? ($it->assignee_name ?: 'Unassigned') }}</span></span></td>
                                <td class="text-nowrap small {{ $it->status !== 'completed' && $it->due_date && $it->due_date->isPast() ? 'text-danger fw-bold' : '' }}">{{ $it->due_date?->format('d M Y') ?? '—' }}</td>
                                <td><span class="badge bg-{{ $stTone }} {{ $stTone === 'warning' ? 'text-dark' : '' }}">{{ $stLabel }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if(! ($compact ?? false))
        <div class="mt-2 text-end"><a href="{{ route('meetings.show', $meeting) }}" class="btn btn-outline-primary btn-sm">Open full meeting &rarr;</a></div>
    @endif
</div>
