@extends('layouts.vertical', ['title' => $title ?? 'Cron Run'])

@section('css')
<style>
    .cm-step { padding: .5rem .75rem; border-left: 3px solid #dee2e6; margin-bottom: .35rem; }
    .cm-step.ok { border-color: #198754; background: rgba(25,135,84,.06); }
    .cm-step.warn { border-color: #ffc107; background: rgba(255,193,7,.08); }
    .cm-step.bad { border-color: #dc3545; background: rgba(220,53,69,.06); }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', [
    'page_title' => $log->job_name,
    'sub_title' => 'Run #'.$log->id
])

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
    <a href="{{ route('cron-monitor.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Back to dashboard</a>
    <form method="post" action="{{ route('cron-monitor.retry', $log->id) }}">@csrf<button class="btn btn-sm btn-primary">Retry Job</button></form>
    <form method="post" action="{{ route('cron-monitor.resume', $log->id) }}">@csrf<button class="btn btn-sm btn-outline-primary">Resume</button></form>
    <form method="post" action="{{ route('cron-monitor.retry-failures', $log->id) }}">@csrf<button class="btn btn-sm btn-warning">Retry Failed Records</button></form>
    @if($log->isCancellable())
        <form method="post" action="{{ route('cron-monitor.cancel', $log->id) }}">@csrf<button class="btn btn-sm btn-danger" onclick="return confirm('Cancel this run?')">Cancel Running</button></form>
    @endif
    @if($log->command)
        <form method="post" action="{{ route('cron-monitor.unlock') }}">@csrf<input type="hidden" name="command" value="{{ $log->command }}"><button class="btn btn-sm btn-outline-danger">Unlock</button></form>
    @endif
    <a href="{{ route('cron-monitor.download', $log->id) }}" class="btn btn-sm btn-outline-secondary">Download Log</a>
</div>

@php
    $badge = match($log->status) {
        'success', 'recovered' => 'success',
        'partial_success', 'stuck' => 'warning',
        'running' => 'info',
        default => 'danger',
    };
@endphp

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small text-uppercase">Overall status</div>
                        <span class="badge bg-{{ $badge }} fs-6">{{ strtoupper(str_replace('_', ' ', $log->status)) }}</span>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Health</div>
                        <div class="fs-4 fw-semibold">{{ $log->health_score ?? '—' }}</div>
                        <div class="small text-muted">{{ $log->health_label }}</div>
                    </div>
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-5">Command</dt><dd class="col-7">{{ $log->command ?: '—' }}</dd>
                    <dt class="col-5">Started</dt><dd class="col-7">{{ $log->started_at }}</dd>
                    <dt class="col-5">Finished</dt><dd class="col-7">{{ $log->finished_at ?: '—' }}</dd>
                    <dt class="col-5">Duration</dt><dd class="col-7">{{ $log->duration_seconds }}s @if($avgRuntime) <span class="text-muted">(avg {{ $avgRuntime }}s)</span>@endif</dd>
                    <dt class="col-5">Success %</dt><dd class="col-7">{{ $log->success_percentage }}%</dd>
                    <dt class="col-5">Retries</dt><dd class="col-7">{{ $log->retry_count }} @if($log->last_retry_at)<span class="text-muted">(last {{ $log->last_retry_at->diffForHumans() }})</span>@endif</dd>
                    <dt class="col-5">Recovery</dt><dd class="col-7">{{ $log->recovery_status }}</dd>
                    <dt class="col-5">Category</dt><dd class="col-7">{{ $log->failure_category ?: '—' }}</dd>
                    <dt class="col-5">Resume from</dt><dd class="col-7">{{ $log->resume_from ?? '—' }}</dd>
                    <dt class="col-5">Consecutive fails</dt><dd class="col-7">{{ $log->consecutive_failures }}</dd>
                    <dt class="col-5">Last success</dt><dd class="col-7">{{ $lastSuccess?->finished_at ?: '—' }}</dd>
                    <dt class="col-5">Server</dt><dd class="col-7">{{ $log->execution_server }}</dd>
                    <dt class="col-5">Memory</dt><dd class="col-7">{{ $log->memory_usage }}</dd>
                    <dt class="col-5">CPU</dt><dd class="col-7">{{ $log->cpu_time_ms ? $log->cpu_time_ms.'ms' : '—' }}</dd>
                    <dt class="col-5">API calls</dt><dd class="col-7">{{ $log->api_calls }} @if($log->api_latency_ms_avg) <span class="text-muted">(avg {{ $log->api_latency_ms_avg }}ms)</span>@endif</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0">Execution checklist</h6></div>
            <div class="card-body">
                <div class="cm-step ok">✅ Cron Started</div>
                <div class="cm-step {{ $log->api_connected ? 'ok' : 'bad' }}">{{ $log->api_connected ? '✅' : '❌' }} API Connected</div>
                <div class="cm-step {{ $log->fetched_records > 0 ? 'ok' : 'warn' }}">{{ $log->fetched_records > 0 ? '✅' : '⚠' }} {{ number_format($log->fetched_records) }} Records Fetched</div>
                <div class="cm-step {{ $log->processed_records > 0 ? 'ok' : 'warn' }}">{{ $log->processed_records > 0 ? '✅' : '⚠' }} {{ number_format($log->processed_records) }} Records Processed</div>
                <div class="cm-step {{ $log->updated_records > 0 ? 'ok' : 'warn' }}">{{ $log->updated_records > 0 ? '✅' : '⚠' }} {{ number_format($log->updated_records) }} Records Updated</div>
                @if($log->inserted_records > 0)
                    <div class="cm-step ok">✅ {{ number_format($log->inserted_records) }} Inserted</div>
                @endif
                @if($log->skipped_records > 0)
                    <div class="cm-step warn">⏭ {{ number_format($log->skipped_records) }} Skipped</div>
                @endif
                @if($log->failed_records > 0)
                    <div class="cm-step bad">⚠ {{ number_format($log->failed_records) }} Failed</div>
                @endif
                <div class="cm-step {{ ($log->success_percentage ?? 0) >= 95 ? 'ok' : (($log->success_percentage ?? 0) >= 60 ? 'warn' : 'bad') }}">
                    Success Rate = {{ $log->success_percentage }}%
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0">Root cause & anomalies</h6></div>
            <div class="card-body">
                @if($log->root_cause)
                    <div class="alert alert-secondary small"><strong>Root Cause:</strong> {{ $log->root_cause }}</div>
                @endif
                @if($log->validation_message)
                    <div class="alert alert-warning small">{{ $log->validation_message }}</div>
                @else
                    <div class="alert alert-success small mb-2">Validation passed</div>
                @endif
                @if($log->error_message)
                    <div class="alert alert-danger small">{{ $log->error_message }}</div>
                @endif
                @forelse(($log->anomalies ?? []) as $anomaly)
                    <div class="alert alert-{{ ($anomaly['severity'] ?? '') === 'critical' ? 'danger' : 'warning' }} small">
                        {{ $anomaly['message'] ?? 'Anomaly' }}
                    </div>
                @empty
                    <p class="text-muted small mb-0">No anomalies detected.</p>
                @endforelse
                @if($log->checkpoint)
                    <p class="small text-muted mb-0 mt-2">Checkpoint: <code>{{ json_encode($log->checkpoint) }}</code></p>
                @endif
                @if($log->expected_records !== null)
                    <p class="small text-muted mb-0 mt-2">Expected records: {{ number_format($log->expected_records) }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between"><h6 class="mb-0">Retry queue (unresolved)</h6><span class="small text-muted">{{ $retryQueue->count() }} items</span></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Recoverable</th>
                    <th>Root cause</th>
                    <th>Retries</th>
                    <th>HTTP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($retryQueue as $f)
                    <tr>
                        <td>{{ $f->sku ?: '—' }}</td>
                        <td>{{ $f->failure_category ?: '—' }}</td>
                        <td>{{ $f->recoverable ? 'Yes' : 'No' }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($f->root_cause ?: $f->failure_reason, 80) }}</td>
                        <td>{{ $f->retry_count }}</td>
                        <td>{{ $f->http_status ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted p-3">No unresolved failures.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header"><h6 class="mb-0">Failed records</h6></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Marketplace</th>
                    <th>Category</th>
                    <th>Reason</th>
                    <th>Retries</th>
                    <th>Resolved</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @forelse($log->failures as $f)
                    <tr>
                        <td>{{ $f->sku ?: '—' }}</td>
                        <td>{{ $f->marketplace ?: '—' }}</td>
                        <td>{{ $f->failure_category ?: '—' }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($f->failure_reason, 120) }}</td>
                        <td>{{ $f->retry_count }}</td>
                        <td>{{ $f->resolved ? 'Yes' : 'No' }}</td>
                        <td class="small">{{ $f->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted p-3">No per-record failures logged.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h6 class="mb-0">Last 30 runs — {{ $log->job_name }}</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Duration</th>
                    <th>Updated</th>
                    <th>Failed</th>
                    <th>Success %</th>
                    <th>Health</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history as $h)
                    <tr class="{{ $h->id === $log->id ? 'table-active' : '' }}">
                        <td><a href="{{ route('cron-monitor.show', $h->id) }}">#{{ $h->id }}</a></td>
                        <td>{{ str_replace('_', ' ', $h->status) }}</td>
                        <td>{{ $h->started_at }}</td>
                        <td>{{ $h->duration_seconds }}s</td>
                        <td>{{ number_format($h->updated_records) }}</td>
                        <td>{{ number_format($h->failed_records) }}</td>
                        <td>{{ $h->success_percentage }}%</td>
                        <td>{{ $h->health_score }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($log->exception)
<div class="card shadow-sm mt-3 border-danger">
    <div class="card-header text-danger"><h6 class="mb-0">Exception</h6></div>
    <div class="card-body">
        <pre class="small mb-0" style="white-space: pre-wrap">{{ $log->exception }}</pre>
    </div>
</div>
@endif
@endsection
