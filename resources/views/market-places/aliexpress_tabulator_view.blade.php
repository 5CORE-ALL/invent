@extends('layouts.vertical', ['title' => 'Aliexpress Daily Data', 'sidenav' => 'condensed'])

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
        'page_title' => 'Aliexpress Daily Data',
        'sub_title' => 'Aliexpress Daily Data Analysis',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Aliexpress Daily Data</h4>
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

                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>
                    <button type="button" class="btn btn-sm btn-info" id="ae-tabulator-sync-orders-btn"
                        title="Pull last 60 days of orders from AliExpress Open Platform. Table uses L30; badges use L60.">
                        <i class="fas fa-cloud-download-alt"></i> Sync Orders
                    </button>
                    <span id="ae-tabulator-sync-status" class="small text-muted"></span>
                </div>

                <div id="summary-stats" class="mt-2 p-2 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;" title="L30 orders from AliExpress API">Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;" title="L30 units from AliExpress API">Qty: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-revenue-badge" style="color: white; font-weight: bold;" title="L30 sales from AliExpress API">Sales: $0</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;" title="L30 GPFT % = PFT ÷ Sales">GPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;" title="L30 GROI % = PFT ÷ COGS">GROI: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;" title="L30 qty-weighted avg unit price">Prc: $0</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;" title="L30 profit">PFT: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;" title="L30 LP × qty">COGS: $0</span>
                        <span class="badge fs-6 p-2" id="l60-sales-badge" style="background-color: #667eea; color: white; font-weight: bold;" title="L60 sales from AliExpress API">L60 Sales: $0</span>
                        <span class="badge fs-6 p-2" id="l60-orders-badge" style="background-color: #f5576c; color: white; font-weight: bold;" title="L60 orders from AliExpress API">L60 Orders: 0</span>
                        <span class="badge fs-6 p-2" id="l60-quantity-badge" style="background-color: #00b4d8; color: white; font-weight: bold;" title="L60 units from AliExpress API">L60 Qty: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="aliexpress-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU & Parent Search -->
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU..." style="max-width: 220px;">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="aliexpress-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    const COLUMN_VIS_KEY = "aliexpress_tabulator_column_visibility";
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

        function money0(n) {
            return '$' + Math.round(parseFloat(n) || 0).toLocaleString();
        }

        function paintTabulatorBadges(l30, l60) {
            l30 = l30 || {};
            l60 = l60 || {};
            $('#total-orders-badge').text('Orders: ' + (parseInt(l30.total_orders, 10) || 0).toLocaleString());
            $('#total-quantity-badge').text('Qty: ' + (parseInt(l30.total_quantity, 10) || 0).toLocaleString());
            $('#total-revenue-badge').text('Sales: ' + money0(l30.total_sales));
            $('#pft-percentage-badge').text('GPFT: ' + Math.round(parseFloat(l30.pft_percentage) || 0) + '%');
            $('#roi-percentage-badge').text('GROI: ' + Math.round(parseFloat(l30.roi_percentage) || 0) + '%');
            $('#avg-price-badge').text('Prc: $' + (parseFloat(l30.avg_price) || 0).toFixed(2));
            const pft = parseFloat(l30.total_pft) || 0;
            $('#pft-total-badge').text('PFT: ' + money0(pft));
            $('#pft-total-badge').toggleClass('bg-danger', pft < 0).toggleClass('bg-dark', pft >= 0);
            $('#total-cogs-badge').text('COGS: ' + money0(l30.total_cogs));
            $('#l60-sales-badge').text('L60 Sales: ' + money0(l60.total_sales));
            $('#l60-orders-badge').text('L60 Orders: ' + (parseInt(l60.total_orders, 10) || 0).toLocaleString());
            $('#l60-quantity-badge').text('L60 Qty: ' + (parseInt(l60.total_quantity, 10) || 0).toLocaleString());
        }

        function loadBadgeStats() {
            $.ajax({
                url: '{{ route("aliexpress.tabulator.badges") }}',
                type: 'GET',
                success: function(response) {
                    if (response && response.success) {
                        paintTabulatorBadges(response.l30, response.l60);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading AliExpress badges:', error);
                }
            });
        }

        loadBadgeStats();

        $('#ae-tabulator-sync-orders-btn').on('click', function() {
            if (!confirm('Sync last 60 days of orders from AliExpress API?\n\nThe table will show L30. L60 badges update from the same pull. This may take several minutes.')) {
                return;
            }
            const $btn = $(this);
            const originalHtml = $btn.html();
            const $status = $('#ae-tabulator-sync-status');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing…');
            $status.removeClass('text-success text-danger').addClass('text-muted').text('Pulling orders from AliExpress…');

            $.ajax({
                url: '{{ route("aliexpress.sync.daily.orders") }}',
                type: 'POST',
                timeout: 0,
                data: {
                    _token: '{{ csrf_token() }}',
                    days: 60
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    const msg = (response && response.message) ? response.message : 'AliExpress orders synced.';
                    if (response && response.success === false) {
                        $status.removeClass('text-muted text-success').addClass('text-danger').text(msg);
                        showToast(msg, 'error');
                        return;
                    }
                    $status.removeClass('text-muted text-danger').addClass('text-success').text(msg);
                    showToast(msg, 'success');
                    if (table) {
                        table.setData('/aliexpress/daily-data');
                    }
                    loadBadgeStats();
                },
                error: function(xhr) {
                    let message = 'AliExpress order sync failed.';
                    const j = xhr.responseJSON;
                    if (j && j.message) message = j.message;
                    else if (xhr.status === 419) message = 'Session expired. Refresh the page and try again.';
                    else if (xhr.status === 0) message = 'Request timed out or network error.';
                    $status.removeClass('text-muted text-success').addClass('text-danger').text(message);
                    showToast(message, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
        
        // Initialize Tabulator
        console.log("Initializing Tabulator for Aliexpress Daily Data...");
        table = new Tabulator("#aliexpress-table", {
            ajaxURL: "/aliexpress/daily-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                console.log("AJAX Response received:", response);
                console.log("Response type:", typeof response);
                console.log("Is array:", Array.isArray(response));
                if (Array.isArray(response)) {
                    console.log("Number of records:", response.length);
                    if (response.length > 0) {
                        console.log("First record:", response[0]);
                    }
                }
                // Return the response as-is (should be an array)
                return response;
            },
            ajaxError: function(error) {
                console.error("AJAX Error:", error);
                console.error("Error details:", JSON.stringify(error));
                showToast("Error loading data: " + (error.message || "Unknown error"), "error");
            },
            dataLoaded: function(data) {
                console.log("Data loaded:", data.length, "rows");
                loadBadgeStats();
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
                column: "order_date",
                dir: "desc"
            }],
            columns: [
                {
                    title: "Order #",
                    field: "order_id",
                    width: 180,
                    frozen: true,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search..."
                },
                {
                    title: "SKU",
                    field: "sku_code",
                    width: 150,
                    frozen: true,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold",
                    formatter: function(cell) {
                        const v = (cell.getValue() || '').toString().trim();
                        return v || '—';
                    }
                },
                {
                    title: "Qty",
                    field: "quantity",
                    width: 70,
                    hozAlign: "center",
                    sorter: "number",
                    bottomCalc: "sum",
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue(), 10);
                        const n = Number.isFinite(v) && v > 0 ? v : 1;
                        return `<span class="fw-bold">${n}</span>`;
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
                    title: "Buyer Name",
                    field: "buyer_name",
                    width: 150
                },
                {
                    title: "Order Date",
                    field: "order_date",
                    width: 150,
                    sorter: "datetime",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    }
                },
                {
                    title: "Product Price",
                    field: "product_total",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Unit Price",
                    field: "unit_price",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Order Amount",
                    field: "order_amount",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Platform Coupon",
                    field: "platform_coupon",
                    width: 120,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "LP",
                    field: "lp",
                    width: 80,
                    hozAlign: "right",
                    sorter: "number",
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
                    width: 80,
                    hozAlign: "right",
                    sorter: "number",
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
                    width: 80,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "PFT Each",
                    field: "pft_each",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${value.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "PFT Each %",
                    field: "pft_each_pct",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${value.toFixed(2)}%</span>`;
                    }
                },
                {
                    title: "T PFT",
                    field: "pft",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${value.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "ROI %",
                    field: "roi",
                    width: 80,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${value.toFixed(2)}%</span>`;
                    }
                },
                // {
                //     title: "Payment Method",
                //     field: "payment_method",
                //     width: 150
                // },
                // {
                //     title: "Buyer Country",
                //     field: "buyer_country",
                //     width: 120
                // },
                // {
                //     title: "State/Province",
                //     field: "state_province",
                //     width: 120
                // },
                // {
                //     title: "City",
                //     field: "city",
                //     width: 120
                // },
                // {
                //     title: "Tracking Number",
                //     field: "tracking_number",
                //     width: 150
                // },
                // {
                //     title: "Shipping Time",
                //     field: "shipping_time",
                //     width: 150,
                //     formatter: function(cell) {
                //         const value = cell.getValue();
                //         if (!value) return '';
                //         const date = new Date(value);
                //         return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                //     }
                // }
            ]
        });

        // SKU Search functionality
        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: 'sku_code', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
        });

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/aliexpress-column-visibility', {
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

            fetch('/aliexpress-column-visibility', {
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
            fetch('/aliexpress-column-visibility', {
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
            table.download("csv", "aliexpress_daily_data.csv");
        });
    });
</script>
@endsection
