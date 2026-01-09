@extends('layouts.vertical', ['title' => 'Meta Ads Manager - Automation', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Marketing Masters', 'page_title' => 'Meta Ads Manager - Automation Rules'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <h5>Automation Rules</h5>
                        <a href="{{ route('meta.ads.manager.automation.create') }}" class="btn btn-primary">Create Rule</a>
                    </div>
                    
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    @if($rules->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Entity Type</th>
                                        <th>Status</th>
                                        <th>Last Run</th>
                                        <th>Total Runs</th>
                                        <th>Actions Taken</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rules as $rule)
                                        <tr>
                                            <td>{{ $rule->name }}</td>
                                            <td>{{ $rule->description ?? '-' }}</td>
                                            <td>{{ ucfirst($rule->entity_type) }}</td>
                                            <td>
                                                @if($rule->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>{{ $rule->last_run_at ? $rule->last_run_at->format('Y-m-d H:i:s') : 'Never' }}</td>
                                            <td>{{ $rule->total_runs }}</td>
                                            <td>{{ $rule->total_actions_taken }}</td>
                                            <td>
                                                <a href="{{ route('meta.ads.manager.automation.edit', $rule->id) }}" class="btn btn-sm btn-info">Edit</a>
                                                <form action="{{ route('meta.ads.manager.automation.delete', $rule->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this rule?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                </form>
                                                @if($rule->dry_run_mode)
                                                    <span class="badge bg-warning ms-2">Dry Run</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <p>No automation rules created yet. Click "Create Rule" to get started.</p>
                            <p class="mb-0"><small>Automation rules engine will be implemented in the next phase.</small></p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

