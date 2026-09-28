@extends('layouts.vertical', ['title' => 'Social Media Reports'])

@section('content')
    <h4 class="page-title">Reports</h4>
    @include('social-media._nav')
    <div class="card"><div class="card-body">
        <p>Download a CSV for the selected period. The file includes the KPI summary, platform totals, target progress, and notes about metrics the APIs do not provide.</p>
        <form method="get" action="{{ route('social-media.reports.export') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Preset</label>
                <select name="preset" class="form-select">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly" selected>Monthly</option>
                    <option value="">Custom</option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="start" value="{{ $start->toDateString() }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="end" value="{{ $end->toDateString() }}" class="form-control"></div>
            <div class="col-md-2">
                <label class="form-label">Platform</label>
                <select name="platform" class="form-select">
                    <option value="">All</option>
                    @foreach ($platforms as $platform)<option value="{{ $platform }}">{{ ucfirst($platform) }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Executive</label>
                <select name="user_id" class="form-select">
                    <option value="">All</option>
                    @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Download CSV</button></div>
        </form>
    </div></div>
@endsection
