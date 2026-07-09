@extends('layouts.vertical', ['title' => $title ?? 'AliExpress — Order Detail', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'aliexpress') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Back to Orders</a>
        <h4 class="mt-2 mb-1">Order {{ $orderId }}</h4>
        <p class="text-muted mb-3">
            @if($line->order_date)
                {{ \Carbon\Carbon::parse($line->order_date)->format('M d, Y H:i') }}
            @endif
            <span class="badge bg-secondary ms-1">{{ $line->status ?? '—' }}</span>
        </p>

        @include('marketplace.aliexpress._nav', ['active' => 'orders'])

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Order summary</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th class="ps-3" style="width: 160px;">Order ID</th><td>{{ $orderId }}</td></tr>
                                <tr><th class="ps-3">Order number</th><td>{{ $line->order_number ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Status</th><td>{{ $line->status ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Date</th><td>
                                    @if($line->order_date)
                                        {{ \Carbon\Carbon::parse($line->order_date)->format('M d, Y H:i') }}
                                    @else
                                        —
                                    @endif
                                </td></tr>
                                <tr><th class="ps-3">Total</th><td>{{ isset($orderTotal) ? number_format((float)$orderTotal, 2) : '—' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Shopify import</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th class="ps-3" style="width: 160px;">Shopify order</th><td>{{ $line->shopify_order_id ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Import status</th><td>{{ $line->import_status ?? 'pending' }}</td></tr>
                                <tr><th class="ps-3">Pushed at</th><td>
                                    @if($line->pushed_to_shopify_at)
                                        {{ \Carbon\Carbon::parse($line->pushed_to_shopify_at)->format('M d, Y H:i') }}
                                    @else
                                        —
                                    @endif
                                </td></tr>
                                <tr><th class="ps-3">Action</th><td>
                                    @if($line->shopify_order_id)
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

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Buyer</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th class="ps-3" style="width: 160px;">Name</th><td>{{ $buyer['name'] ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Login ID</th><td>{{ $buyer['login_id'] ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Email</th><td>{{ $buyer['email'] ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Phone</th><td>{{ $buyer['phone'] ?? '—' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Shipping</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th class="ps-3" style="width: 160px;">Recipient</th><td>{{ $shipping['recipient'] ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Address</th><td>{{ $shipping['address'] ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Country</th><td>{{ $shipping['country'] ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Logistics</th><td>{{ $shipping['logistics_type'] ?? '—' }}</td></tr>
                                <tr><th class="ps-3">Tracking</th><td>{{ $shipping['tracking'] ?? '—' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Line items ({{ $lines->count() }})</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Product ID</th>
                                <th>Title</th>
                                <th>Qty</th>
                                <th>Unit amount</th>
                                <th>Import</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $item)
                                <tr class="{{ $item->id === $line->id ? 'table-active' : '' }}">
                                    <td><code>{{ $item->sku }}</code></td>
                                    <td>{{ $item->product_id ?? '—' }}</td>
                                    <td>{{ $item->display_title ?? '—' }}</td>
                                    <td>{{ $item->quantity ?? 1 }}</td>
                                    <td>{{ is_numeric($item->amount) ? number_format((float)$item->amount, 2) : '—' }}</td>
                                    <td>{{ $item->import_status ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($orderRoot !== [])
            <div class="card">
                <div class="card-header">Raw order payload</div>
                <div class="card-body">
                    <pre class="small mb-0 bg-light p-3 rounded" style="max-height: 400px; overflow: auto;">{{ json_encode($orderRoot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        @endif
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
