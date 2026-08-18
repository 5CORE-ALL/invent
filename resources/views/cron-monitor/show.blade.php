@extends('layouts.vertical', ['title' => $title ?? 'Cron Run'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    .cm-stat-card { border: 1px solid #e5e7eb; box-shadow: none; }
    .cm-stat-card .card-body { padding: .85rem 1rem; }
    .cm-stat-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
    .cm-stat-value { font-size: 1.25rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .cm-stat-value.ok { color: #059669; }
    .cm-stat-value.warn { color: #d97706; }
    .cm-stat-value.bad { color: #dc2626; }
    .cm-card { border: 1px solid #e5e7eb; box-shadow: none; }
    .cm-card .card-header { background: #f8fafc; border-bottom: 1px solid #e5e7eb; }
    .cm-meta-table { width: 100%; margin: 0; font-size: .84rem; }
    .cm-meta-table th { width: 38%; color: #6b7280; font-weight: 600; padding: .4rem 0; border: 0; vertical-align: top; }
    .cm-meta-table td { padding: .4rem 0; border: 0; font-variant-numeric: tabular-nums; word-break: break-word; }
    .cm-cmd { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .78rem; color: #4b5563; }
    .cm-step {
        display: flex; align-items: center; gap: .5rem; padding: .45rem .65rem; border-radius: 6px;
        border: 1px solid #e5e7eb; margin-bottom: .4rem; font-size: .84rem; background: #fff;
    }
    .cm-step.ok { border-color: #a7f3d0; background: #ecfdf5; color: #065f46; }
    .cm-step.warn { border-color: #fde68a; background: #fffbeb; color: #92400e; }
    .cm-step.bad { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
    .cm-pill {
        display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .55rem; border-radius: 999px;
        font-size: .75rem; font-weight: 650; text-transform: capitalize; border: 1px solid transparent;
    }
    .cm-pill-ok { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .cm-pill-warn { background: #fffbeb; color: #d97706; border-color: #fde68a; }
    .cm-pill-bad { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .cm-pill-run { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .cm-chunk-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
    @media (max-width: 767px) { .cm-chunk-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .cm-chunk-cell { border: 1px solid #e5e7eb; border-radius: 8px; padding: .75rem .85rem; background: #fff; }
    .cm-chunk-cell .label { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
    .cm-chunk-cell .value { font-size: 1.05rem; font-weight: 700; margin-top: .2rem; font-variant-numeric: tabular-nums; }
    .tabulator { border: 1px solid #e5e7eb; }
    .tabulator .tabulator-header { background: #eef2f7; }
    .tabulator .tabulator-col { background: #eef2f7 !important; }
</style>
@endsection

@section('content')
@php
    $pill = match ($log->status) {
        'success', 'recovered' => 'ok',
        'partial_success', 'stuck' => 'warn',
        'running' => 'run',
        default => 'bad',
    };
    $pillLabel = str_replace('_', ' ', (string) $log->status);
    $chunkProgress = is_array($log->meta) ? ($log->meta['chunk_progress'] ?? null) : null;
    $fmtEta = function ($seconds): string {
        if ($seconds === null || $seconds === '') return '—';
        $seconds = (int) $seconds;
        if ($seconds < 60) return $seconds . 's';
        return gmdate($seconds >= 3600 ? 'H:i:s' : 'i:s', $seconds);
    };
@endphp

@include('layouts.shared.page-title', [
    'page_title' => $log->job_name,
    'sub_title' => 'Run #'.$log->id.($log->command ? ' · '.$log->command : ''),
])

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a href="{{ route('cron-monitor.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Dashboard</a>
    <form method="post" action="{{ route('cron-monitor.retry', $log->id) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">Retry Job</button></form>
    <form method="post" action="{{ route('cron-monitor.resume', $log->id) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Resume</button></form>
    <form method="post" action="{{ route('cron-monitor.retry-failures', $log->id) }}">@csrf<button class="btn btn-sm btn-warning" type="submit">Retry Failed Records</button></form>
    @if($log->isCancellable())
        <form method="post" action="{{ route('cron-monitor.cancel', $log->id) }}">@csrf<button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Cancel this run?')">Cancel Running</button></form>
    @endif
    @if($log->command)
        <form method="post" action="{{ route('cron-monitor.unlock') }}">@csrf<input type="hidden" name="command" value="{{ $log->command }}"><button class="btn btn-sm btn-outline-danger" type="submit">Unlock</button></form>
    @endif
    <a href="{{ route('cron-monitor.download', $log->id) }}" class="btn btn-sm btn-outline-secondary">Download Log</a>
    <span class="cm-pill cm-pill-{{ $pill }}">{{ $pillLabel }}</span>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card cm-stat-card h-100"><div class="card-body">
            <div class="cm-stat-label">Health</div>
            <div class="cm-stat-value">{{ $log->health_score ?? '—' }} <span class="fs-6 fw-normal text-muted">/ 100</span></div>
            <div class="text-muted small">{{ $log->health_label ?: '—' }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card cm-stat-card h-100"><div class="card-body">
            <div class="cm-stat-label">Success %</div>
            <div class="cm-stat-value {{ ($log->success_percentage ?? 0) >= 95 ? 'ok' : (($log->success_percentage ?? 0) >= 60 ? 'warn' : 'bad') }}">
                {{ $log->success_percentage !== null ? number_format((float) $log->success_percentage, 1).'%' : '—' }}
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card cm-stat-card h-100"><div class="card-body">
            <div class="cm-stat-label">Updated / Failed</div>
            <div class="cm-stat-value">
                {{ number_format((int) $log->updated_records) }}
                <span class="fs-6 fw-normal {{ $log->failed_records > 0 ? 'text-danger' : 'text-muted' }}">/ {{ number_format((int) $log->failed_records) }}</span>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card cm-stat-card h-100"><div class="card-body">
            <div class="cm-stat-label">Duration</div>
            <div class="cm-stat-value">{{ $log->duration_seconds !== null ? number_format((int) $log->duration_seconds).'s' : '—' }}</div>
            @if($avgRuntime)<div class="text-muted small">avg {{ $avgRuntime }}s</div>@endif
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card cm-card h-100">
            <div class="card-header"><h6 class="mb-0">Run details</h6></div>
            <div class="card-body">
                <table class="cm-meta-table">
                    <tr><th>Command</th><td class="cm-cmd">{{ $log->command ?: '—' }}</td></tr>
                    <tr><th>Started</th><td>{{ $log->started_at ?: '—' }}</td></tr>
                    <tr><th>Finished</th><td>{{ $log->finished_at ?: '—' }}</td></tr>
                    <tr><th>Retries</th><td>{{ (int) $log->retry_count }}</td></tr>
                    <tr><th>Recovery</th><td>{{ $log->recovery_status ?: '—' }}</td></tr>
                    <tr><th>Category</th><td>{{ $log->failure_category ?: '—' }}</td></tr>
                    <tr><th>Resume from</th><td>{{ $log->resume_from ?? '—' }}</td></tr>
                    <tr><th>Consecutive fails</th><td>{{ (int) $log->consecutive_failures }}</td></tr>
                    <tr><th>Last success</th><td>{{ $lastSuccess?->finished_at ?: '—' }}</td></tr>
                    <tr><th>Server</th><td>{{ $log->execution_server ?: '—' }}</td></tr>
                    <tr><th>Memory</th><td>{{ $log->memory_usage ?: '—' }}</td></tr>
                    <tr><th>API calls</th><td>{{ (int) $log->api_calls }}@if($log->api_latency_ms_avg) <span class="text-muted">(avg {{ $log->api_latency_ms_avg }}ms)</span>@endif</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card cm-card h-100">
            <div class="card-header"><h6 class="mb-0">Execution checklist</h6></div>
            <div class="card-body">
                <div class="cm-step ok">Cron started</div>
                <div class="cm-step {{ $log->api_connected ? 'ok' : 'bad' }}">{{ $log->api_connected ? 'API connected' : 'API not connected' }}</div>
                <div class="cm-step {{ $log->fetched_records > 0 ? 'ok' : 'warn' }}">{{ number_format((int) $log->fetched_records) }} records fetched</div>
                <div class="cm-step {{ $log->processed_records > 0 ? 'ok' : 'warn' }}">{{ number_format((int) $log->processed_records) }} records processed</div>
                <div class="cm-step {{ $log->updated_records > 0 ? 'ok' : 'warn' }}">{{ number_format((int) $log->updated_records) }} records updated</div>
                @if($log->skipped_records > 0)<div class="cm-step warn">{{ number_format((int) $log->skipped_records) }} skipped</div>@endif
                @if($log->failed_records > 0)<div class="cm-step bad">{{ number_format((int) $log->failed_records) }} failed</div>@endif
                <div class="cm-step {{ ($log->success_percentage ?? 0) >= 95 ? 'ok' : (($log->success_percentage ?? 0) >= 60 ? 'warn' : 'bad') }}">
                    Success rate {{ $log->success_percentage !== null ? number_format((float) $log->success_percentage, 1).'%' : '—' }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card cm-card h-100">
            <div class="card-header"><h6 class="mb-0">Root cause & anomalies</h6></div>
            <div class="card-body">
                @if($log->root_cause)<div class="alert alert-secondary small py-2">{{ $log->root_cause }}</div>@endif
                @if($log->validation_message)
                    <div class="alert alert-warning small py-2">{{ $log->validation_message }}</div>
                @else
                    <div class="alert alert-success small py-2 mb-2">Validation passed</div>
                @endif
                @if($log->error_message)<div class="alert alert-danger small py-2">{{ $log->error_message }}</div>@endif
                @forelse(($log->anomalies ?? []) as $anomaly)
                    <div class="alert alert-{{ ($anomaly['severity'] ?? '') === 'critical' ? 'danger' : 'warning' }} small py-2 mb-2">{{ $anomaly['message'] ?? 'Anomaly' }}</div>
                @empty
                    <p class="text-muted small mb-0">No anomalies detected.</p>
                @endforelse
                @if($log->checkpoint)
                    <p class="small text-muted mb-0 mt-2">Checkpoint: <code class="cm-cmd">{{ json_encode($log->checkpoint) }}</code></p>
                @endif
            </div>
        </div>
    </div>
</div>

@if(is_array($chunkProgress))
<div class="card cm-card mb-3">
    <div class="card-header"><h6 class="mb-0">Chunk progress</h6></div>
    <div class="card-body">
        <div class="cm-chunk-grid">
            <div class="cm-chunk-cell"><div class="label">Total chunks</div><div class="value">{{ $chunkProgress['total_chunks'] ?? '—' }}</div></div>
            <div class="cm-chunk-cell"><div class="label">Completed</div><div class="value">{{ $chunkProgress['completed'] ?? '—' }}</div></div>
            <div class="cm-chunk-cell"><div class="label">Failed chunks</div><div class="value {{ ($chunkProgress['failed'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $chunkProgress['failed'] ?? '—' }}</div></div>
            <div class="cm-chunk-cell"><div class="label">Current chunk</div><div class="value">{{ $chunkProgress['current'] ?? '—' }}</div></div>
            <div class="cm-chunk-cell"><div class="label">Resume point</div><div class="value" style="font-size:.95rem">{{ $chunkProgress['resume_point'] ?? ($log->resume_from ?? '—') }}</div></div>
            <div class="cm-chunk-cell"><div class="label">Avg chunk time</div><div class="value">@if(isset($chunkProgress['avg_chunk_ms'])){{ number_format((int) $chunkProgress['avg_chunk_ms']) }} ms@else — @endif</div></div>
            <div class="cm-chunk-cell"><div class="label">ETA</div><div class="value">{{ isset($chunkProgress['eta_seconds']) ? $fmtEta($chunkProgress['eta_seconds']) : '—' }}</div></div>
            <div class="cm-chunk-cell"><div class="label">Retries</div><div class="value">{{ (int) $log->retry_count }}</div></div>
            <div class="cm-chunk-cell"><div class="label">Memory</div><div class="value" style="font-size:.95rem">{{ $chunkProgress['memory_usage'] ?? $log->memory_usage ?? '—' }}</div></div>
        </div>
    </div>
</div>
@endif

<div class="card cm-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0">Retry queue (unresolved)</h6>
            <small class="text-muted">Tabulator</small>
        </div>
        <span class="text-muted small">{{ count($retryTableData) }} items</span>
    </div>
    <div class="card-body p-0">
        <div id="cmRetryTable"></div>
    </div>
</div>

<div class="card cm-card mb-3">
    <div class="card-header">
        <h6 class="mb-0">Failed records</h6>
        <small class="text-muted">Tabulator · per-record failures</small>
    </div>
    <div class="card-body p-0">
        <div id="cmFailuresTable"></div>
    </div>
</div>

<div class="card cm-card mb-3">
    <div class="card-header">
        <h6 class="mb-0">Last 30 runs — {{ $log->job_name }}</h6>
        <small class="text-muted">Tabulator · history</small>
    </div>
    <div class="card-body p-0">
        <div id="cmHistoryTable"></div>
    </div>
</div>

@if($log->exception)
<div class="card cm-card border-danger mb-3">
    <div class="card-header"><h6 class="mb-0 text-danger">Exception</h6></div>
    <div class="card-body">
        <pre class="small mb-0 cm-cmd" style="white-space:pre-wrap;max-height:360px;overflow:auto;">{{ $log->exception }}</pre>
    </div>
</div>
@endif
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const retryData = @json($retryTableData);
    const failuresData = @json($failuresTableData);
    const historyData = @json($historyTableData);

    function statusPill(status) {
        const s = String(status || '');
        let cls = 'cm-pill-bad';
        if (['success', 'recovered'].includes(s)) cls = 'cm-pill-ok';
        else if (['partial_success', 'stuck'].includes(s)) cls = 'cm-pill-warn';
        else if (s === 'running') cls = 'cm-pill-run';
        return `<span class="cm-pill ${cls}">${s.replace(/_/g, ' ')}</span>`;
    }

    new Tabulator('#cmRetryTable', {
        data: retryData,
        layout: 'fitDataStretch',
        height: retryData.length ? '320px' : '120px',
        pagination: 'local',
        paginationSize: 25,
        placeholder: 'No unresolved failures.',
        columns: [
            { title: 'SKU', field: 'sku', width: 160, formatter: (c) => `<span class="cm-cmd">${c.getValue() || '—'}</span>` },
            { title: 'Category', field: 'failure_category', width: 120, formatter: (c) => c.getValue() || '—' },
            { title: 'Recoverable', field: 'recoverable', width: 110, hozAlign: 'center' },
            { title: 'Root cause', field: 'root_cause', minWidth: 220 },
            { title: 'Retries', field: 'retry_count', width: 90, hozAlign: 'right' },
            { title: 'HTTP', field: 'http_status', width: 90, hozAlign: 'right', formatter: (c) => c.getValue() || '—' },
        ],
    });

    new Tabulator('#cmFailuresTable', {
        data: failuresData,
        layout: 'fitDataStretch',
        height: failuresData.length ? '360px' : '120px',
        pagination: 'local',
        paginationSize: 25,
        placeholder: 'No per-record failures logged.',
        columns: [
            { title: 'SKU', field: 'sku', width: 150, formatter: (c) => `<span class="cm-cmd">${c.getValue() || '—'}</span>` },
            { title: 'Marketplace', field: 'marketplace', width: 120, formatter: (c) => c.getValue() || '—' },
            { title: 'Category', field: 'failure_category', width: 120, formatter: (c) => c.getValue() || '—' },
            { title: 'Reason', field: 'failure_reason', minWidth: 240 },
            { title: 'Retries', field: 'retry_count', width: 90, hozAlign: 'right' },
            { title: 'Resolved', field: 'resolved', width: 100, hozAlign: 'center' },
            { title: 'When', field: 'created_at', width: 160 },
        ],
        rowFormatter: function (row) {
            if (row.getData().resolved === 'No') row.getElement().style.background = '#fef2f2';
        },
    });

    new Tabulator('#cmHistoryTable', {
        data: historyData,
        layout: 'fitDataStretch',
        height: '360px',
        pagination: 'local',
        paginationSize: 30,
        placeholder: 'No history for this job.',
        columns: [
            { title: 'ID', field: 'id', width: 80, hozAlign: 'right', formatter: (c) => {
                const d = c.getRow().getData();
                return `<a class="fw-semibold text-decoration-none" href="${d.details_url}">#${c.getValue()}</a>`;
            }},
            { title: 'Status', field: 'status', width: 130, hozAlign: 'center', formatter: (c) => statusPill(c.getValue()) },
            { title: 'Started', field: 'started_at', width: 160 },
            { title: 'Duration', field: 'duration_seconds', width: 100, hozAlign: 'right', formatter: (c) => c.getValue() == null ? '—' : Number(c.getValue()).toLocaleString() + 's' },
            { title: 'Updated', field: 'updated_records', width: 100, hozAlign: 'right' },
            { title: 'Failed', field: 'failed_records', width: 90, hozAlign: 'right', formatter: (c) => {
                const v = Number(c.getValue() || 0);
                return v > 0 ? `<span class="text-danger fw-semibold">${v.toLocaleString()}</span>` : '0';
            }},
            { title: 'Success %', field: 'success_percentage', width: 100, hozAlign: 'right', formatter: (c) => c.getValue() == null ? '—' : Math.round(Number(c.getValue())) + '%' },
            { title: 'Health', field: 'health_score', width: 90, hozAlign: 'right' },
        ],
        rowFormatter: function (row) {
            if (row.getData().is_current) row.getElement().style.background = '#eff6ff';
        },
    });
});
</script>
@endsection
