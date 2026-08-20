@extends('layouts.vertical', ['title' => $title ?? 'Shopify PLS — Order', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'pls') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Shopify PLS Orders</a>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-1">
            <div>
                @include('marketplace._page-heading', ['slug' => 'pls', 'heading' => 'Order '.($line->order_name ?: $line->order_number), 'mb' => 'mb-0', 'mt' => ''])
            </div>
            <div class="d-flex gap-2">
                @include('marketplace._fetch-tracking-button', [
                    'fetchTrackingMarketplace' => 'pls',
                    'fetchTrackingOrderId' => $line->id,
                    'fetchTrackingShopifyId' => $line->shopify_order_id,
                ])
            </div>
        </div>
        @include('marketplace.pls._nav', ['active' => 'orders'])
        <div class="alert alert-info py-2 small mb-3">
            This is a Shopify ProLightSounds order.@include('marketplace._fetch-tracking-hint')
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="text-muted small">Financial</div><div>{{ $line->financial_status ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="text-muted small">Fulfillment</div><div>{{ $line->fulfillment_status ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="text-muted small">Date</div><div>{{ $line->order_date ? \Illuminate\Support\Carbon::parse($line->order_date)->format('M d, Y') : '—' }}</div></div>
                    <div class="col-md-3"><div class="text-muted small">Customer</div><div>{{ $line->customer_email ?? $line->customer_name ?? '—' }}</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Line items ({{ $lines->count() }})</h5></div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as $row)
                            <tr>
                                <td><code>{{ $row->sku ?? '—' }}</code></td>
                                <td>{{ $row->product_title ?? '—' }}</td>
                                <td>{{ $row->quantity ?? 1 }}</td>
                                <td>{{ is_numeric($row->price) ? number_format((float) $row->price, 2) : '—' }}</td>
                                <td>{{ is_numeric($row->total_amount) ? number_format((float) $row->total_amount, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
