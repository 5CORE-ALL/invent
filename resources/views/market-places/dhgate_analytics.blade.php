@extends('layouts.vertical', ['title' => 'DH Gate Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #dhgate-analytics-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        #dhgate-analytics-wrap .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        #dhgate-analytics-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #dhgate-analytics-wrap .tabulator .tabulator-header .tabulator-col {
            background: #f8f9fa !important;
            border-right: 1px solid #e9ecef;
            font-weight: 700;
        }
        #dhgate-analytics-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
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
        #dhgate-analytics-wrap .tabulator .tabulator-row .tabulator-cell {
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
        'page_title' => 'DH Gate Analytics',
        'sub_title' => 'DH Gate',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card position-relative shadow-sm">
                <div id="dg-loader" class="card-loader-overlay">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div class="card-body">
                    <div id="summary-stats" class="mb-2 p-3 bg-light rounded">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-dark fs-6 p-2" id="dg-rows-badge">Row: 0</span>
                            <span class="badge fs-6 p-2" style="background:#0d9488;color:#fff;" id="dg-inv-badge">INV: 0</span>
                            <span class="badge fs-6 p-2" style="background:#0369a1;color:#fff;" id="dg-ovl30-badge">OV L30: 0</span>
                            @include('partials.analytics-dil-badge', ['dilChannel' => 'dhgate'])
                        </div>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        <select id="dg-row-type" class="form-select form-select-sm" style="width:110px;" title="SKU or parent row">
                            <option value="all">All</option>
                            <option value="sku" selected>SKU</option>
                            <option value="row">Parent</option>
                        </select>
                        <select id="dg-inventory-filter" class="form-select form-select-sm" style="width:150px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>
                        <select id="dg-dil-filter" class="form-select form-select-sm" style="width:160px;">
                            <option value="all">DIL%</option>
                            <option value="red">Red (&lt;25%)</option>
                            <option value="green">Green (25–50%)</option>
                            <option value="pink">Pink (50%+)</option>
                        </select>
                        <input type="text" id="dg-search" class="form-control form-control-sm" style="max-width:240px;" placeholder="Search Parent or SKU...">
                    </div>

                    <div id="dhgate-analytics-wrap">
                        <div id="dhgate-analytics-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let dgTable = null;
        let dgAllRows = [];

        function dgIsParent(data) {
            if (window.isPmParentRowData) return window.isPmParentRowData(data);
            return !!(data && (data.is_parent || data.is_parent_summary || String(data.sku || '').toUpperCase().indexOf('PARENT') === 0));
        }

        function dgInv(data) {
            const n = parseInt(data && (data.inv != null ? data.inv : data.INV), 10);
            return Number.isFinite(n) && n > 0 ? n : 0;
        }

        function dgDil(data) {
            const inv = dgInv(data);
            const ov = parseInt(data && data.ov_l30, 10) || 0;
            if (inv === 0) return 0;
            return (ov / inv) * 100;
        }

        function dgEsc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function dgRowMatches(data) {
            if (!data) return false;
            const parent = dgIsParent(data);
            const view = document.getElementById('dg-row-type').value;
            if (view === 'sku' && parent) return false;
            if (view === 'row' && !parent) return false;

            const inv = dgInv(data);
            const inventory = document.getElementById('dg-inventory-filter').value;
            if (inventory === 'zero' && inv !== 0) return false;
            if (inventory === 'more' && inv <= 0) return false;

            const dil = dgDil(data);
            const dilF = document.getElementById('dg-dil-filter').value;
            if (dilF === 'red' && !(dil < 25)) return false;
            if (dilF === 'green' && !(dil >= 25 && dil < 50)) return false;
            if (dilF === 'pink' && !(dil >= 50)) return false;

            const q = (document.getElementById('dg-search').value || '').trim().toLowerCase();
            if (q) {
                const hay = (String(data.parent || '') + ' ' + String(data.Parent || '') + ' ' + String(data.sku || '')).toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        }

        function updateSummary(rows) {
            let visible = 0, inv = 0, ov = 0;
            (rows || []).forEach(function (row) {
                if (dgIsParent(row)) return;
                visible++;
                inv += dgInv(row);
                ov += parseInt(row.ov_l30, 10) || 0;
            });
            document.getElementById('dg-rows-badge').textContent = 'Row: ' + visible.toLocaleString();
            document.getElementById('dg-inv-badge').textContent = 'INV: ' + inv.toLocaleString();
            document.getElementById('dg-ovl30-badge').textContent = 'OV L30: ' + ov.toLocaleString();
            if (window.AnalyticsDilBadge) {
                window.AnalyticsDilBadge.set(inv > 0 ? (ov / inv) * 100 : 0, ov, inv);
            }
        }

        function visibleRows() {
            if (dgTable && typeof dgTable.getData === 'function') {
                try { return dgTable.getData('active') || []; } catch (e) { /* fall through */ }
            }
            return (dgAllRows || []).filter(dgRowMatches);
        }

        function applyFilters() {
            if (!dgTable) return;
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function () { applyFilters(); });
                return;
            }
            dgTable.setFilter(dgRowMatches);
            try { dgTable.setPage(1); } catch (e) { /* ignore */ }
            updateSummary(visibleRows());
        }

        function loadTable() {
            const loader = document.getElementById('dg-loader');
            loader.style.display = 'flex';
            fetch("{{ route('dhgate.analytics.data') }}", { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    dgAllRows = json.data || [];
                    window.allTableData = dgAllRows;
                    if (window.ParentExpand) ParentExpand.captureDataset(dgAllRows);
                    if (dgTable) {
                        applyFilters();
                        return;
                    }
                    dgTable = new Tabulator('#dhgate-analytics-table', {
                        data: dgAllRows,
                        layout: 'fitDataStretch',
                        height: '68vh',
                        placeholder: 'No DH Gate SKUs found.',
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
                                    if (dgIsParent(data) || !v) return '';
                                    return '<img src="' + dgEsc(v) + '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;">';
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
                                    if (dgIsParent(data)) return '';
                                    const v = cell.getValue() || '';
                                    if (!v) return '<span style="color:#adb5bd;">–</span>';
                                    return '<span style="color:#0d6efd;font-size:11px;font-weight:600;">' + dgEsc(v) + '</span>';
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
                                    const val = dgEsc(cell.getValue() || '');
                                    if (dgIsParent(data)) return '<span style="color:#1e40af;font-weight:700;">' + val + '</span>';
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
                                    const val = dgInv(cell.getRow().getData());
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
                                    const dil = dgDil(data);
                                    if (dgInv(data) === 0) return '<span style="color:#6c757d;">0%</span>';
                                    let color = '#e83e8c';
                                    if (dil < 25) color = '#dc3545';
                                    else if (dil < 50) color = '#28a745';
                                    return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                                }
                            }
                        ]
                    });
                    dgTable.on('tableBuilt', function () { applyFilters(); });
                    if (window.ParentExpand) {
                        ParentExpand.configure({
                            parentField: 'parent',
                            skuField: 'sku',
                            getTable: function () { return dgTable; },
                            getDataset: function () { return dgAllRows; },
                            onAfterExpand: function () { updateSummary(dgTable.getData()); },
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
                    document.getElementById('dhgate-analytics-table').textContent = 'Failed to load DH Gate analytics.';
                })
                .finally(function () { loader.style.display = 'none'; });
        }

        ['dg-row-type', 'dg-inventory-filter', 'dg-dil-filter'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', applyFilters);
        });
        document.getElementById('dg-search').addEventListener('input', applyFilters);
        loadTable();
    </script>
@endsection
