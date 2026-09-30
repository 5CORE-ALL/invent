@extends('layouts.vertical', ['title' => 'Inv Change L30', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Inv Change L30', 'sub_title' => 'Inventory'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-0">Inv Change L30</h4>
                        <small class="text-muted">SKUs with zero Shopify INV and OVL30 &gt; 0. Same data as CP Master.<span id="invc7Baseline" class="d-none"></span></small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="invc7Refresh">
                            <i class="fas fa-rotate me-1"></i>Refresh
                        </button>
                        <span class="badge bg-primary fs-6 px-3 py-2" title="Rows currently shown">Rows: <span id="invc7Count">0</span></span>
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
    const dataUrl = @json(route('inv.change.l30.data'));
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

    let allRows = [];

    function rowPasses(data) {
        const skuQ = (document.getElementById('invc7SkuSearch').value || '').trim().toLowerCase();
        const parentQ = (document.getElementById('invc7ParentSearch').value || '').trim().toLowerCase();
        if (Number(data.inv) !== 0) return false;
        if (!(Number(data.ovl30) > 0)) return false;
        if (skuQ && !String(data.sku || '').toLowerCase().includes(skuQ)) return false;
        if (parentQ && !String(data.parent || '').toLowerCase().includes(parentQ)) return false;
        return true;
    }

    function updateCount() {
        const n = allRows.filter(rowPasses).length;
        document.getElementById('invc7Count').textContent = n.toLocaleString();
    }

    function applyFilters() {
        if (!table) return;
        table.setFilter(rowPasses);
        updateCount();
    }

    table = new Tabulator('#invc7-table', {
        ajaxURL: dataUrl,
        ajaxResponse: function (url, params, response) {
            if (response && response.baseline_date) {
                document.getElementById('invc7Baseline').textContent = response.baseline_date;
            }
            allRows = Array.isArray(response?.data) ? response.data : [];
            updateCount();
            return allRows;
        },
        layout: 'fitColumns',
        height: '72vh',
        pagination: true,
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 200, true],
        placeholder: 'No SKUs with zero INV and OVL30 > 0',
        initialSort: [{ column: 'ovl30', dir: 'asc' }],
        initialFilter: rowPasses,
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
                width: 100,
                hozAlign: 'right',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    return fmtNum(cell.getValue());
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
            { title: 'Inv 7d ago', field: 'prev_inv', visible: false, sorter: 'number' },
        ],
    });

    table.on('dataFiltered', updateCount);

    let searchTimer = null;
    function onSearchInput() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 200);
    }
    document.getElementById('invc7SkuSearch').addEventListener('input', onSearchInput);
    document.getElementById('invc7ParentSearch').addEventListener('input', onSearchInput);
    document.getElementById('invc7Refresh').addEventListener('click', function () {
        if (table) table.replaceData();
    });
});
</script>
@endsection
