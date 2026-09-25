@extends('layouts.vertical', ['title' => $title.' Ads', 'sidenav' => 'condensed'])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $title.' Ads',
        'sub_title' => $title,
    ])
@endsection
