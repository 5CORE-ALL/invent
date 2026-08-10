@extends('layouts.vertical', ['title' => $title ?? 'Temu — Orders', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'temu') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Temu Manager</a>
        @include('marketplace._page-heading', ['slug' => 'temu', 'heading' => 'Temu Orders'])
        <p class="text-muted mb-3">Orders stored locally from Temu API. Push to Shopify manually or enable auto-import in <a href="{{ route('marketplace.settings', 'temu') }}">Settings</a>.</p>

        @include('marketplace.temu._nav', ['active' => 'orders'])

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
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btn-bulk-reverse" disabled title="Unlink selected pushed SKUs from Shopify (local only)">
                        <i class="ri-arrow-go-back-line"></i> Reverse selected (<span id="bulk-reverse-count">0</span>)
                    </button>
                    <span id="bulk-push-status" class="small text-muted" style="display:none;"></span>
                    <select id="fetch-days" class="form-select form-select-sm" style="width:auto;">
                        <option value="from:2026-07-07" selected>From July 7, 2026 onward</option>
                        <option value="7">Last 7 days (from July 7 min)</option>
                        <option value="30">Last 30 days (from July 7 min)</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-fetch-orders">
                        <i class="ri-download-cloud-line"></i> Fetch from Temu
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:36px;" onclick="event.stopPropagation();">
                                    <input type="checkbox" class="form-check-input" id="select-all-orders" title="Select all on this page">
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
                                    $orderUrl = route('marketplace.orders.show', ['marketplace' => 'temu', 'order' => $o->id]);
                                    $sku = $o->ext_code ?: $o->display_sku;
                                    $title = $o->goods_name;
                                    $amount = $o->order_base_amount ?? $o->order_total_amount;
                                    $status = $o->parent_order_status_text ?: $o->order_status_text;
                                    $pushBlocked = ($importPaidOrdersOnly ?? false)
                                        && ! \App\Services\MarketplaceManager\MarketplaceOrderPaidFilter::isPaid('temu', $o);
                                    $isPushed = ! empty($o->shopify_order_id);
                                    $canPush = ! $isPushed && ! $pushBlocked;
                                @endphp
                                <tr style="cursor: pointer;" onclick="window.location='{{ $orderUrl }}'" data-order-id="{{ $o->id }}" data-sku="{{ e($sku ?: '') }}" data-pushed="{{ $isPushed ? '1' : '0' }}">
                                    <td onclick="event.stopPropagation();">
                                        <input type="checkbox"
                                               class="form-check-input order-select-check"
                                               value="{{ $o->id }}"
                                               data-sku="{{ e($sku ?: '') }}"
                                               data-pushed="{{ $isPushed ? '1' : '0' }}"
                                               data-pushable="{{ $canPush ? '1' : '0' }}">
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
                                    <td class="shopify-status-cell" data-order-id="{{ $o->id }}">
                                        @if($o->shopify_order_id)
                                            @if(str_starts_with((string) $o->shopify_order_id, 'manual'))
                                                <span class="badge bg-success">Already imported</span>
                                            @else
                                                <span class="badge bg-success">Pushed</span>
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
                                    <td class="order-action-cell">
                                        <div class="d-flex gap-1 flex-wrap" onclick="event.stopPropagation();">
                                            @if($isPushed)
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-reverse-order" data-id="{{ $o->id }}" data-sku="{{ e($sku ?: '') }}" title="Unlink this SKU from Shopify (local only)">Reverse</button>
                                            @elseif($pushBlocked)
                                                <button type="button" class="btn btn-sm btn-secondary" disabled title="{{ \App\Services\MarketplaceManager\MarketplaceOrderPaidFilter::unpaidPushBlockedMessage() }}">Push to Shopify</button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-warning btn-push-order" data-id="{{ $o->id }}">Push to Shopify</button>
                                                <button type="button" class="btn btn-sm btn-outline-success btn-mark-imported" data-id="{{ $o->id }}" data-order-id="{{ $orderId }}" title="Mark as already imported / entered manually">Already imported</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ready-order" data-id="{{ $o->id }}" data-order-id="{{ $orderId }}" title="Remove from ready-for-import">Delete</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No orders yet. Click <strong>Fetch from Temu</strong>.
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
    function selectedChecks(filterFn) {
        return Array.prototype.slice.call(document.querySelectorAll('.order-select-check:checked'))
            .filter(function (el) { return !filterFn || filterFn(el); });
    }

    function selectedPushableIds() {
        return selectedChecks(function (el) { return el.getAttribute('data-pushable') === '1'; })
            .map(function (el) { return parseInt(el.value, 10); })
            .filter(function (id) { return id > 0; });
    }

    function selectedPushedIds() {
        return selectedChecks(function (el) { return el.getAttribute('data-pushed') === '1'; })
            .map(function (el) { return parseInt(el.value, 10); })
            .filter(function (id) { return id > 0; });
    }

    function refreshBulkUi() {
        var pushIds = selectedPushableIds();
        var reverseIds = selectedPushedIds();
        var pushCount = document.getElementById('bulk-push-count');
        var reverseCount = document.getElementById('bulk-reverse-count');
        var pushBtn = document.getElementById('btn-bulk-push');
        var reverseBtn = document.getElementById('btn-bulk-reverse');
        if (pushCount) pushCount.textContent = String(pushIds.length);
        if (reverseCount) reverseCount.textContent = String(reverseIds.length);
        if (pushBtn) pushBtn.disabled = pushIds.length === 0;
        if (reverseBtn) reverseBtn.disabled = reverseIds.length === 0;
        var all = document.querySelectorAll('.order-select-check');
        var checked = document.querySelectorAll('.order-select-check:checked');
        var selectAll = document.getElementById('select-all-orders');
        if (selectAll && all.length) {
            selectAll.checked = checked.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    }

    document.getElementById('select-all-orders')?.addEventListener('change', function () {
        var on = !!document.getElementById('select-all-orders').checked;
        document.querySelectorAll('.order-select-check').forEach(function (el) { el.checked = on; });
        refreshBulkUi();
    });

    document.querySelectorAll('.order-select-check').forEach(function (el) {
        el.addEventListener('change', refreshBulkUi);
    });

    function setShopifyCell(orderId, html) {
        var cell = document.querySelector('.shopify-status-cell[data-order-id="' + orderId + '"]');
        if (cell) cell.innerHTML = html;
    }

    function rowByOrderId(orderId) {
        return document.querySelector('.shopify-status-cell[data-order-id="' + orderId + '"]')?.closest('tr');
    }

    function setRowPushing(orderId) {
        setShopifyCell(orderId, '<span class="badge bg-warning text-dark">Pushing…</span>');
        var row = rowByOrderId(orderId);
        if (!row) return;
        row.querySelectorAll('.btn-push-order, .order-select-check, .btn-mark-imported, .btn-delete-ready-order, .btn-reverse-order').forEach(function (el) {
            el.disabled = true;
        });
    }

    function setRowPushed(orderId, shopifyOrderId) {
        var sid = shopifyOrderId || '';
        setShopifyCell(orderId,
            '<span class="badge bg-success">Pushed</span>' +
            (sid ? '<small class="d-block text-muted">' + sid + '</small>' : '')
        );
        var row = rowByOrderId(orderId);
        if (!row) return;
        row.setAttribute('data-pushed', '1');
        var check = row.querySelector('.order-select-check');
        if (check) {
            check.checked = false;
            check.setAttribute('data-pushed', '1');
            check.setAttribute('data-pushable', '0');
            check.disabled = false;
        }
        var action = row.querySelector('.order-action-cell');
        var sku = (check && check.getAttribute('data-sku')) || row.getAttribute('data-sku') || '';
        if (action) {
            action.innerHTML =
                '<div class="d-flex gap-1 flex-wrap" onclick="event.stopPropagation();">' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-reverse-order" data-id="' + orderId + '" data-sku="' + sku.replace(/"/g, '&quot;') + '" title="Unlink this SKU from Shopify (local only)">Reverse</button>' +
                '</div>';
            action.querySelector('.btn-reverse-order')?.addEventListener('click', onReverseClick);
        }
    }

    function setRowPending(orderId) {
        setShopifyCell(orderId, '<span class="badge bg-light text-muted">Pending</span>');
        var row = rowByOrderId(orderId);
        if (!row) return;
        row.setAttribute('data-pushed', '0');
        var check = row.querySelector('.order-select-check');
        if (check) {
            check.checked = false;
            check.setAttribute('data-pushed', '0');
            check.setAttribute('data-pushable', '1');
            check.disabled = false;
        }
        var action = row.querySelector('.order-action-cell');
        if (action) {
            action.innerHTML =
                '<div class="d-flex gap-1 flex-wrap" onclick="event.stopPropagation();">' +
                '<button type="button" class="btn btn-sm btn-warning btn-push-order" data-id="' + orderId + '">Push to Shopify</button>' +
                '</div>';
            action.querySelector('.btn-push-order')?.addEventListener('click', onPushClick);
        }
    }

    function setRowFailed(orderId, message) {
        var msg = (message || 'Push failed').toString();
        setShopifyCell(orderId,
            '<span class="badge bg-danger">Failed</span>' +
            '<small class="d-block text-danger" title="' + msg.replace(/"/g, '&quot;') + '">' +
            (msg.length > 48 ? msg.slice(0, 48) + '…' : msg) +
            '</small>'
        );
        var row = rowByOrderId(orderId);
        if (!row) return;
        row.querySelectorAll('.btn-push-order, .order-select-check, .btn-mark-imported, .btn-delete-ready-order, .btn-reverse-order').forEach(function (el) {
            el.disabled = false;
        });
    }

    function updateBulkStatus(total, done, pushed, failed, skipped, running) {
        var el = document.getElementById('bulk-push-status');
        if (!el) return;
        el.style.display = '';
        if (running) {
            el.innerHTML =
                '<span class="badge bg-warning text-dark me-1">Pushing ' + done + '/' + total + '</span>' +
                '<span class="text-success me-1">Pushed ' + pushed + '</span>' +
                '<span class="text-danger me-1">Failed ' + failed + '</span>' +
                '<span class="text-muted">Skipped ' + skipped + '</span>';
        } else {
            el.innerHTML =
                '<span class="badge bg-secondary me-1">Done ' + done + '/' + total + '</span>' +
                '<span class="text-success me-1">Pushed ' + pushed + '</span>' +
                '<span class="text-danger me-1">Failed ' + failed + '</span>' +
                '<span class="text-muted">Skipped ' + skipped + '</span>';
        }
    }

    async function pushOneOrder(id) {
        var response = await fetch('{{ route('marketplace.orders.push', 'temu') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id }),
        });
        var data = await response.json().catch(function () { return {}; });
        return { ok: response.ok, data: data };
    }

    document.getElementById('btn-bulk-push')?.addEventListener('click', async function () {
        var ids = selectedPushableIds();
        if (!ids.length) return;
        if (ids.length > 50) {
            alert('Select at most 50 orders at a time.');
            return;
        }
        if (!confirm('Push ' + ids.length + ' Temu order(s) to Shopify?\n\nRows will update live as each order is pushed.')) {
            return;
        }

        var btn = this;
        var reverseBtn = document.getElementById('btn-bulk-reverse');
        var selectAll = document.getElementById('select-all-orders');
        btn.disabled = true;
        if (reverseBtn) reverseBtn.disabled = true;
        if (selectAll) selectAll.disabled = true;
        document.querySelectorAll('.order-select-check').forEach(function (el) { el.disabled = true; });

        var pushed = 0, failed = 0, skipped = 0, done = 0;
        updateBulkStatus(ids.length, 0, 0, 0, 0, true);

        for (var i = 0; i < ids.length; i++) {
            var id = ids[i];
            setRowPushing(id);
            updateBulkStatus(ids.length, done, pushed, failed, skipped, true);
            try {
                var res = await pushOneOrder(id);
                var msg = (res.data && res.data.message) ? String(res.data.message) : '';
                if (res.data && res.data.success) {
                    if (msg.toLowerCase().indexOf('already') !== -1) {
                        skipped++;
                        setRowPushed(id, res.data.shopify_order_id || '');
                    } else {
                        pushed++;
                        setRowPushed(id, res.data.shopify_order_id || '');
                    }
                } else {
                    failed++;
                    setRowFailed(id, msg || 'Shopify import failed.');
                }
            } catch (e) {
                failed++;
                setRowFailed(id, 'Request failed.');
            }
            done++;
            updateBulkStatus(ids.length, done, pushed, failed, skipped, true);
        }

        updateBulkStatus(ids.length, done, pushed, failed, skipped, false);
        if (selectAll) selectAll.disabled = false;
        document.querySelectorAll('.order-select-check').forEach(function (el) { el.disabled = false; });
        refreshBulkUi();
    });

    async function reverseOrders(ids) {
        var response = await fetch('{{ route('marketplace.orders.reverse-push', 'temu') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ids: ids }),
        });
        var data = await response.json().catch(function () { return {}; });
        return { ok: response.ok, data: data };
    }

    async function runReverse(ids) {
        if (!ids.length) return;
        if (!confirm(
            'Reverse push for ' + ids.length + ' selected SKU row(s)?\n\n' +
            'This unlinks only the selected SKU(s) locally so they can be re-pushed.\n' +
            'Shopify orders are NOT deleted.'
        )) {
            return;
        }

        var statusEl = document.getElementById('bulk-push-status');
        if (statusEl) {
            statusEl.style.display = '';
            statusEl.innerHTML = '<span class="badge bg-warning text-dark">Reversing…</span>';
        }

        ids.forEach(function (id) {
            setShopifyCell(id, '<span class="badge bg-warning text-dark">Reversing…</span>');
        });

        try {
            var res = await reverseOrders(ids);
            var results = (res.data && res.data.results) ? res.data.results : [];
            results.forEach(function (row) {
                if (!row || !row.id) return;
                if (row.status === 'reversed') {
                    setRowPending(row.id);
                } else {
                    // Keep prior Shopify link visible; only show a brief skip note.
                    var cell = document.querySelector('.shopify-status-cell[data-order-id="' + row.id + '"]');
                    if (cell && cell.innerHTML.indexOf('Reversing') !== -1) {
                        setShopifyCell(row.id,
                            '<span class="badge bg-success">Pushed</span>' +
                            '<small class="d-block text-muted">' + (row.message || 'Skipped') + '</small>'
                        );
                    }
                }
            });
            if (statusEl) {
                statusEl.innerHTML =
                    '<span class="badge bg-secondary me-1">Reverse done</span>' +
                    '<span class="text-success me-1">Unlinked ' + (res.data.reversed || 0) + '</span>' +
                    '<span class="text-muted">Skipped ' + (res.data.skipped || 0) + '</span>';
            }
            if (!(res.data && res.data.success) && results.length === 0) {
                alert((res.data && res.data.message) || 'Reverse failed.');
            }
        } catch (e) {
            alert('Reverse request failed.');
            if (statusEl) statusEl.innerHTML = '<span class="text-danger">Reverse failed</span>';
        }
        refreshBulkUi();
    }

    function onReverseClick(e) {
        e.stopPropagation();
        var id = parseInt(this.getAttribute('data-id'), 10);
        if (!id) return;
        runReverse([id]);
    }

    document.getElementById('btn-bulk-reverse')?.addEventListener('click', function () {
        runReverse(selectedPushedIds());
    });

    document.querySelectorAll('.btn-reverse-order').forEach(function (btn) {
        btn.addEventListener('click', onReverseClick);
    });

    document.getElementById('btn-fetch-orders')?.addEventListener('click', function () {
        var btn = this;
        var selected = document.getElementById('fetch-days')?.value || '0';
        var body = { import: false };
        var confirmMsg = '';

        if (selected.indexOf('from:') === 0) {
            var fromDate = selected.slice(5);
            body.from_date = fromDate;
            confirmMsg = 'Fetch Temu orders from ' + fromDate + ' onward?\n\nThis will NOT auto-push to Shopify (avoids duplicates for orders already entered).';
        } else {
            var days = parseInt(selected, 10);
            body.days = days;
            confirmMsg = days === 0
                ? 'Fetch all Temu orders (up to 2 years)? This may take several minutes.\n\nThis will NOT auto-push to Shopify.'
                : 'Fetch orders from the last ' + days + ' days?\n\nThis will NOT auto-push to Shopify.';
        }

        if (!confirm(confirmMsg)) {
            return;
        }
        btn.disabled = true;
        fetch('{{ route('marketplace.manager.temu.fetch.orders') }}', {
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

    async function onPushClick(e) {
        if (e) e.stopPropagation();
        var id = this.getAttribute('data-id');
        if (!id) return;
        this.disabled = true;
        setRowPushing(id);
        try {
            var res = await pushOneOrder(id);
            if (res.data && res.data.success) {
                setRowPushed(id, res.data.shopify_order_id || '');
                updateBulkStatus(1, 1, 1, 0, 0, false);
            } else {
                setRowFailed(id, (res.data && res.data.message) || 'Push failed');
            }
        } catch (err) {
            setRowFailed(id, 'Request failed.');
        }
        refreshBulkUi();
    }

    document.querySelectorAll('.btn-push-order').forEach(function (btn) {
        btn.addEventListener('click', onPushClick);
    });

    document.querySelectorAll('.btn-delete-ready-order').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            var orderId = this.getAttribute('data-order-id') || id;
            if (!id) return;
            if (!confirm('Delete Temu order ' + orderId + ' from ready-for-import?\n\nThis only removes it from our platform. It does not delete the order on Temu or Shopify.')) {
                return;
            }
            this.disabled = true;
            fetch('{{ route('marketplace.orders.delete-ready', 'temu') }}', {
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
            if (!confirm('Mark Temu order ' + orderId + ' as already imported?\n\nUse this if the order was already entered in Shopify manually. No new Shopify order will be created.')) {
                return;
            }
            var shopifyOrderId = prompt('Optional Shopify order ID (leave blank if entered manually):', '') || '';
            this.disabled = true;
            fetch('{{ route('marketplace.orders.mark-imported', 'temu') }}', {
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
