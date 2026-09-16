@extends('layouts.vertical', ['title' => $title ?? 'B5C B2B listing', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.products', 'b5cb2b') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Listings</a>
        @include('marketplace._page-heading', ['slug' => 'b5cb2b', 'heading' => $product->sku])
        @include('marketplace.b5cb2b._nav', ['active' => 'products'])
        <div class="card">
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>SKU</th><td>{{ $product->sku }}</td></tr>
                    <tr><th>Title</th><td>{{ $product->title }}</td></tr>
                    <tr><th>Qty</th><td>{{ $product->qty }}</td></tr>
                    <tr><th>Listing ID</th><td>{{ $product->listing_id }}</td></tr>
                    <tr><th>Slug</th><td>{{ $product->slug }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
