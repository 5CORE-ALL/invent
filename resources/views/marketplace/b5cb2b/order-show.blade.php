@extends('layouts.vertical', ['title' => $title ?? 'B5C B2B order', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'b5cb2b') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Orders</a>
        @include('marketplace._page-heading', ['slug' => 'b5cb2b', 'heading' => 'Order '.$order->channelOrderNumber()])
        @include('marketplace.b5cb2b._nav', ['active' => 'orders'])
        <div class="card">
            <div class="card-body">
                @if(!empty($pushError))
                    <div class="alert alert-warning py-2">Shopify import: {{ $pushError }}</div>
                @endif
                <table class="table table-sm">
                    <tr><th style="width:140px">Status</th><td>{{ $order->status }}</td></tr>
                    <tr><th>Customer</th><td>{{ $order->customer_name }} / {{ $order->customer_email }}</td></tr>
                    <tr><th>Total</th><td>{{ $order->currency }} {{ $order->total }}</td></tr>
                    <tr><th>Tracking</th><td>{{ $order->tracking_reference ?: '—' }}</td></tr>
                    <tr><th>Shopify</th><td>{{ $order->shopify_order_id ?: '—' }}</td></tr>
                </table>
                @php $lines = $order->displayLines(); @endphp
                @if($lines)
                    <h6 class="mt-3">Lines</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width:160px">SKU</th>
                                    <th>Name</th>
                                    <th style="width:80px">Qty</th>
                                    <th style="width:100px">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $line)
                                    <tr>
                                        <td class="text-nowrap fw-semibold">{{ $line['sku'] !== '' ? $line['sku'] : '—' }}</td>
                                        <td>{{ $line['name'] }}</td>
                                        <td>{{ $line['qty'] }}</td>
                                        <td>{{ $line['price'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
