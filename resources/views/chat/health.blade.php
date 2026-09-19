@extends('layouts.vertical', ['title' => 'Chat Health'])

@section('content')
    <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="mb-0">Chat Health</h3>
                <p class="text-muted mb-0">Admin diagnostics. Database is the source of truth; WebSocket/Reverb is optional.</p>
            </div>
            <a href="{{ route('chat.index') }}" class="btn btn-light">Back to Chat</a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Realtime</div>
                    <div class="fs-5 fw-bold">{{ $realtime }}</div>
                    <div class="small">Driver: {{ $driver }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Active now</div>
                    <div class="fs-5 fw-bold">{{ $active_connections }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Messages today</div>
                    <div class="fs-5 fw-bold">{{ $messages_today }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Failed deliveries (24h)</div>
                    <div class="fs-5 fw-bold">{{ $failed_messages }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Failed notifications</div>
                    <div class="fs-5 fw-bold">{{ $failed_notifications }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Pending jobs</div>
                    <div class="fs-5 fw-bold">{{ $pending_jobs }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Queue failures</div>
                    <div class="fs-5 fw-bold">{{ $queue_failures }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Reconnects (24h)</div>
                    <div class="fs-5 fw-bold">{{ $reconnects_today }}</div>
                </div></div>
            </div>
        </div>

        <form method="post" action="{{ route('chat.health.retry') }}" class="mb-4">
            @csrf
            <input type="hidden" name="action" value="retry_failed_jobs">
            <button class="btn btn-outline-primary" type="submit">Retry failed queue jobs</button>
        </form>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Recent chat errors</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Type</th><th>User</th><th>When</th></tr></thead>
                            <tbody>
                            @forelse ($recent_errors as $row)
                                <tr>
                                    <td>{{ $row->type }}</td>
                                    <td>{{ $row->user_id }}</td>
                                    <td>{{ $row->created_at }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">None</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Recent audit</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Action</th><th>Actor</th><th>When</th></tr></thead>
                            <tbody>
                            @forelse ($recent_audits as $row)
                                <tr>
                                    <td>{{ $row->action }}</td>
                                    <td>{{ $row->actor_id }}</td>
                                    <td>{{ $row->created_at }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">None</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
