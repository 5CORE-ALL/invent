@extends('layouts.vertical', ['title' => $title.' Ads', 'sidenav' => 'condensed'])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $title.' Ads',
        'sub_title' => $title,
    ])

    @if (! empty($missingAdsUrl))
        <div class="row">
            <div class="col-12">
                <a href="{{ $missingAdsUrl }}" class="btn btn-sm btn-warning">
                    {{ $title }} Missing Ads
                </a>
            </div>
        </div>
    @endif
@endsection
