@extends('layouts.vertical', ['title' => 'Inv Change 7days', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    #invc7-table.tabulator { width: 100%; }
    #invc7-table .tabulator-header {
        background: linear-gradient(180deg, #eef3fb 0%, #e3ebf8 100%);
        border-bottom: 1px solid #c5d4ea;
    }
    #invc7-table .tabulator-header .tabulator-col {
        background: transparent;
        border-right: 1px solid #d7e0ef;
    }
    #invc7-table .tabulator-header .tabulator-col-content .tabulator-col-title {
        color: #1a3d7c;
        font-weight: 700;
        font-size: 0.9rem;
        text-align: center;
    }
    #invc7-table .tabulator-row .tabulator-cell {
        padding: 6px 8px;
        overflow: visible;
    }
    #invc7-table .tabulator-tableholder { overflow: auto !important; }
    #invc7-table .invc7-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 56px;
    }
    .invc7-product-img {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        display: block;
    }
    .invc7-dil {
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.85rem;
    }
    .invc7-dil--hot { background: #fee2e2; color: #b91c1c; }
    .invc7-dil--warm { background: #fef3c7; color: #b45309; }
    .invc7-dil--ok { background: #dcfce7; color: #15803d; }
    .invc7-change { font-size: 0.75rem; font-weight: 600; }
    .invc7-change--up { color: #15803d; }
    .invc7-change--down { color: #b91c1c; }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Inv Change 7days', 'sub_title' => 'Inventory'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-0">Inv Change 7days</h4>
                        <small class="text-muted">SKUs whose Shopify INV changed versus the daily snapshot from 7 days ago (<span id="invc7Baseline">—</span>). Same data as CP Master.</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <select id="invc7Direction" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">Up &amp; Down</option>
                            <option value="down">Decreased only</option>
                            <option value="up">Increased only</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="invc7Refresh">
                            <i class="fas fa-rotate me-1"></i>Refresh
                        </button>
                        <span class="badge bg-primary-subtle text-primary" id="invc7Count">0</span>
                    </div>
                </div>
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-4 col-lg-3">
                        <label for="invc7ParentSearch" class="form-label fw-semibold mb-1">Search Parent</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="invc7ParentSearch" class="form-control" placeholder="Type Parent…">
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="invc7SkuSearch" class="form-label fw-semibold mb-1">Search SKU</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="invc7SkuSearch" class="form-control" placeholder="Type SKU…">
                        </div>
                    </div>
                </div>
                <div id="invc7-table"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dataUrl = @json(route('inv.change.seven.days.data'));
    let table = null;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function fmtNum(v) {
        const n = Number(v);
        if (!Number.isFinite(n)) return '—';
        return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
    }

    function updateCount() {
        if (!table) return;
        document.getElementById('invc7Count').textContent = String(table.getDataCount('active'));
    }

    function applyFilters() {
        if (!table) return;
        const skuQ = (document.getElementById('invc7SkuSearch').value || '').trim().toLowerCase();
        const parentQ = (document.getElementById('invc7ParentSearch').value || '').trim().toLowerCase();
        const dir = document.getElementById('invc7Direction').value || 'all';
        table.setFilter(function (data) {
            if (skuQ && !String(data.sku || '').toLowerCase().includes(skuQ)) return false;
            if (parentQ && !String(data.parent || '').toLowerCase().includes(parentQ)) return false;
            const change = Number(data.change || 0);
            if (dir === 'up' && change <= 0) return false;
            if (dir === 'down' && change >= 0) return false;
            return true;
        });
        updateCount();
    }

    table = new Tabulator('#invc7-table', {
        ajaxURL: dataUrl,
        ajaxResponse: function (url, params, response) {
            if (response && response.baseline_date) {
                document.getElementById('invc7Baseline').textContent = response.baseline_date;
            }
            return response?.data || [];
        },
        layout: 'fitColumns',
        height: '72vh',
        pagination: true,
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 200, true],
        placeholder: 'No INV changes in the last 7 days',
        columns: [
            {
                title: 'Image',
                field: 'image',
                width: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const src = cell.getValue();
                    if (!src) return '<div class="invc7-cell"><span class="text-muted">—</span></div>';
                    return `<div class="invc7-cell"><img src="${escapeHtml(src)}" class="invc7-product-img" alt="SKU" loading="lazy" onerror="this.outerHTML='<span class=\\'text-muted\\'>—</span>'"></div>`;
                },
            },
            {
                title: 'Parent',
                field: 'parent',
                minWidth: 140,
                widthGrow: 1,
                headerFilter: 'input',
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
                headerFilter: 'input',
                formatter: function (cell) {
                    return `<span class="fw-semibold">${escapeHtml(cell.getValue())}</span>`;
                },
            },
            {
                title: 'INV',
                field: 'inv',
                width: 130,
                hozAlign: 'right',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const d = cell.getRow().getData();
                    const change = Number(d.change || 0);
                    const cls = change > 0 ? 'invc7-change--up' : 'invc7-change--down';
                    const sign = change > 0 ? '+' : '';
                    const pct = (d.change_pct === null || d.change_pct === undefined) ? '' : ` (${sign}${d.change_pct}%)`;
                    return `${fmtNum(cell.getValue())}<div class="invc7-change ${cls}" title="Was ${fmtNum(d.prev_inv)} on ${escapeHtml(d.baseline_date || '')}">${sign}${fmtNum(change)}${pct} from ${fmtNum(d.prev_inv)}</div>`;
                },
            },
            {
                title: 'OVL30',
                field: 'ovl30',
                width: 100,
                hozAlign: 'right',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    return fmtNum(cell.getValue());
                },
            },
            {
                title: 'DIL',
                field: 'dil',
                width: 100,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const v = cell.getValue();
                    const inv = Number(cell.getRow().getData().inv);
                    if (v === null || v === undefined) {
                        return inv === 0 ? '<span class="invc7-dil invc7-dil--hot">0 inv</span>' : '<span class="text-muted">—</span>';
                    }
                    const n = Number(v);
                    const cls = n >= 200 ? 'invc7-dil--hot' : (n >= 100 ? 'invc7-dil--warm' : 'invc7-dil--ok');
                    return `<span class="invc7-dil ${cls}">${n}%</span>`;
                },
            },
            { title: 'Change', field: 'change', visible: false, sorter: 'number' },
        ],
    });

    table.on('dataLoaded', updateCount);
    table.on('dataFiltered', updateCount);

    let searchTimer = null;
    function onSearchInput() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 200);
    }
    document.getElementById('invc7SkuSearch').addEventListener('input', onSearchInput);
    document.getElementById('invc7ParentSearch').addEventListener('input', onSearchInput);
    document.getElementById('invc7Direction').addEventListener('change', applyFilters);
    document.getElementById('invc7Refresh').addEventListener('click', function () {
        if (table) table.replaceData();
    });
});
</script>
@endsection
