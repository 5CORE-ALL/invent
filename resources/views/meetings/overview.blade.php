@extends('layouts.vertical', ['title' => 'President Summary — Meetings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @include('meetings._styles')
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between py-1 flex-wrap gap-2">
                    <h4 class="page-title mb-0">President Summary <small class="text-muted fs-6">Meeting overview for management</small></h4>
                    <div class="d-flex gap-2">
                        <a href="{{ route('meetings.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Meeting Room</a>
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="ri-printer-line me-1"></i>Print</button>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3"><label class="form-label small mb-0">Search</label>
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Title, agenda or summary"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Priority</label>
                <select name="priority" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach(\App\Models\Meeting::PRIORITIES as $k => $p)<option value="{{ $k }}" @selected($priority === $k)>{{ $p[1] }} {{ $p[0] }}</option>@endforeach
                </select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All except cancelled</option>
                    @foreach(['scheduled' => 'Scheduled', 'live' => 'Live', 'ended' => 'Ended', 'cancelled' => 'Cancelled'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
                </select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">From</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">To</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
            <div class="col-md-1"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
        </form>

        @forelse($meetings as $meeting)
            @include('meetings._summary', ['meeting' => $meeting])
        @empty
            <div class="alert alert-light border">No meetings match these filters.</div>
        @endforelse

        {{ $meetings->links() }}
    </div>
@endsection
