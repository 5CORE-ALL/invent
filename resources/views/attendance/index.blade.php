@extends('layouts.vertical', ['title' => $title ?? 'My Attendance'])

@php use App\Support\AttendanceAccess; @endphp

@section('css')
<style>
    .att-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .att-stat { border-radius: 10px; padding: .85rem 1rem; background: #f8f9fa; }
    .att-stat .val { font-size: 1.35rem; font-weight: 700; }
    .clock-btn { min-width: 140px; }
    .status-pill { font-size: .75rem; padding: .25rem .6rem; border-radius: 999px; }
    .status-present { background: #d1e7dd; color: #0f5132; }
    .status-late { background: #fff3cd; color: #664d03; }
    .status-absent { background: #f8d7da; color: #842029; }
    .status-half_day { background: #e2e3e5; color: #41464b; }
    .session-live { animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.6} }
    .progress-thin { height: 6px; }
</style>
@endsection

@section('content')
<div class="container-fluid" id="attendanceApp"
     data-csrf="{{ csrf_token() }}"
     data-base-url="{{ url('/attendance') }}">

    <div class="row mb-3">
        <div class="col-12">
            <div class="att-card p-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h4 class="mb-1"><i class="ri-time-line me-2 text-primary"></i>My Attendance</h4>
                        <p class="text-muted mb-0 small">Clock in, track activity, and view your work sessions</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('attendance.agent') }}" class="btn btn-sm btn-primary">
                            <i class="ri-download-cloud-line me-1"></i> Desktop Agent
                        </a>
                        <input type="date" class="form-control form-control-sm" id="attDatePicker" value="{{ $date }}" style="max-width:160px">
                        @if(AttendanceAccess::canMonitor(auth()->user()))
                        <a href="{{ route('attendance.monitor') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ri-radar-line me-1"></i> Team Monitor
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="att-card p-3 h-100">
                <h6 class="text-muted mb-3">Clock Control</h6>
                <div id="clockStatus" class="mb-3">
                    @if($active_session)
                        <span class="badge bg-success session-live"><i class="ri-record-circle-line me-1"></i>
                            {{ ucfirst($active_session->status) }} since {{ $active_session->started_at->format('h:i A') }}
                        </span>
                        <div class="small text-muted mt-2">
                            Active: <span id="liveActive">{{ gmdate('H:i:s', $active_session->total_active_seconds) }}</span>
                            · Idle: <span id="liveIdle">{{ gmdate('H:i:s', $active_session->total_idle_seconds) }}</span>
                        </div>
                    @else
                        <span class="badge bg-secondary">Not clocked in</span>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <select class="form-select form-select-sm" id="workLocation" style="max-width:140px">
                        <option value="wfh">WFH</option>
                        <option value="office">Office</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-success clock-btn" id="btnClockIn" {{ $active_session ? 'disabled' : '' }}>
                        <i class="ri-login-circle-line me-1"></i> Clock In
                    </button>
                    <button type="button" class="btn btn-danger clock-btn" id="btnClockOut" {{ $active_session ? '' : 'disabled' }}>
                        <i class="ri-logout-circle-line me-1"></i> Clock Out
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="btnPause" {{ ($active_session && $active_session->status === 'active') ? '' : 'disabled' }}>
                        Pause
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" id="btnResume" {{ ($active_session && $active_session->status === 'paused') ? '' : 'disabled' }}>
                        Resume
                    </button>
                </div>
                @if($policy)
                <hr>
                <div class="small text-muted">
                    <div>Expected: {{ \Carbon\Carbon::parse($policy->expected_start)->format('h:i A') }} – {{ \Carbon\Carbon::parse($policy->expected_end)->format('h:i A') }}</div>
                    <div>Min hours: {{ $policy->min_daily_hours }}h · Grace: {{ $policy->grace_minutes }}m</div>
                </div>
                @endif
            </div>
        </div>
        <div class="col-lg-8">
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <div class="att-stat">
                        <div class="text-muted small">Status</div>
                        <div class="val">
                            @php $st = $summary?->status ?? 'absent'; @endphp
                            <span class="status-pill status-{{ $st }}">{{ ucfirst(str_replace('_', ' ', $st)) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="att-stat">
                        <div class="text-muted small">Work Hours</div>
                        <div class="val">{{ $summary ? number_format($summary->workHours(), 1) : '0.0' }}h</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="att-stat">
                        <div class="text-muted small">Active %</div>
                        <div class="val">{{ $summary ? $summary->activePercent() : 0 }}%</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="att-stat">
                        <div class="text-muted small">Productivity</div>
                        <div class="val">{{ $summary?->productivity_score ?? '—' }}</div>
                    </div>
                </div>
            </div>
            @if($summary && $summary->team_logger_hours)
            <div class="att-card p-3 mt-2">
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">TeamLogger hours (comparison)</span>
                    <strong>{{ $summary->team_logger_hours }}h</strong>
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="att-card p-3">
                <h6 class="mb-3">Today's Sessions</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Start</th><th>End</th><th>Location</th><th>Active</th><th>Idle</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($sessions as $s)
                            <tr>
                                <td>{{ $s->started_at->format('h:i A') }}</td>
                                <td>{{ $s->ended_at?->format('h:i A') ?? '—' }}</td>
                                <td><span class="badge bg-light text-dark">{{ strtoupper($s->work_location) }}</span></td>
                                <td>{{ gmdate('H:i', $s->total_active_seconds) }}</td>
                                <td>{{ gmdate('H:i', $s->total_idle_seconds) }}</td>
                                <td>{{ ucfirst($s->status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center py-3">No sessions for this date</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="att-card p-3 mb-3">
                <h6 class="mb-3">This Week</h6>
                @forelse($week_summaries as $ws)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small">{{ $ws->work_date->format('D, M j') }}</span>
                        <span class="small">{{ number_format($ws->workHours(), 1) }}h</span>
                        <div class="progress progress-thin flex-grow-1 mx-2" style="max-width:120px">
                            <div class="progress-bar bg-success" style="width:{{ $ws->productivity_score ?? 0 }}%"></div>
                        </div>
                        <span class="status-pill status-{{ $ws->status }}">{{ ucfirst($ws->status) }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No data this week yet.</p>
                @endforelse
            </div>
            @if($summary?->top_activities)
            <div class="att-card p-3">
                <h6 class="mb-3">Top Activities</h6>
                @foreach($summary->top_activities as $act)
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-truncate me-2" style="max-width:70%">{{ $act['label'] }}</span>
                        <span class="text-muted">{{ $act['count'] }}</span>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ asset('js/attendance-tracker.js') }}?v=1"></script>
<script>
(function() {
    const app = document.getElementById('attendanceApp');
    const csrf = app.dataset.csrf;
    const base = app.dataset.baseUrl;

    async function post(url, body) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(body || {})
        });
        return r.json();
    }

    document.getElementById('attDatePicker')?.addEventListener('change', function() {
        window.location.href = base + '?date=' + this.value;
    });

    document.getElementById('btnClockIn')?.addEventListener('click', async function() {
        const loc = document.getElementById('workLocation').value;
        const res = await post(base + '/clock-in', { work_location: loc });
        if (res.ok) location.reload();
        else alert(res.message || 'Could not clock in');
    });

    document.getElementById('btnClockOut')?.addEventListener('click', async function() {
        if (!confirm('Clock out now?')) return;
        const res = await post(base + '/clock-out');
        if (res.ok) location.reload();
    });

    document.getElementById('btnPause')?.addEventListener('click', async function() {
        await post(base + '/pause');
        location.reload();
    });

    document.getElementById('btnResume')?.addEventListener('click', async function() {
        await post(base + '/resume');
        location.reload();
    });

    if (window.AttendanceTracker) {
        window.AttendanceTracker.init({ baseUrl: base, csrf: csrf });
    }
})();
</script>
@endsection
