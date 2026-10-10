@extends('layouts.vertical', ['title' => 'INV Management 5Core', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    #inv5c-table.tabulator { width: 100%; }
    #inv5c-table .tabulator-header {
        background: linear-gradient(180deg, #eef3fb 0%, #e3ebf8 100%);
        border-bottom: 1px solid #c5d4ea;
    }
    #inv5c-table .tabulator-header .tabulator-col { background: transparent; border-right: 1px solid #d7e0ef; }
    #inv5c-table .tabulator-header .tabulator-col-content .tabulator-col-title {
        color: #1a3d7c;
        font-weight: 700;
        font-size: 0.82rem;
        text-align: center;
    }
    #inv5c-table .tabulator-row .tabulator-cell { padding: 6px 8px; }
    #inv5c-table .tabulator-cell.inv5c-app,
    #inv5c-table .tabulator-col.inv5c-app { background: #f3f7ff; }
    #inv5c-table .tabulator-row.inv5c-parent { background: #e8eef8; font-weight: 700; }
    .inv5c-img {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        display: block;
    }
    .inv5c-app-btn {
        border: 0;
        background: transparent;
        color: #1a3d7c;
        font-weight: 700;
        padding: 0;
    }
    .inv5c-app-btn:hover { text-decoration: underline; }
    .inv5c-note { max-width: 920px; }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'INV Management 5Core', 'sub_title' => 'Inventory'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                    <div class="inv5c-note">
                        <h4 class="mb-1">INV Management 5Core</h4>
                        <p class="text-muted mb-0 small">
                            INV, L30, and DIL% come from Shopify. INV APP is this page’s own inventory.
                            Seed opening once from Shopify, matched by SKU. After that, Shopify is not imported again.
                            A marketplace order commits quantity first. On hand drops when that order is fulfilled. Each step is stored in the SKU history.
                            L30 APP is marketplace units sold over the last 30 days.
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="inv5cSeed">Seed opening</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="inv5cSales">Record marketplace orders</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="inv5cRefresh">
                            <i class="fas fa-rotate me-1"></i>Refresh
                        </button>
                        <span class="badge bg-primary fs-6 px-3 py-2">Rows: <span id="inv5cCount">0</span></span>
                    </div>
                </div>
                <div id="inv5cAlert" class="alert alert-info py-2 small d-none" role="status"></div>
                <div class="d-flex flex-wrap gap-2 mb-3 small text-muted" id="inv5cMeta">
                    <span>Seeded SKUs: <strong id="inv5cSeeded">0</strong></span>
                    <span>Waiting for opening: <strong id="inv5cUnseeded">0</strong></span>
                </div>
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-4 col-lg-3">
                        <label for="inv5cParentSearch" class="form-label fw-semibold mb-1">Search Parent</label>
                        <input type="text" id="inv5cParentSearch" class="form-control" placeholder="Type Parent…">
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="inv5cSkuSearch" class="form-label fw-semibold mb-1">Search SKU</label>
                        <input type="text" id="inv5cSkuSearch" class="form-control" placeholder="Type SKU…">
                    </div>
                </div>
                <div id="inv5c-table"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="inv5cHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width: 96vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjustment history <span id="inv5cHistorySku" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="inv5cHistorySummary"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th class="text-end">Change</th>
                                <th class="text-end">Before</th>
                                <th class="text-end">After</th>
                                <th>Reference</th>
                                <th>Channel</th>
                                <th>Detail</th>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Created by</th>
                                <th class="text-end">Committed</th>
                                <th class="text-end">Available</th>
                                <th class="text-end">On hand</th>
                            </tr>
                        </thead>
                        <tbody id="inv5cHistoryBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="inv5cAdjustModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="inv5cAdjustForm">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust <span id="inv5cAdjustSku" class="text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Current INV APP: <strong id="inv5cAdjustCurrent">—</strong></p>
                    <div class="mb-2">
                        <label class="form-label" for="inv5cAdjustType">Type</label>
                        <select id="inv5cAdjustType" class="form-select" required>
                            <option value="adjustment">Adjustment (set on-hand)</option>
                            <option value="incoming">Incoming (add)</option>
                            <option value="return">Return (add)</option>
                            <option value="write_off">Write-off (subtract)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="inv5cAdjustQty">Quantity</label>
                        <input type="number" id="inv5cAdjustQty" class="form-control" min="0" step="1" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="inv5cAdjustDetail">Detail</label>
                        <textarea id="inv5cAdjustDetail" class="form-control" rows="3" maxlength="2000" required placeholder="Why this quantity changed"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="inv5cAdjustSave">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dataUrl = @json(route('inv.management.5core.data'));
    const seedUrl = @json(route('inv.management.5core.seed'));
    const salesUrl = @json(route('inv.management.5core.sales'));
    const adjustUrl = @json(route('inv.management.5core.adjust'));
    const historyUrl = @json(route('inv.management.5core.history'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let table = null;
    let allRows = [];
    let adjustSku = '';

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function fmtNum(v) {
        if (v === null || v === undefined || v === '') return '—';
        const n = Number(v);
        if (!Number.isFinite(n)) return '—';
        return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
    }

    function stateCell(delta, after) {
        const qty = fmtNum(after);
        const change = Number(delta);
        if (!Number.isFinite(change) || change === 0) {
            return qty;
        }
        const sign = change > 0 ? '+' : '';
        const color = change > 0 ? '#1a7f37' : '#d72c0d';
        return `<span style="color:${color};font-weight:600;">(${sign}${fmtNum(change)})</span> ${qty}`;
    }

    function dilHtml(inv, l30) {
        const inventory = Number(inv);
        const sold = Number(l30);
        if (!Number.isFinite(inventory) || inventory <= 0 || !Number.isFinite(sold)) {
            return '<span class="text-muted">—</span>';
        }
        const pct = Math.round((sold / inventory) * 100);
        const color = pct < 25 ? '#dc3545' : (pct < 50 ? '#1b5e20' : '#ad1457');
        return `<span style="color:${color};font-weight:700;">${pct}%</span>`;
    }

    function showAlert(kind, message) {
        const box = document.getElementById('inv5cAlert');
        box.className = 'alert alert-' + kind + ' py-2 small';
        box.textContent = message;
    }

    function rowPasses(data) {
        const skuQ = (document.getElementById('inv5cSkuSearch').value || '').trim().toLowerCase();
        const parentQ = (document.getElementById('inv5cParentSearch').value || '').trim().toLowerCase();
        if (skuQ && !String(data.sku || '').toLowerCase().includes(skuQ)) return false;
        if (parentQ && !String(data.parent || '').toLowerCase().includes(parentQ)) return false;
        return true;
    }

    function updateCount() {
        document.getElementById('inv5cCount').textContent = allRows.filter(rowPasses).length.toLocaleString();
    }

    async function postJson(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body || {}),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || 'Request failed');
        }
        return data;
    }

    function openHistory(sku) {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('inv5cHistoryModal'));
        document.getElementById('inv5cHistorySku').textContent = sku;
        document.getElementById('inv5cHistorySummary').textContent = 'Loading…';
        document.getElementById('inv5cHistoryBody').innerHTML = '';
        modal.show();
        fetch(historyUrl + '?sku=' + encodeURIComponent(sku), { headers: { 'Accept': 'application/json' } })
            .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) throw new Error(data.message || 'Unable to load history');
                const seeded = data.seeded
                    ? ('Opening ' + fmtNum(data.opening_qty) + ' on ' + (data.opening_seeded_at || '—') + '. INV APP ' + fmtNum(data.inv_app) + '.')
                    : 'Opening inventory has not been seeded for this SKU.';
                document.getElementById('inv5cHistorySummary').textContent = seeded;
                const rows = Array.isArray(data.rows) ? data.rows : [];
                document.getElementById('inv5cHistoryBody').innerHTML = rows.length
                    ? rows.map((row) => `<tr>
                        <td class="text-nowrap">${escapeHtml(row.occurred_at)}</td>
                        <td>${escapeHtml(row.txn_type)}</td>
                        <td class="text-end">${fmtNum(row.qty_delta)}</td>
                        <td class="text-end">${fmtNum(row.qty_before)}</td>
                        <td class="text-end">${fmtNum(row.qty_after)}</td>
                        <td>${escapeHtml(row.reference) || '—'}</td>
                        <td>${escapeHtml(row.channel) || '—'}</td>
                        <td>${escapeHtml(row.detail)}</td>
                        <td>${escapeHtml(row.user_name) || '—'}</td>
                        <td>${escapeHtml(row.activity)}</td>
                        <td>${escapeHtml(row.created_by) || '—'}</td>
                        <td class="text-end text-nowrap">${stateCell(row.committed_delta, row.committed_after)}</td>
                        <td class="text-end text-nowrap">${stateCell(row.available_delta, row.available_after)}</td>
                        <td class="text-end text-nowrap">${stateCell(row.on_hand_delta, row.on_hand_after)}</td>
                    </tr>`).join('')
                    : '<tr><td colspan="14" class="text-muted">No movements yet.</td></tr>';
            })
            .catch((err) => {
                document.getElementById('inv5cHistorySummary').textContent = err.message;
            });
    }

    function openAdjust(data) {
        if (!data || data.is_parent) return;
        if (!data.seeded) {
            showAlert('warning', 'Seed opening inventory before adjusting ' + data.sku + '.');
            return;
        }
        adjustSku = data.sku;
        document.getElementById('inv5cAdjustSku').textContent = data.sku;
        document.getElementById('inv5cAdjustCurrent').textContent = fmtNum(data.inv_app);
        document.getElementById('inv5cAdjustQty').value = '';
        document.getElementById('inv5cAdjustDetail').value = '';
        document.getElementById('inv5cAdjustType').value = 'adjustment';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('inv5cAdjustModal')).show();
    }

    table = new Tabulator('#inv5c-table', {
        ajaxURL: dataUrl,
        ajaxResponse: function (url, params, response) {
            allRows = Array.isArray(response?.data) ? response.data : [];
            const meta = response?.meta || {};
            document.getElementById('inv5cSeeded').textContent = Number(meta.seeded_count || 0).toLocaleString();
            document.getElementById('inv5cUnseeded').textContent = Number(meta.unseeded_count || 0).toLocaleString();
            updateCount();
            if (response && response.status && response.status !== 200 && response.message) {
                showAlert('danger', response.message);
            }
            return allRows;
        },
        layout: 'fitColumns',
        height: '72vh',
        pagination: true,
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 200],
        selectable: true,
        placeholder: 'No products found',
        initialFilter: rowPasses,
        rowFormatter: function (row) {
            if (row.getData().is_parent) row.getElement().classList.add('inv5c-parent');
        },
        columns: [
            {
                title: 'Select',
                formatter: 'rowSelection',
                titleFormatter: 'rowSelection',
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                width: 70,
            },
            {
                title: 'Image',
                field: 'image',
                width: 80,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const src = cell.getValue();
                    if (!src) return '<span class="text-muted">—</span>';
                    return `<img src="${escapeHtml(src)}" class="inv5c-img" alt="" loading="lazy">`;
                },
            },
            { title: 'Parent', field: 'parent', minWidth: 140, widthGrow: 1 },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 200,
                widthGrow: 2,
                formatter: function (cell) {
                    const sku = cell.getValue();
                    return `<span class="fw-semibold">${escapeHtml(sku)}</span>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 inv5c-history" data-sku="${escapeHtml(sku)}" title="Adjustment history">History</button>`;
                },
            },
            { title: 'INV', field: 'inv', width: 90, hozAlign: 'right', sorter: 'number', formatter: (cell) => fmtNum(cell.getValue()) },
            { title: 'L30', field: 'l30', width: 90, hozAlign: 'right', sorter: 'number', formatter: (cell) => fmtNum(cell.getValue()) },
            {
                title: 'DIL%',
                field: 'dil',
                width: 90,
                hozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const data = cell.getRow().getData();
                    return dilHtml(data.inv, data.l30);
                },
            },
            {
                title: 'INV APP',
                field: 'inv_app',
                width: 110,
                hozAlign: 'right',
                sorter: 'number',
                cssClass: 'inv5c-app',
                formatter: function (cell) {
                    const data = cell.getRow().getData();
                    if (data.is_parent) return fmtNum(cell.getValue());
                    if (!data.seeded) return '<span class="text-muted">—</span>';
                    return `<button type="button" class="inv5c-app-btn" title="Adjust inventory">${fmtNum(cell.getValue())}</button>`;
                },
                cellClick: function (e, cell) { openAdjust(cell.getRow().getData()); },
            },
            {
                title: 'L30 APP',
                field: 'l30_app',
                width: 110,
                hozAlign: 'right',
                sorter: 'number',
                cssClass: 'inv5c-app',
                formatter: (cell) => fmtNum(cell.getValue()),
            },
        ],
    });

    document.getElementById('inv5c-table').addEventListener('click', function (event) {
        const button = event.target.closest('.inv5c-history');
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();
        openHistory(button.getAttribute('data-sku') || '');
    });

    document.getElementById('inv5cParentSearch').addEventListener('input', function () {
        table.setFilter(rowPasses);
        updateCount();
    });
    document.getElementById('inv5cSkuSearch').addEventListener('input', function () {
        table.setFilter(rowPasses);
        updateCount();
    });
    document.getElementById('inv5cRefresh').addEventListener('click', function () { table.setData(); });

    document.getElementById('inv5cSeed').addEventListener('click', async function () {
        if (!window.confirm('Copy Shopify INV into INV APP once for every SKU that is not seeded yet? Seeded SKUs stay as they are. Shopify is not imported again after that.')) {
            return;
        }
        const button = this;
        button.disabled = true;
        try {
            const data = await postJson(seedUrl, {});
            showAlert('success', data.message);
            table.setData();
        } catch (err) {
            showAlert('danger', err.message);
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('inv5cSales').addEventListener('click', async function () {
        const button = this;
        button.disabled = true;
        try {
            const data = await postJson(salesUrl, {});
            showAlert('success', data.message);
            table.setData();
        } catch (err) {
            showAlert('danger', err.message);
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('inv5cAdjustType').addEventListener('change', function () {
        document.getElementById('inv5cAdjustQty').placeholder = this.value === 'adjustment' ? 'New on-hand quantity' : 'Quantity to add or remove';
    });

    document.getElementById('inv5cAdjustForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const type = document.getElementById('inv5cAdjustType').value;
        const mode = type === 'adjustment' ? 'set' : (type === 'write_off' ? 'subtract' : 'add');
        const button = document.getElementById('inv5cAdjustSave');
        button.disabled = true;
        try {
            const data = await postJson(adjustUrl, {
                sku: adjustSku,
                txn_type: type,
                mode: mode,
                qty: Number(document.getElementById('inv5cAdjustQty').value),
                detail: document.getElementById('inv5cAdjustDetail').value,
            });
            showAlert('success', data.message + ' ' + data.sku + ' INV APP is ' + fmtNum(data.inv_app) + '.');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('inv5cAdjustModal')).hide();
            table.setData();
        } catch (err) {
            showAlert('danger', err.message);
        } finally {
            button.disabled = false;
        }
    });
});
</script>
@endsection
