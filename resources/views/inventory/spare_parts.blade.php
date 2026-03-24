@extends('layouts.vertical', ['title' => 'Spare Parts', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sp-card {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
        }
        .sp-card h3 {
            font-size: 1.75rem;
            margin-bottom: 0;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
        }
        .tree ul {
            list-style: none;
            padding-left: 1.25rem;
            margin-bottom: 0;
        }
        .tree li {
            padding: 0.15rem 0;
        }
        .breadcrumb-sm {
            font-size: 0.875rem;
        }
        #tab-requisitions .card,
        #tab-requisitions .card-body,
        #tab-po .card,
        #tab-po .card-body,
        #req-lines,
        #po-lines {
            overflow: visible !important;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Spare Parts</h4>
                <nav class="breadcrumb-sm text-muted" aria-label="breadcrumb">
                    <span>Inventory Management</span>
                    <span class="mx-1">/</span>
                    <span class="text-body">Spare Parts</span>
                </nav>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-refresh-summary">Refresh summary</button>
        </div>

        <div class="row g-3 mb-4" id="dashboard-cards">
            @php $s = $initialSummary ?? []; @endphp
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Spare Parts</div>
                        <h3 id="card-total-spare">{{ $s['total_spare_parts'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100 border-warning">
                    <div class="card-body">
                        <div class="text-muted small">Low Stock Items</div>
                        <h3 class="text-warning" id="card-low-stock">{{ $s['low_stock_items'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Pending Requisitions</div>
                        <h3 id="card-pending-req">{{ $s['pending_requisitions'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Pending Purchase Orders</div>
                        <h3 id="card-pending-po">{{ $s['pending_purchase_orders'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-requisitions" type="button">Requisitions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-issue" type="button">Issue Parts</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-po" type="button">Purchase Orders</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-low" type="button">Low Stock</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-parts" type="button">Spare Parts &amp; Parents</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-requisitions">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">New requisition</h5>
                        <form id="form-requisition" class="row g-2 mb-4">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select form-select-sm">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Lines (select part from Product Master)</label>
                                <p class="small text-muted mb-1">Each line uses a dropdown of all SKUs from <code>product_master</code> (same catalog as Product Master). Large lists may take a moment to load once.</p>
                                <div id="req-lines"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="btn-add-req-line">Add line</button>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">Save draft</button>
                            </div>
                        </form>
                        <h5 class="card-title">Recent requisitions</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="table-requisitions">
                                <thead><tr><th>ID</th><th>Status</th><th>Priority</th><th>Dept</th><th>Items</th><th>Actions</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-issue">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Issue against approved requisitions</h5>
                        <div class="table-responsive">
                            <table class="table table-sm" id="table-issues">
                                <thead><tr><th>Req</th><th>SKU</th><th>Approved</th><th>Issued</th><th>Remaining</th><th>Stock</th><th>Qty</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-po">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">New spare parts PO</h5>
                        <form id="form-po" class="row g-2">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" id="po-supplier" class="form-select form-select-sm" required></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Expected</label>
                                <input type="date" name="expected_at" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Lines (SKU from Product Master)</label>
                                <p class="small text-muted mb-1">Same <code>product_master</code> SKU dropdown as requisitions.</p>
                                <div id="po-lines"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="btn-add-po-line">Add line</button>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">Create PO</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Purchase orders</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="table-po">
                                <thead><tr><th>PO #</th><th>Supplier</th><th>Status</th><th>Receive</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-low">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label">Parent group</label>
                                <select id="filter-low-parent" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    @foreach ($parentOptions as $p)
                                        <option value="{{ $p->id }}">{{ $p->sku }} @if($p->productCategory?->category_name) — {{ $p->productCategory->category_name }} @endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-secondary" id="btn-reload-low">Apply</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm" id="table-low">
                                <thead><tr><th>SKU</th><th>Parent</th><th>Stock</th><th>Reorder at</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-parts">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Parent group</label>
                                <select id="filter-parts-parent" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    @foreach ($parentOptions as $p)
                                        <option value="{{ $p->id }}">{{ $p->sku }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Spare part type</label>
                                <select id="filter-parts-type" class="form-select form-select-sm">
                                    <option value="spare">Spare parts only</option>
                                    <option value="all">All (linked to parent filter)</option>
                                    <option value="parent">Parents with children</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-secondary" id="btn-reload-parts">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Parts list</h5>
                                <div class="table-responsive" style="max-height:420px;overflow:auto;">
                                    <table class="table table-sm table-hover" id="table-parts">
                                        <thead class="sticky-top bg-light"><tr><th>SKU</th><th>Stock</th><th>Parent</th><th>Reorder</th><th>Edit</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Tree (top-level spares)</h5>
                                <div id="tree-wrap" class="tree small border rounded p-2" style="max-height:420px;overflow:auto;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="toast-ok" class="toast align-items-center text-bg-success border-0" role="alert">
                <div class="d-flex"><div class="toast-body" id="toast-ok-body"></div></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

        /** Same-origin relative paths (works with subfolders & avoids APP_URL vs browser host mismatch) */
        const SP = {
            summary: @json(route('inventory.spare-parts.api.summary', [], false)),
            searchParts: @json(route('inventory.spare-parts.api.search-parts', [], false)),
            partSkus: @json(route('inventory.spare-parts.api.part-skus', [], false)),
            parts: @json(route('inventory.spare-parts.api.parts', [], false)),
            lowStock: @json(route('inventory.spare-parts.api.low-stock', [], false)),
            tree: @json(route('inventory.spare-parts.api.tree', [], false)),
            suppliers: @json(route('inventory.spare-parts.api.suppliers', [], false)),
            reqIndex: @json(route('inventory.spare-parts.api.requisitions.index', [], false)),
            reqStore: @json(route('inventory.spare-parts.api.requisitions.store', [], false)),
            issuesPending: @json(route('inventory.spare-parts.api.issues.pending', [], false)),
            issueStore: @json(route('inventory.spare-parts.api.issues.store', [], false)),
            poIndex: @json(route('inventory.spare-parts.api.purchase-orders.index', [], false)),
            poStore: @json(route('inventory.spare-parts.api.purchase-orders.store', [], false)),
            partUpdate0: @json(route('inventory.spare-parts.api.parts.update', ['id' => 0], false)),
        };
        const reqActionUrl = (id, action) => `${SP.reqIndex}/${id}/${action}`;
        const poActionUrl = (id, action) => `${SP.poIndex}/${id}/${action}`;
        const partUpdateUrl = (id) => SP.partUpdate0.replace(/\/0$/, '/' + id);

        function toast(msg) {
            const el = document.getElementById('toast-ok');
            document.getElementById('toast-ok-body').textContent = msg;
            new bootstrap.Toast(el, {delay: 3500}).show();
        }

        let __partSkusPromise = null;
        let __partSkusData = null;

        function ensurePartSkusLoaded() {
            if (__partSkusData !== null) {
                return Promise.resolve(__partSkusData);
            }
            if (__partSkusPromise) {
                return __partSkusPromise;
            }
            __partSkusPromise = fetchJson(SP.partSkus + '?limit=100000').then((r) => {
                __partSkusData = r.data || [];
                return __partSkusData;
            }).catch((e) => {
                __partSkusPromise = null;
                throw e;
            });
            return __partSkusPromise;
        }

        function fillPartSelect(sel, data) {
            if (!sel) return;
            sel.innerHTML = '';
            const o0 = document.createElement('option');
            o0.value = '';
            o0.textContent = '— Select SKU —';
            sel.appendChild(o0);
            for (let i = 0; i < data.length; i++) {
                const p = data[i];
                const o = document.createElement('option');
                o.value = String(p.id);
                o.textContent = p.sku || ('#' + p.id);
                sel.appendChild(o);
            }
        }

        function resolvePartFromRow(row) {
            const sel = row.querySelector('.part-select');
            if (!sel || !sel.value) {
                return { ok: false, reason: 'empty' };
            }
            return { ok: true, partId: parseInt(sel.value, 10) };
        }

        async function fetchJson(url, options = {}) {
            const headers = {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            };
            if (options.body && !(options.body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
            }
            const res = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                ...options,
                headers,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data.message || data.error || (data.errors && JSON.stringify(data.errors)) || res.statusText;
                throw new Error(typeof msg === 'string' ? msg : 'Request failed');
            }
            return data;
        }

        async function refreshSummary() {
            const s = await fetchJson(SP.summary);
            document.getElementById('card-total-spare').textContent = s.total_spare_parts;
            document.getElementById('card-low-stock').textContent = s.low_stock_items;
            document.getElementById('card-pending-req').textContent = s.pending_requisitions;
            document.getElementById('card-pending-po').textContent = s.pending_purchase_orders;
        }

        document.getElementById('btn-refresh-summary').addEventListener('click', () => refreshSummary().catch(e => alert(e.message)));

        function addReqLine() {
            const wrap = document.createElement('div');
            wrap.className = 'row g-1 mb-1 align-items-center';
            wrap.innerHTML = `
                <div class="col-md-8 col-7">
                    <select class="form-select form-select-sm part-select" aria-label="Product SKU">
                        <option value="">Loading SKUs…</option>
                    </select>
                </div>
                <div class="col-md-2 col-2"><input type="number" min="1" class="form-control form-control-sm qty-inp" value="1"></div>
                <div class="col-md-1 col-1"><button type="button" class="btn btn-sm btn-link text-danger rm" title="Remove line">✕</button></div>`;
            wrap.querySelector('.rm').onclick = () => wrap.remove();
            document.getElementById('req-lines').appendChild(wrap);
            const sel = wrap.querySelector('.part-select');
            ensurePartSkusLoaded()
                .then((data) => fillPartSelect(sel, data))
                .catch((err) => {
                    sel.innerHTML = '';
                    const o = document.createElement('option');
                    o.value = '';
                    o.textContent = 'Failed to load SKUs';
                    sel.appendChild(o);
                    alert(err.message || 'Could not load product_master SKUs');
                });
        }
        document.getElementById('btn-add-req-line').addEventListener('click', addReqLine);
        addReqLine();

        document.getElementById('form-requisition').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const items = [];
            for (const row of document.querySelectorAll('#req-lines .row')) {
                const qty = parseInt(row.querySelector('.qty-inp').value, 10);
                if (!(qty > 0)) {
                    continue;
                }
                const resolved = resolvePartFromRow(row);
                if (!resolved.ok) {
                    alert('Each line with a quantity needs a SKU chosen from the dropdown.');
                    return;
                }
                items.push({ part_id: resolved.partId, quantity_requested: qty });
            }
            if (!items.length) {
                alert('Add at least one line: choose a SKU from the dropdown and enter quantity.');
                return;
            }
            const body = {
                department: fd.get('department') || null,
                priority: fd.get('priority'),
                notes: fd.get('notes') || null,
                items
            };
            try {
                await fetchJson(SP.reqStore, {
                    method: 'POST',
                    body: JSON.stringify(body)
                });
                toast('Requisition saved');
                loadRequisitions();
                refreshSummary();
            } catch (err) { alert(err.message); }
        });

        async function loadRequisitions() {
            const r = await fetchJson(SP.reqIndex);
            const tb = document.querySelector('#table-requisitions tbody');
            tb.innerHTML = '';
            r.data.forEach(row => {
                const tr = document.createElement('tr');
                const items = (row.items || []).map(i => i.part?.sku || i.part_id).join(', ');
                tr.innerHTML = `<td>${row.id}</td><td>${row.status}</td><td>${row.priority}</td><td>${row.department || ''}</td><td>${items}</td><td class="text-nowrap"></td>`;
                const td = tr.querySelector('td:last-child');
                if (row.status === 'draft') {
                    td.innerHTML += `<button class="btn btn-sm btn-outline-secondary me-1" data-act="submit" data-id="${row.id}">Submit</button>`;
                }
                if (row.status === 'submitted' || row.status === 'draft') {
                    td.innerHTML += `<button class="btn btn-sm btn-outline-success me-1" data-act="approve" data-id="${row.id}">Approve</button>`;
                }
                td.innerHTML += `<button class="btn btn-sm btn-outline-dark" data-act="close" data-id="${row.id}">Close</button>`;
                tb.appendChild(tr);
            });
            tb.querySelectorAll('button[data-act]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');
                    const act = btn.getAttribute('data-act');
                    try {
                        if (act === 'submit') await fetchJson(reqActionUrl(id, 'submit'), {method: 'POST', body: JSON.stringify({})});
                        if (act === 'approve') await fetchJson(reqActionUrl(id, 'approve'), {method: 'POST', body: JSON.stringify({})});
                        if (act === 'close') await fetchJson(reqActionUrl(id, 'close'), {method: 'POST', body: JSON.stringify({})});
                        toast('Updated');
                        loadRequisitions();
                        loadIssues();
                        refreshSummary();
                    } catch (err) { alert(err.message); }
                });
            });
        }

        async function loadIssues() {
            const r = await fetchJson(SP.issuesPending);
            const tb = document.querySelector('#table-issues tbody');
            tb.innerHTML = '';
            r.data.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.requisition_id}</td>
                    <td>${row.sku}</td>
                    <td>${row.quantity_approved}</td>
                    <td>${row.quantity_issued}</td>
                    <td>${row.remaining}</td>
                    <td>${row.stock_available}</td>
                    <td><input type="number" class="form-control form-control-sm issue-qty" style="width:70px" min="1" max="${row.remaining}" value="${Math.min(1, row.remaining)}"></td>
                    <td><button class="btn btn-sm btn-primary btn-issue" data-item="${row.item_id}">Issue</button></td>`;
                tb.appendChild(tr);
            });
            tb.querySelectorAll('.btn-issue').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const tr = btn.closest('tr');
                    const qty = parseInt(tr.querySelector('.issue-qty').value, 10);
                    try {
                        await fetchJson(SP.issueStore, {
                            method: 'POST',
                            body: JSON.stringify({requisition_item_id: parseInt(btn.getAttribute('data-item'), 10), quantity: qty})
                        });
                        toast('Issued');
                        loadIssues();
                        refreshSummary();
                    } catch (err) { alert(err.message); }
                });
            });
        }

        async function loadSuppliers() {
            const r = await fetchJson(SP.suppliers);
            const sel = document.getElementById('po-supplier');
            sel.innerHTML = '<option value="">Select…</option>';
            r.data.forEach(s => {
                const o = document.createElement('option');
                o.value = s.id;
                o.textContent = (s.name || s.company || 'Supplier') + ' (#' + s.id + ')';
                sel.appendChild(o);
            });
        }

        function addPoLine() {
            const wrap = document.createElement('div');
            wrap.className = 'row g-1 mb-1 align-items-center';
            wrap.innerHTML = `
                <div class="col-md-6 col-6">
                    <select class="form-select form-select-sm part-select" aria-label="Product SKU">
                        <option value="">Loading SKUs…</option>
                    </select>
                </div>
                <div class="col-md-2 col-2"><input type="number" min="1" class="form-control form-control-sm qty-ord" value="1"></div>
                <div class="col-md-2 col-2"><input type="number" step="0.01" class="form-control form-control-sm unit-cost" placeholder="Cost"></div>
                <div class="col-md-1 col-1"><button type="button" class="btn btn-sm btn-link text-danger rm" title="Remove line">✕</button></div>`;
            wrap.querySelector('.rm').onclick = () => wrap.remove();
            document.getElementById('po-lines').appendChild(wrap);
            const sel = wrap.querySelector('.part-select');
            ensurePartSkusLoaded()
                .then((data) => fillPartSelect(sel, data))
                .catch((err) => {
                    sel.innerHTML = '';
                    const o = document.createElement('option');
                    o.value = '';
                    o.textContent = 'Failed to load SKUs';
                    sel.appendChild(o);
                    alert(err.message || 'Could not load product_master SKUs');
                });
        }
        document.getElementById('btn-add-po-line').addEventListener('click', addPoLine);
        addPoLine();
        ensurePartSkusLoaded().catch(() => {});

        document.getElementById('form-po').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const items = [];
            for (const row of document.querySelectorAll('#po-lines .row')) {
                const qty = parseInt(row.querySelector('.qty-ord').value, 10);
                if (!(qty > 0)) {
                    continue;
                }
                const resolved = resolvePartFromRow(row);
                if (!resolved.ok) {
                    alert('Each PO line with a quantity needs a SKU from the dropdown.');
                    return;
                }
                const cost = row.querySelector('.unit-cost').value;
                items.push({
                    part_id: resolved.partId,
                    qty_ordered: qty,
                    unit_cost: cost ? parseFloat(cost) : null,
                });
            }
            if (!items.length) {
                alert('Add at least one line: choose a SKU and quantity.');
                return;
            }
            const body = {
                supplier_id: parseInt(fd.get('supplier_id'), 10),
                expected_at: fd.get('expected_at') || null,
                notes: fd.get('notes') || null,
                items
            };
            try {
                await fetchJson(SP.poStore, {
                    method: 'POST',
                    body: JSON.stringify(body)
                });
                toast('PO created');
                loadPo();
                refreshSummary();
            } catch (err) { alert(err.message); }
        });

        async function loadPo() {
            const r = await fetchJson(SP.poIndex);
            const tb = document.querySelector('#table-po tbody');
            tb.innerHTML = '';
            r.data.forEach(po => {
                const tr = document.createElement('tr');
                const sup = po.supplier ? (po.supplier.name || po.supplier.company) : '';
                let receiveCell = '';
                if (po.status === 'draft') {
                    receiveCell = `<button class="btn btn-sm btn-outline-secondary btn-send-po" data-id="${po.id}">Mark sent</button>`;
                } else if (po.status === 'sent' || po.status === 'partially_received') {
                    receiveCell = (po.items || []).map(it => {
                        const left = (it.qty_ordered || 0) - (it.qty_received || 0);
                        if (left <= 0) return '';
                        return `<div class="mb-1">${it.part?.sku || it.part_id}: left ${left}
                            <input type="number" class="form-control form-control-sm d-inline-block recv-qty" style="width:65px" min="1" max="${left}" value="${left}">
                            <button type="button" class="btn btn-sm btn-success btn-recv" data-po="${po.id}" data-item="${it.id}">Recv</button></div>`;
                    }).join('') || '—';
                } else {
                    receiveCell = '—';
                }
                tr.innerHTML = `<td>${po.po_number}</td><td>${sup}</td><td>${po.status}</td><td>${receiveCell}</td>`;
                tb.appendChild(tr);
            });
            tb.querySelectorAll('.btn-send-po').forEach(btn => {
                btn.addEventListener('click', async () => {
                    try {
                        await fetchJson(poActionUrl(btn.getAttribute('data-id'), 'send'), {method: 'POST', body: JSON.stringify({})});
                        toast('PO sent');
                        loadPo();
                        refreshSummary();
                    } catch (e) { alert(e.message); }
                });
            });
            tb.querySelectorAll('.btn-recv').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const div = btn.closest('div');
                    const qty = parseInt(div.querySelector('.recv-qty').value, 10);
                    try {
                        await fetchJson(`/inventory/spare-parts/api/purchase-orders/${btn.getAttribute('data-po')}/receive`, {
                            method: 'POST',
                            body: JSON.stringify({item_id: parseInt(btn.getAttribute('data-item'), 10), quantity: qty})
                        });
                        toast('Received');
                        loadPo();
                        refreshSummary();
                    } catch (e) { alert(e.message); }
                });
            });
        }

        function partsQuery() {
            const pid = document.getElementById('filter-parts-parent').value;
            const type = document.getElementById('filter-parts-type').value;
            let url = SP.parts + '?type=' + encodeURIComponent(type);
            if (pid) url += '&parent_id=' + encodeURIComponent(pid);
            return url;
        }

        async function loadParts() {
            const r = await fetchJson(partsQuery());
            const tb = document.querySelector('#table-parts tbody');
            tb.innerHTML = '';
            r.data.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${p.sku}</td><td>${p.stock}</td><td>${p.parent_sku || '—'}</td><td>${p.reorder_level ?? '—'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary btn-edit-part" data-id="${p.id}">Flags</button>
                    </td>`;
                tb.appendChild(tr);
            });
            tb.querySelectorAll('.btn-edit-part').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');
                    const isSpare = confirm('Mark this SKU as spare part?');
                    const parentSel = prompt('Parent product ID (optional, blank to skip):', '');
                    const body = {is_spare_part: isSpare};
                    if (parentSel && parentSel.trim() !== '') body.parent_id = parseInt(parentSel.trim(), 10);
                    try {
                        await fetchJson(partUpdateUrl(id), {method: 'PATCH', body: JSON.stringify(body)});
                        toast('Updated');
                        loadParts();
                        refreshSummary();
                    } catch (e) { alert(e.message); }
                });
            });
        }

        async function loadLow() {
            const pid = document.getElementById('filter-low-parent').value;
            let url = SP.lowStock;
            if (pid) url += '?parent_id=' + encodeURIComponent(pid);
            const r = await fetchJson(url);
            const tb = document.querySelector('#table-low tbody');
            tb.innerHTML = '';
            r.data.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${p.sku}</td><td>${p.parent_sku || '—'}</td><td>${p.stock}</td><td>${p.reorder_level}</td>`;
                tb.appendChild(tr);
            });
        }

        function renderTree(nodes) {
            const ul = document.createElement('ul');
            nodes.forEach(n => {
                const li = document.createElement('li');
                li.textContent = n.sku;
                if (n.children && n.children.length) li.appendChild(renderTree(n.children));
                ul.appendChild(li);
            });
            return ul;
        }

        async function loadTree() {
            const r = await fetchJson(SP.tree);
            const wrap = document.getElementById('tree-wrap');
            wrap.innerHTML = '';
            if (r.data && r.data.length) wrap.appendChild(renderTree(r.data));
            else wrap.textContent = 'No top-level spare parts (set parent / flags on the Parts tab).';
        }

        document.getElementById('btn-reload-parts').addEventListener('click', () => { loadParts(); loadTree(); });
        document.getElementById('btn-reload-low').addEventListener('click', loadLow);

        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const id = e.target.getAttribute('data-bs-target');
                if (id === '#tab-requisitions') loadRequisitions();
                if (id === '#tab-issue') loadIssues();
                if (id === '#tab-po') { loadSuppliers(); loadPo(); }
                if (id === '#tab-low') loadLow();
                if (id === '#tab-parts') { loadParts(); loadTree(); }
            });
        });

        loadRequisitions();
    </script>
@endsection
