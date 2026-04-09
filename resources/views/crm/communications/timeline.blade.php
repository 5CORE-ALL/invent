@extends('layouts.vertical', ['title' => 'Timeline — '.$customer->name, 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @include('crm.communications.partials.timeline-styles')
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Customer timeline',
        'sub_title' => $customer->name.' · #'.$customer->id,
    ])

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('crm.follow-ups.index', ['customer_id' => $customer->id]) }}" class="btn btn-outline-secondary btn-sm">
            Follow-ups for this customer
        </a>
        <a href="{{ route('crm.customers.show', $customer) }}" class="btn btn-outline-primary btn-sm">Customer detail</a>
        <a href="{{ route('crm.follow-ups.index') }}" class="btn btn-light btn-sm">All follow-ups</a>
        <span class="text-muted small ms-auto">{{ $timeline->count() }} {{ Str::plural('event', $timeline->count()) }}</span>
    </div>

    <div class="card">
        <div class="card-body">
            @include('crm.communications.partials.timeline-list', ['timeline' => $timeline])
        </div>
    </div>
@endsection
