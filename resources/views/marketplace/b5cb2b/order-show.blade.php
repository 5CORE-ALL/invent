@extends('layouts.vertical', ['title' => $title ?? 'B5C B2B order', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'b5cb2b') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Orders</a>
        @include('marketplace._page-heading', ['slug' => 'b5cb2b', 'heading' => 'Order '.$order->channelOrderNumber()])
        @include('marketplace.b5cb2b._nav', ['active' => 'orders'])
        <div class="card">
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Status</th><td>{{ $order->status }}</td></tr>
                    <tr><th>Customer</th><td>{{ $order->customer_name }} / {{ $order->customer_email }}</td></tr>
                    <tr><th>Total</th><td>{{ $order->currency }} {{ $order->total }}</td></tr>
                    <tr><th>Tracking</th><td>{{ $order->tracking_reference ?: '—' }}</td></tr>
                    <tr><th>Shopify</th><td>{{ $order->shopify_order_id ?: '—' }}</td></tr>
                </table>
                @php
                    $payload = is_array($order->payload) ? $order->payload : [];
                    $lines = $payload['products'] ?? $payload['line_items'] ?? $payload['items'] ?? $payload['lines'] ?? [];
                @endphp
                @if($lines)
                    <h6 class="mt-3">Lines</h6>
                    <table class="table table-sm">
                        <thead><tr><th>SKU</th><th>Name</th><th>Qty</th><th>Price</th></tr></thead>
                        <tbody>
                            @foreach($lines as $line)
                                <tr>
                                    <td>{{ $line['sku'] ?? '' }}</td>
                                    <td>{{ $line['name'] ?? '' }}</td>
                                    <td>{{ $line['qty'] ?? '' }}</td>
                                    <td>{{ $line['unit_price'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
