@extends('layouts.vertical', ['title' => 'CRM Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'CRM dashboard',
        'sub_title' => 'Follow-up overview',
    ])

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Pending</h6>
                    <p class="h4 mb-0">{{ $counts['pending'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Overdue</h6>
                    <p class="h4 mb-0">{{ $counts['overdue'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Due today</h6>
                    <p class="h4 mb-0">{{ $counts['today'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Conversion (completed)</h5>
            <p class="mb-1"><strong>Total completed:</strong> {{ $conversion['completed_total'] }}</p>
            <p class="mb-1"><strong>Converted:</strong> {{ $conversion['converted'] }}</p>
            <p class="mb-0"><strong>Conversion rate:</strong>
                @if ($conversion['conversion_rate'] !== null)
                    {{ $conversion['conversion_rate'] }}%
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Overdue (sample)</span>
                    <a href="{{ route('crm.follow-ups.index', ['status' => 'pending']) }}" class="btn btn-sm btn-light">All follow-ups</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($lists['overdue'] as $f)
                            <li class="list-group-item">
                                <a href="{{ route('crm.follow-ups.show', $f) }}">{{ $f->title }}</a>
                                <span class="text-muted small"> · {{ $f->customer->name ?? '—' }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">None</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">Due today (sample)</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($lists['today'] as $f)
                            <li class="list-group-item">
                                <a href="{{ route('crm.follow-ups.show', $f) }}">{{ $f->title }}</a>
                                <span class="text-muted small"> · {{ $f->customer->name ?? '—' }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">None</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
