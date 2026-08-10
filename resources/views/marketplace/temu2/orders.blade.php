@extends('layouts.vertical', ['title' => $title ?? 'Temu — Orders', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'temu2') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Temu 2 Manager</a>
        @include('marketplace._page-heading', ['slug' => 'temu2', 'heading' => 'Temu Orders'])
        <p class="text-muted mb-3">Orders stored locally from Temu 2 API. Push to Shopify manually or enable auto-import in <a href="{{ route('marketplace.settings', 'temu2') }}">Settings</a>.</p>

        @include('marketplace.temu2._nav', ['active' => 'orders'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">{{ $orders->total() }} orders</span>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <button type="button" class="btn btn-sm btn-warning" id="btn-bulk-push" disabled>
                        <i class="ri-upload-cloud-2-line"></i> Push selected (<span id="bulk-push-count">0</span>)
                    </button>
                    <select id="fetch-days" class="form-select form-select-sm" style="width:auto;">
                        <option value="from:2026-07-07" selected>From July 7, 2026 onward</option>
                        <option value="7">Last 7 days (from July 7 min)</option>
                        <option value="30">Last 30 days (from July 7 min)</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-fetch-orders">
                        <i class="ri-download-cloud-line"></i> Fetch from Temu 2
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:36px;" onclick="event.stopPropagation();">
                                    <input type="checkbox" class="form-check-input" id="select-all-pushable" title="Select all pushable on this page">
                                </th>
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
                                @php
                                    $orderId = $o->parent_order_sn ?: $o->order_sn;
                                    $orderUrl = route('marketplace.orders.show', ['marketplace' => 'temu2', 'order' => $o->id]);
                                    $sku = $o->ext_code ?: $o->display_sku;
                                    $title = $o->goods_name;
                                    $amount = $o->order_base_amount ?? $o->order_total_amount;
                                    $status = $o->parent_order_status_text ?: $o->order_status_text;
                                    $pushBlocked = ($importPaidOrdersOnly ?? false)
                                        && ! \App\Services\MarketplaceManager\MarketplaceOrderPaidFilter::isPaid('temu2', $o);
                                    $canPush = empty($o->shopify_order_id) && ! $pushBlocked;
                                @endphp
                                <tr style="cursor: pointer;" onclick="window.location='{{ $orderUrl }}'">
                                    <td onclick="event.stopPropagation();">
                                        @if($canPush)
                                            <input type="checkbox" class="form-check-input order-push-check" value="{{ $o->id }}">
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ $orderUrl }}" class="text-decoration-none" onclick="event.stopPropagation();">{{ $orderId ?: '—' }}</a>
                                    </td>
                                    <td class="small">
                                        @if($o->parent_order_time)
                                            {{ \Carbon\Carbon::parse($o->parent_order_time)->format('M d, Y H:i') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td><span class="badge bg-secondary">{{ $status ?: '—' }}</span></td>
                                    <td><code>{{ $sku ?: '—' }}</code></td>
                                    <td>{{ Str::limit($title ?? '—', 40) }}</td>
                                    <td>{{ $o->quantity ?? 1 }}</td>
                                    <td>{{ is_numeric($amount) ? number_format((float) $amount, 2) : '—' }}</td>
                                    <td>
                                        @if($o->shopify_order_id)
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
                                        @else
                                            <span class="badge bg-light text-muted">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($o->shopify_order_id)
                                            —
                                        @else
                                            <div class="d-flex gap-1 flex-wrap" onclick="event.stopPropagation();">
                                                @if($pushBlocked)
                                                    <button type="button" class="btn btn-sm btn-secondary" disabled title="{{ \App\Services\MarketplaceManager\MarketplaceOrderPaidFilter::unpaidPushBlockedMessage() }}">Push to Shopify</button>
                                                    <small class="text-muted align-self-center">Turn off “Only auto-import paid orders” in Settings to push unpaid orders.</small>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-warning btn-push-order" data-id="{{ $o->id }}">Push to Shopify</button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-outline-success btn-mark-imported" data-id="{{ $o->id }}" data-order-id="{{ $orderId }}" title="Mark as already imported / entered manually">Already imported</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ready-order" data-id="{{ $o->id }}" data-order-id="{{ $orderId }}" title="Remove from ready-for-import">Delete</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No orders yet. Click <strong>Fetch from Temu 2</strong>.
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
(function () {
    function selectedIds() {
        return Array.prototype.slice.call(document.querySelectorAll('.order-push-check:checked'))
            .map(function (el) { return parseInt(el.value, 10); })
            .filter(function (id) { return id > 0; });
    }

    function refreshBulkUi() {
        var ids = selectedIds();
        var countEl = document.getElementById('bulk-push-count');
        var btn = document.getElementById('btn-bulk-push');
        if (countEl) countEl.textContent = String(ids.length);
        if (btn) btn.disabled = ids.length === 0;
        var all = document.querySelectorAll('.order-push-check');
        var selectAll = document.getElementById('select-all-pushable');
        if (selectAll && all.length) {
            selectAll.checked = ids.length > 0 && ids.length === all.length;
            selectAll.indeterminate = ids.length > 0 && ids.length < all.length;
        }
    }

    document.getElementById('select-all-pushable')?.addEventListener('change', function () {
        document.querySelectorAll('.order-push-check').forEach(function (el) {
            el.checked = !!document.getElementById('select-all-pushable').checked;
        });
        refreshBulkUi();
    });

    document.querySelectorAll('.order-push-check').forEach(function (el) {
        el.addEventListener('change', refreshBulkUi);
    });

    document.getElementById('btn-bulk-push')?.addEventListener('click', function () {
        var ids = selectedIds();
        if (!ids.length) return;
        if (ids.length > 50) {
            alert('Select at most 50 orders at a time.');
            return;
        }
        if (!confirm('Push ' + ids.length + ' Temu 2 order(s) to Shopify?\n\nThis may take a minute. Already-imported parents will be skipped.')) {
            return;
        }
        var btn = this;
        btn.disabled = true;
        fetch('{{ route('marketplace.orders.bulk-push', 'temu2') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ids: ids }),
        })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
            alert(res.data.message || (res.data.success ? 'Done' : 'Failed'));
            if (res.data.pushed > 0 || res.data.failed > 0 || res.data.skipped > 0) {
                location.reload();
            } else {
                btn.disabled = selectedIds().length === 0;
            }
        })
        .catch(function () {
            alert('Request failed.');
            btn.disabled = false;
        });
    });

    document.getElementById('btn-fetch-orders')?.addEventListener('click', function () {
        var btn = this;
        var selected = document.getElementById('fetch-days')?.value || '0';
        var body = { import: false };
        var confirmMsg = '';

        if (selected.indexOf('from:') === 0) {
            var fromDate = selected.slice(5);
            body.from_date = fromDate;
            confirmMsg = 'Fetch Temu 2 orders from ' + fromDate + ' onward?\n\nThis will NOT auto-push to Shopify (avoids duplicates for orders already entered).';
        } else {
            var days = parseInt(selected, 10);
            body.days = days;
            confirmMsg = days === 0
                ? 'Fetch all Temu 2 orders (up to 2 years)? This may take several minutes.\n\nThis will NOT auto-push to Shopify.'
                : 'Fetch orders from the last ' + days + ' days?\n\nThis will NOT auto-push to Shopify.';
        }

        if (!confirm(confirmMsg)) {
            return;
        }
        btn.disabled = true;
        fetch('{{ route('marketplace.manager.temu2.fetch.orders') }}', {
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
            this.disabled = true;
            fetch('{{ route('marketplace.orders.push', 'temu2') }}', {
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

    document.querySelectorAll('.btn-delete-ready-order').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            var orderId = this.getAttribute('data-order-id') || id;
            if (!id) return;
            if (!confirm('Delete Temu 2 order ' + orderId + ' from ready-for-import?\n\nThis only removes it from our platform. It does not delete the order on Temu or Shopify.')) {
                return;
            }
            this.disabled = true;
            fetch('{{ route('marketplace.orders.delete-ready', 'temu2') }}', {
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

    document.querySelectorAll('.btn-mark-imported').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            var orderId = this.getAttribute('data-order-id') || id;
            if (!id) return;
            if (!confirm('Mark Temu 2 order ' + orderId + ' as already imported?\n\nUse this if the order was already entered in Shopify manually. No new Shopify order will be created.')) {
                return;
            }
            var shopifyOrderId = prompt('Optional Shopify order ID (leave blank if entered manually):', '') || '';
            this.disabled = true;
            fetch('{{ route('marketplace.orders.mark-imported', 'temu2') }}', {
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
                alert(data.message || (data.success ? 'Marked' : 'Failed'));
                if (data.success) location.reload();
                else btn.disabled = false;
            })
            .catch(function () {
                alert('Request failed.');
                btn.disabled = false;
            });
        });
    });

    refreshBulkUi();
})();
</script>
@endsection
