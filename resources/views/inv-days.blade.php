@extends('layouts.vertical', ['title' => 'Inv Days', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    .page-title-box .page-title { display: none; }
    .invdays-toolbar {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: flex-start;
        direction: ltr;
        gap: 8px;
        margin-bottom: 12px;
        overflow-x: auto;
    }
    .invdays-toolbar .form-control,
    .invdays-toolbar .form-select,
    .invdays-toolbar .input-group-text {
        height: 31px;
        padding-top: 2px;
        padding-bottom: 2px;
    }
    .invdays-toolbar .invdays-search { width: 180px; flex: 0 0 180px; }
    .invdays-toolbar .invdays-inv-filter { width: 120px; flex: 0 0 120px; }
    .invdays-toolbar .invdays-band-filter { width: 168px; flex: 0 0 168px; }
    #invdays-table .tabulator-cell.invdays-band--yellow { background: #fde047 !important; }
    #invdays-table .tabulator-cell.invdays-band--red { background: #f87171 !important; color: #fff; }
    #invdays-table.tabulator { width: 100%; }
    #invdays-table .tabulator-header {
        background: linear-gradient(180deg, #eef3fb 0%, #e3ebf8 100%);
        border-bottom: 1px solid #c5d4ea;
    }
    #invdays-table .tabulator-header .tabulator-col {
        background: transparent;
        border-right: 1px solid #d7e0ef;
    }
    #invdays-table .tabulator-header .tabulator-col-content .tabulator-col-title {
        color: #1a3d7c;
        font-weight: 700;
        font-size: 0.9rem;
        text-align: center;
    }
    #invdays-table .tabulator-row .tabulator-cell {
        padding: 6px 8px;
        overflow: visible;
        text-align: center !important;
    }
    #invdays-table .tabulator-tableholder { overflow: auto !important; }
    #invdays-table .invdays-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 38px;
    }
    .invdays-product-img {
        width: 38px;
        height: 38px;
        object-fit: cover;
        border: none;
        outline: none;
        background: transparent;
        display: block;
    }
    .invdays-dil {
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.85rem;
    }
    .invdays-dil--hot { background: #fee2e2; color: #b91c1c; }
    .invdays-dil--warm { background: #fef3c7; color: #b45309; }
    .invdays-dil--ok { background: #dcfce7; color: #166534; }
    .invdays-clearance {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .invdays-clearance-btn {
        border: none;
        border-radius: 999px;
        min-width: 52px;
        padding: 2px 10px;
        font-weight: 700;
        font-size: 0.8rem;
        line-height: 1.4;
        cursor: pointer;
        background: #e5e7eb;
        color: #374151;
    }
    .invdays-clearance-btn--yes {
        background: #16a34a;
        color: #fff;
    }
    .invdays-clearance-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        border: none;
        padding: 0;
        background: #94a3b8;
        cursor: pointer;
        flex: 0 0 auto;
    }
    .invdays-clearance-dot--has { background: #2563eb; }
    .invdays-clearance-dot:hover { transform: scale(1.25); }
    .invdays-nrp-dot {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: none;
        padding: 0;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
        cursor: pointer;
    }
    .invdays-nrp-dot:hover { transform: scale(1.25); }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Inv Days', 'sub_title' => 'Inventory'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="invdays-toolbar">
                    <button type="button" class="btn btn-sm text-white text-nowrap d-none" id="invdaysBulkYes" style="background:#16a34a;">Clearance Yes</button>
                    <button type="button" class="btn btn-sm btn-secondary text-nowrap d-none" id="invdaysBulkNo">Clearance NO</button>
                    <button type="button" class="btn btn-sm text-white text-nowrap d-none" id="invdaysBulkReq" style="background:#22c55e;">NRP REQ</button>
                    <button type="button" class="btn btn-sm text-white text-nowrap d-none" id="invdaysBulkNr" style="background:#dc3545;">NRP NR</button>
                    <button type="button" class="btn btn-sm text-dark text-nowrap d-none" id="invdaysBulkLater" style="background:#facc15;">NRP LATER</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="invdaysRefresh">
                        <i class="fas fa-rotate me-1"></i>Refresh
                    </button>
                    <span class="badge bg-primary px-2 py-1 text-nowrap" title="Rows currently shown">Rows: <span id="invdaysCount">0</span></span>
                    <span class="badge bg-info text-dark px-2 py-1 text-nowrap" title="Sum of INV for rows currently shown">Inv Sum: <span id="invdaysInvSum">0</span></span>
                    <span class="badge bg-success px-2 py-1 text-nowrap" title="Sum of Inv Value for rows currently shown">Inv Value Sum: <span id="invdaysInvValueSum">0</span></span>
                    <span class="badge bg-warning text-dark px-2 py-1 text-nowrap" title="Average Age Days for rows currently shown">Age Days Avg: <span id="invdaysAgeAvg">—</span></span>
                    <span class="badge bg-secondary px-2 py-1 text-nowrap" title="Average Days Exp for rows currently shown">Exp Days Avg: <span id="invdaysExpAvg">—</span></span>
                    <div class="input-group input-group-sm invdays-search">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="invdaysParentSearch" class="form-control" placeholder="Parent" aria-label="Search Parent">
                    </div>
                    <select id="invdaysInvFilter" class="form-select form-select-sm invdays-inv-filter" aria-label="Inv">
                        <option value="all">All Inv</option>
                        <option value="zero">0 Inv</option>
                        <option value="gt">&gt; Inv</option>
                    </select>
                    <select id="invdaysAgeFilter" class="form-select form-select-sm invdays-band-filter" aria-label="Age Days">
                        <option value="all">All Age</option>
                        <option value="yellow">Age Yellow (&gt;120 &lt;180)</option>
                        <option value="red">Age Red (≥180)</option>
                    </select>
                    <select id="invdaysExpFilter" class="form-select form-select-sm invdays-band-filter" aria-label="Exp Days">
                        <option value="all">All Exp</option>
                        <option value="yellow">Exp Yellow (&gt;120 &lt;180)</option>
                        <option value="red">Exp Red (≥180)</option>
                    </select>
                    <div class="input-group input-group-sm invdays-search">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="invdaysSkuSearch" class="form-control" placeholder="SKU" aria-label="Search SKU">
                    </div>
                </div>
                <div id="invdays-table"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="invdaysClearanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clearance history — <span id="invdaysClearanceSku"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="invdaysClearanceBody" class="text-muted">Loading…</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dataUrl = @json(route('inv.days.data'));
    const clearanceUrl = @json(route('inv.days.clearance'));
    const clearanceBulkUrl = @json(route('inv.days.clearance.bulk'));
    const nrpBulkUrl = @json(route('inv.days.nrp.bulk'));
    const selectedSkus = new Set();
    const clearanceHistoryUrl = @json(route('inv.days.clearance.history'));
    let table = null;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function fmtNum(v, decimals) {
        const n = Number(v);
        if (!Number.isFinite(n)) return '—';
        return Number.isInteger(n) ? String(n) : n.toFixed(decimals || 2).replace(/\.?0+$/, '');
    }

    function missingLastSorter(a, b) {
        const an = Number(a);
        const bn = Number(b);
        const aOk = a !== null && a !== undefined && a !== '' && Number.isFinite(an);
        const bOk = b !== null && b !== undefined && b !== '' && Number.isFinite(bn);
        if (!aOk && !bOk) return 0;
        if (!aOk) return -1;
        if (!bOk) return 1;
        return an - bn;
    }

    function daysExpValue(v) {
        const n = Number(v);
        if (v === null || v === undefined || v === '' || !Number.isFinite(n)) return 99999;
        return n;
    }

    function daysExpSorter(a, b) {
        return daysExpValue(a) - daysExpValue(b);
    }

    let allRows = [];

    function rowPasses(data) {
        const skuQ = (document.getElementById('invdaysSkuSearch').value || '').trim().toLowerCase();
        const parentQ = (document.getElementById('invdaysParentSearch').value || '').trim().toLowerCase();
        if (skuQ && !String(data.sku || '').toLowerCase().includes(skuQ)) return false;
        if (parentQ && !String(data.parent || '').toLowerCase().includes(parentQ)) return false;
        const invMode = document.getElementById('invdaysInvFilter').value;
        const inv = Number(data.inv);
        const invN = Number.isFinite(inv) ? inv : 0;
        if (invMode === 'zero' && invN !== 0) return false;
        if (invMode === 'gt' && !(invN > 0)) return false;
        if (!bandMatches(document.getElementById('invdaysAgeFilter').value, data.age_days)) return false;
        if (!bandMatches(document.getElementById('invdaysExpFilter').value, data.days_exp)) return false;
        return true;
    }

    function dayBand(v) {
        const n = Number(v);
        if (!Number.isFinite(n)) return 'none';
        if (n > 120 && n < 180) return 'yellow';
        if (n >= 180) return 'red';
        return 'none';
    }

    function bandMatches(mode, v) {
        if (!mode || mode === 'all') return true;
        return dayBand(v) === mode;
    }

    function paintDayCell(cell) {
        const el = cell.getElement();
        el.classList.remove('invdays-band--yellow', 'invdays-band--red');
        const band = dayBand(cell.getValue());
        if (band === 'yellow' || band === 'red') el.classList.add('invdays-band--' + band);
        const v = cell.getValue();
        return (v === null || v === undefined || v === '') ? '—' : String(Math.round(Number(v)));
    }

    function finiteNum(v) {
        const n = Number(v);
        return Number.isFinite(n) ? n : null;
    }

    function avgOf(rows, field) {
        let sum = 0;
        let n = 0;
        rows.forEach(function (row) {
            const v = finiteNum(row[field]);
            if (v === null) return;
            sum += v;
            n += 1;
        });
        return n ? Math.round(sum / n) : null;
    }

    function visibleRows() {
        return allRows.filter(rowPasses);
    }

    function updateCount(rows) {
        if (!Array.isArray(rows)) rows = visibleRows();
        const invSum = rows.reduce(function (sum, row) {
            return sum + (finiteNum(row.inv) || 0);
        }, 0);
        const invValueSum = rows.reduce(function (sum, row) {
            return sum + (finiteNum(row.inv_value) || 0);
        }, 0);
        const ageAvg = avgOf(rows, 'age_days');
        const expAvg = avgOf(rows, 'days_exp');

        document.getElementById('invdaysCount').textContent = rows.length.toLocaleString();
        document.getElementById('invdaysInvSum').textContent = Math.round(invSum).toLocaleString();
        document.getElementById('invdaysInvValueSum').textContent = Math.round(invValueSum).toLocaleString();
        document.getElementById('invdaysAgeAvg').textContent = ageAvg === null ? '—' : ageAvg.toLocaleString();
        document.getElementById('invdaysExpAvg').textContent = expAvg === null ? '—' : expAvg.toLocaleString();
    }

    function applyFilters() {
        if (!table) return;
        table.setFilter(rowPasses);
        updateCount();
    }

    table = new Tabulator('#invdays-table', {
        ajaxURL: dataUrl,
        ajaxResponse: function (url, params, response) {
            allRows = Array.isArray(response?.data) ? response.data : [];
            updateCount(allRows.filter(rowPasses));
            return allRows;
        },
        layout: 'fitColumns',
        height: '72vh',
        pagination: false,
        placeholder: 'No SKUs found',
        initialSort: [{ column: 'days_exp', dir: 'desc' }],
        initialFilter: rowPasses,
        columns: [
            {
                title: '',
                field: '_select',
                width: 46,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                titleFormatter: function () {
                    return '<input type="checkbox" id="invdaysSelectAll" aria-label="Select all shown rows">';
                },
                formatter: function (cell) {
                    const sku = cell.getRow().getData().sku;
                    const checked = selectedSkus.has(sku) ? ' checked' : '';
                    return `<input type="checkbox" class="invdays-row-check" data-sku="${escapeHtml(sku)}"${checked} aria-label="Select row">`;
                },
                headerClick: function (e) {
                    const box = e.target.closest('#invdaysSelectAll');
                    if (!box) return;
                    shownSkus().forEach(function (sku) {
                        if (box.checked) selectedSkus.add(sku);
                        else selectedSkus.delete(sku);
                    });
                    refreshVisibleChecks();
                },
                cellClick: function (e, cell) {
                    const box = e.target.closest('.invdays-row-check');
                    if (!box) return;
                    const sku = cell.getRow().getData().sku;
                    if (box.checked) selectedSkus.add(sku);
                    else selectedSkus.delete(sku);
                    syncBulkUi();
                },
            },
            {
                title: 'Image',
                field: 'image',
                width: 72,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const src = cell.getValue();
                    if (!src) return '<div class="invdays-cell"><span class="text-muted">—</span></div>';
                    return `<div class="invdays-cell"><img src="${escapeHtml(src)}" class="invdays-product-img" alt="SKU" loading="lazy" onerror="this.outerHTML='<span class=\\'text-muted\\'>—</span>'"></div>`;
                },
            },
            {
                title: 'Parent',
                field: 'parent',
                minWidth: 140,
                widthGrow: 1,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const v = String(cell.getValue() || '').trim();
                    return v ? escapeHtml(v) : '<span class="text-muted">—</span>';
                },
            },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 220,
                widthGrow: 2,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    return `<span class="fw-semibold">${escapeHtml(cell.getValue())}</span>`;
                },
            },
            {
                title: 'INV',
                field: 'inv',
                width: 100,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    return fmtNum(cell.getValue());
                },
            },
            {
                title: 'Inv Value',
                field: 'inv_value',
                width: 120,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
                    const n = Number(v);
                    return Number.isFinite(n) ? Math.round(n).toLocaleString() : '<span class="text-muted">—</span>';
                },
            },
            {
                title: 'OVL30',
                field: 'ovl30',
                width: 100,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    return fmtNum(cell.getValue());
                },
            },
            {
                title: 'DIL',
                field: 'dil',
                width: 110,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const v = cell.getValue();
                    if (v === null || v === undefined) return '<span class="text-muted">—</span>';
                    const n = Number(v);
                    const cls = n > 100 ? (n >= 200 ? 'invdays-dil--hot' : 'invdays-dil--warm') : 'invdays-dil--ok';
                    return `<span class="invdays-dil ${cls}">${n}%</span>`;
                },
            },
            {
                title: 'Age Days',
                field: 'age_days',
                width: 120,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: missingLastSorter,
                formatter: paintDayCell,
            },
            {
                title: 'Days Exp',
                field: 'days_exp',
                width: 120,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: daysExpSorter,
                formatter: function (cell) {
                    const raw = cell.getValue();
                    if (raw === null || raw === undefined || raw === '') {
                        const el = cell.getElement();
                        el.classList.remove('invdays-band--yellow');
                        el.classList.add('invdays-band--red');
                        return '99999';
                    }
                    return paintDayCell(cell);
                },
            },
            {
                title: 'Clearance',
                field: 'clearance',
                width: 140,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const data = cell.getRow().getData();
                    const yes = String(data.clearance || 'NO').toUpperCase() === 'YES';
                    const label = yes ? 'Yes' : 'NO';
                    const btnCls = yes ? 'invdays-clearance-btn invdays-clearance-btn--yes' : 'invdays-clearance-btn';
                    const dotCls = data.clearance_has_history ? 'invdays-clearance-dot invdays-clearance-dot--has' : 'invdays-clearance-dot';
                    return `<span class="invdays-clearance">
                        <button type="button" class="${btnCls}" data-sku="${escapeHtml(data.sku)}">${label}</button>
                        <button type="button" class="${dotCls}" data-sku="${escapeHtml(data.sku)}" title="Clearance history" aria-label="Clearance history"></button>
                    </span>`;
                },
                cellClick: function (e, cell) {
                    const dot = e.target.closest('.invdays-clearance-dot');
                    const btn = e.target.closest('.invdays-clearance-btn');
                    const sku = cell.getRow().getData().sku;
                    if (dot) {
                        openClearanceHistory(sku);
                        return;
                    }
                    if (btn) toggleClearance(cell, btn);
                },
            },
            {
                title: 'NRP',
                field: 'nr',
                width: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'string',
                formatter: function (cell) {
                    let value = String(cell.getValue() || '').trim().toUpperCase();
                    if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') value = 'REQ';
                    let color = '#22c55e';
                    let tip = 'REQ';
                    if (value === 'NR') {
                        color = '#dc3545';
                        tip = '2BDC';
                    } else if (value === 'LATER') {
                        color = '#facc15';
                        tip = 'LATER';
                    }
                    return `<button type="button" class="invdays-nrp-dot" style="background-color:${color};" title="${tip}" aria-label="${tip}"></button>`;
                },
                cellClick: function (e, cell) {
                    if (!e.target.closest('.invdays-nrp-dot')) return;
                    const data = cell.getRow().getData();
                    let value = String(data.nr || '').trim().toUpperCase();
                    if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') value = 'REQ';
                    const next = value === 'REQ' ? 'NR' : (value === 'NR' ? 'LATER' : 'REQ');
                    applyNrp([{ sku: data.sku, parent: data.parent || '' }], next, false);
                },
            },
        ],
    });

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    async function toggleClearance(cell, btn) {
        const sku = cell.getRow().getData().sku;
        if (!sku || btn.dataset.busy === '1') return;
        btn.dataset.busy = '1';
        btn.disabled = true;
        try {
            const res = await fetch(clearanceUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ sku }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Unable to save clearance.');
            cell.getRow().update({
                clearance: data.clearance,
                clearance_has_history: true,
            });
        } catch (err) {
            btn.disabled = false;
            btn.dataset.busy = '0';
            alert(err.message || 'Unable to save clearance.');
        }
    }

    async function openClearanceHistory(sku) {
        const modalEl = document.getElementById('invdaysClearanceModal');
        document.getElementById('invdaysClearanceSku').textContent = sku || '';
        document.getElementById('invdaysClearanceBody').innerHTML = '<span class="text-muted">Loading…</span>';
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        try {
            const res = await fetch(clearanceHistoryUrl + '?sku=' + encodeURIComponent(sku), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            const rows = Array.isArray(data.data) ? data.data : [];
            if (!rows.length) {
                document.getElementById('invdaysClearanceBody').innerHTML = '<span class="text-muted">No changes yet.</span>';
                return;
            }
            const body = rows.map(function (row) {
                const fromLabel = String(row.from_value || 'NO').toUpperCase() === 'YES' ? 'Yes' : 'NO';
                const toLabel = String(row.to_value || 'NO').toUpperCase() === 'YES' ? 'Yes' : 'NO';
                return `<tr>
                    <td>${escapeHtml(row.created_at || '—')}</td>
                    <td>${escapeHtml(row.changed_by || '—')}</td>
                    <td>${escapeHtml(fromLabel)} → <strong>${escapeHtml(toLabel)}</strong></td>
                </tr>`;
            }).join('');
            document.getElementById('invdaysClearanceBody').innerHTML =
                `<table class="table table-sm mb-0">
                    <thead><tr><th>When</th><th>User</th><th>Change</th></tr></thead>
                    <tbody>${body}</tbody>
                </table>`;
        } catch (err) {
            document.getElementById('invdaysClearanceBody').textContent = 'Unable to load history.';
        }
    }

    table.on('dataFiltered', function () {
        updateCount();
        syncBulkUi();
    });

    function shownSkus() {
        return allRows.filter(rowPasses).map(function (row) { return row.sku; });
    }

    function syncBulkUi() {
        const n = selectedSkus.size;
        document.getElementById('invdaysBulkYes').classList.toggle('d-none', n === 0);
        document.getElementById('invdaysBulkNo').classList.toggle('d-none', n === 0);
        document.getElementById('invdaysBulkReq').classList.toggle('d-none', n === 0);
        document.getElementById('invdaysBulkNr').classList.toggle('d-none', n === 0);
        document.getElementById('invdaysBulkLater').classList.toggle('d-none', n === 0);
        document.getElementById('invdaysBulkYes').textContent = n ? 'Clearance Yes (' + n + ')' : 'Clearance Yes';
        document.getElementById('invdaysBulkNo').textContent = n ? 'Clearance NO (' + n + ')' : 'Clearance NO';
        document.getElementById('invdaysBulkReq').textContent = n ? 'NRP REQ (' + n + ')' : 'NRP REQ';
        document.getElementById('invdaysBulkNr').textContent = n ? 'NRP NR (' + n + ')' : 'NRP NR';
        document.getElementById('invdaysBulkLater').textContent = n ? 'NRP LATER (' + n + ')' : 'NRP LATER';
        const allBox = document.getElementById('invdaysSelectAll');
        if (!allBox) return;
        const shown = shownSkus();
        const selectedShown = shown.filter(function (sku) { return selectedSkus.has(sku); }).length;
        allBox.checked = shown.length > 0 && selectedShown === shown.length;
        allBox.indeterminate = selectedShown > 0 && selectedShown < shown.length;
    }

    function refreshVisibleChecks() {
        if (!table) return;
        table.getRows().forEach(function (row) {
            const el = row.getElement();
            if (el && el.isConnected) row.reformat();
        });
        syncBulkUi();
    }

    function applyToSelectedRows(chosen, patch, clearSelection) {
        const updates = [];
        allRows.forEach(function (row) {
            if (!chosen.has(row.sku)) return;
            Object.assign(row, patch);
            if (row.id != null) updates.push(Object.assign({ id: row.id }, patch));
        });
        if (updates.length && table) table.updateData(updates);
        if (clearSelection) selectedSkus.clear();
        refreshVisibleChecks();
    }

    async function applyBulkClearance(value) {
        const skus = Array.from(selectedSkus);
        if (!skus.length) return;
        const yesBtn = document.getElementById('invdaysBulkYes');
        const noBtn = document.getElementById('invdaysBulkNo');
        yesBtn.disabled = true;
        noBtn.disabled = true;
        try {
            const res = await fetch(clearanceBulkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ skus: skus, value: value }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Unable to save clearance.');
            applyToSelectedRows(new Set(skus), { clearance: value, clearance_has_history: true }, true);
        } catch (err) {
            alert(err.message || 'Unable to save clearance.');
        } finally {
            yesBtn.disabled = false;
            noBtn.disabled = false;
        }
    }

    document.getElementById('invdaysBulkYes').addEventListener('click', function () { applyBulkClearance('YES'); });
    document.getElementById('invdaysBulkNo').addEventListener('click', function () { applyBulkClearance('NO'); });

    function selectedNrpItems() {
        return allRows.filter(function (row) { return selectedSkus.has(row.sku); }).map(function (row) {
            return { sku: row.sku, parent: row.parent || '' };
        });
    }

    const nrpButtons = ['invdaysBulkReq', 'invdaysBulkNr', 'invdaysBulkLater'];

    async function applyNrp(items, value, clearSelection) {
        if (!items.length) return;
        nrpButtons.forEach(function (id) { document.getElementById(id).disabled = true; });
        try {
            const res = await fetch(nrpBulkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ items: items, value: value }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Unable to save NRP.');
            applyToSelectedRows(new Set(items.map(function (item) { return item.sku; })), { nr: value }, clearSelection !== false);
        } catch (err) {
            alert(err.message || 'Unable to save NRP.');
        } finally {
            nrpButtons.forEach(function (id) { document.getElementById(id).disabled = false; });
        }
    }

    document.getElementById('invdaysBulkReq').addEventListener('click', function () { applyNrp(selectedNrpItems(), 'REQ', true); });
    document.getElementById('invdaysBulkNr').addEventListener('click', function () { applyNrp(selectedNrpItems(), 'NR', true); });
    document.getElementById('invdaysBulkLater').addEventListener('click', function () { applyNrp(selectedNrpItems(), 'LATER', true); });

    let searchTimer = null;
    function onSearchInput() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 200);
    }
    document.getElementById('invdaysSkuSearch').addEventListener('input', onSearchInput);
    document.getElementById('invdaysParentSearch').addEventListener('input', onSearchInput);
    document.getElementById('invdaysInvFilter').addEventListener('change', applyFilters);
    document.getElementById('invdaysAgeFilter').addEventListener('change', applyFilters);
    document.getElementById('invdaysExpFilter').addEventListener('change', applyFilters);
    document.getElementById('invdaysRefresh').addEventListener('click', function () {
        if (table) table.replaceData();
    });
});
</script>
@endsection
