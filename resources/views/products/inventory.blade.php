@extends('layouts.vertical', ['title' => 'Contact List', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Products', 'page_title' => 'Amazon Products'])
@endsection