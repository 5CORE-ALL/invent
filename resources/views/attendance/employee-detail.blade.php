@extends('layouts.vertical', ['title' => $title ?? 'Employee Attendance'])

@section('css')
<style>
    .att-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .flag-card { border-left: 3px solid #fd7e14; padding: .75rem; background: #fffbf5; border-radius: 6px; margin-bottom: .5rem; }
    .flag-card.severity-high { border-left-color: #dc3545; background: #fff5f5; }
    .flag-card.severity-low { border-left-color: #6c757d; background: #f8f9fa; }
    .shot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .75rem; }
    .shot-card { border: 1px solid #eee; border-radius: 8px; overflow: hidden; cursor: pointer; }
    .shot-card img { width: 100%; height: 100px; object-fit: cover; display: block; }
    .shot-meta { font-size: .7rem; padding: .35rem .5rem; background: #f8f9fa; }
</style>
@endsection

@section('content')
<div class="container-fluid" id="employeeAttDetail"
     data-csrf="{{ csrf_token() }}"
     data-analyze-url="{{ route('attendance.analyze', $employee) }}">

    <div class="row mb-3">
        <div class="col-12">
            <div class="att-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <a href="{{ route('attendance.monitor') }}" class="small text-muted">← Back to Monitor</a>
                        <h4 class="mb-0 mt-1">{{ $employee->name }}</h4>
                        <div class="text-muted small">{{ $employee->email }} · {{ $employee->designation ?? 'No designation' }}</div>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="get" class="d-flex gap-2 align-items-center">
                            <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                            <span class="small">to</span>
                            <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                            <button class="btn btn-sm btn-primary">Filter</button>
                        </form>
                        @if($can_admin)
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnRunAi">
                            <i class="ri-robot-2-line me-1"></i> Run AI Analysis
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="att-card p-3 mb-3">
                <h6>Policy</h6>
                @if($policy)
                    <div class="small">
                        <div>{{ $policy->name }}</div>
                        <div class="text-muted">{{ \Carbon\Carbon::parse($policy->expected_start)->format('h:i A') }} – {{ \Carbon\Carbon::parse($policy->expected_end)->format('h:i A') }}</div>
                        <div class="text-muted">Min {{ $policy->min_daily_hours }}h · Max idle {{ $policy->max_idle_minutes_per_hour }}m/hr</div>
                    </div>
                @else
                    <p class="text-muted small mb-0">Default policy applies.</p>
                @endif
            </div>

            <div class="att-card p-3 mb-3">
                <h6 class="mb-3">Desktop App Usage</h6>
                @forelse($app_usage as $app)
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ $app->app_name }}</span>
                        <span class="text-muted">{{ $app->hits }} samples</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No desktop agent data in this period.</p>
                @endforelse
            </div>

            <div class="att-card p-3">
                <h6 class="mb-3">AI & Rule Flags</h6>
                @forelse($flags as $flag)
                    <div class="flag-card severity-{{ $flag->severity }}">
                        <div class="d-flex justify-content-between">
                            <strong class="small">{{ $flag->title }}</strong>
                            <span class="badge bg-{{ $flag->status === 'open' ? 'warning' : 'secondary' }}">{{ $flag->status }}</span>
                        </div>
                        <div class="small text-muted">{{ $flag->flag_date?->format('M j, Y') }} · {{ $flag->typeLabel() }} · {{ $flag->source }}</div>
                        <p class="small mb-1">{{ $flag->description }}</p>
                        @if($can_admin && $flag->status === 'open')
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-success btn-review" data-id="{{ $flag->id }}" data-status="reviewed">Reviewed</button>
                            <button class="btn btn-outline-secondary btn-review" data-id="{{ $flag->id }}" data-status="dismissed">Dismiss</button>
                        </div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small">No flags in this period.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-7">
            <div class="att-card p-3 mb-3">
                <h6 class="mb-3">Daily Summaries</h6>
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Hours</th><th>Active%</th><th>Productivity</th><th>AI Risk</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($summaries as $s)
                        <tr>
                            <td>{{ $s->work_date->format('D, M j') }}</td>
                            <td>{{ number_format($s->workHours(), 1) }}h</td>
                            <td>{{ $s->activePercent() }}%</td>
                            <td>{{ $s->productivity_score ?? '—' }}</td>
                            <td>{{ $s->ai_risk_score ?? '—' }}</td>
                            <td>{{ ucfirst($s->status) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="att-card p-3">
                <h6 class="mb-3">Sessions</h6>
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Start</th><th>End</th><th>Location</th><th>Active</th><th>Idle</th></tr></thead>
                    <tbody>
                    @foreach($sessions as $s)
                        <tr>
                            <td>{{ $s->started_at->format('M j') }}</td>
                            <td>{{ $s->started_at->format('h:i A') }}</td>
                            <td>{{ $s->ended_at?->format('h:i A') ?? '—' }}</td>
                            <td>{{ strtoupper($s->work_location) }}</td>
                            <td>{{ gmdate('H:i', $s->total_active_seconds) }}</td>
                            <td>{{ gmdate('H:i', $s->total_idle_seconds) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="att-card p-3">
                <h6 class="mb-3"><i class="ri-camera-line me-1"></i> Screenshots ({{ $screenshots->count() }})</h6>
                @if($screenshots->isNotEmpty())
                <div class="shot-grid">
                    @foreach($screenshots as $shot)
                    <a href="{{ $shot->imageUrl() }}" target="_blank" class="shot-card text-decoration-none text-dark">
                        <img src="{{ $shot->imageUrl() }}" alt="Screenshot" loading="lazy">
                        <div class="shot-meta">
                            <div>{{ $shot->captured_at->format('M j, h:i A') }}</div>
                            <div class="text-truncate">{{ $shot->app_name ?? $shot->window_title ?? 'Desktop' }}</div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">No screenshots captured. Install the desktop agent and clock in.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function() {
    const root = document.getElementById('employeeAttDetail');
    const csrf = root.dataset.csrf;

    document.getElementById('btnRunAi')?.addEventListener('click', async function() {
        const url = root.dataset.analyzeUrl + '?date=' + new URLSearchParams(location.search).get('to') || '';
        const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }});
        const data = await r.json();
        if (data.ok) { alert('AI analysis complete. Risk score: ' + data.risk_score); location.reload(); }
        else alert('Analysis failed');
    });

    document.querySelectorAll('.btn-review').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const status = this.dataset.status;
            await fetch('/attendance/flags/' + id + '/review', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ status })
            });
            location.reload();
        });
    });
})();
</script>
@endsection
