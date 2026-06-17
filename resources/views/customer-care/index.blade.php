@extends('layouts.vertical', ['title' => 'Customer Care', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Customer Care',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-0">Use the sidebar under <strong>Customer Care</strong> to add tickets, returns, or other tools. Add sub-pages in the menu as needed.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
