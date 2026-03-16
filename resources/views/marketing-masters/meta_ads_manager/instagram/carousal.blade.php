@extends('layouts.vertical', ['title' => 'Meta - Instagram Carousal', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    @include('marketing-masters.meta_ads_manager.partials.styles')
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Instagram Carousal Ads',
        'sub_title' => 'Instagram Carousal',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    @include('marketing-masters.meta_ads_manager.partials.filters', [
                        'adType' => 'INSTA GRP CAROUSAL',
                        'latestUpdatedAt' => $latestUpdatedAt ?? null
                    ])
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        var dataUrl = "{{ route('meta.ads.instagram.carousal.data') }}";
    </script>
    @include('marketing-masters.meta_ads_manager.partials.table-script-full')
@endsection
