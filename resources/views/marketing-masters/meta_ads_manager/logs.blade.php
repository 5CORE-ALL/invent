@extends('layouts.vertical', ['title' => 'Meta Ads Manager - Logs', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Marketing Masters', 'page_title' => 'Meta Ads Manager - Logs'])

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a class="nav-link {{ $type == 'actions' ? 'active' : '' }}" href="{{ route('meta.ads.manager.logs', ['type' => 'actions']) }}">Action Logs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $type == 'sync' ? 'active' : '' }}" href="{{ route('meta.ads.manager.logs', ['type' => 'sync']) }}">Sync Runs</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @if($type == 'sync')
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Started At</th>
                                        <th>Finished At</th>
                                        <th>Status</th>
                                        <th>Type</th>
                                        <th>Accounts</th>
                                        <th>Campaigns</th>
                                        <th>AdSets</th>
                                        <th>Ads</th>
                                        <th>Insights</th>
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr>
                                            <td>{{ $log->id }}</td>
                                            <td>{{ $log->started_at->format('Y-m-d H:i:s') }}</td>
                                            <td>{{ $log->finished_at ? $log->finished_at->format('Y-m-d H:i:s') : '-' }}</td>
                                            <td>
                                                @if($log->status == 'completed')
                                                    <span class="badge bg-success">Completed</span>
                                                @elseif($log->status == 'failed')
                                                    <span class="badge bg-danger">Failed</span>
                                                @else
                                                    <span class="badge bg-warning">Running</span>
                                                @endif
                                            </td>
                                            <td>{{ $log->sync_type }}</td>
                                            <td>{{ $log->accounts_synced }}</td>
                                            <td>{{ $log->campaigns_synced }}</td>
                                            <td>{{ $log->adsets_synced }}</td>
                                            <td>{{ $log->ads_synced }}</td>
                                            <td>{{ $log->insights_synced }}</td>
                                            <td>{{ $log->error_summary ? substr($log->error_summary, 0, 50) . '...' : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            {{ $logs->links() }}
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Action Type</th>
                                        <th>Entity Type</th>
                                        <th>Entity Meta ID</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr>
                                            <td>{{ $log->id }}</td>
                                            <td>{{ $log->action_type }}</td>
                                            <td>{{ ucfirst($log->entity_type) }}</td>
                                            <td>{{ $log->entity_meta_id }}</td>
                                            <td>
                                                @if($log->status == 'success')
                                                    <span class="badge bg-success">Success</span>
                                                @else
                                                    <span class="badge bg-danger">Failed</span>
                                                @endif
                                            </td>
                                            <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                            <td>{{ $log->error_message ? substr($log->error_message, 0, 50) . '...' : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

