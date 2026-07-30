@extends('layouts.vertical', ['title' => 'Shein Daily Data', 'sidenav' => 'condensed'])

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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shein Daily Data',
        'sub_title' => 'Orders from Shein Open API (shein_daily_data)',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Shein Daily Data</h4>
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

                    <button type="button" class="btn btn-sm btn-primary" id="sync-orders-l30-btn" title="Pull last 30 days from Shein API">
                        <i class="fa fa-cloud-download-alt"></i> Sync L30 (API)
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="sync-orders-l60-btn" title="Pull last 60 days from Shein API">
                        <i class="fa fa-cloud-download-alt"></i> Sync L60 (API)
                    </button>

                    <button type="button" class="btn btn-sm btn-info" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics (API orders)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-revenue-badge" style="color: white; font-weight: bold;">Total Revenue: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;">PFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;">PFT Total: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-commission-badge" style="color: white; font-weight: bold;">Commission: $0.00</span>
                    </div>
                    <h6 class="mb-2 mt-3">L60 Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge fs-6 p-2" id="l60-sales-badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: bold;">
                            <i class="fa fa-chart-line"></i> L60 Sales: $0.00
                        </span>
                        <span class="badge fs-6 p-2" id="l60-orders-badge" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; font-weight: bold;">
                            <i class="fa fa-shopping-cart"></i> L60 Orders: 0
                        </span>
                        <span class="badge fs-6 p-2" id="l60-quantity-badge" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; font-weight: bold;">
                            <i class="fa fa-box"></i> L60 Quantity: 0
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="shein-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU & Parent Search -->
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU..." style="max-width: 220px;">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="shein-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "shein_tabulator_column_visibility";
    let table = null;
    /** Shein keep-rate from marketplace_percentages (percentage / 100), set from /shein/daily-data */
    let sheinMarketplaceMarginDecimal = 1;
    
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
        
        // Load L60 sales statistics
        function loadL60Sales() {
            $.ajax({
                url: '/shein/l60-sales',
                type: 'GET',
                success: function(response) {
                    if (response.success && response.data) {
                        const data = response.data;
                        $('#l60-sales-badge').html(`<i class="fa fa-chart-line"></i> L60 Sales: $${parseFloat(data.total_sales).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`);
                        $('#l60-orders-badge').html(`<i class="fa fa-shopping-cart"></i> L60 Orders: ${parseInt(data.total_orders).toLocaleString()}`);
                        $('#l60-quantity-badge').html(`<i class="fa fa-box"></i> L60 Quantity: ${parseInt(data.total_quantity).toLocaleString()}`);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading L60 sales:', error);
                }
            });
        }

        // Load L60 sales on page load
        loadL60Sales();
        
        // Initialize Tabulator
        console.log("Initializing Tabulator for Shein Daily Data...");
        table = new Tabulator("#shein-table", {
            ajaxURL: "/shein/daily-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                console.log("AJAX Response received:", response && response.source, response && (response.data || response).length);
                let rows = [];
                if (response && Array.isArray(response.data)) {
                    rows = response.data;
                } else if (response && response.data && typeof response.data === 'object') {
                    rows = Object.values(response.data);
                } else if (Array.isArray(response)) {
                    rows = response;
                }
                const m = parseFloat(response && response.marketplace_margin_decimal);
                sheinMarketplaceMarginDecimal = Number.isFinite(m) ? m : 1;
                return rows;
            },
            ajaxError: function(error) {
                console.error("AJAX Error:", error);
                showToast("Error loading data: " + (error.message || "Unknown error"), "error");
            },
            dataLoaded: function(data) {
                console.log("Data loaded:", data.length, "rows");
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
            initialSort: [{
                column: "order_processed_on",
                dir: "desc"
            }],
            columns: [
                {
                    title: "Order Number",
                    field: "order_number",
                    width: 180,
                    frozen: true,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search..."
                },
                {
                    title: "Seller SKU",
                    field: "seller_sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    width: 150,
                    frozen: true,
                    cssClass: "text-primary fw-bold"
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Ship",
                    field: "ship",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "COGS",
                    field: "cogs",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    mutator: function(value, data, type, params, component) {
                        const quantity = parseInt(data.quantity) || 0;
                        const lp = parseFloat(data.lp) || 0;
                        const cogs = quantity * lp;
                        return cogs.toFixed(2);
                    }
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
                    mutator: function(value, data, type, params, component) {
                        const productPrice = parseFloat(data.product_price) || 0;
                        const quantity = parseInt(data.quantity, 10) || 1;
                        const lp = parseFloat(data.lp) || 0;
                        const ship = parseFloat(data.ship) || 0;

                        const m = sheinMarketplaceMarginDecimal;
                        const pft = (productPrice * m - lp - ship) * quantity;
                        return pft.toFixed(2);
                    }
                },
                {
                    title: "Order Type",
                    field: "order_type",
                    width: 120
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
                    title: "Quantity",
                    field: "quantity",
                    hozAlign: "center",
                    sorter: "number",
                    width: 100
                },
                {
                    title: "Product Name",
                    field: "product_name",
                    width: 300,
                    tooltip: true
                },
                {
                    title: "Product Description",
                    field: "product_description",
                    width: 250,
                    tooltip: true
                },
                {
                    title: "Specification",
                    field: "specification",
                    width: 150
                },
                {
                    title: "Shein SKU",
                    field: "shein_sku",
                    width: 130
                },
                {
                    title: "Product Price",
                    field: "product_price",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Coupon Discount",
                    field: "coupon_discount",
                    hozAlign: "right",
                    sorter: "number",
                    width: 130,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Store Campaign",
                    field: "store_campaign_discount",
                    hozAlign: "right",
                    sorter: "number",
                    width: 130,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Referral Fees",
                    field: "commission",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Est. Revenue",
                    field: "estimated_merchandise_revenue",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Fulfillment Fee",
                    field: "fulfillment_service_fee",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Storage Fee",
                    field: "storage_fee",
                    hozAlign: "right",
                    sorter: "number",
                    width: 110,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Sales Tax",
                    field: "consumption_tax",
                    hozAlign: "right",
                    sorter: "number",
                    width: 130,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Shipment Mode",
                    field: "shipment_mode",
                    width: 130
                },
                {
                    title: "Tracking Number",
                    field: "tracking_number",
                    width: 150
                },
                {
                    title: "Seller Package",
                    field: "sellers_package",
                    width: 130
                },
                {
                    title: "Order Processed",
                    field: "order_processed_on",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    }
                },
                {
                    title: "Collection Deadline",
                    field: "collection_deadline",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString();
                    }
                },
                {
                    title: "Req. Ship Time",
                    field: "requested_shipping_time",
                    sorter: "datetime",
                    width: 140,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString();
                    }
                },
                {
                    title: "Delivery Deadline",
                    field: "delivery_deadline",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString();
                    }
                },
                {
                    title: "Delivery Time",
                    field: "delivery_time",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    }
                },
                {
                    title: "Province",
                    field: "province",
                    width: 120
                },
                {
                    title: "City",
                    field: "city",
                    width: 120
                },
                {
                    title: "Exchange Order",
                    field: "exchange_order",
                    width: 130
                },
                {
                    title: "Product Status",
                    field: "product_status",
                    width: 130
                },
                {
                    title: "SKC",
                    field: "skc",
                    width: 100
                },
                {
                    title: "Item ID",
                    field: "item_id",
                    width: 100
                }
            ]
        });

        // SKU Search functionality
        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: 'seller_sku', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
        });

        // Update summary stats — same formulas as /aliexpress-tabulator
        //   Revenue = Σ (product_price × qty)
        //   PFT     = Σ (price × margin − LP − Ship) × qty
        //   COGS    = Σ (LP × qty)
        //   PFT%    = Σ PFT / Σ Revenue × 100
        //   ROI%    = Σ PFT / Σ COGS × 100
        function updateSummary() {
            const data = table.getData("active");
            let totalOrders = 0;
            let totalQuantity = 0;
            let totalRevenue = 0;
            let totalCommission = 0;
            let totalPft = 0;
            let totalWeightedPrice = 0;
            let totalQuantityForPrice = 0;
            let totalCogs = 0;

            data.forEach(row => {
                // Match ChannelMasterController::aggregateSheinDailyDataLikeTabulator — count rows with order_number OR seller_sku
                const orderNum = String(row.order_number ?? '').trim();
                const sellerSku = String(row.seller_sku ?? '').trim();
                if (!orderNum && !sellerSku) {
                    return;
                }

                // Skip refunded / cancelled orders (same spirit as shein pricing salesAgg filters)
                const orderStatus = (row.order_status || '').toLowerCase();
                if (orderStatus.includes('refund') || orderStatus.includes('return') || orderStatus.includes('returned')
                    || orderStatus.includes('cancel') || orderStatus.includes('cancelled') || orderStatus.includes('closed')
                    || orderStatus.includes('exchange')) {
                    return;
                }

                totalOrders++;
                const quantity = parseInt(row.quantity, 10) || 1;
                const productPrice = parseFloat(row.product_price) || 0;
                const lineRevenue = productPrice * quantity;
                const commission = parseFloat(row.commission) || 0;
                const lp = parseFloat(row.lp) || 0;
                const ship = parseFloat(row.ship) || 0;

                totalQuantity += quantity;
                totalRevenue += lineRevenue;
                totalCommission += commission;

                if (quantity > 0 && productPrice > 0) {
                    totalWeightedPrice += productPrice * quantity;
                    totalQuantityForPrice += quantity;
                }

                // Same as AliExpress / ChannelMaster: PFT = (unit × margin − lp − ship) × qty ; COGS = lp × qty
                const m = sheinMarketplaceMarginDecimal;
                const pft = (productPrice * m - lp - ship) * quantity;
                const cogs = lp * quantity;
                totalPft += pft;
                totalCogs += cogs;
            });

            const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
            const pftPercentage = totalRevenue > 0 ? (totalPft / totalRevenue) * 100 : 0;
            const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#total-revenue-badge').text('Total Revenue: $' + totalRevenue.toFixed(2));
            $('#pft-percentage-badge').text('PFT %: ' + Math.round(pftPercentage) + '%');
            $('#roi-percentage-badge').text('ROI %: ' + Math.round(roiPercentage) + '%');
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#pft-total-badge').text('PFT Total: $' + totalPft.toFixed(2));

            const pftBadge = $('#pft-total-badge');
            if (totalPft >= 0) {
                pftBadge.removeClass('bg-danger').addClass('bg-dark');
            } else {
                pftBadge.removeClass('bg-dark').addClass('bg-danger');
            }

            $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));
            $('#total-commission-badge').text('Commission: $' + totalCommission.toFixed(2));
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/shein-column-visibility', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (!def.field) return;

                        const li = document.createElement("li");
                        const label = document.createElement("label");
                        label.style.display = "block";
                        label.style.padding = "5px 10px";
                        label.style.cursor = "pointer";

                        const checkbox = document.createElement("input");
                        checkbox.type = "checkbox";
                        checkbox.value = def.field;
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
                if (def.field) {
                    visibility[def.field] = col.isVisible();
                }
            });

            fetch('/shein-column-visibility', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    visibility: visibility
                })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/shein-column-visibility', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field && savedVisibility[def.field] === false) {
                            col.hide();
                        }
                    });
                });
        }

        // Wait for table to be built
        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
        });

        table.on('dataLoaded', function() {
            updateSummary();
        });

        // Update summary when data changes
        table.on('dataProcessed', function() {
            updateSummary();
        });

        table.on('renderComplete', function() {
            updateSummary();
        });

        // Toggle column from dropdown
        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const field = e.target.value;
                const col = table.getColumn(field);
                if (e.target.checked) {
                    col.show();
                } else {
                    col.hide();
                }
                saveColumnVisibilityToServer();
            }
        });

        // Show All Columns button
        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => {
                col.show();
            });
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Export functionality
        $('#export-btn').on('click', function() {
            table.download("csv", "shein_daily_data.csv");
        });

        // ── Sync L30 / L60 from Shein Open API ──
        function syncSheinOrders(target, btnSelector) {
            const $btn = $(btnSelector);
            const days = target === 'l60' ? 60 : 30;
            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<i class="fa fa-spinner fa-spin"></i> Syncing…');
            showToast('Syncing Shein ' + target.toUpperCase() + ' orders from API…', 'info');

            $.ajax({
                url: '{{ route("shein.sync.orders") }}',
                type: 'POST',
                data: { target: target, days: days, _token: '{{ csrf_token() }}' },
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                success: function(res) {
                    if (res && res.success) {
                        showToast(res.message || 'Orders synced', 'success');
                        if (table) {
                            try {
                                const p = table.replaceData('/shein/daily-data');
                                if (p && typeof p.then === 'function') {
                                    p.then(function() { updateSummary(); }).catch(function() {
                                        table.setData('/shein/daily-data');
                                        setTimeout(updateSummary, 400);
                                    });
                                } else {
                                    table.setData('/shein/daily-data');
                                    setTimeout(updateSummary, 400);
                                }
                            } catch (e) {
                                table.setData('/shein/daily-data');
                            }
                        }
                        loadL60Sales();
                    } else {
                        showToast((res && res.message) || 'Sync failed', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || xhr.statusText || 'Sync failed';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        }

        $('#sync-orders-l30-btn').on('click', function() {
            syncSheinOrders('l30', '#sync-orders-l30-btn');
        });
        $('#sync-orders-l60-btn').on('click', function() {
            syncSheinOrders('l60', '#sync-orders-l60-btn');
        });
    });
</script>
@endsection
