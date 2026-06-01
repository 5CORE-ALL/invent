@extends('layouts.vertical', ['title' => 'Refunds', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Refunds',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-0">Refunds workspace — add your refund list, filters, or integrations here.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
