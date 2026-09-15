@extends('layouts.vertical', ['title' => 'Alibaba Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #alibaba-analytics-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-header {
            background: #00d5d5;
            border-bottom: 1px solid #ffffff;
        }
        #alibaba-analytics-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-header .tabulator-col {
            background: #00d5d5 !important;
            border-right: 1px solid #ffffff;
            color: #000 !important;
            font-weight: 700;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            font-size: 12.5px;
            font-weight: 700;
            text-align: center;
            color: #000 !important;
            padding: 6px 4px;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 6px 8px !important;
            border-right: 1px solid #f1f5f9;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-row:hover {
            background-color: #f8fafc !important;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .ab-stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            min-width: 86px;
            justify-content: center;
        }
        .ab-stat-badge--rows { background: #0f172a; }
        .ab-stat-badge--active { background: #16a34a; }
        .ab-stat-badge--bulk { background: #2563eb; }
        .ab-stat-badge--manual { background: #d97706; }
        .ab-stat-badge--soh { background: #7c3aed; }
        .ab-stat-badge--inv { background: #0d9488; }
        .ab-stat-badge--ovl30 { background: #0369a1; }
        .card-loader-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.78);
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Alibaba Analytics',
        'sub_title' => 'Alibaba',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card position-relative shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        <span class="ab-stat-badge ab-stat-badge--rows">Rows: <span id="ab-total">0</span></span>
                        <span class="ab-stat-badge ab-stat-badge--inv">INV: <span id="ab-inv-total">0</span></span>
                        <span class="ab-stat-badge ab-stat-badge--ovl30">OV L30: <span id="ab-ovl30">0</span></span>
                        <span class="ab-stat-badge ab-stat-badge--active">Active: <span id="ab-active">0</span></span>
                        <span class="ab-stat-badge ab-stat-badge--bulk">Bulk: <span id="ab-bulk">0</span></span>
                        <span class="ab-stat-badge ab-stat-badge--manual">Manual: <span id="ab-manual">0</span></span>
                        <span class="ab-stat-badge ab-stat-badge--soh">SOH: <span id="ab-soh">0</span></span>

                        <input type="text" id="ab-search-parent" class="form-control form-control-sm" style="width:170px;" placeholder="Search Parent...">
                        <input type="text" id="ab-search-product" class="form-control form-control-sm" style="width:170px;" placeholder="Search Product Id...">
                        <input type="text" id="ab-search-sku" class="form-control form-control-sm" style="width:170px;" placeholder="Search SKU...">
                        <select id="ab-row-type-filter" class="form-select form-select-sm" style="width:auto;">
                            <option value="all">All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus" selected>SKUs</option>
                        </select>
                        <select id="ab-inventory-filter" class="form-select form-select-sm" style="width:auto;" title="Shopify INV — same as /bestbuy-pricing">
                            <option value="all">All INV</option>
                            <option value="zero">INV = 0</option>
                            <option value="more" selected>INV &gt; 0</option>
                        </select>
                        <select id="ab-dil-filter" class="form-select form-select-sm" style="width:auto;" title="Dil = OV L30 ÷ INV — same as /bestbuy-pricing">
                            <option value="all">DIL%</option>
                            <option value="red">Red (&lt;25%)</option>
                            <option value="green">Green (25-50%)</option>
                            <option value="pink">Pink (50%+)</option>
                        </select>
                        <select id="ab-status-filter" class="form-select form-select-sm" style="width:auto;">
                            <option value="all">Status: All</option>
                            <option value="Active">Active</option>
                        </select>
                        <select id="ab-inv-update-filter" class="form-select form-select-sm" style="width:auto;">
                            <option value="all">Inv Update: All</option>
                            <option value="Bulk">Bulk</option>
                            <option value="Manual">Manual</option>
                        </select>

                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ab-sample-btn">
                            <i class="fas fa-download"></i> Download sample
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="ab-export-btn">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="ab-import-btn">
                            <i class="fas fa-file-import"></i> Import sheet
                        </button>
                    </div>

                    <div id="alibaba-analytics-wrap">
                        <div id="alibaba-analytics-table"></div>
                    </div>

                    <div id="ab-loader" class="card-loader-overlay" style="display:none;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status"></div>
                            <div class="mt-2 fw-semibold text-muted">Loading Alibaba Analytics...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="abImportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Alibaba sheet price</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">
                        Use the same columns as the Alibaba price sheet. Product Id and SKU are kept exactly as uploaded.
                    </p>
                    <a href="{{ route('alibaba.analytics.sample') }}" class="btn btn-outline-secondary btn-sm mb-3">
                        <i class="fas fa-download"></i> Download sample
                    </a>
                    <input type="file" id="ab-import-file" class="form-control" accept=".xlsx,.xls,.csv,.tsv,.txt">
                    <small class="text-muted d-block mt-2">
                        Headers: Product Id, SKU, Status, SKU Price.1, SOH, Inv Update
                    </small>
                    <div id="ab-import-message" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="ab-confirm-import">Import</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.body.style.zoom = "90%";

        let abTable = null;
        let abAllRows = [];
        let abLoadedType = null;

        function isAbParentRow(data) {
            if (!data) return false;
            return !!(data.is_parent_summary || data.is_parent_row);
        }

        function abChildRows() {
            return (abAllRows || []).filter(function (r) { return !isAbParentRow(r); });
        }

        function abVisibleRows() {
            const rowType = document.getElementById('ab-row-type-filter').value;
            if (rowType === 'parents') return (abAllRows || []).filter(isAbParentRow);
            if (rowType === 'all') return abAllRows || [];
            return abChildRows();
        }

        function abDilValue(data) {
            const inv = parseFloat(data.INV) || 0;
            const ovL30 = parseFloat(data.L30 != null ? data.L30 : data.ov_l30) || 0;
            if (inv === 0) return 0;
            return (ovL30 / inv) * 100;
        }

        function setStats(stats) {
            document.getElementById('ab-total').textContent = stats.total || 0;
            document.getElementById('ab-active').textContent = stats.active || 0;
            document.getElementById('ab-bulk').textContent = stats.bulk || 0;
            document.getElementById('ab-manual').textContent = stats.manual || 0;
            document.getElementById('ab-soh').textContent = stats.soh || 0;
            const invEl = document.getElementById('ab-inv-total');
            const ovEl = document.getElementById('ab-ovl30');
            if (invEl) invEl.textContent = stats.inv || 0;
            if (ovEl) ovEl.textContent = stats.ov_l30 || 0;
        }

        function applyFilters() {
            if (!abTable) return;
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function () { applyFilters(); });
                return;
            }
            const parentQ = (document.getElementById('ab-search-parent').value || '').trim().toLowerCase();
            const productQ = (document.getElementById('ab-search-product').value || '').trim().toLowerCase();
            const skuQ = (document.getElementById('ab-search-sku').value || '').trim().toLowerCase();
            const status = document.getElementById('ab-status-filter').value;
            const invUpdate = document.getElementById('ab-inv-update-filter').value;
            const inventory = document.getElementById('ab-inventory-filter').value;
            const dilFilter = document.getElementById('ab-dil-filter').value;
            const rowType = document.getElementById('ab-row-type-filter').value;
            const source = abVisibleRows();
            if (abLoadedType !== rowType) {
                abLoadedType = rowType;
                abTable.setData(source).then(function () { applyFilters(); });
                return;
            }

            abTable.setFilter(function (data) {
                const isParent = isAbParentRow(data);
                if (rowType === 'parents' && !isParent) return false;
                if (rowType === 'skus' && isParent) return false;
                if (parentQ && String(data.Parent || '').toLowerCase().indexOf(parentQ) === -1) return false;
                if (productQ && !isParent && String(data.product_id || '').toLowerCase().indexOf(productQ) === -1) return false;
                if (skuQ && String(data.sku || '').toLowerCase().indexOf(skuQ) === -1) return false;
                if (status !== 'all' && !isParent && String(data.status || '').toLowerCase() !== status.toLowerCase()) return false;
                if (invUpdate !== 'all' && !isParent && String(data.inv_update || '').toLowerCase() !== invUpdate.toLowerCase()) return false;
                const inv = parseFloat(data.INV) || 0;
                if (inventory === 'zero' && inv !== 0) return false;
                if (inventory === 'more' && !(inv > 0)) return false;
                const dil = abDilValue(data);
                if (dilFilter === 'red' && !(dil < 25)) return false;
                if (dilFilter === 'green' && !(dil >= 25 && dil < 50)) return false;
                if (dilFilter === 'pink' && !(dil >= 50)) return false;
                return true;
            });
        }

        function loadTable() {
            const loader = document.getElementById('ab-loader');
            loader.style.display = 'flex';
            fetch("{{ route('alibaba.analytics.data') }}", {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(json => {
                abAllRows = json.data || [];
                setStats(json.stats || {});
                abLoadedType = document.getElementById('ab-row-type-filter').value || 'skus';
                const initialRows = abVisibleRows();
                if (window.ParentExpand) ParentExpand.captureDataset(abAllRows);
                if (abTable) {
                    abTable.replaceData(initialRows);
                    applyFilters();
                    return;
                }
                abTable = new Tabulator('#alibaba-analytics-table', {
                    data: initialRows,
                    layout: 'fitColumns',
                    height: '68vh',
                    placeholder: 'No Alibaba sheet prices yet. Import the price sheet or download the sample.',
                    pagination: true,
                    paginationSize: 50,
                    paginationSizeSelector: [25, 50, 100, 250],
                    initialSort: [{ column: 'L30', dir: 'desc' }],
                    rowFormatter: function (row) {
                        const data = row.getData();
                        if (isAbParentRow(data)) {
                            row.getElement().classList.add('parent-row');
                            row.getElement().style.backgroundColor = '#fffef2';
                        }
                    },
                    columns: [
                        (window.ParentExpand ? ParentExpand.columnDef() : { title: 'P', field: '_parent_expand', width: 36, frozen: true, headerSort: false }),
                        { title: 'Parent', field: 'Parent', hozAlign: 'left', headerHozAlign: 'center', minWidth: 140, frozen: true },
                        { title: 'SKU', field: 'sku', hozAlign: 'left', headerHozAlign: 'center', minWidth: 200, frozen: true },
                        { title: 'Product Id', field: 'product_id', hozAlign: 'left', headerHozAlign: 'center', minWidth: 150 },
                        { title: 'INV', field: 'INV', hozAlign: 'center', headerHozAlign: 'center', width: 70, sorter: 'number' },
                        { title: 'OV L30', field: 'L30', hozAlign: 'center', headerHozAlign: 'center', width: 80, sorter: 'number' },
                        {
                            title: 'Dil',
                            field: 'dil_percent',
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            width: 70,
                            sorter: 'number',
                            headerTooltip: 'Dil = OV L30 ÷ INV × 100 — same as /bestbuy-pricing',
                            formatter: function (cell) {
                                const rowData = cell.getRow().getData();
                                const dil = abDilValue(rowData);
                                if ((parseFloat(rowData.INV) || 0) === 0) {
                                    return '<span style="color: #6c757d;">0%</span>';
                                }
                                let color = '#e83e8c';
                                if (dil < 25) color = '#dc3545';
                                else if (dil < 50) color = '#28a745';
                                return '<span style="color: ' + color + '; font-weight: 600;">' + Math.round(dil) + '%</span>';
                            }
                        },
                        { title: 'Status', field: 'status', hozAlign: 'center', headerHozAlign: 'center', width: 100 },
                        {
                            title: 'SKU Price.1',
                            field: 'sku_price',
                            hozAlign: 'right',
                            headerHozAlign: 'center',
                            width: 120,
                            formatter: function (cell) {
                                const v = cell.getValue();
                                return v === null || v === undefined || v === '' ? '-' : Number(v).toFixed(2);
                            }
                        },
                        { title: 'SOH', field: 'soh', hozAlign: 'right', headerHozAlign: 'center', width: 80 },
                        { title: 'Inv Update', field: 'inv_update', hozAlign: 'center', headerHozAlign: 'center', width: 120 },
                    ],
                });
                if (window.ParentExpand) {
                    ParentExpand.configure({
                        parentField: 'Parent',
                        skuField: 'sku',
                        getTable: () => abTable,
                        getDataset: () => abAllRows,
                        isParentRow: isAbParentRow,
                        onAfterExpand: function () {},
                        onCollapse: function () { applyFilters(); },
                    });
                    ParentExpand.bind();
                    ParentExpand.captureDataset(abAllRows);
                }
                applyFilters();
            })
            .finally(() => { loader.style.display = 'none'; });
        }

        document.getElementById('ab-search-parent').addEventListener('input', applyFilters);
        document.getElementById('ab-search-product').addEventListener('input', applyFilters);
        document.getElementById('ab-search-sku').addEventListener('input', applyFilters);
        document.getElementById('ab-status-filter').addEventListener('change', applyFilters);
        document.getElementById('ab-inv-update-filter').addEventListener('change', applyFilters);
        document.getElementById('ab-inventory-filter').addEventListener('change', applyFilters);
        document.getElementById('ab-dil-filter').addEventListener('change', applyFilters);
        document.getElementById('ab-row-type-filter').addEventListener('change', applyFilters);
        document.getElementById('ab-sample-btn').addEventListener('click', function () {
            window.location.href = "{{ route('alibaba.analytics.sample') }}";
        });
        document.getElementById('ab-export-btn').addEventListener('click', function () {
            window.location.href = "{{ route('alibaba.analytics.export') }}";
        });
        document.getElementById('ab-import-btn').addEventListener('click', function () {
            document.getElementById('ab-import-message').textContent = '';
            new bootstrap.Modal(document.getElementById('abImportModal')).show();
        });
        document.getElementById('ab-confirm-import').addEventListener('click', function () {
            const fileInput = document.getElementById('ab-import-file');
            const msg = document.getElementById('ab-import-message');
            if (!fileInput.files.length) {
                msg.className = 'small mt-2 text-danger';
                msg.textContent = 'Choose a file first.';
                return;
            }
            const form = new FormData();
            form.append('excel_file', fileInput.files[0]);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            msg.className = 'small mt-2 text-muted';
            msg.textContent = 'Importing...';
            fetch("{{ route('alibaba.analytics.import') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json' },
                body: form
            })
            .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
            .then(({ ok, body }) => {
                msg.className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger');
                msg.textContent = body.message || (ok ? 'Imported.' : 'Import failed.');
                if (ok) {
                    loadTable();
                    setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('abImportModal')).hide(), 700);
                }
            })
            .catch(() => {
                msg.className = 'small mt-2 text-danger';
                msg.textContent = 'Import failed.';
            });
        });

        loadTable();
    </script>
@endsection
