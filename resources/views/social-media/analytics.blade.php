@extends('layouts.vertical', ['title' => 'Social Media Analytics'])

@section('content')
    <h4 class="page-title">Analytics</h4>
    @include('social-media._nav')
    @include('social-media._filters')
    @if (($report['accounts'] ?? collect())->isEmpty())
        <div class="card"><div class="card-body text-muted">No accounts match these filters.</div></div>
    @else
        @include('social-media._charts')
        <div class="card"><div class="card-body">
            <h5>Previous windows</h5>
            <p class="mb-1">Previous month: {{ ($report['cards']['previous_month']['available'] ?? false) ? 'Historical rows exist for this window.' : 'Not enough history for a previous-month comparison.' }}</p>
            <p class="mb-0">Previous year: {{ ($report['cards']['previous_year']['available'] ?? false) ? 'Historical rows exist for this window.' : 'Not enough history for a previous-year comparison.' }}</p>
        </div></div>
    @endif
@endsection
