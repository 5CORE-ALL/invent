@extends('layouts.vertical', ['title' => $title ?? 'Amz — Orders', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .badge.amazon-orders-badge,
    .badge.amazon-status-badge {
        font-size: 1.5rem !important;
        padding: 0.7rem 1.3rem !important;
        line-height: 1.2;
    }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'amazon') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Amz Manager</a>
        @include('marketplace._page-heading', ['slug' => 'amazon', 'heading' => 'Amz Orders'])
        <p class="text-muted mb-3">
            Orders stored locally from Amz SP-API. FBM orders on/after {{ $shopifyImportCutoff ?? '2026-08-06' }} PT
            are pushed to Shopify (FBA is never created). Duplicate check links existing Shopify orders
            from the previous sync app. Configure auto-import in
            <a href="{{ route('marketplace.settings', 'amazon') }}">Settings</a>.
        </p>

        @include('marketplace.amazon._nav', ['active' => 'orders'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        @php
            $statusBadgeClass = [
                'SHIPPED' => 'bg-success',
                'UNSHIPPED' => 'bg-warning text-dark',
                'PARTIALLYSHIPPED' => 'bg-info text-dark',
                'CANCELED' => 'bg-danger',
                'CANCELLED' => 'bg-danger',
                'PENDING' => 'bg-secondary',
                'UNKNOWN' => 'bg-secondary',
            ];
        @endphp

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-primary amazon-orders-badge">{{ $orders->total() }} orders</span>
                    @foreach(($statusCounts ?? []) as $status => $count)
                        @php
                            $key = strtoupper((string) $status);
                            $cls = $statusBadgeClass[$key] ?? 'bg-secondary';
                            $label = str_replace('_', ' ', ucwords(strtolower($key), ' _'));
                        @endphp
                        <span class="badge {{ $cls }} amazon-status-badge" title="{{ $label }}">{{ $label }}: {{ number_format((int) $count) }}</span>
                    @endforeach
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="text" name="q" value="{{ $search ?? '' }}" class="form-control form-control-sm" placeholder="Order / SKU / ASIN" style="width:180px;">
                        <select name="status" class="form-select form-select-sm" style="width:auto;">
                            <option value="">All statuses</option>
                            @foreach(($statusCounts ?? []) as $status => $count)
                                <option value="{{ $status }}" @selected(($statusFilter ?? '') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                    </form>
                    <select id="fetch-days" class="form-select form-select-sm" style="width:auto;">
                        <option value="7" selected>Last 7 days</option>
                        <option value="14">Last 14 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="from:{{ now('America/Los_Angeles')->subDays(35)->toDateString() }}">Last 35 days</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-fetch-orders">
                        <i class="ri-download-cloud-line"></i> Fetch from Amz
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>SKU / ASIN</th>
                                <th>Items</th>
                                <th>Qty</th>
                                <th>Amount</th>
                                <th>Shopify</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $o)
                                @php
                                    $orderUrl = route('marketplace.orders.show', ['marketplace' => 'amazon', 'order' => $o->id]);
                                    $firstItem = $o->items->first();
                                    $qty = (int) $o->items->sum('quantity');
                                    $lineSum = (float) $o->items->sum('price');
                                    $amount = (float) ($o->total_amount ?: $lineSum);
                                    $isFba = $o->isFba();
                                    $channel = $o->fulfillmentChannel();
                                    $pushBlocked = ($importPaidOrdersOnly ?? false)
                                        && ! \App\Services\MarketplaceManager\MarketplaceOrderPaidFilter::isPaid('amazon', $o);
                                    $canPush = empty($o->shopify_order_id) && ! $isFba && $o->canCreateShopifyOrder() && ! $pushBlocked;
                                @endphp
                                <tr style="cursor: pointer;" onclick="window.location='{{ $orderUrl }}'">
                                    <td>
                                        <a href="{{ $orderUrl }}" class="text-decoration-none" onclick="event.stopPropagation();">{{ $o->amazon_order_id }}</a>
                                    </td>
                                    <td class="small">
                                        @if($o->order_date)
                                            {{ \Carbon\Carbon::parse($o->order_date)->timezone('America/Los_Angeles')->format('M d, Y H:i') }}
                                            <span class="text-muted">PT</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $rowStatus = strtoupper(trim((string) ($o->status ?? 'UNKNOWN')));
                                            $rowCls = $statusBadgeClass[$rowStatus] ?? 'bg-secondary';
                                        @endphp
                                        <span class="badge {{ $rowCls }}">{{ $o->status ?: '—' }}</span>
                                    </td>
                                    <td>
                                        @if($firstItem)
                                            <code>{{ $firstItem->sku ?: '—' }}</code>
                                            <small class="d-block text-muted">{{ $firstItem->asin ?: '' }}</small>
                                            @if($o->items->count() > 1)
                                                <small class="text-muted">+{{ $o->items->count() - 1 }} more</small>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $o->items->count() }}</td>
                                    <td>{{ $qty }}</td>
                                    <td>
                                        {{ $amount > 0 ? number_format($amount, 2) : '—' }}
                                        <small class="text-muted">{{ $o->currency ?: 'USD' }}</small>
                                    </td>
                                    <td>
                                        @if($isFba)
                                            <span class="badge bg-dark">FBA</span>
                                            <small class="d-block text-muted">Not sent to Shopify</small>
                                        @elseif($o->shopify_order_id)
                                            @if(str_starts_with((string) $o->shopify_order_id, 'manual'))
                                                <span class="badge bg-success">Already imported</span>
                                            @else
                                                <span class="badge bg-success">Imported</span>
                                            @endif
                                            <small class="d-block text-muted">{{ $o->shopify_order_id }}</small>
                                        @elseif(($o->import_status ?? '') === 'queued')
                                            <span class="badge bg-info">Queued</span>
                                        @elseif(($o->import_status ?? '') === 'import_failed')
                                            <span class="badge bg-danger">Failed</span>
                                        @elseif(($o->import_status ?? '') === 'skipped_pre_cutoff')
                                            <span class="badge bg-secondary">Pre-cutoff</span>
                                        @else
                                            <span class="badge bg-light text-muted">Pending</span>
                                            @if($channel)
                                                <small class="d-block text-muted">{{ $channel }}</small>
                                            @endif
                                        @endif
                                    </td>
                                    <td onclick="event.stopPropagation();">
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="{{ $orderUrl }}" class="btn btn-sm btn-outline-primary">View</a>
                                            @if(empty($o->shopify_order_id) && ! $isFba)
                                                @if($canPush)
                                                    <button type="button" class="btn btn-sm btn-warning btn-push-order" data-id="{{ $o->id }}">Push to Shopify</button>
                                                @elseif($pushBlocked)
                                                    <button type="button" class="btn btn-sm btn-secondary" disabled title="{{ \App\Services\MarketplaceManager\MarketplaceOrderPaidFilter::unpaidPushBlockedMessage() }}">Push to Shopify</button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-outline-success btn-mark-imported" data-id="{{ $o->id }}" data-order-id="{{ $o->amazon_order_id }}" title="Mark as already imported — no new Shopify order">Already imported</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No orders yet. Click <strong>Fetch from Amz</strong>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($orders->hasPages())
                    <div class="d-flex justify-content-center py-3">{{ $orders->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-fetch-orders')?.addEventListener('click', function () {
    var btn = this;
    var selected = document.getElementById('fetch-days')?.value || '7';
    var body = {};
    var confirmMsg = '';

    if (selected.indexOf('from:') === 0) {
        var fromDate = selected.slice(5);
        body.from_date = fromDate;
        confirmMsg = 'Fetch Amz orders from ' + fromDate + ' onward (Pacific)?\n\nThis will NOT auto-push to Shopify (avoids duplicates).';
    } else {
        var days = parseInt(selected, 10) || 7;
        body.days = days;
        confirmMsg = 'Fetch Amz orders from the last ' + days + ' days (Pacific)?\n\nThis will NOT auto-push to Shopify.';
    }

    if (!confirm(confirmMsg)) {
        return;
    }
    btn.disabled = true;
    fetch('{{ route('marketplace.manager.amazon.fetch.orders') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        alert(data.message || (data.success ? 'Done' : 'Failed'));
        if (data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () { btn.disabled = false; });
});

document.querySelectorAll('.btn-push-order').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        if (!id) return;
        if (!confirm('Create a Shopify order from this Amazon FBM order?\n\nFBA orders are never created. Already-synced orders are linked, not duplicated.')) return;
        this.disabled = true;
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
        .finally(function () { btn.disabled = false; }.bind(this));
    });
});

document.querySelectorAll('.btn-mark-imported').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        var orderId = this.getAttribute('data-order-id') || id;
        if (!id) return;
        if (!confirm('Mark Amazon order ' + orderId + ' as already imported?\n\nNo new Shopify order will be created.')) return;
        var shopifyOrderId = prompt('Optional Shopify order ID (leave blank if entered manually):', '') || '';
        this.disabled = true;
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
        .finally(function () { btn.disabled = false; }.bind(this));
    });
});
</script>
@endsection
