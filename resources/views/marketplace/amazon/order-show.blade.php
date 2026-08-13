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
                            <dt class="col-5">Fulfillment</dt>
                            <dd class="col-7">
                                @if($order->isFba())
                                    <span class="badge bg-dark">FBA</span>
                                    <span class="small text-muted">not sent to Shopify</span>
                                @else
                                    <span class="badge bg-info text-dark">{{ $order->fulfillmentChannel() ?: 'MFN' }}</span>
                                @endif
                            </dd>
                            <dt class="col-5">Shopify</dt>
                            <dd class="col-7">
                                @if($order->shopify_order_id)
                                    <span class="badge bg-success">Imported</span>
                                    <small class="d-block text-muted">{{ $order->shopify_order_id }}</small>
                                @elseif($order->isFba())
                                    <span class="text-muted">FBA — skipped</span>
                                @else
                                    <span class="text-muted">{{ $order->import_status ?: 'Pending' }}</span>
                                @endif
                            </dd>
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Shopify import</h5>
                <div class="d-flex flex-wrap gap-2">
                    @if($order->shopify_order_id)
                        <span class="text-muted small align-self-center">Already imported</span>
                    @elseif($order->isFba())
                        <span class="text-muted small align-self-center">FBA orders are not created on Shopify.</span>
                    @else
                        @php
                            $pushBlocked = ($importPaidOrdersOnly ?? false) && ! ($orderIsPaid ?? true);
                            $canCreate = $order->canCreateShopifyOrder();
                        @endphp
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-dry-run-shopify" data-id="{{ $order->id }}">
                            Dry run (preview)
                        </button>
                        @if($pushBlocked)
                            <button type="button" class="btn btn-sm btn-secondary" disabled>Push to Shopify</button>
                        @elseif($canCreate)
                            <button type="button" class="btn btn-sm btn-warning" id="btn-push-order" data-id="{{ $order->id }}">
                                Push to Shopify
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-success" id="btn-mark-imported" data-id="{{ $order->id }}" data-order-id="{{ $order->amazon_order_id }}">
                            Already imported
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body small text-muted">
                FBM orders on/after {{ \App\Models\AmazonOrder::SHOPIFY_IMPORT_CUTOFF_DATE }} PT are created on Shopify.
                Existing Shopify orders (previous sync app) are linked, never duplicated. FBA is never created.
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

<div class="modal fade" id="shopifyDryRunModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Shopify push preview (dry run)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="shopify-dry-run-summary" class="mb-3"></div>
                <pre id="shopify-dry-run-json" class="small bg-light border rounded p-3 mb-0" style="max-height: 420px; overflow: auto;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-dry-run-shopify')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    btn.disabled = true;
    fetch('{{ route('marketplace.orders.push', 'amazon') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id, dry_run: true }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        var summary = document.getElementById('shopify-dry-run-summary');
        var jsonEl = document.getElementById('shopify-dry-run-json');
        summary.innerHTML = '<p class="mb-1">' + (data.message || '') + '</p>';
        jsonEl.textContent = JSON.stringify(data.payload || data, null, 2);
        new bootstrap.Modal(document.getElementById('shopifyDryRunModal')).show();
    })
    .catch(function () { alert('Dry run request failed.'); })
    .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-push-order')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    if (!confirm('Create a real Shopify order from this Amazon FBM order?')) return;
    btn.disabled = true;
    fetch('{{ route('marketplace.orders.push', 'amazon') }}', {
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
        alert(data.message || (data.success ? 'Pushed to Shopify.' : 'Push failed'));
        if (data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-mark-imported')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    var orderId = btn.getAttribute('data-order-id') || id;
    if (!id) return;
    if (!confirm('Mark Amazon order ' + orderId + ' as already imported?\n\nNo new Shopify order will be created.')) return;
    var shopifyOrderId = prompt('Optional Shopify order ID (leave blank if entered manually):', '') || '';
    btn.disabled = true;
    fetch('{{ route('marketplace.orders.mark-imported', 'amazon') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id, shopify_order_id: shopifyOrderId }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        alert(data.message || (data.success ? 'Marked imported.' : 'Failed'));
        if (data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () { btn.disabled = false; });
});
</script>
@endsection
