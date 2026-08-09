@extends('layouts.vertical', ['title' => $title ?? 'Amz Order', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'amazon') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Amz Orders</a>
        @include('marketplace._page-heading', ['slug' => 'amazon', 'heading' => 'Order '.$order->amazon_order_id, 'mb' => 'mb-3'])

        @include('marketplace.amazon._nav', ['active' => 'orders'])

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><h5 class="card-title mb-0">Order</h5></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Amz order</dt>
                            <dd class="col-7"><code>{{ $order->amazon_order_id }}</code></dd>
                            <dt class="col-5">Date (PT)</dt>
                            <dd class="col-7">
                                @if($order->order_date)
                                    {{ \Carbon\Carbon::parse($order->order_date)->timezone('America/Los_Angeles')->format('M d, Y H:i') }}
                                @else
                                    —
                                @endif
                            </dd>
                            <dt class="col-5">Status</dt>
                            <dd class="col-7"><span class="badge bg-secondary">{{ $order->status ?: '—' }}</span></dd>
                            <dt class="col-5">Total</dt>
                            <dd class="col-7">
                                {{ is_numeric($order->total_amount) ? number_format((float) $order->total_amount, 2) : '—' }}
                                {{ $order->currency ?: 'USD' }}
                            </dd>
                            <dt class="col-5">Period</dt>
                            <dd class="col-7">{{ $order->period ?: '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><h5 class="card-title mb-0">Buyer</h5></div>
                    <div class="card-body">
                        <p class="mb-1"><strong>{{ $buyerInfo['name'] ?? '—' }}</strong></p>
                        <p class="mb-0 text-muted small">{{ $buyerInfo['email'] ?? '—' }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><h5 class="card-title mb-0">Shipping</h5></div>
                    <div class="card-body small">
                        @if(!empty(array_filter($shippingAddress ?? [])))
                            <div>{{ $shippingAddress['name'] ?? '' }}</div>
                            <div>{{ $shippingAddress['line1'] ?? '' }}</div>
                            @if(!empty($shippingAddress['line2']))
                                <div>{{ $shippingAddress['line2'] }}</div>
                            @endif
                            <div>
                                {{ $shippingAddress['city'] ?? '' }}
                                {{ $shippingAddress['state'] ?? '' }}
                                {{ $shippingAddress['postal'] ?? '' }}
                            </div>
                            <div>{{ $shippingAddress['country'] ?? '' }}</div>
                            @if(!empty($shippingAddress['phone']))
                                <div class="text-muted mt-1">{{ $shippingAddress['phone'] }}</div>
                            @endif
                        @else
                            <p class="text-muted mb-0">No shipping address in stored payload (Amz often redacts until ship-ready).</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h5 class="card-title mb-0">Line items</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>ASIN</th>
                                <th>Title</th>
                                <th>Qty</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                <tr>
                                    <td><code>{{ $item->sku ?: '—' }}</code></td>
                                    <td>{{ $item->asin ?: '—' }}</td>
                                    <td>{{ $item->title ?: '—' }}</td>
                                    <td>{{ $item->quantity ?? 0 }}</td>
                                    <td>
                                        {{ is_numeric($item->price) ? number_format((float) $item->price, 2) : '—' }}
                                        {{ $item->currency ?: ($order->currency ?: 'USD') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No line items stored for this order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
