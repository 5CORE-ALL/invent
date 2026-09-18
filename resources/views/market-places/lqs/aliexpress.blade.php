@extends('layouts.vertical', ['title' => $lqsPage['title'] ?? 'LQS', 'sidenav' => 'condensed'])

@section('css')
    @include('market-places.lqs._styles')
@endsection

@section('content')
    @include('market-places.lqs._content')
@endsection

@section('script-bottom')
    @include('market-places.lqs._scripts')
@endsection
