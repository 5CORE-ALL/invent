@extends('layouts.vertical', ['title' => $title ?? 'AliExpress — Orders', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'aliexpress') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> AliExpress Manager</a>
        <h4 class="mt-2 mb-1">AliExpress Orders</h4>
        <p class="text-muted mb-3">Orders stored locally from AliExpress API. Push to Shopify manually or enable auto-import in <a href="{{ route('marketplace.settings', 'aliexpress') }}">Settings</a>.</p>

        @include('marketplace.aliexpress._nav', ['active' => 'orders'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">{{ $orders->total() }} orders</span>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <select id="fetch-days" class="form-select form-select-sm" style="width:auto;">
                        <option value="0" selected>All orders (2 years)</option>
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="365">Last 365 days</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-fetch-orders">
                        <i class="ri-download-cloud-line"></i> Fetch from AliExpress
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
                                <th>SKU</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Amount</th>
                                <th>Shopify</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $o)
                                @php $orderUrl = route('marketplace.orders.show', ['marketplace' => 'aliexpress', 'order' => $o->id]); @endphp
                                <tr style="cursor: pointer;" onclick="window.location='{{ $orderUrl }}'">
                                    <td>
                                        <a href="{{ $orderUrl }}" class="text-decoration-none" onclick="event.stopPropagation();">{{ $o->order_id }}</a>
                                    </td>
                                    <td class="small">
                                        @if($o->order_date)
                                            {{ \Carbon\Carbon::parse($o->order_date)->format('M d, Y H:i') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td><span class="badge bg-secondary">{{ $o->status }}</span></td>
                                    <td><code>{{ $o->sku }}</code></td>
                                    <td>{{ Str::limit($o->display_title ?? '—', 40) }}</td>
                                    <td>{{ $o->quantity ?? 1 }}</td>
                                    <td>{{ is_numeric($o->amount) ? number_format((float)$o->amount, 2) : '—' }}</td>
                                    <td>
                                        @if($o->shopify_order_id)
                                            <span class="badge bg-success">Imported</span>
                                            <small class="d-block text-muted">{{ $o->shopify_order_id }}</small>
                                        @elseif(($o->import_status ?? '') === 'queued')
                                            <span class="badge bg-info">Queued</span>
                                        @elseif(($o->import_status ?? '') === 'import_failed')
                                            <span class="badge bg-danger">Failed</span>
                                        @else
                                            <span class="badge bg-light text-muted">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($o->shopify_order_id)
                                            —
                                        @else
                                            <div class="d-flex gap-1 flex-wrap" onclick="event.stopPropagation();">
                                                <button type="button" class="btn btn-sm btn-warning btn-push-order" data-id="{{ $o->id }}">Push to Shopify</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ready-order" data-id="{{ $o->id }}" data-order-id="{{ $o->order_id }}" title="Remove from ready-for-import">Delete</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No orders yet. Click <strong>Fetch from AliExpress</strong>.
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
    var days = parseInt(document.getElementById('fetch-days')?.value || '0', 10);
    if (!confirm(days === 0
        ? 'Fetch all AliExpress orders (up to 2 years)? This may take several minutes.'
        : 'Fetch orders from the last ' + days + ' days?')) {
        return;
    }
    btn.disabled = true;
    fetch('{{ route('marketplace.manager.aliexpress.fetch.orders') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ days: days, import: true }),
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
        this.disabled = true;
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
        .finally(function () { btn.disabled = false; }.bind(this));
    });
});

document.querySelectorAll('.btn-delete-ready-order').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        var orderId = this.getAttribute('data-order-id') || id;
        if (!id) return;
        if (!confirm('Delete AliExpress order ' + orderId + ' from ready-for-import?\n\nThis only removes it from our platform. It does not delete the order on AliExpress or Shopify.')) {
            return;
        }
        this.disabled = true;
        fetch('{{ route('marketplace.orders.delete-ready', 'aliexpress') }}', {
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
            alert(data.message || (data.success ? 'Deleted' : 'Failed'));
            if (data.success) location.reload();
            else btn.disabled = false;
        })
        .catch(function () {
            alert('Request failed.');
            btn.disabled = false;
        });
    });
});
</script>
@endsection
