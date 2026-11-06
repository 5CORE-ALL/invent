@extends('layouts.vertical', ['title' => 'CJ Dropshipping Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #cjdropshipping-analytics-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        #cjdropshipping-analytics-wrap .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        #cjdropshipping-analytics-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #cjdropshipping-analytics-wrap .tabulator .tabulator-header .tabulator-col {
            background: #f8f9fa !important;
            border-right: 1px solid #e9ecef;
            font-weight: 700;
        }
        #cjdropshipping-analytics-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            padding: 6px 4px;
        }
        #cjdropshipping-analytics-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 6px 8px !important;
            border-right: 1px solid #f1f5f9;
        }
        #summary-stats .badge {
            font-size: 1rem;
            white-space: nowrap;
            font-weight: bold;
        }
        .card-loader-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.78);
            z-index: 20;
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'CJ Dropshipping Analytics',
        'sub_title' => 'CJ Dropshipping',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card position-relative shadow-sm">
                <div id="cj-loader" class="card-loader-overlay">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div class="card-body">
                    <div id="summary-stats" class="mb-2 p-3 bg-light rounded">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-dark fs-6 p-2" id="cj-rows-badge">Row: 0</span>
                            <span class="badge fs-6 p-2" style="background:#0d9488;color:#fff;" id="cj-inv-badge">INV: 0</span>
                            <span class="badge fs-6 p-2" style="background:#0369a1;color:#fff;" id="cj-ovl30-badge">OV L30: 0</span>
                            @include('partials.analytics-dil-badge', ['dilChannel' => 'cjdropshipping'])
                        </div>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        <select id="cj-row-type" class="form-select form-select-sm" style="width:110px;" title="SKU or parent row">
                            <option value="all">All</option>
                            <option value="sku" selected>SKU</option>
                            <option value="row">Parent</option>
                        </select>
                        <select id="cj-inventory-filter" class="form-select form-select-sm" style="width:150px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>
                        <select id="cj-dil-filter" class="form-select form-select-sm" style="width:160px;">
                            <option value="all">DIL%</option>
                            <option value="red">Red (&lt;25%)</option>
                            <option value="green">Green (25–50%)</option>
                            <option value="pink">Pink (50%+)</option>
                        </select>
                        <input type="text" id="cj-search" class="form-control form-control-sm" style="max-width:240px;" placeholder="Search Parent or SKU...">
                    </div>

                    <div id="cjdropshipping-analytics-wrap">
                        <div id="cjdropshipping-analytics-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let cjTable = null;
        let cjAllRows = [];

        function cjIsParent(data) {
            if (window.isPmParentRowData) return window.isPmParentRowData(data);
            return !!(data && (data.is_parent || data.is_parent_summary || String(data.sku || '').toUpperCase().indexOf('PARENT') === 0));
        }

        function cjInv(data) {
            const n = parseInt(data && (data.inv != null ? data.inv : data.INV), 10);
            return Number.isFinite(n) && n > 0 ? n : 0;
        }

        function cjDil(data) {
            const inv = cjInv(data);
            const ov = parseInt(data && data.ov_l30, 10) || 0;
            if (inv === 0) return 0;
            return (ov / inv) * 100;
        }

        function cjEsc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function cjRowMatches(data) {
            if (!data) return false;
            const parent = cjIsParent(data);
            const view = document.getElementById('cj-row-type').value;
            if (view === 'sku' && parent) return false;
            if (view === 'row' && !parent) return false;

            const inv = cjInv(data);
            const inventory = document.getElementById('cj-inventory-filter').value;
            if (inventory === 'zero' && inv !== 0) return false;
            if (inventory === 'more' && inv <= 0) return false;

            const dil = cjDil(data);
            const dilF = document.getElementById('cj-dil-filter').value;
            if (dilF === 'red' && !(dil < 25)) return false;
            if (dilF === 'green' && !(dil >= 25 && dil < 50)) return false;
            if (dilF === 'pink' && !(dil >= 50)) return false;

            const q = (document.getElementById('cj-search').value || '').trim().toLowerCase();
            if (q) {
                const hay = (String(data.parent || '') + ' ' + String(data.Parent || '') + ' ' + String(data.sku || '')).toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        }

        function updateSummary(rows) {
            let visible = 0, inv = 0, ov = 0;
            (rows || []).forEach(function (row) {
                if (cjIsParent(row)) return;
                visible++;
                inv += cjInv(row);
                ov += parseInt(row.ov_l30, 10) || 0;
            });
            document.getElementById('cj-rows-badge').textContent = 'Row: ' + visible.toLocaleString();
            document.getElementById('cj-inv-badge').textContent = 'INV: ' + inv.toLocaleString();
            document.getElementById('cj-ovl30-badge').textContent = 'OV L30: ' + ov.toLocaleString();
            if (window.AnalyticsDilBadge) {
                window.AnalyticsDilBadge.set(inv > 0 ? (ov / inv) * 100 : 0, ov, inv);
            }
        }

        function visibleRows() {
            if (cjTable && typeof cjTable.getData === 'function') {
                try { return cjTable.getData('active') || []; } catch (e) { /* fall through */ }
            }
            return (cjAllRows || []).filter(cjRowMatches);
        }

        function applyFilters() {
            if (!cjTable) return;
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function () { applyFilters(); });
                return;
            }
            cjTable.setFilter(cjRowMatches);
            try { cjTable.setPage(1); } catch (e) { /* ignore */ }
            updateSummary(visibleRows());
        }

        function loadTable() {
            const loader = document.getElementById('cj-loader');
            loader.style.display = 'flex';
            fetch("{{ route('cjdropshipping.analytics.data') }}", { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    cjAllRows = json.data || [];
                    window.allTableData = cjAllRows;
                    if (window.ParentExpand) ParentExpand.captureDataset(cjAllRows);
                    if (cjTable) {
                        applyFilters();
                        return;
                    }
                    cjTable = new Tabulator('#cjdropshipping-analytics-table', {
                        data: cjAllRows,
                        layout: 'fitDataStretch',
                        height: '68vh',
                        placeholder: 'No CJ Dropshipping SKUs found.',
                        pagination: true,
                        paginationSize: 50,
                        paginationSizeSelector: [25, 50, 100, 250],
                        rowFormatter: function (row) {
                            if (window.pmParentRowFormatter) window.pmParentRowFormatter(row);
                        },
                        columns: [
                            {
                                title: 'Image',
                                field: 'image',
                                headerSort: false,
                                width: 60,
                                hozAlign: 'center',
                                formatter: function (cell) {
                                    const data = cell.getRow().getData();
                                    const v = cell.getValue();
                                    if (cjIsParent(data) || !v) return '';
                                    return '<img src="' + cjEsc(v) + '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;">';
                                }
                            },
                            {
                                title: 'Parent',
                                field: 'parent',
                                hozAlign: 'left',
                                width: 140,
                                frozen: true,
                                formatter: function (cell) {
                                    const data = cell.getRow().getData();
                                    if (cjIsParent(data)) return '';
                                    const v = cell.getValue() || '';
                                    if (!v) return '<span style="color:#adb5bd;">–</span>';
                                    return '<span style="color:#0d6efd;font-size:11px;font-weight:600;">' + cjEsc(v) + '</span>';
                                }
                            },
                            (window.ParentExpand ? ParentExpand.columnDef() : { title: 'P', field: '_parent_expand', width: 36, headerSort: false }),
                            {
                                title: 'SKU',
                                field: 'sku',
                                hozAlign: 'left',
                                minWidth: 180,
                                frozen: true,
                                cssClass: 'fw-bold',
                                formatter: function (cell) {
                                    const data = cell.getRow().getData();
                                    const val = cjEsc(cell.getValue() || '');
                                    if (cjIsParent(data)) return '<span style="color:#1e40af;font-weight:700;">' + val + '</span>';
                                    return '<span class="fw-bold">' + val + '</span>';
                                }
                            },
                            {
                                title: 'Inv',
                                field: 'inv',
                                hozAlign: 'center',
                                width: 70,
                                sorter: 'number',
                                formatter: function (cell) {
                                    const val = cjInv(cell.getRow().getData());
                                    if (val === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                                    return '<span style="font-weight:600;">' + val.toLocaleString() + '</span>';
                                }
                            },
                            {
                                title: 'Ovl30',
                                field: 'ov_l30',
                                hozAlign: 'center',
                                width: 70,
                                sorter: 'number',
                                headerTooltip: 'Shopify quantity, last 30 days',
                                formatter: function (cell) {
                                    return '<span style="font-weight:700;">' + (parseInt(cell.getValue(), 10) || 0).toLocaleString() + '</span>';
                                }
                            },
                            {
                                title: 'Dil',
                                field: 'dil_percent',
                                hozAlign: 'center',
                                width: 70,
                                sorter: 'number',
                                headerTooltip: 'Dil = OV L30 ÷ INV × 100',
                                formatter: function (cell) {
                                    const data = cell.getRow().getData();
                                    const dil = cjDil(data);
                                    if (cjInv(data) === 0) return '<span style="color:#6c757d;">0%</span>';
                                    let color = '#e83e8c';
                                    if (dil < 25) color = '#dc3545';
                                    else if (dil < 50) color = '#28a745';
                                    return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                                }
                            }
                        ]
                    });
                    cjTable.on('tableBuilt', function () { applyFilters(); });
                    if (window.ParentExpand) {
                        ParentExpand.configure({
                            parentField: 'parent',
                            skuField: 'sku',
                            getTable: function () { return cjTable; },
                            getDataset: function () { return cjAllRows; },
                            onAfterExpand: function () { updateSummary(cjTable.getData()); },
                            onCollapse: function () { applyFilters(); }
                        });
                        ParentExpand.bind();
                    }
                    if (window.AnalyticsDilBadge) {
                        window.AnalyticsDilBadge.init({
                            getRows: function () { return visibleRows(); }
                        });
                    }
                    applyFilters();
                })
                .catch(function () {
                    document.getElementById('cjdropshipping-analytics-table').textContent = 'Failed to load CJ Dropshipping analytics.';
                })
                .finally(function () { loader.style.display = 'none'; });
        }

        ['cj-row-type', 'cj-inventory-filter', 'cj-dil-filter'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', applyFilters);
        });
        document.getElementById('cj-search').addEventListener('input', applyFilters);
        loadTable();
    </script>
@endsection
