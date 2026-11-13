{{--
    Meeting booking form used for both "New meeting" (POST meetings.store) and "Edit meeting" (PUT meetings.update).
    Vars: $formId, $action, $method ('POST'|'PUT'), $users, $me, $meeting (optional), $submitLabel
--}}
@php
    $meeting = $meeting ?? null;
    $tz = \App\Casts\MeetingDateTime::timezone();
    $v = fn (string $key, $default = null) => old($key, $default);
    $selectedIds = collect(old('invitee_ids', $meeting
        ? $meeting->participants->where('is_invited', true)->pluck('user_id')->all()
        : []))->map(fn ($i) => (int) $i)->all();
    $curPriority = old('priority', $meeting?->priority ?? 'normal');
    $curRoom = (int) old('room_slot', $meeting?->room_slot ?: 0);
@endphp
<form method="POST" action="{{ $action }}" id="{{ $formId }}" data-mt-form data-availability="{{ route('meetings.availability') }}" data-tz="{{ $tz }}" data-exclude="{{ $meeting?->id }}" class="modal-content">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <div class="modal-header">
        <h5 class="modal-title"><i class="ri-vidicon-fill me-1 text-primary"></i>{{ $title }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        @if(! ($googleReady ?? true))
            <div class="alert alert-warning py-2 small">
                <strong>Google Meet is not connected.</strong> {{ $googleMessage ?? 'Connect a Google account first.' }}
            </div>
        @endif

        <div class="mb-3">
            <label class="form-label fw-semibold">Meeting title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" maxlength="255" required value="{{ $v('title', $meeting?->title) }}" placeholder="e.g. Weekly Amazon ads review">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Agenda <small class="text-muted fw-normal">(sent in the Google Calendar invitation)</small></label>
            <textarea name="agenda" class="form-control" rows="3" maxlength="5000">{{ $v('agenda', $meeting?->agenda) }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold d-block">Priority <span class="text-danger">*</span></label>
            <div class="d-flex flex-wrap gap-2">
                @foreach(\App\Models\Meeting::PRIORITIES as $key => $p)
                    <label class="mt-prio-opt {{ $key }}">
                        <input type="radio" name="priority" value="{{ $key }}" {{ $curPriority === $key ? 'checked' : '' }} required>
                        <span><i></i>{{ $p[1] }} {{ $p[0] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold d-block">Meeting room <span class="text-danger">*</span></label>
            <div class="mt-room-grid">
                @foreach(\App\Models\Meeting::ROOMS as $n => $label)
                    <label class="mt-room-opt">
                        <input type="radio" name="room_slot" value="{{ $n }}" {{ $curRoom === $n ? 'checked' : '' }} required>
                        <span><i class="ri-door-open-line"></i> {{ $label }}<small>Google Meet</small></span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="row g-2 mb-1">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                <input type="date" name="meeting_date" class="form-control" required value="{{ $v('meeting_date', $meeting?->scheduled_at?->format('Y-m-d')) }}">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label fw-semibold">Start time <span class="text-danger">*</span></label>
                <input type="time" name="start_time" class="form-control" required value="{{ $v('start_time', $meeting?->scheduled_at?->format('H:i')) }}">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label fw-semibold">End time <span class="text-danger">*</span></label>
                <input type="time" name="end_time" class="form-control" required value="{{ $v('end_time', $meeting?->scheduled_end_at?->format('H:i')) }}">
            </div>
        </div>
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <div class="mt-meta">Times are in <strong>{{ $tz }}</strong>.</div>
            <button type="button" class="btn btn-link btn-sm p-0" data-mt-now>Use now → +30 min</button>
        </div>
        <div class="mt-avail mb-3" data-mt-avail></div>

        <div class="mb-1 d-flex justify-content-between align-items-center">
            <label class="form-label fw-semibold mb-0">Participants <span class="badge bg-primary ms-1" data-mt-count>0</span></label>
            <input type="search" class="form-control form-control-sm" style="width:210px" placeholder="Search name / designation" data-mt-search>
        </div>
        <div class="mt-invitee-list" data-mt-list>
            @foreach($users as $u)
                @continue((int) $u->id === (int) ($meeting?->host_user_id ?? $me->id))
                <label data-name="{{ strtolower($u->name.' '.$u->designation) }}">
                    <input type="checkbox" name="invitee_ids[]" value="{{ $u->id }}" {{ in_array((int) $u->id, $selectedIds, true) ? 'checked' : '' }}>
                    @include('meetings._avatar', ['user' => $u, 'size' => 30])
                    <span>{{ $u->name }} <small>{{ $u->designation }}@if(! filter_var($u->email, FILTER_VALIDATE_EMAIL)) &middot; <span class="text-danger">no email</span>@endif</small></span>
                </label>
            @endforeach
        </div>
        <div class="mt-meta mt-2">Pick as many people as you need. Each gets a Google Calendar invitation with the Google Meet link.</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary" data-mt-submit data-busy="{{ $method === 'POST' ? 'Creating Google Meet…' : 'Updating Google Calendar…' }}" {{ ($googleReady ?? true) ? '' : 'disabled' }}>{{ $submitLabel }}</button>
    </div>
</form>

@once
@push('mt-scripts')
<script>
    (function () {
        function pad(n) { return String(n).padStart(2, '0'); }
        function nowIn(tz, plusMin) {
            var d = new Date(Date.now() + (plusMin || 0) * 60000);
            var parts = {};
            new Intl.DateTimeFormat('en-GB', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
                .formatToParts(d).forEach(function (p) { parts[p.type] = p.value; });
            return { date: parts.year + '-' + parts.month + '-' + parts.day, time: parts.hour + ':' + parts.minute };
        }
        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

        document.querySelectorAll('[data-mt-form]').forEach(function (form) {
            var q = function (sel) { return form.querySelector(sel); };
            var tz = form.getAttribute('data-tz');
            var avail = q('[data-mt-avail]');
            var count = q('[data-mt-count]');

            function updateCount() { count.textContent = form.querySelectorAll('[data-mt-list] input:checked').length; }
            form.addEventListener('change', function (e) { if (e.target.closest('[data-mt-list]')) updateCount(); });
            updateCount();

            q('[data-mt-search]').addEventListener('input', function () {
                var s = this.value.trim().toLowerCase();
                form.querySelectorAll('[data-mt-list] label').forEach(function (l) { l.style.display = !s || l.getAttribute('data-name').indexOf(s) !== -1 ? '' : 'none'; });
            });

            q('[data-mt-now]').addEventListener('click', function () {
                var a = nowIn(tz, 0), b = nowIn(tz, 30);
                form.elements.meeting_date.value = a.date;
                form.elements.start_time.value = a.time;
                form.elements.end_time.value = b.date === a.date ? b.time : '23:59';
                check();
            });

            var seq = 0;
            function check() {
                var room = form.querySelector('input[name=room_slot]:checked');
                var date = form.elements.meeting_date.value;
                if (!room || !date) { avail.innerHTML = ''; return; }
                var my = ++seq;
                var url = form.getAttribute('data-availability') + '?room_slot=' + encodeURIComponent(room.value) + '&meeting_date=' + encodeURIComponent(date)
                    + (form.getAttribute('data-exclude') ? '&exclude=' + encodeURIComponent(form.getAttribute('data-exclude')) : '');
                fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (my !== seq) return;
                        if (!j.busy || !j.busy.length) { avail.innerHTML = '<span class="text-success"><i class="ri-checkbox-circle-line"></i> ' + esc(j.room) + ' is free all day.</span>'; return; }
                        avail.innerHTML = '<span class="text-danger"><i class="ri-error-warning-line"></i> ' + esc(j.room) + ' is already booked:</span> '
                            + j.busy.map(function (b) { return '<span class="badge bg-light text-dark border">' + esc(b.from) + ' – ' + esc(b.to) + ' · ' + esc(b.title) + '</span>'; }).join(' ');
                    }).catch(function () {});
            }
            form.addEventListener('change', function (e) { if (e.target.name === 'room_slot' || e.target.name === 'meeting_date') check(); });
            check();

            form.addEventListener('submit', function () {
                var b = q('[data-mt-submit]'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (b.getAttribute('data-busy') || 'Saving…');
            });
        });
    })();
</script>
@endpush
@endonce
