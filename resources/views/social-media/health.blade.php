@extends('layouts.vertical', ['title' => 'Social Media Sync Health'])

@section('content')
    <h4 class="page-title">Sync / API health</h4>
    @include('social-media._nav')
    <div class="row">
        @forelse ($accounts as $account)
            <div class="col-md-6 col-xl-4">
                <div class="card"><div class="card-body">
                    <h5 class="mb-1">{{ ucfirst($account->platform) }} — {{ $account->account_name }}</h5>
                    <div>Connection: {{ $account->status }}</div>
                    <div>Token: {{ ucfirst($account->tokenStatus()) }}@if($account->token_expires_at) · expires {{ $account->token_expires_at->toDayDateTimeString() }}@endif</div>
                    <div>Last success: {{ $account->last_sync_status === 'success' ? optional($account->last_synced_at)->toDayDateTimeString() : '—' }}</div>
                    <div>Last result: {{ $account->last_sync_status ?? 'No sync yet' }}</div>
                    @if ($account->last_sync_error)
                        <div class="text-warning mt-1">{{ $account->last_sync_error }}</div>
                    @endif
                </div></div>
            </div>
        @empty
            <div class="col-12"><div class="card"><div class="card-body text-muted">No accounts to monitor.</div></div></div>
        @endforelse
    </div>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm">
            <thead>
                <tr><th>Started</th><th>Account</th><th>Type</th><th>Status</th><th>Records</th><th>Duration</th><th>Message</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ optional($log->started_at)->toDayDateTimeString() }}</td>
                        <td>{{ $log->account->account_name ?? $log->platform }}</td>
                        <td>{{ $log->sync_type }}</td>
                        <td>{{ str_replace('_', ' ', $log->status) }}</td>
                        <td>{{ $log->records_processed }}</td>
                        <td>
                            @if ($log->completed_at)
                                {{ $log->started_at->diffInSeconds($log->completed_at) }}s
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $log->error_message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No synchronization logs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $logs->links() }}
    </div></div>
@endsection
