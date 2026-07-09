@extends('layouts.vertical', ['title' => $title ?? 'AliExpress — Listing Detail', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@php
    $shopifyQty = $shopify->available_to_sell ?? $shopify->inv ?? $shopify->on_hand ?? null;
    $shopifyPrice = $shopify->b2c_price ?? $shopify->price ?? null;
@endphp
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.products', 'aliexpress') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Back to Listings</a>
        <h4 class="mt-2 mb-1">{{ $displayTitle }}</h4>
        <p class="text-muted mb-3">
            <code>{{ $shopify->sku }}</code>
            @if($linked)
                <span class="badge bg-success-subtle text-success ms-1">Linked on AliExpress</span>
            @else
                <span class="badge bg-light text-muted ms-1">Not linked</span>
            @endif
        </p>

        @include('marketplace.aliexpress._nav', ['active' => 'products'])

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">Image</div>
                    <div class="card-body text-center">
                        @if(!empty($shopify->image_src))
                            <img src="{{ $shopify->image_src }}" alt="" class="img-fluid rounded border" style="max-height: 280px; object-fit: contain;">
                        @else
                            <p class="text-muted mb-0">No image</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">Shopify (source)</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th class="ps-3" style="width: 180px;">SKU</th><td><code>{{ $shopify->sku }}</code></td></tr>
                                <tr><th class="ps-3">Product title</th><td>{{ $shopify->product_title ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Variant title</th><td>{{ $shopify->variant_title ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Variant ID</th><td>{{ $shopify->variant_id ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Available to sell</th><td>{{ $shopifyQty ?? '—' }}</td></tr>
                                <tr><th class="ps-3">On hand</th><td>{{ $shopify->on_hand ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Committed</th><td>{{ $shopify->committed ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Incoming</th><td>{{ $shopify->incoming ?? '—' }}</td></tr>
                                <tr><th class="ps-3">B2C price</th><td>{{ isset($shopifyPrice) ? number_format((float)$shopifyPrice, 2) : '—' }}</td></tr>
                                <tr><th class="ps-3">B2B price</th><td>{{ isset($shopify->b2b_price) ? number_format((float)$shopify->b2b_price, 2) : '—' }}</td></tr>
                                <tr><th class="ps-3">Shopify L30</th><td>{{ $shopify->shopify_l30 ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Product link</th><td>
                                    @if(!empty($shopify->product_link))
                                        <a href="{{ $shopify->product_link }}" target="_blank" rel="noopener">Open in Shopify</a>
                                    @else
                                        —
                                    @endif
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">AliExpress link (local map)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th class="ps-3" style="width: 180px;">AliExpress product ID</th><td>{{ $metric?->product_id ?? '—' }}</td></tr>
                        <tr><th class="ps-3">AE title (cached)</th><td>{{ $metric?->product_name ?? '—' }}</td></tr>
                        <tr><th class="ps-3">AE price (cached)</th><td>{{ isset($metric?->price) ? number_format((float)$metric->price, 2) : '—' }}</td></tr>
                        <tr><th class="ps-3">L30 / L60 (metric)</th><td>{{ $metric?->l30 ?? '—' }} / {{ $metric?->l60 ?? '—' }}</td></tr>
                        <tr><th class="ps-3">Last order date</th><td>
                            @if($metric?->last_order_date)
                                {{ \Carbon\Carbon::parse($metric->last_order_date)->format('M d, Y H:i') }}
                            @else
                                —
                            @endif
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if($linked)
            <div class="card mb-3">
                <div class="card-header">AliExpress live details</div>
                <div class="card-body">
                    @if($aeLiveError)
                        <div class="alert alert-warning mb-0">{{ $aeLiveError }}</div>
                    @elseif($aeSkuRows !== [])
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>SKU code</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($aeSkuRows as $row)
                                        <tr>
                                            <td><code>{{ $row['sku'] ?? '—' }}</code></td>
                                            <td>{{ isset($row['price']) ? number_format((float)$row['price'], 2) : '—' }}</td>
                                            <td>{{ $row['stock'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No variant rows returned from AliExpress API.</p>
                    @endif
                </div>
            </div>
        @endif

        @if(is_array($aeLive) && $aeLive !== [])
            <div class="card">
                <div class="card-header">Raw AliExpress API response</div>
                <div class="card-body">
                    <pre class="small mb-0 bg-light p-3 rounded" style="max-height: 320px; overflow: auto;">{{ json_encode($aeLive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
