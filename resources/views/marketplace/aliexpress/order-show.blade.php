@extends('layouts.vertical', ['title' => $title ?? 'AliExpress — Order Detail', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@php
    $summary = $detail['summary'] ?? [];
    $amounts = $detail['amounts'] ?? [];
    $funds = $detail['funds'] ?? [];
    $buyer = $detail['buyer'] ?? [];
    $shipping = $detail['shipping'] ?? [];
    $shipment = $detail['shipment'] ?? [];
    $logistics = $detail['logistics'] ?? [];
    $lineItems = $detail['line_items'] ?? [];
    $shopify = $detail['shopify'] ?? [];
    $payment = $detail['payment'] ?? [];
    $currency = $funds['currency'] ?? $amounts['currency'] ?? $payment['currency'] ?? '';
    $fmt = function ($value) use ($currency) {
        if ($value === null || $value === '') return '—';
        return ($currency ? $currency.' ' : '').number_format((float)$value, 2);
    };
    $aeSource = $aeDataSource ?? 'cached';
@endphp
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'aliexpress') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Back to Orders</a>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-1">
            <div>
                <h4 class="mb-1">Order {{ $summary['order_id'] ?? $orderId }}</h4>
                <p class="text-muted mb-0">
                    @if(!empty($summary['created']))
                        {{ \Carbon\Carbon::parse($summary['created'])->format('M d, Y H:i') }}
                    @endif
                    <span class="badge bg-secondary ms-1">{{ $summary['status'] ?? '—' }}</span>
                    @if($aeSource === 'api')
                        <span class="badge bg-info-subtle text-info ms-1">AE data: live API</span>
                    @else
                        <span class="badge bg-warning-subtle text-warning ms-1">AE data: cached</span>
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2">
                @if($connected)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-pull-ae-order" data-id="{{ $line->id }}">
                        <i class="ri-download-cloud-line"></i> Pull from AliExpress
                    </button>
                @endif
            </div>
        </div>

        @include('marketplace.aliexpress._nav', ['active' => 'orders'])

        <div class="alert alert-info py-2 small mb-3">
            <strong>Read-only view.</strong> Shipping, buyer, payment, and fund details are pulled from AliExpress and sent to Shopify when you push this order.
        </div>

        @if($aeLiveError ?? null)
            <div class="alert alert-warning">{{ $aeLiveError }}</div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">Order summary</div>
                    <div class="card-body p-0">
                        @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
                            'Order ID' => $summary['order_id'] ?? null,
                            'Order number' => $summary['order_number'] ?? null,
                            'Status' => $summary['status'] ?? null,
                            'Buyer login' => $summary['buyer_login_id'] ?? null,
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
                    <div class="card-header">Payment details</div>
                    <div class="card-body p-0">
                        @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
                            'Total amount paid' => isset($payment['total_paid']) ? $fmt($payment['total_paid']) : null,
                            'Payment time' => !empty($payment['paid_at']) ? \Carbon\Carbon::parse($payment['paid_at'])->format('M d, Y H:i') : null,
                            'Payment method' => $payment['method'] ?? null,
                            'Currency' => $currency ?: null,
                        ]])
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Fund details</div>
            <div class="card-body p-0">
                @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
                    'Product total' => isset($funds['product_total']) ? $fmt($funds['product_total']) : null,
                    'Shipping cost' => isset($funds['shipping_cost']) ? $fmt($funds['shipping_cost']) : null,
                    'Adjustment' => isset($funds['adjustment']) ? $fmt($funds['adjustment']) : null,
                    'Store promotion' => isset($funds['store_promotion']) ? $fmt($funds['store_promotion']) : null,
                    'Order amount' => isset($funds['order_amount']) ? $fmt($funds['order_amount']) : null,
                    'Platform commission' => isset($funds['platform_commission']) ? $fmt($funds['platform_commission']) : null,
                    'Affiliate commission' => isset($funds['affiliate_commission']) ? $fmt($funds['affiliate_commission']) : null,
                    'Cashback paid by seller' => isset($funds['cashback_paid_by_seller']) ? $fmt($funds['cashback_paid_by_seller']) : null,
                    'Transaction service fee' => isset($funds['transaction_service_fee']) ? $fmt($funds['transaction_service_fee']) : null,
                    'Platform offer tax' => isset($funds['platform_offer_tax']) ? $fmt($funds['platform_offer_tax']) : null,
                    'Amount paid' => isset($funds['amount_paid']) ? $fmt($funds['amount_paid']) : null,
                ]])
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Shipment information</div>
            <div class="card-body p-0">
                @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
                    'Shipping time' => !empty($shipment['shipped_at']) ? \Carbon\Carbon::parse($shipment['shipped_at'])->format('M d, Y H:i') : null,
                    'Shipping method' => $shipment['service'] ?? null,
                    'Tracking number' => !empty($shipment['tracking']) ? '<code>'.e($shipment['tracking']).'</code>' : null,
                    'Status' => $shipment['status'] ?? null,
                    'Status message' => $shipment['status_message'] ?? null,
                ]])
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Buyer</div>
                    <div class="card-body p-0">
                        @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
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
                    <div class="card-header">Complete shipping address</div>
                    <div class="card-body p-0">
                        @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
                            'Receiver name' => $shipping['recipient'] ?? null,
                            'Detailed address' => $shipping['detail_address'] ?? $shipping['full_address'] ?? null,
                            'Address line 1' => $shipping['address_line_1'] ?? null,
                            'Address line 2' => $shipping['address_line_2'] ?? null,
                            'City' => $shipping['city'] ?? null,
                            'State / Province' => $shipping['province'] ?? null,
                            'Zip code' => $shipping['zip'] ?? null,
                            'Country' => $shipping['country_name'] ?? $shipping['country'] ?? null,
                            'Localized address' => $shipping['localized_address'] ?? null,
                            'Email' => $shipping['email'] ?? null,
                            'Phone' => $shipping['phone'] ?? null,
                            'Tax number' => $shipping['tax_number'] ?? null,
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
                                <th>Shipped</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logistics as $log)
                                <tr>
                                    <td class="ps-3">{{ $log['service'] ?? '—' }}</td>
                                    <td><code>{{ $log['tracking'] ?? '—' }}</code></td>
                                    <td class="small">{{ !empty($log['shipped_at']) ? \Carbon\Carbon::parse($log['shipped_at'])->format('M d, Y H:i') : '—' }}</td>
                                    <td>{{ $log['status'] ?? '—' }}</td>
                                    <td class="small">{{ $log['status_message'] ?? '—' }}</td>
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
                                <th>Child order</th>
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
                                            <img src="{{ $item['image'] }}" alt="" class="img-thumbnail" style="max-width:56px; max-height:56px; object-fit:contain;" referrerpolicy="no-referrer" loading="lazy">
                                        @else — @endif
                                    </td>
                                    <td><code>{{ $item['sku'] ?? '—' }}</code></td>
                                    <td>{{ $item['product_id'] ?? '—' }}</td>
                                    <td>{{ $item['child_order_id'] ?? '—' }}</td>
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
                                <tr><td colspan="10" class="text-center text-muted py-4">No line items found.</td></tr>
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
                            @else — @endif
                        </td></tr>
                        <tr><th class="ps-3">Sent to Shopify</th><td class="small text-muted">
                            Shipping address, buyer email/phone, payment method, tracking number, tax number, and all line items.
                        </td></tr>
                        <tr><th class="ps-3">Action</th><td>
                            @if($shopify['shopify_order_id'] ?? null)
                                <span class="text-muted">Already imported</span>
                            @else
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-dry-run-shopify" data-id="{{ $line->id }}">
                                        Dry run (preview)
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning" id="btn-push-order" data-id="{{ $line->id }}">
                                        Push to Shopify
                                    </button>
                                </div>
                            @endif
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="shopifyDryRunModal" tabindex="-1" aria-labelledby="shopifyDryRunModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shopifyDryRunModalLabel">Shopify push preview (dry run)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="shopify-dry-run-summary" class="mb-3"></div>
                <pre id="shopify-dry-run-json" class="small bg-light border rounded p-3 mb-0" style="max-height: 420px; overflow: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-pull-ae-order')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Pulling…';
    fetch('{{ url('marketplace/aliexpress/orders') }}/' + id + '/pull', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        alert(data.message || (data.success ? 'Done' : 'Failed'));
        if (data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-download-cloud-line"></i> Pull from AliExpress';
    });
});

document.getElementById('btn-dry-run-shopify')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    btn.disabled = true;
    btn.textContent = 'Running dry run…';
    fetch('{{ route('marketplace.orders.push', 'aliexpress') }}', {
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
        var summaryEl = document.getElementById('shopify-dry-run-summary');
        var jsonEl = document.getElementById('shopify-dry-run-json');
        if (!data.success) {
            summaryEl.innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'Dry run failed') + '</div>';
            jsonEl.textContent = JSON.stringify(data, null, 2);
        } else {
            var p = data.preview || {};
            var warnHtml = (data.warnings || []).length
                ? '<div class="alert alert-warning py-2 small"><strong>Warnings</strong><ul class="mb-0 ps-3">' + data.warnings.map(function (w) { return '<li>' + w + '</li>'; }).join('') + '</ul></div>'
                : '<div class="alert alert-success py-2 small mb-2">Ready to push — no warnings.</div>';
            var linesHtml = '<ul class="small mb-2">';
            (p.line_items || []).forEach(function (li) {
                linesHtml += '<li><code>' + (li.sku || '—') + '</code> × ' + li.quantity + ' @ $' + li.price
                    + (li.variant_id ? ' → variant ' + li.variant_id : ' → custom line') + '</li>';
            });
            linesHtml += '</ul>';
            var customerName = (p.customer && p.customer.name) ? p.customer.name : '—';
            var customerEmail = (p.customer && p.customer.email) ? p.customer.email : '—';
            if (p.customer && p.customer.email_is_placeholder) {
                customerEmail += ' (placeholder)';
            }
            summaryEl.innerHTML = warnHtml
                + '<p class="small mb-1"><strong>Store:</strong> ' + (data.shopify_store || '—') + '</p>'
                + '<p class="small mb-1"><strong>Customer:</strong> ' + customerName + ' &lt;' + customerEmail + '&gt;</p>'
                + '<p class="small mb-1"><strong>Ship to:</strong> ' + ((p.shipping_address && p.shipping_address.address1) || '—') + ', '
                + ((p.shipping_address && p.shipping_address.city) || '') + ' ' + ((p.shipping_address && p.shipping_address.province) || '') + ' '
                + ((p.shipping_address && p.shipping_address.zip) || '') + ' ' + ((p.shipping_address && p.shipping_address.country_code) || '') + '</p>'
                + '<p class="small mb-1"><strong>Tracking:</strong> ' + (p.tracking || '—') + ' (' + (p.shipping_method || '—') + ')</p>'
                + '<p class="small mb-1"><strong>Line items</strong></p>' + linesHtml
                + '<p class="small text-muted mb-0">' + (data.message || '') + '</p>';
            jsonEl.textContent = JSON.stringify(data.payload || data, null, 2);
        }
        var modal = new bootstrap.Modal(document.getElementById('shopifyDryRunModal'));
        modal.show();
    })
    .catch(function () { alert('Dry run request failed.'); })
    .finally(function () {
        btn.disabled = false;
        btn.textContent = 'Dry run (preview)';
    });
});

document.getElementById('btn-push-order')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    if (!confirm('Create a real Shopify order from this AliExpress order?')) return;
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
