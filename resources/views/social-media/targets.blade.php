@extends('layouts.vertical', ['title' => 'Social Media Targets'])

@section('content')
    <h4 class="page-title">KPI targets</h4>
    @include('social-media._nav')
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    <div class="card"><div class="card-body">
        <form method="post" action="{{ route('social-media.targets.store') }}" class="row g-2">
            @csrf
            <div class="col-md-3">
                <label class="form-label">KPI</label>
                <select name="metric_name" class="form-select" required>
                    @foreach ($metrics as $metric)
                        <option value="{{ $metric }}">{{ str_replace('_', ' ', $metric) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Target</label>
                <input name="target_value" type="number" step="0.0001" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Start</label>
                <input name="period_start" type="date" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">End</label>
                <input name="period_end" type="date" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Platform</label>
                <select name="platform" class="form-select">
                    <option value="">All</option>
                    @foreach ($platforms as $platform)
                        <option value="{{ $platform }}">{{ ucfirst($platform) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Executive</label>
                <select name="user_id" class="form-select">
                    <option value="">All</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary" type="submit">Save target</button>
            </div>
        </form>
    </div></div>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm">
            <thead><tr><th>KPI</th><th>Target</th><th>Period</th><th>Platform</th><th>Executive</th><th></th></tr></thead>
            <tbody>
                @forelse ($targets as $target)
                    <tr>
                        <td>{{ str_replace('_', ' ', $target->metric_name) }}</td>
                        <td>{{ number_format((float) $target->target_value) }}</td>
                        <td>{{ $target->period_start->toDateString() }} – {{ $target->period_end->toDateString() }}</td>
                        <td>{{ $target->platform ? ucfirst($target->platform) : 'All' }}</td>
                        <td>{{ $target->user->name ?? 'All' }}</td>
                        <td>
                            <form method="post" action="{{ route('social-media.targets.destroy', $target) }}">
                                @csrf @method('delete')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No targets configured.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $targets->links() }}
    </div></div>
@endsection
