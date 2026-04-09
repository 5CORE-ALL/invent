@extends('layouts.vertical', ['title' => 'CRM Follow-ups', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Follow-ups',
        'sub_title' => 'B2B marketing follow-ups',
    ])

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
        <a href="{{ route('crm.follow-ups.create') }}" class="btn btn-primary btn-sm">New follow-up</a>
        <a href="{{ route('crm.follow-ups.export', request()->only(['customer_id', 'status'])) }}" class="btn btn-outline-success btn-sm">Export CSV</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form class="row g-2 mb-3" method="get" action="{{ route('crm.follow-ups.index') }}">
                <div class="col-md-3">
                    <input type="number" name="customer_id" class="form-control" placeholder="Customer ID"
                           value="{{ $filters['customer_id'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (['pending', 'completed', 'postponed', 'cancelled'] as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Scheduled</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($followUps as $fu)
                            <tr>
                                <td>{{ $fu->id }}</td>
                                <td>{{ $fu->title }}</td>
                                <td>
                                    @if ($fu->customer)
                                        <a href="{{ route('crm.customers.show', $fu->customer) }}">{{ $fu->customer->name }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><span class="badge bg-secondary">{{ $fu->status }}</span></td>
                                <td>{{ optional($fu->scheduled_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                <td><a href="{{ route('crm.follow-ups.show', $fu) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted text-center py-4">No follow-ups found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $followUps->links() }}
        </div>
    </div>
@endsection
