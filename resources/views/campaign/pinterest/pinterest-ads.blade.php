@extends('layouts.vertical', ['title' => 'Pinterest Ads', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    @include('partials.ads-tabulator-page', ['adsPart' => 'css'])
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Pinterest Ads',
        'sub_title' => 'Pinterest Ads',
    ])
    @include('partials.ads-tabulator-page', ['adsPart' => 'content'])
@endsection

@section('script-bottom')
    @include('partials.ads-tabulator-page', [
        'adsPart' => 'script',
        'dataUrl' => $dataUrl,
        'channelKey' => $channelKey,
    ])
@endsection
