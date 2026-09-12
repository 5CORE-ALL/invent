@extends('layouts.vertical', ['title' => 'Temu 2 Daily Data', 'sidenav' => 'condensed'])

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
        'page_title' => 'Temu 2 Daily Data',
        'sub_title' => 'Temu 2 orders from Open API',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Temu 2 Daily Data</h4>
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
                    <a href="{{ route('temu2.decrease') }}" class="btn btn-sm btn-outline-primary" title="View Temu 2 pricing (DIL%, CVR, orders from temu2_orders)">
                        <i class="fa fa-chart-line"></i> Temu Analytics
                    </a>
                    <a href="{{ route('newtemutwo.index') }}" class="btn btn-sm btn-outline-primary" title="New Temu Two — same methods as New Temu One, Temu 2 listings">
                        <i class="fa fa-chart-line"></i> New Temu Two
                    </a>
                </div>

                <!-- Summary Stats (same badges as Temu) -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge fs-6 p-2" id="y-sales-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;"
                            title="Yesterday's Temu 2 sales from bg.order.amount.query (base + freight) — same definition as /temu-tabulator Y Sales.">Y Sales: ${{ number_format((float) ($temu2YSales ?? 0), 0) }}</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge"
                            style="color: white; font-weight: bold;"
                            title="GPFT % = Σ GPFT$ ÷ Σ (Temu Price × Qty) × 100">GPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge"
                            style="background-color: purple; color: white; font-weight: bold;"
                            title="GROI % = Σ GPFT$ ÷ Σ (LP × Qty) × 100">GROI: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;"
                            title="GPFT$ = Σ (R Price × margin − LP − Temu Ship) × Qty">GPFT$: $0</span>
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
                <div id="temu2-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU & Parent Search -->
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU..." style="max-width: 220px;">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="temu2-table" style="flex: 1;"></div>
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
    /** Temu 2 Base = stored/API unit as-is (do not subtract $2.99). */
    function temuGoodsBase(rawUnit) {
        const b = parseFloat(rawUnit) || 0;
        if (b <= 0) return 0;
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
    function temuPriceHoverText(row) {
        const base = temuRowBase(row);
        if (!(base > 0)) {
            return 'Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99';
        }
        const afterMult = +(base * TEMU_PRICE_MULT).toFixed(4);
        const temuPrice = temuPriceFromBase(base);
        if (afterMult <= TEMU_FREIGHT_CAP) {
            return 'Temu Price = (Base × 1.1364) + $2.99\n$'
                + base.toFixed(2) + ' × 1.1364 = $' + afterMult.toFixed(2)
                + ' + $2.99 = $' + temuPrice.toFixed(2);
        }
        return 'Temu Price = (Base × 1.1364)\n$'
            + base.toFixed(2) + ' × 1.1364 = $' + temuPrice.toFixed(2)
            + ' (no +$2.99, result > $26.99)';
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
    function temuRowRPrice(row) {
        const qty = parseInt(row && row.quantity_purchased) || 0;
        return temuFbPrice(temuRowBase(row), qty);
    }
    /** Per-unit profit on R Price — GPFT$ / GPFT % / GROI %. */
    function temuRowRPriceProfit(row) {
        const rPrice = temuRowRPrice(row);
        if (!(rPrice > 0)) return 0;
        const lp = parseFloat(row && row.lp) || 0;
        const ship = parseFloat(row && row.temu_ship) || 0;
        return rPrice * TEMU_MARGIN - lp - ship;
    }
    function temuRowGpftPercent(row) {
        const temuPrice = temuRowTemuPrice(row);
        if (!(temuPrice > 0)) return 0;
        return (temuRowRPriceProfit(row) / temuPrice) * 100;
    }
    function temuRowGroiPercent(row) {
        const lp = parseFloat(row && row.lp) || 0;
        if (!(lp > 0)) return 0;
        return (temuRowRPriceProfit(row) / lp) * 100;
    }
    const COLUMN_VIS_KEY = "temu2_tabulator_column_visibility";
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
        
        // Initialize Tabulator (Temu 2 data: temu2_orders API)
        console.log("Initializing Tabulator for Temu 2 Daily Data...");
        table = new Tabulator("#temu2-table", {
            ajaxURL: "/temu2/daily-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            tooltip: true,
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
                    width: 150
                },
                {
                    title: "Parent Order SN",
                    field: "parent_order_sn",
                    width: 180,
                    frozen: true
                },
                {
                    title: "Order SN",
                    field: "order_sn",
                    width: 170
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
                { title: "Ext Code", field: "ext_code", width: 140 },
                { title: "Display SKU", field: "display_sku", width: 140 },
                { title: "SKU ID", field: "sku_id", width: 140 },
                { title: "Goods ID", field: "goods_id", width: 150 },
                { title: "Product SKU ID", field: "product_sku_id", width: 140 },
                { title: "Goods Name", field: "goods_name", width: 280, tooltip: true },
                { title: "Spec", field: "spec", width: 140, tooltip: true },
                { title: "Qty", field: "quantity", hozAlign: "center", sorter: "number", width: 80 },
                { title: "Original Qty", field: "original_order_quantity", hozAlign: "center", sorter: "number", width: 110 },
                { title: "Canceled Qty", field: "canceled_quantity_before_shipment", hozAlign: "center", sorter: "number", width: 110 },
                {
                    title: "Order Base Amt",
                    field: "order_base_amount",
                    hozAlign: "right",
                    sorter: "number",
                    width: 130,
                    headerTooltip: "Raw bg.order.amount.query basePrice stored on temu2_orders",
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
                },
                {
                    title: "Order Total Amt",
                    field: "order_total_amount",
                    hozAlign: "right",
                    sorter: "number",
                    width: 130,
                    headerTooltip: "Raw bg.order.amount.query total stored on temu2_orders",
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
                },
                {
                    title: "Listing Base",
                    field: "listing_base_price",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    headerTooltip: "Catalog base from bg.local.goods.sku.list.price.query (temu2_metrics)",
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
                },
                {
                    title: "Line Sales",
                    field: "line_sales",
                    hozAlign: "right",
                    sorter: "number",
                    width: 110,
                    headerTooltip: "API line sales = basePrice + shipAmountTotal",
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
                },
                { title: "Status Code", field: "order_status_code", hozAlign: "center", width: 100 },
                {
                    title: "Status Text",
                    field: "order_status_text",
                    width: 130,
                    formatter: function(cell) {
                        const value = cell.getValue() || cell.getRow().getData().order_status;
                        if (!value) return '';
                        let color = 'secondary';
                        const lower = String(value).toLowerCase();
                        if (lower.includes('delivered')) color = 'success';
                        else if (lower.includes('shipped')) color = 'info';
                        else if (lower.includes('cancel')) color = 'danger';
                        else if (lower.includes('pending')) color = 'warning';
                        return `<span class="badge bg-${color}">${value}</span>`;
                    }
                },
                { title: "Parent Status", field: "parent_order_status", hozAlign: "center", width: 110 },
                { title: "Parent Status Text", field: "parent_order_status_text", width: 150 },
                { title: "Fulfillment Type", field: "fulfillment_type", width: 140 },
                { title: "Payment Type", field: "order_payment_type", width: 130 },
                { title: "Region ID", field: "region_id", hozAlign: "center", width: 90 },
                { title: "Site ID", field: "site_id", hozAlign: "center", width: 80 },
                { title: "Parent Order Time", field: "parent_order_time", width: 160 },
                { title: "Expect Ship Latest", field: "expect_ship_latest_time", width: 160 },
                { title: "Parent Shipping Time", field: "parent_shipping_time", width: 160 },
                { title: "Latest Delivery", field: "latest_delivery_time", width: 160 },
                { title: "Order Update Time", field: "order_update_time", width: 160 },
                { title: "Order Shipping Time", field: "order_shipping_time", width: 160 },
                { title: "Tracking", field: "tracking_number", width: 150 },
                { title: "Carrier", field: "carrier", width: 120 },
                { title: "Package SN", field: "package_sn", width: 140 },
                { title: "Tracking Fetched", field: "tracking_fetched_at", width: 160 },
                { title: "Amount Fetched", field: "amount_fetched_at", width: 160 },
                { title: "Fetch Window", field: "fetch_window", width: 110 },
                { title: "Fetched At", field: "fetched_at", width: 160 },
                { title: "Import Status", field: "import_status", width: 120 },
                { title: "Shopify Order ID", field: "shopify_order_id", width: 150 },
                { title: "Pushed Shopify", field: "pushed_to_shopify_at", width: 160 },
                {
                    title: "Thumb",
                    field: "thumb_url",
                    width: 70,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const url = cell.getValue();
                        if (!url) return '';
                        return `<img src="${url}" alt="" style="height:36px;width:36px;object-fit:cover;border-radius:4px;">`;
                    }
                },
                {
                    title: "Base Price",
                    field: "base_price_total",
                    hozAlign: "right",
                    sorter: function(a, b) {
                        return temuGoodsBase(a) - temuGoodsBase(b);
                    },
                    width: 120,
                    headerTooltip: "Base = stored/API unit. No −$2.99 (Temu 2 keeps the unit as-is).",
                    accessorDownload: function(value) {
                        const n = temuGoodsBase(value);
                        return n > 0 ? n.toFixed(2) : '';
                    },
                    formatter: function(cell) {
                        const raw = parseFloat(cell.getValue()) || 0;
                        const base = temuGoodsBase(raw);
                        if (!(base > 0)) return '';
                        return `<span title="Base = stored/API unit (no −$2.99)">$${base.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "R Price",
                    field: "fb_price",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    headerTooltip: "R Price = Base; +$2.99 if Base ≤ $26.99",
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        return temuFbPrice(temuRowBase(data), quantity).toFixed(2);
                    },
                    tooltip: function(e, cell) {
                        const data = cell.getRow().getData();
                        const base = temuRowBase(data);
                        const rPrice = parseFloat(cell.getValue()) || 0;
                        if (!(rPrice > 0) || !(base > 0)) return '';
                        if (base <= TEMU_FREIGHT_CAP) {
                            return 'R Price = Base + $2.99\n$' + base.toFixed(2) + ' + $2.99 = $' + rPrice.toFixed(2);
                        }
                        return 'R Price = Base (no +$2.99, base > $26.99)\n$' + base.toFixed(2);
                    },
                    formatter: function(cell) {
                        const data = cell.getRow().getData();
                        const base = temuRowBase(data);
                        const rPrice = parseFloat(cell.getValue()) || 0;
                        if (!(rPrice > 0)) return '';
                        const tip = base <= TEMU_FREIGHT_CAP
                            ? ('R Price = Base + $2.99 → $' + base.toFixed(2) + ' + $2.99 = $' + rPrice.toFixed(2))
                            : ('R Price = Base (no +$2.99, base > $26.99) → $' + base.toFixed(2));
                        return `<span title="${tip}">$${rPrice.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "Temu Price",
                    field: "temu_price",
                    hozAlign: "right",
                    sorter: "number",
                    width: 130,
                    visible: true,
                    headerTooltip: "Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99",
                    mutator: function(value, data) {
                        return temuPriceFromBase(temuRowBase(data));
                    },
                    tooltip: function(e, cell) {
                        return temuPriceHoverText(cell.getRow().getData());
                    },
                    formatter: function(cell) {
                        const data = cell.getRow().getData();
                        const temuPrice = temuRowTemuPrice(data);
                        if (!(temuPrice > 0)) return '';
                        return `<span title="${temuPriceHoverText(data).replace(/\n/g, ' — ')}">$${temuPrice.toFixed(2)}</span>`;
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
                    title: "GPFT$",
                    field: "pft",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${parseFloat(value).toFixed(2)}</span>`;
                    },
                    headerTooltip: "PFT $ = (R Price × margin − LP − Temu Ship) × Qty",
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        return (temuRowRPriceProfit(data) * quantity).toFixed(2);
                    }
                },
                {
                    title: "GPFT %",
                    field: "gpft_percent",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    headerTooltip: "GPFT % = GPFT$ ÷ Temu Price × 100",
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
                    headerTooltip: "GROI % = GPFT$ ÷ LP × 100",
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
                    totalPft += temuRowRPriceProfit(row) * quantity;
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
            $('#pft-total-badge').text('GPFT$: $' + Math.round(totalPft).toLocaleString());
            $('#pft-total-badge').toggleClass('bg-danger', totalPft < 0).toggleClass('bg-dark', totalPft >= 0);
            $('#l30-sales-badge').text('L30 Sales: $' + Math.round(totalL30Sales).toLocaleString());
            $('#temu-full-price-sales-badge').text('Temu Full Price Sales: $' + Math.round(totalTemuFullPriceSales).toLocaleString());
            $('#total-cogs-badge').text('Total COGS: $' + Math.round(totalCogs).toLocaleString());
        }

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';
            fetch('/temu2-column-visibility', { method: 'GET', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
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
            fetch('/temu2-column-visibility', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ visibility: visibility })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/temu2-column-visibility', { method: 'GET', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field === 'temu_price') {
                            col.show();
                            return;
                        }
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
            table.download("csv", "temu2_l30_daily_data.csv");
        });

        // Export L7 Data - fetches from L7 endpoint and downloads as CSV
        $(document).on('click', '.export-l7', function(e) {
            e.preventDefault();
            const $loading = $('#export-loading');
            $loading.show();
            $.ajax({
                url: '{{ url("/temu2/daily-data-l7") }}',
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                success: function(data) {
                    if (!Array.isArray(data)) {
                        showToast('Invalid response from L7 endpoint', 'error');
                        return;
                    }
                    const columns = table.getColumns().map(function(col) {
                        return col.getField();
                    }).filter(Boolean);
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
                            pft: (temuRowRPriceProfit(row) * qty).toFixed(2),
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
                    link.download = 'temu2_l7_daily_data.csv';
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
    });
</script>
@endsection
