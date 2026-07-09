@extends('layouts.vertical', ['title' => $title ?? 'AliExpress — Order Detail', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@php
    $summary = $detail['summary'] ?? [];
    $amounts = $detail['amounts'] ?? [];
    $buyer = $detail['buyer'] ?? [];
    $shipping = $detail['shipping'] ?? [];
    $logistics = $detail['logistics'] ?? [];
    $lineItems = $detail['line_items'] ?? [];
    $shopify = $detail['shopify'] ?? [];
    $payment = $detail['payment'] ?? [];
    $currency = $amounts['currency'] ?? $payment['currency'] ?? '';
@endphp
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'aliexpress') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Back to Orders</a>
        <h4 class="mt-2 mb-1">Order {{ $summary['order_id'] ?? $orderId }}</h4>
        <p class="text-muted mb-3">
            @if(!empty($summary['created']))
                {{ \Carbon\Carbon::parse($summary['created'])->format('M d, Y H:i') }}
            @endif
            <span class="badge bg-secondary ms-1">{{ $summary['status'] ?? '—' }}</span>
            @if(isset($amounts['order_total']))
                <span class="ms-2 fw-semibold">{{ $currency }} {{ number_format((float)$amounts['order_total'], 2) }}</span>
            @endif
        </p>

        @include('marketplace.aliexpress._nav', ['active' => 'orders'])

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">Order summary</div>
                    <div class="card-body p-0">
                        @include('marketplace.aliexpress._detail-table', ['rows' => [
                            'Order ID' => $summary['order_id'] ?? null,
                            'Order number' => $summary['order_number'] ?? null,
                            'Status' => $summary['status'] ?? null,
                            'Created' => !empty($summary['created']) ? \Carbon\Carbon::parse($summary['created'])->format('M d, Y H:i') : null,
                            'Paid' => !empty($summary['paid']) ? \Carbon\Carbon::parse($summary['paid'])->format('M d, Y H:i') : null,
                            'Shipped' => !empty($summary['sent']) ? \Carbon\Carbon::parse($summary['sent'])->format('M d, Y H:i') : null,
                            'Finished' => !empty($summary['finished']) ? \Carbon\Carbon::parse($summary['finished'])->format('M d, Y H:i') : null,
                            'Modified' => !empty($summary['modified']) ? \Carbon\Carbon::parse($summary['modified'])->format('M d, Y H:i') : null,
                            'Buyer remark' => $summary['buyer_remark'] ?? null,
                            'Seller remark' => $summary['seller_remark'] ?? null,
                        ]])
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">Payment & totals</div>
                    <div class="card-body p-0">
                        @include('marketplace.aliexpress._detail-table', ['rows' => [
                            'Currency' => $currency ?: null,
                            'Order total' => isset($amounts['order_total']) ? number_format((float)$amounts['order_total'], 2) : null,
                            'Pay amount' => isset($amounts['pay_amount']) ? number_format((float)$amounts['pay_amount'], 2) : null,
                            'Shipping' => isset($amounts['shipping_cost']) ? number_format((float)$amounts['shipping_cost'], 2) : null,
                            'Discount' => isset($amounts['discount']) ? number_format((float)$amounts['discount'], 2) : null,
                            'Tax' => isset($amounts['tax']) ? number_format((float)$amounts['tax'], 2) : null,
                            'Payment method' => $payment['method'] ?? null,
                        ]])
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Buyer</div>
                    <div class="card-body p-0">
                        @include('marketplace.aliexpress._detail-table', ['rows' => [
                            'Name' => trim(($buyer['name'] ?? '').' '.($buyer['last_name'] ?? '')) ?: null,
                            'Login ID' => $buyer['login_id'] ?? null,
                            'Email' => $buyer['email'] ?? null,
                            'Phone' => $buyer['phone'] ?? null,
                            'Country' => $buyer['country'] ?? null,
                        ]])
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Shipping address</div>
                    <div class="card-body p-0">
                        @include('marketplace.aliexpress._detail-table', ['rows' => [
                            'Recipient' => $shipping['recipient'] ?? null,
                            'Address line 1' => $shipping['address_line_1'] ?? null,
                            'Address line 2' => $shipping['address_line_2'] ?? null,
                            'City' => $shipping['city'] ?? null,
                            'State / Province' => $shipping['province'] ?? null,
                            'ZIP' => $shipping['zip'] ?? null,
                            'Country' => $shipping['country'] ?? null,
                            'Full address' => $shipping['full_address'] ?? null,
                        ]])
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($logistics))
            <div class="card mb-3">
                <div class="card-header">Logistics & tracking</div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Service</th>
                                <th>Tracking</th>
                                <th>Status</th>
                                <th>Send type</th>
                                <th>Receive status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logistics as $log)
                                <tr>
                                    <td class="ps-3">{{ $log['service'] ?? '—' }}</td>
                                    <td><code>{{ $log['tracking'] ?? '—' }}</code></td>
                                    <td>{{ $log['status'] ?? '—' }}</td>
                                    <td>{{ $log['send_type'] ?? '—' }}</td>
                                    <td>{{ $log['receive_status'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header">Products in this order ({{ count($lineItems) }})</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:72px;">Image</th>
                                <th>SKU</th>
                                <th>Product ID</th>
                                <th>Title</th>
                                <th>Qty</th>
                                <th>Unit price</th>
                                <th>Line total</th>
                                <th>Status</th>
                                <th>Shopify</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lineItems as $item)
                                <tr>
                                    <td>
                                        @if(!empty($item['image']))
                                            <img src="{{ $item['image'] }}" alt="" class="img-thumbnail" style="max-width:56px; max-height:56px; object-fit:contain;">
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td><code>{{ $item['sku'] ?? '—' }}</code></td>
                                    <td>{{ $item['product_id'] ?? '—' }}</td>
                                    <td>{{ $item['title'] ?? '—' }}</td>
                                    <td>{{ $item['quantity'] ?? 1 }}</td>
                                    <td>{{ isset($item['unit_price']) ? number_format((float)$item['unit_price'], 2) : '—' }}</td>
                                    <td>{{ isset($item['line_total']) ? number_format((float)$item['line_total'], 2) : '—' }}</td>
                                    <td>{{ $item['status'] ?? '—' }}</td>
                                    <td>
                                        @if(!empty($item['shopify_order_id']))
                                            <span class="badge bg-success">Imported</span>
                                        @else
                                            {{ $item['import_status'] ?? 'pending' }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted py-4">No line items found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Shopify import</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th class="ps-3" style="width:200px;">Shopify order ID</th><td>{{ $shopify['shopify_order_id'] ?? '—' }}</td></tr>
                        <tr><th class="ps-3">Import status</th><td>{{ $shopify['import_status'] ?? 'pending' }}</td></tr>
                        <tr><th class="ps-3">Pushed at</th><td>
                            @if(!empty($shopify['pushed_to_shopify_at']))
                                {{ \Carbon\Carbon::parse($shopify['pushed_to_shopify_at'])->format('M d, Y H:i') }}
                            @else
                                —
                            @endif
                        </td></tr>
                        <tr><th class="ps-3">Action</th><td>
                            @if($shopify['shopify_order_id'] ?? null)
                                <span class="text-muted">Already imported</span>
                            @else
                                <button type="button" class="btn btn-sm btn-warning" id="btn-push-order" data-id="{{ $line->id }}">Push to Shopify</button>
                            @endif
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-push-order')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    btn.disabled = true;
    fetch('{{ route('marketplace.orders.push', 'aliexpress') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        alert(data.message || (data.success ? 'Queued' : 'Failed'));
        if (data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () { btn.disabled = false; });
});
</script>
@endsection
