@extends('layouts.vertical', ['title' => 'Meeting Room', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @include('meetings._styles')
@endsection

@section('content')
@php
    $chipOf = function ($p) {
        if ($p->isInRoom()) return ['In room', 'success'];
        if ($p->first_joined_at) return ['Left', 'secondary'];
        return match ($p->rsvp) { 'accepted' => ['Accepted', 'success'], 'declined' => ['Declined', 'danger'], 'completed' => ['Completed', 'primary'], default => ['Waiting', 'warning'] };
    };
@endphp
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between py-1 flex-wrap gap-2">
                    <h4 class="page-title mb-0">Meeting Room</h4>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('meetings.minutes.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ri-draft-line me-1"></i>Meeting Minutes
                        </a>
                        <a href="{{ route('meetings.overview') }}" class="btn btn-outline-dark btn-sm">
                            <i class="ri-presentation-line me-1"></i>President Summary
                        </a>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newMeetingModal">
                            <i class="ri-vidicon-fill me-1"></i>New Google Meet
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif

        @if(session('google_config_error'))
            <div class="alert alert-danger"><i class="ri-error-warning-fill me-1"></i><strong>Google Meet:</strong> {{ session('google_config_error') }}</div>
        @endif

        @if(! $googleReady)
            <div class="alert alert-warning">
                <div class="fw-bold mb-1"><i class="ri-plug-line me-1"></i>Google Meet is not connected, so new meetings cannot be created yet.</div>
                <div class="small mb-2">{{ $google['message'] }}</div>
                @if(! empty($google['missing']))
                    <ul class="small mb-2 ps-3">@foreach($google['missing'] as $miss)<li>{{ $miss }}</li>@endforeach</ul>
                @endif
                @if($canConnectGoogle && $googleOauthReady)
                    <a href="{{ route('meetings.google.connect') }}" class="btn btn-primary btn-sm"><i class="ri-google-fill me-1"></i>Connect Google account</a>
                @elseif(! $canConnectGoogle)
                    <span class="small">Ask an administrator to connect the Google account.</span>
                @endif
            </div>
        @else
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 small text-muted">
                <span><i class="ri-checkbox-circle-fill text-success"></i> Google Meet connected{{ $google['email'] ? ' as '.$google['email'] : '' }}.
                    @if(! $google['recordings'])<span title="{{ implode(' | ', $google['missing']) }}">Automatic Google recording import is not authorised.</span>@endif
                </span>
                @if($canConnectGoogle && $google['mode'] === 'oauth')
                    <form method="POST" action="{{ route('meetings.google.disconnect') }}" onsubmit="return confirm('Disconnect the Google account? New meetings cannot be created until it is connected again. Existing meetings are not touched.');">
                        @csrf <button class="btn btn-link btn-sm text-danger p-0">Disconnect Google</button>
                    </form>
                @endif
            </div>
        @endif

        @if($invites->isNotEmpty())
            <div class="mt-section-title text-danger"><i class="ri-notification-3-fill me-1"></i>Meeting requests for you ({{ $invites->count() }}) <small class="text-muted fw-normal">&middot; oldest requests first</small></div>
            <div class="row g-3" id="mtInvites">
                @foreach($invites as $inv)
                    @php $im = $inv->meeting; @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="mt-card prio-{{ $im->priority }}" style="background:#fffafa">
                            <h6>{{ $im->title }} @include('meetings._priority', ['priority' => $im->priority]) @if($im->isLive())<span class="badge bg-success">Live now</span>@endif</h6>
                            <div class="mt-meta mb-1">
                                From <strong>{{ $im->host?->name ?? '—' }}</strong> &middot;
                                {{ $im->scheduled_at ? $im->scheduled_at->format('D, d M Y · h:i A') : ($im->isLive() ? 'happening now' : 'time not set') }}@if($im->scheduled_end_at) – {{ $im->scheduled_end_at->format('h:i A') }}@endif
                                @if($im->roomLabel())&middot; <span class="mt-room-pill">{{ $im->roomLabel() }}</span>@endif
                                &middot; requested {{ $im->created_at?->diffForHumans() }}
                            </div>
                            @if($im->agenda)<div class="small mb-2" style="white-space:pre-line"><span class="text-muted">Agenda:</span> {{ \Illuminate\Support\Str::limit($im->agenda, 220) }}</div>@endif
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-success btn-sm" data-rsvp="accepted" data-url="{{ route('meetings.rsvp', $im) }}" data-room="{{ $im->isGoogleMeet() ? route('meetings.show', $im) : route('meetings.room', $im) }}" data-live="{{ $im->isLive() ? 1 : 0 }}"><i class="ri-check-line"></i> Accept</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-rsvp="declined" data-url="{{ route('meetings.rsvp', $im) }}" data-title="{{ $im->title }}"><i class="ri-close-line"></i> Decline</button>
                                @include('meetings._join', ['m' => $im])
                                <a href="{{ route('meetings.show', $im) }}" class="btn btn-light btn-sm">Details</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-section-title">Live now</div>
        <div class="row g-3">
            @forelse($live as $m)
                <div class="col-md-6 col-xl-4">
                    <div class="mt-card prio-{{ $m->priority }}">
                        <h6>{{ $m->title }} @include('meetings._priority', ['priority' => $m->priority])</h6>
                        <div class="mt-meta mb-2">
                            Host: {{ $m->host?->name ?? '—' }} &middot; started {{ $m->started_at?->diffForHumans() }}
                            @if($m->roomLabel())&middot; <span class="mt-room-pill">{{ $m->roomLabel() }}</span>@endif
                            @unless($m->isGoogleMeet())&middot; <strong>{{ $m->in_room_count }}</strong> in room @endunless
                        </div>
                        @if($m->participants->isNotEmpty())
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @foreach($m->participants->sortBy(fn ($p) => $p->user?->name) as $p)
                                    @php [$label, $tone] = $chipOf($p); @endphp
                                    <span class="badge bg-{{ $tone }} {{ $tone === 'warning' ? 'text-dark' : '' }}">{{ $p->user?->name ?? 'Unknown' }} &middot; {{ $label }}</span>
                                @endforeach
                            </div>
                        @endif
                        @include('meetings._join', ['m' => $m])
                        <a href="{{ route('meetings.show', $m) }}" class="btn btn-light btn-sm">Details</a>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="text-muted">No meetings are live right now.</div></div>
            @endforelse
        </div>

        <div class="mt-section-title">Your upcoming meetings <small class="text-muted fw-normal">&middot; oldest requests first</small></div>
        <div class="row g-3">
            @forelse($upcoming as $m)
                <div class="col-md-6 col-xl-4">
                    <div class="mt-card prio-{{ $m->priority }}">
                        <h6>{{ $m->title }} @include('meetings._priority', ['priority' => $m->priority])</h6>
                        <div class="mt-meta mb-1">
                            {{ $m->scheduled_at ? $m->scheduled_at->format('D, d M Y · h:i A') : 'No time set' }}@if($m->scheduled_end_at) – {{ $m->scheduled_end_at->format('h:i A') }}@endif
                            @if($m->roomLabel())&middot; <span class="mt-room-pill">{{ $m->roomLabel() }}</span>@endif
                        </div>
                        <div class="mt-meta mb-2">
                            <span class="mt-person">@include('meetings._avatar', ['user' => $m->host, 'size' => 22]) {{ $m->host?->name ?? '—' }}</span>
                            &middot; requested {{ $m->created_at?->diffForHumans() }}
                        </div>
                        @if($m->agenda)<div class="small text-secondary mb-2">{{ \Illuminate\Support\Str::limit($m->agenda, 140) }}</div>@endif
                        @if($m->participants->isNotEmpty())
                            <div class="mb-2 mt-av-stack">
                                @foreach($m->participants->sortBy(fn ($p) => $p->user?->name)->take(6) as $p)
                                    @include('meetings._avatar', ['user' => $p->user, 'size' => 26])
                                @endforeach
                                @if($m->participants->count() > 6)<span class="small text-muted ms-2 align-self-center">+{{ $m->participants->count() - 6 }}</span>@endif
                            </div>
                        @endif
                        @if((int) $m->host_user_id === (int) $me->id && ($m->accepted_count + $m->declined_count + $m->pending_count) > 0)
                            <div class="small mb-2">
                                <span class="text-success">{{ $m->accepted_count }} accepted</span> &middot;
                                <span class="text-danger">{{ $m->declined_count }} declined</span> &middot;
                                <span class="text-muted">{{ $m->pending_count }} waiting</span>
                            </div>
                        @endif
                        @if((int) $m->host_user_id === (int) $me->id && ! $m->isGoogleMeet() && $m->participants->isNotEmpty())
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @foreach($m->participants->sortBy(fn ($p) => $p->user?->name) as $p)
                                    @php [$label, $tone] = $chipOf($p); @endphp
                                    <span class="badge bg-{{ $tone }} {{ $tone === 'warning' ? 'text-dark' : '' }}" title="{{ $label }}">{{ $p->user?->name ?? 'Unknown' }} &middot; {{ $label }}</span>
                                @endforeach
                            </div>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            @include('meetings._join', ['m' => $m])
                            <a href="{{ route('meetings.show', $m) }}" class="btn btn-light btn-sm">Details</a>
                            @if((int) $m->host_user_id === (int) $me->id && ! $m->isGoogleMeet())
                                <form method="POST" action="{{ route('meetings.destroy', $m) }}" class="d-inline" onsubmit="return confirm(@js('Cancel and delete "'.$m->title.'"? Invited members will no longer see it. This cannot be undone.'));">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm"><i class="ri-delete-bin-line me-1"></i>Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="text-muted">Nothing scheduled.</div></div>
            @endforelse
        </div>

        {{-- ============ All meetings: sortable by request date, priority, status, meeting time ============ --}}
        @php
            $sortUrl = function (string $col) use ($sort, $dir) {
                $next = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
                return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $next, 'page' => null]);
            };
            $arrow = fn (string $col) => $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
        @endphp
        <div class="mt-section-title">All meetings</div>
        <form method="GET" class="row g-2 align-items-end mb-2">
            <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach(['open' => 'Pending & live', 'scheduled' => 'Scheduled', 'live' => 'Live', 'ended' => 'Ended', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $lbl)
                        <option value="{{ $k }}" @selected($statusFilter === $k)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Priority</label>
                <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Any priority</option>
                    @foreach(\App\Models\Meeting::PRIORITIES as $k => $p)<option value="{{ $k }}" @selected($priorityFilter === $k)>{{ $p[1] }} {{ $p[0] }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Room</label>
                <select name="room" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Any room</option>
                    @foreach(\App\Models\Meeting::ROOMS as $n => $lbl)<option value="{{ $n }}" @selected($roomFilter === $n)>{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-0">Sort by</label>
                <select class="form-select form-select-sm" onchange="location.href=this.value">
                    <option value="{{ $sortUrl('requested') }}" @selected($sort === 'requested')>Request date (oldest on top)</option>
                    <option value="{{ $sortUrl('priority') }}" @selected($sort === 'priority')>Priority (urgent first)</option>
                    <option value="{{ $sortUrl('status') }}" @selected($sort === 'status')>Status</option>
                    <option value="{{ $sortUrl('meeting') }}" @selected($sort === 'meeting')>Meeting date</option>
                </select>
            </div>
        </form>
        <div class="mt-card p-0" style="height:auto">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><a class="text-dark text-decoration-none" href="{{ $sortUrl('priority') }}">Priority{{ $arrow('priority') }}</a></th>
                            <th>Meeting</th>
                            <th><a class="text-dark text-decoration-none" href="{{ $sortUrl('requested') }}">Requested{{ $arrow('requested') }}</a></th>
                            <th><a class="text-dark text-decoration-none" href="{{ $sortUrl('meeting') }}">When{{ $arrow('meeting') }}</a></th>
                            <th>Room</th>
                            <th>Host &amp; participants</th>
                            <th><a class="text-dark text-decoration-none" href="{{ $sortUrl('status') }}">Status{{ $arrow('status') }}</a></th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($meetings as $m)
                            <tr>
                                <td>@include('meetings._priority', ['priority' => $m->priority])</td>
                                <td style="min-width:200px">
                                    <a href="{{ route('meetings.show', $m) }}" class="fw-semibold text-dark">{{ $m->title }}</a>
                                    @if(! $m->isGoogleMeet())<span class="badge bg-light text-muted border ms-1" title="Created before the Google Meet integration">legacy room</span>@endif
                                </td>
                                <td class="small text-nowrap">{{ $m->created_at?->setTimezone(\App\Casts\MeetingDateTime::timezone())->format('d M Y, h:i A') }}<div class="text-muted">{{ $m->created_at?->diffForHumans() }}</div></td>
                                <td class="small text-nowrap">
                                    @if($m->scheduled_at){{ $m->scheduled_at->format('d M Y') }}<div class="text-muted">{{ $m->scheduled_at->format('h:i A') }}@if($m->scheduled_end_at) – {{ $m->scheduled_end_at->format('h:i A') }}@endif</div>@else — @endif
                                </td>
                                <td>@if($m->roomLabel())<span class="mt-room-pill">{{ $m->roomLabel() }}</span>@else<span class="text-muted">—</span>@endif</td>
                                <td>
                                    <div class="mt-av-stack">
                                        @include('meetings._avatar', ['user' => $m->host, 'size' => 28])
                                        @foreach($m->participants->where('user_id', '!=', $m->host_user_id)->take(4) as $p)@include('meetings._avatar', ['user' => $p->user, 'size' => 28])@endforeach
                                    </div>
                                    @php $more = max(0, $m->participants->where('user_id', '!=', $m->host_user_id)->count() - 4); @endphp
                                    @if($more)<span class="small text-muted ms-1">+{{ $more }}</span>@endif
                                </td>
                                <td>@include('meetings._status', ['status' => $m->status])</td>
                                <td class="text-end text-nowrap">
                                    @if(in_array($m->status, ['scheduled', 'live'], true))@include('meetings._join', ['m' => $m])@endif
                                    <a href="{{ route('meetings.show', $m) }}" class="btn btn-light btn-sm">Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No meetings match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-2">{{ $meetings->links() }}</div>

        <div class="d-flex align-items-center justify-content-between">
            <div class="mt-section-title">Recent meetings</div>
            <a href="{{ route('meetings.minutes.index') }}" class="small">All minutes &rarr;</a>
        </div>
        <div class="row g-3 mb-4">
            @forelse($recent as $m)
                <div class="col-md-6 col-xl-3">
                    <div class="mt-card prio-{{ $m->priority }}">
                        <h6>{{ $m->title }}</h6>
                        <div class="mt-meta mb-2">{{ $m->ended_at?->format('d M Y, h:i A') }}@if($m->isGoogleMeet() && $m->actualDurationSeconds() !== null) &middot; {{ \App\Models\Meeting::humanDuration($m->actualDurationSeconds()) }}@endif</div>
                        @if($m->isGoogleMeet())@include('meetings._priority', ['priority' => $m->priority])@else @include('meetings._minutes-badge', ['status' => $m->minutes_status])@endif
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
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            @include('meetings._form', [
                'formId' => 'newMeetingForm', 'action' => route('meetings.store'), 'method' => 'POST',
                'users' => $users, 'me' => $me, 'meeting' => null,
                'title' => 'New Google Meet', 'submitLabel' => 'Create Google Meet',
                'googleReady' => $googleReady, 'googleMessage' => $google['message'],
            ])
        </div>
    </div>
@endsection

@section('script')
    @stack('mt-scripts')
    <script>
        (function () {
            @if($errors->any() || old('title'))
                var m = document.getElementById('newMeetingModal');
                if (m && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(m).show();
            @endif
            document.querySelectorAll('#mtInvites [data-rsvp]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var resp = btn.getAttribute('data-rsvp');
                    if (resp === 'declined' && !confirm('Decline "' + btn.getAttribute('data-title') + '"? The host will see that you declined.')) return;
                    var wrap = btn.parentNode; wrap.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                    fetch(btn.getAttribute('data-url'), {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ response: resp })
                    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }).then(function (res) {
                        if (!res.ok || !res.j.ok) { alert(res.j.message || 'Could not save your answer.'); wrap.querySelectorAll('button').forEach(function (b) { b.disabled = false; }); return; }
                        if (resp === 'accepted' && res.j.live) { window.location.href = res.j.room_url; } else { window.location.reload(); }
                    }).catch(function () { alert('Network problem. Please try again.'); wrap.querySelectorAll('button').forEach(function (b) { b.disabled = false; }); });
                });
            });
        })();
    </script>
@endsection
