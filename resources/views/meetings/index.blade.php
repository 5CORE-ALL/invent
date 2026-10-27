@extends('layouts.vertical', ['title' => 'Meeting Room', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .mt-rec-banner { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: 10px 14px; font-size: .9rem; }
        .mt-rec-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #dc2626; margin-right: 6px; animation: mtPulse 1.4s infinite; }
        @keyframes mtPulse { 0%,100% { opacity: 1 } 50% { opacity: .25 } }
        .mt-card { background: #fff; border: 1px solid #e5e9f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(15,23,42,.05); height: 100%; }
        .mt-card h6 { margin: 0 0 4px; font-weight: 700; color: #0f172a; }
        .mt-meta { color: #64748b; font-size: .82rem; }
        .mt-section-title { font-weight: 700; color: #0f172a; margin: 22px 0 10px; }
        .mt-invitee-list { max-height: 220px; overflow-y: auto; border: 1px solid #e5e9f0; border-radius: 8px; padding: 6px 10px; }
        .mt-invitee-list label { display: flex; gap: 8px; align-items: center; padding: 3px 0; margin: 0; cursor: pointer; font-weight: 400; }
        .mt-invitee-list small { color: #94a3b8; }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between py-1 flex-wrap gap-2">
                    <h4 class="page-title mb-0">Meeting Room</h4>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('meetings.minutes.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ri-draft-line me-1"></i>Meeting Minutes
                        </a>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newMeetingModal" data-mode="now">
                            <i class="ri-vidicon-line me-1"></i>Start Meeting
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newMeetingModal" data-mode="later">
                            <i class="ri-calendar-event-line me-1"></i>Schedule Meeting
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif

        <div class="mt-rec-banner mb-3">
            <span class="mt-rec-dot"></span><strong>Every meeting is recorded.</strong>
            Recording is mandatory and starts when you enter the room. After the meeting, AI writes the minutes and
            creates follow-up tasks automatically &mdash; see them under <a href="{{ route('meetings.minutes.index') }}">Meeting Minutes</a>.
        </div>

        <div class="mt-section-title">Live now</div>
        <div class="row g-3">
            @forelse($live as $m)
                <div class="col-md-6 col-xl-4">
                    <div class="mt-card">
                        <h6>{{ $m->title }}</h6>
                        <div class="mt-meta mb-2">
                            Host: {{ $m->host?->name ?? '—' }} &middot; started {{ $m->started_at?->diffForHumans() }}
                            &middot; <strong>{{ $m->in_room_count }}</strong> in room
                        </div>
                        <a href="{{ route('meetings.room', $m) }}" class="btn btn-success btn-sm"><i class="ri-login-box-line me-1"></i>Join</a>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="text-muted">No meetings are live right now.</div></div>
            @endforelse
        </div>

        <div class="mt-section-title">Your upcoming meetings</div>
        <div class="row g-3">
            @forelse($upcoming as $m)
                <div class="col-md-6 col-xl-4">
                    <div class="mt-card">
                        <h6>{{ $m->title }}</h6>
                        <div class="mt-meta mb-1">
                            {{ $m->scheduled_at ? $m->scheduled_at->format('D, d M Y · h:i A') : 'No time set' }}
                            &middot; Host: {{ $m->host?->name ?? '—' }}
                        </div>
                        @if($m->agenda)<div class="small text-secondary mb-2">{{ \Illuminate\Support\Str::limit($m->agenda, 140) }}</div>@endif
                        @if((int) $m->host_user_id === (int) $me->id && ($m->accepted_count + $m->declined_count + $m->pending_count) > 0)
                            <div class="small mb-2">
                                <span class="text-success">{{ $m->accepted_count }} accepted</span> &middot;
                                <span class="text-danger">{{ $m->declined_count }} declined</span> &middot;
                                <span class="text-muted">{{ $m->pending_count }} waiting</span>
                            </div>
                        @endif
                        <a href="{{ route('meetings.room', $m) }}" class="btn btn-primary btn-sm">
                            {{ (int) $m->host_user_id === (int) $me->id ? 'Start' : 'Join' }}
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="text-muted">Nothing scheduled.</div></div>
            @endforelse
        </div>

        <div class="d-flex align-items-center justify-content-between">
            <div class="mt-section-title">Recent meetings</div>
            <a href="{{ route('meetings.minutes.index') }}" class="small">All minutes &rarr;</a>
        </div>
        <div class="row g-3 mb-4">
            @forelse($recent as $m)
                <div class="col-md-6 col-xl-3">
                    <div class="mt-card">
                        <h6>{{ $m->title }}</h6>
                        <div class="mt-meta mb-2">{{ $m->ended_at?->format('d M Y, h:i A') }}</div>
                        @include('meetings._minutes-badge', ['status' => $m->minutes_status])
                        <div class="mt-2"><a href="{{ route('meetings.minutes.show', $m) }}" class="btn btn-outline-secondary btn-sm">Open minutes</a></div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="text-muted">No past meetings yet.</div></div>
            @endforelse
        </div>
    </div>
@endsection

@section('modal')
    <div class="modal fade" id="newMeetingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="POST" action="{{ route('meetings.store') }}">
                @csrf
                <input type="hidden" name="start_now" id="mtStartNow" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="mtModalTitle">Start a meeting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Meeting title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" maxlength="255" required placeholder="e.g. Weekly Amazon ads review">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Agenda <small class="text-muted">(helps the AI write better minutes)</small></label>
                        <textarea name="agenda" class="form-control" rows="3" maxlength="5000"></textarea>
                    </div>
                    <div class="mb-3 d-none" id="mtWhenWrap">
                        <label class="form-label">Date &amp; time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control" id="mtWhen">
                    </div>
                    <div class="mb-1 d-flex justify-content-between align-items-center">
                        <label class="form-label mb-0">Invite team members</label>
                        <input type="search" id="mtInviteSearch" class="form-control form-control-sm" style="width:200px" placeholder="Search…">
                    </div>
                    <div class="mt-invitee-list" id="mtInviteList">
                        @foreach($users as $u)
                            @continue((int) $u->id === (int) $me->id)
                            <label data-name="{{ strtolower($u->name.' '.$u->designation) }}">
                                <input type="checkbox" name="invitee_ids[]" value="{{ $u->id }}">
                                <span>{{ $u->name }} <small>{{ $u->designation }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-rec-banner mt-3">
                        <span class="mt-rec-dot"></span>This meeting will be <strong>recorded</strong>. Everyone who joins must allow their
                        microphone to be recorded. AI minutes and tasks are generated afterwards.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="mtSubmit">Start meeting</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            var modal = document.getElementById('newMeetingModal');
            modal.addEventListener('show.bs.modal', function (ev) {
                var later = ev.relatedTarget && ev.relatedTarget.getAttribute('data-mode') === 'later';
                document.getElementById('mtStartNow').value = later ? '0' : '1';
                document.getElementById('mtWhenWrap').classList.toggle('d-none', !later);
                document.getElementById('mtWhen').required = later;
                document.getElementById('mtModalTitle').textContent = later ? 'Schedule a meeting' : 'Start a meeting';
                document.getElementById('mtSubmit').textContent = later ? 'Schedule' : 'Start meeting';
            });
            document.getElementById('mtInviteSearch').addEventListener('input', function () {
                var q = this.value.trim().toLowerCase();
                document.querySelectorAll('#mtInviteList label').forEach(function (l) {
                    l.style.display = !q || l.getAttribute('data-name').indexOf(q) !== -1 ? '' : 'none';
                });
            });
        })();
    </script>
@endsection
