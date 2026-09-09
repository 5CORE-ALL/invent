@extends('layouts.vertical', ['title' => 'Temu 3 Sales Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
        /* Vertical column headers */
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

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* Link tooltip styling */
        .link-tooltip {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .link-tooltip a {
            text-decoration: none;
        }

        .link-tooltip a:hover {
            text-decoration: underline;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu 3 Sales Data',
        'sub_title' => 'Same temu3_orders rows as /temu3-decrease',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Temu 3 Sales Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-success dropdown-toggle" type="button"
                            id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-file-excel"></i> Export
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                            <li>
                                <a class="dropdown-item export-l30" href="#" data-action="l30">
                                    <i class="fa fa-download me-1"></i> Export L30 Data
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item export-l7" href="#" data-action="l7">
                                    <i class="fa fa-download me-1"></i> Export L7 Data
                                </a>
                            </li>
                        </ul>
                    </div>
                    <span id="export-loading" class="ms-2" style="display: none;">
                        <span class="spinner-border spinner-border-sm text-success" role="status"></span>
                        <span class="ms-1">Loading L7 data...</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadOrdersModal" title="Upload Temu Seller Center order export (replaces temu3_orders)">
                        <i class="fa fa-upload"></i> Upload Sales
                    </button>
                    <a href="{{ route('temu3.decrease') }}" class="btn btn-sm btn-outline-primary" title="View Temu 3 pricing (DIL%, CVR, orders from temu3_orders)">
                        <i class="fa fa-chart-line"></i> Temu Analytics
                    </a>
                </div>

                <!-- Summary Stats (same badges as Temu) -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge fs-6 p-2" id="y-sales-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;"
                            title="Temu 3 Full Temu Price sales for Pacific yesterday ({{ $temu3YDate ?? 'n/a' }}) from temu3_orders — same source as /temu3-decrease.">Y Sales{{ !empty($temu3YDate) ? ' (' . \Carbon\Carbon::parse($temu3YDate)->format('M j') . ')' : '' }}: ${{ number_format((float) ($temu3YSales ?? 0), 0) }}</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge"
                            style="color: white; font-weight: bold;"
                            title="GPFT % = Σ (Temu Price × margin − LP − Ship) × Qty ÷ Σ (Temu Price × Qty) × 100">GPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge"
                            style="background-color: purple; color: white; font-weight: bold;"
                            title="GROI % = Σ (Temu Price × margin − LP − Ship) × Qty ÷ Σ (LP × Qty) × 100">GROI: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;">PFT Total: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="l30-sales-badge"
                            style="color: white; font-weight: bold;"
                            title="L30 Sales = Σ Temu Price × Qty — Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99">L30 Sales: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="temu-full-price-sales-badge"
                            style="color: white; font-weight: bold;"
                            title="Σ Temu Price × Qty — Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99">Temu Full Price Sales: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="temu3-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU & Parent Search -->
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU..." style="max-width: 220px;">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="temu3-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadOrdersModal" tabindex="-1" aria-labelledby="uploadOrdersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="uploadOrdersModalLabel">
                        <i class="fa fa-upload me-2"></i>Upload Temu 3 Sales
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadOrdersForm" method="POST" action="{{ route('temu3.orders.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="ordersFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Temu Seller Center order export
                            </label>
                            <input type="file" class="form-control" name="orders_file" id="ordersFile"
                                   accept=".xlsx,.xls,.csv,.tsv,.txt" required>
                            <div class="form-text">
                                Accepts .xlsx, .xls, .csv, .tsv, or .txt (Max: 40MB)
                            </div>
                        </div>
                        <div class="alert alert-info mb-2">
                            <strong>Format:</strong> Order ID, order status, contribution sku, SKU ID,
                            quantity purchased, purchase date, <strong>goods base price</strong>, tracking number, …
                            <br>
                            Each upload <strong>replaces</strong> all previous Temu 3 order rows (old orders are truncated).
                            This is the same sales file as <strong>Up Orders</strong> on Temu Analytics.
                            <br>
                            <a href="{{ route('temu3.orders.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                        <div id="ordersUploadResult" class="alert" style="display:none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="startOrdersUploadBtn">
                        <i class="fa fa-upload me-1"></i>Upload Sales
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    // Same margin as /temu-tabulator — marketplace_percentages.Temu (no hardcode)
    const TEMU_MARGIN = {{ (float) $temuMargin }};
    const TEMU_PRICE_MULT = 1.1364;
    const TEMU_FREIGHT = 2.99;
    const TEMU_FREIGHT_CAP = 26.99;
    function temuGoodsBase(rawUnit) {
        const b = parseFloat(rawUnit) || 0;
        if (b <= 0) return 0;
        if (b < TEMU_FREIGHT_CAP) {
            return Math.max(0, +(b - TEMU_FREIGHT).toFixed(2));
        }
        return +b.toFixed(2);
    }
    function temuRowBase(row) {
        return temuGoodsBase(row && row.base_price_total);
    }
    function temuPriceFromBase(basePrice) {
        const b = parseFloat(basePrice) || 0;
        if (b <= 0) return 0;
        let price = b * TEMU_PRICE_MULT;
        if (price <= TEMU_FREIGHT_CAP) price += TEMU_FREIGHT;
        return price;
    }
    function temuFbPrice(basePrice, quantity) {
        const base = parseFloat(basePrice) || 0;
        const qty = parseInt(quantity) || 0;
        if (qty <= 0 || base <= 0) return 0;
        return base <= TEMU_FREIGHT_CAP ? base + TEMU_FREIGHT : base;
    }
    function temuRowTemuPrice(row) {
        return temuPriceFromBase(temuRowBase(row));
    }
    function temuRowTemuProfit(row) {
        const temuPrice = temuRowTemuPrice(row);
        if (!(temuPrice > 0)) return 0;
        const lp = parseFloat(row && row.lp) || 0;
        const ship = parseFloat(row && row.temu_ship) || 0;
        return temuPrice * TEMU_MARGIN - lp - ship;
    }
    function temuRowGpftPercent(row) {
        const temuPrice = temuRowTemuPrice(row);
        if (!(temuPrice > 0)) return 0;
        return (temuRowTemuProfit(row) / temuPrice) * 100;
    }
    function temuRowGroiPercent(row) {
        const lp = parseFloat(row && row.lp) || 0;
        if (!(lp > 0)) return 0;
        return (temuRowTemuProfit(row) / lp) * 100;
    }
    const COLUMN_VIS_KEY = "temu3_tabulator_column_visibility";
    let table = null;
    
    // Toast notification function
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
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
        // Set CSRF token for AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        // Initialize Tabulator from temu3_orders (same source as /temu3-decrease)
        console.log("Initializing Tabulator for Temu 3 orders...");
        table = new Tabulator("#temu3-table", {
            ajaxURL: "/temu3/daily-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                if (Array.isArray(response)) {
                    return response;
                }
                return response;
            },
            ajaxError: function(error) {
                showToast("Error loading data: " + (error.message || "Unknown error"), "error");
            },
            dataLoaded: function(data) {
                updateSummary();
            },
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "Show",
                        "first": "First",
                        "first_title": "First Page",
                        "last": "Last",
                        "last_title": "Last Page",
                        "prev": "Prev",
                        "prev_title": "Prev Page",
                        "next": "Next",
                        "next_title": "Next Page",
                        "counter": {
                            "showing": "Showing",
                            "of": "of",
                            "rows": "rows"
                        }
                    }
                }
            },
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT')) {
                    row.getElement().style.backgroundColor = "#fffef2";
                }
            },
            initialSort: [{
                column: "created_at",
                dir: "desc"
            }],
            columns: [
                {
                    title: "Parent",
                    field: "Parent",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent...",
                    cssClass: "text-primary",
                    tooltip: true,
                    frozen: true,
                    width: 150,
                    visible: false
                },
                {
                    title: "Order ID",
                    field: "order_id",
                    width: 180,
                    frozen: true
                },
                {
                    title: "SKU",
                    field: "contribution_sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    width: 150,
                    frozen: true,
                    cssClass: "text-primary fw-bold",
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const isParent = rowData.Parent && rowData.Parent.startsWith('PARENT');
                        if (isParent) return '';
                        return sku || '';
                    }
                },
                {
                    title: "Product Name",
                    field: "product_name_by_customer_order",
                    width: 300,
                    tooltip: true
                },
                {
                    title: "Variation",
                    field: "variation",
                    width: 120
                },
                {
                    title: "Qty Purchased",
                    field: "quantity_purchased",
                    hozAlign: "center",
                    sorter: "number",
                    width: 120
                },
                {
                    title: "Qty Shipped",
                    field: "quantity_shipped",
                    hozAlign: "center",
                    sorter: "number",
                    width: 120
                },
                {
                    title: "Qty To Ship",
                    field: "quantity_to_ship",
                    hozAlign: "center",
                    sorter: "number",
                    width: 120
                },
                {
                    title: "Base Price",
                    field: "base_price_total",
                    hozAlign: "right",
                    sorter: function(a, b) {
                        return temuGoodsBase(a) - temuGoodsBase(b);
                    },
                    width: 120,
                    headerTooltip: "Base = stored/API unit − $2.99 when that unit is < $26.99. Otherwise stored/API unit.",
                    accessorDownload: function(value) {
                        const n = temuGoodsBase(value);
                        return n > 0 ? n.toFixed(2) : '';
                    },
                    formatter: function(cell) {
                        const raw = parseFloat(cell.getValue()) || 0;
                        const base = temuGoodsBase(raw);
                        if (!(base > 0)) return '';
                        const tip = raw < TEMU_FREIGHT_CAP
                            ? ('$' + raw.toFixed(2) + ' − $2.99 (unit < $26.99)')
                            : ('$' + raw.toFixed(2) + ' (unit ≥ $26.99, no −$2.99)');
                        return `<span title="${tip}">$${base.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "FB Prc",
                    field: "fb_price",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 },
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        return temuFbPrice(temuRowBase(data), quantity).toFixed(2);
                    }
                },
                {
                    title: "Temu Price",
                    field: "temu_price",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    headerTooltip: "Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99",
                    mutator: function(value, data) {
                        return temuRowTemuPrice(data);
                    },
                    formatter: function(cell) {
                        const basePrice = temuRowBase(cell.getRow().getData());
                        const temuPrice = parseFloat(cell.getValue()) || temuPriceFromBase(basePrice);
                        if (!(temuPrice > 0)) return '';
                        const afterMult = basePrice * TEMU_PRICE_MULT;
                        const tip = '(Base × 1.1364)' + (afterMult <= TEMU_FREIGHT_CAP ? ' + $2.99' : '');
                        return `<span title="${tip}">$${temuPrice.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
                },
                {
                    title: "COGS",
                    field: "cogs",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 },
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const lp = parseFloat(data.lp) || 0;
                        return (quantity * lp).toFixed(2);
                    }
                },
                {
                    title: "Hdl Charge",
                    field: "handling_charge",
                    headerTooltip: "Handling Charge saved on Shipping Master (included in Temu Ship)",
                    hozAlign: "right",
                    width: 90
                },
                {
                    title: "O-Size Charge",
                    field: "o_size_charge",
                    headerTooltip: "O-Size Charge saved on Shipping Master (included in Temu Ship)",
                    hozAlign: "right",
                    width: 100
                },
                {
                    title: "Temu Ship",
                    field: "temu_ship",
                    headerTooltip: "Saved Temu ship = slab + Handling Charge + O-Size Charge",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
                },
                {
                    title: "PFT Total",
                    field: "pft",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${parseFloat(value).toFixed(2)}</span>`;
                    },
                    headerTooltip: "PFT $ = (Temu Price × margin − LP − Temu Ship) × Qty",
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        return (temuRowTemuProfit(data) * quantity).toFixed(2);
                    }
                },
                {
                    title: "GPFT %",
                    field: "gpft_percent",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    headerTooltip: "GPFT % = (Temu Price × margin − LP − Temu Ship) ÷ Temu Price × 100",
                    mutator: function(value, data) {
                        return temuRowGpftPercent(data);
                    },
                    formatter: function(cell) {
                        const n = parseFloat(cell.getValue());
                        if (!isFinite(n)) return '';
                        const color = n >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${Math.round(n)}%</span>`;
                    }
                },
                {
                    title: "GROI %",
                    field: "groi_percent",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    headerTooltip: "GROI % = (Temu Price × margin − LP − Temu Ship) ÷ LP × 100",
                    mutator: function(value, data) {
                        return temuRowGroiPercent(data);
                    },
                    formatter: function(cell) {
                        const n = parseFloat(cell.getValue());
                        if (!isFinite(n)) return '';
                        const color = n >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${Math.round(n)}%</span>`;
                    }
                },
                {
                    title: "L30 Sales",
                    field: "l30_sales",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    headerTooltip: "L30 Sales = Temu Price × Qty. Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99",
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 },
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        return (quantity * temuRowTemuPrice(data)).toFixed(2);
                    }
                },
                {
                    title: "Order Status",
                    field: "order_status",
                    width: 120,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        let color = 'secondary';
                        if (value.toLowerCase().includes('delivered')) color = 'success';
                        else if (value.toLowerCase().includes('shipped')) color = 'info';
                        else if (value.toLowerCase().includes('cancelled') || value.toLowerCase().includes('cancel')) color = 'danger';
                        else if (value.toLowerCase().includes('pending')) color = 'warning';
                        return `<span class="badge bg-${color}">${value}</span>`;
                    }
                },
                {
                    title: "Fulfillment",
                    field: "fulfillment_mode",
                    width: 150
                },
                {
                    title: "Tracking",
                    field: "tracking_number",
                    width: 150
                },
                {
                    title: "Carrier",
                    field: "carrier",
                    width: 120
                },
                {
                    title: "Created At",
                    field: "created_at",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    }
                }
            ]
        });

        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: 'contribution_sku', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
        });

        function updateSummary() {
            const data = table.getData("active");
            let totalOrders = 0, totalQuantity = 0, totalPft = 0, totalL30Sales = 0;
            let totalTemuFullPriceSales = 0;
            let totalWeightedPrice = 0, totalQuantityForPrice = 0, totalCogs = 0;

            data.forEach(row => {
                if (row.Parent && row.Parent.startsWith('PARENT')) return;
                if (!row.contribution_sku || row.contribution_sku === '' || !row.order_id || row.order_id === '') return;
                totalOrders++;
                const quantity = parseInt(row.quantity_purchased) || 0;
                const basePrice = temuRowBase(row);
                const temuPrice = temuRowTemuPrice(row);
                const lp = parseFloat(row.lp) || 0;
                totalQuantity += quantity;
                if (quantity > 0 && basePrice > 0) {
                    totalWeightedPrice += basePrice * quantity;
                    totalQuantityForPrice += quantity;
                    const profit = temuRowTemuProfit(row) * quantity;
                    totalPft += profit;
                    totalL30Sales += quantity * temuPrice;
                    totalTemuFullPriceSales += quantity * temuPrice;
                    totalCogs += lp * quantity;
                }
            });

            const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
            const pftPercentage = totalTemuFullPriceSales > 0
                ? (totalPft / totalTemuFullPriceSales) * 100
                : 0;
            const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#pft-percentage-badge').text('GPFT: ' + Math.round(pftPercentage) + '%');
            $('#roi-percentage-badge').text('GROI: ' + Math.round(roiPercentage) + '%');
            $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice).toLocaleString());
            $('#pft-total-badge').text('PFT Total: $' + Math.round(totalPft).toLocaleString());
            $('#pft-total-badge').toggleClass('bg-danger', totalPft < 0).toggleClass('bg-dark', totalPft >= 0);
            $('#l30-sales-badge').text('L30 Sales: $' + Math.round(totalL30Sales).toLocaleString());
            $('#temu-full-price-sales-badge').text('Temu Full Price Sales: $' + Math.round(totalTemuFullPriceSales).toLocaleString());
            $('#total-cogs-badge').text('Total COGS: $' + Math.round(totalCogs).toLocaleString());
        }

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';
            fetch('/temu3-column-visibility', { method: 'GET', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (!def.field) return;
                        const li = document.createElement("li");
                        const label = document.createElement("label");
                        label.style.display = "block"; label.style.padding = "5px 10px"; label.style.cursor = "pointer";
                        const checkbox = document.createElement("input");
                        checkbox.type = "checkbox"; checkbox.value = def.field;
                        checkbox.checked = savedVisibility[def.field] !== false;
                        checkbox.style.marginRight = "8px";
                        label.appendChild(checkbox);
                        label.appendChild(document.createTextNode(def.title));
                        li.appendChild(label);
                        menu.appendChild(li);
                    });
                });
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) visibility[def.field] = col.isVisible();
            });
            fetch('/temu3-column-visibility', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ visibility: visibility })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/temu3-column-visibility', { method: 'GET', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field && savedVisibility[def.field] === false) col.hide();
                    });
                });
        }

        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
        });
        table.on('dataLoaded', updateSummary);
        table.on('dataProcessed', updateSummary);
        table.on('renderComplete', updateSummary);

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const col = table.getColumn(e.target.value);
                e.target.checked ? col.show() : col.hide();
                saveColumnVisibilityToServer();
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Export L30 Data - uses current table data
        $(document).on('click', '.export-l30', function(e) {
            e.preventDefault();
            table.download("csv", "temu3_l30_orders.csv");
        });

        // Export L7 Data - fetches from L7 endpoint and downloads as CSV
        $(document).on('click', '.export-l7', function(e) {
            e.preventDefault();
            const $loading = $('#export-loading');
            $loading.show();
            $.ajax({
                url: '{{ url("/temu3/daily-data-l7") }}',
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                success: function(data) {
                    if (!Array.isArray(data)) {
                        showToast('Invalid response from L7 endpoint', 'error');
                        return;
                    }
                    const columns = ['Parent', 'order_id', 'contribution_sku', 'product_name_by_customer_order', 'variation',
                        'quantity_purchased', 'quantity_shipped', 'quantity_to_ship', 'base_price_total', 'fb_price', 'temu_price',
                        'lp', 'temu_ship', 'pft', 'gpft_percent', 'groi_percent', 'l30_sales', 'order_status', 'fulfillment_mode', 'tracking_number', 'carrier', 'created_at'];
                    const headers = columns.join(',');
                    const escapeCsv = function(val) {
                        if (val === null || val === undefined) return '""';
                        const s = String(val);
                        if (s.includes(',') || s.includes('"') || s.includes('\n')) {
                            return '"' + s.replace(/"/g, '""') + '"';
                        }
                        return '"' + s + '"';
                    };
                    const rows = data.map(function(row) {
                        const qty = parseInt(row.quantity_purchased) || 0;
                        const temuPrice = temuRowTemuPrice(row);
                        const l7Sales = (qty * temuPrice).toFixed(2);
                        const rowData = {
                            ...row,
                            base_price_total: temuRowBase(row),
                            temu_price: temuPrice,
                            pft: (temuRowTemuProfit(row) * qty).toFixed(2),
                            gpft_percent: temuRowGpftPercent(row),
                            groi_percent: temuRowGroiPercent(row),
                            l30_sales: l7Sales
                        };
                        return columns.map(function(col) {
                            return escapeCsv(rowData[col] ?? '');
                        }).join(',');
                    });
                    const csv = [headers, ...rows].join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'temu3_l7_orders.csv';
                    link.click();
                    URL.revokeObjectURL(link.href);
                    showToast('L7 data exported successfully', 'success');
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Failed to fetch L7 data';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $loading.hide();
                }
            });
        });

        $('#startOrdersUploadBtn').on('click', function() {
            const fileInput = document.getElementById('ordersFile');
            const file = fileInput && fileInput.files && fileInput.files[0];
            if (!file) {
                showToast('Choose a Temu 3 order export first', 'error');
                return;
            }
            const $btn = $(this);
            const $result = $('#ordersUploadResult');
            $result.hide().removeClass('alert-success alert-danger');
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Uploading…');

            const fd = new FormData();
            fd.append('orders_file', file);
            fd.append('_token', '{{ csrf_token() }}');

            $.ajax({
                url: '{{ route("temu3.orders.upload") }}',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                timeout: 180000,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                success: function(res) {
                    const msg = (res && res.message) || 'Sales uploaded';
                    $result.addClass(res && res.success === false ? 'alert-danger' : 'alert-success')
                        .text(msg).show();
                    showToast(msg, res && res.success === false ? 'error' : 'success');
                    if (res && res.success !== false) {
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message)
                        || 'Temu 3 sales upload failed';
                    $result.addClass('alert-danger').text(msg).show();
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i>Upload Sales');
                }
            });
        });

        $('#uploadOrdersModal').on('hidden.bs.modal', function() {
            $('#ordersFile').val('');
            $('#ordersUploadResult').hide().removeClass('alert-success alert-danger').text('');
            $('#startOrdersUploadBtn').prop('disabled', false).html('<i class="fa fa-upload me-1"></i>Upload Sales');
        });

    });
</script>
@endsection
