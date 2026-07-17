@extends('layouts.vertical', ['title' => $title ?? 'Cron Monitor'])

@section('css')
<style>
    .cm-stat { border-radius: 10px; border: 1px solid rgba(0,0,0,.06); }
    .cm-badge-success { background: #198754; }
    .cm-badge-warning { background: #ffc107; color: #212529; }
    .cm-badge-danger { background: #dc3545; }
    .cm-badge-info { background: #0dcaf0; color: #212529; }
    .cm-job-row:hover { background: rgba(0,0,0,.02); }
    .cm-health-bar { height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden; }
    .cm-health-bar > span { display: block; height: 100%; }
    #cmTrendChart { min-height: 320px; }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Cron Monitor', 'sub_title' => 'Health & Execution'])

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card cm-stat shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Jobs tracked</div>
                <div class="fs-3 fw-semibold">{{ $overview['total_jobs'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card cm-stat shadow-sm h-100 border-success">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Healthy</div>
                <div class="fs-3 fw-semibold text-success">{{ $overview['healthy'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card cm-stat shadow-sm h-100 border-warning">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Partial</div>
                <div class="fs-3 fw-semibold text-warning">{{ $overview['partial'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card cm-stat shadow-sm h-100 border-danger">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Failed / Missed</div>
                <div class="fs-3 fw-semibold text-danger">{{ $overview['failed'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Trend — last 30 runs</h5>
                <form method="get" class="d-flex gap-2">
                    <select name="job" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All jobs (pick one for trend)</option>
                        @foreach($jobNames as $name)
                            <option value="{{ $name }}" @selected($selectedJob === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @if($filters['status'])
                        <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    @endif
                </form>
            </div>
            <div class="card-body">
                @if($selectedJob)
                    <div class="small text-muted mb-2">
                        {{ $selectedJob }}
                        @if($avgRuntime !== null)
                            · Avg runtime {{ $avgRuntime }}s
                        @endif
                    </div>
                    <div id="cmTrendChart"></div>
                @else
                    <p class="text-muted mb-0">Select a job to view trends.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="mb-0">Recent alerts</h5></div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($alerts as $alert)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span class="badge cm-badge-{{ $alert->severity === 'critical' ? 'danger' : 'warning' }}">
                                    {{ $alert->alert_type }}
                                </span>
                                <small class="text-muted">{{ $alert->created_at?->diffForHumans() }}</small>
                            </div>
                            <div class="fw-semibold mt-1">{{ $alert->title }}</div>
                            <div class="small text-muted text-truncate">{{ $alert->message }}</div>
                        </div>
                    @empty
                        <div class="p-3 text-muted">No alerts yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header"><h5 class="mb-0">Current status by job</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Status</th>
                    <th>Last run</th>
                    <th>Duration</th>
                    <th>Success %</th>
                    <th>Health</th>
                    <th>Updated</th>
                    <th>Failed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($latest as $row)
                    @php
                        $badge = match($row->status) {
                            'success' => 'success',
                            'partial_success' => 'warning',
                            'running' => 'info',
                            default => 'danger',
                        };
                    @endphp
                    <tr class="cm-job-row">
                        <td class="fw-semibold">{{ $row->job_name }}</td>
                        <td><span class="badge cm-badge-{{ $badge }}">{{ str_replace('_', ' ', $row->status) }}</span></td>
                        <td>{{ $row->started_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $row->duration_seconds !== null ? $row->duration_seconds.'s' : '—' }}</td>
                        <td>{{ $row->success_percentage !== null ? $row->success_percentage.'%' : '—' }}</td>
                        <td style="min-width:120px">
                            <div class="small mb-1">{{ $row->health_score ?? '—' }}/100</div>
                            <div class="cm-health-bar">
                                <span style="width: {{ min(100, (int)($row->health_score ?? 0)) }}%; background: {{ $badge === 'success' ? '#198754' : ($badge === 'warning' ? '#ffc107' : '#dc3545') }}"></span>
                            </div>
                        </td>
                        <td>{{ number_format((int)$row->updated_records) }}</td>
                        <td class="{{ $row->failed_records > 0 ? 'text-danger' : '' }}">{{ number_format((int)$row->failed_records) }}</td>
                        <td><a href="{{ route('cron-monitor.show', $row->id) }}" class="btn btn-sm btn-outline-primary">Details</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted p-4">No monitored cron runs yet. Try <code>php artisan cron-monitor:demo</code>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Execution log</h5>
        <form method="get" class="d-flex gap-2 flex-wrap">
            <select name="job" class="form-select form-select-sm">
                <option value="">All jobs</option>
                @foreach($jobNames as $name)
                    <option value="{{ $name }}" @selected(($filters['job'] ?? '') === $name)>{{ $name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach(['success','partial_success','failed','running','timed_out','missed'] as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ str_replace('_',' ',$st) }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Job</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Duration</th>
                    <th>Fetched</th>
                    <th>Updated</th>
                    <th>Failed</th>
                    <th>Success %</th>
                    <th>Health</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recent as $log)
                    <tr>
                        <td><a href="{{ route('cron-monitor.show', $log->id) }}">#{{ $log->id }}</a></td>
                        <td>{{ $log->job_name }}</td>
                        <td>{{ str_replace('_', ' ', $log->status) }}</td>
                        <td>{{ $log->started_at?->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->duration_seconds }}s</td>
                        <td>{{ number_format((int)$log->fetched_records) }}</td>
                        <td>{{ number_format((int)$log->updated_records) }}</td>
                        <td>{{ number_format((int)$log->failed_records) }}</td>
                        <td>{{ $log->success_percentage }}%</td>
                        <td>{{ $log->health_score }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($recent->hasPages())
        <div class="card-footer">{{ $recent->withQueryString()->links() }}</div>
    @endif
</div>
@endsection

@section('script')
@if($selectedJob && $trend->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = @json($trend->map(fn ($r) => optional($r->started_at)->format('m/d H:i'))->values());
    const success = @json($trend->pluck('success_percentage')->values());
    const health = @json($trend->pluck('health_score')->values());
    const updated = @json($trend->pluck('updated_records')->values());
    const failed = @json($trend->pluck('failed_records')->values());

    if (typeof Highcharts === 'undefined') return;

    Highcharts.chart('cmTrendChart', {
        chart: { zoomType: 'x' },
        title: { text: null },
        xAxis: { categories: labels, labels: { rotation: -35 } },
        yAxis: [{
            title: { text: 'Success % / Health' },
            max: 100
        }, {
            title: { text: 'Records' },
            opposite: true
        }],
        tooltip: { shared: true },
        series: [
            { name: 'Success %', data: success.map(Number), color: '#198754' },
            { name: 'Health score', data: health.map(Number), color: '#0d6efd' },
            { name: 'Updated', data: updated.map(Number), yAxis: 1, color: '#6f42c1', type: 'column' },
            { name: 'Failed', data: failed.map(Number), yAxis: 1, color: '#dc3545', type: 'column' }
        ],
        credits: { enabled: false }
    });
});
</script>
@endif
@endsection
