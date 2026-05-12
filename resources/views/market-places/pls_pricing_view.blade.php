@extends('layouts.vertical', ['title' => 'PLS Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }
        
        .copy-sku-btn:hover {
            color: #0d6efd !important;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'PLS Pricing',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>PLS Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <div class="d-flex flex-column gap-1" style="width: auto;">
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                    </div>

                    <select id="roi-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="125-175">125–175%</option>
                        <option value="175-250">175–250%</option>
                        <option value="gt250">&gt; 250%</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All DIL%</option>
                        <option value="red">Red (&lt;16.7%)</option>
                        <option value="yellow">Yellow (16.7-25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu" style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary</h6>
                    <div class="d-flex gap-2 mb-2">
                        <span class="badge bg-info fs-6 p-2 flex-fill text-center" id="total-l30-badge">PLS L30: 0</span>
                        <span class="badge bg-warning fs-6 p-2 flex-fill text-center" id="avg-price-badge">Price: $0</span>
                        <span class="badge bg-danger fs-6 p-2 flex-fill text-center" id="avg-gpft-badge">GPFT%: 0%</span>
                        <span class="badge fs-6 p-2 flex-fill text-center" id="avg-roi-badge" style="background-color: purple; color: white;">ROI%: 0%</span>
                        <span class="badge bg-danger fs-6 p-2 flex-fill text-center" id="missing-price-badge" style="cursor: pointer;">Missing: 0</span>
                        <span class="badge bg-danger fs-6 p-2 flex-fill text-center" id="not-mapped-badge" style="cursor: pointer;">N MP: 0</span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="pls-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU...">
                    </div>
                    <div id="pls-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "pls_tabulator_column_visibility";
    const PLS_PERCENTAGE = {{ $plsPercentage ?? 100 }} / 100; // Dynamic from database
    let table = null;

    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    $(document).ready(function() {

        $('#inventory-filter').on('change', function () { applyFilters(); });
        $('#gpft-filter').on('change', function () { applyFilters(); });
        $('#roi-filter').on('change', function () { applyFilters(); });
        $('#dil-filter').on('change', function () { applyFilters(); });

        $('#sku-search').on('keyup', function () {
            const val = $(this).val();
            if (val) {
                table.setFilter('sku', 'like', val);
            } else {
                table.clearFilter();
                applyFilters();
            }
        });

        function applyFilters() {
            table.clearFilter();
            
            const invFilter = $('#inventory-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const roiFilter = $('#roi-filter').val();
            const dilFilter = $('#dil-filter').val();
            
            // Use a single combined filter function
            table.addFilter(function(data) {
                // Inventory filter
                if (invFilter === 'zero' && parseInt(data.inventory) !== 0) return false;
                if (invFilter === 'more' && parseInt(data.inventory) <= 0) return false;
                
                // GPFT filter
                if (gpftFilter !== 'all') {
                    const gpft = parseFloat(data.gpft_pct) || 0;
                    if (gpftFilter === 'negative' && gpft >= 0) return false;
                    if (gpftFilter === '0-10' && (gpft < 0 || gpft >= 10)) return false;
                    if (gpftFilter === '10-20' && (gpft < 10 || gpft >= 20)) return false;
                    if (gpftFilter === '20-30' && (gpft < 20 || gpft >= 30)) return false;
                    if (gpftFilter === '30-40' && (gpft < 30 || gpft >= 40)) return false;
                    if (gpftFilter === '40-50' && (gpft < 40 || gpft >= 50)) return false;
                    if (gpftFilter === '50plus' && gpft < 50) return false;
                }
                
                // ROI filter
                if (roiFilter !== 'all') {
                    const roi = parseFloat(data.roi_pct) || 0;
                    if (roiFilter === 'lt40' && roi >= 40) return false;
                    if (roiFilter === '40-75' && (roi < 40 || roi >= 75)) return false;
                    if (roiFilter === '75-125' && (roi < 75 || roi >= 125)) return false;
                    if (roiFilter === '125-175' && (roi < 125 || roi >= 175)) return false;
                    if (roiFilter === '175-250' && (roi < 175 || roi >= 250)) return false;
                    if (roiFilter === 'gt250' && roi < 250) return false;
                }
                
                // DIL filter
                if (dilFilter !== 'all') {
                    const dil = parseFloat(data.dil_pct) || 0;
                    if (dilFilter === 'red' && dil >= 16.7) return false;
                    if (dilFilter === 'yellow' && (dil < 16.7 || dil >= 25)) return false;
                    if (dilFilter === 'green' && (dil < 25 || dil >= 50)) return false;
                    if (dilFilter === 'pink' && dil < 50) return false;
                }
                
                return true;
            });
            
            updateSummary();
        }

        // Initialize Tabulator
        table = new Tabulator('#pls-table', {
            ajaxURL: '/pls-pricing-data-json',
            ajaxSorting: false,
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: 'rows',
            initialSort: [
                {column: "sku", dir: "asc"}
            ],
            columnCalcs: 'both',
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            rowFormatter: function(row) {
                if (row.getData().parent && row.getData().parent.startsWith('PARENT')) {
                    row.getElement().style.backgroundColor = "rgba(69, 233, 255, 0.1)";
                }
            },
            columns: [
                {
                    title: "Image",
                    field: "image_path",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
                        }
                        return '';
                    },
                    headerSort: false,
                    width: 70
                },
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold",
                    tooltip: true,
                    frozen: true,
                    width: 200,
                    sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                        // Case-insensitive alphabetical sorting
                        const aVal = (a || '').toString().toUpperCase();
                        const bVal = (b || '').toString().toUpperCase();
                        return aVal.localeCompare(bVal);
                    },
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        let html = `<span>${sku}</span>`;
                        html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                   data-sku="${sku}"
                                   title="Copy SKU"></i>`;
                        return html;
                    }
                },
                {
                    title: "INV",
                    field: "inventory",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600;">${v}</span>`;
                    }
                },
                {
                    title: "OV L30",
                    field: "l30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600;">${v}</span>`;
                    }
                },
                {
                    title: "Dil",
                    field: "dil_pct",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const INV = parseFloat(rowData.inventory) || 0;
                        const OVL30 = parseFloat(rowData.l30) || 0;
                        
                        if (INV === 0) return '<span style="color: #6c757d;">0%</span>';
                        
                        const dil = (OVL30 / INV) * 100;
                        let color = '';
                        
                        if (dil < 16.66) color = '#a00211';
                        else if (dil >= 16.66 && dil < 25) color = '#ffc107';
                        else if (dil >= 25 && dil < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "PLS INV",
                    field: "pls_inventory",
                    hozAlign: "center",
                    width: 80,
                    sorter: "number",
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600; color: #28a745;">${v}</span>`;
                    }
                },
                {
                    title: "Prc",
                    field: "price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        
                        if (value === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                        }
                        
                        return `<span style="font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    width: 70
                },
                {
                    title: "PLS L30",
                    field: "pls_l30",
                    hozAlign: "center",
                    width: 80,
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600; color: #17a2b8;">${v}</span>`;
                    }
                },
                {
                    title: "MC L30",
                    field: "l60",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600;">${v}</span>`;
                    }
                },
                {
                    title: "Parent",
                    field: "parent",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent...",
                    cssClass: "text-primary",
                    tooltip: true,
                    width: 150,
                    visible: false,
                    sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                        // Case-insensitive alphabetical sorting
                        const aVal = (a || '').toString().toUpperCase();
                        const bVal = (b || '').toString().toUpperCase();
                        return aVal.localeCompare(bVal);
                    }
                },
                {
                    title: "PLS L60",
                    field: "pls_l60",
                    hozAlign: "center",
                    width: 80,
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600; color: #6f42c1;">${v}</span>`;
                    }
                },
                {
                    title: "Missing",
                    field: "missing",
                    hozAlign: "center",
                    sorter: "string",
                    width: 80,
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 'M') {
                            return '<span style="color: #dc3545; font-weight: bold;" title="Not found in pls_products or INV>0 but no price">M</span>';
                        }
                        return '';
                    }
                },
                {
                    title: "MAP",
                    field: "MAP",
                    hozAlign: "center",
                    width: 90,
                    sorter: "string",
                    visible: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const missing = rowData['missing'];

                        // Only show MAP if SKU exists in PLS (not missing)
                        if (missing === 'M') {
                            return ''; // Don't show MAP for missing items
                        }
                        
                        const plsInventory = parseFloat(rowData['pls_inventory']) || 0;
                        const inv = parseFloat(rowData['inventory']) || 0;
                        
                        if (inv > 0 && plsInventory === 0) {
                            if (inv <= 3) {
                                return '<span style="color: #28a745; font-weight: bold;" title="Within tolerance (≤3)">MP</span>';
                            }
                            return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${inv})</span>`;
                        }
                        
                        if (inv > 0 && plsInventory > 0) {
                            if (inv === plsInventory || Math.abs(inv - plsInventory) <= 3) {
                                return '<span style="color: #28a745; font-weight: bold;" title="Within ≤3: counts as MP">MP</span>';
                            } else {
                                const diff = inv - plsInventory;
                                const sign = diff > 0 ? '+' : '';
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                            }
                        }
                        
                        return '';
                    }
                },
                {
                    title: "GPFT%",
                    field: "gpft_pct",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent < 30) color = '#28a745';
                        else color = '#20c997';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(1)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "PFT%",
                    field: "gpft_pct",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent < 30) color = '#28a745';
                        else color = '#20c997';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(1)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "ROI%",
                    field: "roi_pct",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 40) color = '#a00211';
                        else if (percent >= 40 && percent < 75) color = '#ffc107';
                        else if (percent >= 75 && percent < 125) color = '#3591dc';
                        else if (percent >= 125 && percent < 175) color = '#28a745';
                        else color = '#20c997';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "S PRC",
                    field: "sprice",
                    hozAlign: "center",
                    editor: "input",
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const hasCustomSprice = rowData.has_custom_sprice;
                        const currentPrice = parseFloat(rowData.price) || 0;
                        const sprice = parseFloat(value) || 0;
                        
                        if (!value) return '';
                        
                        // Show SPRICE value
                        const formattedValue = `$${parseFloat(value).toFixed(2)}`;
                        
                        // If using default price (not custom), show in blue
                        if (hasCustomSprice === false) {
                            return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                        }
                        
                        return formattedValue;
                    },
                    width: 80
                },
                {
                    title: "SGPFT%",
                    field: "sgpft",
                    hozAlign: "center",
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent < 30) color = '#28a745';
                        else color = '#20c997';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(1)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "SROI%",
                    field: "sroi",
                    hozAlign: "center",
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 40) color = '#a00211';
                        else if (percent >= 40 && percent < 75) color = '#ffc107';
                        else if (percent >= 75 && percent < 125) color = '#3591dc';
                        else if (percent >= 125 && percent < 175) color = '#28a745';
                        else color = '#20c997';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "P STS",
                    field: "pls_status",
                    hozAlign: "center",
                    headerSort: false,
                    tooltip: "PLS push status: ✓✓ pushed, ✗ failed, — not pushed",
                    formatter: function(cell) {
                        const st = cell.getValue();
                        if (st === 'pushed' || st === 'applied') {
                            return '<span style="color:#28a745;" title="PLS: price pushed"><i class="fa-solid fa-check-double"></i></span>';
                        }
                        if (st === 'error') {
                            return '<span style="color:#dc3545;" title="PLS: push failed"><i class="fa-solid fa-xmark"></i></span>';
                        }
                        if (st === 'processing') {
                            return '<span style="color:#ffc107;" title="PLS: processing..."><i class="fas fa-spinner fa-spin"></i></span>';
                        }
                        return '<span style="color:#adb5bd;" title="PLS: not pushed">—</span>';
                    },
                    width: 50
                },
                {
                    title: "Push",
                    field: "_push",
                    hozAlign: "center",
                    headerSort: false,
                    tooltip: "Push price to PLS marketplace",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const spriceRaw = rowData.sprice;
                        const sprice = spriceRaw ? parseFloat(spriceRaw) : 0;
                        const plsStatus = rowData.pls_status || null;
                        
                        if (!sku || !sprice || sprice <= 0) {
                            return '<span style="color: #adb5bd;">N/A</span>';
                        }
                        
                        // Determine PLS button icon and color
                        let plsIcon = '<i class="fas fa-check"></i>';
                        let plsColor = '#28a745'; // Green
                        let plsTitle = 'Push to PLS';
                        
                        if (plsStatus === 'pushed' || plsStatus === 'applied') {
                            plsIcon = '<i class="fa-solid fa-check-double"></i>';
                            plsColor = '#28a745'; // Green when pushed
                            plsTitle = 'Price pushed to PLS';
                        } else if (plsStatus === 'error') {
                            plsIcon = '<i class="fa-solid fa-x"></i>';
                            plsColor = '#dc3545'; // Red
                            plsTitle = 'Error pushing to PLS';
                        } else if (plsStatus === 'processing') {
                            plsIcon = '<i class="fas fa-spinner fa-spin"></i>';
                            plsColor = '#ffc107'; // Yellow
                            plsTitle = 'Pushing to PLS...';
                        }
                        
                        return `<button type="button" class="btn btn-sm push-price-btn btn-circle" 
                                       data-sku="${sku}" 
                                       data-price="${spriceRaw}" 
                                       data-status="${plsStatus || ''}" 
                                       title="${plsTitle}" 
                                       style="border: none; background: none; color: ${plsColor}; padding: 0; cursor: pointer; font-size: 1.3em;">
                                    ${plsIcon}
                                </button>`;
                    },
                    cellClick: function(e, cell) {
                        // Handle button click
                        const $target = $(e.target);
                        
                        if ($target.hasClass('push-price-btn') || $target.closest('.push-price-btn').length) {
                            e.stopPropagation();
                            const $btn = $target.hasClass('push-price-btn') ? $target : $target.closest('.push-price-btn');
                            
                            // Read price from fresh row data
                            const rowData = cell.getRow().getData();
                            const sku = rowData.sku;
                            const price = parseFloat(rowData.sprice) || 0;
                            
                            if (!sku || !price || price <= 0 || isNaN(price)) {
                                showToast('Invalid SKU or price', 'error');
                                return;
                            }
                            
                            // Disable button and show loading state
                            $btn.prop('disabled', true);
                            $btn.html('<i class="fas fa-clock fa-spin" style="color: #ffc107;"></i>');
                            
                            // Update row status to processing
                            const row = cell.getRow();
                            const updatedData = row.getData();
                            updatedData.pls_status = 'processing';
                            row.update(updatedData);
                            
                            // Push to PLS
                            $.ajax({
                                url: '/push-pls-price',
                                method: 'POST',
                                timeout: 120000,
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                data: {
                                    sku: sku,
                                    price: price
                                },
                                success: function(response) {
                                    // Check for errors in response
                                    if (response.errors && response.errors.length > 0) {
                                        const errorMsg = response.errors[0].message || 'Unknown error';
                                        const row = cell.getRow();
                                        const rowData = row.getData();
                                        rowData.pls_status = 'error';
                                        row.update(rowData);
                                        
                                        $btn.prop('disabled', false);
                                        $btn.html('<i class="fas fa-times" style="color: #dc3545;"></i>');
                                        showToast(`PLS push failed: ${errorMsg}`, 'error');
                                        return;
                                    }
                                    
                                    // Success - update row data with pushed status
                                    const row = cell.getRow();
                                    const rowData = row.getData();
                                    const plsPush = response.pls_push || {};
                                    rowData.pls_status = (plsPush.ok) ? 'pushed' : 'error';
                                    row.update(rowData);
                                    
                                    $btn.prop('disabled', false);
                                    if (plsPush.ok) {
                                        $btn.html('<i class="fas fa-check-double" style="color: #28a745;"></i>');
                                        const msg = plsPush.message || 'Price pushed to PLS successfully';
                                        showToast(`${msg} for SKU: ${sku}`, 'success');
                                    } else {
                                        $btn.html('<i class="fas fa-times" style="color: #dc3545;"></i>');
                                        const msg = plsPush.message || 'PLS push failed';
                                        showToast(msg, 'error');
                                    }
                                    
                                    // Update summary
                                    updateSummary();
                                },
                                error: function(xhr) {
                                    const row = cell.getRow();
                                    const rowData = row.getData();
                                    rowData.pls_status = 'error';
                                    row.update(rowData);
                                    
                                    $btn.prop('disabled', false);
                                    $btn.html('<i class="fas fa-times" style="color: #dc3545;"></i>');
                                    
                                    const errorMsg = xhr.responseJSON?.message || xhr.responseJSON?.errors?.[0]?.message || 'Unknown error';
                                    showToast(`PLS push failed: ${errorMsg}`, 'error');
                                    
                                    // Update summary
                                    updateSummary();
                                }
                            });
                        }
                    },
                    width: 60
                },
            ]
        });

        // Copy SKU functionality
        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(function() {
                showToast(`SKU "${sku}" copied to clipboard`, 'success');
            }).catch(function() {
                showToast('Failed to copy SKU', 'error');
            });
        });

        function updateSummary() {
            const data = table.getData('active');
            let totalProducts = data.length;
            let totalInventory = 0;
            let totalPlsL30 = 0;
            let totalPrice = 0;
            let priceCount = 0;
            let missingPrice = 0;
            let notMappedCount = 0;
            let totalSales = 0;
            let totalProfit = 0;
            let totalCogs = 0;

            data.forEach(row => {
                const inv = parseInt(row.inventory) || 0;
                const l30 = parseInt(row.l30) || 0;
                const plsL30 = parseInt(row.pls_l30) || 0;
                const price = parseFloat(row.price) || 0;
                const gpft = parseFloat(row.gpft_pct) || 0;
                const roi = parseFloat(row.roi_pct) || 0;
                const plsInv = parseInt(row.pls_inventory) || 0;
                const missing = row.missing || '';
                const lp = parseFloat(row.lp) || 0;
                const ship = parseFloat(row.ship) || 0;

                totalInventory += inv;
                totalPlsL30 += plsL30;

                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                } else if (inv > 0) {
                    missingPrice++;
                }

                // Calculate weighted GPFT and ROI (by sales volume, using dynamic marketplace percentage)
                if (plsL30 > 0 && price > 0) {
                    const sales = price * plsL30;
                    const profit = ((price * PLS_PERCENTAGE) - lp - ship) * plsL30;
                    const cogs = lp * plsL30;
                    
                    totalSales += sales;
                    totalProfit += profit;
                    totalCogs += cogs;
                }
                
                // Count N MP (Not Mapped) - same logic as MAP column formatter
                if (missing !== 'M') {
                    if (inv > 0 && plsInv === 0 && inv > 3) {
                        notMappedCount++;
                    } else if (inv > 0 && plsInv > 0) {
                        if (inv !== plsInv && Math.abs(inv - plsInv) > 3) {
                            notMappedCount++;
                        }
                    }
                }
            });

            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgGpft = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
            const avgRoi = totalCogs > 0 ? (totalProfit / totalCogs) * 100 : 0;

            $('#total-l30-badge').text(`PLS L30: ${totalPlsL30.toLocaleString()}`);
            $('#avg-price-badge').text(`Price: $${avgPrice.toFixed(2)}`);
            $('#avg-gpft-badge').text(`GPFT%: ${avgGpft.toFixed(1)}%`);
            $('#avg-roi-badge').text(`ROI%: ${avgRoi.toFixed(0)}%`);
            $('#missing-price-badge').text(`Missing: ${missingPrice}`);
            $('#not-mapped-badge').text(`N MP: ${notMappedCount}`);
        }

        table.on('dataLoaded', function () { setTimeout(updateSummary, 100); });
        table.on('dataFiltered', function () { setTimeout(updateSummary, 100); });
        table.on('renderComplete', function () { setTimeout(updateSummary, 100); });

        // Cell edited event for SPRICE
        table.on('cellEdited', function(cell) {
            const field = cell.getField();
            const row = cell.getRow();
            const data = row.getData();
            
            if (field === 'sprice') {
                const sku = data.sku;
                const value = parseFloat(cell.getValue());
                
                if (!sku || !value || value <= 0) {
                    showToast('error', 'Invalid SPRICE value');
                    return;
                }
                
                // Save SPRICE to server
                $.ajax({
                    url: '/save-pls-sprice',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        sprice: value
                    },
                    success: function(response) {
                        showToast('success', 'SPRICE updated successfully');
                        // Update SGPFT% and SROI% from response
                        const updates = { 'sprice': response.data || value };
                        if (response.sgpft_percent !== undefined) {
                            updates['sgpft'] = response.sgpft_percent;
                        }
                        if (response.sroi_percent !== undefined) {
                            updates['sroi'] = response.sroi_percent;
                        }
                        if (response.has_custom_sprice !== undefined) {
                            updates['has_custom_sprice'] = response.has_custom_sprice;
                        }
                        row.update(updates);
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to update SPRICE');
                        console.error('SPRICE save error:', xhr);
                    }
                });
            }
        });

        // Toast notification function
        // Badge click filters
        $('#missing-price-badge').on('click', function() {
            table.clearFilter();
            table.addFilter(function(data) {
                return parseFloat(data.price) === 0 && parseInt(data.inventory) > 0;
            });
            updateSummary();
        });
        
        $('#not-mapped-badge').on('click', function() {
            table.clearFilter();
            table.addFilter(function(data) {
                const inv = parseInt(data.inventory) || 0;
                const plsInv = parseInt(data.pls_inventory) || 0;
                const missing = data.missing || '';
                
                // Show N MP rows - same logic as MAP column
                if (missing === 'M') return false;
                
                if (inv > 0 && plsInv === 0 && inv > 3) return true;
                if (inv > 0 && plsInv > 0 && inv !== plsInv && Math.abs(inv - plsInv) > 3) return true;
                
                return false;
            });
            updateSummary();
        });

        // Column dropdown
        function buildColumnDropdown() {
            let html = '';
            table.getColumns().forEach(col => {
                const field = col.getField(), title = col.getDefinition().title;
                if (field && title) {
                    html += `<li class="dropdown-item"><label style="cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" class="column-toggle" data-field="${field}" ${col.isVisible() ? 'checked' : ''}>
                        ${title.replace(/<[^>]*>/g, '')}
                    </label></li>`;
                }
            });
            $('#column-dropdown-menu').html(html);
        }

        table.on('tableBuilt', buildColumnDropdown);

        document.getElementById('column-dropdown-menu').addEventListener('change', function (e) {
            if (e.target.classList.contains('column-toggle')) {
                const col = table.getColumn(e.target.dataset.field);
                if (col) e.target.checked ? col.show() : col.hide();
            }
        });

        document.getElementById('show-all-columns-btn').addEventListener('click', function () {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
        });

        // Export CSV
        document.getElementById('export-btn').addEventListener('click', function () {
            const visibleCols = table.getColumns().filter(c => c.isVisible());
            const headers = visibleCols.map(c => c.getDefinition().title || c.getField()).map(h => h.replace(/<[^>]*>/g, ''));
            const rows = table.getData('active').map(row =>
                visibleCols.map(col => {
                    let v = row[col.getField()];
                    if (v === null || v === undefined) return '';
                    if (typeof v === 'number') return parseFloat(v.toFixed(2));
                    if (typeof v === 'string' && (v.includes(',') || v.includes('"')))
                        return '"' + v.replace(/"/g, '""') + '"';
                    return v;
                })
            );
            const csv = [headers, ...rows].map(r => r.join(',')).join('\n');
            const link = document.createElement('a');
            link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
            link.download = 'pls_pricing_' + new Date().toISOString().slice(0, 10) + '.csv';
            link.style.visibility = 'hidden';
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            showToast('Export downloaded!', 'success');
        });
    });
</script>
@endsection
