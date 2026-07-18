@extends('layouts.vertical', ['title' => $title ?? 'Cron Monitor'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    .cm-card { border: 1px solid #e5e7eb; box-shadow: none; }
    .cm-card .card-header { background: #f8fafc; border-bottom: 1px solid #e5e7eb; }
    #cmJobsTable, #cmLogsTable, #cmAlertsTable { font-size: .85rem; }
    .tabulator { border: 1px solid #e5e7eb; border-radius: 0 0 .35rem .35rem; }
    .tabulator .tabulator-header { background: #eef2f7; border-bottom: 1px solid #e5e7eb; }
    .tabulator .tabulator-col { background: #eef2f7 !important; }
    .tabulator .tabulator-col .tabulator-col-content { padding: 8px 10px; }
    .tabulator .tabulator-cell { padding: 8px 10px; }
    .cm-pill {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .15rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 650;
        text-transform: capitalize; border: 1px solid transparent; white-space: nowrap;
    }
    .cm-pill-ok { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .cm-pill-warn { background: #fffbeb; color: #d97706; border-color: #fde68a; }
    .cm-pill-bad { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .cm-pill-run { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    #cmTrendChart { min-height: 260px; }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', [
    'page_title' => 'Cron Monitor',
    'sub_title' => 'Health, chunks, retries & execution history',
])

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif

<div class="mb-3 p-2 bg-light rounded d-flex flex-nowrap align-items-center gap-2 overflow-auto">
    <span class="badge bg-secondary fs-6 p-2 flex-shrink-0" style="color: white; font-weight: bold;">Jobs: {{ number_format($overview['total_jobs']) }}</span>
    <span class="badge bg-success fs-6 p-2 flex-shrink-0" style="color: black; font-weight: bold;">Healthy: {{ number_format($overview['healthy']) }}</span>
    <span class="badge bg-warning fs-6 p-2 flex-shrink-0" style="color: black; font-weight: bold;">Partial: {{ number_format($overview['partial']) }}</span>
    <span class="badge bg-danger fs-6 p-2 flex-shrink-0" style="color: white; font-weight: bold;">Failed: {{ number_format($overview['failed']) }}</span>
    <span class="badge bg-info fs-6 p-2 flex-shrink-0" style="color: black; font-weight: bold;">Running: {{ number_format($overview['running'] ?? 0) }}</span>
    <span class="badge {{ ($overview['stuck'] ?? 0) > 0 ? 'bg-danger' : 'bg-secondary' }} fs-6 p-2 flex-shrink-0" style="color: white; font-weight: bold;">Stuck: {{ number_format($overview['stuck'] ?? 0) }}</span>
    <form id="cmFilterForm" method="get" action="{{ route('cron-monitor.index') }}" class="d-flex flex-nowrap align-items-center gap-2 ms-auto flex-shrink-0">
        <select name="job" class="form-select form-select-sm cm-auto-filter" style="width:160px">
            <option value="">All jobs</option>
            @foreach($jobNames as $name)
                <option value="{{ $name }}" @selected(($filters['job'] ?? '') === $name)>{{ $name }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select form-select-sm cm-auto-filter" style="width:120px">
            <option value="">All statuses</option>
            @foreach(['success','recovered','partial_success','failed','running','stuck','timed_out','missed','cancelled'] as $st)
                <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ str_replace('_',' ',$st) }}</option>
            @endforeach
        </select>
        <select name="category" class="form-select form-select-sm cm-auto-filter" style="width:120px">
            <option value="">All categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat }}" @selected(($filters['category'] ?? '') === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('cron-monitor.index') }}">Reset</a>
    </form>
</div>

<div class="card cm-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Current status by job</h5>
            <small class="text-muted">Tabulator · sortable · filterable · paginated</small>
        </div>
        <input type="text" id="cmJobsSearch" class="form-control form-control-sm" placeholder="Search jobs…" style="max-width:220px">
    </div>
    <div class="card-body p-0">
        <div id="cmJobsTable"></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card cm-card h-100 mb-0">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-0">Trend — last 30 runs</h5>
                    <small class="text-muted">
                        @if($selectedJob)
                            {{ $selectedJob }}
                            @if($avgRuntime !== null) · avg {{ $avgRuntime }}s @endif
                            @if($lastSuccess) · last success {{ $lastSuccess->finished_at?->diffForHumans() }} @endif
                        @else
                            Pick a job to chart
                        @endif
                    </small>
                </div>
                <form method="get" class="d-flex gap-2">
                    <select name="job" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
                        <option value="">Pick job for trend</option>
                        @foreach($jobNames as $name)
                            <option value="{{ $name }}" @selected($selectedJob === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @if(!empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    @if(!empty($filters['category']))<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif
                </form>
            </div>
            <div class="card-body">
                @if($selectedJob && $trend->isNotEmpty())
                    <div id="cmTrendChart"></div>
                @elseif($selectedJob)
                    <p class="text-muted text-center mb-0 py-4">No finished runs yet for this job.</p>
                @else
                    <p class="text-muted text-center mb-0 py-4">Select a job to view success %, health, and record trends.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card cm-card h-100 mb-0">
            <div class="card-header">
                <h5 class="mb-0">Recent alerts</h5>
                <small class="text-muted">Latest notifications</small>
            </div>
            <div class="card-body p-0">
                <div id="cmAlertsTable"></div>
            </div>
        </div>
    </div>
</div>

<div class="card cm-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Execution log</h5>
            <small class="text-muted">Recent runs (up to 300) · Tabulator</small>
        </div>
        <input type="text" id="cmLogsSearch" class="form-control form-control-sm" placeholder="Search logs…" style="max-width:220px">
    </div>
    <div class="card-body p-0">
        <div id="cmLogsTable"></div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('cmFilterForm');
    filterForm?.querySelectorAll('.cm-auto-filter').forEach(function (el) {
        el.addEventListener('change', function () { filterForm.submit(); });
    });

    const jobsData = @json($jobsTableData);
    const logsData = @json($logsTableData);
    const alertsData = @json($alertsTableData);

    function statusPill(status) {
        const s = String(status || '');
        let cls = 'cm-pill-bad';
        if (['success', 'recovered'].includes(s)) cls = 'cm-pill-ok';
        else if (['partial_success', 'stuck'].includes(s)) cls = 'cm-pill-warn';
        else if (s === 'running') cls = 'cm-pill-run';
        return `<span class="cm-pill ${cls}">${s.replace(/_/g, ' ')}</span>`;
    }

    function statusFormatter(cell) {
        return statusPill(cell.getValue());
    }

    function linkFormatter(cell) {
        const row = cell.getRow().getData();
        const label = cell.getValue();
        return `<a href="${row.details_url}" class="fw-semibold text-decoration-none">${label}</a>`;
    }

    const jobsTable = new Tabulator('#cmJobsTable', {
        data: jobsData,
        layout: 'fitDataStretch',
        height: '520px',
        pagination: 'local',
        paginationSize: 25,
        paginationSizeSelector: [25, 50, 100],
        movableColumns: true,
        placeholder: 'No monitored jobs found.',
        initialSort: [{ column: 'job_name', dir: 'asc' }],
        columns: [
            { title: 'Job', field: 'job_name', width: 220, formatter: linkFormatter, headerFilter: 'input' },
            { title: 'Command', field: 'command', width: 200, formatter: (c) => `<span class="text-muted small">${c.getValue() || '—'}</span>` },
            { title: 'Status', field: 'status', width: 130, hozAlign: 'center', formatter: statusFormatter, headerFilter: 'list', headerFilterParams: { valuesLookup: true, clearable: true } },
            { title: 'Health', field: 'health_score', width: 90, hozAlign: 'right', sorter: 'number' },
            { title: 'Success %', field: 'success_percentage', width: 100, hozAlign: 'right', formatter: (c) => c.getValue() == null ? '—' : Number(c.getValue()).toFixed(1) + '%' },
            { title: 'Updated', field: 'updated_records', width: 100, hozAlign: 'right', sorter: 'number', formatter: 'money', formatterParams: { precision: 0, thousand: ',', symbol: '' } },
            { title: 'Failed', field: 'failed_records', width: 90, hozAlign: 'right', sorter: 'number', formatter: (c) => {
                const v = Number(c.getValue() || 0);
                return v > 0 ? `<span class="text-danger fw-semibold">${v.toLocaleString()}</span>` : '0';
            }},
            { title: 'Chunks', field: 'chunks', width: 100, hozAlign: 'right' },
            { title: 'Duration', field: 'duration_seconds', width: 100, hozAlign: 'right', formatter: (c) => c.getValue() == null ? '—' : Number(c.getValue()).toLocaleString() + 's' },
            { title: 'Last success', field: 'last_success_at', width: 140 },
            { title: 'Retries', field: 'retry_count', width: 90, hozAlign: 'right', sorter: 'number' },
            { title: 'Memory', field: 'memory', width: 100 },
            { title: '', field: 'details_url', width: 90, hozAlign: 'center', headerSort: false, formatter: (c) => `<a class="btn btn-sm btn-outline-primary" href="${c.getValue()}">Details</a>` },
        ],
        rowFormatter: function (row) {
            const s = row.getData().status;
            if (s === 'running') row.getElement().style.background = '#eff6ff';
            else if (['failed', 'timed_out', 'missed', 'cancelled'].includes(s)) row.getElement().style.background = '#fef2f2';
            else if (['partial_success', 'stuck'].includes(s)) row.getElement().style.background = '#fffbeb';
        },
    });

    document.getElementById('cmJobsSearch')?.addEventListener('keyup', function () {
        const q = this.value.trim();
        if (!q) { jobsTable.clearFilter(); return; }
        jobsTable.setFilter([
            [
                { field: 'job_name', type: 'like', value: q },
                { field: 'command', type: 'like', value: q },
                { field: 'root_cause', type: 'like', value: q },
            ],
        ]);
    });

    new Tabulator('#cmAlertsTable', {
        data: alertsData,
        layout: 'fitColumns',
        height: '360px',
        pagination: 'local',
        paginationSize: 10,
        placeholder: 'No alerts yet.',
        columns: [
            { title: 'Type', field: 'alert_type', width: 120, formatter: (c) => {
                const sev = c.getRow().getData().severity === 'critical' ? 'cm-pill-bad' : 'cm-pill-warn';
                return `<span class="cm-pill ${sev}">${c.getValue() || '—'}</span>`;
            }},
            { title: 'Alert', field: 'title', formatter: (c) => {
                const d = c.getRow().getData();
                return `<div class="fw-semibold">${d.title || ''}</div><div class="text-muted small text-truncate" title="${(d.message || '').replace(/"/g, '&quot;')}">${d.message || ''}</div>`;
            }},
            { title: 'When', field: 'when', width: 110 },
        ],
    });

    const logsTable = new Tabulator('#cmLogsTable', {
        data: logsData,
        layout: 'fitDataStretch',
        height: '480px',
        pagination: 'local',
        paginationSize: 25,
        paginationSizeSelector: [25, 50, 100],
        movableColumns: true,
        placeholder: 'No execution logs match these filters.',
        initialSort: [{ column: 'id', dir: 'desc' }],
        columns: [
            { title: 'ID', field: 'id', width: 80, hozAlign: 'right', formatter: linkFormatter },
            { title: 'Job', field: 'job_name', width: 200, headerFilter: 'input' },
            { title: 'Status', field: 'status', width: 130, hozAlign: 'center', formatter: statusFormatter, headerFilter: 'list', headerFilterParams: { valuesLookup: true, clearable: true } },
            { title: 'Category', field: 'failure_category', width: 120, formatter: (c) => c.getValue() || '—' },
            { title: 'Started', field: 'started_at', width: 160 },
            { title: 'Duration', field: 'duration_seconds', width: 100, hozAlign: 'right', formatter: (c) => c.getValue() == null ? '—' : Number(c.getValue()).toLocaleString() + 's' },
            { title: 'Retries', field: 'retry_count', width: 90, hozAlign: 'right' },
            { title: 'Updated', field: 'updated_records', width: 100, hozAlign: 'right' },
            { title: 'Failed', field: 'failed_records', width: 90, hozAlign: 'right', formatter: (c) => {
                const v = Number(c.getValue() || 0);
                return v > 0 ? `<span class="text-danger fw-semibold">${v.toLocaleString()}</span>` : '0';
            }},
            { title: 'Success %', field: 'success_percentage', width: 100, hozAlign: 'right', formatter: (c) => c.getValue() == null ? '—' : Number(c.getValue()).toFixed(1) + '%' },
            { title: 'Health', field: 'health_score', width: 90, hozAlign: 'right' },
            { title: 'API latency', field: 'api_latency_ms_avg', width: 110, hozAlign: 'right', formatter: (c) => c.getValue() ? Number(c.getValue()).toLocaleString() + 'ms' : '—' },
        ],
        rowFormatter: function (row) {
            const s = row.getData().status;
            if (s === 'running') row.getElement().style.background = '#eff6ff';
            else if (['failed', 'timed_out', 'missed', 'cancelled'].includes(s)) row.getElement().style.background = '#fef2f2';
            else if (['partial_success', 'stuck'].includes(s)) row.getElement().style.background = '#fffbeb';
        },
    });

    document.getElementById('cmLogsSearch')?.addEventListener('keyup', function () {
        if (!this.value) { logsTable.clearFilter(); return; }
        logsTable.setFilter([
            [
                { field: 'job_name', type: 'like', value: this.value },
                { field: 'failure_category', type: 'like', value: this.value },
                { field: 'status', type: 'like', value: this.value },
            ],
        ]);
    });

    @if($selectedJob && $trend->isNotEmpty())
    if (typeof Highcharts !== 'undefined') {
        const trendLabels = @json($trend->map(fn ($r) => optional($r->started_at)->format('m/d H:i'))->values());
        const trendSuccess = @json($trend->pluck('success_percentage')->map(fn ($v) => (float) $v)->values());
        const trendHealth = @json($trend->pluck('health_score')->map(fn ($v) => (float) $v)->values());
        const trendUpdated = @json($trend->pluck('updated_records')->map(fn ($v) => (int) $v)->values());
        const trendFailed = @json($trend->pluck('failed_records')->map(fn ($v) => (int) $v)->values());

        Highcharts.chart('cmTrendChart', {
            chart: { zoomType: 'x', backgroundColor: 'transparent', height: 280 },
            title: { text: null },
            xAxis: {
                categories: trendLabels,
                labels: { rotation: -35, style: { fontSize: '10px', color: '#6b7280' } }
            },
            yAxis: [
                { title: { text: 'Success % / Health' }, max: 100, gridLineColor: '#f3f4f6' },
                { title: { text: 'Records' }, opposite: true, gridLineWidth: 0 }
            ],
            tooltip: { shared: true },
            series: [
                { name: 'Success %', data: trendSuccess, color: '#059669' },
                { name: 'Health', data: trendHealth, color: '#2563eb' },
                { name: 'Updated', data: trendUpdated, yAxis: 1, color: '#7c3aed', type: 'column' },
                { name: 'Failed', data: trendFailed, yAxis: 1, color: '#dc2626', type: 'column' }
            ],
            credits: { enabled: false }
        });
    }
    @endif
});
</script>
@endsection
